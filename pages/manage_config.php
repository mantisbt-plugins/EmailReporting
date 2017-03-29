<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

$t_this_page = 'manage_config';
ERP_page_begin( $t_this_page );

// Output scheduled job info box
ERP_output_note_open();
?>
<p><i class="fa fa-info-circle"></i> 
<?php
$t_link1 = helper_mantis_url( 'plugins/' . plugin_get_current() . '/scripts/bug_report_mail.php' );
$t_link2 = plugin_page( 'bug_report_mail' );
ERP_print_documentation_link( 'setting_up_a_scheduled_cron_job_for_emailreporting', 'jobsetup' );
?>
</p>
<ol>
	<li><a href="<?php echo $t_link1 ?>"><?php echo $t_link1 ?></a></li>
	<li><a href="<?php echo $t_link2 ?>"><?php echo $t_link2 ?></a></li>
</ol>
<?php
ERP_output_note_close();

// Output mbstring info box
if ( !extension_loaded( 'mbstring' ) )
{
	ERP_output_note_open();
?>
<p><i class="fa fa-info-circle"></i> 
<?php
	echo plugin_lang_get( 'mbstring_unavailable' );
?>
</p>
<?php
	ERP_output_note_close();
}

// Output utf8 info box
elseif ( $t_results_utf_test = test_database_utf8() )
{
	ERP_output_note_open();
?>
<p><i class="fa fa-info-circle"></i> 
<?php
	echo plugin_lang_get( 'db_utf8_issue' );
?>
</p>

<table class="table table-bordered table-condensed table-striped">
<?php
	echo $t_results_utf_test;
?>
</table>
<?php
	ERP_output_note_close();
}

// Output scheduled job users warning box
$t_job_users = (array) plugin_config_get( 'job_users' );
$t_username = ERP_get_current_os_user();
$t_file_upload_method = config_get( 'file_upload_method' );
if ( count( array_diff( (array) $t_job_users, (array) $t_username ) ) > 0 && $t_file_upload_method == DISK )
{
	ERP_output_note_open();
?>
<p><i class="fa fa-warning"></i> 
<?php
	echo plugin_lang_get( 'job_users' ) . $t_username;
?>
</p>
<?php
	ERP_output_table_open();
?>
		<thead>
			<tr>
				<th class="bold left">SAPI <a href="http://www.php.net/php_sapi_name" target="_blank">[?]</a></th>
				<th class="bold left"><?php echo lang_get( 'username' ) ?></th>
			</tr>
		</thead>
		<tbody>
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
		</tbody>
<?php
	ERP_output_table_close();

	ERP_output_note_close();
}

ERP_output_note_open();
?>
<p><i class="fa fa-info-circle"></i> 
<?php echo plugin_lang_get( 'problems' ) ?>
</p>
<?php
ERP_output_note_close();
?>

<form action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post">
<?php

ERP_output_table_open( 'security_options' );
ERP_output_config_option( 'mail_secured_script', 'boolean' );
ERP_output_config_option( 'mail_secured_ipaddr', 'string' );
ERP_output_table_close();

ERP_output_table_open( 'runtime_options' );
ERP_output_config_option( 'mail_delete', 'boolean' );
ERP_output_config_option( 'mail_max_email_body', 'integer' );
ERP_output_config_option( 'mail_max_email_body_text', 'string' );
ERP_output_config_option( 'mail_max_email_body_add_attach', 'boolean' );
ERP_output_table_close();

ERP_output_table_open( 'reporter_options' );
ERP_output_config_option( 'mail_use_reporter', 'boolean' );
ERP_output_config_option( 'mail_fallback_mail_reporter', 'boolean' );
ERP_output_config_option( 'mail_reporter_id', 'dropdown', NULL, 'print_reporter_option_list' );
ERP_output_config_option( 'mail_auto_signup', 'boolean' );
ERP_output_config_option( 'mail_preferred_username', 'dropdown', NULL, 'print_descriptions_option_list', array( 'name', 'email_address', 'email_no_domain', 'from_ldap' ) );
ERP_output_config_option( 'mail_preferred_realname', 'dropdown', NULL, 'print_descriptions_option_list', array( 'name', 'email_address', 'email_no_domain', 'from_ldap', 'full_from' ) );
ERP_output_config_option( 'mail_disposable_email_checker', 'boolean' );
ERP_output_table_close();

ERP_output_table_open( 'feature_options' );
ERP_output_config_option( 'mail_add_bug_reports', 'boolean' );
ERP_output_config_option( 'mail_add_bugnotes', 'boolean' );
ERP_output_config_option( 'mail_rule_system', 'disabled' ); //disabled as the rule system is not ready for use
ERP_output_config_option( 'mail_parse_html', 'boolean' );
ERP_output_config_option( 'mail_email_receive_own', 'boolean' );
ERP_output_config_option( 'mail_save_from', 'boolean' );
ERP_output_config_option( 'mail_save_subject_in_note', 'boolean' );
ERP_output_config_option( 'mail_subject_id_regex', 'dropdown', NULL, 'print_descriptions_option_list', array( 'strict', 'balanced', 'relaxed' ) );
ERP_output_config_option( 'mail_use_message_id', 'boolean' );
ERP_output_config_option( 'mail_add_users_from_cc_to', 'boolean' );
ERP_output_table_close();

ERP_output_table_open( 'priority_feature_options' );
ERP_output_config_option( 'mail_use_bug_priority', 'boolean' );
ERP_output_config_option( 'mail_bug_priority', 'string_multiline_array' );
ERP_output_table_close();

ERP_output_table_open( 'attachment_feature_options' );
ERP_output_config_option( 'mail_block_attachments_md5', 'string_multiline' );
ERP_output_config_option( 'mail_block_attachments_logging', 'boolean' );
ERP_output_table_close();

ERP_output_table_open( 'strip_signature_feature_options' );
ERP_output_config_option( 'mail_strip_signature', 'boolean' );
ERP_output_config_option( 'mail_strip_signature_delim', 'string' );
ERP_output_table_close();

ERP_output_table_open( 'default_texts_options' );
ERP_output_config_option( 'mail_nosubject', 'string' );
ERP_output_config_option( 'mail_nodescription', 'string' );
ERP_output_table_close();

ERP_output_table_open( 'remove_reply_options' );
ERP_output_config_option( 'mail_remove_replies', 'boolean' );
ERP_output_config_option( 'mail_remove_replies_after', 'string_multiline' );
ERP_output_config_option( 'mail_strip_gmail_style_replies', 'boolean' );
ERP_output_config_option( 'mail_remove_mantis_email', 'boolean' );
ERP_output_config_option( 'mail_removed_reply_text', 'string' );
ERP_output_table_close();

ERP_output_table_open( 'debug_options' );
ERP_output_config_option( 'mail_debug', 'boolean' );
ERP_output_config_option( 'mail_debug_directory', 'directory_string' );
ERP_output_config_option( 'mail_add_complete_email', 'boolean' );
ERP_output_config_option( 'mail_debug_show_memory_usage', 'boolean' );
ERP_output_table_close();

ERP_output_table_open();
ERP_output_table_close( 'update_configuration' );

?>
</form>

<?php
ERP_page_end( __FILE__ );
?>
