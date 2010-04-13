<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'title' ) );

print_manage_menu( );

?>

<br/>
<form action="<?php echo plugin_page( 'maintainmailbox_edit' )?>" method="post">
<table align="center" class="width50" cellspacing="1">

<tr>
	<td class="form-title" width="60%">
		<?php echo plugin_lang_get( 'title' ) . ': ' . plugin_lang_get( 'mailbox_settings' )?>
	</td>
	<td class="right" width="40%" colspan="2">
		<a href="<?php echo plugin_page( 'config' ) ?>"><?php echo plugin_lang_get( 'config' ) ?></a>
	</td>
</tr>

<?php

$t_mailboxes = plugin_config_get( 'mailboxes' );

$f_mailbox_action = gpc_get_string( 'mailbox_action', 'add' );
$f_select_mailbox = gpc_get_int( 'select_mailbox', -1 );

$t_config_array = array(
	array(
		'name'  => 'mailbox_description',
		'type'  => 'string',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_type',
		'type'  => 'dropdown_mailbox_type',
		'value' => 'POP3',
	),
	array(
		'name'  => 'mailbox_hostname',
		'type'  => 'string',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_encryption',
		'type'  => 'dropdown_mailbox_encryption',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_username',
		'type'  => 'string',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_password',
		'type'  => 'string_password',
		'value' => '',
	),
	array(
		'name'	=> 'mailbox_auth_method',
		'type'	=> 'custom_auth_string',
		'value' => 'USER',
	),
	array(
		'name'  => 'mailbox_basefolder',
		'type'  => 'string',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_createfolderstructure',
		'type'  => 'boolean',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_project',
		'type'  => 'dropdown_projects',
		'value' => '',
	),
	array(
		'name'  => 'mailbox_global_category',
		'type'  => 'dropdown_global_categories',
		'value' => '',
	),
);

foreach( $t_config_array AS $t_config )
{
	$t_config[ 'value' ] = ( ( $f_mailbox_action !== 'add' && !empty( $t_mailboxes[ $f_select_mailbox ][ $t_config[ 'name' ] ] ) ) ? $t_mailboxes[ $f_select_mailbox ][ $t_config[ 'name' ] ] : $t_config[ 'value' ] );

	switch( $t_config['type'] )
	{
		case 'boolean':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" width="20%">
		<label><input type="radio" name="<?php echo $t_config['name'] ?>" value="1" <?php echo ( ( ON == $t_config[ 'value' ] ) ? 'checked="checked" ' : '' )?>/>
			<?php echo plugin_lang_get( 'enabled' ) ?></label>
	</td>
	<td class="center" width="20%">
		<label><input type="radio" name="<?php echo $t_config['name'] ?>" value="0" <?php echo ( ( OFF == $t_config[ 'value' ] ) ? 'checked="checked" ' : '' ) ?>/>
			<?php echo plugin_lang_get( 'disabled' ) ?></label>
	</td>
</tr>
<?php
			break;

		case 'string':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config[ 'name' ] )?>
	</td>
	<td class="center" width="40%" colspan="2">
		<label><input type="text" size="40" maxlength="50" name="<?php echo $t_config[ 'name' ] ?>" value="<?php echo $t_config[ 'value' ] ?>"/></label>
	</td>
</tr>
<?php
			break;

		case 'string_password':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config[ 'name' ] )?>
	</td>
	<td class="center" width="40%" colspan="2">
		<label><input type="password" size="40" maxlength="50" name="<?php echo $t_config[ 'name' ] ?>" value="<?php echo base64_decode( $t_config[ 'value' ] ) ?>"/></label>
	</td>
</tr>
<?php
			break;

		case 'dropdown_projects':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config[ 'name' ] )?>
	</td>
	<td class="center" width="40%" colspan="2">
		<label><select name="<?php echo $t_config[ 'name' ] ?>"> <?php print_project_option_list( $t_config[ 'value' ], false, null, true ) ?></select></label>
	</td>
</tr>
<?php
			break;

		case 'dropdown_global_categories':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config[ 'name' ] )?>
	</td>
	<td class="center" width="40%" colspan="2">
		<label><select name="<?php echo $t_config[ 'name' ] ?>"> <?php print_category_option_list( $t_config[ 'value' ], ALL_PROJECTS ) ?></select></label>
	</td>
</tr>
<?php
			break;

		case 'dropdown_mailbox_type':
			$t_mailbox_types = array( 'IMAP', 'POP3' );
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config[ 'name' ] )?>
	</td>
	<td class="center" width="40%" colspan="2">
		<label><select name="<?php echo $t_config[ 'name' ] ?>">
<?php
			foreach ( $t_mailbox_types AS $t_mailbox_type )
			{
				echo '<option' . ( ( $t_config[ 'value' ] === $t_mailbox_type ) ? ' selected' : '' ) . '>' . $t_mailbox_type . '</option>';
			}
?>
		</select></label>
	</td>
