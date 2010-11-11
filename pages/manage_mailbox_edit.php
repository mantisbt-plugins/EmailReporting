<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/config_api.php' );

$f_mailbox_action = gpc_get_string( 'mailbox_action' );
$f_select_mailbox = gpc_get_int( 'select_mailbox' );

$t_mailboxes = plugin_config_get( 'mailboxes' );

if ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'test' ) && $f_select_mailbox >= 0 ) )
{
	$t_mailbox = array(
		'enabled'				=> gpc_get_bool( 'enabled' ),
		'description'			=> gpc_get_string( 'description' ),
		'type'					=> gpc_get_string( 'type' ),
		'hostname'				=> gpc_get_string( 'hostname' ),
		'port'					=> gpc_get_string( 'port' ),
		'encryption'			=> gpc_get_string( 'encryption' ),
		'username'				=> gpc_get_string( 'username' ),
		'password'				=> base64_encode( gpc_get_string( 'password' ) ),
		'auth_method'			=> gpc_get_string( 'auth_method' ),
		'project_id'			=> gpc_get_int( 'project_id' ),
		'global_category_id'	=> gpc_get_int( 'global_category_id' ),
	);

	if ( $t_mailbox[ 'type' ] === 'IMAP' )
	{
		$t_mailbox_imap = array(
			'basefolder'			=> ERP_prepare_directory_string( gpc_get_string( 'basefolder' ), TRUE ),
			'createfolderstructure'	=> gpc_get_bool( 'createfolderstructure' ),
		);

		$t_mailbox += $t_mailbox_imap;
	}

	$t_plugin_content = gpc_get_string_array( 'plugin_content', NULL );

	if ( is_array( $t_plugin_content ) )
	{
		$t_mailbox += array( 'plugin_content' => $t_plugin_content );
	}
}

if ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' )
{
	$t_mailboxes[] = $t_mailbox;
}
elseif ( $f_mailbox_action === 'edit' && $f_select_mailbox >= 0 )
{
	$t_mailboxes[ $f_select_mailbox ] = $t_mailbox;
}
elseif ( $f_mailbox_action === 'delete' && $f_select_mailbox >= 0 )
{
	unset( $t_mailboxes[ $f_select_mailbox ] );
}
elseif ( $f_mailbox_action === 'test' && $f_select_mailbox >= 0 )
{
	# Verify mailbox - from Recmail by Cas Nuy
	require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/mail_api.php' );

	$t_mailbox_api = new ERP_mailbox_api( TRUE );
	$t_result = $t_mailbox_api->process_mailbox( $t_mailbox );

	$t_is_custom_error = ( is_array( $t_result ) && isset( $t_result[ 'ERROR_TYPE' ] ) && $t_result[ 'ERROR_TYPE' ] === 'NON-PEAR-ERROR' );

	if ( $t_is_custom_error || PEAR::isError( $t_result ) )
	{
		$t_no_redirect = TRUE;

		html_page_top( plugin_lang_get( 'plugin_title' ) );
?>
<br /><div class="center">
<?php
		echo plugin_lang_get( 'test_failure' ) . '<br /><br />';
		echo plugin_lang_get( 'description' ) . ': ' . $t_mailbox_api->_mailbox[ 'description' ] . '<br />';
		echo plugin_lang_get( 'type' ) . ': ' . $t_mailbox_api->_mailbox[ 'type' ] . '<br />';
		echo plugin_lang_get( 'hostname' ) . ': ', $t_mailbox_api->_mailbox[ 'hostname' ] . '<br />';
		echo plugin_lang_get( 'port' ) . ': ', $t_mailbox_api->_mailbox[ 'port' ] . '<br />';
		echo plugin_lang_get( 'encryption' ) . ': ' . $t_mailbox_api->_mailbox[ 'encryption' ] . '<br />';
		echo plugin_lang_get( 'username' ) . ': ' . $t_mailbox_api->_mailbox[ 'username' ] . '<br />';
		echo plugin_lang_get( 'password' ) . ': ******' . '<br />';
		echo plugin_lang_get( 'auth_method' ) . ': ' . $t_mailbox_api->_mailbox[ 'auth_method' ] . '<br />';

		if ( $t_mailbox_api->_mailbox[ 'type' ] === 'IMAP' )
		{
			echo plugin_lang_get( 'basefolder' ) . ': ' . $t_mailbox_api->_mailbox[ 'basefolder' ] . '<br />';
		}

		echo '<br />' . ( ( $t_is_custom_error ) ? $t_result[ 'ERROR_MESSAGE' ] : $t_result->toString() ) . '<br /><br />';

		print_bracket_link( plugin_page( 'manage_mailbox', TRUE ), lang_get( 'proceed' ) );
?>
</div>
<?php
		html_page_bottom( __FILE__ );
	}
}

if( plugin_config_get( 'mailboxes' ) != $t_mailboxes && ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'delete' ) && $f_select_mailbox >= 0 ) ) )
{
	plugin_config_set( 'mailboxes', $t_mailboxes );
}

if ( !isset( $t_no_redirect ) )
{
	print_successful_redirect( plugin_page( 'manage_mailbox', TRUE ) );
}
