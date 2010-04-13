<?php
$_GET[ 'mail_nocron' ] = true;

$t_dir_emailreporting_adjust = '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
require_once( $t_dir_emailreporting_adjust . 'core.php' );

$t_tmp_plugin_page = plugin_page( 'bug_report_mail', true, 'EmailReporting' );
$t_tmp_plugin_page = explode( '?', $t_tmp_plugin_page, 2 );
$t_tmp_plugin_page[ 1 ] = explode( '=', $t_tmp_plugin_page[ 1 ], 2 );

$_GET[ $t_tmp_plugin_page[ 1 ][ 0 ] ] = $t_tmp_plugin_page[ 1 ][ 1 ];
require_once( $t_dir_emailreporting_adjust . $t_tmp_plugin_page[ 0 ] );
?>
