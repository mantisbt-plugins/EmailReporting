<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

$t_this_page = 'view_readme';
ERP_page_begin( $t_this_page );

?>

<pre>
<?php
plugin_require_api( 'doc/INSTALL.txt' );
?>
</pre>

<?php
ERP_page_end( __FILE__ );
?>
