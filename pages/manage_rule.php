<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

require_api( 'custom_field_api.php' );

$t_this_page = 'manage_rule';
ERP_page_begin( $t_this_page );

ERP_output_note_open();
echo '<p>' . plugin_lang_get( 'rule_wildcards' ) . '<br />' . nl2br( plugin_lang_get( 'rule_wildcards_help' ) ) . '</p>';
ERP_output_note_close();

ERP_output_note_open();
echo '<p>' . plugin_lang_get( 'rule_conditions' ) . '<br />' . nl2br( plugin_lang_get( 'rule_conditions_help' ) ) . '</p>';
ERP_output_note_close();

ERP_output_note_open();
echo '<p>' . plugin_lang_get( 'rule_actions' ) . '<br />' . nl2br( plugin_lang_get( 'rule_actions_help' ) ) . '</p>';
ERP_output_note_close();

ERP_output_note_open();
echo '<p>' . plugin_lang_get( 'rule_exceptions' ) . '<br />' . nl2br( plugin_lang_get( 'rule_exceptions_help' ) ) . '</p>';
ERP_output_note_close();

?>

<form action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post">

<?php

$t_rules = plugin_config_get( 'rules' );

$f_rule_action = gpc_get_string( 'rule_action', 'add' );
$f_select_rule = gpc_get_int( 'select_rule', -1 );

$t_rule = array();

if ( $f_rule_action !== 'add' )
{
	if ( isset( $t_rules[ $f_select_rule ] ) )
	{
		$t_rule = $t_rules[ $f_select_rule ];

		// Add "Copy of" text if necessary to rules being copied
		if ( $f_rule_action === 'copy' )
		{
			$t_rule[ 'description' ] = plugin_lang_get( 'copy_of') . ' ' . $t_rule[ 'description' ];
		}
	}
	else
	{
		$f_rule_action = 'add';
	}
}

ERP_output_config_option( 'rule_action', 'hidden', $f_rule_action );
ERP_output_config_option( 'select_rule', 'hidden', $f_select_rule );

ERP_output_table_open( 'rule_settings' );
ERP_output_config_option( 'enabled', 'boolean', $t_rule );
ERP_output_config_option( 'description', 'string', $t_rule );
ERP_output_table_close();

ERP_output_table_open( 'rule_conditions' );
ERP_output_config_option( 'cond_issue_creation_method', 'dropdown_multiselect_any', $t_rule, 'print_descriptions_option_list', array_keys( $g_plugin_cache ) );
ERP_output_config_option( 'cond_mailbox', 'dropdown_multiselect_any', $t_rule, 'print_descriptions_option_list', plugin_config_get( 'mailboxes' ) );
ERP_output_config_option( 'cond_issue_issue_issuenote', 'dropdown_multiselect_any', $t_rule, 'print_descriptions_option_list', array( 'newnote', 'newissue', 'newattachment' ) );
ERP_output_config_option( 'cond_issue_reporter', 'dropdown_multiselect_any', $t_rule, 'print_reporter_option_list' );
ERP_output_config_option( 'cond_issue_project', 'dropdown_multiselect_any', $t_rule, 'print_projects_option_list' );
ERP_output_config_option( 'cond_issue_category', 'dropdown_multiselect_any', $t_rule, 'print_global_category_option_list' );
ERP_output_config_option( 'cond_issue_priority', 'dropdown_multiselect_any', $t_rule, 'print_priority_option_list' );
ERP_output_config_option( 'cond_issue_summary', 'string', $t_rule );
ERP_output_config_option( 'cond_issue_description', 'string', $t_rule );
ERP_output_table_close();

ERP_output_table_open( 'rule_actions' );
ERP_output_config_option( 'act_issue_severity', 'dropdown_any', $t_rule, 'print_severity_option_list' );
ERP_output_config_option( 'act_issue_status', 'dropdown_any', $t_rule, 'print_status_option_list' );
ERP_output_config_option( 'act_issue_category', 'dropdown_any', $t_rule, 'print_global_category_option_list' );
ERP_output_config_option( 'act_issue_tag', 'dropdown_multiselect', $t_rule, 'print_tag_attach_option_list' );
ERP_output_config_option( 'act_issue_custom_field', 'custom', $t_rule, 'print_custom_fields' );
ERP_output_table_close();

ERP_output_table_open( 'rule_exceptions' );
ERP_output_config_option( 'excep_issue_summary', 'string', $t_rule );
ERP_output_config_option( 'excep_issue_description', 'string', $t_rule );
ERP_output_table_close();

ERP_output_table_open();
ERP_output_table_close( $f_rule_action . '_action' );

?>

</form>

<div class="space-10"></div>

<form action="<?php echo plugin_page( $t_this_page )?>" method="post">

<?php

ERP_output_table_open( 'rules' );
ERP_output_config_option( 'rule_action', 'radio_buttons', $f_rule_action, 'print_rule_action_radio_buttons', $t_rules );
ERP_output_config_option( 'select_rule', 'dropdown', $f_select_rule, 'print_descriptions_option_list', $t_rules );
ERP_output_table_close( 'select_rule' );

?>

<form>

<?php
ERP_page_end( __FILE__ );
?>
