<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id: mail_api.php,v 1.8 2009/07/28 18:52:43 SC Kruiper Exp $
	# --------------------------------------------------------

	require_once( 'bug_api.php' );
	require_once( 'bugnote_api.php' );
	require_once( 'user_api.php' );
	require_once( 'file_api.php' );

	require_once( 'custom_file_api.php' );

	# This page receives an E-Mail via POP3 and generates an Report

	require_once( 'Net/POP3.php' );
	require_once( 'Mail/Parser.php' );

	# --------------------
	# return all mailaccounts
	#  return an empty array if there are none
	function mail_get_accounts() {
		$t_accounts = plugin_config_get( 'mailboxes', array() );

		return $t_accounts;
	}

	# --------------------
	# return all mails for an account
	#  return an empty array if there are no new mails
	function mail_process_all_mails( &$p_account, $p_test_only = false ) {
		$t_mail_fetch_max	= plugin_config_get( 'mail_fetch_max' );
		$t_mail_delete		= plugin_config_get( 'mail_delete' );
		$t_mail_auth_method	= plugin_config_get( 'mail_auth_method' );
		$t_mail_debug		= plugin_config_get( 'mail_debug' );


		$t_mailbox = &new Net_POP3();
		$t_mailbox_hostname = explode( ':', $p_account[ 'mailbox_hostname' ], 2 );
		$t_mailbox_username = $p_account[ 'mailbox_username' ];
		$t_mailbox_password = base64_decode( $p_account[ 'mailbox_password' ] );
		$t_mailbox->connect( $t_mailbox_hostname[ 0 ], ( ( empty( $t_mailbox_hostname[ 1 ] ) || (int) $t_mailbox_hostname[ 1 ] === 0 ) ? 110 : (int) $t_mailbox_hostname[ 1 ] ) );
		$t_result = $t_mailbox->login( $t_mailbox_username, $t_mailbox_password, $t_mail_auth_method );

		if ( $p_test_only === false )
		{
			if ( PEAR::isError( $t_result ) ) {
				echo "\n\nerror:" . $p_account[ 'mailbox_description' ] . "\n";
				echo $t_result->toString();
			}

			if ( 0 == $t_mailbox->numMsg() ) {
				return;
			}

			for ( $j = 1; $j <= $t_mailbox->numMsg(); $j++ )
			{
				for ( $i = $j; $i < $j+$t_mail_fetch_max; $i++ ) {
					$t_msg = $t_mailbox->getMsg( $i );

					$t_mail = mail_parse_content( $t_msg );

					if ( $t_mail_debug ) {
						var_dump( $t_mail );
						mail_save_message_to_file( $t_msg );
					}

					mail_add_bug( $t_mail, $p_account );

					if ( $t_mail_delete ) {
						$t_mailbox->deleteMsg( $i );
					}
				}
			}
		}

		$t_mailbox->disconnect();

		if ( $p_test_only === true )
		{
			return( $t_result );
		}
	}

	# --------------------
	# return the mail parsed for Mantis
	function mail_parse_content ( &$p_mail ) {
		$t_mail_debug		= plugin_config_get( 'mail_debug' );
		$t_mail_parse_mime	= plugin_config_get( 'mail_parse_mime' );
		$t_mail_parse_html	= plugin_config_get( 'mail_parse_html' );
		$t_mail_use_bug_priority = plugin_config_get( 'mail_use_bug_priority' );
		$t_mail_bug_priority_default = plugin_config_get( 'mail_bug_priority_default' );
		$t_mail_bug_priority	= plugin_config_get( 'mail_bug_priority' );
		$t_mail_add_complete_email	= plugin_config_get( 'mail_add_complete_email' );
		$t_mail_encoding = plugin_config_get( 'mail_encoding' );

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
		if ( empty( $t_mail[ 'Subject' ] ) ) {
			$t_mail[ 'Subject' ] = plugin_config_get( 'mail_nosubject' );
		}

		$t_mail[ 'X-Mantis-Body' ] = trim( $t_mp->body() );
		if ( empty( $t_mail[ 'X-Mantis-Body' ] ) ) {
			$t_mail[ 'X-Mantis-Body' ] = plugin_config_get( 'mail_nodescription' );
		}

		$t_mail[ 'X-Mantis-Parts' ] = $t_mp->parts();

		if ( true == $t_mail_use_bug_priority ) {
			$t_priority =  strtolower( $t_mp->priority() );
			$t_mail[ 'Priority' ] = $t_mail_bug_priority[ $t_priority ];
		} else {
			$t_mail[ 'Priority' ] = gpc_get_int( 'priority', $t_mail_bug_priority_default );
		}

		if ( true == $t_mail_add_complete_email ) {
			$t_part = array(
				'name' => 'Complete email.txt',
				'ctype' => 'text/plain',
				'body' => $p_mail,
			);

			$t_mail[ 'X-Mantis-Complete' ] = $t_part;
		} else {
			$t_mail[ 'X-Mantis-Complete' ] = null;
		}

		return $t_mail;
	}

	# --------------------
	# return the mailadress from the mail's 'From'
	function mail_parse_address ( $p_mailaddress ) {
		if ( preg_match( "/<(.*?)>/", $p_mailaddress, $matches ) ) {
			$v_mailaddress = $matches[ 1 ];
		}

		return $v_mailaddress;
	}

	# --------------------
	# return the a valid username from an email address
	function mail_user_name_from_address ( $p_mailaddress ) {
		return strtolower( preg_replace( "/[@\.-]/", '_', $p_mailaddress ) );
	}

	# --------------------
	# return true if there is a valid mantis bug refererence in subject or return false if not found
	function mail_is_a_bugnote ( $p_mail_subject ) {
		if ( preg_match( "/\[([A-Za-z0-9-_\. ]*\s[0-9]{1,7})\]/", $p_mail_subject ) ){
			$t_bug_id = mail_get_bug_id_from_subject( $p_mail_subject );
			if ( bug_exists( $t_bug_id ) ){
				return true;
			}
		}

		return false;
	}

	# --------------------
	# return the bug's id from the subject
	function mail_get_bug_id_from_subject ( $p_mail_subject ) {
		preg_match( "/\[([A-Za-z0-9-_\. ]*\s([0-9]{1,7}?))\]/", $p_mail_subject, $v_matches );

		return $v_matches[ 2 ];
	}
	
	# --------------------
	# return the user id for the mail reporting user
	function mail_get_user ( $p_mailaddress ) {
		$t_mail_use_reporter	= plugin_config_get( 'mail_use_reporter' );
		$t_mail_auto_signup	= plugin_config_get( 'mail_auto_signup' );
		$t_mail_reporter	= plugin_config_get( 'mail_reporter' );
		
		$v_mailaddress = mail_parse_address( $p_mailaddress );

		if ( $t_mail_use_reporter ) {
			// Always report as mail_reporter
			$t_reporter_id = user_get_id_by_name( $t_mail_reporter );
			$t_reporter = $t_mail_reporter;
		} else {
			// Try to get the reporting users id
			$t_reporter_id = user_get_id_by_email ( $v_mailaddress );
			echo 'Reporter: ' . $t_reporter_id . '<br />' . $v_mailaddress . '<br />';
			if ( ! $t_reporter_id && $t_mail_auto_signup ) {
				// So, we've to sign up a new user...
				$t_reporter = mail_user_name_from_address ( $v_mailaddress );
				# notify the selected group a new user has signed-up
				if( user_signup( $t_reporter, $v_mailaddress ) ) {
					email_notify_new_account( $t_reporter, $v_mailaddress );
				}
				$t_reporter_id = user_get_id_by_name ( $t_reporter );
			} elseif ( ! $t_reporter_id ) {
				// Fall back to the default mail_reporter
				$t_reporter_id = user_get_id_by_name( $t_mail_reporter );
				$t_reporter = $t_mail_reporter;
			} else {
				$t_reporter = user_get_field( $t_reporter_id, 'username' );
			}
		}

		auth_attempt_script_login( $t_reporter );

		return $t_reporter_id;
	}

	# --------------------
	# Very dirty: Adds a file to a bug.
	# returns true on success and the filename with reason on error
	function mail_add_file( $p_bug_id, $p_part ) {
		# Handle the file upload
		static $number = 1;
		static $c_bug_id = null;

		$t_part_name = ( ( isset( $p_part[ 'name' ] ) ) ? trim( $p_part[ 'name' ] ) : null );

		if ( empty( $t_part_name ) )
		{
			return( $t_part_name . ' = filename is missing' . "\r\n" );
		}
		elseif( !file_type_check( $t_part_name ) )
		{
			return( $t_part_name . ' = filetype not allowed' . "\r\n" );
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

			$t_mail_tmp_directory	= plugin_config_get( 'mail_tmp_directory', '' );
			$t_file_name = ( ( empty( $t_mail_tmp_directory ) ) ? '.' : $t_mail_tmp_directory ) . '/' . md5 ( microtime() );
			$t_method = config_get( 'file_upload_method' );

			file_put_contents( $t_file_name, $p_part[ 'body' ] );

			emailreporting_custom_file_add( $p_bug_id, array(
				'tmp_name' => realpath( $t_file_name ),
				'name' => $number . '-' . $t_part_name,
				'type' => $p_part[ 'ctype' ],
				'error' => null
			), 'bug' );

			if ( $t_method != DISK )
			{
				unlink( $t_file_name );
			}

			$number++;
		}

		return( true );
	}

	# --------------------
	# Saves the complete email to file
	# Only works in debug mode
	function mail_save_message_to_file ( &$p_msg ) {
		$t_mail_debug		= plugin_config_get( 'mail_debug' );
		$t_mail_directory	= plugin_config_get( 'mail_directory' );
		
		if ( $t_mail_debug && is_dir( $t_mail_directory ) && is_writeable( $t_mail_directory ) ) {
			$t_file_name = $t_mail_directory . '/' . time() . md5( microtime() );
			file_put_contents( $t_file_name, $p_msg );
		}
	}

	# --------------------
	# Removes the original mantis email from replies
	# Very experimental new function
	function mail_identify_reply_part ( $p_description )
	{
		$t_mail_identify_reply = plugin_config_get( 'mail_identify_reply' );
		$t_email_separator1 = config_get( 'email_separator1' );

		# The pear email parser seems to be remoing the last for various reasons.
		$t_email_separator1 = substr( $t_email_separator1, 0, -1);

		if ( $t_mail_identify_reply )
		{
			$t_first_occurence = strpos( $p_description, $t_email_separator1 );
			if ( $t_first_occurence !== false && substr_count( $p_description, $t_email_separator1, $t_first_occurence ) >= 3 )
			{
				$t_description = substr( $p_description, 0, $t_first_occurence );

				return( $t_description );
			}
		}

		return( $p_description );
	}

	# --------------------
	# Adds a bug which is reported via email
	# Taken from bug_report.php in MantisBT 1.2.0rc1
	function mail_add_bug ( &$p_mail, &$p_account ) {
		$t_mail_save_from	= plugin_config_get( 'mail_save_from' );
		$t_mail_add_complete_email	= plugin_config_get( 'mail_add_complete_email' );

		$t_bug_data = new BugData;
		$t_bug_data->build				= gpc_get_string( 'build', '' );
		$t_bug_data->platform				= gpc_get_string( 'platform', '' );
		$t_bug_data->os					= gpc_get_string( 'os', '' );
		$t_bug_data->os_build				= gpc_get_string( 'os_build', '' );
		$t_bug_data->version			= gpc_get_string( 'product_version', '' );
		$t_bug_data->profile_id			= gpc_get_int( 'profile_id', 0 );
		$t_bug_data->handler_id			= gpc_get_int( 'handler_id', 0 );
		$t_bug_data->view_state			= gpc_get_int( 'view_state', config_get( 'default_bug_view_status' ) );

		$t_bug_data->category_id			= gpc_get_int( 'category_id', $p_account[ 'mailbox_global_category' ] );
		$t_bug_data->reproducibility		= config_get( 'default_bug_reproducibility', 10 );
		$t_bug_data->severity			= config_get( 'default_bug_severity', 50 );
		$t_bug_data->priority			= $p_mail[ 'Priority' ];
		$t_bug_data->projection				= gpc_get_int( 'projection', config_get( 'default_bug_projection' ) );
		$t_bug_data->eta					= gpc_get_int( 'eta', config_get( 'default_bug_eta' ) );
		$t_bug_data->resolution				= config_get( 'default_bug_resolution' );
		$t_bug_data->status					= config_get( 'bug_submit_status' );
		$t_bug_data->summary				= $p_mail[ 'Subject' ];
		
		# Ppostponed the saving of the description until after the bugnote identification
		if ( $t_mail_save_from ) {
			$t_bug_data->description	= 'Email from: ' . $p_mail[ 'From' ] . "\n\n" . $p_mail[ 'X-Mantis-Body' ];
		} else {
			$t_bug_data->description	= $p_mail[ 'X-Mantis-Body' ];
		}

		$t_bug_data->steps_to_reproduce		= gpc_get_string( 'steps_to_reproduce', config_get( 'default_bug_steps_to_reproduce' ) );;
		$t_bug_data->additional_information	= gpc_get_string( 'additional_info', config_get ( 'default_bug_additional_info' ) );
		$t_bug_data->due_date 				= gpc_get_string( 'due_date', '' );
		if ( is_blank ( $t_bug_data->due_date ) ) {
			$t_bug_data->due_date = date_get_null();
		} else {
			$t_bug_data->due_date = $t_bug_data->due_date;
		}

		$t_bug_data->project_id			= $p_account[ 'mailbox_project' ];

		$t_bug_data->reporter_id		= mail_get_user( $p_mail[ 'From' ] );

		$t_bug_data->summary			= trim( $t_bug_data->summary );

		if ( mail_is_a_bugnote( $p_mail[ 'Subject' ] ) ) {
			# Add a bug note
			$t_bug_id = mail_get_bug_id_from_subject( $p_mail[ 'Subject' ] );
			if ( ! bug_is_readonly( $t_bug_id ) ) {
				$t_description = mail_identify_reply_part( $t_bug_data->description );

				bugnote_add ( $t_bug_id, $t_description );

				email_bugnote_add ( $t_bug_id );

				if ( bug_get_field( $t_bug_id, 'status' ) > config_get( 'bug_reopen_status' ) )
				{
					bug_reopen( $t_bug_id );
				}
			}
			else{
				return;
			}
		} else	{
			# Create the bug
			$t_bug_id = $t_bug_data->create();

			email_new_bug( $t_bug_id );
		}
		
		# Add files
		if ( true == $t_mail_add_complete_email ) {
			array_push( $p_mail[ 'X-Mantis-Parts' ], $p_mail[ 'X-Mantis-Complete' ] );
		}

		if ( null != $p_mail[ 'X-Mantis-Parts' ] ) {
			$t_rejected_files = null;

			foreach ( $p_mail[ 'X-Mantis-Parts' ] as $part ) {
				$t_file_rejected = mail_add_file ( $t_bug_id, $part );

				if ( $t_file_rejected !== true )
				{
					$t_rejected_files .= $t_file_rejected;
				}
			}

			if ( !is_null( $t_rejected_files ) )
			{
				$part = array(
					'name' => 'Rejected files.txt',
					'ctype' => 'text/plain',
					'body' => 'List of rejected files' . "\r\n\r\n" . $t_rejected_files,
				);

				$t_reject_rejected_files = mail_add_file ( $t_bug_id, $part );
				if ( $t_reject_rejected_files !== true )
				{
					echo '"' . $part[ 'name' ] . '" is not allowed as a filetype.' . "\r\n" . $part[ 'body' ];
				}
			}
		}

	}

?>
