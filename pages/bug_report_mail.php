<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id: bug_report_mail.php,v 1.6 2009/12/13 05:37:52 SL-Server\SC Kruiper Exp $
	# --------------------------------------------------------

	# This page receives an E-Mail via POP3 and IMAP and generates a new issue

	global $g_bypass_headers;
	$g_bypass_headers = 1;

	require_once( ( ( isset( $GLOBALS[ 't_dir_emailreporting_adjust' ] ) ) ? $GLOBALS[ 't_dir_emailreporting_adjust' ] : '' ) . 'core.php' );

	# Make sure this script doesn't run via the webserver
	if( php_sapi_name() != 'cli' && plugin_config_get( 'mail_secured_script', ON ) ) {
		echo "bug_report_mail.php is not allowed to run through the webserver.\n";
		exit( 1 );
	}

	$t_mail_tmp_directory = plugin_config_get( 'mail_tmp_directory' );
	if ( is_dir( $t_mail_tmp_directory ) && is_writeable( $t_mail_tmp_directory ) )
	{
		require_once( 'mail_api.php' );

		$t_mailboxes = mail_get_mailboxes();

		$t_mail_mantisbt_path = plugin_config_get( 'mail_mantisbt_url', '' );
		if ( php_sapi_name() == 'cli' && !empty( $t_mail_mantisbt_path ) )
		{
			config_set_cache( 'path', $t_mail_mantisbt_path, CONFIG_TYPE_STRING);
		}

		foreach ($t_mailboxes as $t_mailbox)
		{
			if ( plugin_config_get( 'mail_debug' ) )
			{
				var_dump( $t_mailbox );
			}

			mail_process_all_mails( $t_mailbox );
		}

		echo "\n\n" . 'Done checking all mailboxes';
	}
	else
	{
		echo 'The temporary mail directory is not writable. Please correct it in the configuration';
	}

	exit( 0 );
?>

