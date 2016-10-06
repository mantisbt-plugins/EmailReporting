<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

layout_page_header( plugin_lang_get( 'plugin_title' ) );

layout_page_begin( 'manage_overview_page.php' );

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
layout_page_end();
?>
