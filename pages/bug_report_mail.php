<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# This page receives an E-Mail via POP3 and IMAP and generates a new issue

	$GLOBALS[ 'g_bypass_headers' ] = 1;

	# Make sure this script doesn't run via the webserver
	$t_mail_secured_script = plugin_config_get( 'mail_secured_script' );
	if( php_sapi_name() !== 'cli' && $t_mail_secured_script )
	{
		echo 'bug_report_mail.php is not allowed to be invoked through a webserver.' . "\n";
		exit( 1 );
	}

	$t_remote_addr = trim( ( ( isset( $_SERVER[ 'REMOTE_ADDR' ] ) ) ? $_SERVER['REMOTE_ADDR'] : NULL ) );
	$t_mail_secured_ipaddr = trim( plugin_config_get( 'mail_secured_ipaddr' ) );
	if ( !is_blank( $t_mail_secured_ipaddr ) && !is_blank( $t_remote_addr ) )
	{
		if ( $t_remote_addr !== $t_mail_secured_ipaddr )
		{
			echo 'bug_report_mail.php is not allowed to be invoked from: ' . $t_remote_addr . "\n";
			exit( 1 );
		}
		else
		{
			echo 'bug_report_mail.php invoked from: ' . $t_remote_addr . "\n";
		}
	}

	ini_set( 'memory_limit', -1 );
	if ( ini_get( 'safe_mode' ) == 0 )
	{
		set_time_limit( 0 );
	}

	if ( php_sapi_name() !== 'cli' )
	{
		echo '<pre>';
	}

	plugin_require_api( 'core/mail_api.php' );
	plugin_require_api( 'core/config_api.php' );

	$GLOBALS[ 't_mailboxes' ] = plugin_config_get( 'mailboxes' );

	$t_mail_mantisbt_url_fix = plugin_config_get( 'mail_mantisbt_url_fix', '' );
	if ( isset( $GLOBALS[ 't_dir_emailreporting_adjust' ] ) && !is_blank( $t_mail_mantisbt_url_fix ) )
	{
		ERP_set_temporary_overwrite( 'path', $t_mail_mantisbt_url_fix );
	}

	// Register the user that is currently running this script
	$t_job_users = plugin_config_get( 'job_users' );
	$t_username = ERP_get_current_os_user();
	if ( !isset( $t_job_users[ php_sapi_name() ] ) || $t_job_users[ php_sapi_name() ] !== $t_username )
	{
		$t_job_users[ php_sapi_name() ] = (string) $t_username;
		plugin_config_set( 'job_users', $t_job_users );
	}

	echo 'Start checking all mailboxes: ' . date('l jS \of F Y H:i:s') . "\n\n";

	$t_mailbox_api_index = ERP_get_mailbox_api_name();

	$GLOBALS[ $t_mailbox_api_index ] = new ERP_mailbox_api;

	foreach ( $GLOBALS[ 't_mailboxes' ] as $t_mailbox )
	{
		$GLOBALS[ $t_mailbox_api_index ]->process_mailbox( $t_mailbox );
	}

	echo "\n\n" . 'Done checking all mailboxes' . "\n";

	if ( php_sapi_name() !== 'cli' )
	{
		echo '</pre>';
	}

	exit( 0 );
?>

