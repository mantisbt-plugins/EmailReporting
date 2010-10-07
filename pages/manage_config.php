<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

$t_this_page = 'manage_config';
ERP_print_menu( $t_this_page );

?>

<br />
<table align="center" class="width75" cellspacing="1">

<tr>
	<td class="left">
<?php
	$t_link1 = helper_mantis_url( 'plugins/' . plugin_get_current() . '/scripts/bug_report_mail.php' );
	$t_link2 = plugin_page( 'bug_report_mail' );
	echo plugin_lang_get( 'jobsetup' ) . '<hr />' .
		plugin_lang_get( 'job1' ) . '<a href="' . $t_link1 . '">' . $t_link1 . '</a><br />' .
		plugin_lang_get( 'job2' ) . '<a href="' . $t_link2 . '">' . $t_link2 . '</a>';
?>
	</td>
</tr>

</table>
<br />

<form action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post">
<table align="center" class="width75" cellspacing="1">

<?php
ERP_output_config_option( 'problems', 'header' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'security_options', 'header', 'manage_mailbox' );
ERP_output_config_option( 'mail_secured_script', 'boolean', -2 );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'runtime_options', 'header' );
ERP_output_config_option( 'mail_fetch_max', 'integer', -2 );
ERP_output_config_option( 'mail_delete', 'boolean', -2 );
ERP_output_config_option( 'mail_tmp_directory', 'directory_string', -2 );
ERP_output_config_option( 'mail_encoding', 'dropdown_mbstring_encodings', -2 );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'reporter_options', 'header' );
ERP_output_config_option( 'mail_use_reporter', 'boolean', -2 );
ERP_output_config_option( 'mail_fallback_mail_reporter', 'boolean', -2 );
ERP_output_config_option( 'mail_reporter_id', 'dropdown_list_reporters', -2 );
ERP_output_config_option( 'mail_auto_signup', 'boolean', -2 );
ERP_output_config_option( 'mail_preferred_username', 'dropdown_pref_usernames', -2 );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'feature_options', 'header' );
ERP_output_config_option( 'mail_add_bug_reports', 'boolean', -2 );
ERP_output_config_option( 'mail_add_bugnotes', 'boolean', -2 );
ERP_output_config_option( 'mail_parse_html', 'boolean', -2 );
ERP_output_config_option( 'mail_remove_mantis_email', 'boolean', -2 );
ERP_output_config_option( 'mail_remove_replies', 'boolean', -2 );
ERP_output_config_option( 'mail_email_receive_own', 'boolean', -2 );
ERP_output_config_option( 'mail_save_from', 'boolean', -2 );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'priority_feature_options', 'header' );
ERP_output_config_option( 'mail_use_bug_priority', 'boolean', -2 );
ERP_output_config_option( 'mail_bug_priority', 'string_multiline', -2 );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'default_texts_options', 'header' );
ERP_output_config_option( 'mail_nosubject', 'string', -2 );
ERP_output_config_option( 'mail_nodescription', 'string', -2 );
ERP_output_config_option( 'mail_removed_reply_text', 'string', -2 );
ERP_output_config_option( 'mail_remove_replies_after', 'string_multiline', -2 );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'debug_options', 'header' );
ERP_output_config_option( 'mail_debug', 'boolean', -2 );
ERP_output_config_option( 'mail_debug_directory', 'directory_string', -2 );
ERP_output_config_option( 'mail_add_complete_email', 'boolean', -2 );

ERP_output_config_option( 'update_configuration', 'submit' );

?>

</table>
</form>

<?php
html_page_bottom( __FILE__ );
?>
