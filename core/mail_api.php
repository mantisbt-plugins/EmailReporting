<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# Copyright (C) 2007  Rolf Kleef - rolf@drostan.org (IMAP)
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id: mail_api.php,v 1.41 2010/04/09 18:08:34 SL-Server\SC Kruiper Exp $
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

	# --------------------
	# show pear error when login to mailbox failed
	#  return a boolean for whether the mailbox has failed
	function ERP_pear_error( $p_mailbox_description, &$p_result )
	{
		if ( PEAR::isError( $p_result ) )
		{
			echo "\n\n" . 'Error: ' . $p_mailbox_description . "\n" . $p_result->toString();
			return( TRUE );
		}
		else
		{
			return( FALSE );
		}
	}

	# --------------------
	# show non-pear error when connection to mailbox failed
	#  return a false or an array with a customized error
	function ERP_custom_error( $p_mailbox_description, &$p_test_only, $p_error_text )
	{
		$t_error_text = "\n\n" . 'Error: ' . $p_mailbox_description . "\n" . ' -> ' . $p_error_text . '.';

		if ( $p_test_only === FALSE )
		{
			echo $t_error_text;
			$t_result = FALSE;
		}
		else
		{
			$t_result = array(
				'ERROR_TYPE'	=> 'NON-PEAR-ERROR',
				'ERROR_MESSAGE'	=> $t_error_text,
			);
		}

		return( $t_result );
	}

	# --------------------
	# return all mails for an mailbox
	#  return a boolean for whether the mailbox was succesfully processed
	function ERP_process_all_mails( $p_mailbox, $p_test_only = FALSE )
	{
		$t_mailbox = array_merge( ERP_get_default_mailbox(), $p_mailbox );
		
		$t_mailbox_function = 'ERP_process_all_mails_' . strtolower( $t_mailbox[ 'mailbox_type' ] );

		$t_result = $t_mailbox_function( $t_mailbox, $p_test_only );

		return( $t_result );
	}

	# --------------------
	# return all mails for an pop3 mailbox
	#  return a boolean for whether the mailbox was succesfully processed
	function ERP_process_all_mails_pop3( &$p_mailbox, $p_test_only = FALSE )
	{
		$t_mailbox_hostname = ERP_prepare_mailbox_hostname( $p_mailbox );

		$t_mailbox_connection = new Net_POP3();

		$t_result = $t_mailbox_connection->connect( $t_mailbox_hostname[ 'hostname' ], $t_mailbox_hostname[ 'port' ] );

		if ( $t_result === TRUE )
		{
			$t_result = ERP_mailbox_login( $p_mailbox, $t_mailbox_connection );
		}
		else
		{
			$t_error_text = 'Failed to connect to mail server';
			$t_result = ERP_custom_error( $p_mailbox[ 'mailbox_description' ], $p_test_only, $t_error_text );

			return( $t_result );
		}

		if ( $p_test_only === FALSE )
		{
			if ( !ERP_pear_error( $p_mailbox[ 'mailbox_description' ], $t_result ) )
			{
				$t_mail_delete = plugin_config_get( 'mail_delete' );

				$t_numMsg = ERP_check_fetch_max( $t_mailbox_connection->numMsg() );

				for ( $i = 1; $i <= $t_numMsg; $i++ )
				{
					ERP_process_single_email( $i, $p_mailbox, $t_mailbox_connection );

					if ( $t_mail_delete )
					{
						$t_mailbox_connection->deleteMsg( $i );
					}
				}
			}
		}

		$t_mailbox_connection->disconnect();

		return( $t_result );
	}

	# --------------------
	# return all mails for an imap mailbox
	#  return a boolean for whether the mailbox was succesfully processed
	function ERP_process_all_mails_imap( &$p_mailbox, $p_test_only = FALSE )
	{
		$t_mailbox_hostname = ERP_prepare_mailbox_hostname( $p_mailbox );

		$t_mailbox_connection = new Net_IMAP( $t_mailbox_hostname[ 'hostname' ], $t_mailbox_hostname[ 'port' ] );

		if ( $t_mailbox_connection->_connected === TRUE )
		{
			$t_result = ERP_mailbox_login( $p_mailbox, $t_mailbox_connection );
		}
		else
		{
			$t_error_text = 'Failed to connect to mail server';
			$t_result = ERP_custom_error( $p_mailbox[ 'mailbox_description' ], $p_test_only, $t_error_text );

			return( $t_result );
		}

		$t_mailbox_basefolder = ( ( empty( $p_mailbox[ 'mailbox_basefolder' ] ) ) ? $t_mailbox_connection->getCurrentMailbox() : $p_mailbox[ 'mailbox_basefolder' ] );

		if ( $t_result === TRUE )
		{
			if ( $t_mailbox_connection->mailboxExist( $t_mailbox_basefolder ) )
			{
				if ( $p_test_only === FALSE )
				{
					$t_mailbox_createfolderstructure = $p_mailbox[ 'mailbox_createfolderstructure' ];

					// There does not seem to be a viable api function which removes this plugins dependability on table column names
					// So if a column name is changed it might cause problems if the code below depends on it.
					// Luckily we only depend on id, name and enabled
					if ( $t_mailbox_createfolderstructure === TRUE )
					{
						$t_projects = project_get_all_rows();
						$t_hierarchydelimiter = $t_mailbox_connection->getHierarchyDelimiter();
					}
					else
					{
						$t_projects = array( 0 => project_get_row( $p_mailbox[ 'mailbox_project' ] ) );
					}

					$t_total_fetch_counter = 0;

					foreach ( $t_projects AS $t_project )
					{
						if ( $t_project[ 'enabled' ] == TRUE && ERP_check_fetch_max( $t_total_fetch_counter, 0, TRUE ) === FALSE )
						{
							$t_project_name = ERP_cleanup_project_name( $t_project[ 'name' ] );

							$t_foldername = $t_mailbox_basefolder . ( ( $t_mailbox_createfolderstructure ) ? $t_hierarchydelimiter . $t_project_name : NULL );

							if ( $t_mailbox_connection->mailboxExist( $t_foldername ) === TRUE )
							{
								$t_mailbox_connection->selectMailbox( $t_foldername );

								$t_isdeleted_count = 0;

								$t_numMsg = $t_mailbox_connection->numMsg();
								if ( !ERP_pear_error( $p_mailbox[ 'mailbox_description' ], $t_numMsg ) )
								{
									$t_numMsg = ERP_check_fetch_max( $t_numMsg, $t_total_fetch_counter );

									for ( $i = 1; ( $i - $t_isdeleted_count ) <= $t_numMsg; $i++ )
									{
										if ( $t_mailbox_connection->isDeleted( $i ) === TRUE )
										{
											$t_isdeleted_count++;
										}
										else
										{
											ERP_process_single_email( $i, $p_mailbox, $t_mailbox_connection, $t_project[ 'id' ] );

											$t_total_fetch_counter++;

											$t_mailbox_connection->deleteMsg( $i );
										}
									}
								}
							}
							else
							{
								if ( $t_mailbox_createfolderstructure === TRUE )
								{
									// create this mailbox
									$t_mailbox_connection->createMailbox( $t_foldername );
								}
							}
						}
					}
				}
			}
			else
			{
				$t_error_text = 'IMAP basefolder not found';
				$t_result = ERP_custom_error( $p_mailbox[ 'mailbox_description' ], $p_test_only, $t_error_text );
			}
		}
		elseif ( $p_test_only === FALSE )
		{
			ERP_pear_error( $p_mailbox[ 'mailbox_description' ], $t_result );
		}

		if ( $t_mailbox_connection->_connected === TRUE )
		{
			// mail_delete decides whether to perform the expunge command before closing the connection
			$t_mail_delete = plugin_config_get( 'mail_delete' );

			// Rolf Kleef: explicit expunge to remove deleted messages, disconnect() gives an error...
			// EmailReporting 0.7.0: Corrected IMAPProtocol_1.0.3.php on line 704. disconnect() works again
			//$t_mailbox->expunge();
			$t_mailbox_connection->disconnect( (bool) $t_mail_delete );
		}
		
		return( $t_result );
	}

	# --------------------
	# Perform the login to the mailbox
	function ERP_mailbox_login( &$p_mailbox, &$p_mailbox_connection )
	{
		$t_mailbox_username = $p_mailbox[ 'mailbox_username' ];
		$t_mailbox_password = base64_decode( $p_mailbox[ 'mailbox_password' ] );
		$t_mailbox_auth_method = $p_mailbox[ 'mailbox_auth_method' ];

		$t_result = $p_mailbox_connection->login( $t_mailbox_username, $t_mailbox_password, $t_mailbox_auth_method );

		return( $t_result );
	}

	# --------------------
	# Process a single email from either a pop3 or imap mailbox
	function ERP_process_single_email( &$p_i, &$p_mailbox, &$p_mailbox_connection , $p_project_id = NULL )
	{
		$t_mail_debug    = plugin_config_get( 'mail_debug' );

		$t_msg = $p_mailbox_connection->getMsg( $p_i );

		$t_mail = ERP_parse_content( $t_msg );

		if ( $t_mail_debug )
		{
			var_dump( $t_mail );
			ERP_save_message_to_file( $t_msg );
		}

		$t_mail_from = ERP_parse_address( $t_mail[ 'From' ] );
		if ( email_is_valid( $t_mail_from[ 'email' ] ) )
		{
			ERP_add_bug( $t_mail, $p_mailbox, $p_project_id );
		}
		else
		{
			echo 'From email address rejected by email_is_valid function: ' . $t_mail[ 'From' ] . "\n";
		}
	}

	# --------------------
	# Translate the project name into an IMAP folder name:
	# - translate all accented characters to plain ASCII equivalents
	# - replace all but alphanum chars and space and colon to dashes
	# - replace multiple dots by a single one
	# - strip spaces, dots and dashes at the beginning and end
	# (It should be possible to use UTF-7, but this is working)
	function ERP_cleanup_project_name( $p_project_name )
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
	function ERP_prepare_mailbox_hostname( &$p_mailbox )
	{
		$t_def_ports = array(
			'POP3' => array( 110, 995 ),
			'IMAP' => array( 143, 993 ),
		);

		$t_mailbox_hostname = ERP_correct_hostname_port( $p_mailbox[ 'mailbox_hostname' ] );

		if ( $p_mailbox[ 'mailbox_encryption' ] !== 'None' && extension_loaded( 'openssl' ) )
		{
			$t_mailbox_hostname[ 'hostname' ] = strtolower( $p_mailbox[ 'mailbox_encryption' ] ) . '://' . $t_mailbox_hostname[ 'hostname' ];

			$t_mailbox_def_port = $t_def_ports[ $p_mailbox[ 'mailbox_type' ] ][ 1 ];
		}
		else
		{
			$t_mailbox_def_port = $t_def_ports[ $p_mailbox[ 'mailbox_type' ] ][ 0 ];
		}

		$t_mailbox_hostname[ 'port' ] = ( ( !empty( $t_mailbox_hostname[ 'port' ] ) && ( (int) $t_mailbox_hostname[ 'port' ] ) > 0 ) ? (int) $t_mailbox_hostname[ 'port' ] : (int) $t_mailbox_def_port );

		return( $t_mailbox_hostname );
	}

	# --------------------
	# return whether the current process has reached the mail_fetch_max parameter
	# $p_return_bool decides whether or not a boolean or a integer is returned
	#  integer will be the maximum number of emails that are allowed to be processed for this mailbox
	#  boolean will be true or false depending on whether or not the maximum number of emails have been processed
	function ERP_check_fetch_max( $p_numMsg, $p_numMsg_processed = 0, $p_return_bool = FALSE )
	{
		$t_mail_fetch_max = plugin_config_get( 'mail_fetch_max' );

		if ( ( $p_numMsg + $p_numMsg_processed ) >= $t_mail_fetch_max )
		{
			$t_return_value = ( ( $p_return_bool ) ? TRUE : $t_mail_fetch_max - $p_numMsg_processed );
		}
		else
		{
			$t_return_value = ( ( $p_return_bool ) ? FALSE : $p_numMsg );
		}

		return( $t_return_value );
	}

	# --------------------
	# return the mail parsed for Mantis
	function ERP_parse_content( &$p_mail )
	{
		$t_mail_parse_mime            = plugin_config_get( 'mail_parse_mime' );
		$t_mail_parse_html            = plugin_config_get( 'mail_parse_html' );
		$t_mail_use_bug_priority      = plugin_config_get( 'mail_use_bug_priority' );
		$t_mail_bug_priority_default  = plugin_config_get( 'mail_bug_priority_default' );
		$t_mail_bug_priority          = plugin_config_get( 'mail_bug_priority' );
		$t_mail_add_complete_email    = plugin_config_get( 'mail_add_complete_email' );
		$t_mail_encoding              = plugin_config_get( 'mail_encoding' );

		$t_options = array();
		$t_options[ 'parse_mime' ] = $t_mail_parse_mime;
		$t_options[ 'parse_html' ] = $t_mail_parse_html;
		$t_options[ 'mail_encoding' ] = $t_mail_encoding;

		$t_mp = new Mail_Parser( $t_options );

		$t_mp->setInputString( $p_mail );
		$t_mp->parse();

		$t_mail = array();
		$t_mail[ 'From' ] = $t_mp->from();

		$t_mail[ 'Subject' ] = trim( $t_mp->subject() );

		$t_mail[ 'X-Mantis-Body' ] = trim( $t_mp->body() );

		$t_mail[ 'X-Mantis-Parts' ] = $t_mp->parts();

		if ( TRUE == $t_mail_use_bug_priority )
		{
			$t_priority =  strtolower( $t_mp->priority() );
			$t_mail[ 'Priority' ] = $t_mail_bug_priority[ $t_priority ];
		}
		else
		{
			$t_mail[ 'Priority' ] = $t_mail_bug_priority_default;
		}

		if ( TRUE == $t_mail_add_complete_email )
		{
			$t_part = array(
				'name' => 'Complete email.txt',
				'ctype' => 'text/plain',
				'body' => $p_mail,
			);

			$t_mail[ 'X-Mantis-Parts' ][] = $t_part;
		}

		return( $t_mail );
	}

	# --------------------
	# return the mailadress from the mail's 'From'
	function ERP_parse_address( $p_mailaddress )
	{
		if ( preg_match( "/(.*?)<(.*?)>/", $p_mailaddress, $matches ) )
		{
			$v_mailaddress = array(
				'username' => trim( $matches[ 1 ], '"\' ' ),
				'email'    => trim( $matches[ 2 ] ),
			);
		}
		else
		{
			$v_mailaddress = array(
				'username' => '',
				'email'    => $p_mailaddress,
			);
		}

		return( $v_mailaddress );
	}

	# --------------------
	# return the a valid username from an email address
	function ERP_prepare_username( $p_mailusername )
	{
		# I would have liked to validate the username and remove any non-allowed characters
		# using the config user_login_valid_regex but that seems not possible and since
		# it's a config any mantis installation could have a different one
		if ( user_is_name_valid( $p_mailusername[ 'username' ] ) )
		{
			return( $p_mailusername[ 'username' ] );
		}

		return( strtolower( str_replace( array( '@', '.', '-' ), '_', $p_mailusername[ 'email' ] ) ) );
	}

	# --------------------
	# return true if there is a valid mantis bug refererence in subject or return false if not found
	function ERP_mail_is_a_bugnote( $p_mail_subject )
	{
		if ( preg_match( "/\[([A-Za-z0-9-_\. ]*\s[0-9]{1,7})\]/", $p_mail_subject ) )
		{
			$t_bug_id = mail_get_bug_id_from_subject( $p_mail_subject );

			if ( bug_exists( $t_bug_id ) && !bug_is_readonly( $t_bug_id ) )
			{
				return( TRUE );
			}
		}

		return( FALSE );
	}

	# --------------------
	# return the bug's id from the subject
	function ERP_get_bug_id_from_subject( $p_mail_subject )
	{
		preg_match( "/\[([A-Za-z0-9-_\. ]*\s([0-9]{1,7}?))\]/", $p_mail_subject, $v_matches );

		return( $v_matches[ 2 ] );
	}
	
	# --------------------
	# return the user id for the mail reporting user
	function ERP_get_user( $p_mailaddress )
	{
		$t_mail_use_reporter    = plugin_config_get( 'mail_use_reporter' );
		$t_mail_reporter_id     = plugin_config_get( 'mail_reporter_id' );
		
		$v_mailaddress = ERP_parse_address( $p_mailaddress );

		// Need to disable show_realname because i must have the username
		config_set_cache( 'show_realname', OFF, CONFIG_TYPE_STRING );
		config_set_global( 'show_realname', OFF );

		if ( $t_mail_use_reporter )
		{
			// Always report as mail_reporter
			$t_reporter_id = $t_mail_reporter_id;
			$t_reporter = user_get_name( $t_mail_reporter_id );
		}
		else
		{
			// Try to get the reporting users id
			$t_reporter_id = user_get_id_by_email( $v_mailaddress[ 'email' ] );

			if ( !$t_reporter_id )
			{
				$t_mail_auto_signup = plugin_config_get( 'mail_auto_signup' );

				if ( $t_mail_auto_signup )
				{
					// So, we have to sign up a new user...
					$t_reporter = ERP_prepare_username( $v_mailaddress );

					// user_is_name_valid is performed twice (one time in mail_prepare_username
					// and one time here because the name could be the email address if the
					// username already failed)
					if ( user_is_name_valid( $t_reporter ) && user_is_name_unique( $t_reporter ) )
					{
						if( user_signup( $t_reporter, $v_mailaddress[ 'email' ] ) )
						{
							# notify the selected group a new user has signed-up
							email_notify_new_account( $t_reporter, $v_mailaddress[ 'email' ] );

							$t_reporter_id = user_get_id_by_email( $v_mailaddress[ 'email' ] );
						}
					}

					if ( !$t_reporter_id )
					{
						echo 'Failed to create user based on: ' . $p_mailaddress . "\n" . 'Falling back to the mail_reporter' . "\n";
					}
				}

				if ( !$t_reporter_id )
				{
					// Fall back to the default mail_reporter
					$t_reporter_id = $t_mail_reporter_id;
					$t_reporter = user_get_name( $t_mail_reporter_id );
				}
			}
			else
			{
				$t_reporter = user_get_name( $t_reporter_id );
			}
		}

		echo 'Reporter: ' . $t_reporter_id . ' - ' . $v_mailaddress[ 'email' ] . "\n\n";
		auth_attempt_script_login( $t_reporter );

		return $t_reporter_id;
	}

	# --------------------
	# Very dirty: Adds a file to a bug.
	# returns true on success and the filename with reason on error
	function ERP_add_file( $p_bug_id, &$p_part )
	{
		# Handle the file upload
		static $number = 1;
		static $c_bug_id = NULL;

		$t_max_file_size = (int) min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );

		$t_part_name = ( ( isset( $p_part[ 'name' ] ) ) ? trim( $p_part[ 'name' ] ) : NULL );
		$t_strlen_body = strlen( trim( $p_part[ 'body' ] ) );

		if ( empty( $t_part_name ) )
		{
			return( $t_part_name . ' = filename is missing' . "\n" );
		}
		elseif ( !file_type_check( $t_part_name ) )
		{
			return( $t_part_name . ' = filetype not allowed' . "\n" );
		}
		elseif ( 0 == $t_strlen_body )
		{
			return( $t_part_name . ' = attachment size is zero (' . $t_strlen_body . ' / ' . $t_max_file_size . ')' . "\n" );
		}
		elseif ( $t_strlen_body > $t_max_file_size )
		{
			return( $t_part_name . ' = attachment size exceeds maximum allowed file size (' . $t_strlen_body . ' / ' . $t_max_file_size . ')' . "\n" );
		}
		else
		{
			if ( $p_bug_id !== $c_bug_id )
			{
				$number = 1;
				$c_bug_id = $p_bug_id;
			}

			while( !file_is_name_unique( $number . '-' . $t_part_name, $p_bug_id ) )
			{
				$number++;
			}

			$t_mail_tmp_directory = plugin_config_get( 'mail_tmp_directory' );
			$t_file_name = $t_mail_tmp_directory . '/' . md5( microtime() );

			file_put_contents( $t_file_name, $p_part[ 'body' ] );

			ERP_custom_file_add( $p_bug_id, array(
				'tmp_name' => realpath( $t_file_name ),
				'name'     => $number . '-' . $t_part_name,
				'type'     => $p_part[ 'ctype' ],
				'error'    => NULL
			), 'bug' );

			$t_method = config_get( 'file_upload_method' );
			if ( $t_method != DISK )
			{
				unlink( $t_file_name );
			}

			$number++;
		}

		return( TRUE );
	}

	# --------------------
	# Saves the complete email to file
	# Only works in debug mode
	function ERP_save_message_to_file( &$p_msg )
	{
		$t_mail_debug            = plugin_config_get( 'mail_debug' );
		$t_mail_debug_directory  = plugin_config_get( 'mail_debug_directory' );
		
		if ( $t_mail_debug && is_dir( $t_mail_debug_directory ) && is_writeable( $t_mail_debug_directory ) )
		{
			$t_file_name = $t_mail_debug_directory . '/' . time() . '_' . md5( microtime() );
			file_put_contents( $t_file_name, $p_msg );
		}
	}

	# --------------------
	# Removes the original mantis email from replies
	function ERP_identify_reply_part( $p_description )
	{
		$t_mail_identify_reply = plugin_config_get( 'mail_identify_reply' );

		if ( $t_mail_identify_reply )
		{
			$t_email_separator1 = config_get( 'email_separator1' );

			# The pear mimeDecode.php seems to be removing the last "=" in some versions of the pear package.
			# the version delivered with this package seems to be working OK though
			$t_email_separator1 = substr( $t_email_separator1, 0, -1);

			$t_first_occurence = strpos( $p_description, $t_email_separator1 );
			if ( $t_first_occurence !== FALSE && substr_count( $p_description, $t_email_separator1, $t_first_occurence ) >= 5 )
			{
				$t_mail_removed_reply_text = plugin_config_get( 'mail_removed_reply_text' );

				$t_description = substr( $p_description, 0, $t_first_occurence ) . $t_mail_removed_reply_text;

				return( $t_description );
			}
		}

		return( $p_description );
	}

	# --------------------
	# Fixes an empty subject and description with a predefined default text
	function ERP_fix_empty_fields( &$p_mail )
	{
		$t_mail = $p_mail;

		if ( empty( $t_mail[ 'Subject' ] ) )
		{
			$t_mail[ 'Subject' ] = plugin_config_get( 'mail_nosubject' );
		}

		if ( empty( $t_mail[ 'X-Mantis-Body' ] ) )
		{
			$t_mail[ 'X-Mantis-Body' ] = plugin_config_get( 'mail_nodescription' );
		}

		return( $t_mail );
	}

	# --------------------
	# Add the save from text if enabled
	function ERP_apply_mail_save_from( $p_from, $p_description )
	{
		$t_description = $p_description;
		$t_mail_save_from = plugin_config_get( 'mail_save_from' );

		if ( $t_mail_save_from ) {
			$t_description	= 'Email from: ' . $p_from . "\n\n" . $t_description;
		}

		return( $t_description );
	}

	# --------------------
	# Adds a bug which is reported via email
	# Taken from bug_report.php in MantisBT 1.2.0rc1
	function ERP_add_bug( &$p_mail, &$p_mailbox, $p_imap_project_id = NULL )
	{
		$t_allow_file_upload = config_get( 'allow_file_upload' );

		$t_reporter_id = ERP_get_user( $p_mail[ 'From' ] );

		if ( ERP_mail_is_a_bugnote( $p_mail[ 'Subject' ] ) )
		{
			$t_description = $p_mail[ 'X-Mantis-Body' ];

			$t_bug_id = ERP_get_bug_id_from_subject( $p_mail[ 'Subject' ] );

			$t_description = ERP_identify_reply_part( $t_description );
			$t_description = ERP_apply_mail_save_from( $p_mail[ 'From' ], $t_description );

			$t_resolved = config_get( 'bug_resolved_status_threshold' );
			$t_status = bug_get_field( $t_bug_id, 'status' );

			if ( $t_resolved <= $t_status )
			{
				# Reopen issue and add a bug note
				bug_reopen( $t_bug_id, $t_description );
			}
			elseif ( !empty( $t_description ) )
			{
				# Add a bug note
				bugnote_add( $t_bug_id, $t_description );
			}
		}
		else
		{
			$p_mail = ERP_fix_empty_fields( $p_mail );

			$t_bug_data = new BugData;
			$t_bug_data->build					= '';
			$t_bug_data->platform				= '';
			$t_bug_data->os						= '';
			$t_bug_data->os_build				= '';
			$t_bug_data->version				= '';
			$t_bug_data->profile_id				= 0;
			$t_bug_data->handler_id				= 0;
			$t_bug_data->view_state				= config_get( 'default_bug_view_status' );

			$t_bug_data->category_id			= $p_mailbox[ 'mailbox_global_category' ];
			$t_bug_data->reproducibility		= config_get( 'default_bug_reproducibility', 10 );
			$t_bug_data->severity				= config_get( 'default_bug_severity', 50 );
			$t_bug_data->priority				= $p_mail[ 'Priority' ];
			$t_bug_data->projection				= config_get( 'default_bug_projection' );
			$t_bug_data->eta					= config_get( 'default_bug_eta' );
			$t_bug_data->resolution				= config_get( 'default_bug_resolution' );
			$t_bug_data->status					= config_get( 'bug_submit_status' );
			$t_bug_data->summary				= $p_mail[ 'Subject' ];

			$t_bug_data->description			= ERP_apply_mail_save_from( $p_mail[ 'From' ], $p_mail[ 'X-Mantis-Body' ] );

			$t_bug_data->steps_to_reproduce		= config_get( 'default_bug_steps_to_reproduce' );
			$t_bug_data->additional_information	= config_get( 'default_bug_additional_info' );
			$t_bug_data->due_date 				= date_get_null();

			$t_bug_data->project_id				= ( ( is_null( $p_imap_project_id ) ) ? $p_mailbox[ 'mailbox_project' ] : $p_imap_project_id );

			$t_bug_data->reporter_id			= $t_reporter_id;

			$t_bug_data->summary				= trim( $t_bug_data->summary );

			# Create the bug
			$t_bug_id = $t_bug_data->create();

			email_new_bug( $t_bug_id );
		}
		
		# Add files
		if ( $t_allow_file_upload )
		{
			if ( NULL != $p_mail[ 'X-Mantis-Parts' ] )
			{
				$t_rejected_files = NULL;

				foreach ( $p_mail[ 'X-Mantis-Parts' ] as $part )
				{
					$t_file_rejected = ERP_add_file( $t_bug_id, $part );

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

					$t_reject_rejected_files = ERP_add_file( $t_bug_id, $part );
					if ( $t_reject_rejected_files !== TRUE )
					{
						$part[ 'body' ] .= $t_reject_rejected_files;
						echo 'Failed to add "' . $part[ 'name' ] . '" to the issue. See below for all errors.' . "\n" . $part[ 'body' ];
					}
				}
			}
		}
	}

?>
