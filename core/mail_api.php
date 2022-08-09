<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# Copyright (C) 2007  Rolf Kleef - rolf@drostan.org (IMAP)
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# This page receives an E-Mail via POP3 or IMAP and generates an Report

	require_api( 'bug_api.php' );
	require_api( 'bugnote_api.php' );
	require_api( 'user_api.php' );
	require_api( 'file_api.php' );

	require_once( config_get_global( 'absolute_path' ) . 'api/soap/mc_file_api.php' );

	//require_once( 'Net/POP3.php' );
	plugin_require_api( 'core_pear/Net/POP3.php' );
	//require_once( 'Net/IMAP.php' );
	plugin_require_api( 'core_pear/Net/IMAP.php' );

	plugin_require_api( 'core/config_api.php' );
	plugin_require_api( 'core/Mail/Parser.php' );

	plugin_require_api( 'core/EmailReplyParser/Parser/EmailParser.php');
	plugin_require_api( 'core/EmailReplyParser/Parser/FragmentDTO.php');
	plugin_require_api( 'core/EmailReplyParser/Email.php');
	plugin_require_api( 'core/EmailReplyParser/Fragment.php');

class ERP_mailbox_api
{
	private $_functionality_enabled = FALSE;
	private $_test_only = FALSE;
	private $_mailbox_starttime = NULL;

	public $_mailbox = array( 'description' => 'INITIALIZATION PHASE' );

	private $_mailserver = NULL;
	private $_result = TRUE;

	private $_default_ports = array(
		'POP3' => array( 'normal' => 110, 'encrypted' => 995 ),
		'IMAP' => array( 'normal' => 143, 'encrypted' => 993 ),
	);

	private $_validated_email_list = array();

	// Unable to use the MantisBT email_regex_simple because it doesn't capture the local and domain seperately anymore since MantisBT 1.3.x
	// Removed the local part limit because we might actually want the longer email addresses and we won't be using it to scan large texts anyway
	private $_email_regex_simple = "(?P<local>[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+)@(?P<domain>[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*)";
	private $_mail_max_email_summary = 128;

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
	private $_mail_ignore_auto_replies;
	private $_mail_max_email_body;
	private $_mail_max_email_body_text;
	private $_mail_max_email_body_add_attach;
	private $_mail_nodescription;
	private $_mail_nosubject;
	private $_mail_preferred_username;
	private $_mail_preferred_realname;
	private $_mail_remove_mantis_email;
	private $_mail_remove_replies;
	private $_mail_removed_reply_text;
	private $_mail_reporter_id;
	private $_mail_respect_permissions;
	private $_mail_save_from;
	private $_mail_save_subject_in_note;
	private $_mail_strip_signature;
	private $_mail_subject_id_regex;
	private $_mail_use_bug_priority;
	private $_mail_use_message_id;
	private $_mail_use_reporter;

	private $_mp_options = array();

	private $_allow_file_upload;
	private $_file_upload_method;
	private $_email_separator1;
	private $_login_method;
	private $_use_ldap_email;
	private $_plugin_mime_types;

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
		$this->_mail_ignore_auto_replies		= plugin_config_get( 'mail_ignore_auto_replies' );
		$this->_mail_max_email_body				= plugin_config_get( 'mail_max_email_body' );
		$this->_mail_max_email_body_text		= plugin_config_get( 'mail_max_email_body_text' );
		$this->_mail_max_email_body_add_attach	= plugin_config_get( 'mail_max_email_body_add_attach' );
		$this->_mail_nodescription				= plugin_config_get( 'mail_nodescription' );
		$this->_mail_nosubject					= plugin_config_get( 'mail_nosubject' );
		$this->_mail_preferred_username			= plugin_config_get( 'mail_preferred_username' );
		$this->_mail_preferred_realname			= plugin_config_get( 'mail_preferred_realname' );
		$this->_mail_remove_mantis_email		= plugin_config_get( 'mail_remove_mantis_email' );
		$this->_mail_remove_replies				= plugin_config_get( 'mail_remove_replies' );
		$this->_mail_removed_reply_text			= plugin_config_get( 'mail_removed_reply_text' );
		$this->_mail_reporter_id				= plugin_config_get( 'mail_reporter_id' );
		$this->_mail_respect_permissions		= plugin_config_get( 'mail_respect_permissions' );
		$this->_mail_save_from					= plugin_config_get( 'mail_save_from' );
		$this->_mail_save_subject_in_note		= plugin_config_get( 'mail_save_subject_in_note' );
		$this->_mail_strip_signature			= plugin_config_get( 'mail_strip_signature' );
		$this->_mail_subject_id_regex			= plugin_config_get( 'mail_subject_id_regex' );
		$this->_mail_use_bug_priority			= plugin_config_get( 'mail_use_bug_priority' );
		$this->_mail_use_message_id				= plugin_config_get( 'mail_use_message_id' );
		$this->_mail_use_reporter				= plugin_config_get( 'mail_use_reporter' );

		$this->_mp_options[ 'add_attachments' ]	= config_get( 'allow_file_upload' );
		$this->_mp_options[ 'debug' ]			= $this->_mail_debug;
		$this->_mp_options[ 'show_mem_usage' ]	= $this->_mail_debug_show_memory_usage;
		$this->_mp_options[ 'parse_html' ]		= plugin_config_get( 'mail_parse_html' );

		$this->_mp_options[ 'process_markdown' ]	= OFF;
		if ( plugin_is_loaded( 'MantisCoreFormatting' ) )
		{
			plugin_push_current( 'MantisCoreFormatting' );
			$this->_mp_options[ 'process_markdown' ] = plugin_config_get( 'process_markdown' );
			plugin_pop_current();
		}

