<?php
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );

class EmailReportingPlugin extends MantisPlugin {
	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */ 
	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page = 'config';

		$this->version = '0.7.4';
		$this->requires = array(
			'MantisCore' => '1.2',
		);

		$this->author = plugin_lang_get( 'author' );
		$this->contact = '';
		$this->url = 'http://www.mantisbt.org/bugs/view.php?id=4286';
	}

	/**
	 * EmailReporting plugin configuration.
	 */
	function config() {
		return array(
			'reset_schema' => 0,
			'schema' => -1,

			# --- mail reporting settings -----
			# Empty default mailboxes array. This array will be used for all the mailbox
			# accounts
			'mailboxes' => array(),
			
			# Do you want to secure the EmailReporting script so that it cannot be run
			# via a webserver?
			'mail_secured_script'		=> ON,

			# This tells Mantis to report all the Mail with only one account
			# ON = mail uses the reporter account in the setting below
			# OFF = it identifies the reporter using the email address of the sender
			'mail_use_reporter'			=> ON,
		
			# The account's name for mail reporting
			# Also used for fallback if a user is not found in database
			'mail_reporter'				=> 'Mail',
		
			# Signup new users automatically (possible security risk!)
			# Default is OFF, if mail_use_reporter is ON and this is off then it will
			# fallback on the mail_reporter account above
			'mail_auto_signup'			=> OFF,
		
			# How many mails should be fetched at the same time
			# If big mails with attachments should be received, specify only one
			'mail_fetch_max'			=> 1,
		
			# Add complete email into the attachments
			'mail_add_complete_email'	=> OFF,
		
			# Write sender of the message into the bug report
			'mail_save_from'			=> ON,
		
			# Parse MIME mails (may require a lot of memory)
			'mail_parse_mime'			=> OFF,
		
			# Parse HTML mails
			'mail_parse_html'			=> ON,

			# Try to identify only the reply parts in emails incase of notes
			'mail_identify_reply'		=> ON,
		
			# directory for saving temporary mail content
			'mail_tmp_directory'		=> '/tmp',
		
			# Delete incoming mail from POP3 server
			'mail_delete'				=> ON,
		
			# Used for debugging the system.
			# Use with care
			'mail_debug'				=> OFF,
		
			# Save mail contents to this directory if debug mode is ON
			'mail_directory'			=> '/tmp/mantis',
		
			# Looks for priority header field
			'mail_use_bug_priority' 	=> ON,
		
			# Default priority for mail reported bugs
			'mail_bug_priority_default'	=> NORMAL,

			# Use the following text when the subject is missing from the email
			'mail_nosubject' 			=> 'No subject found', 

			# Use the following text when the description is missing from the email
			'mail_nodescription' 		=> 'No description found', 
		
			# Classify bug priorities
			'mail_bug_priority' 		=> array(
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
				'normal' 		=> 30,
				'high' 			=> 40,
				'' 				=> 30,
				'?' 			=> 30
			),
		
			# Need to set the character encoding to which the email will be converted
			# This should be the same as the character encoding used in the database system used for mantis
			# values should be acceptable to the following function: http://www.php.net/mb_convert_encoding
			'mail_encoding' 			=> 'UTF-8', 

			# This decides whether this script will run as a cron / scheduled job or not
			'mail_cronjob_present' 		=> ON, 

			# This decides how long between checking the mailboxes for new emails when no cron / scheduled job is present
			'mail_check_timer' 			=> 300,
		);
	} 

	/**
	 * EmailReporting installation function.
	 */
	function install(){
		$t_random_user_number = plugin_config_get( 'random_user_number', 'NOT FOUND' );
		if ( $t_random_user_number === 'NOT FOUND' )
		{
			# We need to allow blank emails for a sec
			config_set_cache( 'allow_blank_email', ON, CONFIG_TYPE_STRING);

			$t_rand = MT_RAND( 4, 5 );

			$t_username = plugin_config_get( 'mail_reporter', 'Mail' ) . $t_rand;

			$t_email = '';

			$t_seed = $t_email . $t_username;

			# Create random password
			$t_password = auth_generate_random_password( $t_seed );

			# create the user
			$t_result_user_create = user_create( $t_username, $t_password, $t_email, REPORTER, false, true, 'Mail Reporter' );

			# Save these after the user has been created succesfully
			if ( $t_result_user_create )
			{
				plugin_config_set( 'random_user_number', $t_rand );
				plugin_config_set( 'mail_reporter', $t_username );
				plugin_config_set( 'reset_schema', 1 );
			}

			return( $t_result_user_create );
		}

		return( TRUE );
	}

	/**
	 * EmailReporting uninstallation function.
	 */
	function uninstall(){
		# User removal from the install function will not be done
		# The reason being thats its possibly connected to issues in the system
		return( TRUE );
	}

	/**
	 * EmailReporting initialation function.
	 */
	function init() {
		$t_path = config_get_global('plugin_path' ). plugin_get_current() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;

		set_include_path(get_include_path() . PATH_SEPARATOR . $t_path);
	} 

	/**
	 * EmailReporting plugin hooks.
	 */
	function hooks( ) {
		$hooks = array(
			'EVENT_MENU_MANAGE'			=> 'EmailReporting_maintain_mailbox_menu',
			'EVENT_CORE_READY'			=> 'EmailReporting_core_ready',
		);

		return $hooks;
	}

	/**
	 * EmailReporting plugin hooks - add mailbox settings menu item.
	 */
	function EmailReporting_maintain_mailbox_menu( ) {
		return array( '<a href="' . plugin_page( 'maintainmailbox' ) . '">' . plugin_lang_get( 'mailbox_settings' ) . '</a>', );
	}

	/* 
	 * This function will run when the mantis core is ready
	 */
	function EmailReporting_core_ready( )
	{
		$this->EmailReporting_reset_schema_check( );

		$this->EmailReporting_check_mantisbt_path( );

		$this->EmailReporting_email_check_all( );
	}

	/* 
	 * Since schema is not used anymore some corrections need to be applied
	 * Schema will be completely reset by this just once
	 */
	function EmailReporting_reset_schema_check( )
	{
		$t_reset_schema = plugin_config_get( 'reset_schema', 0 );

		if ( $t_reset_schema === 0 )
		{
			$t_username = plugin_config_get( 'mail_reporter' );
			$t_user_id = user_get_id_by_name( $t_username );
	
			if ( $t_user_id !== false )
			{
				$t_user_email = user_get_field( $t_user_id, 'email' );
			
				if ( $t_user_email === 'nomail' )
				{
					user_set_field( $t_user_id, 'email', '' );
				}
			}
	
			plugin_config_set( 'schema', -1 );
			plugin_config_set( 'reset_schema', 1 );
		}
	}

	/* 
	 * Prepare mantisbt variable for use while bug_report_mail is running
	 * This variable fixes the problem where when EmailReporting sends emails
	 * that the url in the emails is incorrect
	 */
	function EmailReporting_check_mantisbt_path( )
	{
		$t_mail_mantisbt_url_fix = plugin_config_get( 'mail_mantisbt_url_fix', '' );
		$t_path = config_get( 'path' );

		if ( php_sapi_name() != 'cli' && $t_path !== $t_mail_mantisbt_url_fix )
		{
			plugin_config_set( 'mail_mantisbt_url_fix', $t_path );
		}
	}

	/* 
	 * Check all mailboxes for new email if bug_report_mail can not or does not run in a cron / scheduled job
	 */
	function EmailReporting_email_check_all( )
	{
		$t_mail_cronjob_present = plugin_config_get( 'mail_cronjob_present' );
		$t_mail_nocron = gpc_get_bool( 'mail_nocron', 0 );

		if ( $t_mail_cronjob_present == false && $t_mail_nocron == false )
		{
			$t_mail_check_timer = plugin_config_get( 'mail_check_timer' );
			$t_mail_last_check = plugin_config_get( 'mail_last_check', 0 );

			$t_time_now = explode( ' ', microtime() );

			if ( $t_mail_last_check < ( $t_time_now[ 1 ] - $t_mail_check_timer ) )
			{
				plugin_config_set( 'mail_last_check', $t_time_now[ 1 ] );
				$t_mail_debug = plugin_config_get( 'mail_debug' );

				$t_mail_secured_script = plugin_config_get( 'mail_secured_script' );

				if ( $t_mail_secured_script == true )
				{
					plugin_config_set( 'mail_secured_script', OFF );
				}

				$t_address = config_get( 'path' ) . 'plugins/' . plugin_get_current() . '/scripts/bug_report_mail.php';

				$t_address_parsed = parse_url( $t_address );

				# file_get_contents crashed apache child process on windows using php 5.3.1 -> created work around
				# Apparently there is a problem when the hostname is not an ip in the setup above. Will not use the code because virtual host support will be broken using this method
//				$t_address = str_replace( $t_address_parsed[ 'scheme' ] . '://' . $t_address_parsed[ 'host' ], $t_address_parsed[ 'scheme' ] . '://' . gethostbyname( $t_address_parsed[ 'host' ] ), $t_address );
//				$t_dummy = file_get_contents( $t_address ); 

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

				if ( $t_mail_secured_script == true )
				{
					plugin_config_set( 'mail_secured_script', ON );
				}
			}
		}
	}
}
