<?php

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

		$this->version = '0.10.1';
		$this->requires = array(
			'MantisCore' => '1.3.0, <2.99.99',
		);

		$this->author = plugin_lang_get( 'plugin_author' );
		$this->contact = '';
		$this->url = 'http://www.mantisbt.org/wiki/doku.php/mantisbt:plugins:emailreporting';
	}

	/**
	 * EmailReporting plugin configuration.
	 */
	function config()
	{
		return array(
			'reset_schema'					=> 0,
			'config_version'				=> 0,
			'schema'						=> -1,
			'mantisbt_version'				=> (int) trim( MANTIS_VERSION )[ 0 ],
			'job_users'						=> array(),

			# --- mail reporting settings -----
			# Empty default mailboxes array. This array will be used for all the mailbox
			# accounts
			'mailboxes'						=> array(),

			# Empty default rules array. This array will be used for all the rules
			'rules'							=> array(),

			# Is this plugin allowed to process and create new bug reports
			'mail_add_bug_reports'			=> ON,

			# Is this plugin allowed to process and add notes to existing issues
			'mail_add_bugnotes'				=> ON,

			# Add complete email into the attachments
			'mail_add_complete_email'		=> OFF,

			// Add users from Cc and To field in mail header
			'mail_add_users_from_cc_to'		=> OFF,

			# Signup new users automatically (possible security risk!)
			# Default is OFF, if mail_use_reporter is OFF and this is OFF then it will
			# fallback to the mail_reporter account above
			'mail_auto_signup'				=> OFF,

			# List of md5 hashes of attachments which will be blocked.
			'mail_block_attachments_md5'	=> array(),

			# Log blocked attachments in the rejected files list
			'mail_block_attachments_logging'=> ON,

			# Classify bug priorities
			'mail_bug_priority'				=> array(
				'5 (lowest)'	=> '10',
				'4 (low)'		=> '20',
				'3 (normal)'	=> '30',
				'2 (high)'		=> '40',
				'1 (highest)'	=> '50',
				5		=> '20',
				4		=> '20',
				3		=> '30',
				2		=> '40',
				1		=> '50',
				0		=> '10',
				'low'			=> '20',
				'normal'		=> '30',
				'high'			=> '40',
				''		=> '30',
				'?'		=> '30',
			),

			# Used for debugging the system.
			# Use with care
			'mail_debug'					=> OFF,

			# Save mail contents to this directory if debug mode is ON
			'mail_debug_directory'			=> '/tmp/mantis',

			# Used for debugging the system.
			# Shows the memory usage in different stages of the debugging process
			'mail_debug_show_memory_usage'	=> OFF,

			# Delete incoming mail from POP3 server
			'mail_delete'					=> ON,

			# MantisBT always has the disposble email checker enabled. We needed an option to disable this in EmailReporting
			'mail_disposable_email_checker'	=> ON,

			# Should users always receive emails on actions they performed by email even though email_receive_own is OFF
			'mail_email_receive_own'		=> OFF,

			# Enable fallback to mail reporter
			'mail_fallback_mail_reporter'	=> ON,

			# Maximum size of the description/note. Restricion needed for database limitations
			# Older installations of MantisBT never had there description fields in MYSQL increased from TEXT to MEDIUMTEXT so TEXT is the default max
			'mail_max_email_body'			=> 65535,

			# Use the following text when part of the email has been truncated
			'mail_max_email_body_text'		=> '[EmailReporting -> Email body truncated]',

			# Add the complete description or note as an attachment when mail_max_email_body was triggered
			'mail_max_email_body_add_attach'=> OFF,

			# Use the following text when the description is missing from the email
			'mail_nodescription'			=> 'No description found',

			# Use the following text when the subject is missing from the email
			'mail_nosubject'				=> 'No subject found',

			# Parse HTML mails
			'mail_parse_html'				=> ON,

			# Preferred username for new user creations
			'mail_preferred_username'		=> 'name',

			# Preferred realname for new user creations
			'mail_preferred_realname'		=> 'name',

			# Try to identify the original mantis email and remove it from the description
			'mail_remove_mantis_email'		=> ON,

			# Remove everything after and including the remove_replies_after text
			'mail_remove_replies'			=> OFF,

			# Text which decides after (and including) which all content needs to be removed
			'mail_remove_replies_after'		=> '-----Original Message-----',

			# Use the following text when part of the email has been removed
			'mail_removed_reply_text'		=> '[EmailReporting -> Removed part identified as reply]',

			# The account's id for mail reporting
			# Also used for fallback if a user is not found in database
			# Mail is just the default name which will be converted to a user id during installation
			'mail_reporter_id'				=> 'Mail',

			# Is the rule system enabled
			'mail_rule_system'				=> OFF,

			# Write the sender of the email into the issue report/note
			'mail_save_from'				=> ON,

			# Write the subject of the email in the note
			'mail_save_subject_in_note'		=> OFF,

			# Do you want to secure the EmailReporting script so that it cannot be invoked
			# via a webserver?
			'mail_secured_script'			=> ON,

			# If you must invoke bug_report_mail though a webserver you can use this to restrict
			# access to this IP address
			'mail_secured_ipaddr'			=> '',

			//Strip Gmail style replies from body of the message
			'mail_strip_gmail_style_replies'=> OFF,

			#Removes the signature that are delimited by mail_strip_signature_delim
			'mail_strip_signature'			=> OFF,

			#Removes the signature that are delimited by --
			'mail_strip_signature_delim'	=> '--',

			# Which regex should be used for finding the issue id in the subject
			'mail_subject_id_regex'			=> 'strict',

			# Looks for priority header field
			'mail_use_bug_priority'			=> ON,

			# This tells Mantis to report all the Mail with only one account
			# ON = mail uses the reporter account in the setting below
			# OFF = it identifies the reporter using the email address of the sender
			'mail_use_reporter'				=> ON,

			// Whether to identify notes using Message-ID in the mail header
			'mail_use_message_id'			=> ON,
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

		if ( !@include_once( config_get_global( 'absolute_path' ) . 'api/soap/mc_file_api.php' ) )
		{
			# @todo returning false should trigger some error reporting, needs rethinking error_api
			error_parameters( plugin_lang_get( 'apisoap_error' ) );
			trigger_error( ERROR_PLUGIN_INSTALL_FAILED, ERROR ); 
			return( FALSE );
		};

		if ( $t_mail_reporter_id === 'Mail' )
		{
			// The plugin variable path_erp is not yet available. So path_erp cannot be used here
			plugin_require_api( 'core/config_api.php' );

			# We need to allow blank emails for a sec
			ERP_set_temporary_overwrite( 'allow_blank_email', ON );

			$t_rand = mt_rand( 1000, 99999 );

			$t_username = $t_mail_reporter_id . $t_rand;

			$t_email = '';

			$t_seed = $t_email . $t_username;

			# Create random password
			$t_password = auth_generate_random_password( $t_seed );

			# create the user
			$t_result_user_create = user_create( $t_username, $t_password, $t_email, config_get_global( 'report_bug_threshold' ), FALSE, TRUE, 'Mail Reporter', plugin_lang_get( 'plugin_title' ) );

			# Save these after the user has been created successfully
			if ( $t_result_user_create )
			{
				$t_user_id = user_get_id_by_name( $t_username );

				plugin_config_set( 'mail_reporter_id', $t_user_id );
			}

			// We need to set this here otherwise we mess up new installations with ERP_update_check
			plugin_config_set( 'reset_schema', 1 );

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

	/*
	 * Database table to store the Message-ID to detect the replies
	 * This is to implement #0016719
	 */
	function schema()
	{
		return array(
			array( 'CreateTableSQL', array( plugin_table( 'msgids' ), "
				id              I       UNSIGNED NOTNULL PRIMARY AUTOINCREMENT,
				issue_id        I       UNSIGNED NOTNULL,
				msg_id          C(255)  NOTNULL
				", Array('mysql' => 'DEFAULT CHARSET=utf8', 'pgsql' => 'WITHOUT OIDS')
				)
			),
			array( 'CreateIndexSQL', array( 'idx_erp_msgids_msgid', plugin_table( 'msgids' ), 'msg_id', array( 'UNIQUE' ) ) ),
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
			'EVENT_BUG_DELETED'	=> 'ERP_issue_deleted',
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
	 * Make sure that it is no problem if a user would delete the variable config_version
	 * as it would cause all the patches below to be executed all over again.
	 * New installations will have all of these patches executed as well.
	 */
	function ERP_update_check( )
	{
		$t_config_version = plugin_config_get( 'config_version' );

		if ( $t_config_version === 0 )
		{
			$t_username = plugin_config_get( 'mail_reporter', '' );

			if ( strlen( $t_username ) > 0 )
			{
				$t_user_id = user_get_id_by_name( $t_username );

				if ( $t_user_id !== FALSE )
				{
					$t_user_email = user_get_email( $t_user_id );

					if ( $t_user_email === 'nomail' )
					{
						plugin_require_api( 'core/config_api.php' );

						# We need to allow blank emails for a sec
						ERP_set_temporary_overwrite( 'allow_blank_email', ON );

						user_set_email( $t_user_id, '' );
					}
				}
			}

			$t_schema = plugin_config_get( 'schema' );
			$t_reset_schema = plugin_config_get( 'reset_schema' );
			if ( $t_schema !== -1 && $t_reset_schema === 0 )
			{
				plugin_config_set( 'schema', -1 );
				plugin_config_set( 'reset_schema', 1 );
			}

			plugin_config_set( 'config_version', 1 );
		}

		if ( $t_config_version <= 1 )
		{
			$t_mail_reporter		= plugin_config_get( 'mail_reporter', '' );

			if ( strlen( $t_mail_reporter ) > 0 )
			{
				$t_mail_reporter_id = user_get_id_by_name( $t_mail_reporter );
				plugin_config_set( 'mail_reporter_id', $t_mail_reporter_id );
			}

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
			$t_mailboxes = plugin_config_get( 'mailboxes', array() );
			$t_indexes = array(
				'mailbox_project' => 'mailbox_project_id',
				'mailbox_global_category' => 'mailbox_global_category_id',
			);

			foreach ( $t_mailboxes AS $t_key => $t_array )
			{
				if ( isset( $t_array[ 'mailbox_hostname' ] ) )
				{
					# Correct the hostname if it is stored in an older format
					$t_hostname = $t_array[ 'mailbox_hostname' ];

					if ( !is_array( $t_hostname ) )
					{
						// ipv6 also uses : so we need to work around that
						if ( substr_count( $t_hostname, ':' ) === 1 )
						{
							$t_hostname = explode( ':', $t_hostname, 2 );
						}
						else
						{
							$t_hostname = array( $t_hostname );
						}

						$t_hostname = array(
							'hostname'	=> $t_hostname[ 0 ],
							'port'		=> ( ( isset( $t_hostname[ 1 ] ) ) ? $t_hostname[ 1 ] : '' ),
						);

						$t_array[ 'mailbox_hostname' ] = $t_hostname;
					}
				}

				$t_mailboxes[ $t_key ] = $this->ERP_update_indexes( $t_array, $t_indexes );
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 4 );
		}

		if ( $t_config_version <= 4 )
		{
			$t_mail_remove_mantis_email	= plugin_config_get( 'mail_remove_mantis_email', -1 );
			$t_mail_identify_reply		= plugin_config_get( 'mail_identify_reply', $t_mail_remove_mantis_email );

			if ( $t_mail_remove_mantis_email !== -1 && $t_mail_identify_reply !== $t_mail_remove_mantis_email )
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
			$t_mailboxes = plugin_config_get( 'mailboxes', array() );
			$t_indexes = array(
				'mailbox_enabled' => 'enabled',
				'mailbox_description' => 'description',
				'mailbox_type' => 'type',
				'mailbox_hostname' => 'hostname',
				'mailbox_encryption' => 'encryption',
				'mailbox_username' => 'username',
				'mailbox_password' => 'password',
				'mailbox_auth_method' => 'auth_method',
				'mailbox_project_id' => 'project_id',
				'mailbox_global_category_id' => 'global_category_id',
				'mailbox_basefolder' => 'basefolder',
				'mailbox_createfolderstructure' => 'createfolderstructure',
			);

			foreach ( $t_mailboxes AS $t_key => $t_array )
			{
				$t_mailboxes[ $t_key ] = $this->ERP_update_indexes( $t_array, $t_indexes );
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 7 );
		}

		if ( $t_config_version <= 7 )
		{
			$t_mailboxes = plugin_config_get( 'mailboxes', array() );

			foreach ( $t_mailboxes AS $t_key => $t_array )
			{
				if ( isset( $t_array[ 'hostname' ] ) )
				{
					$t_hostname = $t_array[ 'hostname' ];

					if ( is_array( $t_hostname ) )
					{
						$t_array[ 'hostname' ] = $t_hostname[ 'hostname' ];
						$t_array[ 'port' ] = $t_hostname[ 'port' ];
					}

					$t_mailboxes[ $t_key ] = $t_array;
				}
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 8 );
		}

		if ( $t_config_version <= 8 )
		{
			plugin_config_delete( 'mail_tmp_directory' );

			plugin_config_set( 'config_version', 9 );
		}

		if ( $t_config_version <= 9 )
		{
			$t_mailboxes = plugin_config_get( 'mailboxes', array() );
			$t_indexes = array(
				'type' => 'mailbox_type',
				'basefolder' => 'imap_basefolder',
				'createfolderstructure' => 'imap_createfolderstructure',
			);

			foreach ( $t_mailboxes AS $t_key => $t_array )
			{
				$t_mailboxes[ $t_key ] = $this->ERP_update_indexes( $t_array, $t_indexes );
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_set( 'config_version', 10 );
		}

		if ( $t_config_version <= 10 )
		{
			plugin_config_delete( 'mail_rule_system' );

			plugin_config_set( 'config_version', 11 );
		}

		if ( $t_config_version <= 11 )
		{
			$t_mailboxes = plugin_config_get( 'mailboxes', array() );
			$t_indexes = array(
				'username' => 'erp_username',
				'password' => 'erp_password',
			);

			foreach ( $t_mailboxes AS $t_key => $t_array )
			{
				$t_mailboxes[ $t_key ] = $this->ERP_update_indexes( $t_array, $t_indexes );
			}

			plugin_config_set( 'mailboxes', $t_mailboxes );

			plugin_config_delete( 'rules' );
			plugin_config_delete( 'mail_encoding' );

			plugin_config_set( 'config_version', 12 );
		}

		if ( $t_config_version <= 12 )
		{
			plugin_config_set( 'reset_schema', 1 );

			plugin_config_set( 'config_version', 13 );
		}

		if ( $t_config_version <= 13 )
		{
			plugin_config_delete( 'mail_fetch_max' );

			plugin_config_set( 'config_version', 14 );
		}

		if ( $t_config_version <= 14 )
		{
			$t_mail_reporter_id = plugin_config_get( 'mail_reporter_id', 'Mail' );
			$t_report_bug_threshold = config_get_global( 'report_bug_threshold' );

			if ( $t_mail_reporter_id !== 'Mail' && user_exists( $t_mail_reporter_id ) )
			{
				if ( !access_has_global_level( $t_report_bug_threshold, $t_mail_reporter_id ) )
				{
					user_set_field( $t_mail_reporter_id, 'access_level', $t_report_bug_threshold );
				}
			}

			plugin_config_set( 'config_version', 15 );
		}

		if ( $t_config_version <= 15 )
		{
			plugin_require_api( 'core/mail_api.php' );
			ERP_mailbox_api::clean_references_for_deleted_issues();

			plugin_config_set( 'config_version', 16 );
		}
	}

	/*
	 * Modifies indexes in an array based on given array
	 */
	function ERP_update_indexes( $p_array, $p_indexes )
	{
		$t_array = $p_array;

		foreach ( $p_indexes AS $t_old_index => $t_new_index )
		{
			if ( isset( $t_array[ $t_old_index ] ) )
			{
				$t_array[ $t_new_index ] = $t_array[ $t_old_index ];
				unset( $t_array[ $t_old_index ] );
			}
		}

		return( $t_array );
	}

	/*
	 * Prepare mantisbt variable for use while bug_report_mail is running
	 * This variable fixes the problem where when EmailReporting sends emails
	 * that the url in the emails is incorrect
	 */
	function ERP_check_mantisbt_url( )
	{
		if ( php_sapi_name() !== 'cli' && !isset( $GLOBALS[ 't_dir_emailreporting_adjust' ] ) )
		{
			$t_path						= config_get_global( 'path' );
			$t_mail_mantisbt_url_fix	= plugin_config_get( 'mail_mantisbt_url_fix', '' );
			$t_absolute_path			= realpath( config_get_global( 'absolute_path' ) );
			$t_dir_script_filename		= realpath( str_replace( array( '\\', '/'), DIRECTORY_SEPARATOR, dirname( $_SERVER['SCRIPT_FILENAME'] ) . DIRECTORY_SEPARATOR ) );

			if ( strncasecmp( $t_path, 'http', 4 ) === 0 &&
				$t_path !== $t_mail_mantisbt_url_fix &&
				$t_absolute_path === $t_dir_script_filename )
			{
				plugin_config_set( 'mail_mantisbt_url_fix', $t_path );
			}
		}
	}

	/*
	 * Clean up the stored data when an issue is deleted
	 */
	function ERP_issue_deleted( $p_event, $p_bug_id )
	{
		plugin_require_api( 'core/mail_api.php' );
		ERP_mailbox_api::delete_references_for_bug_id( $p_bug_id );
	}

}
