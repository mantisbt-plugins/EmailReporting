<?php

declare(strict_types=1);

require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'user_api.php' );

plugin_require_api( 'core/config_api.php' );
plugin_require_api( 'core/error_api.php' );

class ERP_rule_api extends ERP_ErrorHandling
{
	private bool $_functionality_enabled = FALSE;
	private bool $_perform_actions = FALSE;
	private bool $_mail_debug = FALSE;

	/*
	 * Start processing rules for the triggered event
	 *
	 */
	public function __construct(
		private readonly string $p_event,
		private readonly int $p_bug_id,
		private readonly ?int $p_bugnote_id = NULL,
	)
	{
		$this->_functionality_enabled = (bool) plugin_config_get( 'mail_rule_system' );

		if ( $this->_functionality_enabled == OFF )
		{
			return;
		}

		$this->_mail_debug = (bool) plugin_config_get( 'mail_debug' );

		// We want to perform all actions as the rule system user
		$t_rule_user_id = plugin_config_get( 'rule_user_id' );
		if ( !( user_exists( $t_rule_user_id ) && user_is_enabled( $t_rule_user_id ) ) )
		{
			$this->setError( 'Rule system user not found or not enabled' );
		}
		else
		{
			$t_rule_user_username = user_get_field( $t_rule_user_id, 'username' );
			$t_authattemptresult = auth_attempt_script_login( $t_rule_user_username );

			if ( $t_authattemptresult !== TRUE )
			{
				$this->setError( 'Rule system user could not be used' );
			}
		}

		if ( $this->hasError() )
		{
			while ( $this->hasError() )
			{
				$t_error = (
					'Rule engine: INITIALIZATION' . "\n" .
					'Message: ' . $this->getError()
				);
				plugin_log_event( $t_error );
			}

			return;
		}

		$t_rules = plugin_config_get( 'rules' );

		foreach ( $t_rules AS $t_rule )
		{
			$t_result = $this->process_rule( $t_rule );

			if ( $this->_mail_debug == ON )
			{
				while ( $this->hasError() )
				{
					$t_error = (
						'Rule: ' . $t_rule[ 'description' ] . "\n" .
						'Message: ' . $this->getError()
					);
					plugin_log_event( $t_error );
				}
			}

			$this->clearError();
		}
	}

	/*
	 * Process a single rule
	 *
	 */
	private function process_rule( array $p_rule ): bool
	{
		if ( $p_rule[ 'enabled' ] == OFF )
		{
			if ( $this->_mail_debug == ON )
			{
				$this->setError( 'Rule disabled' );
			}
			return( FALSE );
		}

		if ( !bug_exists( $this->p_bug_id ) )
		{
			$this->setError( 'Bug does not exist' );
			return( FALSE );
		}

		if ( bug_is_readonly( $this->p_bug_id ) )
		{
			$this->setError( 'Bug is readonly' );
			return( FALSE );
		}

		if ( $this->p_bugnote_id !== NULL )
		{
			if ( !bugnote_exists( $this->p_bugnote_id ) )
			{
				$this->setError( 'Bugnote does not exist' );
				return( FALSE );
			}

			// Bugnote by the ERP Rule system user should be ignored
			if ( (int) bugnote_get_field( $this->p_bugnote_id, 'reporter_id' ) === plugin_config_get( 'rule_user_id' ) )
			{
				$this->setError( 'Bugnote by the ERP Rule system user ignored' );
				return( FALSE );
			}
		}

		$t_conditions_check = $this->check_conditions( $p_rule[ 'conditions' ], $p_rule[ 'exceptions' ] );

		if ( !$t_conditions_check )
		{
			return( FALSE );
		}

		$this->_perform_actions = (bool) $t_conditions_check;

		$this->apply_actions( $p_rule[ 'actions' ] );

		$this->_perform_actions = FALSE;

		return( TRUE );
	}

