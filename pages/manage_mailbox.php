<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

$t_this_page = 'manage_mailbox';
ERP_page_begin( $t_this_page );

?>

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
	ERP_output_note_open();
?>
<p><i class="fa fa-warning"></i> 
<?php echo nl2br( plugin_lang_get( 'complete_test_action_note' ) ) ?>
</p>
<?php
	ERP_output_note_close();
}

// Loading this one here to throw a error if necessary and notifying the user of the issue
plugin_require_api( 'core_pear/PEAR.php' );
if ( !defined( 'PEAR_OS' ) )
{
	ERP_output_note_open();
?>
<p><i class="fa fa-warning"></i> 
<?php echo plugin_lang_get( 'pear_load_error' ); ?>
</p>
<?php
	ERP_output_note_close();
}
?>

<?php

ERP_output_table_open( 'mailbox_settings' );
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
ERP_output_table_close();

ERP_output_table_open( 'mailbox_settings_imap' );
ERP_output_config_option( 'imap_basefolder', 'string', $t_mailbox );
ERP_output_config_option( 'imap_createfolderstructure', 'boolean', $t_mailbox );
ERP_output_table_close();

ERP_output_table_open( 'mailbox_settings_issue' );
ERP_output_config_option( 'project_id', 'dropdown', $t_mailbox, 'print_projects_option_list' );
ERP_output_config_option( 'global_category_id', 'dropdown', $t_mailbox, 'print_global_category_option_list' );
//ERP_output_config_option( 'link_rules', 'dropdown_multiselect', $t_mailbox, 'print_descriptions_option_list', $t_rules ); // Should we use this here or from the rules page?
ERP_output_table_close();

event_signal( 'EVENT_ERP_OUTPUT_MAILBOX_FIELDS', $f_select_mailbox );

ERP_output_table_open();
ERP_output_table_close( $f_mailbox_action . '_action' );

?>
</form>

<div class="space-10"></div>

<form action="<?php echo plugin_page( $t_this_page )?>" method="post">
<?php

ERP_output_table_open( 'mailboxes' );
ERP_output_config_option( 'mailbox_action', 'radio_buttons', $f_mailbox_action, 'print_mailbox_action_radio_buttons', $GLOBALS[ 't_mailboxes' ] );
ERP_output_config_option( 'select_mailbox', 'dropdown', $f_select_mailbox, 'print_descriptions_option_list', $GLOBALS[ 't_mailboxes' ] );
ERP_output_table_close( 'select_mailbox' );

?>
<form>

<?php
ERP_page_end( __FILE__ );
?>
