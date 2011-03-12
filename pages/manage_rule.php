<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

$t_this_page = 'manage_rule';
ERP_print_menu( $t_this_page );

?>

<br />
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

?>
<table align="center" class="width50 nowrap" cellspacing="1">
<?php

ERP_output_config_option( 'rule_settings', 'header', 'manage_mailbox' );

ERP_output_config_option( 'enabled', 'boolean', -3, $t_rule );
ERP_output_config_option( 'description', 'string', -3, $t_rule );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'rule_conditions', 'header' );
ERP_output_config_option( 'email_subject', 'string', -3, $t_rule );
ERP_output_config_option( 'email_from_name', 'string', -3, $t_rule );
ERP_output_config_option( 'email_from_email', 'string', -3, $t_rule );
ERP_output_config_option( 'email_sendto', 'string', -3, $t_rule );
ERP_output_config_option( 'email_body', 'string', -3, $t_rule );
ERP_output_config_option( 'email_number_of_attachments', 'integer', -3, $t_rule );
ERP_output_config_option( 'issue_reporter', 'string', -3, $t_rule );
ERP_output_config_option( 'email_type', 'string', -3, $t_rule ); //bugnote or new bug
ERP_output_config_option( 'issue_project', 'string', -3, $t_rule );
ERP_output_config_option( 'issue_category', 'string', -3, $t_rule );
ERP_output_config_option( 'email_priority', 'string', -3, $t_rule );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'rule_actions', 'header' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'rule_exceptions', 'header' );

ERP_output_config_option( $f_rule_action . '_action', 'submit' );

?>
</table>
</form>

<br />

<form action="<?php echo plugin_page( $t_this_page )?>" method="post">
<table align="center" class="width50 nowrap" cellspacing="1">
<?php

ERP_output_config_option( 'rules', 'header', 'manage_mailbox' );

$t_actions_list = array(
	0 => array( 'add' ),
	1 => array( 'copy', 'edit', 'delete'/*, 'test'*/ ),
);
ERP_output_config_option( 'rule_action', 'radio_actions', $f_rule_action, $t_rules, $t_actions_list );
ERP_output_config_option( 'select_rule', 'dropdown_descriptions', $f_select_rule, NULL, $t_rules );
ERP_output_config_option( 'disabled', 'empty' );

ERP_output_config_option( 'select_rule', 'submit' );

?>
</table>
<form>
	
<?php
html_page_bottom( __FILE__ );
?>