	/*
	 * Check the conditions & exceptions of a rule
	 *
	 * Return value:
	 * TRUE means the conditions match & the exceptions do not match and rule should be executed
	 * FALSE means the conditions do not match or the exceptions match and rule should not be executed
	 */
	private function check_conditions( array $p_conditions, array $p_exceptions ): bool
	{
		// cond_issue_creation_source
		if ( isset( $p_conditions[ 'cond_issue_creation_source' ] ) )
		{
			if ( !in_array( $this->get_event_source(), $p_conditions[ 'cond_issue_creation_source' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_mailbox (ERP only)
		if ( isset( $p_conditions[ 'cond_mailbox' ] ) )
		{
			$t_found_cond_mailbox_match = FALSE;

			$t_current_mailbox_id = $this->get_current_mailbox_id();

			if ( $t_current_mailbox_id !== NULL )
			{
				if ( in_array( (int) $t_current_mailbox_id, $p_conditions[ 'cond_mailbox' ], TRUE ) )
				{
					$t_found_cond_mailbox_match = TRUE;
				}
			}

			if ( $t_found_cond_mailbox_match === FALSE )
			{
				return( FALSE );
			}
		}

		// cond_event_type
		if ( isset( $p_conditions[ 'cond_event_type' ] ) )
		{
			if ( !in_array( $this->p_event, $p_conditions[ 'cond_event_type' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_issue_reporter
		// @TODO Possible future improvement
		// bugnote_get_field( $this->p_bugnote_id, 'reporter_id' )
		if ( isset( $p_conditions[ 'cond_issue_reporter' ] ) )
		{
			if ( !in_array( (int) bug_get_field( $this->p_bug_id, 'reporter_id' ), $p_conditions[ 'cond_issue_reporter' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_issue_handler
		if ( isset( $p_conditions[ 'cond_issue_handler' ] ) )
		{
			if ( !in_array( (int) bug_get_field( $this->p_bug_id, 'handler_id' ), $p_conditions[ 'cond_issue_handler' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_email_from & excep_email_from (ERP only)
		if ( isset( $p_conditions[ 'cond_email_from' ] ) || isset( $p_exceptions[ 'excep_email_from' ] ) )
		{
			$t_found_cond_email_from_match = FALSE;

			$t_from_emailaddress = $this->get_current_from_emailaddress();

			if ( $t_from_emailaddress !== NULL )
			{
				if ( isset( $p_conditions[ 'cond_email_from' ] ) )
				{
					if ( $this->wildcard_match( $p_conditions[ 'cond_email_from' ], $t_from_emailaddress ) )
					{
						$t_found_cond_email_from_match = TRUE;
					}
				}

				if ( isset( $p_exceptions[ 'excep_email_from' ] ) )
				{
					if ( $this->wildcard_match( $p_exceptions[ 'excep_email_from' ], $t_from_emailaddress ) )
					{
						$t_found_cond_email_from_match = FALSE;
					}
				}
			}

			if ( $t_found_cond_email_from_match === FALSE )
			{
				return( FALSE );
			}
		}

		// cond_email_to (ERP only)
		if ( isset( $p_conditions[ 'cond_email_to' ] ) )
		{
			$t_found_cond_email_to_match = FALSE;

			$t_to_emailaddresses = $this->get_current_to_emailaddress();

			if ( $t_to_emailaddresses !== NULL )
			{
				if ( $this->wildcard_match( $p_conditions[ 'cond_email_to' ], $t_to_emailaddresses ) )
				{
					$t_found_cond_email_to_match = TRUE;
				}
			}

			if ( $t_found_cond_email_to_match === FALSE )
			{
				return( FALSE );
			}
		}

		// cond_issue_project
		if ( isset( $p_conditions[ 'cond_issue_project' ] ) )
		{
			if ( !in_array( (int) bug_get_field( $this->p_bug_id, 'project_id' ), $p_conditions[ 'cond_issue_project' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_issue_category
		if ( isset( $p_conditions[ 'cond_issue_category' ] ) )
		{
			if ( !in_array( (int) bug_get_field( $this->p_bug_id, 'category_id' ), $p_conditions[ 'cond_issue_category' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_issue_priority
		if ( isset( $p_conditions[ 'cond_issue_priority' ] ) )
		{
			if ( !in_array( (int) bug_get_field( $this->p_bug_id, 'priority' ), $p_conditions[ 'cond_issue_priority' ], TRUE ) )
			{
				return( FALSE );
			}
		}

		// cond_issue_summary & excep_issue_summary
		if ( isset( $p_conditions[ 'cond_issue_summary' ] ) || isset( $p_exceptions[ 'excep_issue_summary' ] ) )
		{
			if ( isset( $p_conditions[ 'cond_issue_summary' ] ) )
			{
				if ( !$this->wildcard_match( $p_conditions[ 'cond_issue_summary' ], bug_get_field( $this->p_bug_id, 'summary' ) ) )
				{
					return( FALSE );
				}
			}

			if ( isset( $p_exceptions[ 'excep_issue_summary' ] ) )
			{
				if ( $this->wildcard_match( $p_exceptions[ 'excep_issue_summary' ], bug_get_field( $this->p_bug_id, 'summary' ) ) )
				{
					return( FALSE );
				}
			}
		}

		// cond_issuenote_description & excep_issuenote_description
		if ( isset( $p_conditions[ 'cond_issuenote_description' ] ) || isset( $p_exceptions[ 'excep_issuenote_description' ] ) )
		{
			$t_description = ( ( $this->p_event === 'EVENT_BUGNOTE_ADD' ) ? bugnote_get_text( $this->p_bugnote_id ) : bug_get_text_field( $this->p_bug_id, 'description' ) );

			if ( isset( $p_conditions[ 'cond_issuenote_description' ] ) )
			{
				if ( !$this->wildcard_match( $p_conditions[ 'cond_issuenote_description' ], $t_description ) )
				{
					return( FALSE );
				}
			}

			if ( isset( $p_exceptions[ 'excep_issuenote_description' ] ) )
			{
				if ( $this->wildcard_match( $p_exceptions[ 'excep_issuenote_description' ], $t_description ) )
				{
					return( FALSE );
				}
			}
		}

		// cond_issuenote_attachments
		if ( isset( $p_conditions[ 'cond_issuenote_attachments' ] ) )
		{
			$t_has_attachments = $this->has_attachments( $this->p_bug_id, $this->p_bugnote_id );

			if (
				( !$t_has_attachments && $p_conditions[ 'cond_issuenote_attachments' ] === 'has_attachments' ) ||
				( $t_has_attachments && $p_conditions[ 'cond_issuenote_attachments' ] === 'no_attachments' )
			)
			{
				return( FALSE );
			}
		}

		// cond_issuenote_attachment_filename & excep_issuenote_attachment_filename
		if ( isset( $p_conditions[ 'cond_issuenote_attachment_filename' ] ) || isset( $p_exceptions[ 'excep_issuenote_attachment_filename' ] ) )
		{
			if ( isset( $p_conditions[ 'cond_issuenote_attachment_filename' ] ) )
			{
				$t_cond_match = $this->match_attachments_filenames( $p_conditions[ 'cond_issuenote_attachment_filename' ], $this->p_bug_id, $this->p_bugnote_id );
				if ( !$t_cond_match )
				{
					return( FALSE );
				}
			}

			if ( isset( $p_exceptions[ 'excep_issuenote_attachment_filename' ] ) )
			{
				$t_excep_match = $this->match_attachments_filenames( $p_exceptions[ 'excep_issuenote_attachment_filename' ], $this->p_bug_id, $this->p_bugnote_id );
				if ( $t_excep_match )
				{
					return( FALSE );
				}
			}
		}

		return( TRUE );
	}

	/*
	 * Apply the actions of the rule
	 *
	 * Return value:
	 * TRUE or FALSE say whether any actions are applied. It should check current value whether action is needed.
	 */
	private function apply_actions( array $p_actions ): bool
	{
		if ( $this->_perform_actions == OFF )
		{
			return( FALSE );
		}

		// act_issue_project
		if ( isset( $p_actions[ 'act_issue_project' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'project_id' ) !== $p_actions[ 'act_issue_project' ] )
			{
				bug_move( $this->p_bug_id, $p_actions[ 'act_issue_project' ] );
			}
		}

		// act_issue_category
		if ( isset( $p_actions[ 'act_issue_category' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'category_id' ) !== $p_actions[ 'act_issue_category' ] )
			{
				bug_set_field( $this->p_bug_id, 'category_id', $p_actions[ 'act_issue_category' ] );
			}
		}

		// act_issue_severity
		if ( isset( $p_actions[ 'act_issue_severity' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'severity' ) !== $p_actions[ 'act_issue_severity' ] )
			{
				bug_set_field( $this->p_bug_id, 'severity', $p_actions[ 'act_issue_severity' ] );
			}
		}

		// act_issue_status
		if ( isset( $p_actions[ 'act_issue_status' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'status' ) !== $p_actions[ 'act_issue_status' ] )
			{
				bug_set_field( $this->p_bug_id, 'status', $p_actions[ 'act_issue_status' ] );
			}
		}

		// act_issue_view_state
		if ( isset( $p_actions[ 'act_issue_view_state' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'view_state' ) !== $p_actions[ 'act_issue_view_state' ] )
			{
				bug_set_field( $this->p_bug_id, 'view_state', $p_actions[ 'act_issue_view_state' ] );
			}
		}

		// act_issue_reproducibility
		if ( isset( $p_actions[ 'act_issue_reproducibility' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'reproducibility' ) !== $p_actions[ 'act_issue_reproducibility' ] )
			{
				bug_set_field( $this->p_bug_id, 'reproducibility', $p_actions[ 'act_issue_reproducibility' ] );
			}
		}

		// act_issue_reporter
		if ( isset( $p_actions[ 'act_issue_reporter' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'reporter_id' ) !== $p_actions[ 'act_issue_reporter' ] )
			{
				bug_set_field( $this->p_bug_id, 'reporter_id', $p_actions[ 'act_issue_reporter' ] );
			}
		}

		// act_issue_handler
		if ( isset( $p_actions[ 'act_issue_handler' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'handler_id' ) !== $p_actions[ 'act_issue_handler' ] )
			{
				bug_set_field( $this->p_bug_id, 'handler_id', $p_actions[ 'act_issue_handler' ] );
			}
		}

		// act_issue_duedate
		if ( isset( $p_actions[ 'act_issue_duedate' ] ) )
		{
			$t_current_duedate = 1;

			if ( $p_actions[ 'act_issue_duedate' ][ 'modifier' ] === 'current' )
			{
				$t_current_duedate = (int) bug_get_field( $this->p_bug_id, 'due_date' );
			}

			if ( $p_actions[ 'act_issue_duedate' ][ 'modifier' ] === 'now' || $t_current_duedate === 1 )
			{
				$t_current_duedate = time();
			}

			$t_duedate_amount = ( ( isset( $p_actions[ 'act_issue_duedate' ][ 'amount' ] ) ) ? $p_actions[ 'act_issue_duedate' ][ 'amount' ] : 0 );

			$t_new_duedate = $t_current_duedate + ( $t_duedate_amount * 86400 );

			if ( $t_current_duedate !== $t_new_duedate )
			{
				bug_set_field( $this->p_bug_id, 'due_date', $t_new_duedate );
			}
		}

		// act_issue_priority
		if ( isset( $p_actions[ 'act_issue_priority' ] ) )
		{
			if ( (int) bug_get_field( $this->p_bug_id, 'priority' ) !== $p_actions[ 'act_issue_priority' ] )
			{
				bug_set_field( $this->p_bug_id, 'priority', $p_actions[ 'act_issue_priority' ] );
			}
		}

		// act_issue_tag
		if ( isset( $p_actions[ 'act_issue_tag' ] ) )
		{
			foreach ( $p_actions[ 'act_issue_tag' ] AS $t_tag_id )
			{
				if ( tag_exists( $t_tag_id ) )
				{
					if ( !tag_bug_is_attached( $t_tag_id, $this->p_bug_id ) )
					{
						tag_bug_attach( $t_tag_id, $this->p_bug_id );
					}
				}
			}
		}

		// act_issue_custom_fields
		if ( isset( $p_actions[ 'act_issue_custom_fields' ] ) )
		{
			$t_related_custom_field_ids = custom_field_get_linked_ids( bug_get_field( $this->p_bug_id, 'project_id' ) );
			$t_act_issue_custom_fields = array_intersect_key( $p_actions[ 'act_issue_custom_fields' ], array_flip( $t_related_custom_field_ids ) );

			foreach ( $t_act_issue_custom_fields AS $t_cf_id => $t_cf_value )
			{
				if ( custom_field_validate( $t_cf_id, $t_cf_value ) )
				{
					if ( custom_field_get_value( $t_cf_id, $this->p_bug_id ) !== $t_cf_value )
					{
						custom_field_set_value( $t_cf_id, $this->p_bug_id, $t_cf_value );
					}
				}
				else
				{
					$this->setError( 'Customfield has incorrect value: ' . $t_cf_id . ' / ' . $t_cf_value );
				}
			}
		}

		$this->_perform_actions = FALSE;

		return( TRUE );
	}

	/*
	 * Get the source of the triggered event
	 *
	 */
	private function get_event_source(): string
	{
		static $t_previous_plugin = NULL;

		if ( $t_previous_plugin === NULL )
		{
			$t_current_plugin = plugin_get_current();
			plugin_pop_current();
			$t_previous_plugin = plugin_get_current() ?? 'MantisCore';
			plugin_push_current( $t_current_plugin );
		}

		return( $t_previous_plugin );
	}

	/*
	 * Get the current mailbox id being processed by ERP
	 *
	 */
	private function get_current_mailbox_id(): ?int
	{
		$t_current_mailbox_id = NULL;

		$t_mailbox_api_index = ERP_get_mailbox_api_name();

		if ( $this->get_event_source() === plugin_get_current() && isset( $GLOBALS[ $t_mailbox_api_index ] ) && is_object( $GLOBALS[ $t_mailbox_api_index ] ) )
		{
			$t_current_mailbox_id = $GLOBALS[ $t_mailbox_api_index ]->get_current_mailbox_id();
		}

		return( $t_current_mailbox_id );
	}

	/*
	 * Get the current from field of the email being processed by ERP
	 *
	 */
	private function get_current_from_emailaddress(): ?string
	{
		$t_from_emailaddress = NULL;

		$t_current_mailbox_id = $this->get_current_mailbox_id();

		if ( $t_current_mailbox_id !== NULL )
		{
			$t_mailbox_api_index = ERP_get_mailbox_api_name();

			$t_from_emailaddress = $GLOBALS[ $t_mailbox_api_index ]->get_from_emailaddress( $t_current_mailbox_id );
		}

		return( $t_from_emailaddress );
	}

	/*
	 * Get the current to field of the email being processed by ERP
	 *
	 */
	private function get_current_to_emailaddress(): ?string
	{
		$t_to_emailaddress = NULL;

		$t_current_mailbox_id = $this->get_current_mailbox_id();

		if ( $t_current_mailbox_id !== NULL )
		{
			$t_mailbox_api_index = ERP_get_mailbox_api_name();

			$t_to_emailaddress = $GLOBALS[ $t_mailbox_api_index ]->get_to_emailaddress( $t_current_mailbox_id );
		}

		return( $t_to_emailaddress );
	}

	/*
	 * Do a wildcard search using p_needle and p_haystack
	 *
	 * The matching is case insensitive
	 */
	private function wildcard_match( string $p_needle, string|array $p_haystack ): bool
	{
		$p_haystack = (array) $p_haystack;

		$t_regex = preg_quote( $p_needle, '/' );
		$t_regex = str_replace( array( '\*', '\?' ), array( '.*', '.' ), $t_regex );

		foreach ( $p_haystack AS $t_item )
		{
			if ( preg_match( '/\A' . $t_regex . '\z/uis', (string) $t_item ) === 1 )
			{
				return( TRUE );
			}
		}

		return( FALSE );
	}

	/*
	 * Check whether a bug or bugnote has attachments
	 *
	 */
	private function has_attachments( int $p_bug_id, ?int $p_bugnote_id = NULL ): bool
	{
		$t_attachment_rows = bug_get_attachments( $p_bug_id );

		$t_bug_attachments_count = count( $t_attachment_rows );
		if( $t_bug_attachments_count === 0 )
		{
			return( FALSE );
		}

		if ( $p_bugnote_id === NULL )
		{
			return( TRUE );
		}
		else
		{
			foreach ( $t_attachment_rows AS $t_row )
			{
				$t_bugnote_id = (int) $t_row[ 'bugnote_id' ];
				if ( $t_bugnote_id === $p_bugnote_id )
				{
					return( TRUE );
				}
			}
		}

		return( FALSE );
	}

	/*
	 * Check whether a bug or bugnote has a filename match based on p_needle
	 *
	 */
	private function match_attachments_filenames( string $p_needle, int $p_bug_id, ?int $p_bugnote_id = NULL ): bool
	{
		$t_attachment_rows = bug_get_attachments( $p_bug_id );

		$t_bug_attachments_count = count( $t_attachment_rows );
		if( $t_bug_attachments_count === 0 )
		{
			return( FALSE );
		}

		$t_attachment_display_names = array();
		foreach ( $t_attachment_rows AS $t_row )
		{
			if ( $p_bugnote_id === NULL )
			{
				$t_attachment_display_names[] = file_get_display_name( $t_row[ 'filename' ] );
			}
			else
			{
				$t_bugnote_id = (int) $t_row[ 'bugnote_id' ];
				if ( $t_bugnote_id === $p_bugnote_id )
				{
					$t_attachment_display_names[] = file_get_display_name( $t_row[ 'filename' ] );
				}
			}

		}

		$t_match = $this->wildcard_match( $p_needle, $t_attachment_display_names );

		return( $t_match );
	}
}

?>
