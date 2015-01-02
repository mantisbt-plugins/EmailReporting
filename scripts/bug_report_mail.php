<?php
$GLOBALS[ 't_dir_emailreporting_adjust' ] = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
require_once( $GLOBALS[ 't_dir_emailreporting_adjust' ] . 'core.php' );

$t_basename = 'EmailReporting';
$t_pagename = 'bug_report_mail';

if( plugin_needs_upgrade( $g_plugin_cache[ $t_basename ] ) )
{
	error_parameters( $t_basename );
	echo error_string( ERROR_PLUGIN_UPGRADE_NEEDED );
	exit;
}

// This would work but skips some important checks done in plugin.php.
// For the moment this code will be disabled
/*
$t_pagename = 'pages/bug_report_mail.php';
plugin_push_current( $t_basename );
plugin_require_api( $t_pagename );
*/

$t_tmp_plugin_page = plugin_page( $t_pagename, TRUE, $t_basename );
$t_tmp_plugin_page = explode( '?', $t_tmp_plugin_page, 2 );
$t_tmp_plugin_page[ 1 ] = explode( '=', $t_tmp_plugin_page[ 1 ], 2 );

$_GET[ $t_tmp_plugin_page[ 1 ][ 0 ] ] = $t_tmp_plugin_page[ 1 ][ 1 ];
require_once( $GLOBALS[ 't_dir_emailreporting_adjust' ] . $t_tmp_plugin_page[ 0 ] );
?>
