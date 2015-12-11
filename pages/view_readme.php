<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'plugin_title' ) );

print_manage_menu();

plugin_require_api( 'core/config_api.php' );

$t_this_page = 'view_readme';
ERP_print_menu( $t_this_page );

?>

<pre>
<?php
plugin_require_api( 'doc/INSTALL.txt' );
?>
</pre>

<?php
html_page_bottom( __FILE__ );
?>
