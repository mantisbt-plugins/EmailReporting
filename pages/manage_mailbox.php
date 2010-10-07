<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

$t_this_page = 'manage_mailbox';
ERP_print_menu( $t_this_page );

?>

<br />
<form action="<?php echo plugin_page( $t_this_page . '_edit' )?>" method="post">

<?php

$GLOBALS[ 't_mailboxes' ] = plugin_config_get( 'mailboxes' );

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

?>
<table align="center" class="width50 nowrap" cellspacing="1">
<?php

ERP_output_config_option( 'mailbox_settings', 'header', 'manage_config' );

ERP_output_config_option( 'enabled', 'boolean', -3, $t_mailbox );
ERP_output_config_option( 'description', 'string', -3, $t_mailbox );
ERP_output_config_option( 'type', 'dropdown_mailbox_type', -3, $t_mailbox );
ERP_output_config_option( 'hostname', 'string', -3, $t_mailbox );
ERP_output_config_option( 'port', 'string', -3, $t_mailbox );
ERP_output_config_option( 'encryption', 'dropdown_encryption', -3, $t_mailbox );
ERP_output_config_option( 'username', 'string', -3, $t_mailbox );
ERP_output_config_option( 'password', 'string_password', -3, $t_mailbox );
ERP_output_config_option( 'auth_method', 'dropdown_auth_method', -3, $t_mailbox );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'mailbox_settings_imap', 'header' );
ERP_output_config_option( 'basefolder', 'string', -3, $t_mailbox );
ERP_output_config_option( 'createfolderstructure', 'boolean', -3, $t_mailbox );

ERP_output_config_option( NULL, 'empty' );
ERP_output_config_option( 'mailbox_settings_issue', 'header' );
ERP_output_config_option( 'project_id', 'dropdown_projects', -3, $t_mailbox );
ERP_output_config_option( 'global_category_id', 'dropdown_global_categories', -3, $t_mailbox );

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

$t_actions_list = array(
	0 => array( 'add' ),
	1 => array( 'copy', 'edit', 'delete', 'test' ),
);
ERP_output_config_option( 'mailbox_action', 'radio_actions', $f_mailbox_action, $GLOBALS[ 't_mailboxes' ], $t_actions_list );
ERP_output_config_option( 'select_mailbox', 'dropdown_descriptions', $f_select_mailbox, NULL, $GLOBALS[ 't_mailboxes' ] );
ERP_output_config_option( 'mailboxes_disabled', 'empty' );

ERP_output_config_option( 'select_mailbox', 'submit' );

?>
</table>
<form>
	
<?php
html_page_bottom( __FILE__ );
?>
