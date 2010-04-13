<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

html_page_top( plugin_lang_get( 'title' ) );

print_manage_menu( );

?>

<br/>
<table align="center" class="width50" cellspacing="1">

<tr>
	<td class="left">
		<?php echo plugin_lang_get( 'jobsetup' ) . '<br />' . plugin_lang_get( 'job1' ) . '<a href="plugins/' . plugin_get_current() . '/scripts/bug_report_mail.php">/plugins/' . plugin_get_current() . '/scripts/bug_report_mail.php</a>' . '<br />' . plugin_lang_get( 'job2' ) . '<a href="' . plugin_page( 'bug_report_mail' ) . '">/' . plugin_page( 'bug_report_mail', true ) . '</a>' ?>
	</td>
</tr>

</table>
<br />

<form action="<?php echo plugin_page( 'config_edit' )?>" method="post">
<table align="center" class="width50" cellspacing="1">

<tr>
	<td class="form-title">
		<?php echo plugin_lang_get( 'title' ) . ': ' . plugin_lang_get( 'config' )?>
	</td>
	<td class="right" colspan="2">
		<a href="<?php echo plugin_page( 'maintainmailbox' ) ?>"><?php echo plugin_lang_get( 'mailbox_settings' ) ?></a>
	</td>
</tr>

<tr>
	<td class="form-title" colspan="3">
		<?php echo plugin_lang_get( 'problems' ) ?>
	</td>
</tr>

<?php
$t_config_array = array(
	array(
		'name' => 'mail_secured_script',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_use_reporter',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_reporter',
		'type' => 'custom_mail_reporter',
	),
	array(
		'name' => 'mail_auto_signup',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_fetch_max',
		'type' => 'integer',
	),
	array(
		'name' => 'mail_add_complete_email',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_save_from',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_parse_mime',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_parse_html',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_identify_reply',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_tmp_directory',
		'type' => 'directory_string',
	),
	array(
		'name' => 'mail_delete',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_debug',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_directory',
		'type' => 'directory_string',
	),
	array(
		'name' => 'mail_nosubject',
		'type' => 'string',
	),
	array(
		'name' => 'mail_nodescription',
		'type' => 'string',
	),
	array(
		'name' => 'mail_use_bug_priority',
		'type' => 'boolean',
	),
	array(
		'name' => 'mail_bug_priority_default',
		'type' => 'custom_priority_integer',
	),
	array(
		'name' => 'mail_bug_priority',
		'type' => 'array',
	),
	array(
		'name' => 'mail_encoding',
		'type' => 'custom_mail_encoding',
	),
);

foreach( $t_config_array AS $t_config )
{
	switch( $t_config['type'] )
	{
		case 'boolean':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" width="20%">
		<label><input type="radio" name="<?php echo $t_config['name'] ?>" value="1" <?php echo( ON == plugin_config_get( $t_config['name'] ) ) ? 'checked="checked" ' : ''?>/>
			<?php echo plugin_lang_get( 'enabled' )?></label>
	</td>
	<td class="center" width="20%">
		<label><input type="radio" name="<?php echo $t_config['name'] ?>" value="0" <?php echo( OFF == plugin_config_get( $t_config['name'] ) ) ? 'checked="checked" ' : ''?>/>
			<?php echo plugin_lang_get( 'disabled' )?></label>
	</td>
</tr>
<?php
			break;

		case 'integer':
		case 'string':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" colspan="2">
		<label><input type="text" size="30" maxlength="200" name="<?php echo $t_config['name'] ?>" value="<?php echo plugin_config_get( $t_config['name'] )?>"/></label>
	</td>
</tr>
<?php
			break;

		case 'directory_string':
			$t_dir = plugin_config_get( $t_config['name'] );
			if ( is_dir( $t_dir ) )
			{
				$t_result_is_dir_color = 'positive';
				$t_result_is_dir_text = plugin_lang_get( 'directory_exists' );

				if ( is_writable( $t_dir ) )
				{
					$t_result_is_writable_color = 'positive';
					$t_result_is_writable_text = plugin_lang_get( 'directory_writable' );
				}
				else
				{
					$t_result_is_writable_color = 'negative';
					$t_result_is_writable_text = plugin_lang_get( 'directory_unwritable' );
				}
			}
			else
			{
				$t_result_is_dir_color = 'negative';
				$t_result_is_dir_text = plugin_lang_get( 'directory_unavailable' );
				$t_result_is_writable_color = null;
				$t_result_is_writable_text = null;
			}
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" width="20%">
		<label><input type="text" size="20" maxlength="200" name="<?php echo $t_config['name'] ?>" value="<?php echo $t_dir ?>"/></label>
	</td>
	<td class="center" width="20%">
		<label><span class="<?php echo $t_result_is_dir_color ?>"><?php echo $t_result_is_dir_text ?></span><br /><span class="<?php echo $t_result_is_writable_color ?>"><?php echo $t_result_is_writable_text ?></span></label>
	</td>
</tr>
<?php
			break;

		case 'custom_priority_integer':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" colspan="2">
		<label><select name="<?php echo $t_config['name'] ?>">
			<?php print_enum_string_option_list( 'priority', plugin_config_get( $t_config['name'] ) ) ?>
		</select></label>
	</td>
</tr>
<?php
			break;		

		case 'array':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" colspan=2>
		<label><textarea cols="35" rows="5" name="<?php echo $t_config['name'] ?>"><?php var_export( plugin_config_get( $t_config['name'] ) ) ?></textarea></label>
	</td>
</tr>
<?php
			break;		

		case 'custom_mail_encoding':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" colspan="2">
		<label>
<?php
			if ( extension_loaded( 'mbstring' ) )
			{
?>
			<select name="<?php echo $t_config['name'] ?>">
<?php
				$t_list_encodings = mb_list_encodings();
				foreach( $t_list_encodings AS $t_encoding )
				{
?>
			<option<?php echo ( ( $t_encoding == plugin_config_get( $t_config['name'] ) ) ? ' selected' : '' ) ?>><?php echo $t_encoding ?></option>
<?php
				}
?>
			</select>
<?php
			}
			else
			{
				echo plugin_lang_get( 'mbstring_unavailable' );
			}
?>
		</label>
	</td>
</tr>
<?php
			break;		

		case 'custom_mail_reporter':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo plugin_lang_get( $t_config['name'] )?>
	</td>
	<td class="center" colspan=2>
		<label><select name="<?php echo $t_config['name'] ?>">
<?php
			$t_reporter_id = user_get_id_by_name( plugin_config_get( 'mail_reporter' ) );

			if ( $t_reporter_id === false )
			{
				echo '<option value="">' . plugin_lang_get( 'missing_reporter' ) . '</option>';
			}
			print_user_option_list( $t_reporter_id, ALL_PROJECTS, config_get_global( 'report_bug_threshold' ) );
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
	<td class="center" colspan="3">
		<input type="submit" class="button" value="<?php echo lang_get( 'change_configuration' ) ?>" />
	</td>
</tr>

</table>
</form>

<?php
html_page_bottom( __FILE__ );
