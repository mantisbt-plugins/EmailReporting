<?php
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );

class EmailReportingPlugin extends MantisPlugin
{
	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */ 
	function register()
	{
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page = 'manage_config';

		$this->version = '0.8.0-DEV';
		$this->requires = array(
			'MantisCore' => '1.2',
		);

		$this->author = plugin_lang_get( 'author' );
		$this->contact = '';
		$this->url = 'http://git.mantisforge.org/w/EmailReporting.git';
	}

	/**
	 * EmailReporting plugin configuration.
	 */
	function config()
	{
		$t_upload_tmp_dir = trim( str_replace( '\\', '/', ini_get( 'upload_tmp_dir' ) ), '/ ' );

		return array(
			'config_version'				=> 0,
			'schema'						=> -1,

			# --- mail reporting settings -----
			# Empty default mailboxes array. This array will be used for all the mailbox
			# accounts
			'mailboxes'						=> array(),

			# Do you want to secure the EmailReporting script so that it cannot be run
			# via a webserver?
			'mail_secured_script'			=> ON,

			# This tells Mantis to report all the Mail with only one account
			# ON = mail uses the reporter account in the setting below
			# OFF = it identifies the reporter using the email address of the sender
			'mail_use_reporter'				=> ON,

			# The account's name for mail reporting
			# Also used for fallback if a user is not found in database
			# Mail is just the default name which will be converted to a user id during installation
			'mail_reporter_id'				=> 'Mail',

			# Signup new users automatically (possible security risk!)
			# Default is OFF, if mail_use_reporter is OFF and this is OFF then it will
			# fallback to the mail_reporter account above
			'mail_auto_signup'				=> OFF,

			# How many mails should be fetched at the same time
			# If big mails with attachments should be received, specify only one
			'mail_fetch_max'				=> 1,

			# Add complete email into the attachments
			'mail_add_complete_email'		=> OFF,

			# Write sender of the message into the bug report
			'mail_save_from'				=> ON,

			# Parse MIME mails (may require a lot of memory)
			'mail_parse_mime'				=> OFF,

			# Parse HTML mails
			'mail_parse_html'				=> ON,

			# Try to identify only the reply parts in emails incase of notes
			'mail_identify_reply'			=> ON,

			# directory for saving temporary mail content
			'mail_tmp_directory'			=> ( ( is_blank( $t_upload_tmp_dir ) ) ? '/tmp' : $t_upload_tmp_dir ),

			# Delete incoming mail from POP3 server
			'mail_delete'					=> ON,

			# Used for debugging the system.
			# Use with care
			'mail_debug'					=> OFF,

			# Save mail contents to this directory if debug mode is ON
			'mail_debug_directory'			=> '/tmp/mantis',

			# Looks for priority header field
			'mail_use_bug_priority'			=> ON,

			# Use the following text when the subject is missing from the email
			'mail_nosubject'				=> 'No subject found', 

			# Use the following text when the description is missing from the email
			'mail_nodescription'			=> 'No description found', 

			# Use the following text when a mantis email has been removed
			'mail_removed_reply_text'		=> '[EmailReporting -> Mantis notification email removed]',

			# Classify bug priorities
			'mail_bug_priority'				=> array(
				'5 (lowest)'	=> 10,
				'4 (low)'		=> 20,
				'3 (normal)'	=> 30,
				'2 (high)'		=> 40,
				'1 (highest)'	=> 50,
				'5'		=> 20,
				'4'		=> 20,
				'3'		=> 30,
				'2'		=> 40,
				'1'		=> 50,
				'0'		=> 10,
				'low'			=> 20,
				'normal'		=> 30,
				'high'			=> 40,
				''		=> 30,
				'?'		=> 30
			),

			# Need to set the character encoding to which the email will be converted
			# This should be the same as the character encoding used in the database system used for mantis
			# values should be acceptable to the following function: http://www.php.net/mb_convert_encoding
			'mail_encoding'					=> 'UTF-8', 

			# This decides whether this script will run as a scheduled job or not
			'mail_cronjob_present'			=> ON, 

			# This decides how long between checking the mailboxes for new emails when no scheduled job is present
			'mail_check_timer'				=> 300,

			# Remove everything after and including the remove_replies_after text
			'mail_remove_replies'			=> OFF,

			# Text which decides after (and including) which all content needs to be removed
			'mail_remove_replies_after'		=> '-----Original Message-----',

			# Should users allways receive emails on actions they performed by email even though email_receive_own is OFF
			'mail_email_receive_own'		=> OFF,

			# Is this plugin allowed to process and create new bug reports
			'mail_add_bug_reports'			=> ON,

			# Is this plugin allowed to process and add bugnotes to existing issues
			'mail_add_bugnotes'				=> ON,

			# Enable fallback to mail reporter
			'mail_fallback_mail_reporter'	=> ON,
		);
	} 

