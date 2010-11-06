<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# Copyright (C) 2007  Rolf Kleef - rolf@drostan.org (IMAP)
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# This page receives an E-Mail via POP3 or IMAP and generates an Report

	require_once( 'bug_api.php' );
	require_once( 'bugnote_api.php' );
	require_once( 'user_api.php' );
	require_once( 'file_api.php' );

	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/custom_file_api.php' );

	require_once( 'Net/POP3.php' );
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Net/IMAP_1.0.3.php' );

	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Mail/Parser.php' );
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Mail/simple_html_dom.php');

class ERP_mailbox_api
{
	private $_functionality_enabled = FALSE;
	private $_test_only = FALSE;

	public $_mailbox = array( 'description' => 'INITIALIZATION PHASE' );

	private $_mailserver = NULL;
	private $_result = FALSE;

	private $_default_ports = array(
		'POP3' => array( 'normal' => 110, 'encrypted' => 995 ),
		'IMAP' => array( 'normal' => 143, 'encrypted' => 993 ),
	);

	private $_validated_email_list = array();

	private $_mail_add_bug_reports;
	private $_mail_add_bugnotes;
	private $_mail_add_complete_email;
	private $_mail_auto_signup;
	private $_mail_bug_priority;
	private $_mail_debug;
	private $_mail_debug_directory;
	private $_mail_delete;
	private $_mail_fallback_mail_reporter;
	private $_mail_fetch_max;
	private $_mail_nodescription;
	private $_mail_nosubject;
	private $_mail_preferred_username;
	private $_mail_remove_mantis_email;
	private $_mail_remove_replies;
	private $_mail_remove_replies_after;
	private $_mail_removed_reply_text;
	private $_mail_reporter_id;
	private $_mail_save_from;
	private $_mail_tmp_directory;
	private $_mail_use_bug_priority;
	private $_mail_use_reporter;

	private $_mp_options = array();

	private $_allow_file_upload;
	private $_bug_resolved_status_threshold;
	private $_email_separator1;
	private $_validate_email;
	private $_login_method;
	private $_use_ldap_email;

	private $_bug_submit_status;
	private $_default_bug_additional_info;
	private $_default_bug_eta;
	private $_default_bug_priority;
	private $_default_bug_projection;
	private $_default_bug_reproducibility;
	private $_default_bug_resolution;
	private $_default_bug_severity;
	private $_default_bug_steps_to_reproduce;
	private $_default_bug_view_status;

	private $_max_file_size;

	# --------------------
	# Retrieve all necessary configuration options
	public function __construct( $p_test_only = FALSE )
	{
		$this->_test_only = $p_test_only;

		$this->_mail_add_bug_reports			= plugin_config_get( 'mail_add_bug_reports' );
		$this->_mail_add_bugnotes				= plugin_config_get( 'mail_add_bugnotes' );
		$this->_mail_add_complete_email			= plugin_config_get( 'mail_add_complete_email' );
		$this->_mail_auto_signup				= plugin_config_get( 'mail_auto_signup' );
		$this->_mail_bug_priority				= plugin_config_get( 'mail_bug_priority' );
		$this->_mail_debug						= plugin_config_get( 'mail_debug' );
		$this->_mail_debug_directory			= plugin_config_get( 'mail_debug_directory' );
		$this->_mail_delete						= plugin_config_get( 'mail_delete' );
		$this->_mail_fallback_mail_reporter		= plugin_config_get( 'mail_fallback_mail_reporter' );
		$this->_mail_fetch_max					= plugin_config_get( 'mail_fetch_max' );
		$this->_mail_nodescription				= plugin_config_get( 'mail_nodescription' );
		$this->_mail_nosubject					= plugin_config_get( 'mail_nosubject' );
		$this->_mail_preferred_username			= plugin_config_get( 'mail_preferred_username' );
		$this->_mail_remove_mantis_email		= plugin_config_get( 'mail_remove_mantis_email' );
		$this->_mail_remove_replies				= plugin_config_get( 'mail_remove_replies' );
		$this->_mail_remove_replies_after		= plugin_config_get( 'mail_remove_replies_after' );
		$this->_mail_removed_reply_text			= plugin_config_get( 'mail_removed_reply_text' );
		$this->_mail_reporter_id				= plugin_config_get( 'mail_reporter_id' );
		$this->_mail_save_from					= plugin_config_get( 'mail_save_from' );
		$this->_mail_tmp_directory				= plugin_config_get( 'mail_tmp_directory' );
		$this->_mail_use_bug_priority			= plugin_config_get( 'mail_use_bug_priority' );
		$this->_mail_use_reporter				= plugin_config_get( 'mail_use_reporter' );

		$this->_mp_options[ 'add_attachments' ]	= config_get( 'allow_file_upload' );
		$this->_mp_options[ 'debug' ]			= $this->_mail_debug;
		$this->_mp_options[ 'encoding' ]		= plugin_config_get( 'mail_encoding' );
		$this->_mp_options[ 'parse_html' ]		= plugin_config_get( 'mail_parse_html' );

		$this->_allow_file_upload				= config_get( 'allow_file_upload' );
		$this->_bug_resolved_status_threshold	= config_get( 'bug_resolved_status_threshold' );
		$this->_email_separator1				= config_get( 'email_separator1' );
		$this->_validate_email					= config_get( 'validate_email' );
		$this->_login_method					= config_get( 'login_method' );
		$this->_use_ldap_email					= config_get( 'use_ldap_email' );

		$this->_bug_submit_status				= config_get( 'bug_submit_status' );
		$this->_default_bug_additional_info		= config_get( 'default_bug_additional_info' );
		$this->_default_bug_eta					= config_get( 'default_bug_eta' );
		$this->_default_bug_priority			= config_get( 'default_bug_priority' );
		$this->_default_bug_projection			= config_get( 'default_bug_projection' );
		$this->_default_bug_reproducibility		= config_get( 'default_bug_reproducibility', 10 );
		$this->_default_bug_resolution			= config_get( 'default_bug_resolution' );
		$this->_default_bug_severity			= config_get( 'default_bug_severity', 50 );
		$this->_default_bug_steps_to_reproduce	= config_get( 'default_bug_steps_to_reproduce' );
		$this->_default_bug_view_status			= config_get( 'default_bug_view_status' );

		$this->_max_file_size					= (int) min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );

