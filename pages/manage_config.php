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
		'<ol><li><a href="' . $t_link1 . '">' . $t_link1 . '</a></li>' .
		'<li><a href="' . $t_link2 . '">' . $t_link2 . '</a></li></ol>';
?>
	</td>
</tr>

</table>
<br />

<?php
	$t_job_users = plugin_config_get( 'job_users' );
	$t_username = ERP_get_current_os_user();
	$t_file_upload_method = config_get( 'file_upload_method' );
	if ( count( array_diff( $t_job_users, array( $t_username ) ) ) > 0 && $t_file_upload_method == DISK )
	{
?>
<table align="center" class="width75" cellspacing="1">

<tr>
	<td class="left">
<?php
		echo plugin_lang_get( 'job_users' ) . $t_username . '<hr />';
?>
		<table align="center" class="width50" cellspacing="1">
			<tr>
				<th class="left">SAPI <a href="http://www.php.net/php_sapi_name">[?]</a></th>
				<th class="left">Username</th>
			</tr>
<?php
		foreach( $t_job_users AS $t_key => $t_array )
		{
?>
			<tr>
				<td class="left"><?php echo $t_key ?></td>
				<td class="left"><?php echo $t_array ?></td>
			</tr>
<?php
		}
?>
		</table>
	</td>
</tr>

</table>
<br />
<?php
	}
?>

<form action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post">
<table align="center" class="width75" cellspacing="1">

<?php
ERP_output_config_option( 'problems', 'header' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'security_options', 'header', 'manage_mailbox' );
ERP_output_config_option( 'mail_secured_script', 'boolean' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'runtime_options', 'header' );
ERP_output_config_option( 'mail_fetch_max', 'integer' );
ERP_output_config_option( 'mail_delete', 'boolean' );
ERP_output_config_option( 'mail_encoding', 'dropdown', NULL, 'print_mbstring_encoding_option_list' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'reporter_options', 'header' );
ERP_output_config_option( 'mail_use_reporter', 'boolean' );
ERP_output_config_option( 'mail_fallback_mail_reporter', 'boolean' );
ERP_output_config_option( 'mail_reporter_id', 'dropdown', NULL, 'print_reporter_option_list' );
ERP_output_config_option( 'mail_auto_signup', 'boolean' );
ERP_output_config_option( 'mail_preferred_username', 'dropdown', NULL, 'print_descriptions_option_list', array( 'name', 'email_address', 'email_no_domain', 'from_ldap' ) );
ERP_output_config_option( 'mail_preferred_realname', 'dropdown', NULL, 'print_descriptions_option_list', array( 'name', 'email_address', 'email_no_domain', 'from_ldap', 'full_from' ) );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'feature_options', 'header' );
ERP_output_config_option( 'mail_add_bug_reports', 'boolean' );
ERP_output_config_option( 'mail_add_bugnotes', 'boolean' );
ERP_output_config_option( 'mail_rule_system', 'boolean' );
ERP_output_config_option( 'mail_parse_html', 'boolean' );
ERP_output_config_option( 'mail_remove_mantis_email', 'boolean' );
ERP_output_config_option( 'mail_remove_replies', 'boolean' );
ERP_output_config_option( 'mail_email_receive_own', 'boolean' );
ERP_output_config_option( 'mail_save_from', 'boolean' );
ERP_output_config_option( 'mail_save_subject_in_note', 'boolean' );
ERP_output_config_option( 'mail_subject_remove_re_fwd', 'boolean' );
ERP_output_config_option( 'mail_subject_id_regex', 'dropdown', NULL, 'print_descriptions_option_list', array( 'strict', 'balanced', 'relaxed' ) );
ERP_output_config_option( 'mail_subject_summary_match', 'boolean' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'priority_feature_options', 'header' );
ERP_output_config_option( 'mail_use_bug_priority', 'boolean' );
ERP_output_config_option( 'mail_bug_priority', 'string_multiline' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'default_texts_options', 'header' );
ERP_output_config_option( 'mail_nosubject', 'string' );
ERP_output_config_option( 'mail_nodescription', 'string' );
ERP_output_config_option( 'mail_removed_reply_text', 'string' );
ERP_output_config_option( 'mail_remove_replies_after', 'string_multiline' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'debug_options', 'header' );
ERP_output_config_option( 'mail_debug', 'boolean' );
ERP_output_config_option( 'mail_debug_directory', 'directory_string' );
ERP_output_config_option( 'mail_add_complete_email', 'boolean' );

ERP_output_config_option( 'update_configuration', 'submit' );

?>

</table>
</form>

<?php
html_page_bottom( __FILE__ );
?>