		$this->_allow_file_upload				= config_get( 'allow_file_upload' );
		$this->_file_upload_method				= config_get( 'file_upload_method' );
		$this->_email_separator1				= config_get( 'email_separator1' );
		$this->_login_method					= config_get( 'login_method' );
		$this->_use_ldap_email					= config_get( 'use_ldap_email' );
		$this->_plugin_mime_types				= config_get( 'plugin_mime_types' );

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
		$this->_mailbox_starttime = ERP_get_timestamp();
		
		$this->_mailbox = $p_mailbox + ERP_get_default_mailbox();

		if ( $this->_functionality_enabled )
		{
			if ( $this->_mailbox[ 'enabled' ] )
			{
				// Check whether EmailReporting supports the mailbox type. The check is based on available default ports
				if ( isset( $this->_default_ports[ $this->_mailbox[ 'mailbox_type' ] ] ) )
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
									echo "\n";
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
					$this->custom_error( 'Unknown mailbox type' );
				}
			}
			else
			{
				$this->custom_error( 'Mailbox disabled' );
			}
		}
		else
		{
			$this->custom_error( 'EmailReporting not initialised properly' );
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
			$p_pear->ERP_location = $p_location;

			$this->_result = &$p_pear;

			if ( !$this->_test_only )
			{
				echo 'Mailbox: ' . $this->_mailbox[ 'description' ] . "\n" . 'Location: ' . $p_location . "\n" . $p_pear->toString() . "\n\n";
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
			echo 'Mailbox: ' . $this->_mailbox[ 'description' ] . "\n" . $t_error_text . "\n\n";
		}
	}

	# --------------------
	# process all mails for a pop3 mailbox
	private function process_pop3_mailbox()
	{
		$this->_mailserver = new Net_POP3();
		$this->_mailserver->_timeout = 3;

		$t_connectresult = $this->_mailserver->connect( $this->_mailbox[ 'hostname' ], $this->_mailbox[ 'port' ], $this->get_StreamContextOptions() );

		if ( $t_connectresult === TRUE )
		{
			$t_loginresult = $this->mailbox_login();

			if ( !$this->pear_error( 'Attempt login', $t_loginresult ) )
			{
				if ( $this->_test_only === FALSE )
				{
					if ( project_get_field( $this->_mailbox[ 'project_id' ], 'enabled' ) == ON )
					{
						$t_ListMsgs = $this->getListing();

						if ( !$this->pear_error( 'Retrieve list of messages', $t_ListMsgs ) )
						{
							while ( $t_Msg = array_pop( $t_ListMsgs ) )
							{
								$t_emailresult = $this->process_single_email( $t_Msg[ 'msg_id' ] );

								if ( $this->_mail_delete && $t_emailresult )
								{
									$t_deleteresult = $this->_mailserver->deleteMsg( $t_Msg[ 'msg_id' ] );

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
			}

			$this->_mailserver->disconnect();
		}
		else
		{
			$this->custom_error( 'Failed to connect to the mail server' . ( ( $this->_mailbox[ 'encryption' ] !== 'None' && $this->_mailbox[ 'ssl_cert_verify' ] == ON ) ? '. This could possibly be because SSL certificate verification failed' : NULL ) );
		}
	}

	# --------------------
	# process all mails for an imap mailbox
	private function process_imap_mailbox()
	{
//		$this->_mailserver = new Net_IMAP( $this->_mailbox[ 'hostname' ], $this->_mailbox[ 'port' ] );
		$this->_mailserver = new Net_IMAP();
		$this->_mailserver->setTimeout( 3 );

		$this->_mailserver->setStreamContextOptions( $this->get_StreamContextOptions() );

		$this->_mailserver->connect( $this->_mailbox[ 'hostname' ], $this->_mailbox[ 'port' ], ( ( $this->_mailbox[ 'encryption' ] === 'STARTTLS' ) ? TRUE : FALSE ) );

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
						if ( $this->_mailbox[ 'imap_createfolderstructure' ] == ON )
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
							if ( $t_project[ 'enabled' ] == ON )
							{
								$t_project_name = $this->cleanup_project_name( $t_project[ 'name' ] );

								$t_foldername = $this->_mailbox[ 'imap_basefolder' ] . ( ( $this->_mailbox[ 'imap_createfolderstructure' ] ) ? $t_hierarchydelimiter . $t_project_name : NULL );

								// We don't need to check twice whether the mailbox exist incase createfolderstructure is false
								if ( !$this->_mailbox[ 'imap_createfolderstructure' ] || $this->_mailserver->mailboxExist( $t_foldername ) === TRUE )
								{
									// Exchange does not seem to like numMsg so that was changed to getListing
									// getListing returns an error when there are no emails in an IMAP folder.
									// After 10 errors Exchange will ignore the connection and any further commands will fail with ", "
									// 10 errors or more can happen when imap_createfolderstructure is ON
									// examineMailbox allows EmailReporting to check whether or not there are emails in the folder without producing an error
									$t_result = $this->_mailserver->examineMailbox( $t_foldername );

									if ( !$this->pear_error( 'Examine IMAP folder', $t_result ) && $t_result[ 'EXISTS' ] > 0 )
									{
										$t_result = $this->_mailserver->selectMailbox( $t_foldername );

										if ( !$this->pear_error( 'Select IMAP folder', $t_result ) )
										{
											$t_ListMsgs = $this->getListing();

											if ( !$this->pear_error( 'Retrieve list of messages', $t_ListMsgs ) )
											{
												$t_flags = $this->_mailserver->getFlags();

												while ( $t_Msg = array_pop( $t_ListMsgs ) )
												{
													$t_isDeleted = $this->isDeleted( $t_Msg[ 'msg_id' ], $t_flags );

													if ( $this->pear_error( 'Check email deleted flag', $t_isDeleted ) )
													{
														// Should we stop processing if the flag cannot be verified or process the email?
														// Let's ignore the email and hope the check works on the next run
														$t_isDeleted = TRUE;
													}

													if ( $t_isDeleted === TRUE )
													{
														// Email marked as deleted. Do nothing
													}
													else
													{
														$t_emailresult = $this->process_single_email( $t_Msg[ 'msg_id' ], (int) $t_project[ 'id' ] );

														if ( $t_emailresult === TRUE )
														{
															$t_deleteresult = $this->_mailserver->deleteMsg( $t_Msg[ 'msg_id' ] );

															$this->pear_error( 'Attempt delete email', $t_deleteresult );
														}
													}
												}
											}
										}
									}
								}
								elseif ( $this->_mailbox[ 'imap_createfolderstructure' ] == ON )
								{
									// create this mailbox
									$t_result = $this->_mailserver->createMailbox( $t_foldername );

									$this->pear_error( 'Create IMAP folder: "' . $t_foldername . '"', $t_result );
								}
							}
							elseif ( $this->_mailbox[ 'imap_createfolderstructure' ] == OFF )
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

			//$this->_mailserver->expunge(); //disabled as this is handled by the disconnect

			// mail_delete decides whether to perform the expunge command before closing the connection
			$this->_mailserver->disconnect( (bool) $this->_mail_delete );
		}
		else
		{
			$this->custom_error( 'Failed to connect to the mail server' . ( ( $this->_mailbox[ 'encryption' ] !== 'None' && $this->_mailbox[ 'ssl_cert_verify' ] == ON ) ? '. This could possibly be because SSL certificate verification failed' : NULL ) );
		}
	}

	# Return Stream Context Options array
	private function get_StreamContextOptions()
	{
		return( array(
			'ssl' => array
			(
				'verify_peer'      => (bool) $this->_mailbox[ 'ssl_cert_verify' ],
				'verify_peer_name' => (bool) $this->_mailbox[ 'ssl_cert_verify' ]
			)
		) );
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
	# Return a list of emails in the mailbox
	# Needed a workaround to sort IMAP emails in a certain order
	private function getListing()
	{
		$t_ListMsgs = $this->_mailserver->getListing();

		if ( !PEAR::isError( $t_ListMsgs ) )
		{
			if ( $this->_mailbox[ 'mailbox_type' ] === 'IMAP' )
			{
				$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'uidl' );
			}
			else
			{
				$t_ListMsgs = array_column( $t_ListMsgs, NULL, 'msg_id' );
			}

		}

		krsort( $t_ListMsgs );

		return( $t_ListMsgs );
	}

	# --------------------
	# Process a single email from either a pop3 or imap mailbox
	# Returns true or false based on succesfull email retrieval from the mailbox
	private function process_single_email( $p_i, $p_overwrite_project_id = FALSE )
	{
		$this->show_memory_usage( 'Start process single email' );

		$t_msg = $this->getMsg( $p_i );

		if ( empty( $t_msg ) )
		{
			$this->custom_error( 'Retrieved message was empty. Either an invalid message ID was passed or there is a problem with one of the required PEAR packages' );

			return( FALSE );
		}

		if ( $this->pear_error( 'Retrieve raw message', $t_msg ) )
		{
			return( FALSE );
		}

		$this->show_memory_usage( 'Single email retrieved from mailbox' );

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
				// Ignore the email if it is an auto-reply
				if ( $this->_mail_ignore_auto_replies && $t_email[ 'Is-Auto-Reply' ] )
				{
					$this->custom_error( 'Email is marked as an auto-reply and has been ignored.' );
				}
				else
				{
					$this->add_bug( $t_email, $p_overwrite_project_id );
				}
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
	# Handles a workaround for problems with Net_IMAP 1.1.x concerning the getMsg function
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
	# Check whether a email is deleted
	# for IMAP only function
	# Handles a workaround for problems with Net_IMAP 1.1.x with the hasFlag function (isDeleted uses that function)
	private function isDeleted( $p_msg_id, &$p_flags )
	{
//		return $this->hasFlag($message_nro, '\Deleted');
		$flag = '\Deleted';

		if ( $p_flags instanceOf PEAR_Error )
		{
			return $p_flags;
		}

		if ( isset( $p_flags[ $p_msg_id ] ) )
		{
			if ( is_array( $p_flags[ $p_msg_id ] ) )
			{
				if ( in_array( $flag, $p_flags[ $p_msg_id ] ) )
				{
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	# --------------------
	# parse the email using mimeDecode for Mantis
	private function parse_content( &$p_msg )
	{
		$this->show_memory_usage( 'Start Mail Parser' );

		$t_mp = new ERP_Mail_Parser( $this->_mp_options, $this->_mailbox_starttime );

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

		$t_email[ 'From_parsed' ] = $this->parse_from_field( trim( (string)$t_mp->from() ) );
		$t_email[ 'Reporter_id' ] = $this->get_user( $t_email[ 'From_parsed' ] );

		$t_email[ 'Subject' ] = trim( (string)$t_mp->subject() );

		$t_email[ 'To' ] = $this->get_emailaddr_from_string( $t_mp->to() );
		$t_email[ 'Cc' ] = $this->get_emailaddr_from_string( $t_mp->cc() );

		$t_email[ 'X-Mantis-Body' ] = trim( (string)$t_mp->body() );

		$t_email[ 'X-Mantis-Parts' ] = $t_mp->parts();

		$t_email[ 'Is-Auto-Reply' ] = $t_mp->is_auto_reply();

		if ( $this->_mail_add_complete_email )
		{
			$t_email[ 'X-Mantis-Parts' ][] = $t_part;

			unset( $t_part );
		}

		if ( isset( $this->_mail_bug_priority[ strtolower( $t_mp->priority() ) ] ) )
		{
			$t_email[ 'Priority' ] = (int) $this->_mail_bug_priority[ strtolower( $t_mp->priority() ) ];
		}
		else
		{
			$this->custom_error( 'Unknown email priority encountered (' . strtolower( $t_mp->priority() ) . '). Falling back to default priority', FALSE );
			$t_email[ 'Priority' ] = FALSE;
		}

		$t_email[ 'Message-ID' ] = $t_mp->messageid();
		$t_email[ 'References' ] = $t_mp->references();
		$t_email[ 'In-Reply-To' ] = $t_mp->inreplyto();

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

							$this->custom_error( 'Reporter created: ' . $t_reporter_id . ' - ' . $p_parsed_from[ 'email' ], FALSE );
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
		$this->custom_error( 'Could not get a valid reporter. Email will be ignored and no other attempt will be made' );

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

		//Merge References and In-Reply-To headers into one array
		$t_references = $p_email['References'];
		$t_references[] = $p_email['In-Reply-To'];
		// Add Message-ID, to have all references, and in case the email is duplicated
		$t_references[] = $p_email['Message-ID'];

		if ( $this->_mail_add_bugnotes )
		{
			$t_bug_id = $this->mail_is_a_bugnote( $p_email[ 'Subject' ], $t_references );
		}
		else
		{
			$t_bug_id = FALSE;
		}

		$t_bugnote_id = NULL;
		if ( $t_bug_id !== FALSE && !bug_is_readonly( $t_bug_id ) )
		{
			$t_project_id = bug_get_field( $t_bug_id, 'project_id' );
			ERP_set_temporary_overwrite( 'project_override', $t_project_id );

			$t_description = $p_email[ 'X-Mantis-Body' ];

			$t_description = $this->identify_mantisbt_replies( $t_description );
			$t_description = $this->parse_email_body( $t_description );
			$t_description = $this->add_additional_info( 'note', $p_email, $t_description );
			$t_description = $this->limit_body_size( 'note', $t_description, $p_email );

			# Event integration
			# Core mantis event already exists within bugnote_add function
			$t_description = event_signal( 'EVENT_ERP_BUGNOTE_DATA', $t_description, $t_bug_id );

			# Check reopen permissions
			$t_bug = bug_get( $t_bug_id, true );
			if ( bug_is_resolved( $t_bug_id ) && ( !$this->_mail_respect_permissions || access_can_reopen_bug( $t_bug ) ) )
			{
				if ( !is_blank( $t_description ) )
				{
					# Reopen issue and add a bug note
					$t_bugnote_id = bugnote_add( $t_bug_id, $t_description, '0:00', config_get( 'default_bugnote_view_status' ) == VS_PRIVATE, BUGNOTE, '', null, false );
					bugnote_process_mentions( $t_bug_id, $t_bugnote_id, $t_description );
					bug_reopen( $t_bug_id );

					$t_updated_bug = bug_get( $t_bug_id, true );
					event_signal( 'EVENT_UPDATE_BUG', array( $t_bug, $t_updated_bug ) );
				}
			}
			elseif ( !is_blank( $t_description ) )
			{
				# Check note permissions
				if ( !$this->_mail_respect_permissions || access_has_bug_level( config_get( 'add_bugnote_threshold' ), $t_bug_id ) )
				{
					# Add a bug note
					$t_bugnote_id = bugnote_add( $t_bug_id, $t_description, '0:00', config_get( 'default_bugnote_view_status' ) == VS_PRIVATE );

					// MantisBT 1.3.x function
					// reassign_on_feedback only needs to be done here incase of MantisBT 1.3.x
					if ( function_exists( 'bugnote_process_mentions' ) )
					{
						# Process the mentions in the added note
						bugnote_process_mentions( $t_bug->id, $t_bugnote_id, $t_description );

						/* Code based on MantisBT 1.3.4 */
						# Handle the reassign on feedback feature. Note that this feature generally
						# won't work very well with custom workflows as it makes a lot of assumptions
						# that may not be true. It assumes you don't have any statuses in the workflow
						# between 'bug_submit_status' and 'bug_feedback_status'. It assumes you only
						# have one feedback, assigned and submitted status.
						if( config_get( 'reassign_on_feedback' ) &&
							 $t_bug->status === config_get( 'bug_feedback_status' ) &&
							 $t_bug->handler_id !== (int) auth_get_current_user_id() &&
							 $t_bug->reporter_id === (int) auth_get_current_user_id() ) {
							if( $t_bug->handler_id !== NO_USER ) {
								bug_set_field( $t_bug->id, 'status', config_get( 'bug_assigned_status' ) );
							} else {
								bug_set_field( $t_bug->id, 'status', config_get( 'bug_submit_status' ) );
							}
						}
					}
				}
				else
				{
					// Access denied for adding new notes / reopen issue
					$this->custom_error( 'Access denied for adding notes. Email ignored.' . "\n" );
					return;
				}
			}
		}
		elseif ( $this->_mail_add_bug_reports )
		{
			$t_project_id = ( ( $p_overwrite_project_id === FALSE ) ? $this->_mailbox[ 'project_id' ] : $p_overwrite_project_id );
			ERP_set_temporary_overwrite( 'project_override', $t_project_id );

			# Check issue permissions
			if ( !$this->_mail_respect_permissions || access_has_project_level( config_get('report_bug_threshold' ) ) )
			{
				$t_master_bug_id = NULL;
				if ( $t_bug_id !== FALSE && bug_is_readonly( $t_bug_id ) )
				{
					$t_master_bug_id = $t_bug_id;

					// Issues beyond the readonly border will not be reopened and will result in a new issue with a relationship to the old one
					// This requires us to relink the references to the new issue by first cleaning up the old ones
					ERP_mailbox_api::delete_references_for_bug_id( $t_master_bug_id );
				}

				$this->fix_empty_fields( $p_email );

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

				$t_priority = $this->verify_priority( $p_email[ 'Priority' ] );
				$t_bug_data->priority				= (int) $t_priority;
				$t_bug_data->projection				= (int) config_get( 'default_bug_projection' );
				$t_bug_data->eta					= (int) config_get( 'default_bug_eta' );
				$t_bug_data->resolution				= config_get( 'default_bug_resolution' );
				$t_bug_data->status					= config_get( 'bug_submit_status' );
				$t_bug_data->summary				= mb_substr( $p_email[ 'Subject' ], 0, $this->_mail_max_email_summary );

				$t_description = $p_email[ 'X-Mantis-Body' ];
				$t_description = $this->add_additional_info( 'issue', $p_email, $t_description );
				$t_description = $this->limit_body_size( 'description', $t_description, $p_email );
				$t_bug_data->description			= $t_description;

				$t_bug_data->steps_to_reproduce		= config_get( 'default_bug_steps_to_reproduce' );
				$t_bug_data->additional_information	= config_get( 'default_bug_additional_info' );
				
				$t_fields = config_get( 'bug_report_page_fields' );
				$t_fields = columns_filter_disabled( $t_fields );
				$t_update_due_date = in_array( 'due_date', $t_fields ) && access_has_project_level( config_get( 'due_date_update_threshold' ), helper_get_current_project(), auth_get_current_user_id() );
				$t_bug_data->due_date				= date_strtotime( config_get( 'due_date_default' ) );
				if( ( $this->_mail_respect_permissions && !$t_update_due_date ) || $t_bug_data->due_date === '' )
				{
					$t_bug_data->due_date			= date_get_null();
				}

				$t_bug_data->project_id				= $t_project_id;

				$t_bug_data->reporter_id			= $p_email[ 'Reporter_id' ];

				// This function might do stuff that EmailReporting cannot handle. Disabled
				//helper_call_custom_function( 'issue_create_validate', array( $t_bug_data ) );

				// @TODO@ Disabled for now but possibly needed for other future features
				# Validate the custom fields before adding the bug.
				$t_related_custom_field_ids = custom_field_get_linked_ids( $t_bug_data->project_id );
/*
				foreach( $t_related_custom_field_ids as $t_id ) {
					$t_def = custom_field_get_definition( $t_id );

					# Produce an error if the field is required but wasn't posted
					if( !gpc_isset_custom_field( $t_id, $t_def['type'] )
					   && $t_def['require_report']
					) {
						error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
						trigger_error( ERROR_EMPTY_FIELD, ERROR );
					}

					if( !custom_field_validate( $t_id, gpc_get_custom_field( 'custom_field_' . $t_id, $t_def['type'], null ) ) ) {
						error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
						trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, ERROR );
					}
				}
*/

				# Allow plugins to pre-process bug data
				$t_bug_data = event_signal( 'EVENT_REPORT_BUG_DATA', $t_bug_data );
				$t_bug_data = event_signal( 'EVENT_ERP_REPORT_BUG_DATA', $t_bug_data );

				# Create the bug
				$t_bug_id = $t_bug_data->create();
				// MantisBT 1.3.x function
				if ( method_exists( $t_bug_data, 'process_mentions' ) )
				{
					$t_bug_data->process_mentions();
				}

				// @TODO@ Enabled for processing default values. Needs more work for other features
				// - Handling of non default values not working
				// - Error handling not working
				# Handle custom field submission
				foreach ( $t_related_custom_field_ids as $t_id )
				{
					# Do not set custom field value if user has no write access
					if ( !custom_field_has_write_access( $t_id, $t_bug_id ) )
					{
						continue;
					}

					$t_def = custom_field_get_definition( $t_id );
					$t_default_value = custom_field_default_to_value( $t_def['default_value'], $t_def['type'] );
					$t_value = $t_default_value; //gpc_get_custom_field( 'custom_field_' . $t_id, $t_def['type'], $t_default_value );
					if ( !custom_field_set_value( $t_id, $t_bug_id, $t_value, /* log insert */ false ) )
					{
/*
						error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
						trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, ERROR );
*/
					}
				}

				// Lets link a readonly already existing bug to the newly created one
				if ( !empty( $t_master_bug_id ) )
				{
					$t_rel_type = BUG_RELATED;

					# update master bug last updated
					bug_update_date( $t_master_bug_id );

					# Add the relationship
					relationship_add( $t_bug_id, $t_master_bug_id, $t_rel_type );

					# Add log line to the history (both issues)
					history_log_event_special( $t_master_bug_id, BUG_ADD_RELATIONSHIP, relationship_get_complementary_type( $t_rel_type ), $t_bug_id );
					history_log_event_special( $t_bug_id, BUG_ADD_RELATIONSHIP, $t_rel_type, $t_master_bug_id );

					# Send the email notification
					email_relationship_added( $t_master_bug_id, $t_bug_id, relationship_get_complementary_type( $t_rel_type ), false );
				}

				helper_call_custom_function( 'issue_create_notify', array( $t_bug_id ) );

				# Allow plugins to post-process bug data with the new bug ID
				event_signal( 'EVENT_REPORT_BUG', array( $t_bug_data, $t_bug_id ) );

				email_bug_added( $t_bug_id );
			}
			else
			{
				// Access denied for adding new issues
				$this->custom_error( 'Access denied for adding new issues. Email ignored.' . "\n" );
				return;
			}
		}
		else
		{
			// Not allowed to add issues and not allowed / able to add notes. Need to stop processing
			$this->custom_error( 'Not allowed to create a new issue. Email ignored.' );
			return;
		}

		$this->custom_error( 'Reporter: ' . $p_email[ 'Reporter_id' ] . ' - ' . $p_email[ 'From_parsed' ][ 'email' ] . ' --> Issue ID: #' . $t_bug_id, FALSE );

		$this->show_memory_usage( 'Finished add bug' );

		$this->show_memory_usage( 'Start processing attachments' );

		# Add files
		if ( $this->_allow_file_upload && bug_exists( $t_bug_id ) )
		{
			if ( count( $p_email[ 'X-Mantis-Parts' ] ) > 0 )
			{
				# Check attachment permissions
				if( !$this->_mail_respect_permissions || file_allow_bug_upload( $t_bug_id ) )
				{
					$t_rejected_files = NULL;

					while ( $t_part = array_shift( $p_email[ 'X-Mantis-Parts' ] ) )
					{
						$t_file_rejected = $this->add_file( $t_bug_id, $t_part, $t_bugnote_id );

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

						$t_reject_rejected_files = $this->add_file( $t_bug_id, $t_part, $t_bugnote_id );

						if ( $t_reject_rejected_files !== TRUE )
						{
							$t_part[ 'body' ] .= $t_reject_rejected_files;
							$this->custom_error( 'Failed to add "' . $t_part[ 'name' ] . '" to the issue. See below for all errors.' . "\n" . $t_part[ 'body' ] );
						}
					}
				}
				else
				{
					// Access denied for adding new attachments. No 'return' since its possible a new note or issue was allowed
					$this->custom_error( 'Access denied for uploading files. Email (partially) ignored.' . "\n" );
				}
			}
		}

		//Add the users in Cc and To list in mail header
		$this->add_monitors( $t_bug_id, $p_email );

		//Add the message-id to the database
		$this->add_msg_ids( $t_bug_id, $t_references );

		ERP_set_temporary_overwrite( 'project_override', NULL );

		$this->show_memory_usage( 'Finished processing attachments' );
	}

	# --------------------
	# Very dirty: Adds a file to a bug.
	# returns true on success and the filename with reason on error
	private function add_file( $p_bug_id, &$p_part, $p_bugnote_id = NULL )
	{
		# Handle the file upload
		$t_part_name = ( ( isset( $p_part[ 'name' ] ) ) ? trim( (string)$p_part[ 'name' ] ) : NULL );
		$t_strlen_body = strlen( $p_part[ 'body' ] );

		if ( is_blank( $t_part_name ) )
		{
			// Try setting the file extension according to it's mime type
			$t_ext = array_search( $p_part[ 'ctype' ], $this->_plugin_mime_types, TRUE );
			if( $t_ext === FALSE )
			{
				$t_ext = 'erp';
			}

			$t_part_name = md5( microtime() ) . '.' . $t_ext;
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
		elseif ( !bug_exists( $p_bug_id ) )
		{
			return( $t_part_name . ' = given bug_id does not exist' . "\n" );
		}
		else
		{
			$t_file_number = 0;
			$t_opt_name = '';
			$t_dot_index = strripos( $t_part_name, '.' );
			if( $t_dot_index === false )
			{
				$t_extension = '';
				$t_file_name = $t_part_name;
			}
			else
			{
				$t_extension = substr( $t_part_name, $t_dot_index, strlen( $t_part_name ) - $t_dot_index );
				$t_file_name = substr( $t_part_name, 0, $t_dot_index );
			}

			// check max length filename. Shorten if necessary. Leave room for file number.
			$t_max_length = ( ( defined( 'DB_FIELD_SIZE_FILENAME' ) ) ? DB_FIELD_SIZE_FILENAME : 250 ) - 5;
			if ( strlen( $t_file_name ) > ( $t_max_length - strlen( $t_extension ) ) )
			{
				$t_file_name = substr( $t_file_name, 0, ( $t_max_length - strlen( $t_extension ) ) );
			}

			while ( !file_is_name_unique( $t_file_name . $t_opt_name . $t_extension, $p_bug_id ) )
			{
				$t_file_number++;
				$t_opt_name = '-' . $t_file_number;
			}

			$t_attachment_id = mci_file_add( $p_bug_id, $t_file_name . $t_opt_name . $t_extension, $p_part[ 'body' ], $p_part[ 'ctype' ], 'bug' );

			if ( function_exists( 'file_link_to_bugnote' ) && is_numeric( $t_attachment_id ) && $t_attachment_id > 0 && $p_bugnote_id !== NULL )
			{
				file_link_to_bugnote( $t_attachment_id, $p_bugnote_id );
			}
		}

		return( TRUE );
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
		$t_project_name = trim( (string)$t_project_name, "-. " );

		return( $t_project_name );
	}

	# --------------------
	# return the hostname parsed into a hostname + port
	private function prepare_mailbox_hostname()
	{
		$t_def_mailbox_port_index = 'normal';
		$this->_mailbox[ 'port' ] = (int) $this->_mailbox[ 'port' ];

		if ( $this->_mailbox[ 'encryption' ] !== 'None' && $this->_mailbox[ 'encryption' ] !== 'STARTTLS' )
		{
			if ( extension_loaded( 'openssl' ) )
			{
				$t_def_mailbox_port_index = 'encrypted';

				// The IMAP pear package will enable encryption after the connection is established if the default port is used. So we need to work around that
				// No longer needed since we disabled the code in question in IMAPProtocol.php
//				if ( !( $this->_mailbox[ 'mailbox_type' ] === 'IMAP' && ( $this->_mailbox[ 'port' ] <= 0 || $this->_mailbox[ 'port' ] === $this->_default_ports[ $this->_mailbox[ 'mailbox_type' ] ][ $t_def_mailbox_port_index ] ) ) )
				{
					$this->_mailbox[ 'hostname' ] = strtolower( $this->_mailbox[ 'encryption' ] ) . '://' . $this->_mailbox[ 'hostname' ];
				}
			}
			else
			{
				$this->custom_error( 'OpenSSL plugin not available even though the mailbox is configured to use it. Please check whether OpenSSL is properly being loaded' );
			}
		}

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
	# Return the emailaddress from the mail's 'From' field
	private function parse_from_field( $p_from_address )
	{
		if ( preg_match( '/^(?:(?P<name>.*?)<|)(?P<email>' . $this->_email_regex_simple . ')(?:>|)/u', trim( (string)$p_from_address ), $match ) )
		{
			$v_from_address = array(
				'name'	=> trim( (string)$match[ 'name' ], '"\' ' ),
				'email'	=> trim( (string)$match[ 'email' ] ),
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
	# Return all email addresses present in a string
	# Generally used to parse email fields like to and cc
	private function get_emailaddr_from_string( $p_addresses )
	{
		$v_addresses = array();

		if ( preg_match_all( '/' . $this->_email_regex_simple . '/u', (string)$p_addresses, $matches, PREG_SET_ORDER ) )
		{
			foreach( $matches AS $match )
			{
				$v_addresses[] = trim( (string)$match[ 0 ] );
			}
		}

		return( $v_addresses );
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
				if( preg_match( '/' . $this->_email_regex_simple . '/', $p_user_info[ 'email' ], $t_check ) )
				{
					$t_local = $t_check[ 'local' ];
					$t_domain = $t_check[ 'domain' ];

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

		if ( mb_strlen( $t_username . $p_rand ) > DB_FIELD_SIZE_USERNAME )
		{
			$t_username = mb_substr( $t_username, 0, ( DB_FIELD_SIZE_USERNAME - strlen( $p_rand ) ) );
		}

		$t_username = $t_username . $p_rand;

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
				if( preg_match( '/' . $this->_email_regex_simple . '/', $p_user_info[ 'email' ], $t_check ) )
				{
					$t_local = $t_check[ 'local' ];
					$t_domain = $t_check[ 'domain' ];

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

		if ( mb_strlen( $t_realname ) > DB_FIELD_SIZE_REALNAME )
		{
			$t_realname = mb_substr( $t_realname, 0, DB_FIELD_SIZE_REALNAME );
		}

		if ( mb_strlen( $t_realname ) > 0 )
		{
			return( $t_realname );
		}

		return( FALSE );
	}

	# --------------------
	# return bug_id if there is a valid mantis bug refererence in subject, reference header or return false if not found
	private function mail_is_a_bugnote( $p_mail_subject, $p_references )
	{
		$t_bug_id = $this->get_bug_id_from_subject( $p_mail_subject );

		if ( $t_bug_id !== FALSE && bug_exists( $t_bug_id ) )
		{
			return( $t_bug_id );
		}

		//Get the ids from Mail References(header)
		$t_bug_id = $this->get_bug_id_from_references( $p_references );

		if ( $t_bug_id !== FALSE )
		{
			if( bug_exists( $t_bug_id ) )
			{
				return( $t_bug_id );
			}
			else
			{
				// We found a referenced bug_id that does not exists.
				// Do a clean up of the table to avoid inconsistencies.
				self::clean_references_for_deleted_issues();
			}
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
			case 'relaxed':
				$t_subject_id_regex = "/\[(?P<project>[^\]]*\s|)0*(?P<id>[0-9]+)\s*\]/u";
				break;

			case 'balanced':
				$t_subject_id_regex = "/\[(?P<project>[^\]]+\s|)0*(?P<id>[0-9]+)\]/u";
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
	# Get the Bug ID from messageid
	private function get_bug_id_from_references( $p_references )
	{
		if( $this->_mail_use_message_id )
		{
			$p_references = (array) $p_references;

			foreach( $p_references AS $t_reference ) 
			{
				$query = 'SELECT issue_id FROM ' . plugin_table( 'msgids' ) . ' WHERE msg_id=' . db_param();
				$t_bug_id = db_result( db_query( $query, array( $t_reference ), 1 ) );

				if( $t_bug_id !== FALSE ) 
				{
					return( $t_bug_id );
				}
			}
		}

		return( FALSE );
	}

	# --------------------
	# Return the mail references ids stored for a bug id
	private function get_bug_references( $p_bug_id )
	{
		$t_ref_ids = array();

		$query = 'SELECT msg_id FROM ' . plugin_table( 'msgids' ) . ' WHERE issue_id=' . db_param();
		$t_result = db_query( $query, array( (int)$p_bug_id ) );

		while( $t_row = db_fetch_array( $t_result ) )
		{
			$t_ref_ids[] = $t_row['msg_id'];
		}

		return $t_ref_ids;
	}

	# --------------------
	# Add message references from the new mail to the database
	private function add_msg_ids( $p_bug_id, array $p_ref_ids )
	{
		if( $this->_mail_use_message_id )
		{
			// get existing references, and insert only new ones
			$t_existing_refs = $this->get_bug_references( $p_bug_id );
			$t_new_references = array_diff( array_unique( $p_ref_ids ), $t_existing_refs );

			// Add the references ids to the table for future reference
			foreach( $t_new_references as $t_ref )
			{
				if ( !is_blank( $t_ref ) )
				{
					// ignore references longer then 255 characters as they are most likely malformed
					if ( strlen( $t_ref ) > 255 )
					{
						$this->custom_error( 'Reference id encountered thats longer then 255 characters. It will be ignored' );
					}
					else
					{
						// Check whether the msg_id is already in the database table (incase its under a different bug id)
						$t_bug_id = $this->get_bug_id_from_references( $t_ref );

						if( $t_bug_id === FALSE )
						{
							// Add the Message-ID to the table for future reference
							$t_query = 'INSERT INTO ' . plugin_table( 'msgids' ) . '( issue_id, msg_id ) VALUES'
									. ' (' . db_param() . ', ' . db_param() . ')';
							db_query( $t_query, array( (int)$p_bug_id, $t_ref ) );
						}
					}
				}
			}
		}
	}

	# --------------------
	# Deletes the stored references for an issue
	# It's static to be called from main plugin event for bug deletion
	public static function delete_references_for_bug_id( $p_bug_id )
	{
		$t_query = 'DELETE FROM ' . plugin_table( 'msgids' ) . ' WHERE issue_id = ' . db_param();
		db_query( $t_query, array( (int)$p_bug_id ) );
	}

	# --------------------
	# Deletes all references linked to non existant issues
	# It's static to be called by upgrade as a cleanup step
	public static function clean_references_for_deleted_issues()
	{
		$t_query = 'DELETE FROM ' . plugin_table( 'msgids' ) . ' WHERE NOT EXISTS'
				. '( SELECT 1 FROM ' . db_get_table( 'bug' ) . ' B WHERE B.id = issue_id )';
		db_query( $t_query );
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
	# Removes MantisBT email parts from replies
	private function identify_mantisbt_replies( $p_description )
	{
		$t_description = $p_description;

		if ( $this->_mail_remove_mantis_email )
		{
			# The pear mimeDecode.php seems to be removing the last "=" in some versions of the pear package.
			# the version delivered with this package seems to be working OK though but just to be sure
			$t_email_separator1 = mb_substr( $this->_email_separator1, 0, -1 );

			$t_first_occurence = mb_strpos( $t_description, $t_email_separator1 );
			if ( $t_first_occurence !== FALSE && mb_substr_count( $t_description, $t_email_separator1 ) >= 5 )
			{
				$t_description = mb_substr( $t_description, 0, $t_first_occurence );
			}
		}

		//append the mail removed notice.
		if ( $t_description !== $p_description )
		{
			$t_description .= $this->_mail_removed_reply_text;
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
	# Process the body of an email to separate signatures and replies
	private function parse_email_body( $p_description )
	{
		$t_description = $p_description;

		if ( $this->_mail_remove_replies || $this->_mail_strip_signature )
		{
			// Lines starting with -- are seen as signatures. EmailReplyParser doesn't use "-----Original Message-----" anyway
			$t_description = preg_replace('/(?:\\\\{1}---){1,2}-{0,2}\h?[ \S]+\h?(?:\\\\{1}---){1,2}-{0,2}/', '', $t_description );

			$EmailBodyParser = new EmailReplyParser\Parser\EmailParser;
			$bodyParsed = $EmailBodyParser->parse( $t_description );
			$bodyfragments = $bodyParsed->getFragments();

			$selectedFragments = array_filter( $bodyfragments, array( $this, 'selectFragments' ) );

			$t_description = rtrim( (string)implode( "\n", $selectedFragments ) );
		}

		return( $t_description );
	}

	# --------------------
	# Select the fragments of interest to us
	private function selectFragments( EmailReplyParser\Fragment $fragment )
	{
		return( !( $fragment->isEmpty() ) && !( $this->_mail_remove_replies && $fragment->isQuoted() ) && !( $this->_mail_strip_signature && $fragment->isSignature() ) );
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
	# Limit email body size
	private function limit_body_size( $p_type, $p_description, &$p_email )
	{
		$t_description = $p_description;

		if ( mb_strlen( $t_description ) > $this->_mail_max_email_body )
		{
			$t_mail_max_email_body_text = "\n" . $this->_mail_max_email_body_text;
			
			// Decide on max length and truncate. Remove one extra character just to be sure
			$t_description = mb_substr( $t_description, 0, $this->_mail_max_email_body - mb_strlen( $t_mail_max_email_body_text ) - 1 ) . $t_mail_max_email_body_text;

			if ( $this->_mail_max_email_body_add_attach )
			{
				$t_part = array(
					'name' => $p_type . '.txt',
					'ctype' => 'text/plain',
					'body' => $p_description,
				);

				$p_email[ 'X-Mantis-Parts' ][] = $t_part;
			}
		}

		return( $t_description );
	}

	# --------------------
	# Check whether the priority thats going to be used actually exists in MantisBT
	private function verify_priority( $p_priority )
	{
		$t_priority = config_get( 'default_bug_priority' );

		if ( $this->_mail_use_bug_priority && $p_priority !== FALSE )
		{
			$t_available_priorities = MantisEnum::getValues( config_get( 'priority_enum_string' ) );
			if ( in_array( (int) $p_priority, $t_available_priorities, TRUE ) )
			{
				$t_priority = $p_priority;
			}
			else
			{
				$this->custom_error( 'Unknown MantisBT priority encountered (' . $p_priority . '). Falling back to default priority', FALSE );
			}
		}

		return( $t_priority );
	}

	# --------------------
	# Show memory usage in debug mode
	private function show_memory_usage( $p_location )
	{
		if ( !$this->_test_only && $this->_mail_debug && $this->_mail_debug_show_memory_usage )
		{
			$t_current_runtime = ( ( $this->_mailbox_starttime !== NULL ) ? round( ERP_get_timestamp() - $this->_mailbox_starttime, 4 ) : 0 );
			echo 'Debug output memory usage' . "\n" .
				'Location: Mail API - ' . $p_location . "\n" .
				'Runtime in seconds: ' . $t_current_runtime . "\n" .
				'Current memory usage: ' . ERP_formatbytes( memory_get_usage( FALSE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Peak memory usage: ' . ERP_formatbytes( memory_get_peak_usage( FALSE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Current real memory usage: ' . ERP_formatbytes( memory_get_usage( TRUE ) ) . ' / ' . $this->_memory_limit . "\n" .
				'Peak real memory usage: ' . ERP_formatbytes( memory_get_peak_usage( TRUE ) ) . ' / ' . $this->_memory_limit . "\n\n";
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

				if( $t_user_id !== FALSE ) 
				{ 
					// Make sure that mail_reporter_id and reporter_id are not added as a monitors.
					if( $this->_mail_reporter_id != $t_user_id && $p_email[ 'Reporter_id' ] != $t_user_id )
					{
						bug_monitor( $p_bug_id, $t_user_id );

						$this->custom_error( 'Monitor: ' . $t_user_id . ' - ' . $t_email . ' --> Issue ID: #' . $p_bug_id, FALSE );
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

	# --------------------
	# Returns the current timestamp
	function ERP_get_timestamp()
	{
		$t_time = explode( ' ', microtime() );
		return( $t_time[1] + $t_time[0] );
	}

?>
