<?php
	# Mantis - a php based bugtracking system
	# Copyright (C) 2002 - 2004  Mantis Team   - mantisbt-dev@lists.sourceforge.net
	# Copyright (C) 2004  Gerrit Beine - gerrit.beine@pitcom.de
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------------------------------------------
	# $Id: bug_report_mail.php,v 1.16 2010/04/07 23:53:44 SL-Server\SC Kruiper Exp $
	# --------------------------------------------------------

	# This page receives an E-Mail via POP3 and IMAP and generates a new issue

	$GLOBALS[ 'g_bypass_headers' ] = 1;

	# Make sure this script doesn't run via the webserver
	$t_mail_secured_script = plugin_config_get( 'mail_secured_script' );
	if( php_sapi_name() !== 'cli' && $t_mail_secured_script )
	{
		echo "bug_report_mail.php is not allowed to run through the webserver.\n";
		exit( 1 );
	}

	$t_mail_tmp_directory = plugin_config_get( 'mail_tmp_directory' );
	if ( is_dir( $t_mail_tmp_directory ) && is_writeable( $t_mail_tmp_directory ) )
	{
		require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/mail_api.php' );

		$t_mail_debug = plugin_config_get( 'mail_debug' );
		$t_mailboxes  = plugin_config_get( 'mailboxes' );

		$t_mail_mantisbt_url_fix = plugin_config_get( 'mail_mantisbt_url_fix', '' );
		if ( php_sapi_name() === 'cli' && !empty( $t_mail_mantisbt_url_fix ) )
		{
			config_set_global( 'path', $t_mail_mantisbt_url_fix );
		}

		foreach ( $t_mailboxes as $t_mailbox )
		{
			if ( $t_mail_debug )
			{
				var_dump( $t_mailbox );
			}

			ERP_process_all_mails( $t_mailbox );
		}

		echo "\n\n" . 'Done checking all mailboxes';
	}
	else
	{
		echo 'The temporary mail directory is not writable. Please correct it in the configuration options';
	}

	exit( 0 );
?>

