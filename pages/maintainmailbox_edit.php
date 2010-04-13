<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$f_mailbox_action = gpc_get_string( 'mailbox_action', 'add' );
$f_select_mailbox = gpc_get_int( 'select_mailbox', -1 );

$t_mailboxes = plugin_config_get( 'mailboxes' );

if ( $f_mailbox_action === 'add' || ( ( $f_mailbox_action === 'edit' || $f_mailbox_action === 'test' ) && $f_select_mailbox >= 0 ) )
{
	$t_mailbox = array(
		'mailbox_description'		=> gpc_get_string( 'mailbox_description' ),
		'mailbox_hostname'			=> gpc_get_string( 'mailbox_hostname' ),
		'mailbox_username'			=> gpc_get_string( 'mailbox_username' ),
		'mailbox_password'			=> base64_encode( gpc_get_string( 'mailbox_password' ) ),
		'mailbox_project'			=> gpc_get_int( 'mailbox_project' ),
		'mailbox_global_category'	=> gpc_get_int( 'mailbox_global_category' ),
	);
}

if ( $f_mailbox_action === 'add' )
{
	array_push( $t_mailboxes, $t_mailbox );
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
	require_once( 'mail_api.php' );

	$t_result = mail_process_all_mails( $t_mailbox, true );

	if ( PEAR::isError( $t_result ) )
	{
		$t_no_redirect = true;

		html_page_top1();
		html_page_top2();
?>
<br /><div class="center">
		<?php echo plugin_lang_get( 'test_failure' ); ?>
	<br><br>
		<?php echo plugin_lang_get( 'mailbox_description' ) . ': ' . $t_mailbox[ 'mailbox_description' ]; ?>
	<br>
		<?php echo plugin_lang_get( 'mailbox_hostname' ) . ': ' . $t_mailbox[ 'mailbox_hostname' ]; ?>
	<br>
		<?php echo plugin_lang_get( 'mailbox_username' ) . ': ' . $t_mailbox[ 'mailbox_username' ]; ?>
	<br>
		<?php echo plugin_lang_get( 'mailbox_password' ) . ': ******'; ?>
	<br><br>
		<?php echo $t_result->toString(); ?>
	<br><br>
		<?php print_bracket_link( plugin_page( 'maintainmailbox', TRUE ), lang_get( 'proceed' ) ); ?>
</div>
<?php
		html_page_bottom1(); 
	}
}

if( plugin_config_get( 'mailboxes' ) != $t_mailboxes && ( $f_mailbox_action === 'add' || ( $f_mailbox_action === 'edit' && $f_select_mailbox >= 0 ) ) ) {
	plugin_config_set( 'mailboxes', $t_mailboxes );
}

if ( !isset( $t_no_redirect ) )
{
	print_successful_redirect( plugin_page( 'maintainmailbox', true ) );
}
