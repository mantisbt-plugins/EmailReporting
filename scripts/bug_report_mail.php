<?php
$GLOBALS[ 't_dir_emailreporting_adjust' ] = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
require_once( $GLOBALS[ 't_dir_emailreporting_adjust' ] . 'core.php' );

$t_tmp_plugin_page = plugin_page( 'bug_report_mail', TRUE, 'EmailReporting' );
$t_tmp_plugin_page = explode( '?', $t_tmp_plugin_page, 2 );
$t_tmp_plugin_page[ 1 ] = explode( '=', $t_tmp_plugin_page[ 1 ], 2 );

$_GET[ $t_tmp_plugin_page[ 1 ][ 0 ] ] = $t_tmp_plugin_page[ 1 ][ 1 ];
require_once( $GLOBALS[ 't_dir_emailreporting_adjust' ] . $t_tmp_plugin_page[ 0 ] );
?>
