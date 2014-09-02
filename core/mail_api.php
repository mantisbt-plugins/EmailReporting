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

	require_once( config_get_global( 'absolute_path' ) . 'api/soap/mc_file_api.php' );

	require_once( 'Net/POP3.php' );
	require_once( 'Net/IMAP.php' );

	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Mail/Parser.php' );

class ERP_mailbox_api
{
	private $_functionality_enabled = FALSE;
	private $_test_only = FALSE;

	public $_mailbox = array( 'description' => 'INITIALIZATION PHASE' );

	private $_mailserver = NULL;
	private $_result = TRUE;

	private $_default_ports = array(
		'POP3' => array( 'normal' => 110, 'encrypted' => 995 ),
		'IMAP' => array( 'normal' => 143, 'encrypted' => 993 ),
	);

	private $_mails_fetched = 0;

	private $_validated_email_list = array();

	private $_mail_add_bug_reports;
	private $_mail_add_bugnotes;
	private $_mail_add_complete_email;
	private $_mail_add_users_from_cc_to;
	private $_mail_auto_signup;
	private $_mail_block_attachments_md5;
	private $_mail_block_attachments_logging;
	private $_mail_bug_priority;
	private $_mail_debug;
	private $_mail_debug_directory;
	private $_mail_debug_show_memory_usage;
	private $_mail_delete;
	private $_mail_disposable_email_checker;
	private $_mail_fallback_mail_reporter;
	private $_mail_fetch_max;
	private $_mail_nodescription;
	private $_mail_nosubject;
	private $_mail_preferred_username;
	private $_mail_preferred_realname;
	private $_mail_remove_mantis_email;
	private $_mail_remove_replies;
	private $_mail_remove_replies_after;
	private $_mail_removed_reply_text;
	private $_mail_reporter_id;
	private $_mail_save_from;
	private $_mail_save_subject_in_note;
	private $_mail_strip_gmail_style_replies;
	private $_mail_strip_signature;
	private $_mail_strip_signature_delim;
	private $_mail_subject_id_regex;
	private $_mail_use_bug_priority;
	private $_mail_use_reporter;

	private $_mp_options = array();

	private $_allow_file_upload;
	private $_file_upload_method;
	private $_email_separator1;
	private $_login_method;
	private $_use_ldap_email;

	private $_max_file_size;
	private $_memory_limit;

	# --------------------
	# Retrieve all necessary configuration options
	public function __construct( $p_test_only = FALSE )
	{
		$this->_test_only = $p_test_only;

		$this->_mail_add_bug_reports			= plugin_config_get( 'mail_add_bug_reports' );
		$this->_mail_add_bugnotes				= plugin_config_get( 'mail_add_bugnotes' );
		$this->_mail_add_complete_email			= plugin_config_get( 'mail_add_complete_email' );
		$this->_mail_add_users_from_cc_to		= plugin_config_get( 'mail_add_users_from_cc_to' );
		$this->_mail_auto_signup				= plugin_config_get( 'mail_auto_signup' );
		$this->_mail_block_attachments_md5		= plugin_config_get( 'mail_block_attachments_md5' );
		$this->_mail_block_attachments_logging	= plugin_config_get( 'mail_block_attachments_logging' );
		$this->_mail_bug_priority				= plugin_config_get( 'mail_bug_priority' );
		$this->_mail_debug						= plugin_config_get( 'mail_debug' );
		$this->_mail_debug_directory			= plugin_config_get( 'mail_debug_directory' );
		$this->_mail_debug_show_memory_usage	= plugin_config_get( 'mail_debug_show_memory_usage' );
		$this->_mail_delete						= plugin_config_get( 'mail_delete' );
		$this->_mail_disposable_email_checker	= plugin_config_get( 'mail_disposable_email_checker' );
		$this->_mail_fallback_mail_reporter		= plugin_config_get( 'mail_fallback_mail_reporter' );
		$this->_mail_fetch_max					= plugin_config_get( 'mail_fetch_max' );
		$this->_mail_nodescription				= plugin_config_get( 'mail_nodescription' );
		$this->_mail_nosubject					= plugin_config_get( 'mail_nosubject' );
		$this->_mail_preferred_username			= plugin_config_get( 'mail_preferred_username' );
		$this->_mail_preferred_realname			= plugin_config_get( 'mail_preferred_realname' );
		$this->_mail_remove_mantis_email		= plugin_config_get( 'mail_remove_mantis_email' );
		$this->_mail_remove_replies				= plugin_config_get( 'mail_remove_replies' );
		$this->_mail_remove_replies_after		= plugin_config_get( 'mail_remove_replies_after' );
		$this->_mail_removed_reply_text			= plugin_config_get( 'mail_removed_reply_text' );
		$this->_mail_reporter_id				= plugin_config_get( 'mail_reporter_id' );
		$this->_mail_save_from					= plugin_config_get( 'mail_save_from' );
		$this->_mail_save_subject_in_note		= plugin_config_get( 'mail_save_subject_in_note' );
		$this->_mail_strip_gmail_style_replies	= plugin_config_get( 'mail_strip_gmail_style_replies' );
		$this->_mail_strip_signature			= plugin_config_get( 'mail_strip_signature' );
		$this->_mail_strip_signature_delim		= plugin_config_get( 'mail_strip_signature_delim' );
		$this->_mail_subject_id_regex			= plugin_config_get( 'mail_subject_id_regex' );
		$this->_mail_use_bug_priority			= plugin_config_get( 'mail_use_bug_priority' );
		$this->_mail_use_reporter				= plugin_config_get( 'mail_use_reporter' );

		$this->_mp_options[ 'add_attachments' ]	= config_get( 'allow_file_upload' );
		$this->_mp_options[ 'debug' ]			= $this->_mail_debug;
		$this->_mp_options[ 'show_mem_usage' ]	= $this->_mail_debug_show_memory_usage;
		$this->_mp_options[ 'parse_html' ]		= plugin_config_get( 'mail_parse_html' );

		$this->_allow_file_upload				= config_get( 'allow_file_upload' );
		$this->_file_upload_method				= config_get( 'file_upload_method' );
		$this->_email_separator1				= config_get( 'email_separator1' );
		$this->_login_method					= config_get( 'login_method' );
		$this->_use_ldap_email					= config_get( 'use_ldap_email' );

		$this->_max_file_size					= (int) min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );

		if ( !$this->_test_only && $this->_mail_debug )
		{
			$this->_memory_limit = ini_get( 'memory_limit' );
		}

		// Do we need to temporarily enable emails on a users own actions?
		$t_mail_email_receive_own				= plugin_config_get( 'mail_email_receive_own' );
		if ( $t_mail_email_receive_own )
		{
			ERP_set_temporary_overwrite( 'email_receive_own', ON );
		}

		$this->_functionality_enabled = TRUE;

		// Because of a notice level error in core/email_api.php on line 516 in MantisBT 1.2.0 we need to fill this value
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
				// Check whether EmailReporting supports the mailbox type. The check is based on available default ports
				if ( isset( $this->_default_ports[ $this->_mailbox[ 'mailbox_type' ] ] ) )
				{
					if ( $this->check_fetch_max() === FALSE )
					{
						if ( project_exists( $this->_mailbox[ 'project_id' ] ) )
						{
							if ( category_exists( $this->_mailbox[ 'global_category_id' ] ) )
							{
								$t_upload_folder_passed = TRUE;

								if ( $this->_allow_file_upload && $this->_file_upload_method == DISK )
								{
									$t_upload_folder_passed = FALSE;

									$t_file_path = project_get_field( $this->_mailbox[ 'project_id' ], 'file_path' );
									if( $t_file_path == '' )
									{
										$t_file_path = config_get( 'absolute_path_default_upload_folder' );
									}

									$t_file_path = ERP_prepare_directory_string( $t_file_path, TRUE );
									$t_real_file_path = ERP_prepare_directory_string( $t_file_path );

									if( !file_exists( $t_file_path ) || !is_dir( $t_file_path ) || !is_writable( $t_file_path ) || !is_readable( $t_file_path ) )
									{
										$this->custom_error( 'Upload folder is not writable: ' . $t_file_path . "\n" );
									}
									elseif ( strcasecmp( $t_real_file_path, $t_file_path ) !== 0 )
									{
										$this->custom_error( 'Upload folder is not an absolute path' . "\n" .
											'Upload folder: ' . $t_file_path . "\n" .
											'Absolute path: ' . $t_real_file_path . "\n" );
									}
									else
									{
										$t_upload_folder_passed = TRUE;
									}
								}

								if ( $t_upload_folder_passed )
								{
									$this->prepare_mailbox_hostname();

									if ( !$this->_test_only && $this->_mail_debug )
									{
										var_dump( $this->_mailbox );
									}

									$this->show_memory_usage( 'Start process mailbox' );

									$t_process_mailbox_function = 'process_' . strtolower( $this->_mailbox[ 'mailbox_type' ] ) . '_mailbox';

									$this->$t_process_mailbox_function();

									$this->show_memory_usage( 'Finished process mailbox' );
								}
							}
							else
							{
								$this->custom_error( 'Category does not exist' );
							}
						}
						else
						{
							$this->custom_error( 'Project does not exist' );
						}
					}
					else
					{
						$this->custom_error( 'Maximum number of emails retrieved for this session. Waiting for next scheduled job run' );
					}
				}
				else
				{
					$this->custom_error( 'Unknown mailbox type' );
				}
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
	private function pear_error( $p_location, &$p_pear )
	{
		if ( PEAR::isError( $p_pear ) )
		{
			$this->_result = &$p_pear;

			if ( !$this->_test_only )
			{
				echo "\n\n" . 'Mailbox: ' . $this->_mailbox[ 'description' ] . "\n" . 'Location: ' . $p_location . "\n" . $p_pear->toString() . "\n";
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
	private function custom_error( $p_error_text, $p_is_error = TRUE )
	{
		$t_error_text = 'Message: ' . $p_error_text . "\n";

		if ( $p_is_error === TRUE )
		{
			$this->_result = array(
				'ERROR_TYPE'	=> 'NON-PEAR-ERROR',
				'ERROR_MESSAGE'	=> $t_error_text,
			);
		}

		if ( !$this->_test_only )
		{
			echo "\n\n" . 'Mailbox: ' . $this->_mailbox[ 'description' ] . "\n" . $t_error_text;
		}
	}

	# --------------------
	# process all mails for a pop3 mailbox
	private function process_pop3_mailbox()
	{
		$this->_mailserver = new Net_POP3();

		$t_connectresult = $this->_mailserver->connect( $this->_mailbox[ 'hostname' ], $this->_mailbox[ 'port' ] );

		if ( $t_connectresult === TRUE )
		{
			$t_loginresult = $this->mailbox_login();

			if ( $this->_test_only === FALSE && !$this->pear_error( 'Attempt login', $t_loginresult ) )
			{
				if ( project_get_field( $this->_mailbox[ 'project_id' ], 'enabled' ) == TRUE )
				{
					$t_numMsg = $this->_mailserver->numMsg();
					if ( !$this->pear_error( 'Retrieve number of messages', $t_numMsg ) )
					{
						$t_numMsg = $this->check_fetch_max( $t_numMsg );

						for ( $i = 1; $i <= $t_numMsg; $i++ )
						{
							$t_emailresult = $this->process_single_email( $i );

							if ( $this->_mail_delete && $t_emailresult )
							{
								$t_deleteresult = $this->_mailserver->deleteMsg( $i );

								$this->pear_error( 'Attempt delete email', $t_deleteresult );
							}
						}
					}
				}
				else
				{
					$this->custom_error( 'Project is disabled: ' . project_get_field( $this->_mailbox[ 'project_id' ], 'name' ) );
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
			$t_loginresult = $this->mailbox_login();

			if ( !$this->pear_error( 'Attempt login', $t_loginresult ) )
			{
				// If basefolder is empty we try to select the inbox folder
				if ( is_blank( $this->_mailbox[ 'imap_basefolder' ] ) )
				{
					$this->_mailbox[ 'imap_basefolder' ] = $this->_mailserver->getCurrentMailbox();
				}

				if ( $this->_mailserver->mailboxExist( $this->_mailbox[ 'imap_basefolder' ] ) )
				{
					if ( $this->_test_only === FALSE )
					{
						// There does not seem to be a viable api function which removes this plugins dependability on table column names
						// So if a column name is changed it might cause problems if the code below depends on it.
						// Luckily we only depend on id, name and enabled
						if ( $this->_mailbox[ 'imap_createfolderstructure' ] === TRUE )
						{
							$t_projects = project_get_all_rows();
							$t_hierarchydelimiter = $this->_mailserver->getHierarchyDelimiter();
						}
						else
						{
							$t_projects = array( 0 => project_get_row( $this->_mailbox[ 'project_id' ] ) );
						}

						foreach ( $t_projects AS $t_project )
						{
							if ( $t_project[ 'enabled' ] == TRUE )
							{
								if ( $this->check_fetch_max() === FALSE )
								{
									$t_project_name = $this->cleanup_project_name( $t_project[ 'name' ] );

									$t_foldername = $this->_mailbox[ 'imap_basefolder' ] . ( ( $this->_mailbox[ 'imap_createfolderstructure' ] ) ? $t_hierarchydelimiter . $t_project_name : NULL );

									// We don't need to check twice whether the mailbox exist incase createfolderstructure is false
									if ( !$this->_mailbox[ 'imap_createfolderstructure' ] || $this->_mailserver->mailboxExist( $t_foldername ) === TRUE )
									{
										$this->_mailserver->selectMailbox( $t_foldername );

										$t_numMsg = $this->_mailserver->numMsg();

										if ( !$this->pear_error( 'Retrieve number of messages', $t_numMsg ) )
										{
											// check_fetch_max not performed here as $t_numMsg could contain emails marked as deleted.
											for ( $i = 1; $i <= $t_numMsg; $i++ )
											{
												if ( $this->check_fetch_max() === TRUE )
												{
													break 2;
												}
												elseif ( $this->_mailserver->isDeleted( $i ) === TRUE )
												{
													// Email marked as deleted. Do nothing
												}
												else
												{
													$t_emailresult = $this->process_single_email( $i, (int) $t_project[ 'id' ] );

													if ( $t_emailresult === TRUE )
													{
														$t_deleteresult = $this->_mailserver->deleteMsg( $i );

														$this->pear_error( 'Attempt delete email', $t_deleteresult );
													}
												}
											}
										}
									}
									elseif ( $this->_mailbox[ 'imap_createfolderstructure' ] === TRUE )
									{
										// create this mailbox
										$this->_mailserver->createMailbox( $t_foldername );
									}
								}
							}
							else
							{
								$this->custom_error( 'Project is disabled: ' . $t_project[ 'name' ] );
							}
						}
					}
				}
				else
				{
					$this->custom_error( 'IMAP basefolder not found' );
				}
			}

			//$t_mailbox->expunge(); //disabled as this is handled by the disconnect

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
		$t_mailbox_username = $this->_mailbox[ 'erp_username' ];
		$t_mailbox_password = base64_decode( $this->_mailbox[ 'erp_password' ] );
		$t_mailbox_auth_method = $this->_mailbox[ 'auth_method' ];

		return( $this->_mailserver->login( $t_mailbox_username, $t_mailbox_password, $t_mailbox_auth_method ) );
	}

	# --------------------
	# Process a single email from either a pop3 or imap mailbox
	# Returns true or false based on succesfull email retrieval from the mailbox
	private function process_single_email( $p_i, $p_overwrite_project_id = FALSE )
	{
		$this->show_memory_usage( 'Start process single email' );

		$t_msg = $this->getMsg( $p_i );

		if ( empty( $t_msg ) || $this->pear_error( 'Retrieve raw message', $t_msg ) )
		{
			if ( empty( $t_msg ) )
			{
				$this->custom_error( 'Retrieved message was empty. Either an invalid message ID was passed or there is a problem with one of the required PEAR packages' );
			}

			$this->pear_error( 'Retrieve raw message', $t_msg );

			return( FALSE );
		}

		$this->show_memory_usage( 'Single email retrieved from mailbox' );

		$this->_mails_fetched++;

		$this->save_message_to_file( 'raw_msg', $t_msg );

		$t_email = $this->parse_content( $t_msg );

		unset( $t_msg );

		$this->show_memory_usage( 'Parsed single email' );

		$this->save_message_to_file( 'parsed_msg', $t_email );

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
				$this->custom_error( 'From email address rejected by email_is_valid function based on: ' . $t_email[ 'From_parsed' ][ 'From' ] );
			}
		}

		$this->show_memory_usage( 'Finished process single email' );

		return( TRUE );
	}

	# --------------------
	# Return a single raw email
	# Handles a workaround for problems with Net_IMAP 1.1.0 and 1.1.2
	private function getMsg( $p_msg_id )
	{
		if ( $this->_mailbox[ 'mailbox_type' ] === 'IMAP' )
		{
			// Net_IMAP 1.1.0 and 1.1.2 seems to have a somewhat broken getMsg function.
			$t_msg = $this->_mailserver->getMessages( $p_msg_id, TRUE );

			if ( is_array( $t_msg ) && count( $t_msg ) === 1 )
			{
				$t_msg = $t_msg[ key( $t_msg ) ];
			}
		}
		else
		{
			$t_msg = $this->_mailserver->getMsg( $p_msg_id );
		}

		return( $t_msg );
	}

	# --------------------
	# parse the email using mimeDecode for Mantis
	private function parse_content( &$p_msg )
	{
		$this->show_memory_usage( 'Start Mail Parser' );

		$t_mp = new ERP_Mail_Parser( $this->_mp_options );

		$t_mp->setInputString( $p_msg );

		if ( $this->_mail_add_complete_email )
		{
			$t_part = array(
				'name' => 'Complete email.txt',
				'ctype' => 'text/plain',
				'body' => $p_msg,
			);
		}

		$p_msg = NULL;

		$t_mp->parse();

		$t_email[ 'From_parsed' ] = $this->parse_address( trim( $t_mp->from() ) );
		$t_email[ 'Reporter_id' ] = $this->get_user( $t_email[ 'From_parsed' ] );

		$t_email[ 'Subject' ] = trim( $t_mp->subject() );

		$t_email[ 'To' ] = $t_mp->to();
		$t_email[ 'Cc' ] = $t_mp->cc();

		$t_email[ 'X-Mantis-Body' ] = trim( $t_mp->body() );

		$t_email[ 'X-Mantis-Parts' ] = $t_mp->parts();

		if ( $this->_mail_use_bug_priority )
		{
			$t_email[ 'Priority' ] = $this->_mail_bug_priority[ strtolower( $t_mp->priority() ) ];
		}
		else
		{
			$t_email[ 'Priority' ] = config_get( 'default_bug_priority' );
		}

		if ( $this->_mail_add_complete_email )
		{
			$t_email[ 'X-Mantis-Parts' ][] = $t_part;

			unset( $t_part );
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
			$t_reporter_id = $this->get_userid_from_email( $p_parsed_from[ 'email' ] );

			if ( !$t_reporter_id )
			{
				if ( $this->_mail_auto_signup )
				{
					// So, we have to sign up a new user...
					$t_new_reporter_name = $this->prepare_username( $p_parsed_from );

					if ( $t_new_reporter_name !== FALSE && $this->validate_email_address( $p_parsed_from[ 'email' ] ) )
					{
						if( user_signup( $t_new_reporter_name, $p_parsed_from[ 'email' ] ) )
						{
							# notify the selected group a new user has signed-up
							email_notify_new_account( $t_new_reporter_name, $p_parsed_from[ 'email' ] );

							$t_reporter_id = user_get_id_by_email( $p_parsed_from[ 'email' ] );
							$t_reporter_name = $t_new_reporter_name;

							$t_realname = $this->prepare_realname( $p_parsed_from, $t_reporter_name );

							if ( $t_realname !== FALSE )
							{
								user_set_realname( $t_reporter_id, $t_realname );
							}
						}
					}

					if ( !$t_reporter_id )
					{
						$this->custom_error( 'Failed to create user based on: ' . $p_parsed_from[ 'From' ] );
					}
				}
			}

			if ( ( !$t_reporter_id || !user_is_enabled( $t_reporter_id ) ) && $this->_mail_fallback_mail_reporter )
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

			$t_authattemptresult = auth_attempt_script_login( $t_reporter_name );

			# last attempt for fallback
			if ( $t_authattemptresult === FALSE && $this->_mail_fallback_mail_reporter && $t_reporter_id != $this->_mail_reporter_id && user_is_enabled( $this->_mail_reporter_id ) )
			{
				$t_reporter_id = $this->_mail_reporter_id;
				$t_reporter_name = user_get_field( $t_reporter_id, 'username' );
				$t_authattemptresult = auth_attempt_script_login( $t_reporter_name );
			}

			if ( $t_authattemptresult === TRUE )
			{
				user_update_last_visit( $t_reporter_id );

				return( (int) $t_reporter_id );
			}
		}

		// Normally this function does not get here unless all else failed
		$this->custom_error( 'Could not get a valid reporter. Email will be ignored' );

		return( FALSE );
	}

	# --------------------
	# Try to obtain an existing userid based on an email address
	private function get_userid_from_email( $p_email_address )
	{
		$t_reporter_id = FALSE;
		
		if ( $this->_use_ldap_email )
		{
			$t_username = $this->ldap_get_username_from_email( $p_email_address );

			if ( $t_username !== NULL && user_is_name_valid( $t_username ) )
			{
				$t_reporter_id = user_get_id_by_name( $t_username );
			}
		}

		if ( !$t_reporter_id )
		{
			$t_reporter_id = user_get_id_by_email( $p_email_address );
		}

		return( $t_reporter_id );
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
			$t_description = $this->strip_signature( $t_description );
			$t_description = $this->add_additional_info( 'note', $p_email, $t_description );

			$t_project_id = bug_get_field( $t_bug_id, 'project_id' );
			ERP_set_temporary_overwrite( 'project_override', $t_project_id );

			# Event integration
			# Core mantis event already exists within bugnote_add function
			$t_description = event_signal( 'EVENT_ERP_BUGNOTE_DATA', $t_description, $t_bug_id );

			if ( bug_is_resolved( $t_bug_id ) )
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

			$t_project_id = ( ( $p_overwrite_project_id === FALSE ) ? $this->_mailbox[ 'project_id' ] : $p_overwrite_project_id );
			ERP_set_temporary_overwrite( 'project_override', $t_project_id );

			$t_bug_data = new BugData;
			$t_bug_data->build					= '';
			$t_bug_data->platform				= '';
			$t_bug_data->os						= '';
			$t_bug_data->os_build				= '';
			$t_bug_data->version				= '';
			$t_bug_data->profile_id				= 0;
			$t_bug_data->handler_id				= 0;
			$t_bug_data->view_state				= (int) config_get( 'default_bug_view_status' );

			$t_bug_data->category_id			= (int) $this->_mailbox[ 'global_category_id' ];
			$t_bug_data->reproducibility		= (int) config_get( 'default_bug_reproducibility' );
			$t_bug_data->severity				= (int) config_get( 'default_bug_severity' );
			$t_bug_data->priority				= (int) $p_email[ 'Priority' ];
			$t_bug_data->projection				= (int) config_get( 'default_bug_projection' );
			$t_bug_data->eta					= (int) config_get( 'default_bug_eta' );
			$t_bug_data->resolution				= config_get( 'default_bug_resolution' );
			$t_bug_data->status					= config_get( 'bug_submit_status' );
			$t_bug_data->summary				= $p_email[ 'Subject' ];

			$t_description = $p_email[ 'X-Mantis-Body' ];
			$t_description = $this->strip_signature( $t_description );
			$t_description = $this->add_additional_info( 'issue', $p_email, $t_description );
			$t_bug_data->description			= $t_description;

			$t_bug_data->steps_to_reproduce		= config_get( 'default_bug_steps_to_reproduce' );
			$t_bug_data->additional_information	= config_get( 'default_bug_additional_info' );
			$t_bug_data->due_date				= date_get_null();

			$t_bug_data->project_id				= $t_project_id;

			$t_bug_data->reporter_id			= $p_email[ 'Reporter_id' ];

			// This function might do stuff that EmailReporting cannot handle. Disabled
			//helper_call_custom_function( 'issue_create_validate', array( $t_bug_data ) );

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
			// Not allowed to add issues and not allowed / able to add notes. Need to stop processing
			$this->custom_error( 'Not allowed to create a new issue. Email ignored' );
			return;
		}

		$this->custom_error( 'Reporter: ' . $p_email[ 'Reporter_id' ] . ' - ' . $p_email[ 'From_parsed' ][ 'email' ] . ' --> Issue ID: #' . $t_bug_id, FALSE );

		$this->show_memory_usage( 'Finished add bug' );

		$this->show_memory_usage( 'Start processing attachments' );

		# Add files
		if ( $this->_allow_file_upload )
		{
			if ( count( $p_email[ 'X-Mantis-Parts' ] ) > 0 )
			{
				$t_rejected_files = NULL;

				while ( $t_part = array_shift( $p_email[ 'X-Mantis-Parts' ] ) )
				{
					$t_file_rejected = $this->add_file( $t_bug_id, $t_part );

					if ( $t_file_rejected !== TRUE )
					{
						$t_rejected_files .= $t_file_rejected;
					}
				}

				if ( $t_rejected_files !== NULL )
				{
					$t_part = array(
						'name' => 'Rejected files.txt',
						'ctype' => 'text/plain',
						'body' => 'List of rejected files' . "\n\n" . $t_rejected_files,
					);

					$t_reject_rejected_files = $this->add_file( $t_bug_id, $t_part );

					if ( $t_reject_rejected_files !== TRUE )
					{
						$t_part[ 'body' ] .= $t_reject_rejected_files;
						$this->custom_error( 'Failed to add "' . $t_part[ 'name' ] . '" to the issue. See below for all errors.' . "\n" . $t_part[ 'body' ] );
					}
				}
			}
		}

		//Add the users in Cc and To list in mail header
		$this->add_monitors( $t_bug_id, $p_email );

		ERP_set_temporary_overwrite( 'project_override', NULL );

		$this->show_memory_usage( 'Finished processing attachments' );
	}

	# --------------------
	# Very dirty: Adds a file to a bug.
	# returns true on success and the filename with reason on error
	private function add_file( $p_bug_id, &$p_part )
	{
		# Handle the file upload
		$t_part_name = ( ( isset( $p_part[ 'name' ] ) ) ? trim( $p_part[ 'name' ] ) : NULL );
		$t_strlen_body = strlen( $p_part[ 'body' ] );

		if ( is_blank( $t_part_name ) )
		{
			$t_part_name = md5( microtime() ) . '.erp';
		}

		$t_body_md5 = ( ( !empty( $this->_mail_block_attachments_md5 ) ) ? md5( $p_part[ 'body' ] ) : NULL );

		if ( !file_type_check( $t_part_name ) )
		{
			return( $t_part_name . ' = filetype not allowed' . "\n" );
		}
		elseif ( 0 === $t_strlen_body )
		{
			return( $t_part_name . ' = attachment size is zero (0 / ' . $this->_max_file_size . ')' . "\n" );
		}
		elseif ( $t_strlen_body > $this->_max_file_size )
		{
			return( $t_part_name . ' = attachment size exceeds maximum allowed file size (' . $t_strlen_body . ' / ' . $this->_max_file_size . ')' . "\n" );
		}
		elseif ( in_array( $t_body_md5, $this->_mail_block_attachments_md5, TRUE ) )
		{
			if ( $this->_mail_block_attachments_logging )
			{
				return( $t_part_name . ' = attachment refused as it matched the md5 on the attachment blocklist (' . $t_body_md5 . ')' . "\n" );
			}
			else
			{
				return( TRUE );
			}
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

			mci_file_add( $p_bug_id, $t_opt_name . $t_part_name, $p_part[ 'body' ], $p_part[ 'ctype' ], 'bug' );
		}

		return( TRUE );
	}

	# --------------------
	# return whether the current process has reached the mail_fetch_max parameter
	# $p_numMsg either left empty or contains the number of emails EmailReporting would like to process
	#  integer will be the number of emails EmailReporting is still allowed and able to process
	#  boolean will be true or false depending on whether or not the maximum number of emails processed has been reached
	private function check_fetch_max( $p_numMsg = FALSE )
	{
		if ( ( $this->_mails_fetched ) >= $this->_mail_fetch_max )
		{
			$t_numMsg_allowed = ( ( $p_numMsg === FALSE ) ? TRUE : 0 );
		}
		else
		{
			$t_numMsg_allowed = ( ( $p_numMsg === FALSE ) ? FALSE : min( $p_numMsg, ( $this->_mail_fetch_max - $this->_mails_fetched ) ) );
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
		$t_def_mailbox_port_index = 'normal';

		if ( $this->_mailbox[ 'encryption' ] !== 'None' )
		{
			if ( extension_loaded( 'openssl' ) )
			{
				$this->_mailbox[ 'hostname' ] = strtolower( $this->_mailbox[ 'encryption' ] ) . '://' . $this->_mailbox[ 'hostname' ];

				$t_def_mailbox_port_index = 'encrypted';
			}
			else
			{
				$this->custom_error( 'OpenSSL plugin not available even though the mailbox is configured to use it. Please check whether OpenSSL is properly being loaded' );
			}
		}

		$this->_mailbox[ 'port' ] = (int) $this->_mailbox[ 'port' ];
		if ( $this->_mailbox[ 'port' ] <= 0 )
		{
			$this->_mailbox[ 'port' ] = (int) $this->_default_ports[ $this->_mailbox[ 'mailbox_type' ] ][ $t_def_mailbox_port_index ];
		}
	}

	# --------------------
	# Validate the email address
	private function validate_email_address( $p_email_address )
	{
		// Lets see if the email address is valid and maybe we already have a cached result
		if ( isset( $this->_validated_email_list[ $p_email_address ] ) )
		{
			$t_valid = $this->_validated_email_list[ $p_email_address ];
		}
		else
		{
			$t_valid = ( email_is_valid( $p_email_address ) && ( $this->_mail_disposable_email_checker === OFF || !email_is_disposable( $p_email_address ) ) );

			$this->_validated_email_list[ $p_email_address ] = $t_valid;
		}

		return( $t_valid );
	}

	# --------------------
	# return the mailadress from the mail's 'From'
	private function parse_address( $p_from_address )
	{
		if ( preg_match( "/(?P<name>.*)<(?P<email>\S+@\S+)>$/u", $p_from_address, $matches ) )
		{
			$v_from_address = array(
				'name'	=> trim( $matches[ 'name' ], '"\' ' ),
				'email'	=> trim( $matches[ 'email' ] ),
				'From'	=> $p_from_address,
			);
		}
		else
		{
			$v_from_address = array(
				'name'	=> '',
				'email'	=> $p_from_address,
				'From'	=> $p_from_address,
			);
		}

		return( $v_from_address );
	}

	# --------------------
	# return a valid username from an email address
	private function prepare_username( $p_user_info )
	{
		# I would have liked to validate the username and remove any non-allowed characters
		# using the config user_login_valid_regex but that seems not possible and since
		# it's a config, any mantis installation could have a different one
		switch ( $this->_mail_preferred_username )
		{
			case 'email_address':
				$t_username = $p_user_info[ 'email' ];
				break;

			case 'email_no_domain':
				if( preg_match( email_regex_simple(), $p_user_info[ 'email' ], $t_check ) )
				{
					$t_local = $t_check[ 1 ];
					$t_domain = $t_check[ 2 ];

					$t_username = $t_local;
				}
				break;

			case 'from_ldap':
				$t_username = $this->ldap_get_username_from_email( $p_user_info[ 'email' ] );
				break;

			case 'name':
			default:
				$t_username = $p_user_info[ 'name' ];
		}

		$t_username_validated = $this->validate_username( $t_username );

		if ( $t_username_validated === FALSE )
		{
			// fallback username
			$t_username = strtolower( str_replace( array( '@', '.', '-' ), '_', $p_user_info[ 'email' ] ) );
			$t_rand = '_' . mt_rand( 1000, 99999 );

			$t_username_validated = $this->validate_username( $t_username, $t_rand );
		}

		return( $t_username_validated );
	}

	# --------------------
	# Validates and truncates the username
	private function validate_username( $p_username, $p_rand = '' )
	{
		$t_username = $p_username;

		if ( utf8_strlen( $t_username . $p_rand ) > DB_FIELD_SIZE_USERNAME )
		{
			$t_username = utf8_substr( $t_username, 0, ( DB_FIELD_SIZE_USERNAME - strlen( $p_rand ) ) );
		}

		$t_username = $t_username . $t_rand;

		if ( user_is_name_valid( $t_username ) && user_is_name_unique( $t_username ) )
		{
			return( $t_username );
		}

		return( FALSE );
	}

	# --------------------
	# return a valid realname from an email address
	private function prepare_realname( $p_user_info, $p_username )
	{
		switch( $this->_mail_preferred_realname ){
			case 'email_address':
				$t_realname = $p_user_info[ 'email' ];
				break;

			case 'email_no_domain':
				if( preg_match( email_regex_simple(), $p_user_info[ 'email' ], $t_check ) )
				{
					$t_local = $t_check[ 1 ];
					$t_domain = $t_check[ 2 ];

					$t_realname = $t_local;
				}
				break;

			case 'from_ldap':
				$t_realname = ldap_realname_from_username( $p_username );
				break;

			case 'full_from':
				$t_realname = str_replace( array( '<', '>' ), array( '(', ')' ), $p_user_info[ 'From' ] );
				break;

			case 'name':
			default:
				$t_realname = $p_user_info[ 'name' ];
		}

		$t_realname = string_normalize( $t_realname );

		if ( utf8_strlen( $t_realname ) > DB_FIELD_SIZE_REALNAME )
		{
			$t_realname = utf8_substr( $t_realname, 0, DB_FIELD_SIZE_REALNAME );
		}

		if ( user_is_realname_valid( $t_realname ) && user_is_realname_unique( $p_username, $t_realname ) )
		{
			return( $t_realname );
		}

		return( FALSE );
	}

	# --------------------
	# return bug_id if there is a valid mantis bug refererence in subject or return false if not found
	private function mail_is_a_bugnote( $p_mail_subject )
	{
		$t_bug_id = $this->get_bug_id_from_subject( $p_mail_subject );

		if ( $t_bug_id !== FALSE && bug_exists( $t_bug_id ) )
		{
			return( $t_bug_id );
		}

		return( FALSE );
	}

	# --------------------
	# return the bug's id from the subject
	private function get_bug_id_from_subject( $p_mail_subject )
	{
		// strict is default incase the setting contains an invalid value
		switch ( $this->_mail_subject_id_regex )
		{
			case 'balanced':
				$t_subject_id_regex = "/\[(?P<project>[^\]]+\s|)0*(?P<id>[0-9]+)\]/u";
				break;

			case 'relaxed':
				$t_subject_id_regex = "/\[(?P<project>[^\]]*\s|)0*(?P<id>[0-9]+)\s*\]/u";
				break;

			case 'strict':
			default:
				$t_subject_id_regex = "/\[(?P<project>[^\]]+\s)0*(?P<id>[0-9]+)\]/u";
		}

		preg_match( $t_subject_id_regex, $p_mail_subject, $v_matches );

		if ( isset( $v_matches[ 'id' ] ) )
		{
			return( (int) $v_matches[ 'id' ] );
		}

		return( FALSE );
	}

	# --------------------
	# Saves the complete email to file
	# Only works in debug mode
	private function save_message_to_file( $message_type, &$p_msg )
	{
		if ( $this->_mail_debug )
		{
			if ( is_dir( $this->_mail_debug_directory ) && is_writeable( $this->_mail_debug_directory ) )
			{
				$t_file_name = $this->_mail_debug_directory . '/' . $message_type . '_' . time() . '_' . md5( microtime() );
				file_put_contents( $t_file_name, ( ( is_array( $p_msg ) ) ? var_export( $p_msg, TRUE ) : $p_msg ) );
			}
			else
			{
				$this->custom_error( 'Mail debug directory does not exist or is not writable.', FALSE );
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

			//remove gmail style replies
			if( $this->_mail_strip_gmail_style_replies )
			{
				$t_description = preg_replace( '/^\s*>?\s*On\b.*\bwrote:.*?/msU', "\n", $t_description );
			}

			//append the mail removed notice.
			$t_description .= $this->_mail_removed_reply_text;
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
	private function fix_empty_fields( &$p_email )
	{
		if ( is_blank( $p_email[ 'Subject' ] ) )
		{
			$p_email[ 'Subject' ] = $this->_mail_nosubject;
		}

		if ( is_blank( $p_email[ 'X-Mantis-Body' ] ) )
		{
			$p_email[ 'X-Mantis-Body' ] = $this->_mail_nodescription;
		}
	}

	# --------------------
	# Add additional info if enabled
	private function add_additional_info( $p_type, &$p_email, $p_description )
	{
		$t_additional_info = NULL;

		if ( $this->_mail_save_from )
		{
			$t_additional_info .= 'Email from: ' . $p_email[ 'From_parsed' ][ 'From' ] . "\n";
		}

		if ( $p_type === 'note' && $this->_mail_save_subject_in_note )
		{
			$t_additional_info .= 'Subject: ' . $p_email[ 'Subject' ] . "\n";
		}

		if ( $t_additional_info !== NULL )
		{
			$t_additional_info .= "\n";
		}

		return( $t_additional_info . $p_description );
	}

	# --------------------
	# Strip signature from the mail body. Only removes the last part set by the delimiter
	private function strip_signature( $p_description )
	{
		$t_description = $p_description;

		if ( $this->_mail_strip_signature && strlen( trim( $this->_mail_strip_signature_delim ) ) > 1 )
		{
			$t_parts = preg_split( '/((?:\r|\n|\r\n)' . $this->_mail_strip_signature_delim . '\s*(?:\r|\n|\r\n))/', $t_description, -1, PREG_SPLIT_DELIM_CAPTURE );

			if ( count( $t_parts ) > 2 ) // String should not start with the delimiter so that why we need at least 3 parts
			{
				array_pop( $t_parts );
				array_pop( $t_parts );
				$t_description = implode( '', $t_parts );
			}
		}

		return( $t_description );
	}

	# --------------------
	# Show memory usage in debug mode
	private function show_memory_usage( $p_location )
	{
		if ( !$this->_test_only && $this->_mail_debug && $this->_mail_debug_show_memory_usage )
		{
			echo 'Debug output memory usage' . "\n" .
				'Location: Mail API - ' . $p_location . "\n" .
				'Current memory usage: ' . ERP_formatbytes( memory_get_usage( FALSE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Peak memory usage: ' . ERP_formatbytes( memory_get_peak_usage( FALSE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Current real memory usage: ' . ERP_formatbytes( memory_get_usage( TRUE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Peak real memory usage: ' . ERP_formatbytes( memory_get_peak_usage( TRUE ) ) . ' / ' . $this->_memory_limit . "\n";
		}
	}

	// --------------------
	// Add monitors from Cc and To fields in mail header
	private function add_monitors( $p_bug_id, $p_email )
	{
		if ( $this->_mail_add_users_from_cc_to )
		{
			$t_emails = array_merge( $p_email[ 'To' ], $p_email[ 'Cc' ] );

			foreach( $t_emails as $t_email )
			{
				$t_user_id = $this->get_userid_from_email( $t_email );

				$this->custom_error( 'Monitor: ' . $t_user_id . ' - ' . $t_email . ' --> Issue ID: #' . $p_bug_id, FALSE );

				if( $t_user_id !== FALSE ) 
				{ 
					// Make sure that mail_reporter_id and reporter_id are not added as a monitors.
					if( $this->_mail_reporter_id != $t_user_id && $p_email[ 'Reporter_id' ] != $t_user_id )
					{
						bug_monitor( $p_bug_id, $t_user_id );
					}
				}
			}
		}
	}

	/**
	 * Gets the username from LDAP given the email address
	 *
	 * @todo Implement caching by retrieving all needed information in one query.
	 * @todo Implement logging to LDAP queries same way like DB queries.
	 *
	 * @param string $p_email_address The email address.
	 * @return string The username or null if not found.
	 *
	 * Based on ldap_get_field_from_username from MantisBT 1.2.14
	 */
	private function ldap_get_username_from_email( $p_email_address )
	{
		if ( $this->_login_method == LDAP )
		{
			$t_email_field = 'mail';

			$t_ldap_organization    = config_get( 'ldap_organization' );
			$t_ldap_root_dn         = config_get( 'ldap_root_dn' );
			$t_ldap_uid_field       = config_get( 'ldap_uid_field' );

			$c_email_address = ldap_escape_string( $p_email_address );

			log_event( LOG_LDAP, "Retrieving field '$t_ldap_uid_field' for '$p_email_address'" );

			# Bind
			log_event( LOG_LDAP, "Binding to LDAP server" );
			$t_ds = @ldap_connect_bind();
			if ( $t_ds === false ) {
				ldap_log_error( $t_ds );
				return null;
			}

			# Search
			$t_search_filter        = "(&$t_ldap_organization($t_email_field=$c_email_address))";
			$t_search_attrs         = array( $t_ldap_uid_field, $t_email_field, 'dn' );

			log_event( LOG_LDAP, "Searching for $t_search_filter" );
			$t_sr = @ldap_search( $t_ds, $t_ldap_root_dn, $t_search_filter, $t_search_attrs );
			if ( $t_sr === false ) {
				ldap_log_error( $t_ds );
				ldap_unbind( $t_ds );
				log_event( LOG_LDAP, "ldap search failed" );
				return null;
			}

			# Get results
			$t_info = ldap_get_entries( $t_ds, $t_sr );
			if ( $t_info === false ) {
				ldap_log_error( $t_ds );
				log_event( LOG_LDAP, "ldap_get_entries() returned false." );
				return null;
			}

			# Free results / unbind
			log_event( LOG_LDAP, "Unbinding from LDAP server" );
			ldap_free_result( $t_sr );
			ldap_unbind( $t_ds );

			# If no matches, return null.
			if ( $t_info['count'] == 0 ) {
				log_event( LOG_LDAP, "No matches found." );
				return null;
			}

			# Make sure the requested field exists
			if( is_array($t_info[0]) && array_key_exists( strtolower( $t_ldap_uid_field ), $t_info[0] ) ) {
				$t_value = $t_info[0][ strtolower( $t_ldap_uid_field ) ][0];
				log_event( LOG_LDAP, "Found value '{$t_value}' for field '{$t_ldap_uid_field}'." );
			} else {
				log_event( LOG_LDAP, "WARNING: field '$t_ldap_uid_field' does not exist" );
				return null;
			}

			return $t_value;
		}

		return null;
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
?>
