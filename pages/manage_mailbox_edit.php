<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_mailbox_action = gpc_get_string( 'mailbox_action' );
$f_select_mailbox = gpc_get_int( 'select_mailbox' );

$t_mailboxes = plugin_config_get( 'mailboxes' );

if ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'test' ) && $f_select_mailbox >= 0 ) )
{
	$t_mailbox = array(
		'mailbox_description'		=> gpc_get_string( 'mailbox_description' ),
		'mailbox_type'				=> gpc_get_string( 'mailbox_type' ),
		'mailbox_hostname'			=> gpc_get_string_array( 'mailbox_hostname' ),
		'mailbox_encryption'		=> gpc_get_string( 'mailbox_encryption' ),
		'mailbox_username'			=> gpc_get_string( 'mailbox_username' ),
		'mailbox_password'			=> base64_encode( gpc_get_string( 'mailbox_password' ) ),
		'mailbox_auth_method'		=> gpc_get_string( 'mailbox_auth_method' ),
		'mailbox_project'			=> gpc_get_int( 'mailbox_project' ),
		'mailbox_global_category'	=> gpc_get_int( 'mailbox_global_category' ),
	);

	if ( $t_mailbox[ 'mailbox_type' ] === 'IMAP' )
	{
		$t_mailbox_imap = array(
			'mailbox_basefolder'			=> trim( str_replace( '\\', '/', gpc_get_string( 'mailbox_basefolder' ) ), '/ ' ),
			'mailbox_createfolderstructure'	=> gpc_get_bool( 'mailbox_createfolderstructure' ),
		);

		$t_mailbox += $t_mailbox_imap;
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

		html_page_top( plugin_lang_get( 'title' ) );
?>
<br /><div class="center">
<?php
		echo plugin_lang_get( 'test_failure' ) . '<br /><br />';
		echo plugin_lang_get( 'mailbox_description' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_description' ] . '<br />';
		echo plugin_lang_get( 'mailbox_type' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_type' ] . '<br />';
		echo plugin_lang_get( 'mailbox_hostname' ) . ': ' . implode( ' (', $t_mailbox_api->_mailbox[ 'mailbox_hostname' ] ) . ')<br />';
		echo plugin_lang_get( 'mailbox_encryption' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_encryption' ] . '<br />';
		echo plugin_lang_get( 'mailbox_username' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_username' ] . '<br />';
		echo plugin_lang_get( 'mailbox_password' ) . ': ******' . '<br />';
		echo plugin_lang_get( 'mailbox_auth_method' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_auth_method' ] . '<br />';

		if ( $t_mailbox[ 'mailbox_type' ] === 'IMAP' )
		{
			echo plugin_lang_get( 'mailbox_basefolder' ) . ': ' . $t_mailbox_api->_mailbox[ 'mailbox_basefolder' ] . '<br />';
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
