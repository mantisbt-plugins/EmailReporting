<?php

declare(strict_types=1);

require_api( 'bug_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'user_api.php' );

plugin_require_api( 'core/error_api.php' );

class ERP_rule_api extends ERP_ErrorHandling
{
	private bool $_functionality_enabled = FALSE;
	private bool $_perform_actions = FALSE;
	private bool $_mail_debug = FALSE;
	private array $_actions_performed = array();

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

		$t_rules = plugin_config_get( 'rules' );

		foreach ( $t_rules AS $t_rule )
		{
			$t_result = $this->process_rule( $t_rule );

			if ( $t_result === FALSE && $this->_mail_debug == ON )
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
		}
//exit;
	}

	private function process_rule( array $p_rule ): bool
	{
//var_dump($p_rule);
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
		}

		$t_condition_check = $this->check_conditions( $p_rule[ 'conditions' ] );
//var_dump($t_condition_check);

		if ( !$t_condition_check )
		{
			return( FALSE );
		}

		$t_exception_check = $this->check_exceptions( $p_rule[ 'exceptions' ] );
//var_dump($t_exception_check);

		if ( !$t_exception_check )
		{
			return( FALSE );
		}

		$this->_perform_actions = (bool) ( $t_condition_check && $t_exception_check ); // @TODO both TRUE check? verify!!!
//var_dump($this->_perform_actions);

		$t_actions_performed = $this->apply_actions( $p_rule[ 'actions' ] );
//var_dump($t_actions_performed);

		$this->write_private_note( $p_rule[ 'add_note_actions' ] );

		return( TRUE );
	}

	private function check_conditions( array $p_conditions ): bool
	{
		if ( isset( $p_conditions[ 'cond_issue_creation_source' ] ) && !in_array( $this->get_event_source(), $p_conditions[ 'cond_issue_creation_source' ], TRUE ) )
		{
			return( FALSE );
		}

		return( TRUE );
	}

	private function check_exceptions( array $p_exceptions ): bool
	{
		
		return( TRUE );
	}

	private function apply_actions( array $p_actions ): bool
	{
		if ( $this->_perform_actions == OFF )
		{
			return( FALSE );
		}

		$t_changes_made = FALSE;

		$t_bug_data = bug_get( $this->p_bug_id, FALSE ); // @TODO $p_get_extended = false Should be decided based on need of rule conditions/exceptions

		$t_related_custom_field_ids = custom_field_get_linked_ids( $t_bug_data->project_id );
		$t_act_issue_custom_fields = array_intersect_key( $p_actions[ 'act_issue_custom_fields' ], array_flip( $t_related_custom_field_ids ) );

		$this->_perform_actions = FALSE;

		return( $t_changes_made );
	}

	/*
	 * 
	 * 
	 * Be careful not to create a infinite loop as bugnote_add triggers the event again.
	 * @TODO improve created note content
	 */
	private function write_private_note( bool|int $p_add_note_actions ): void
	{
		if ( empty( $this->_actions_performed ) )
		{
			return;
		}

		if ( $p_add_note_actions == OFF )
		{
			$this->_actions_performed = array();
			return;
		}

		$t_bugnote_id = bugnote_add( $this->p_bug_id, implode( "\n", $this->_actions_performed ), '0:00', TRUE );

		$this->_actions_performed = array();
	}

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
}

?>
