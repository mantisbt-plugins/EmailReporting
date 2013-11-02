<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

$t_this_page = 'view_readme';
ERP_print_menu( $t_this_page );

?>

<pre>
<?php
require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'doc/README.bug_report_mail.txt' );
?>
</pre>

<?php
html_page_bottom( __FILE__ );
?>
