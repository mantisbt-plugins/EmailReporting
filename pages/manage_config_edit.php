<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

$f_gpc = array(
	'mail_add_bug_reports'			=> gpc_get_int( 'mail_add_bug_reports' ),
	'mail_add_bugnotes'				=> gpc_get_int( 'mail_add_bugnotes' ),
	'mail_add_complete_email'		=> gpc_get_int( 'mail_add_complete_email' ),
	'mail_add_users_from_cc_to'		=> gpc_get_int( 'mail_add_users_from_cc_to' ),
	'mail_auto_signup'				=> gpc_get_int( 'mail_auto_signup' ),
	'mail_block_attachments_md5'	=> array_map( 'strtolower', array_filter( array_map( 'trim', explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", gpc_get_string( 'mail_block_attachments_md5' ) ) ) ) ) ),
	'mail_block_attachments_logging'=> gpc_get_int( 'mail_block_attachments_logging' ),
	'mail_debug'					=> gpc_get_int( 'mail_debug' ),
	'mail_debug_directory'			=> ERP_prepare_directory_string( gpc_get_string( 'mail_debug_directory' ) ),
	'mail_debug_show_memory_usage'	=> gpc_get_int( 'mail_debug_show_memory_usage' ),
	'mail_delete'					=> gpc_get_int( 'mail_delete' ),
	'mail_disposable_email_checker'	=> gpc_get_int( 'mail_disposable_email_checker' ),
	'mail_email_receive_own'		=> gpc_get_int( 'mail_email_receive_own' ),
	'mail_fallback_mail_reporter'	=> gpc_get_int( 'mail_fallback_mail_reporter' ),
	'mail_max_email_body'			=> gpc_get_int( 'mail_max_email_body' ),
	'mail_max_email_body_text'		=> gpc_get_string( 'mail_max_email_body_text' ),
	'mail_max_email_body_add_attach'=> gpc_get_int( 'mail_max_email_body_add_attach' ),
	'mail_nodescription'			=> gpc_get_string( 'mail_nodescription' ),
	'mail_nosubject'				=> gpc_get_string( 'mail_nosubject' ),
	'mail_parse_html'				=> gpc_get_int( 'mail_parse_html' ),
	'mail_preferred_username'		=> gpc_get_string( 'mail_preferred_username' ),
	'mail_preferred_realname'		=> gpc_get_string( 'mail_preferred_realname' ),
	'mail_remove_mantis_email'		=> gpc_get_int( 'mail_remove_mantis_email' ),
	'mail_remove_replies'			=> gpc_get_int( 'mail_remove_replies' ),
	'mail_strip_gmail_style_replies'=> gpc_get_int( 'mail_strip_gmail_style_replies' ),
	'mail_remove_replies_after'		=> gpc_get_string( 'mail_remove_replies_after' ),
	'mail_removed_reply_text'		=> gpc_get_string( 'mail_removed_reply_text' ),
	'mail_reporter_id'				=> gpc_get_int( 'mail_reporter_id' ),
	'mail_rule_system'				=> gpc_get_int( 'mail_rule_system' ),
	'mail_save_from'				=> gpc_get_int( 'mail_save_from' ),
	'mail_save_subject_in_note'		=> gpc_get_int( 'mail_save_subject_in_note' ),
	'mail_secured_script'			=> gpc_get_int( 'mail_secured_script' ),
	'mail_secured_ipaddr'			=> gpc_get_string( 'mail_secured_ipaddr' ),
	'mail_strip_signature'			=> gpc_get_int( 'mail_strip_signature' ),
	'mail_strip_signature_delim'	=> gpc_get_string( 'mail_strip_signature_delim' ),
	'mail_subject_id_regex'			=> gpc_get_string( 'mail_subject_id_regex' ),
	'mail_use_bug_priority'			=> gpc_get_int( 'mail_use_bug_priority' ),
	'mail_use_message_id'			=> gpc_get_int( 'mail_use_message_id' ),
	'mail_use_reporter'				=> gpc_get_int( 'mail_use_reporter' ),
);

$f_mail_bug_priority				= 'array (' . "\n" . gpc_get_string( 'mail_bug_priority' ) . "\n" . ')';

foreach ( $f_gpc AS $t_key => $t_value )
{
	if( plugin_config_get( $t_key ) !== $t_value )
	{
		plugin_config_set( $t_key, $t_value );
	}
}

$t_mail_bug_priority = process_complex_value( $f_mail_bug_priority );
if( is_array( $t_mail_bug_priority ) )
{
	if ( plugin_config_get( 'mail_bug_priority' ) !== $t_mail_bug_priority )
	{
		plugin_config_set( 'mail_bug_priority', $t_mail_bug_priority );
	}
}
else
{
	ERP_page_begin( plugin_lang_get( 'plugin_title' ) );

	echo '<br /><div class="center">';
	echo plugin_lang_get( 'mail_bug_priority_array_failure' ) . ' ';
	print_bracket_link( plugin_page( 'manage_config', TRUE ), lang_get( 'proceed' ) );
	echo '</div>';

	$t_notsuccesfull = TRUE;

	ERP_page_end( __FILE__ );
}

if ( !isset( $t_notsuccesfull ) )
{
	print_successful_redirect( plugin_page( 'manage_config', TRUE ) );
}
