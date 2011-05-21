<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

$t_this_page = 'view_documentation';
ERP_print_menu( $t_this_page );

?>

<?php
$t_docu_head = gpc_get_string( 'docu_head', -1 );
if ( $t_docu_head !== -1 )
{
	echo plugin_lang_get( gpc_get_string( 'docu_head' ) );
	echo nl2br( plugin_lang_get( 'help_' . gpc_get_string( 'docu_head' ) ) );
}
else
{
	echo plugin_lang_get( 'help_unknown' );
}
?>
	
<?php
html_page_bottom( __FILE__ );
?>