	/**
	 * EmailReporting installation function.
	 */
	function install()
	{
		// We need to load a default value since the function config() which sets
		// the defaults has not been run yet. On the other hand, configuration options
		// already present in the database will be available.
		$t_mail_reporter_id = plugin_config_get( 'mail_reporter_id', 'Mail' );

		if ( $t_mail_reporter_id === 'Mail' )
		{
			# We need to allow blank emails for a sec
			config_set_cache( 'allow_blank_email', ON, CONFIG_TYPE_STRING );
			config_set_global( 'allow_blank_email', ON );

			$t_rand = MT_RAND( 1000, 50000 );

			$t_username = $t_mail_reporter_id . $t_rand;

			$t_email = '';

			$t_seed = $t_email . $t_username;

			# Create random password
			$t_password = auth_generate_random_password( $t_seed );

			# create the user
			$t_result_user_create = user_create( $t_username, $t_password, $t_email, REPORTER, FALSE, TRUE, 'Mail Reporter' );

			# Save these after the user has been created successfully
			if ( $t_result_user_create )
			{
				$t_user_id = user_get_id_by_name( $t_username );

				plugin_config_set( 'mail_reporter_id', $t_user_id );
				plugin_config_set( 'config_version', 2 );
			}

			return( $t_result_user_create );
		}

		return( TRUE );
	}

	/**
	 * EmailReporting uninstallation function.
	 */
	function uninstall()
	{
		# User removal from the install function will not be done
		# The reason being thats its possibly connected to issues in the system
		return( TRUE );
	}

	/**
	 * EmailReporting initialization function.
	 */
	function init()
	{
		$t_path = config_get_global( 'plugin_path' ) . plugin_get_current() . '/core_pear/';

		if ( is_dir( $t_path ) )
		{
			set_include_path( get_include_path() . PATH_SEPARATOR . $t_path );
		}
	}

	/**
	 * EmailReporting plugin hooks.
	 */
	function hooks( )
	{
		$hooks = array(
			'EVENT_MENU_MANAGE'	=> 'ERP_manage_emailreporting_menu',
			'EVENT_CORE_READY'	=> 'ERP_core_ready',
		);

		return $hooks;
	}

	/**
	 * EmailReporting plugin hooks - add emailreporting menu item.
	 */
	function ERP_manage_emailreporting_menu( )
	{
		return array( '<a href="' . plugin_page( 'manage_mailbox' ) . '">' . plugin_lang_get( 'manage' ) . ' ' . plugin_lang_get( 'title' ) . '</a>', );
	}

	/* 
	 * This function will run when the mantis core is ready
	 */
	function ERP_core_ready( )
	{
		$this->ERP_update_check( );

		$this->ERP_check_mantisbt_url( );

		$this->ERP_check_mantisbt_erp_path( );

		$this->ERP_email_check_all( );
	}

	/* 
	 * Since schema is not used anymore some corrections need to be applied
	 * Schema will be completely reset by this just once
	 * 
	 * The second part updates various configuration options and performs some cleaning
	 */
	function ERP_update_check( )
	{
		$t_config_version = plugin_config_get( 'config_version' );

		if ( $t_config_version === 0 )
		{
			$t_reset_schema = plugin_config_get( 'reset_schema', 0 );

			if ( $t_reset_schema === 1 )
			{
				plugin_config_delete( 'reset_schema' );
			}
			else
			{
				$t_username = plugin_config_get( 'mail_reporter' );

				$t_user_id = user_get_id_by_name( $t_username );

				if ( $t_user_id !== FALSE )
				{
					$t_user_email = user_get_email( $t_user_id );

					if ( $t_user_email === 'nomail' )
					{
						# We need to allow blank emails for a sec
						config_set_cache( 'allow_blank_email', ON, CONFIG_TYPE_STRING );
						config_set_global( 'allow_blank_email', ON );

						user_set_email( $t_user_id, '' );
					}
				}

				plugin_config_set( 'schema', -1 );
			}

			plugin_config_set( 'config_version', 1 );
		}

		if ( $t_config_version <= 1 )
		{
			$t_mail_debug_directory	= plugin_config_get( 'mail_debug_directory' );
			$t_mail_directory		= plugin_config_get( 'mail_directory', $t_mail_debug_directory );

			$t_mail_reporter		= plugin_config_get( 'mail_reporter' );

			if ( $t_mail_directory !== $t_mail_debug_directory )
			{
				plugin_config_set( 'mail_debug_directory', $t_mail_directory );
			}

			$t_mail_reporter_id = user_get_id_by_name( $t_mail_reporter );
			plugin_config_set( 'mail_reporter_id', $t_mail_reporter_id );

			plugin_config_delete( 'mail_directory' );
			plugin_config_delete( 'mail_reporter' );
			plugin_config_delete( 'mail_additional' );
			plugin_config_delete( 'random_user_number' );
			plugin_config_delete( 'mail_bug_priority_default' );

			plugin_config_set( 'config_version', 2 );
		}
	}