</tr>
<?php
			break;

		case 'custom_auth_string':
			require_once( 'Net/POP3.php' );
			require_once( 'Net/IMAPProtocol_1.0.3.php' );

			$t_mailbox_connection_pop3 = &new Net_POP3();
			$t_mailbox_connection_imap = &new Net_IMAPProtocol();

			$t_supported_auth_methods = array_unique( array_merge( $t_mailbox_connection_pop3->supportedAuthMethods, $t_mailbox_connection_imap->supportedAuthMethods ) );
			sort( $t_supported_auth_methods );
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" colspan="2">
		<label><select name="<?php echo $t_config['name'] ?>">
<?php
			foreach ( $t_supported_auth_methods AS $t_supported_auth_method )
			{
				echo '<option' . ( ( $t_supported_auth_method === $t_config[ 'value' ] ) ? ' selected' : '' ) . '>' . $t_supported_auth_method . '</option>';
			}
?>
		</select></label>
	</td>
</tr>
<?php
			unset( $t_mailbox_connection_pop3, $t_mailbox_connection_imap );

			break;

		case 'dropdown_mailbox_encryption':
			$t_supported_encryptions = array( 'None', 'SSL', 'SSLv2', 'SSLv3', 'TLS' );
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config[ 'name' ] )?>
	</td>
	<td class="center" width="40%" colspan="2">
		<label><select name="<?php echo $t_config[ 'name' ] ?>">
<?php
			foreach ( $t_supported_encryptions AS $t_supported_encryption )
			{
				echo '<option' . ( ( $t_config[ 'value' ] === $t_supported_encryption ) ? ' selected' : '' ) . '>' . $t_supported_encryption . '</option>';
			}
?>
		</select></label>
	</td>
</tr>
<?php
			break;
		default: echo '<tr><td colspan="3">' . plugin_lang_get( 'unknown_setting' ) . '</td></tr>';
	}
}
?>

<tr>
	<td width="60%">
		<label><input type="hidden" size="40" maxlength="50" name="mailbox_action" value="<?php echo $f_mailbox_action ?>"/></label>
	</td>
	<td width="40%" colspan="2">
		<label><input type="hidden" size="40" maxlength="50" name="select_mailbox" value="<?php echo $f_select_mailbox ?>"/></label>
	</td>
</tr>

<tr>
	<td class="center" colspan="3">
		<input type="submit" class="button" value="<?php echo plugin_lang_get( $f_mailbox_action . '_mailbox' ) ?>" />
	</td>
</tr>

</table>
</form>


<br />


<form action="<?php echo plugin_page( 'maintainmailbox' )?>" method="post">
<table align="center" class="width50" cellspacing="1">

<tr>
	<td class="form-title" width="50%">
		<?php echo plugin_lang_get( 'title' ) . ': ' . plugin_lang_get( 'mailboxes' )?>
	</td>
	<td class="right" width="50%">
		<a href="<?php echo plugin_page( 'config' ) ?>"><?php echo plugin_lang_get( 'config' ) ?></a>
	</td>
</tr>

<tr <?php echo helper_alternate_class( )?>>
	<td class="center" colspan="2">
		<label><input type="radio" name="mailbox_action" value="add"<?php echo ( ( $f_mailbox_action === 'add' ) ? ' checked="checked"' : '' ) ?>/><?php echo plugin_lang_get( 'add_mailbox' )?></label>
<?php
if ( count( $t_mailboxes ) > 0 )
{
?>
		<label><input type="radio" name="mailbox_action" value="edit"<?php echo ( ( $f_mailbox_action === 'edit' ) ? ' checked="checked"' : '' ) ?>/><?php echo plugin_lang_get( 'edit_mailbox' )?></label>
		<label><input type="radio" name="mailbox_action" value="delete"<?php echo ( ( $f_mailbox_action === 'delete' ) ? ' checked="checked"' : '' ) ?>/><?php echo plugin_lang_get( 'delete_mailbox' )?></label>
		<label><input type="radio" name="mailbox_action" value="test"<?php echo ( ( $f_mailbox_action === 'test' ) ? ' checked="checked"' : '' ) ?>/><?php echo plugin_lang_get( 'test_mailbox' )?></label>
<?php
}
?>
	</td>
</tr>

<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="50%">
		<?php echo plugin_lang_get( 'select_mailbox' )?>
	</td>
	<td class="center" width="50%">
		<label>
<?php

if ( count( $t_mailboxes ) > 0 )
{
?>
		<select name="select_mailbox">
<?php
	foreach( $t_mailboxes AS $t_mailbox_key => $t_mailbox_data )
	{
?>
		<option value="<?php echo $t_mailbox_key ?>"<?php echo ( ( $t_mailbox_key == $f_select_mailbox ) ? ' selected' : '' ) ?>><?php echo $t_mailbox_data[ 'mailbox_description' ] ?></option>
<?php
	}
?>
		</select>
<?php
}
else
{
	echo plugin_lang_get( 'zero_mailboxes' );
}
?>
		</label>
	</td>
</tr>



<tr>
	<td class="center" colspan="3">
		<input type="submit" class="button" value="<?php echo plugin_lang_get( 'select_mailbox' ) ?>" />
	</td>
</tr>

</table>
<form>
	
<?php
html_page_bottom( __FILE__ );
