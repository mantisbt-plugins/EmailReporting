<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# Copyright (C) 2007  Rolf Kleef - rolf@drostan.org (IMAP)
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id: mail_api.php,v 1.42 2010/04/13 00:14:32 SL-Server\SC Kruiper Exp $
	# --------------------------------------------------------

	# This page receives an E-Mail via POP3 or IMAP and generates an Report

	require_once( 'bug_api.php' );
	require_once( 'bugnote_api.php' );
	require_once( 'user_api.php' );
	require_once( 'file_api.php' );

	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/custom_file_api.php' );

	require_once( 'Net/POP3.php' );
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Net/IMAP_1.0.3.php' );

	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Mail/Parser.php' );
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

class ERP_mailbox_api
{
	private $_functionality_enabled = FALSE;
	private $_test_only = FALSE;

	public $_mailbox = array( 'mailbox_description' => 'INITIALIZATION PHASE' );

	private $_mailserver = NULL;
	private $_result = FALSE;

	private $_default_ports = array(
		'POP3' => array( 'normal' => 110, 'encrypted' => 995 ),
		'IMAP' => array( 'normal' => 143, 'encrypted' => 993 ),
	);
	private $_file_number = 1;

	private $_validated_email_list;

	private $_mail_delete;
	private $_mail_debug;
	private $_mail_debug_directory;
	private $_mail_fetch_max;
	private $_mail_use_bug_priority;
	private $_mail_bug_priority;
	private $_mail_add_complete_email;
	private $_mail_use_reporter;
	private $_mail_reporter_id;
	private $_mail_auto_signup;
	private $_mail_tmp_directory;
	private $_mail_identify_reply;
	private $_mail_removed_reply_text;
	private $_mail_nosubject;
	private $_mail_nodescription;
	private $_mail_save_from;

	private $_mp_options = array();

	private $_default_bug_priority;

	private $_validate_email;
	private $_check_mx_record;
	private $_allow_file_upload;
	private $_bug_resolved_status_threshold;
	private $_email_separator1;
	private $_default_bug_view_status;
	private $_default_bug_reproducibility;
	private $_default_bug_severity;
	private $_default_bug_projection;
	private $_default_bug_eta;
	private $_default_bug_resolution;
	private $_bug_submit_status;
	private $_default_bug_steps_to_reproduce;
	private $_default_bug_additional_info;

	private $_max_file_size;

	# --------------------
	# Retrieve all necessary configuration options
	public function __construct( $p_test_only = FALSE )
	{
		$this->_test_only = $p_test_only;

		$this->_mail_delete                    = plugin_config_get( 'mail_delete' );
		$this->_mail_debug                     = plugin_config_get( 'mail_debug' );
		$this->_mail_debug_directory           = plugin_config_get( 'mail_debug_directory' );
		$this->_mail_fetch_max                 = plugin_config_get( 'mail_fetch_max' );
		$this->_mail_use_bug_priority          = plugin_config_get( 'mail_use_bug_priority' );
		$this->_mail_bug_priority              = plugin_config_get( 'mail_bug_priority' );
		$this->_mail_add_complete_email        = plugin_config_get( 'mail_add_complete_email' );
		$this->_mail_use_reporter              = plugin_config_get( 'mail_use_reporter' );
		$this->_mail_reporter_id               = plugin_config_get( 'mail_reporter_id' );
		$this->_mail_auto_signup               = plugin_config_get( 'mail_auto_signup' );
		$this->_mail_tmp_directory             = plugin_config_get( 'mail_tmp_directory' );
		$this->_mail_identify_reply            = plugin_config_get( 'mail_identify_reply' );
		$this->_mail_removed_reply_text        = plugin_config_get( 'mail_removed_reply_text' );
		$this->_mail_nosubject                 = plugin_config_get( 'mail_nosubject' );
		$this->_mail_nodescription             = plugin_config_get( 'mail_nodescription' );
		$this->_mail_save_from                 = plugin_config_get( 'mail_save_from' );

		$this->_mp_options[ 'parse_mime' ]     = plugin_config_get( 'mail_parse_mime' );
		$this->_mp_options[ 'parse_html' ]     = plugin_config_get( 'mail_parse_html' );
		$this->_mp_options[ 'encoding' ]       = plugin_config_get( 'mail_encoding' );

		$this->_default_bug_priority           = config_get( 'default_bug_priority' );
		$this->_validate_email                 = config_get( 'validate_email' );
		$this->_check_mx_record                = config_get( 'check_mx_record' );
		$this->_allow_file_upload              = config_get( 'allow_file_upload' );
		$this->_bug_resolved_status_threshold  = config_get( 'bug_resolved_status_threshold' );
		$this->_email_separator1               = config_get( 'email_separator1' );
		$this->_default_bug_view_status        = config_get( 'default_bug_view_status' );
		$this->_default_bug_reproducibility    = config_get( 'default_bug_reproducibility', 10 );
		$this->_default_bug_severity           = config_get( 'default_bug_severity', 50 );
		$this->_default_bug_projection         = config_get( 'default_bug_projection' );
		$this->_default_bug_eta                = config_get( 'default_bug_eta' );
		$this->_default_bug_resolution         = config_get( 'default_bug_resolution' );
		$this->_bug_submit_status              = config_get( 'bug_submit_status' );
		$this->_default_bug_steps_to_reproduce = config_get( 'default_bug_steps_to_reproduce' );
		$this->_default_bug_additional_info    = config_get( 'default_bug_additional_info' );

		$this->_max_file_size                  = (int) min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );

		// Need to disable show_realname because i must have the username
		config_set_cache( 'show_realname', OFF, CONFIG_TYPE_STRING );
		config_set_global( 'show_realname', OFF );

		// We need to pass this test else the api is not allowed to work
		if ( is_dir( $this->_mail_tmp_directory ) && is_writeable( $this->_mail_tmp_directory ) )
		{
			$this->_functionality_enabled = TRUE;
		}
		else
		{
			$this->custom_error( 'The temporary mail directory is not writable. Please correct it in the configuration options' );
		}
	}

	# --------------------
	# process all mails for an mailbox
	#  return a boolean for whether the mailbox was successfully processed
	public function process_mailbox( $p_mailbox )
	{
		$this->_mailbox = array_merge( ERP_get_default_mailbox(), $p_mailbox );

		if ( $this->_functionality_enabled )
		{
			$this->prepare_mailbox_hostname();

			if ( $this->_mail_debug )
			{
				var_dump( $this->_mailbox );
			}

			$t_process_mailbox_function = 'process_' . strtolower( $this->_mailbox[ 'mailbox_type' ] ) . '_mailbox';

			$this->$t_process_mailbox_function();
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
			if ( $this->_test_only === FALSE )
			{
				echo "\n\n" . 'Error: ' . $this->_mailbox[ 'mailbox_description' ] . "\n" . $p_pear->toString();
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
		$t_error_text = "\n\n" . 'Error: ' . $this->_mailbox[ 'mailbox_description' ] . "\n" . ' -> ' . $p_error_text . '.';

		if ( $this->_test_only )
		{
			$this->_result = array(
				'ERROR_TYPE'	=> 'NON-PEAR-ERROR',
				'ERROR_MESSAGE'	=> $t_error_text,
			);
		}
		else
		{
			echo $t_error_text;
		}
	}

	# --------------------
	# process all mails for a pop3 mailbox
	private function process_pop3_mailbox()
	{
		$this->_mailserver = new Net_POP3();

		$this->_result = $this->_mailserver->connect( $this->_mailbox[ 'mailbox_hostname' ][ 'hostname' ], $this->_mailbox[ 'mailbox_hostname' ][ 'port' ] );

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
		$this->_mailserver = new Net_IMAP( $this->_mailbox[ 'mailbox_hostname' ][ 'hostname' ], $this->_mailbox[ 'mailbox_hostname' ][ 'port' ] );

		if ( $this->_mailserver->_connected === TRUE )
		{
			$this->mailbox_login();

			// If basefolder is empty we try to select the inbox folder
			if ( is_blank( $this->_mailbox[ 'mailbox_basefolder' ] ) )
			{
				$this->_mailbox[ 'mailbox_basefolder' ] = $this->_mailserver->getCurrentMailbox();
			}

			if ( !$this->pear_error( $this->_result ) )
			{
				if ( $this->_mailserver->mailboxExist( $this->_mailbox[ 'mailbox_basefolder' ] ) )
				{
					if ( $this->_test_only === FALSE )
					{
						$t_createfolderstructure = $this->_mailbox[ 'mailbox_createfolderstructure' ];

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
							$t_projects = array( 0 => project_get_row( $this->_mailbox[ 'mailbox_project' ] ) );
						}

						$t_total_fetch_counter = 0;

						foreach ( $t_projects AS $t_project )
						{
							if ( $t_project[ 'enabled' ] == TRUE && $this->check_fetch_max( $t_total_fetch_counter, 0, TRUE ) === FALSE )
							{
								$t_project_name = $this->cleanup_project_name( $t_project[ 'name' ] );

								$t_foldername = $this->_mailbox[ 'mailbox_basefolder' ] . ( ( $t_createfolderstructure ) ? $t_hierarchydelimiter . $t_project_name : NULL );

								// We don't need to check twice whether the mailbox exist twice incase createfolderstructure is false
								if ( !$t_createfolderstructure || $this->_mailserver->mailboxExist( $t_foldername ) === TRUE )
								{
									$this->_mailserver->selectMailbox( $t_foldername );

									$t_isdeleted_count = 0;

									$t_numMsg = $this->_mailserver->numMsg();
									if ( !$this->pear_error( $t_numMsg ) )
									{
										$t_numMsg = $this->check_fetch_max( $t_numMsg, $t_total_fetch_counter );

										for ( $i = 1; ( $i - $t_isdeleted_count ) <= $t_numMsg; $i++ )
										{
											if ( $this->_mailserver->isDeleted( $i ) === TRUE )
											{
												$t_isdeleted_count++;
											}
											else
											{
												$this->process_single_email( $i, $t_project[ 'id' ] );

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
		$t_mailbox_username = $this->_mailbox[ 'mailbox_username' ];
		$t_mailbox_password = base64_decode( $this->_mailbox[ 'mailbox_password' ] );
		$t_mailbox_auth_method = $this->_mailbox[ 'mailbox_auth_method' ];

		$this->_result = $this->_mailserver->login( $t_mailbox_username, $t_mailbox_password, $t_mailbox_auth_method );
	}

	# --------------------
	# Process a single email from either a pop3 or imap mailbox
	private function process_single_email( $p_i, $p_overwrite_project_id = FALSE )
	{
		$t_msg = $this->_mailserver->getMsg( $p_i );

		$t_email = $this->parse_content( $t_msg );

		if ( $this->_mail_debug )
		{
			var_dump( $t_email );
			$this->save_message_to_file( $t_msg );
		}

		unset( $t_msg );

		// We don't need to validate the email address if it is an existing user (existing user also needs to be set as the reporter of the issue)
		if ( $t_email[ 'Reporter_id' ] !== $this->_mail_reporter_id || validate_email_address( $t_email[ 'From_parsed' ][ 'email' ] ) )
		{
			$this->add_bug( $t_email, $p_overwrite_project_id );
		}
		else
		{
			$this->custom_error( 'From email address rejected by email_is_valid function based on: ' . $t_email[ 'From' ] );
		}
	}

	# --------------------
	# parse the email for Mantis
	private function parse_content( &$p_msg )
	{
		$t_mp = new ERP_Mail_Parser( $this->_mp_options );

		$t_mp->setInputString( $p_msg );

		$t_mp->parse();

		$t_email[ 'From' ] = $t_mp->from();
		$t_email[ 'From_parsed' ] = $this->parse_address( $t_email[ 'From' ] );
		$t_email[ 'Reporter_id' ] = $this->get_user( $t_email[ 'From_parsed' ] );

		$t_email[ 'Subject' ] = trim( $t_mp->subject() );

		$t_email[ 'X-Mantis-Body' ] = trim( $t_mp->body() );

		$t_email[ 'X-Mantis-Parts' ] = $t_mp->parts();

		if ( $this->_mail_use_bug_priority )
		{
			$t_priority =  strtolower( $t_mp->priority() );
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
			$t_reporter_id = user_get_id_by_email( $p_parsed_from[ 'email' ] );

			if ( !$t_reporter_id )
			{
				if ( $this->_mail_auto_signup )
				{
					// So, we have to sign up a new user...
					$t_new_reporter_name = $this->prepare_username( $p_parsed_from );

					// user_is_name_valid is performed twice (one time in mail_prepare_username
					// and one time here because the name could be the email address if the
					// username already failed)
					if ( user_is_name_valid( $t_new_reporter_name ) && user_is_name_unique( $t_new_reporter_name ) && email_is_valid( $p_parsed_from[ 'email' ] ) )
					{
						if( user_signup( $t_new_reporter_name, $p_parsed_from[ 'email' ] ) )
						{
							# notify the selected group a new user has signed-up
							email_notify_new_account( $t_new_reporter_name, $p_parsed_from[ 'email' ] );

							$t_reporter_id = user_get_id_by_email( $p_parsed_from[ 'email' ] );
							$t_reporter_name = $t_new_reporter_name;
						}
					}

					if ( !$t_reporter_id )
					{
						echo 'Failed to create user based on: ' . implode( ' - ', $p_parsed_from ) . "\n" . 'Falling back to the mail_reporter' . "\n";
					}
				}

				if ( !$t_reporter_id )
				{
					// Fall back to the default mail_reporter
					$t_reporter_id = $this->_mail_reporter_id;
				}
			}
			else
			{
			}
		}

		if ( !isset( $t_reporter_name ) )
		{
			$t_reporter_name = user_get_name( $t_reporter_id );
		}

		echo 'Reporter: ' . $t_reporter_id . ' - ' . $p_parsed_from[ 'email' ] . "\n\n";
		auth_attempt_script_login( $t_reporter_name );

		return( $t_reporter_id );
	}

	# --------------------
	# Adds a bug which is reported via email
	# Taken from bug_report.php in MantisBT 1.2.0rc1
	private function add_bug( &$p_mail, $p_overwrite_project_id = FALSE )
	{
		if ( $this->mail_is_a_bugnote( $p_mail[ 'Subject' ] ) )
		{
			$t_description = $p_mail[ 'X-Mantis-Body' ];

			$t_bug_id = $this->get_bug_id_from_subject( $p_mail[ 'Subject' ] );

			$t_description = $this->identify_reply_part( $t_description );
			$t_description = $this->apply_mail_save_from( $p_mail[ 'From' ], $t_description );

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
		else
		{
			$this->fix_empty_fields( $p_mail );

			$t_bug_data = new BugData;
			$t_bug_data->build					= '';
			$t_bug_data->platform				= '';
			$t_bug_data->os						= '';
			$t_bug_data->os_build				= '';
			$t_bug_data->version				= '';
			$t_bug_data->profile_id				= 0;
			$t_bug_data->handler_id				= 0;
			$t_bug_data->view_state				= $this->_default_bug_view_status;

			$t_bug_data->category_id			= $p_mailbox[ 'mailbox_global_category' ];
			$t_bug_data->reproducibility		= $this->_default_bug_reproducibility;
			$t_bug_data->severity				= $this->_default_bug_severity;
			$t_bug_data->priority				= $p_mail[ 'Priority' ];
			$t_bug_data->projection				= $this->_default_bug_projection;
			$t_bug_data->eta					= $this->_default_bug_eta;
			$t_bug_data->resolution				= $this->_default_bug_resolution;
			$t_bug_data->status					= $this->_bug_submit_status;
			$t_bug_data->summary				= $p_mail[ 'Subject' ];

			$t_bug_data->description			= $this->apply_mail_save_from( $p_mail[ 'From' ], $p_mail[ 'X-Mantis-Body' ] );

			$t_bug_data->steps_to_reproduce		= $this->_default_bug_steps_to_reproduce;
			$t_bug_data->additional_information	= $this->_default_bug_additional_info;
			$t_bug_data->due_date 				= date_get_null();

			$t_bug_data->project_id				= ( ( $p_overwrite_project_id === FALSE ) ? $this->mailbox[ 'mailbox_project' ] : $p_overwrite_project_id );

			$t_bug_data->reporter_id			= $p_email[ 'Reporter_id' ];

			# Create the bug
			$t_bug_id = $t_bug_data->create();

			email_new_bug( $t_bug_id );
		}
		
		# Add files
		if ( $this->_allow_file_upload )
		{
			if ( NULL != $p_mail[ 'X-Mantis-Parts' ] )
			{
				$t_rejected_files = NULL;

				$this->_file_number = 1;

				foreach ( $p_mail[ 'X-Mantis-Parts' ] as $part )
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
						echo 'Failed to add "' . $part[ 'name' ] . '" to the issue. See below for all errors.' . "\n" . $part[ 'body' ];
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
			return( $t_part_name . ' = filename is missing' . "\n" );
		}
		elseif ( !file_type_check( $t_part_name ) )
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
			while ( !file_is_name_unique( $this->_file_number . '-' . $t_part_name, $p_bug_id ) )
			{
				$this->_file_number++;
			}

			$t_file_name = $this->_mail_tmp_directory . '/' . md5( microtime() );

			file_put_contents( $t_file_name, $p_part[ 'body' ] );

			ERP_custom_file_add( $p_bug_id, array(
				'tmp_name' => realpath( $t_file_name ),
				'name'     => $this->_file_number . '-' . $t_part_name,
				'type'     => $p_part[ 'ctype' ],
				'error'    => NULL
			), 'bug' );

			if ( is_file( $t_file_name ) )
			{
				unlink( $t_file_name );
			}

			$this->_file_number++;
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
		$this->_mailbox[ 'mailbox_hostname' ] = ERP_correct_hostname_port( $this->_mailbox[ 'mailbox_hostname' ] );

		if ( $this->_mailbox[ 'mailbox_encryption' ] !== 'None' && extension_loaded( 'openssl' ) )
		{
			$this->_mailbox[ 'mailbox_hostname' ][ 'hostname' ] = strtolower( $this->_mailbox[ 'mailbox_encryption' ] ) . '://' . $this->_mailbox[ 'mailbox_hostname' ][ 'hostname' ];

			$t_mailbox_port_index = 'encrypted';
		}
		else
		{
			$t_mailbox_port_index = 'normal';
		}

		$this->_mailbox[ 'mailbox_hostname' ][ 'port' ] = (int) $this->_mailbox[ 'mailbox_hostname' ][ 'port' ];
		if ( $this->_mailbox[ 'mailbox_hostname' ][ 'port' ] <= 0 )
		{
			$this->_mailbox[ 'mailbox_hostname' ][ 'port' ] = (int) $this->_default_ports[ $this->_mailbox[ 'mailbox_type' ] ][ $t_mailbox_port_index ];
		}
	}

	# --------------------
	# Validate the email address
	#  caching is only performed when mx records are checked
	private function validate_email_address( $p_email_address )
	{
		if ( $this->_validate_email )
		{
			// Lets see if the email address is valid and maybe we already have a cached result
			if ( $this->_check_mx_record && isset( $this->_validated_email_list[ $p_email_address ] ) )
			{
				$t_valid = $this->_validated_email_list[ $p_email_address ];
			}
			elseif ( email_is_valid( $p_email_address ) )
			{
				$t_valid = TRUE;
			}
			else
			{
				$t_valid = FALSE;
			}

			if ( $this->_check_mx_record )
			{
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
				'username' => trim( $matches[ 1 ], '"\' ' ),
				'email'    => trim( $matches[ 2 ] ),
			);
		}
		else
		{
			$v_from_address = array(
				'username' => '',
				'email'    => $p_from_address,
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
		# it's a config any mantis installation could have a different one
		if ( user_is_name_valid( $p_user_info[ 'username' ] ) )
		{
			return( $p_user_info[ 'username' ] );
		}

		return( strtolower( str_replace( array( '@', '.', '-' ), '_', $p_user_info[ 'email' ] ) ) );
	}

	# --------------------
	# return true if there is a valid mantis bug refererence in subject or return false if not found
	private function mail_is_a_bugnote( $p_mail_subject )
	{
		if ( preg_match( "/\[(.*?\s[0-9]{1,7})\]/u", $p_mail_subject ) )
		{
			$t_bug_id = $this->get_bug_id_from_subject( $p_mail_subject );

			if ( bug_exists( $t_bug_id ) && !bug_is_readonly( $t_bug_id ) )
			{
				return( TRUE );
			}
		}

		return( FALSE );
	}

	# --------------------
	# return the bug's id from the subject
	private function get_bug_id_from_subject( $p_mail_subject )
	{
		preg_match( "/\[.*?\s([0-9]{1,7}?)\]/u", $p_mail_subject, $v_matches );

		return( $v_matches[ 1 ] );
	}
	
	# --------------------
	# Saves the complete email to file
	# Only works in debug mode
	private function save_message_to_file( &$p_msg )
	{
		if ( $this->_mail_debug && is_dir( $this->_mail_debug_directory ) && is_writeable( $this->_mail_debug_directory ) )
		{
			$t_file_name = $this->_mail_debug_directory . '/' . time() . '_' . md5( microtime() );
			file_put_contents( $t_file_name, $p_msg );
		}
	}

	# --------------------
	# Removes the original mantis email from replies
	private function identify_reply_part( $p_description )
	{
		if ( $this->_mail_identify_reply )
		{
			# The pear mimeDecode.php seems to be removing the last "=" in some versions of the pear package.
			# the version delivered with this package seems to be working OK though but just to be sure
			$t_email_separator1 = substr( $this->_email_separator1, 0, -1 );

			$t_first_occurence = strpos( $p_description, $t_email_separator1 );
			if ( $t_first_occurence !== FALSE && substr_count( $p_description, $t_email_separator1, $t_first_occurence ) >= 5 )
			{
				$t_description = substr( $p_description, 0, $t_first_occurence ) . $this->_mail_removed_reply_text;

				return( $t_description );
			}
		}

		return( $p_description );
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
}
?>
