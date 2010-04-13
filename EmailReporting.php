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

        $this->version = '0.5';
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
			'schema' => -1,
			'mailboxes' => array(),
			
			# --- mail reporting settings -----
			# Do you want to secure the EmailReporting script so that it cannot be run
			# via a webserver?
			'mail_secured_script'			=> ON,

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
			'mail_add_complete_email'			=> OFF,
		
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
		
			# The auth method used for POP3
			# Valid methods are: 'DIGEST-MD5','CRAM-MD5','LOGIN','PLAIN','APOP','USER'
			'mail_auth_method'			=> 'USER',
		
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
		);
	} 

	/**
	 * EmailReporting installation function.
	 */
	function install(){
		return( TRUE );
	}

	/**
	 * EmailReporting uninstallation function.
	 */
	function uninstall(){
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
	 * EmailReporting plugin schema.
	 */
	function schema() {
		$mtime = explode( ' ', microtime() );
		if ( plugin_config_get( 'random_user_number', 'NOT FOUND' ) === 'NOT FOUND' )
		{
			plugin_config_set( 'random_user_number', RAND() );
			plugin_config_set( 'mail_reporter', plugin_config_get( 'mail_reporter', 'Mail' ) . plugin_config_get( 'random_user_number' ) );
		}

		return array(
			array(
				'InsertData',
				array(
					db_get_table( 'mantis_user_table' ),
					' (username, realname, email, password, date_created, last_visit, enabled, protected, access_level, login_count, lost_password_request_count, failed_login_count, cookie_string) VALUES (\'' . plugin_config_get( 'mail_reporter', 'Mail' ) . '\', \'Mail Reporter\', \'nomail\', \'' . MD5( MD5( RAND() ) . MD5( microtime() ) ) . '\', \'' . $mtime[1] . '\', \'' . $mtime[1] . '\', 1, 0, ' . REPORTER . ', 0, 0, 0, \'' . MD5( RAND() ) . MD5( microtime() ) . '\')',
				)
			),
		);
	}

	/**
	 * EmailReporting plugin hooks.
	 */
	function hooks( ) {
		$hooks = array(
			'EVENT_MENU_MANAGE'			=> 'EmailReporting_maintain_mailbox_menu',
			'EVENT_NOTIFY_USER_EXCLUDE'	=> 'EmailReporting_exclude_users_from_email',
		);
		return $hooks;
	}

	/**
	 * EmailReporting plugin hooks - add mailbox settings menu item.
	 */
	function EmailReporting_maintain_mailbox_menu( ) {
		return array( '<a href="' . plugin_page( 'maintainmailbox' ) . '">' . plugin_lang_get( 'mailbox_settings' ) . '</a>', );
	}

	/**
	 * EmailReporting plugin hooks - exclude mail reporter from emails as long as its still using its default email address.
	 */
	function EmailReporting_exclude_users_from_email( $event_type, $p_bug_id, $p_notify_type, $p_user_id ) {
		$t_mail_reporter = plugin_config_get( 'mail_reporter' );
		$t_mail_reporter_id = (int) user_get_id_by_name( $t_mail_reporter );
		$t_reporter_email = user_get_field( $t_mail_reporter_id, 'email' );

		return ( ( $t_mail_reporter_id === $p_user_id && $t_reporter_email === 'nomail' ) ? true : false );
	}
}
