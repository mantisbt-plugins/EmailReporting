<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );

$f_mailbox_action = gpc_get_string( 'mailbox_action' );
$f_select_mailbox = gpc_get_int( 'select_mailbox' );

$t_mailboxes = plugin_config_get( 'mailboxes' );

if ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'test' || $f_mailbox_action === 'complete_test' ) && $f_select_mailbox >= 0 ) )
{
	$t_mailbox = array(
		'enabled'				=> gpc_get_int( 'enabled', ON ),
		'description'			=> gpc_get_string( 'description', '' ),
		'mailbox_type'			=> gpc_get_string( 'mailbox_type' ),
		'hostname'				=> gpc_get_string( 'hostname', '' ),
		'port'					=> gpc_get_string( 'port', '' ),
		'encryption'			=> gpc_get_string( 'encryption' ),
		'ssl_cert_verify'		=> gpc_get_int( 'ssl_cert_verify', ON ),
		'erp_username'			=> gpc_get_string( 'erp_username', '' ),
		'erp_password'			=> base64_encode( gpc_get_string( 'erp_password', '' ) ),
		'auth_method'			=> gpc_get_string( 'auth_method' ),
		'project_id'			=> gpc_get_int( 'project_id' ),
		'global_category_id'	=> gpc_get_int( 'global_category_id' ),
//		'link_rules'			=> gpc_get_int_array( 'link_rules', array() ),
	);

	if ( $t_mailbox[ 'mailbox_type' ] === 'IMAP' )
	{
		$t_mailbox_imap = array(
			'imap_basefolder'				=> ERP_prepare_directory_string( gpc_get_string( 'imap_basefolder', '' ), TRUE ),
			'imap_createfolderstructure'	=> gpc_get_int( 'imap_createfolderstructure' ),
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
elseif ( ( $f_mailbox_action === 'test' || $f_mailbox_action === 'complete_test' ) && $f_select_mailbox >= 0 )
{
	# Verify mailbox - from Recmail by Cas Nuy
	plugin_require_api( 'core/mail_api.php' );

	echo '<pre>';
	$t_mailbox_api = new ERP_mailbox_api( ( ( $f_mailbox_action === 'complete_test' ) ? FALSE : TRUE ) );
	$t_result = $t_mailbox_api->process_mailbox( $t_mailbox );
	echo '</pre>';

	$t_is_custom_error = ( ( is_array( $t_result ) && isset( $t_result[ 'ERROR_TYPE' ] ) && $t_result[ 'ERROR_TYPE' ] === 'NON-PEAR-ERROR' ) || ( is_bool( $t_result ) && $t_result === FALSE ) );

	if ( $t_is_custom_error || PEAR::isError( $t_result ) || $f_mailbox_action === 'complete_test' )
	{
		$t_no_redirect = TRUE;

		html_page_top( plugin_lang_get( 'plugin_title' ) );
?>
<br /><div class="center">
<?php
		echo plugin_lang_get( ( ( $t_is_custom_error || PEAR::isError( $t_result ) ) ? 'test_failure' : 'test_success' ) ) . '<br /><br />';

		echo plugin_lang_get( 'description' ) . ': ' . $t_mailbox_api->_mailbox[ 'description' ] . '<br />';
		echo plugin_lang_get( 'mailbox_type' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_type' ] . '<br />';
		echo plugin_lang_get( 'hostname' ) . ': ', $t_mailbox_api->_mailbox[ 'hostname' ] . '<br />';
		echo plugin_lang_get( 'port' ) . ': ', $t_mailbox_api->_mailbox[ 'port' ] . '<br />';
		echo plugin_lang_get( 'encryption' ) . ': ' . $t_mailbox_api->_mailbox[ 'encryption' ] . '<br />';
		echo plugin_lang_get( 'ssl_cert_verify' ) . ': ' . $t_mailbox_api->_mailbox[ 'ssl_cert_verify' ] . '<br />';
		echo plugin_lang_get( 'erp_username' ) . ': ' . $t_mailbox_api->_mailbox[ 'erp_username' ] . '<br />';
		echo plugin_lang_get( 'erp_password' ) . ': ******' . '<br />';
		echo plugin_lang_get( 'auth_method' ) . ': ' . $t_mailbox_api->_mailbox[ 'auth_method' ] . '<br />';

		if ( $t_mailbox_api->_mailbox[ 'mailbox_type' ] === 'IMAP' )
		{
			echo plugin_lang_get( 'imap_basefolder' ) . ': ' . $t_mailbox_api->_mailbox[ 'imap_basefolder' ] . '<br />';
		}

		echo '<br />' . ( ( $t_is_custom_error ) ? nl2br( $t_result[ 'ERROR_MESSAGE' ] ) : ( ( PEAR::isError( $t_result ) ) ? $t_result->toString() : NULL ) ) . '<br /><br />';

		print_bracket_link( plugin_page( 'manage_mailbox', TRUE ), lang_get( 'proceed' ) );
?>
</div>
<?php
		html_page_bottom( __FILE__ );
	}
}

if( plugin_config_get( 'mailboxes' ) !== $t_mailboxes && ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'delete' ) && $f_select_mailbox >= 0 ) ) )
{
	plugin_config_set( 'mailboxes', $t_mailboxes );
}

if ( !isset( $t_no_redirect ) )
{
	print_successful_redirect( plugin_page( 'manage_mailbox', TRUE ) );
}
