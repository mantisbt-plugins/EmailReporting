<?php
	# Mantis - a php based bugtracking system
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------
	# Return the default mailbox options
	function ERP_get_default_mailbox()
	{
		$t_mailbox = array(
			'enabled'				=> TRUE,
			'type'					=> 'POP3',
			'encryption'			=> 'None',
			'auth_method'			=> 'USER',
			'global_category_id'	=> -1,
		);

		return( $t_mailbox );
	}

	# --------------------
	# Returns the mailbox api name. This allows other plugins to access this api though $GLOBALS[ ERP_get_mailbox_api_name() ]
	function ERP_get_mailbox_api_name()
	{
		$t_mailbox_api_index = 't_mailbox_api';

		return( $t_mailbox_api_index );
	}

	# --------------------
	# Returns the current mailbox being processed by the mailbox_api
	# By default it will only return the added fields by the plugin in question using the EVENT_ERP_OUTPUT_MAILBOX_FIELDS event
	# This function is not meant for usage within the user interface
	function ERP_get_current_mailbox( $p_mailbox_plugin_content = TRUE )
	{
		$t_mailbox_api_index = ERP_get_mailbox_api_name();

		if ( isset( $GLOBALS[ $t_mailbox_api_index ] ) && is_object( $GLOBALS[ $t_mailbox_api_index ] ) && is_array( $GLOBALS[ $t_mailbox_api_index ]->_mailbox ) )
		{
			if ( $p_mailbox_plugin_content )
			{
				if ( isset( $GLOBALS[ $t_mailbox_api_index ]->_mailbox[ 'plugin_content' ][ plugin_get_current() ] ) )
				{
					return( $GLOBALS[ $t_mailbox_api_index ]->_mailbox[ 'plugin_content' ][ plugin_get_current() ] );
				}
			}
			else
			{
				return( $GLOBALS[ $t_mailbox_api_index ]->_mailbox );
			}

			return( array() );
		}
		else
		{
			return( FALSE );
		}
	}

	# --------------------
	# Returns the complete mailbox array
	# You can also get a specific mailbox if you give a mailbox_id
	# If you wish you can also only get the plugin content
	# This is meant for plugin usage and is only available on pages where the mailbox has been retrieved already
	function ERP_get_mailboxes( $p_mailbox_id = FALSE, $p_mailbox_plugin_content = TRUE )
	{
		$t_mailboxes_index = 't_mailboxes';

		if ( isset( $GLOBALS[ $t_mailboxes_index ] ) && is_array( $GLOBALS[ $t_mailboxes_index ] ) )
		{
			if ( $p_mailbox_id !== FALSE )
			{
				if ( isset( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ] ) )
				{
					if ( $p_mailbox_plugin_content )
					{
						if ( isset( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ][ 'plugin_content' ][ plugin_get_current() ] ) )
						{
							return( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ][ 'plugin_content' ][ plugin_get_current() ] );
						}
					}
					else
					{
						return( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ] );
					}
				}
			}
			else
			{
				return( $GLOBALS[ $t_mailboxes_index ] );
			}

			return( array() );
		}
		else
		{
			return( FALSE );
		}
	}

	# --------------------
	# output the menu with the ERP menu links
	function ERP_print_menu( $p_page = '' )
	{
		$t_pages = array(
			'plugin_lang_get' => array(
				'manage_config',
				'manage_mailbox',
			),
			'lang_get' => array(
				'documentation_link'	=> 'view_readme',
				'changelog_link'		=> 'view_changelog',
			),
		);

		if( access_has_global_level( config_get( 'manage_plugin_threshold' ) ) )
		{
			echo '<div align="center"><p>';

			foreach( $t_pages AS $t_lang_function => $t_pageset )
			{
				foreach( $t_pageset AS $t_page_lang => $t_page_name )
				{
					if ( is_int( $t_page_lang ) )
					{
						$t_page_lang = $t_page_name;
					}

					$t_page = ( ( $p_page !== $t_page_name ) ? plugin_page( $t_page_name ) : NULL );

					print_bracket_link( $t_page, $t_lang_function( $t_page_lang ) );
				}
			}

			echo '</p></div>';
		}
	}

	# --------------------
	# Process a string containing a directory location
	function ERP_prepare_directory_string( $p_path, $p_imap_dir = FALSE )
	{
		$t_path = trim( str_replace( '\\', '/', $p_path ) );

		// IMAP directories can not be checked with realpath and will use the old method
		if ( $p_imap_dir )
		{
			return( rtrim( rtrim( $t_path, '/' ) ) );
		}
		else
		{
			$t_realpath = realpath( $t_path );

			if ( $t_realpath !== FALSE )
			{
				return( str_replace( '\\', '/', $t_realpath ) );
			}
			else
			{
				// Path should not exist if realpath() fails. But lets return something atleast
				return( $t_path );
			}
		}
	}

	# --------------------
	# output a configuration option
	# radio_actions type can not be used with the special $p_def_value content (-2 and -3)
	# This function is only meant to be used by the EmailReporting plugin or by other plugins within the EVENT_ERP_OUTPUT_MAILBOX_FIELDS event
	function ERP_output_config_option( $p_name, $p_type, $p_def_value = NULL, $p_variable_array = NULL, $p_options_array = NULL )
	{
		// $p_def_value has special purposes when containing the following values
		// NULL is default value
		// -1 reserved for normal use
		// -2 is for settings on the manage configurations page
		// -3 is for settings on the manage mailboxes page
		if ( $p_def_value === -2 )
		{
			$t_value = plugin_config_get( $p_name );
		}
		elseif ( $p_def_value === -3 )
		{
			$t_value = ( ( isset( $p_variable_array[ $p_name ] ) ) ? $p_variable_array[ $p_name ] : NULL );
		}
		else
		{
			$t_value = $p_def_value;
		}

		// incase we are used from within another plugin, we need to modify its name
		if ( plugin_get_current() !== 'EmailReporting' )
		{
			$t_input_name = 'plugin_content[' . plugin_get_current() . '][' . $p_name . ']';
		}
		else
		{
			$t_input_name = $p_name;
		}

		switch ( $p_type )
		{
			case 'empty':
			case 'header':
?>
<tr>
	<td class="form-title" <?php echo ( ( is_blank( $t_value ) ) ? 'width="100%" colspan="3"' : 'width="60%"' ) ?>>
		<?php echo ( ( !is_blank( $p_name ) ) ? plugin_lang_get( 'plugin_title' ) . ': ' . plugin_lang_get( $p_name ) : '&nbsp;' ) ?>
	</td>
<?php
				if ( !is_blank( $t_value ) )
				{
?>
	<td class="right" width="40%" colspan="2">
		<a href="<?php echo plugin_page( $t_value ) ?>"><?php echo plugin_lang_get( $t_value ) ?></a>
	</td>
<?php
				}
?>
</tr>
<?php
				break;

			case 'hidden':
?>
<input type="hidden" name="<?php echo $t_input_name ?>" value="<?php echo $t_value ?>"/>
<?php
				break;

			case 'radio_actions':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="center" width="100%" colspan="3">
<?php
				foreach ( $p_options_array AS $t_action_key => $t_actions )
				{
					if ( is_array( $p_variable_array ) && count( $p_variable_array ) >= $t_action_key )
					{
						foreach ( $t_actions AS $t_action )
						{
?>
		<label><input type="radio" name="<?php echo $t_input_name ?>" value="<?php echo $t_action ?>"<?php echo ( ( $t_value === $t_action ) ? ' checked="checked"' : NULL ) ?>/><?php echo plugin_lang_get( $t_action . '_action' )?></label>
<?php
						}
					}
				}
?>
	</td>
</tr>
<?php
				break;

			case 'submit':
?>
<tr>
	<td class="center" width="100%" colspan="3">
		<input type="submit" class="button" value="<?php echo plugin_lang_get( $p_name ) ?>" />
	</td>
</tr>
<?php
				break;

			case 'boolean':
			case 'directory_string':
			case 'integer':
			case 'string':
			case 'string_multiline':
			case 'string_password':
			case 'dropdown_auth_method':
			case 'dropdown_descriptions':
			case 'dropdown_descriptions_multiselect':
			case 'dropdown_global_categories':
			case 'dropdown_list_reporters':
			case 'dropdown_encryption':
			case 'dropdown_mailbox_type':
			case 'dropdown_mbstring_encodings':
			case 'dropdown_pref_usernames':
			case 'dropdown_projects':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo plugin_lang_get( $p_name )?>
	</td>
<?php
				switch ( $p_type )
				{
					case 'boolean':
?>
	<td class="center" width="20%">
		<label><input type="radio" name="<?php echo $t_input_name ?>" value="1" <?php echo ( ( ON == $t_value ) ? 'checked="checked" ' : '' )?>/><?php echo lang_get( 'yes' ) ?></label>
	</td>
	<td class="center" width="20%">
		<label><input type="radio" name="<?php echo $t_input_name ?>" value="0" <?php echo ( ( !is_null($t_value) && OFF == $t_value ) ? 'checked="checked" ' : '' ) ?>/><?php echo lang_get( 'no' ) ?></label>
	</td>
<?php
						break;

					case 'directory_string':
						$t_dir = $t_value;
						if ( is_dir( $t_dir ) )
						{
							$t_result_is_dir_color = 'positive';
							$t_result_is_dir_text = plugin_lang_get( 'directory_exists', 'EmailReporting' );

							if ( is_writable( $t_dir ) )
							{
								$t_result_is_writable_color = 'positive';
								$t_result_is_writable_text = plugin_lang_get( 'directory_writable', 'EmailReporting' );
							}
							else
							{
								$t_result_is_writable_color = 'negative';
								$t_result_is_writable_text = plugin_lang_get( 'directory_unwritable', 'EmailReporting' );
							}
						}
						else
						{
							$t_result_is_dir_color = 'negative';
							$t_result_is_dir_text = plugin_lang_get( 'directory_unavailable', 'EmailReporting' );
							$t_result_is_writable_color = NULL;
							$t_result_is_writable_text = NULL;
						}
?>
	<td class="center" width="20%">
		<input type="text" size="30" maxlength="200" name="<?php echo $t_input_name ?>" value="<?php echo $t_dir ?>"/>
	</td>
	<td class="center" width="20%">
		<span class="<?php echo $t_result_is_dir_color ?>"><?php echo $t_result_is_dir_text ?></span><br /><span class="<?php echo $t_result_is_writable_color ?>"><?php echo $t_result_is_writable_text ?></span>
	</td>
<?php
						break;

					case 'integer':
					case 'string':
?>
	<td class="center" width="40%" colspan="2">
		<input type="text" size="50" maxlength="100" name="<?php echo $t_input_name ?>" value="<?php echo $t_value ?>"/>
	</td>
<?php
						break;

					case 'string_multiline':
?>
	<td class="center" width="40%" colspan="2">
		<textarea cols="40" rows="6" name="<?php echo $t_input_name ?>"><?php
						if ( is_array( $t_value ) )
						{
							var_export( $t_value );
						}
						else
						{
							echo $t_value;
						}
?></textarea>
	</td>
<?php
						break;

					case 'string_password':
?>
	<td class="center" width="40%" colspan="2">
		<input type="password" size="50" maxlength="50" name="<?php echo $t_input_name ?>" value="<?php echo base64_decode( $t_value ) ?>"/>
	</td>
<?php
						break;

					case 'dropdown_auth_method':
						require_once( 'Net/POP3.php' );
						require_once( plugin_config_get( 'path_erp', NULL, TRUE ) . 'core/Net/IMAPProtocol_1.0.3.php' );

						$t_mailbox_connection_pop3 = new Net_POP3();
						$t_mailbox_connection_imap = new Net_IMAPProtocol();

						$t_supported_auth_methods = array_unique( array_merge( $t_mailbox_connection_pop3->supportedAuthMethods, $t_mailbox_connection_imap->supportedAuthMethods ) );
						natcasesort( $t_supported_auth_methods );
?>
	<td class="center" width="40%" colspan="2">
		<select name="<?php echo $t_input_name ?>">
<?php
						foreach ( $t_supported_auth_methods AS $t_supported_auth_method )
						{
							echo '<option' . ( ( $t_supported_auth_method === $t_value ) ? ' selected' : '' ) . '>' . $t_supported_auth_method . '</option>';
						}
?>
		</select>
	</td>
<?php
						unset( $t_mailbox_connection_pop3, $t_mailbox_connection_imap );

						break;

					case 'dropdown_descriptions':
?>
	<td class="center" width="40%" colspan="2">
<?php
						if ( is_array( $p_options_array ) && count( $p_options_array ) > 0 )
						{
?>
		<select name="<?php echo $t_input_name ?>">
<?php
							foreach ( $p_options_array AS $t_key => $t_data )
							{
								if ( !isset( $t_data[ 'enabled' ] ) )
								{
									$t_data[ 'enabled' ] = TRUE;
								}
?>
			<option value="<?php echo $t_key ?>"<?php echo ( ( $t_value === $t_key ) ? ' selected' : NULL ) ?>><?php echo ( ( $t_data[ 'enabled' ] === FALSE ) ? '* ' : NULL ) . $t_data[ 'description' ] ?></option>
<?php
							}
?>
		</select>
<?php
						}
						else
						{
							echo plugin_lang_get( 'zero_descriptions', 'EmailReporting' );
						}
?>
	</td>
<?php
						break;

					case 'dropdown_descriptions_multiselect':
?>
	<td class="center" width="40%" colspan="2">
<?php
						if ( is_array( $p_options_array ) && count( $p_options_array ) > 0 )
						{
?>
		<select name="<?php echo $t_input_name ?>[]" multiple size="6">
<?php
							foreach ( $p_options_array AS $t_key => $t_data )
							{
								if ( !isset( $t_data[ 'enabled' ] ) )
								{
									$t_data[ 'enabled' ] = TRUE;
								}
?>
			<option value="<?php echo $t_key ?>"<?php echo ( ( !is_null( $t_value ) && in_array( $t_key, $t_value ) ) ? ' selected' : NULL ) ?>><?php echo ( ( $t_data[ 'enabled' ] === FALSE ) ? '* ' : NULL ) . $t_data[ 'description' ] ?></option>
<?php
							}
?>
		</select>
<?php
						}
						else
						{
							echo plugin_lang_get( 'zero_descriptions', 'EmailReporting' );
						}
?>
	</td>
<?php
						break;

					case 'dropdown_global_categories':
?>
	<td class="center" width="40%" colspan="2">
		<select name="<?php echo $t_input_name ?>">
<?php
						print_category_option_list( $t_value, ALL_PROJECTS );

						$t_all_projects = project_get_all_rows();
						$t_projects_info = array();
						foreach( $t_all_projects AS $t_project )
						{
							$t_projects_info[ $t_project[ 'id' ] ] = $t_project[ 'name' ];
						}

						natcasesort( $t_projects_info );

						foreach( $t_projects_info AS $t_project_id => $t_project_name )
						{
							echo '<optgroup label="' . string_attribute( $t_project_name ) . '">';

							// Need to disable inherit projects for one moment.
							config_set_cache( 'subprojects_inherit_categories', OFF, CONFIG_TYPE_STRING );
							config_set_global( 'subprojects_inherit_categories', OFF );

							print_category_option_list( $t_value, $t_project_id );
							echo '</optgroup>';
						}
?>
		</select>
	</td>
<?php
						break;

					case 'dropdown_list_reporters':
?>
	<td class="center" width="40%" colspan="2">
<?php
						if ( !user_exists( $t_value ) )
						{
							echo '<span class="negative">' . plugin_lang_get( 'missing_reporter', 'EmailReporting' ) . '</span><br />';
						}
?>
		<select name="<?php echo $t_input_name ?>"><?php print_user_option_list( $t_value, ALL_PROJECTS, config_get_global( 'report_bug_threshold' ) ) ?></select>
	</td>
<?php
						break;

					case 'dropdown_encryption':
?>
	<td class="center" width="40%" colspan="2">
		<select name="<?php echo $t_input_name ?>">
<?php
						if ( extension_loaded( 'openssl' ) )
						{
							$t_socket_transports = stream_get_transports();
							$t_supported_encryptions = array( 'None', 'SSL', 'SSLv2', 'SSLv3', 'TLS' );
							foreach ( $t_supported_encryptions AS $t_encryption )
							{
								if ( $t_encryption === 'None' || in_array( strtolower( $t_encryption ), $t_socket_transports ) )
								{
?>
			<option<?php echo ( ( $t_value === $t_encryption ) ? ' selected' : NULL ) ?>><?php echo $t_encryption ?></option>
<?php
								}
							}
						}
						else
						{
?>
			<option value="None" selected class="negative"><?php echo plugin_lang_get( 'openssl_unavailable', 'EmailReporting' ) ?></option>
<?php
						}
?>
		</select>
	</td>
<?php
						break;

					case 'dropdown_mailbox_type':
						$t_mailbox_types = array( 'IMAP', 'POP3' );
?>
	<td class="center" width="40%" colspan="2">
		<select name="<?php echo $t_input_name ?>">
<?php
						foreach ( $t_mailbox_types AS $t_mailbox_type )
						{
							echo '<option' . ( ( $t_value === $t_mailbox_type ) ? ' selected' : '' ) . '>' . $t_mailbox_type . '</option>';
						}
?>
		</select>
	</td>
<?php
						break;

					case 'dropdown_mbstring_encodings':
?>
	<td class="center" width="40%" colspan="2">
			<select name="<?php echo $t_input_name ?>">
<?php
						if ( extension_loaded( 'mbstring' ) )
						{
							$t_list_encodings = mb_list_encodings();
							natcasesort( $t_list_encodings );
							foreach( $t_list_encodings AS $t_encoding )
							{
?>
			<option<?php echo ( ( $t_encoding == $t_value ) ? ' selected' : '' ) ?>><?php echo $t_encoding ?></option>
<?php
							}
						}
						else
						{
?>
			<option value="<?php echo $t_value ?>" selected class="negative"><?php echo plugin_lang_get( 'mbstring_unavailable', 'EmailReporting' ) ?></option>
<?php
						}
?>
			</select>
	</td>
<?php
						break;

					case 'dropdown_pref_usernames':
						$t_username_options = array( 'name', 'email_address', 'email_no_domain', 'from_ldap' );
?>
	<td class="center" width="40%" colspan="2">
		<select name="<?php echo $t_input_name ?>">
<?php
						foreach ( $t_username_options AS $t_option )
						{
?>
			<option value="<?php echo $t_option ?>"<?php echo ( ( $t_option == $t_value ) ? ' selected' : '' ) ?>><?php echo plugin_lang_get( $t_option ) ?></option>
<?php
						}
?>
		</select>
	</td>
<?php

						break;

					case 'dropdown_projects':
?>
	<td class="center" width="40%" colspan="2">
		<select name="<?php echo $t_input_name ?>"><?php print_project_option_list( $t_value, FALSE, NULL, FALSE ) ?></select>
	</td>
<?php
						break;

					default: echo '<tr><td colspan="3">' . plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name . ' -> level 2</td></tr>';
				}
?>
</tr>
<?php
				break;

			default: echo '<tr><td colspan="3">' . plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name . ' -> level 1</td></tr>';
		}
	}

?>