	/* 
	 * Prepare mantisbt variable for use while bug_report_mail is running
	 * This variable fixes the problem where when EmailReporting sends emails
	 * that the url in the emails is incorrect
	 */
	function ERP_check_mantisbt_url( )
	{
		if ( php_sapi_name() !== 'cli' )
		{
			$t_path						= config_get_global( 'path' );
			$t_mail_mantisbt_url_fix	= plugin_config_get( 'mail_mantisbt_url_fix', '' );

			if ( $t_path !== $t_mail_mantisbt_url_fix )
			{
				plugin_config_set( 'mail_mantisbt_url_fix', $t_path );
			}
		}
	}

	/* 
	 * Prepare mantisbt variable for use with including files
	 * This variable contains the full folder location of this plugin
	 */
	function ERP_check_mantisbt_erp_path( )
	{
		$t_basename = plugin_get_current();
		$t_path = config_get_global( 'plugin_path' ) . $t_basename . '/';

		// a plugin_config_set for globals does not exist. So we create it ourselves
		$t_full_option = 'plugin_' . $t_basename . '_path_erp';
		config_set_global( $t_full_option, $t_path );
	}

	/* 
	 * Check all mailboxes for new email if bug_report_mail can not or does not run in as a scheduled job
	 */
	function ERP_email_check_all( )
	{
		$t_mail_cronjob_present	= plugin_config_get( 'mail_cronjob_present' );
		$t_mail_nocron			= gpc_get_bool( 'mail_nocron', 0 );

		if ( php_sapi_name() !== 'cli' && $t_mail_cronjob_present == FALSE && $t_mail_nocron == FALSE )
		{
			$t_mail_check_timer	= plugin_config_get( 'mail_check_timer' );
			$t_mail_last_check	= plugin_config_get( 'mail_last_check', 0 );

			$t_time_now = time();

			if ( $t_mail_last_check < ( $t_time_now - $t_mail_check_timer ) )
			{
				plugin_config_set( 'mail_last_check', $t_time_now );

				$t_mail_debug			= plugin_config_get( 'mail_debug' );
				$t_mail_secured_script	= plugin_config_get( 'mail_secured_script' );

				if ( $t_mail_secured_script == TRUE )
				{
					plugin_config_set( 'mail_secured_script', OFF );
				}

				$t_address = config_get( 'path' ) . 'plugins/' . plugin_get_current() . '/scripts/bug_report_mail.php';

				$t_address_parsed = parse_url( $t_address );

				# file_get_contents crashed apache child process on windows using php 5.3.1 -> created work around
				# Apparently there is a problem when the hostname is not an ip in the setup above. Will not use the code because virtual host support will be broken using this method
//				$t_address = str_replace( $t_address_parsed[ 'scheme' ] . '://' . $t_address_parsed[ 'host' ], $t_address_parsed[ 'scheme' ] . '://' . gethostbyname( $t_address_parsed[ 'host' ] ), $t_address );
//				$t_dummy = file_get_contents( $t_address ); 

				if ( empty( $t_address_parsed[ 'port' ] ) )
				{
					$t_address_parsed[ 'port' ] = ( ( $t_address_parsed[ 'scheme' ] === 'https' ) ? 443 : 80 );
				}

				$t_socket = fsockopen( gethostbyname( $t_address_parsed[ 'host' ] ), $t_address_parsed[ 'port' ], $errno, $errstr, 30);
				if ( !$t_socket )
				{
					if ( $t_mail_debug )
					{
						echo "$errstr ($errno)<br />\n";
					}
				}
				else
				{
					$t_out = "GET " . $t_address_parsed[ 'path' ] . " HTTP/1.1\r\n";
					$t_out .= "Host: " . $t_address_parsed[ 'host' ] . "\r\n";
					$t_out .= "Connection: Close\r\n\r\n";

					fwrite( $t_socket, $t_out );

					while ( !feof( $t_socket ) )
					{
						$t_line = fgets( $t_socket, 128 );

						if ( $t_mail_debug )
						{
							echo $t_line;
						}
					}

					fclose( $t_socket );
				}

				if ( $t_mail_secured_script == TRUE )
				{
					plugin_config_set( 'mail_secured_script', ON );
				}

				if ( $t_mail_debug )
				{
					echo 'DEBUG mode: Are there any errors above?';
					exit;
				}
			}
		}
	}
}
