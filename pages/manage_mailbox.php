<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

plugin_require_api( 'core/config_api.php' );

$t_this_page = 'manage_mailbox';
ERP_print_menu( $t_this_page );

?>

<br />
<form action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post">

<?php

$GLOBALS[ 't_mailboxes' ] = plugin_config_get( 'mailboxes' );
$t_rules = plugin_config_get( 'rules' );

$f_mailbox_action = gpc_get_string( 'mailbox_action', 'add' );
$f_select_mailbox = gpc_get_int( 'select_mailbox', -1 );

// the defaults different from the default NULL value
$t_mailbox = ERP_get_default_mailbox();

if ( $f_mailbox_action !== 'add' )
{
	if ( isset( $GLOBALS[ 't_mailboxes' ][ $f_select_mailbox ] ) )
	{
		// merge existing selected mailbox into the default mailbox overwriting existing default values
		$t_mailbox = $GLOBALS[ 't_mailboxes' ][ $f_select_mailbox ] + $t_mailbox;

		// Add "Copy of" text if necessary to mailboxes being copied
		if ( $f_mailbox_action === 'copy' )
		{
			$t_mailbox[ 'description' ] = plugin_lang_get( 'copy_of') . ' ' . $t_mailbox[ 'description' ];
		}
	}
	else
	{
		$f_mailbox_action = 'add';
	}
}

ERP_output_config_option( 'mailbox_action', 'hidden', $f_mailbox_action );
ERP_output_config_option( 'select_mailbox', 'hidden', $f_select_mailbox );

if ( $f_mailbox_action === 'complete_test' )
{
?>
<table align="center" class="width75" cellspacing="1">

<tr>
	<td class="left">
		<?php echo nl2br( plugin_lang_get( 'complete_test_action_note' ) ) ?>
	</td>
</tr>

</table>
<br />
<?php
}

// Loading this one here to throw a error if necessary and notifying the user of the issue
include_once 'PEAR.php';
if ( !defined( 'PEAR_OS' ) )
{
?>
<table align="center" class="width50" cellspacing="1">

<tr>
	<td class="left">
		<?php echo plugin_lang_get( 'pear_load_error' ); ?>
	</td>
</tr>

</table>
<br />
<?php
}
?>

<table align="center" class="width50 nowrap" cellspacing="1">
<?php

ERP_output_config_option( 'mailbox_settings', 'header', 'manage_config' );

ERP_output_config_option( 'enabled', 'boolean', $t_mailbox );
ERP_output_config_option( 'description', 'string', $t_mailbox );
ERP_output_config_option( 'mailbox_type', 'dropdown', $t_mailbox, 'print_descriptions_option_list', array( 'IMAP', 'POP3' ) );
ERP_output_config_option( 'hostname', 'string', $t_mailbox );
ERP_output_config_option( 'port', 'string', $t_mailbox );
ERP_output_config_option( 'encryption', 'dropdown', $t_mailbox, 'print_encryption_option_list' );
ERP_output_config_option( 'ssl_cert_verify', 'boolean', $t_mailbox );
ERP_output_config_option( 'erp_username', 'string', $t_mailbox );
ERP_output_config_option( 'erp_password', 'string_password', $t_mailbox );
ERP_output_config_option( 'auth_method', 'dropdown', $t_mailbox, 'print_auth_method_option_list' );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'mailbox_settings_imap', 'header' );
ERP_output_config_option( 'imap_basefolder', 'string', $t_mailbox );
ERP_output_config_option( 'imap_createfolderstructure', 'boolean', $t_mailbox );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'mailbox_settings_issue', 'header' );
ERP_output_config_option( 'project_id', 'dropdown', $t_mailbox, 'print_projects_option_list' );
ERP_output_config_option( 'global_category_id', 'dropdown', $t_mailbox, 'print_global_category_option_list' );
//ERP_output_config_option( 'link_rules', 'dropdown_multiselect', $t_mailbox, 'print_descriptions_option_list', $t_rules ); // Should we use this here or from the rules page?
//ERP_output_config_option( 'recorddisabled', 'empty' );

event_signal( 'EVENT_ERP_OUTPUT_MAILBOX_FIELDS', $f_select_mailbox );

ERP_output_config_option( $f_mailbox_action . '_action', 'submit' );

?>
</table>
</form>

<br />

<form action="<?php echo plugin_page( $t_this_page )?>" method="post">
<table align="center" class="width50 nowrap" cellspacing="1">
<?php

ERP_output_config_option( 'mailboxes', 'header', 'manage_config' );

ERP_output_config_option( 'mailbox_action', 'radio_buttons', $f_mailbox_action, 'print_mailbox_action_radio_buttons', $GLOBALS[ 't_mailboxes' ] );
ERP_output_config_option( 'select_mailbox', 'dropdown', $f_select_mailbox, 'print_descriptions_option_list', $GLOBALS[ 't_mailboxes' ] );
ERP_output_config_option( 'recorddisabled', 'empty' );

ERP_output_config_option( 'select_mailbox', 'submit' );

?>
</table>
<form>

<?php
html_page_bottom( __FILE__ );
?>
