<?php
require_once( config_get( 'class_path' ) . 'MantisPlugin.class.php' );

class EmailReportingPlugin extends MantisPlugin
{
	/**
	 *  A method that populates the plugin information and minimum requirements.
	 */ 
	function register()
	{
		$this->name = plugin_lang_get( 'plugin_title' );
		$this->description = plugin_lang_get( 'plugin_description' );
		$this->page = 'manage_config';

		$this->version = '0.8.4';
		$this->requires = array(
			'MantisCore' => '1.2',
		);

		$this->author = plugin_lang_get( 'plugin_author' );
		$this->contact = '';
		$this->url = 'http://git.mantisforge.org/w/EmailReporting.git';
	}

	/**
	 * EmailReporting plugin configuration.
	 */
	function config()
	{
		// This function does not use ERP_prepare_directory_string
		// It would require including config_api.php within this file
		// I decided against that since it would make the functions available at all times and it is not necessary most of the time
		$t_upload_tmp_dir = realpath( trim( str_replace( '\\', '/', ini_get( 'upload_tmp_dir' ) ) ) );   

		return array(
			'config_version'				=> 0,
			'schema'						=> -1,
			'path_erp'						=> config_get_global( 'plugin_path' ) . plugin_get_current() . '/',

			# --- mail reporting settings -----
			# Empty default mailboxes array. This array will be used for all the mailbox
			# accounts
			'mailboxes'						=> array(),

			# Is this plugin allowed to process and create new bug reports
			'mail_add_bug_reports'			=> ON,

			# Is this plugin allowed to process and add bugnotes to existing issues
			'mail_add_bugnotes'				=> ON,

			# Add complete email into the attachments
			'mail_add_complete_email'		=> OFF,

			# Signup new users automatically (possible security risk!)
			# Default is OFF, if mail_use_reporter is OFF and this is OFF then it will
			# fallback to the mail_reporter account above
			'mail_auto_signup'				=> OFF,

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

			# Used for debugging the system.
			# Use with care
			'mail_debug'					=> OFF,

			# Save mail contents to this directory if debug mode is ON
			'mail_debug_directory'			=> '/tmp/mantis',

			# Delete incoming mail from POP3 server
			'mail_delete'					=> ON,

			# Should users allways receive emails on actions they performed by email even though email_receive_own is OFF
			'mail_email_receive_own'		=> OFF,

			# Need to set the character encoding to which the email will be converted
			# This should be the same as the character encoding used in the database system used for mantis
			# values should be acceptable to the following function: http://www.php.net/mb_convert_encoding
			'mail_encoding'					=> 'UTF-8', 

			# Enable fallback to mail reporter
			'mail_fallback_mail_reporter'	=> ON,

			# How many mails should be fetched at the same time
			# If big mails with attachments should be received, specify only one
			'mail_fetch_max'				=> 1,

			# Use the following text when the description is missing from the email
			'mail_nodescription'			=> 'No description found', 

			# Use the following text when the subject is missing from the email
			'mail_nosubject'				=> 'No subject found', 

			# Parse HTML mails
			'mail_parse_html'				=> ON,

			# Preferred username for new username creations
			'mail_preferred_username'		=> 'name',

			# Try to identify the original mantis email and remove it from the description
			'mail_remove_mantis_email'		=> ON,

			# Remove everything after and including the remove_replies_after text
			'mail_remove_replies'			=> OFF,

			# Text which decides after (and including) which all content needs to be removed
			'mail_remove_replies_after'		=> '-----Original Message-----',

			# Use the following text when part of the email has been removed
			'mail_removed_reply_text'		=> '[EmailReporting -> Removed part identified as reply]',

			# The account's name for mail reporting
			# Also used for fallback if a user is not found in database
			# Mail is just the default name which will be converted to a user id during installation
			'mail_reporter_id'				=> 'Mail',

			# Write sender of the message into the bug report
			'mail_save_from'				=> ON,

			# Do you want to secure the EmailReporting script so that it cannot be run
			# via a webserver?
			'mail_secured_script'			=> ON,

			# directory for saving temporary mail content
			'mail_tmp_directory'			=> ( ( $t_upload_tmp_dir === FALSE ) ? '/tmp' : str_replace( '\\', '/', $t_upload_tmp_dir ) ),

			# Looks for priority header field
			'mail_use_bug_priority'			=> ON,

			# This tells Mantis to report all the Mail with only one account
			# ON = mail uses the reporter account in the setting below
			# OFF = it identifies the reporter using the email address of the sender
			'mail_use_reporter'				=> ON,
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

			$t_rand = mt_rand( 1000, 99999 );

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

				plugin_config_set( 'config_version', 8 );
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
		$t_path = config_get_global( 'plugin_path' ) . plugin_get_current() . DIRECTORY_SEPARATOR . 'core_pear' . DIRECTORY_SEPARATOR;

		if ( is_dir( $t_path ) )
		{
			set_include_path( get_include_path() . PATH_SEPARATOR . $t_path );
		}
	}

	function events()
	{
		return array(
			'EVENT_ERP_OUTPUT_MAILBOX_FIELDS' => EVENT_TYPE_OUTPUT,

			// Keep in mind that this event is called BEFORE its MantisBT core counterpart
			// After the MantisBT core event is not possible since that one is integrated into the bugnote_add function
			'EVENT_ERP_BUGNOTE_DATA' => EVENT_TYPE_CHAIN,

			// Keep in mind that this event is called AFTER its MantisBT core counterpart
			'EVENT_ERP_REPORT_BUG_DATA' => EVENT_TYPE_CHAIN,
		);
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
		return array( '<a href="' . plugin_page( 'manage_mailbox' ) . '">' . plugin_lang_get( 'manage' ) . ' ' . plugin_lang_get( 'plugin_title' ) . '</a>', );
	}

	/* 
	 * This function will run when the mantis core is ready
	 */
	function ERP_core_ready( )
	{
		$this->ERP_update_check( );

		$this->ERP_check_mantisbt_url( );
	}

	/* 
	 * Since schema is not used anymore some corrections need to be applied
	 * Schema will be completely reset by this just once
	 * 
	 * The second part updates various configuration options and performs some cleaning
	 * Further updates to the configuration options follow below
	 *
	 * Make sure that if you change anything to the config_version value that you
	 * also change the update the value in install
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

		if ( $t_config_version <= 2 )
		{
			plugin_config_delete( 'mail_cronjob_present' );
			plugin_config_delete( 'mail_check_timer' );
			plugin_config_delete( 'mail_last_check' );

			plugin_config_set( 'config_version', 3 );
		}

		if ( $t_config_version <= 3 )
		{
			$t_mailboxes = plugin_config_get( 'mailboxes' );

			foreach ( $t_mailboxes AS $t_key => $t_value )
			{
				# Correct the hostname if it is stored in an older format
				$t_hostname = $t_value[ 'mailbox_hostname' ];

				if ( !is_array( $t_hostname ) )
				{
					$t_hostname = explode( ':', $t_hostname, 2 );

					$t_hostname = array(
						'hostname'	=> $t_hostname[ 0 ],
						'port'		=> ( ( isset( $t_hostname[ 1 ] ) ) ? $t_hostname[ 1 ] : '' ),
					);

					$t_value[ 'mailbox_hostname' ] = $t_hostname;
				}

				# Correct index mailbox_project --> mailbox_project_id
				if ( isset( $t_value[ 'mailbox_project' ] ) )
				{
					$t_value[ 'mailbox_project_id' ] = $t_value[ 'mailbox_project' ];
					unset( $t_value[ 'mailbox_project' ] );
				}

				# Correct index mailbox_global_category --> mailbox_global_category_id
				if ( isset( $t_value[ 'mailbox_global_category' ] ) )
				{
					$t_value[ 'mailbox_global_category_id' ] = $t_value[ 'mailbox_global_category' ];
					unset( $t_value[ 'mailbox_global_category' ] );
				}

				$t_mailboxes[ $t_key ] = $t_value;
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 4 );
		}

		if ( $t_config_version <= 4 )
		{
			$t_mail_remove_mantis_email	= plugin_config_get( 'mail_remove_mantis_email' );
			$t_mail_identify_reply		= plugin_config_get( 'mail_identify_reply', $t_mail_remove_mantis_email );

			if ( $t_mail_identify_reply !== $t_mail_remove_mantis_email )
			{
				plugin_config_set( 'mail_remove_mantis_email', $t_mail_identify_reply );
			}

			plugin_config_delete( 'mail_identify_reply' );

			plugin_config_set( 'config_version', 5 );
		}

		if ( $t_config_version <= 5 )
		{
			plugin_config_delete( 'mail_parse_mime' );

			plugin_config_set( 'config_version', 6 );
		}

		if ( $t_config_version <= 6 )
		{
			$t_mailboxes = plugin_config_get( 'mailboxes' );

			foreach ( $t_mailboxes AS $t_key => $t_array )
			{
				foreach ( $t_array AS $t_index => $t_value )
				{
					if ( substr( $t_index, 0, 8 ) === 'mailbox_' )
					{
						$t_new_index = substr( $t_index, 8 );
						$t_array[ $t_new_index ] = $t_value;
						unset( $t_array[ $t_index ] );
					}
				}

				$t_mailboxes[ $t_key ] = $t_array;
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 7 );
		}

		if ( $t_config_version <= 7 )
		{
			$t_mailboxes = plugin_config_get( 'mailboxes' );

			foreach ( $t_mailboxes AS $t_key => $t_value )
			{
				$t_hostname = $t_value[ 'hostname' ];

				if ( is_array( $t_hostname ) )
				{
					$t_value[ 'hostname' ] = $t_hostname[ 'hostname' ];
					$t_value[ 'port' ] = $t_hostname[ 'port' ];
				}

				$t_mailboxes[ $t_key ] = $t_value;
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 8 );
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
}