		// Do we need to remporarily enable emails on self actions?
		$t_mail_email_receive_own				= plugin_config_get( 'mail_email_receive_own' );
		if ( $t_mail_email_receive_own )
		{
			config_set_cache( 'email_receive_own', ON, CONFIG_TYPE_STRING );
			config_set_global( 'email_receive_own', ON );
		}

		// We need to pass this test else the api is not allowed to work
		if ( is_dir( $this->_mail_tmp_directory ) && is_writeable( $this->_mail_tmp_directory ) )
		{
			$this->_functionality_enabled = TRUE;
		}
		else
		{
			$this->custom_error( 'The temporary mail directory is not writable. Please correct it in the configuration options' );
		}

		// Because of a notice level errors in core/email_api.php on line 516 in MantisBT 1.2.0 we need to fill this value
		if ( !isset( $_SERVER[ 'REMOTE_ADDR' ] ) )
		{
			$_SERVER[ 'REMOTE_ADDR' ] = '127.0.0.1';
		}

		$this->show_memory_usage( 'Finished __construct' );
	}

	# --------------------
	# process all mails for an mailbox
	#  return a boolean for whether the mailbox was successfully processed
	public function process_mailbox( $p_mailbox )
	{
		$this->_mailbox = $p_mailbox + ERP_get_default_mailbox();

		if ( $this->_functionality_enabled )
		{
			if ( $this->_mailbox[ 'enabled' ] )
			{
				$this->prepare_mailbox_hostname();

				if ( $this->_mail_debug )
				{
					var_dump( $this->_mailbox );
				}

				$this->show_memory_usage( 'Start process mailbox' );

				$t_process_mailbox_function = 'process_' . strtolower( $this->_mailbox[ 'type' ] ) . '_mailbox';

				$this->show_memory_usage( 'Finished process mailbox' );

				$this->$t_process_mailbox_function();
			}
			else
			{
				$this->custom_error( 'Mailbox disabled' );
			}
		}

		return( $this->_result );
	}

	# --------------------
	# Show pear error when pear operation failed
	#  return a boolean for whether the mailbox has failed
	private function pear_error( &$p_pear )
	{
		if ( PEAR::isError( $p_pear ) )
		{
			if ( !$this->_test_only )
			{
				echo "\n\n" . 'Mailbox: ' . $this->_mailbox[ 'description' ] . "\n" . $p_pear->toString() . "\n";
			}

			return( TRUE );
		}
		else
		{
			return( FALSE );
		}
	}

	# --------------------
	# Show non-pear error
	#  set $this->result to an array with the error or show it
	private function custom_error( $p_error_text )
	{
		$t_error_text = 'Message: ' . $p_error_text . "\n";

		if ( $this->_test_only )
		{
			$this->_result = array(
				'ERROR_TYPE'	=> 'NON-PEAR-ERROR',
				'ERROR_MESSAGE'	=> $t_error_text,
			);
		}
		else
		{
			echo "\n\n" . 'Mailbox: ' . $this->_mailbox[ 'description' ] . "\n" . $t_error_text;
		}
	}

	# --------------------
	# process all mails for a pop3 mailbox
	private function process_pop3_mailbox()
	{
		$this->_mailserver = new Net_POP3();

		$this->_result = $this->_mailserver->connect( $this->_mailbox[ 'hostname' ], $this->_mailbox[ 'port' ] );

		if ( $this->_result === TRUE )
		{
			$this->mailbox_login();

			if ( $this->_test_only === FALSE && !$this->pear_error( $this->_result ) )
			{
				$t_numMsg = $this->check_fetch_max( $this->_mailserver->numMsg() );

				for ( $i = 1; $i <= $t_numMsg; $i++ )
				{
					$this->process_single_email( $i );

					if ( $this->_mail_delete )
					{
						$this->_mailserver->deleteMsg( $i );
					}
				}
			}

			$this->_mailserver->disconnect();
		}
		else
		{
			$this->custom_error( 'Failed to connect to the mail server' );
		}
	}

	# --------------------
	# process all mails for an imap mailbox
	private function process_imap_mailbox()
	{
		$this->_mailserver = new Net_IMAP( $this->_mailbox[ 'hostname' ], $this->_mailbox[ 'port' ] );

		if ( $this->_mailserver->_connected === TRUE )
		{
			$this->mailbox_login();

			// If basefolder is empty we try to select the inbox folder
			if ( is_blank( $this->_mailbox[ 'basefolder' ] ) )
			{
				$this->_mailbox[ 'basefolder' ] = $this->_mailserver->getCurrentMailbox();
			}

			if ( !$this->pear_error( $this->_result ) )
			{
				if ( $this->_mailserver->mailboxExist( $this->_mailbox[ 'basefolder' ] ) )
				{
					if ( $this->_test_only === FALSE )
					{
						$t_createfolderstructure = $this->_mailbox[ 'createfolderstructure' ];

						// There does not seem to be a viable api function which removes this plugins dependability on table column names
						// So if a column name is changed it might cause problems if the code below depends on it.
						// Luckily we only depend on id, name and enabled
						if ( $t_createfolderstructure === TRUE )
						{
							$t_projects = project_get_all_rows();
							$t_hierarchydelimiter = $this->_mailserver->getHierarchyDelimiter();
						}
						else
						{
							$t_projects = array( 0 => project_get_row( $this->_mailbox[ 'project_id' ] ) );
						}

						$t_total_fetch_counter = 0;

						foreach ( $t_projects AS $t_project )
						{
							if ( $t_project[ 'enabled' ] == TRUE && $this->check_fetch_max( $t_total_fetch_counter, 0, TRUE ) === FALSE )
							{
								$t_project_name = $this->cleanup_project_name( $t_project[ 'name' ] );

								$t_foldername = $this->_mailbox[ 'basefolder' ] . ( ( $t_createfolderstructure ) ? $t_hierarchydelimiter . $t_project_name : NULL );

								// We don't need to check twice whether the mailbox exist twice incase createfolderstructure is false
								if ( !$t_createfolderstructure || $this->_mailserver->mailboxExist( $t_foldername ) === TRUE )
								{
									$this->_mailserver->selectMailbox( $t_foldername );

									$t_isdeleted_count = 0;

									$t_numMsg = $this->_mailserver->numMsg();
									if ( !$this->pear_error( $t_numMsg ) && $t_numMsg > 0 )
									{
										$t_allowed_numMsg = $this->check_fetch_max( $t_numMsg, $t_total_fetch_counter );

										for ( $i = 1; $i <= $t_numMsg; $i++ )
										{
											if ( ( $i - $t_isdeleted_count ) > $t_allowed_numMsg )
											{
												break;
											}
											elseif ( $this->_mailserver->isDeleted( $i ) === TRUE )
											{
												$t_isdeleted_count++;
											}
											else
											{
												$this->process_single_email( $i, (int) $t_project[ 'id' ] );

												$this->_mailserver->deleteMsg( $i );

												$t_total_fetch_counter++;
											}
										}
									}
								}
								elseif ( $t_createfolderstructure === TRUE )
								{
									// create this mailbox
									$this->_mailserver->createMailbox( $t_foldername );
								}
							}
						}
					}
				}
				else
				{
					$this->custom_error( 'IMAP basefolder not found' );
				}
			}

			// Rolf Kleef: explicit expunge to remove deleted messages, disconnect() gives an error...
			// EmailReporting 0.7.0: Corrected IMAPProtocol_1.0.3.php on line 704. disconnect() works again
			//$t_mailbox->expunge();

			// mail_delete decides whether to perform the expunge command before closing the connection
			$this->_mailserver->disconnect( (bool) $this->_mail_delete );
		}
		else
		{
			$this->custom_error( 'Failed to connect to the mail server' );
		}
	}

	# --------------------
	# Perform the login to the mailbox
	private function mailbox_login()
	{
		$t_mailbox_username = $this->_mailbox[ 'username' ];
		$t_mailbox_password = base64_decode( $this->_mailbox[ 'password' ] );
		$t_mailbox_auth_method = $this->_mailbox[ 'auth_method' ];

		$this->_result = $this->_mailserver->login( $t_mailbox_username, $t_mailbox_password, $t_mailbox_auth_method );
	}

	# --------------------
	# Process a single email from either a pop3 or imap mailbox
	private function process_single_email( $p_i, $p_overwrite_project_id = FALSE )
	{
		$this->show_memory_usage( 'Start process single email' );

		$t_msg = $this->_mailserver->getMsg( $p_i );

		$this->save_message_to_file( $t_msg );

		$t_email = $this->parse_content( $t_msg );

		unset( $t_msg );

		$this->show_memory_usage( 'Parsed single email' );

		$this->save_message_to_file( $t_email );

		// Only continue if we have a valid Reporter to work with
		if ( $t_email[ 'Reporter_id' ] !== FALSE )
		{
			// We don't need to validate the email address if it is an existing user (existing user also needs to be set as the reporter of the issue)
			if ( $t_email[ 'Reporter_id' ] !== $this->_mail_reporter_id || $this->validate_email_address( $t_email[ 'From_parsed' ][ 'email' ] ) )
			{
				$this->add_bug( $t_email, $p_overwrite_project_id );
			}
			else
			{
				$this->custom_error( 'From email address rejected by email_is_valid function based on: ' . $t_email[ 'From' ] );
			}
		}

		$this->show_memory_usage( 'Finished process single email' );
	}

	# --------------------
	# parse the email using mimeDecode for Mantis
	private function parse_content( &$p_msg )
	{
		$this->show_memory_usage( 'Start Mail Parser' );

		$t_mp = new ERP_Mail_Parser( $this->_mp_options );

		$t_mp->setInputString( $p_msg );

		// We can only empty msg if we don't need it anymore
		if ( !$this->_mail_add_complete_email )
		{
			$p_msg = NULL;
		}

		$t_mp->parse();

		$t_email[ 'From' ] = $t_mp->from();
		$t_email[ 'From_parsed' ] = $this->parse_address( $t_email[ 'From' ] );
		$t_email[ 'Reporter_id' ] = $this->get_user( $t_email[ 'From_parsed' ] );

		$t_email[ 'Subject' ] = trim( $t_mp->subject() );

		$t_email[ 'X-Mantis-Body' ] = trim( $t_mp->body() );

		$t_email[ 'X-Mantis-Parts' ] = $t_mp->parts();

		if ( $this->_mail_use_bug_priority )
		{
			$t_priority = strtolower( $t_mp->priority() );
			$t_email[ 'Priority' ] = $this->_mail_bug_priority[ $t_priority ];
		}
		else
		{
			$t_email[ 'Priority' ] = $this->_default_bug_priority;
		}

		if ( $this->_mail_add_complete_email )
		{
			$t_part = array(
				'name' => 'Complete email.txt',
				'ctype' => 'text/plain',
				'body' => $p_msg,
			);

			$t_email[ 'X-Mantis-Parts' ][] = $t_part;
		}

		$this->show_memory_usage( 'Finished Mail Parser' );

		return( $t_email );
	}

	# --------------------
	# return the user id for the mail reporting user
	private function get_user( $p_parsed_from )
	{
		if ( $this->_mail_use_reporter )
		{
			// Always report as mail_reporter
			$t_reporter_id = $this->_mail_reporter_id;
		}
		else
		{
			// Try to get the reporting users id
			if ( $this->_login_method == LDAP && $this->_use_ldap_email )
			{
				$t_username = ERP_ldap_get_username_from_email( $p_parsed_from[ 'email' ] );

				if ( user_is_name_valid( $t_username ) )
				{
					$t_reporter_id = user_get_id_by_name( $t_username );
				}
			}
			else
			{
				$t_reporter_id = user_get_id_by_email( $p_parsed_from[ 'email' ] );
			}

			if ( !$t_reporter_id )
			{
				if ( $this->_mail_auto_signup )
				{
					// So, we have to sign up a new user...
					$t_new_reporter_name = $this->prepare_username( $p_parsed_from );

					if ( $t_new_reporter_name !== FALSE && email_is_valid( $p_parsed_from[ 'email' ] ) )
					{
						if( user_signup( $t_new_reporter_name, $p_parsed_from[ 'email' ] ) )
						{
							# notify the selected group a new user has signed-up
							email_notify_new_account( $t_new_reporter_name, $p_parsed_from[ 'email' ] );

							$t_reporter_id = user_get_id_by_email( $p_parsed_from[ 'email' ] );
							$t_reporter_name = $t_new_reporter_name;

							$t_realname = $p_parsed_from[ 'name' ];

							if ( utf8_strlen( $t_realname ) > REALLEN )
							{
								$t_realname = utf8_substr( $t_realname, 0, REALLEN );
							}

							if ( user_is_realname_valid( $t_realname ) && user_is_realname_unique( $t_reporter_name, $t_realname ) )
							{
								user_set_realname( $t_reporter_id, $t_realname );
							}
						}
					}

					if ( !$t_reporter_id )
					{
						$this->custom_error( 'Failed to create user based on: ' . implode( ' - ', $p_parsed_from ) );
					}
				}

				if ( !$t_reporter_id && $this->_mail_fallback_mail_reporter )
				{
					// Fall back to the default mail_reporter
					$t_reporter_id = $this->_mail_reporter_id;
				}
			}
			elseif ( !user_is_enabled( $t_reporter_id ) && $this->_mail_fallback_mail_reporter )
			{
				// Fall back to the default mail_reporter
				$t_reporter_id = $this->_mail_reporter_id;
			}
		}

		if ( $t_reporter_id && user_is_enabled( $t_reporter_id ) )
		{
			if ( !isset( $t_reporter_name ) )
			{
				$t_reporter_name = user_get_field( $t_reporter_id, 'username' );
			}

			auth_attempt_script_login( $t_reporter_name );

			return( (int) $t_reporter_id );
		}
		else
		{
			$this->custom_error( 'Could not get a valid reporter. Email will be ignored' );

			return( FALSE );
		}
	}

	# --------------------
	# Adds a bug which is reported via email
	# Taken from bug_report.php in MantisBT 1.2.0
	private function add_bug( &$p_email, $p_overwrite_project_id = FALSE )
	{
		$this->show_memory_usage( 'Start add bug' );

		if ( $this->_mail_add_bugnotes )
		{
			$t_bug_id = $this->mail_is_a_bugnote( $p_email[ 'Subject' ] );
		}
		else
		{
			$t_bug_id = FALSE;
		}

		if ( $t_bug_id !== FALSE && !bug_is_readonly( $t_bug_id ) )
		{
			// @TODO@ Disabled for now until we find a good solution on how to handle the reporters possible lack of access permissions
//			access_ensure_bug_level( config_get( 'add_bugnote_threshold' ), $f_bug_id );

			$t_description = $p_email[ 'X-Mantis-Body' ];

			$t_description = $this->identify_replies( $t_description );
			$t_description = $this->apply_mail_save_from( $p_email[ 'From' ], $t_description );

			# Event integration
			# Core mantis event already exists within bignote_add function
			$t_bugnote_text = event_signal( 'EVENT_ERP_BUGNOTE_DATA', $t_description, $t_bug_id );

			$t_status = bug_get_field( $t_bug_id, 'status' );

			if ( $this->_bug_resolved_status_threshold <= $t_status )
			{
				# Reopen issue and add a bug note
				bug_reopen( $t_bug_id, $t_description );
			}
			elseif ( !is_blank( $t_description ) )
			{
				# Add a bug note
				bugnote_add( $t_bug_id, $t_description );
			}
		}
		elseif ( $this->_mail_add_bug_reports )
		{
			// @TODO@ Disabled for now until we find a good solution on how to handle the reporters possible lack of access permissions
//			access_ensure_project_level( config_get('report_bug_threshold' ) );

			$f_master_bug_id = ( ( $t_bug_id !== FALSE && bug_is_readonly( $t_bug_id ) ) ? $t_bug_id : 0 );

			$this->fix_empty_fields( $p_email );

			$t_bug_data = new BugData;
			$t_bug_data->build					= '';
			$t_bug_data->platform				= '';
			$t_bug_data->os						= '';
			$t_bug_data->os_build				= '';
			$t_bug_data->version				= '';
			$t_bug_data->profile_id				= 0;
			$t_bug_data->handler_id				= 0;
			$t_bug_data->view_state				= $this->_default_bug_view_status;

			$t_bug_data->category_id			= $this->_mailbox[ 'global_category_id' ];
			$t_bug_data->reproducibility		= $this->_default_bug_reproducibility;
			$t_bug_data->severity				= $this->_default_bug_severity;
			$t_bug_data->priority				= $p_email[ 'Priority' ];
			$t_bug_data->projection				= $this->_default_bug_projection;
			$t_bug_data->eta					= $this->_default_bug_eta;
			$t_bug_data->resolution				= $this->_default_bug_resolution;
			$t_bug_data->status					= $this->_bug_submit_status;
			$t_bug_data->summary				= $p_email[ 'Subject' ];

			$t_bug_data->description			= $this->apply_mail_save_from( $p_email[ 'From' ], $p_email[ 'X-Mantis-Body' ] );

			$t_bug_data->steps_to_reproduce		= $this->_default_bug_steps_to_reproduce;
			$t_bug_data->additional_information	= $this->_default_bug_additional_info;
			$t_bug_data->due_date				= date_get_null();

			$t_bug_data->project_id				= ( ( $p_overwrite_project_id === FALSE ) ? $this->_mailbox[ 'project_id' ] : $p_overwrite_project_id );

			$t_bug_data->reporter_id			= $p_email[ 'Reporter_id' ];

			// @TODO@ Disabled for now but possibly needed for other future features
			# Validate the custom fields before adding the bug.
/*			$t_related_custom_field_ids = custom_field_get_linked_ids( $t_bug_data->project_id );
			foreach( $t_related_custom_field_ids as $t_id )
			{
				$t_def = custom_field_get_definition( $t_id );

				# Produce an error if the field is required but wasn't posted
				if ( !gpc_isset_custom_field( $t_id, $t_def['type'] ) &&
					( $t_def['require_report'] ||
						$t_def['type'] == CUSTOM_FIELD_TYPE_ENUM ||
						$t_def['type'] == CUSTOM_FIELD_TYPE_LIST ||
						$t_def['type'] == CUSTOM_FIELD_TYPE_MULTILIST ||
						$t_def['type'] == CUSTOM_FIELD_TYPE_RADIO ) ) {
					error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
					trigger_error( ERROR_EMPTY_FIELD, ERROR );
				}
				if ( !custom_field_validate( $t_id, gpc_get_custom_field( "custom_field_$t_id", $t_def['type'], NULL ) ) )
				{
					error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
					trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, ERROR );
				}
			}*/

			# Allow plugins to pre-process bug data
			$t_bug_data = event_signal( 'EVENT_REPORT_BUG_DATA', $t_bug_data );
			$t_bug_data = event_signal( 'EVENT_ERP_REPORT_BUG_DATA', $t_bug_data );

			# Create the bug
			$t_bug_id = $t_bug_data->create();

			// @TODO@ Disabled for now but possibly needed for other future features
			# Handle custom field submission
/*			foreach( $t_related_custom_field_ids as $t_id )
{
				# Do not set custom field value if user has no write access.
				if( !custom_field_has_write_access( $t_id, $t_bug_id ) )
				{
					continue;
				}

				$t_def = custom_field_get_definition( $t_id );
				if( !custom_field_set_value( $t_id, $t_bug_id, gpc_get_custom_field( "custom_field_$t_id", $t_def['type'], '' ), false ) ) {
				{
					error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
					trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, ERROR );
				}
			}*/

			// Lets link a readonly already existing bug to the newly created one
			if ( $f_master_bug_id > 0 )
			{
				$f_rel_type = BUG_RELATED;

				# update master bug last updated
				bug_update_date( $f_master_bug_id );

				# Add the relationship
				relationship_add( $t_bug_id, $f_master_bug_id, $f_rel_type );

				# Add log line to the history (both issues)
				history_log_event_special( $f_master_bug_id, BUG_ADD_RELATIONSHIP, relationship_get_complementary_type( $f_rel_type ), $t_bug_id );
				history_log_event_special( $t_bug_id, BUG_ADD_RELATIONSHIP, $f_rel_type, $f_master_bug_id );

				# Send the email notification
				email_relationship_added( $f_master_bug_id, $t_bug_id, relationship_get_complementary_type( $f_rel_type ) );
			}

			helper_call_custom_function( 'issue_create_notify', array( $t_bug_id ) );

			# Allow plugins to post-process bug data with the new bug ID
			event_signal( 'EVENT_REPORT_BUG', array( $t_bug_data, $t_bug_id ) );

			email_new_bug( $t_bug_id );
		}
		else
		{
			// Not allowed to add bugs and not allowed / able to add bugnote. Need to stop processing
			$this->custom_error( 'Not allowed to create a new issue. Email ignored' );
			return;
		}

		$this->custom_error( 'Reporter: ' . $p_email[ 'Reporter_id' ] . ' - ' . $p_email[ 'From_parsed' ][ 'email' ] . ' --> Issue ID: #' . $t_bug_id );

		$this->show_memory_usage( 'Start processing attachments' );

		# Add files
		if ( $this->_allow_file_upload )
		{
			if ( count( $p_email[ 'X-Mantis-Parts' ] ) > 0 )
			{
				$t_rejected_files = NULL;

				foreach ( $p_email[ 'X-Mantis-Parts' ] as $part )
				{
					$t_file_rejected = $this->add_file( $t_bug_id, $part );

					if ( $t_file_rejected !== TRUE )
					{
						$t_rejected_files .= $t_file_rejected;
					}
				}

				if ( !is_null( $t_rejected_files ) )
				{
					$part = array(
						'name' => 'Rejected files.txt',
						'ctype' => 'text/plain',
						'body' => 'List of rejected files' . "\n\n" . $t_rejected_files,
					);

					$t_reject_rejected_files = $this->add_file( $t_bug_id, $part );
					if ( $t_reject_rejected_files !== TRUE )
					{
						$part[ 'body' ] .= $t_reject_rejected_files;
						$this->custom_error( 'Failed to add "' . $part[ 'name' ] . '" to the issue. See below for all errors.' . "\n" . $part[ 'body' ] );
					}
				}
			}
		}
	}

	# --------------------
	# Very dirty: Adds a file to a bug.
	# returns true on success and the filename with reason on error
	private function add_file( $p_bug_id, &$p_part )
	{
		# Handle the file upload
		$t_part_name = ( ( isset( $p_part[ 'name' ] ) ) ? trim( $p_part[ 'name' ] ) : NULL );
		$t_strlen_body = strlen( trim( $p_part[ 'body' ] ) );

		if ( is_blank( $t_part_name ) )
		{
			$t_part_name = md5( microtime() ) . '.erp';
		}

		if ( !file_type_check( $t_part_name ) )
		{
			return( $t_part_name . ' = filetype not allowed' . "\n" );
		}
		elseif ( 0 == $t_strlen_body )
		{
			return( $t_part_name . ' = attachment size is zero (' . $t_strlen_body . ' / ' . $this->_max_file_size . ')' . "\n" );
		}
		elseif ( $t_strlen_body > $this->_max_file_size )
		{
			return( $t_part_name . ' = attachment size exceeds maximum allowed file size (' . $t_strlen_body . ' / ' . $this->_max_file_size . ')' . "\n" );
		}
		else
		{
			$t_file_number = 0;
			$t_opt_name = '';

			while ( !file_is_name_unique( $t_opt_name . $t_part_name, $p_bug_id ) )
			{
				$t_file_number++;
				$t_opt_name = $t_file_number . '-';
			}

			$t_file_name = $this->_mail_tmp_directory . '/' . md5( microtime() );

			file_put_contents( $t_file_name, $p_part[ 'body' ] );

			ERP_custom_file_add( $p_bug_id, array(
				'tmp_name'	=> realpath( $t_file_name ),
				'name'		=> $t_opt_name . $t_part_name,
				'type'		=> $p_part[ 'ctype' ],
				'error'		=> NULL
			), 'bug' );

			if ( is_file( $t_file_name ) )
			{
				unlink( $t_file_name );
			}
		}

		return( TRUE );
	}

	# --------------------
	# return whether the current process has reached the mail_fetch_max parameter
	# $p_return_bool decides whether or not a boolean or a integer is returned
	#  integer will be the maximum number of emails that are allowed to be processed for this mailbox
	#  boolean will be true or false depending on whether or not the maximum number of emails have been processed
	private function check_fetch_max( $p_numMsg, $p_numMsg_processed = 0, $p_return_bool = FALSE )
	{
		if ( ( $p_numMsg + $p_numMsg_processed ) >= $this->_mail_fetch_max )
		{
			$t_numMsg_allowed = ( ( $p_return_bool ) ? TRUE : $this->_mail_fetch_max - $p_numMsg_processed );
		}
		else
		{
			$t_numMsg_allowed = ( ( $p_return_bool ) ? FALSE : $p_numMsg );
		}

		return( $t_numMsg_allowed );
	}

	# --------------------
	# Translate the project name into an IMAP folder name:
	# - translate all accented characters to plain ASCII equivalents
	# - replace all but alphanum chars and space and colon to dashes
	# - replace multiple dots by a single one
	# - strip spaces, dots and dashes at the beginning and end
	# (It should be possible to use UTF-7, but this is working)
	private function cleanup_project_name( $p_project_name )
	{
		$t_project_name = $p_project_name;
		$t_project_name = htmlentities( $t_project_name, ENT_QUOTES, 'UTF-8' );
		$t_project_name = preg_replace( "/&(.)(acute|cedil|circ|ring|tilde|uml);/", "$1", $t_project_name );
		$t_project_name = preg_replace( "/([^A-Za-z0-9 ]+)/", "-", html_entity_decode( $t_project_name ) );
		$t_project_name = preg_replace( "/(\.+)/", ".", $t_project_name );
		$t_project_name = trim( $t_project_name, "-. " );

		return( $t_project_name );
	}

	# --------------------
	# return the hostname parsed into a hostname + port
	private function prepare_mailbox_hostname()
	{
		if ( $this->_mailbox[ 'encryption' ] !== 'None' && extension_loaded( 'openssl' ) )
		{
			$this->_mailbox[ 'hostname' ] = strtolower( $this->_mailbox[ 'encryption' ] ) . '://' . $this->_mailbox[ 'hostname' ];

			$t_def_mailbox_port_index = 'encrypted';
		}
		else
		{
			$t_def_mailbox_port_index = 'normal';
		}

		$this->_mailbox[ 'port' ] = (int) $this->_mailbox[ 'port' ];
		if ( $this->_mailbox[ 'port' ] <= 0 )
		{
			$this->_mailbox[ 'port' ] = (int) $this->_default_ports[ $this->_mailbox[ 'type' ] ][ $t_def_mailbox_port_index ];
		}
	}

	# --------------------
	# Validate the email address
	private function validate_email_address( $p_email_address )
	{
		if ( $this->_validate_email )
		{
			// Lets see if the email address is valid and maybe we already have a cached result
			if ( isset( $this->_validated_email_list[ $p_email_address ] ) )
			{
				$t_valid = $this->_validated_email_list[ $p_email_address ];
			}
			else
			{
				if ( email_is_valid( $p_email_address ) )
				{
					$t_valid = TRUE;
				}
				else
				{
					$t_valid = FALSE;
				}

				$this->_validated_email_list[ $p_email_address ] = $t_valid;
			}
		}
		else
		{
			$t_valid = TRUE;
		}

		return( $t_valid );
	}

	# --------------------
	# return the mailadress from the mail's 'From'
	private function parse_address( $p_from_address )
	{
		if ( preg_match( "/(.*?)<(.*?)>/", $p_from_address, $matches ) )
		{
			$v_from_address = array(
				'name'	=> trim( $matches[ 1 ], '"\' ' ),
				'email'	=> trim( $matches[ 2 ] ),
			);
		}
		else
		{
			$v_from_address = array(
				'name'	=> '',
				'email'	=> $p_from_address,
			);
		}

		return( $v_from_address );
	}

	# --------------------
	# return the a valid username from an email address
	private function prepare_username( $p_user_info )
	{
		# I would have liked to validate the username and remove any non-allowed characters
		# using the config user_login_valid_regex but that seems not possible and since
		# it's a config, any mantis installation could have a different one
		if ( $this->_mail_preferred_username === 'name' )
		{
			$t_username = $p_user_info[ 'name' ];
		}
		elseif ( $this->_mail_preferred_username === 'email_address' )
		{
			$t_username = $p_user_info[ 'email' ];
		}
		elseif ( $this->_mail_preferred_username === 'email_no_domain' )
		{
			if( preg_match( email_regex_simple(), $p_user_info[ 'email' ], $t_check ) )
			{
				$t_local = $t_check[ 1 ];
				$t_domain = $t_check[ 2 ];

				$t_username = $t_local;
			}
		}
		elseif ( $this->_login_method == LDAP && $this->_mail_preferred_username === 'from_ldap' )
		{
			$t_username = ERP_ldap_get_username_from_email( $p_user_info[ 'email' ] );
		}

		if ( utf8_strlen( $t_username ) > USERLEN )
		{
			$t_username = utf8_substr( $t_username, 0, USERLEN );
		}

		if ( user_is_name_valid( $t_username ) && user_is_name_unique( $t_username ) )
		{
			return( $t_username );
		}

		// fallback username
		$t_username = strtolower( str_replace( array( '@', '.', '-' ), '_', $p_user_info[ 'email' ] ) );
		$t_rand = '_' . mt_rand( 1000, 99999 );

		if ( utf8_strlen( $t_username . $t_rand ) > USERLEN )
		{
			$t_username = utf8_substr( $t_username, 0, ( USERLEN - strlen( $t_rand ) ) );
		}

		$t_username = $t_username . $t_rand;

		if ( user_is_name_valid( $t_username ) && user_is_name_unique( $t_username ) )
		{
			return( $t_username );
		}

		return( FALSE );
	}

	# --------------------
	# return bug_id if there is a valid mantis bug refererence in subject or return false if not found
	private function mail_is_a_bugnote( $p_mail_subject )
	{
		$t_bug_id = $this->get_bug_id_from_subject( $p_mail_subject );

		if ( $t_bug_id !== FALSE )
		{
			if ( bug_exists( $t_bug_id ) )
			{
				return( $t_bug_id );
			}
		}

		return( FALSE );
	}

	# --------------------
	# return the bug's id from the subject
	private function get_bug_id_from_subject( $p_mail_subject )
	{
		preg_match( "/\[.*?\s([0-9]{1,7}?)\]/u", $p_mail_subject, $v_matches );

		if ( isset( $v_matches[ 1 ] ) )
		{
			return( $v_matches[ 1 ] );
		}

		return( FALSE );
	}
	
	# --------------------
	# Saves the complete email to file
	# Only works in debug mode
	private function save_message_to_file( &$p_msg )
	{
		if ( $this->_mail_debug && is_dir( $this->_mail_debug_directory ) && is_writeable( $this->_mail_debug_directory ) )
		{
			$t_end_file_name = time() . '_' . md5( microtime() );

			if ( is_array( $p_msg ) )
			{
				$t_file_name = $this->_mail_debug_directory . '/parsed_email_' . $t_end_file_name;
				file_put_contents( $t_file_name, print_r( $p_msg, TRUE ) );
			}
			else
			{
				$t_file_name = $this->_mail_debug_directory . '/rawmsg_' . $t_end_file_name;
				file_put_contents( $t_file_name, $p_msg );
			}
		}
	}

	# --------------------
	# Removes replies from mails
	private function identify_replies( $p_description )
	{
		$t_description = $p_description;

		if ( $this->_mail_remove_replies )
		{
			$t_first_occurence = stripos( $t_description, $this->_mail_remove_replies_after );
			if ( $t_first_occurence !== FALSE )
			{
				$t_description = substr( $t_description, 0, $t_first_occurence ) . $this->_mail_removed_reply_text;
			}
		}

		if ( $this->_mail_remove_mantis_email )
		{
			# The pear mimeDecode.php seems to be removing the last "=" in some versions of the pear package.
			# the version delivered with this package seems to be working OK though but just to be sure
			$t_email_separator1 = substr( $this->_email_separator1, 0, -1 );

			$t_first_occurence = strpos( $t_description, $t_email_separator1 );
			if ( $t_first_occurence !== FALSE && substr_count( $t_description, $t_email_separator1, $t_first_occurence ) >= 5 )
			{
				$t_description = substr( $t_description, 0, $t_first_occurence ) . $this->_mail_removed_reply_text;
			}
		}

		return( $t_description );
	}

	# --------------------
	# Fixes an empty subject and description with a predefined default text
	#  $p_mail is passed by reference so no return value needed
	private function fix_empty_fields( &$p_mail )
	{
		if ( is_blank( $p_mail[ 'Subject' ] ) )
		{
			$p_mail[ 'Subject' ] = $this->_mail_nosubject;
		}

		if ( is_blank( $p_mail[ 'X-Mantis-Body' ] ) )
		{
			$p_mail[ 'X-Mantis-Body' ] = $this->_mail_nodescription;
		}
	}

	# --------------------
	# Add the save from text if enabled
	private function apply_mail_save_from( $p_from, $p_description )
	{
		if ( $this->_mail_save_from )
		{
			return( 'Email from: ' . $p_from . "\n\n" . $p_description );
		}

		return( $p_description );
	}

	# --------------------
	# Show memory usage in debug mode
	private function show_memory_usage( $p_location )
	{
		if ( !$this->_test_only && $this->_mail_debug )
		{
			$this->custom_error( 'Debug output memory usage' . "\n" .
				'Location: Mail API - ' . $p_location . "\n" .
				'Current memory usage: ' . ERP_formatbytes( memory_get_usage( FALSE ) ) . ' / ' . ERP_formatbytes( memory_get_peak_usage ( FALSE ) ) . ' (memory_limit: ' . ini_get( 'memory_limit' ) . ')' . "\n" .
				'Current real memory usage: ' . ERP_formatbytes( memory_get_usage( TRUE ) ) . ' / ' . ERP_formatbytes( memory_get_peak_usage ( TRUE ) ) . ' (memory_limit: ' . ini_get( 'memory_limit' ) . ')' . "\n"
			);
		}
	}
}

	# --------------------
	# This function formats the bytes so that they are easily readable.
	# Not part of a class
	function ERP_formatbytes( $p_bytes )
	{
		$t_units = array( ' B', ' KiB', ' MiB', ' GiB', ' TiB' );

		$t_bytes = $p_bytes;

		for ( $i = 0; $t_bytes > 1024; $i++ )
		{
			$t_bytes /= 1024;
		}

		return( round( $t_bytes, 2 ) . $t_units[ $i ] );
	}

	/**
	 * Gets the username from LDAP given the email address
	 *
	 * @todo Implement caching by retrieving all needed information in one query.
	 * @todo Implement logging to LDAP queries same way like DB queries.
	 *
	 * @param string $p_email The email address.
	 * @return string The username or null if not found.
	 *
	 * Based on ldap_get_field_from_username from MantisBT 1.2.1
	 */
	function ERP_ldap_get_username_from_email( $p_email ) {
		$t_ldap_organization    = config_get( 'ldap_organization' );
		$t_ldap_root_dn         = config_get( 'ldap_root_dn' );
		$t_ldap_uid_field		= config_get( 'ldap_uid_field' );

		$c_email = ldap_escape_string( $p_email );

		# Bind
		log_event( LOG_LDAP, "Binding to LDAP server" );
		$t_ds = ldap_connect_bind();
		if ( $t_ds === false ) {
			log_event( LOG_LDAP, "ldap_connect_bind() returned false." );
			return null;
		}

		# Search
		$t_search_filter        = "(&$t_ldap_organization(mail=$c_email))";
		$t_search_attrs         = array( $t_ldap_uid_field, 'mail', 'dn' );

		log_event( LOG_LDAP, "Searching for $t_search_filter" );
		$t_sr = ldap_search( $t_ds, $t_ldap_root_dn, $t_search_filter, $t_search_attrs );
		if ( $t_sr === false ) {
			ldap_unbind( $t_ds );
			log_event( LOG_LDAP, "ldap_search() returned false." );
			return null;
		}

		# Get results
		$t_info = ldap_get_entries( $t_ds, $t_sr );
		if ( $t_info === false ) {
			log_event( LOG_LDAP, "ldap_get_entries() returned false." );
			return null;
		}
	
		# Free results / unbind
		log_event( LOG_LDAP, "Unbinding from LDAP server" );
		ldap_free_result( $t_sr );
		ldap_unbind( $t_ds );

		# If no matches, return null.
		if ( count( $t_info ) == 0 ) {
			log_event( LOG_LDAP, "No matches found." );
			return null;
		}

		$t_value = $t_info[0][ strtolower( $t_ldap_uid_field ) ][0];
		log_event( LOG_LDAP, "Found value '{$t_value}' for field '{$p_field}'." );

		return $t_value;
	}
?>
