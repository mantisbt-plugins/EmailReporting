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
		'enabled'                => gpc_get_int( 'enabled', ON ),
		'description'            => gpc_get_string( 'description', '' ),
		'mailbox_type'           => gpc_get_string( 'mailbox_type' ),
		'hostname'               => gpc_get_string( 'hostname', '' ),
		'port'                   => gpc_get_string( 'port', '' ),
		'encryption'             => gpc_get_string( 'encryption' ),
		'ssl_cert_verify'        => gpc_get_int( 'ssl_cert_verify', ON ),
		'auth_method'            => gpc_get_string( 'auth_method' ),
	);

	$t_isOAuth = $t_mailbox[ 'auth_method' ] === 'XOAUTH2';
	if ( !$t_isOAuth )
	{
		$t_mailbox += array(
			'erp_username'           => gpc_get_string( 'erp_username' ),
			'erp_password'           => base64_encode( gpc_get_string( 'erp_password' ) ),
		);
	}

	if ( $t_isOAuth )
	{
		$t_mailbox[ 'oauth_provider' ] = gpc_get_string( 'oauth_provider' );
		$t_isGoogle = $t_mailbox[ 'oauth_provider' ] === ERP_PROVIDER_GOOGLE;
		$t_isMicrosoft = $t_mailbox[ 'oauth_provider' ] === ERP_PROVIDER_MICROSOFT;
		if ( $t_isGoogle )
		{
			if ( empty( $t_mailbox[ 'hostname' ] ) )
			{
				$t_mailbox[ 'hostname' ] = ( ( $t_mailbox[ 'mailbox_type' ] === 'IMAP' ) ? 'imap.gmail.com' : 'pop.gmail.com' );
				$t_mailbox[ 'port' ] = '';
				$t_mailbox[ 'encryption' ] = 'SSL';
			}

			$t_mailbox += array(
				'g_mailbox'                   => gpc_get_string( 'g_mailbox' ),
				'g_serviceAccountCredentials' => ERP_prepare_directory_string( gpc_get_string( 'g_serviceAccountCredentials' ) ),
			);
		}
		elseif ( $t_isMicrosoft )
		{
			if ( empty( $t_mailbox[ 'hostname' ] ) )
			{
				$t_mailbox[ 'hostname' ] = 'outlook.office365.com';
				$t_mailbox[ 'port' ] = '';
				$t_mailbox[ 'encryption' ] = 'SSL';
			}

			$t_mailbox += array(
				'm_mailbox'           => gpc_get_string( 'm_mailbox' ),
				'm_tenantId'          => gpc_get_string( 'm_tenantId' ),
				'm_clientId'          => gpc_get_string( 'm_clientId' ),
				'm_clientSecret'      => base64_encode( gpc_get_string( 'm_clientSecret', '' ) ),
				'm_pfxPath'           => ERP_prepare_directory_string( gpc_get_string( 'm_pfxPath', '' ) ),
				'm_pfxPassword'       => base64_encode( gpc_get_string( 'm_pfxPassword', '' ) ),
			);
		}
	}

	$t_isImap = $t_mailbox[ 'mailbox_type' ] === 'IMAP';
	if ( $t_isImap )
	{
		$t_mailbox += array(
			'imap_basefolder'               => ERP_prepare_directory_string( gpc_get_string( 'imap_basefolder', '' ), TRUE ),
			'imap_createfolderstructure'    => gpc_get_int( 'imap_createfolderstructure' ),
		);
	}

	$t_mailbox += array(
		'project_id'             => gpc_get_int( 'project_id' ),
		'global_category_id'     => gpc_get_int( 'global_category_id' ),
//		'link_rules'             => gpc_get_int_array( 'link_rules', array() ),
	);

	$t_plugin_content = gpc_get_string_array( 'plugin_content', array() );

	if ( is_array( $t_plugin_content ) && !empty( $t_plugin_content ) )
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
	$t_no_redirect = TRUE;

	# Verify mailbox - from Recmail by Cas Nuy
	plugin_require_api( 'core/mail_api.php' );

	ERP_page_begin( 'manage_mailbox' );

	echo '<pre>';
	$t_mailbox_api = new ERP_mailbox_api( ( ( $f_mailbox_action === 'complete_test' ) ? FALSE : TRUE ) );
	$t_result = $t_mailbox_api->process_mailbox( $t_mailbox );
	echo '</pre>';

	$t_is_custom_error = ( ( is_array( $t_result ) && isset( $t_result[ 'ERROR_TYPE' ] ) && $t_result[ 'ERROR_TYPE' ] === 'NON-PEAR-ERROR' ) || ( is_bool( $t_result ) && $t_result === FALSE ) );
	$t_is_pear_error = ( isset( $t_result[ 'pear' ] ) && PEAR::isError( $t_result[ 'pear' ] ) );
?>
<br /><div class="center">
<?php
	$t_message = '';
	$t_message .= plugin_lang_get( ( ( $t_is_custom_error || $t_is_pear_error ) ? 'test_failure' : 'test_success' ) ) . '<br /><br />';

	$t_mailbox = $t_mailbox_api->_mailbox;
	foreach ( $t_mailbox AS $t_key => $t_value )
	{
		If ( is_array( $t_value ) )
		{
			$t_value = implode( ' - ', $t_value );
		}
		if ( $t_key === 'enabled' || $t_key === 'ssl_cert_verify' || $t_key === 'imap_createfolderstructure' )
		{
			$t_value = ( ( $t_value ) ? lang_get( 'yes' ) : lang_get( 'no' ) );
		}
		if ( $t_key === 'erp_password' || $t_key === 'm_clientSecret' || $t_key === 'm_pfxPassword' )
		{
			$t_value = '******';
		}
		if ( $t_key !== 'project_id' && $t_key !== 'global_category_id' && $t_key !== 'link_rules' )
		{
			$t_message .= plugin_lang_get( $t_key ) . ': ' . $t_value . '<br />';
		}
	}

	$t_message .= '<br />' . ( ( $t_is_custom_error ) ? nl2br( $t_result[ 'ERROR_MESSAGE' ] ) : ( ( $t_is_pear_error ) ? 'Location: ' . $t_result[ 'ERP_location' ] . '<br />' . $t_result[ 'pear' ]->toString() : NULL ) );

	if ( ( $t_is_custom_error || $t_is_pear_error ) )
	{
		html_operation_failure( plugin_page( 'manage_mailbox', TRUE ), $t_message );
	}
	else
	{
		html_operation_successful( plugin_page( 'manage_mailbox', TRUE ), $t_message );
	}
?>
</div>
<?php
	ERP_page_end( __FILE__ );
}

if( plugin_config_get( 'mailboxes' ) !== $t_mailboxes && ( $f_mailbox_action === 'add' || $f_mailbox_action === 'copy' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'delete' ) && $f_select_mailbox >= 0 ) ) )
{
	plugin_config_set( 'mailboxes', $t_mailboxes );
}

if ( !isset( $t_no_redirect ) )
{
	print_header_redirect( plugin_page( 'manage_mailbox', TRUE ) );
}
