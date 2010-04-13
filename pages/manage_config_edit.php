<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );


$f_mail_secured_script       = gpc_get_bool( 'mail_secured_script' );
$f_mail_cronjob_present      = gpc_get_bool( 'mail_cronjob_present' );
$f_mail_check_timer          = gpc_get_int( 'mail_check_timer' );
$f_mail_use_reporter         = gpc_get_bool( 'mail_use_reporter' );
$f_mail_reporter_id          = gpc_get_int( 'mail_reporter_id' );
$f_mail_auto_signup          = gpc_get_bool( 'mail_auto_signup' );
$f_mail_fetch_max            = gpc_get_int( 'mail_fetch_max' );
$f_mail_add_complete_email   = gpc_get_bool( 'mail_add_complete_email' );
$f_mail_save_from            = gpc_get_bool( 'mail_save_from' );
$f_mail_parse_mime           = gpc_get_bool( 'mail_parse_mime' );
$f_mail_parse_html           = gpc_get_bool( 'mail_parse_html' );
$f_mail_identify_reply       = gpc_get_bool( 'mail_identify_reply' );
$f_mail_tmp_directory        = trim( str_replace( '\\', '/', gpc_get_string( 'mail_tmp_directory' ) ), '/ ' );
$f_mail_delete               = gpc_get_bool( 'mail_delete' );
$f_mail_debug                = gpc_get_bool( 'mail_debug' );
$f_mail_debug_directory      = trim( str_replace( '\\', '/', gpc_get_string( 'mail_debug_directory' ) ), '/ ' );
$f_mail_nosubject            = gpc_get_string( 'mail_nosubject' );
$f_mail_nodescription        = gpc_get_string( 'mail_nodescription' );
$f_mail_removed_reply_text   = gpc_get_string( 'mail_removed_reply_text' );
$f_mail_use_bug_priority     = gpc_get_bool( 'mail_use_bug_priority' );
$f_mail_bug_priority         = gpc_get_string( 'mail_bug_priority' );
$f_mail_encoding             = gpc_get_string( 'mail_encoding' );


if( plugin_config_get( 'mail_secured_script' ) != $f_mail_secured_script ) {
	plugin_config_set( 'mail_secured_script', $f_mail_secured_script );
}

if( plugin_config_get( 'mail_cronjob_present' ) != $f_mail_cronjob_present ) {
	plugin_config_set( 'mail_cronjob_present', $f_mail_cronjob_present );
}

if( plugin_config_get( 'mail_check_timer' ) != $f_mail_check_timer ) {
	plugin_config_set( 'mail_check_timer', $f_mail_check_timer );
}

if( plugin_config_get( 'mail_use_reporter' ) != $f_mail_use_reporter ) {
	plugin_config_set( 'mail_use_reporter', $f_mail_use_reporter );
}

if( plugin_config_get( 'mail_reporter_id' ) != $f_mail_reporter_id ) {
	plugin_config_set( 'mail_reporter_id', $f_mail_reporter_id );
}

if( plugin_config_get( 'mail_auto_signup' ) != $f_mail_auto_signup ) {
	plugin_config_set( 'mail_auto_signup', $f_mail_auto_signup );
}

if( plugin_config_get( 'mail_fetch_max' ) != $f_mail_fetch_max ) {
	plugin_config_set( 'mail_fetch_max', $f_mail_fetch_max );
}

if( plugin_config_get( 'mail_add_complete_email' ) != $f_mail_add_complete_email ) {
	plugin_config_set( 'mail_add_complete_email', $f_mail_add_complete_email );
}

if( plugin_config_get( 'mail_save_from' ) != $f_mail_save_from ) {
	plugin_config_set( 'mail_save_from', $f_mail_save_from );
}

if( plugin_config_get( 'mail_parse_mime' ) != $f_mail_parse_mime ) {
	plugin_config_set( 'mail_parse_mime', $f_mail_parse_mime );
}

if( plugin_config_get( 'mail_parse_html' ) != $f_mail_parse_html ) {
	plugin_config_set( 'mail_parse_html', $f_mail_parse_html );
}

if( plugin_config_get( 'mail_identify_reply' ) != $f_mail_identify_reply ) {
	plugin_config_set( 'mail_identify_reply', $f_mail_identify_reply );
}

if( plugin_config_get( 'mail_tmp_directory' ) != $f_mail_tmp_directory ) {
	plugin_config_set( 'mail_tmp_directory', $f_mail_tmp_directory );
}

if( plugin_config_get( 'mail_delete' ) != $f_mail_delete ) {
	plugin_config_set( 'mail_delete', $f_mail_delete );
}

if( plugin_config_get( 'mail_debug' ) != $f_mail_debug ) {
	plugin_config_set( 'mail_debug', $f_mail_debug );
}

if( plugin_config_get( 'mail_debug_directory' ) != $f_mail_debug_directory ) {
	plugin_config_set( 'mail_debug_directory', $f_mail_debug_directory );
}

if( plugin_config_get( 'mail_nosubject' ) != $f_mail_nosubject ) {
	plugin_config_set( 'mail_nosubject', $f_mail_nosubject );
}

if( plugin_config_get( 'mail_nodescription' ) != $f_mail_nodescription ) {
	plugin_config_set( 'mail_nodescription', $f_mail_nodescription );
}

if( plugin_config_get( 'mail_removed_reply_text' ) != $f_mail_removed_reply_text ) {
	plugin_config_set( 'mail_removed_reply_text', $f_mail_removed_reply_text );
}

if( plugin_config_get( 'mail_use_bug_priority' ) != $f_mail_use_bug_priority ) {
	plugin_config_set( 'mail_use_bug_priority', $f_mail_use_bug_priority );
}

$t_mail_bug_priority = @eval( 'return ' . $f_mail_bug_priority . ';' );
if( is_array( $t_mail_bug_priority ) )
{
	if ( plugin_config_get( 'mail_bug_priority' ) != $t_mail_bug_priority )	{
		plugin_config_set( 'mail_bug_priority', $t_mail_bug_priority );
	}
}
else
{
	html_page_top( plugin_lang_get( 'title' ) );

	echo '<br /><div class="center">';
	echo plugin_lang_get( 'mail_bug_priority_array_failure' ) . ' ';
	print_bracket_link( plugin_page( 'manage_config', TRUE ), lang_get( 'proceed' ) );
	echo '</div>';

	$t_notsuccesfull = TRUE;

	html_page_bottom( __FILE__ );
}

if( plugin_config_get( 'mail_encoding' ) != $f_mail_encoding ) {
	plugin_config_set( 'mail_encoding', $f_mail_encoding );
}

if ( !isset( $t_notsuccesfull ) )
{
	print_successful_redirect( plugin_page( 'manage_config', TRUE ) );
}
