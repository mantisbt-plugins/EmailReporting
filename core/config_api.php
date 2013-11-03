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
			'mailbox_type'			=> 'POP3',
			'encryption'			=> 'None',
			'auth_method'			=> 'USER',
		);

		return( $t_mailbox );
	}

	# --------------------
	# Returns the mailbox api name. This allows other plugins to access this api through $GLOBALS[ ERP_get_mailbox_api_name() ]
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

		if ( plugin_config_get( 'mail_rule_system' ) == TRUE )
		{
			$t_pages[ 'plugin_lang_get' ] = array_merge( $t_pages[ 'plugin_lang_get' ], array( 'manage_rule' ) );
		}

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
	function ERP_prepare_directory_string( $p_path, $p_no_realpath = FALSE )
	{
		$t_path = trim( str_replace( '\\', '/', $p_path ) );

		// IMAP directories can not be checked with realpath and will use the old method
		if ( $p_no_realpath )
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
	# Function does not exist yet for the plugin api
	# Based on plugin_lang_get with lang_get_defaulted functionality
	function ERP_plugin_lang_get_defaulted( $p_name, $p_basename = null )
	{
		if( $p_basename === NULL )
		{
			$t_basename = plugin_get_current();
		}
		else
		{
			$t_basename = $p_basename;
		}

		$t_name = 'plugin_' . $t_basename . '_' . $p_name;

		$t_lang = lang_get_defaulted( $t_name );

		if ( $t_name === $t_lang )
		{
			return( $p_name );
		}
		else
		{
			return( $t_lang );
		}
	}


	# --------------------
	# Return the username of the OS user account thats currently running this script
	function ERP_get_current_os_user()
	{
		if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_geteuid' ) )
		{
			$t_userinfo = posix_getpwuid( posix_geteuid() );
			return( $t_userinfo[ 'name' ] );
		}
		else
		{
			return( get_current_user() );
		}
	}

	# This prints the little [?] link for user help
	# The $p_a_name is a link into the documentation.html file
	function ERP_print_documentation_link( $p_a_name = '' )
	{
		$t_a_name = preg_replace( '/[^a-z_]/ui', '', $p_a_name );

		echo plugin_lang_get( $p_a_name ) . "\n";
		echo '<a href="http://www.mantisbt.org/wiki/doku.php/mantisbt:emailreporting#' . $t_a_name . '" target="_blank">[?]</a>';
	}

	# This overwrites a specific configuration option for the current request
	function ERP_set_temporary_overwrite( $p_config_name, $p_value )
	{
		global $g_cache_bypass_lookup;

		$g_cache_bypass_lookup[ $p_config_name ] = TRUE;
		config_set_global( $p_config_name, $p_value );
	}

	/**
	 * Copy of the function in /adm_config_set.php (MantisBT 1.2.15)
	 * See http://www.mantisbt.org/bugs/view.php?id=15832
	 */
	/**
	 * Helper function to recursively process complex types
	 * We support the following kind of variables here:
	 * 1. constant values (like the ON/OFF switches): they are defined as constants mapping to numeric values
	 * 2. simple arrays with the form: array( a, b, c, d )
	 * 3. associative arrays with the form: array( a=>1, b=>2, c=>3, d=>4 )
	 * 4. multi-dimensional arrays
	 * commas and '=>' within strings are handled
	 *
	 * @param string $p_value Complex value to process
	 * @return parsed variable
	 */
if ( !function_exists( 'process_complex_value' ) )
{
	function process_complex_value( $p_value, $p_trimquotes = false ) {
		static $s_regex_array = null;
		static $s_regex_string = null;
		static $s_regex_element = null;

		$t_value = trim( $p_value );

		# Parsing regex initialization
		if( is_null( $s_regex_array ) ) {
			$s_regex_array = '^array[\s]*\((.*)\)$';
			$s_regex_string =
				# unquoted string (word)
				'[\w]+' . '|' .
				# single-quoted string
				"'(?:[^'\\\\]|\\\\.)*'" . '|' .
				# double-quoted string
				'"(?:[^"\\\\]|\\\\.)*"';
			# The following complex regex will parse individual array elements,
			# taking into consideration sub-arrays, associative arrays and single,
			# double and un-quoted strings
			# @TODO dregad reverse pattern logic for sub-array to avoid match on array(xxx)=>array(xxx)
			$s_regex_element = '('
				# Main sub-pattern - match one of
				. '(' .
						# sub-array: ungreedy, no-case match ignoring nested parenthesis
						'(?:(?iU:array\s*(?:\\((?:(?>[^()]+)|(?1))*\\))))' . '|' .
						$s_regex_string
				. ')'
				# Optional pattern for associative array, back-referencing the
				# above main pattern
				. '(?:\s*=>\s*(?2))?' .
				')';
		}

		if( preg_match( "/$s_regex_array/s", $t_value, $t_match ) === 1 ) {
			# It's an array - process each element
			$t_processed = array();

			if( preg_match_all( "/$s_regex_element/", $t_match[1], $t_elements ) ) {
				foreach( $t_elements[0] as $key => $element ) {
					if( !trim( $element ) ) {
						# Empty element - skip it
						continue;
					}
					# Check if element is associative array
					preg_match_all( "/($s_regex_string)\s*=>\s*(.*)/", $element, $t_split );
					if( !empty( $t_split[0] ) ) {
						# associative array
						$t_new_key = constant_replace( trim( $t_split[1][0], " \t\n\r\0\x0B\"'" ) );
						$t_new_value = process_complex_value( $t_split[2][0], true );
						$t_processed[$t_new_key] = $t_new_value;
					} else {
						# regular array
						$t_new_value = process_complex_value( $element );
						$t_processed[$key] = $t_new_value;
					}
				}
			}
			return $t_processed;
		} else {
			# Scalar value
			if( $p_trimquotes ) {
				$t_value = trim( $t_value, " \t\n\r\0\x0B\"'" );
			}
			return constant_replace( $t_value );
		}
	}
}

	/**
	 * Copy of the function in /adm_config_set.php (MantisBT 1.2.15)
	 * See http://www.mantisbt.org/bugs/view.php?id=15832
	 */
	/**
	 * Check if the passed string is a constant and returns its value
	 * if yes, or the string itself if not
	 * @param $p_name string to check
	 * @return mixed|string value of constant $p_name, or $p_name itself
	 */
if ( !function_exists( 'constant_replace' ) )
{
	function constant_replace( $p_name ) {
		if( is_string( $p_name ) && defined( $p_name ) ) {
			# we have a constant
			return constant( $p_name );
		}
		return $p_name;
	}
}

	# --------------------
	# output a configuration option
	# This function is only meant to be used by the EmailReporting plugin or by other plugins within the EVENT_ERP_OUTPUT_MAILBOX_FIELDS event
	function ERP_output_config_option( $p_name, $p_type, $p_def_value = NULL, $p_function_name = NULL, $p_function_parameter = NULL )
	{
		// $p_def_value has special purposes when it contains certain values. See below
		if ( $p_def_value === NULL && !is_blank( $p_name ) && !in_array( $p_type, array( 'empty', 'header', 'submit' ), TRUE ) )
		{
			$t_value = plugin_config_get( $p_name );
		}
		// Need to catch the instance where $p_def_value is an array for dropdown_multiselect (_any)
		elseif ( is_array( $p_def_value ) &&
			(
				( !in_array( $p_type, array( 'dropdown_multiselect', 'dropdown_multiselect_any', 'custom' ), TRUE ) ) ||
				( in_array( $p_type, array( 'dropdown_multiselect', 'dropdown_multiselect_any', 'custom' ), TRUE ) &&
					(
						count( $p_def_value ) === 0 ||
						array_values( $p_def_value ) !== $p_def_value
					)
				)
			)
		)
		{
			$t_value = ( ( isset( $p_def_value[ $p_name ] ) ) ? $p_def_value[ $p_name ] : NULL );
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

		$t_input_name = string_attribute( $t_input_name );

		if ( strcasecmp( $t_input_name, 'username' ) === 0 || strcasecmp( $t_input_name, 'password' ) === 0 )
		{
			trigger_error( plugin_lang_get( 'input_name_not_allowed' ), ERROR );
		}

		$t_function_name = 'ERP_custom_function_' . $p_function_name;

		switch ( $p_type )
		{
			case 'empty':
			case 'header':
?>
<tr>
	<td class="form-title" <?php echo ( ( is_blank( $t_value ) ) ? 'width="100%" colspan="3"' : 'width="60%"' ) ?>>
		<?php echo ( ( !is_blank( $p_name ) ) ? ( ( $p_type === 'header' ) ? plugin_lang_get( 'plugin_title' ) . ': ' : NULL ) . plugin_lang_get( $p_name ) : '&nbsp;' ) ?>
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
<input type="hidden" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_value ) ?>"/>
<?php
				break;

			case 'radio_buttons':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="center" width="100%" colspan="3">
<?php
				if ( function_exists( $t_function_name ) )
				{
					$t_function_name( $t_input_name, $t_value, $p_function_parameter );
				}
				else
				{
					echo '<span class="negative">' . plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name . '</span>';
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
		<input <?php echo helper_get_tab_index() ?> type="submit" class="button" value="<?php echo plugin_lang_get( $p_name ) ?>" />
	</td>
</tr>
<?php
				break;

			case 'boolean':
			case 'directory_string':
			case 'disabled':
			case 'integer':
			case 'string':
			case 'string_multiline':
			case 'string_multiline_array':
			case 'string_password':
			case 'dropdown':
			case 'dropdown_any':
			case 'dropdown_multiselect':
			case 'dropdown_multiselect_any':
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category" width="60%">
		<?php echo ERP_print_documentation_link( $p_name ) ?>
	</td>
<?php
				switch ( $p_type )
				{
					case 'boolean':
?>
	<td class="center" width="20%">
		<label><input <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $t_input_name ?>" value="1" <?php check_checked( $t_value, ON ) ?>/><?php echo lang_get( 'yes' ) ?></label>
	</td>
	<td class="center" width="20%">
		<label><input <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $t_input_name ?>" value="0" <?php ( ( $t_value !== NULL ) ? check_checked( $t_value, OFF ) : NULL ) ?>/><?php echo lang_get( 'no' ) ?></label>
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
		<input <?php echo helper_get_tab_index() ?> type="text" size="30" maxlength="200" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_dir ) ?>"/>
	</td>
	<td class="center" width="20%">
		<span class="<?php echo $t_result_is_dir_color ?>"><?php echo $t_result_is_dir_text ?></span><br /><span class="<?php echo $t_result_is_writable_color ?>"><?php echo $t_result_is_writable_text ?></span>
	</td>
<?php
						break;

					case 'disabled':
?>
	<td class="center" width="40%" colspan="2">
		<?php echo plugin_lang_get( 'disabled' ) ?>
<?php
						ERP_output_config_option( $t_input_name, 'hidden', $t_value );
?>
	</td>
<?php
						break;

					case 'integer':
					case 'string':
?>
	<td class="center" width="40%" colspan="2">
		<input <?php echo helper_get_tab_index() ?> type="text" size="50" maxlength="100" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_value ) ?>"/>
	</td>
<?php
						break;

					case 'string_multiline':
					case 'string_multiline_array':
?>
	<td class="center" width="40%" colspan="2">
		<textarea <?php echo helper_get_tab_index() ?> cols="40" rows="6" name="<?php echo $t_input_name ?>"><?php
						if ( is_array( $t_value ) )
						{
							if ( $p_type === 'string_multiline_array' )
							{
								$t_string_array = var_export( $t_value, TRUE );
								$t_string_array = array_map( 'trim', explode( "\n", $t_string_array ) );
								array_shift( $t_string_array );
								array_pop( $t_string_array );
								$t_string_array = implode( "\n", $t_string_array );
							}
							else
							{
								$t_string_array = implode( "\n", $t_value );
							}
							echo string_textarea( $t_string_array );
						}
						else
						{
							echo string_textarea( $t_value );
						}
?></textarea>
	</td>
<?php
						break;

					case 'string_password':
?>
	<td class="center" width="40%" colspan="2">
		<input <?php echo helper_get_tab_index() ?> type="password" size="50" maxlength="50" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( base64_decode( $t_value ) ) ?>"/>
	</td>
<?php
						break;

					case 'dropdown':
					case 'dropdown_any':
					case 'dropdown_multiselect':
					case 'dropdown_multiselect_any':
?>
	<td class="center" width="40%" colspan="2">
		<select <?php echo helper_get_tab_index() ?> name="<?php echo $t_input_name . ( ( in_array( $p_type, array( 'dropdown_multiselect', 'dropdown_multiselect_any' ), TRUE ) ) ? '[]" multiple size="6' : NULL ) ?>">
<?php
						if ( function_exists( $t_function_name ) )
						{
							if ( in_array( $p_type, array( 'dropdown_any', 'dropdown_multiselect_any' ), TRUE ) )
							{
								echo '<option value="' . META_FILTER_ANY . '"';
								check_selected( $t_value, META_FILTER_ANY );
								echo '>[' . lang_get( 'any' ) . ']</option>';
							}

							$t_function_name( $t_value, $p_function_parameter );
						}
						else
						{
							echo '<option class="negative">' . plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name . '</option>';
						}
?>
		</select>
	</td>
<?php
						break;

					default: echo '<tr><td colspan="3">' . plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name . ' -> level 2</td></tr>';
				}
?>
</tr>
<?php
				break;

			case 'custom':
				if ( function_exists( $t_function_name ) )
				{
					$t_function_name( $p_name, $t_value, $p_function_parameter );
				}
				else
				{
					echo '<option class="negative">' . plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name . '</option>';
				}
				break;

			default: echo '<tr><td colspan="3">' . plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name . ' -> level 1</td></tr>';
		}
	}

	# --------------------
	# output all custom fields
	function ERP_custom_function_print_custom_fields( $p_name, $p_sel_value )
	{
		# Custom Fields
		$t_custom_fields = custom_field_get_ids();
		foreach( $t_custom_fields as $t_field_id )
		{
			$t_def = custom_field_get_definition( $t_field_id );
?>
<tr <?php echo helper_alternate_class( )?>>
	<td class="category">
		<?php echo ERP_print_documentation_link( $p_name ) . ': ' . string_display( lang_get_defaulted( $t_def[ 'name' ] ) ) ?>
	</td>
<?php
			echo '<td class="center" colspan="2">';
			ERP_print_custom_field_input( ( ( is_array( $p_sel_value ) && isset( $p_sel_value[ $t_field_id ] ) ) ? $p_sel_value[ $t_field_id ] : NULL ), $t_def );
			echo '</td></tr>';
		}
	}

	# --------------------
	# output a single custom field row
	# Based on MantisBT function print_custom_field_input
	function ERP_print_custom_field_input( $p_sel_value, $p_field_def )
	{

		if( $p_sel_value === NULL )
		{
			$t_custom_field_value = custom_field_default_to_value( $p_field_def[ 'default_value' ], $p_field_def[ 'type' ] );
		}
		else
		{
			$t_custom_field_value = $p_sel_value;
		}

		global $g_custom_field_type_definition;
		if( isset( $g_custom_field_type_definition[ $p_field_def[ 'type' ] ][ '#function_print_input' ] ) )
		{
			call_user_func( $g_custom_field_type_definition[ $p_field_def[ 'type' ] ][ '#function_print_input' ], $p_field_def, $t_custom_field_value );
		}
		else
		{
			trigger_error( ERROR_CUSTOM_FIELD_INVALID_DEFINITION, ERROR );
		}
	}

	# --------------------
	# output a option list for authentication methods for POP3 and IMAP
	function ERP_custom_function_print_auth_method_option_list( $p_sel_value )
	{
		require_once( 'Net/POP3.php' );
		require_once( 'Net/IMAPProtocol.php' );

		$t_mailbox_connection_pop3 = new Net_POP3();
		$t_mailbox_connection_imap = new Net_IMAPProtocol();

		$t_supported_auth_methods = array_unique( array_merge( $t_mailbox_connection_pop3->supportedAuthMethods, $t_mailbox_connection_imap->supportedAuthMethods ) );
		natcasesort( $t_supported_auth_methods );

		foreach ( $t_supported_auth_methods AS $t_supported_auth_method )
		{
			echo '<option';
			check_selected( $p_sel_value, $t_supported_auth_method );
			echo '>' . string_attribute( $t_supported_auth_method ) . '</option>';
		}
	}

	# --------------------
	# output a option list based on an array with an index called "description"
	function ERP_custom_function_print_descriptions_option_list( $p_sel_value, $p_options_array )
	{
		$t_options_sorted = array();
		foreach( $p_options_array AS $t_option_key => $t_option_array )
		{
			if ( !is_array( $t_option_array ) )
			{
				$t_option_key = $t_option_array;
				$t_option_array = array( 'description' => ERP_plugin_lang_get_defaulted( $t_option_array ) );
			}

			$t_options_sorted[ $t_option_key ] = $t_option_array[ 'description' ];
		}

		natcasesort( $t_options_sorted );

		foreach ( $t_options_sorted AS $t_option_key => $t_description )
		{
			echo '<option value="' . string_attribute( $t_option_key ) . '"';
			if ( ( !is_array( $p_sel_value ) && $p_sel_value !== NULL ) || ( is_array( $p_sel_value ) && !empty( $p_sel_value ) ) )
			{
				check_selected( $p_sel_value, $t_option_key );
			}
			echo '>' . ( ( isset( $p_options_array[ $t_option_key ][ 'enabled' ] ) && $p_options_array[ $t_option_key ][ 'enabled' ] === FALSE ) ? '* ' : NULL ) . string_attribute( $t_description ) . '</option>';
		}
	}

	# --------------------
	# output a option list with supported encryptions
	function ERP_custom_function_print_encryption_option_list( $p_sel_value )
	{
		if ( extension_loaded( 'openssl' ) )
		{
			$t_socket_transports = stream_get_transports();
			$t_supported_encryptions = array( 'None', 'SSL', 'SSLv2', 'SSLv3', 'TLS' );
			foreach ( $t_supported_encryptions AS $t_encryption )
			{
				if ( $t_encryption === 'None' || in_array( strtolower( $t_encryption ), $t_socket_transports, TRUE ) )
				{
?>
			<option<?php check_selected( $p_sel_value, $t_encryption ) ?>><?php echo string_attribute( $t_encryption ) ?></option>
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
	}

	# --------------------
	# output a option list with global categories available in the mantisbt system
	function ERP_custom_function_print_global_category_option_list( $p_sel_value )
	{
		// Need to disable allow_no_category for a moment
		ERP_set_temporary_overwrite( 'allow_no_category', OFF );

		// Need to disable inherit projects for one moment.
		ERP_set_temporary_overwrite( 'subprojects_inherit_categories', OFF );

		$t_sel_value = $p_sel_value;
		if ( $t_sel_value === NULL )
		{
			$t_sel_value = -1;
		}

		print_category_option_list( $t_sel_value, ALL_PROJECTS );

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
			print_category_option_list( $t_sel_value, $t_project_id );
			echo '</optgroup>';
		}
	}

	# --------------------
	# output a option list with all priorities in the MantisBT system
	function ERP_custom_function_print_priority_option_list( $p_sel_value )
	{
		print_enum_string_option_list( 'priority', $p_sel_value );
	}

	# --------------------
	# output a option list with all the projects in the MantisBT system
	# Based on MantisBT 1.2.5 function: print_project_option_list
	function ERP_custom_function_print_projects_option_list( $p_sel_value )
	{
		$t_all_projects = project_get_all_rows();

		$t_projects_sorted = array();
		foreach( $t_all_projects AS $t_project_key => $t_project )
		{
			$t_projects_sorted[ $t_project_key ] = $t_project[ 'name' ];
		}

		natcasesort( $t_projects_sorted );

		foreach ( $t_projects_sorted AS $t_project_id => $t_project_name )
		{
			echo '<option value="' . $t_all_projects[ $t_project_id ][ 'id' ] . '"';
			check_selected( $p_sel_value, $t_all_projects[ $t_project_id ][ 'id' ] );
			echo '>' . ( ( $t_all_projects[ $t_project_id ][ 'enabled' ] == FALSE ) ? '* ' : NULL ) . string_attribute( $t_all_projects[ $t_project_id ][ 'name' ] ) . '</option>' . "\n";
		}
	}

	# --------------------
	# output a option list with all users who have at least global reporter rights
	function ERP_custom_function_print_reporter_option_list( $p_sel_value )
	{
		if ( $p_sel_value !== NULL )
		{
			$t_user_ids = (array) $p_sel_value;

			foreach ( $t_user_ids AS $t_single_user_id )
			{
				if ( !user_exists( $t_single_user_id ) )
				{
					echo '<option value="' . $t_single_user_id . '" selected class="negative">' . plugin_lang_get( 'missing_reporter', 'EmailReporting' ) . '</option>';
				}
			}
		}

		print_user_option_list( $p_sel_value, ALL_PROJECTS, config_get_global( 'report_bug_threshold' ) );
	}

	# --------------------
	# output a option list with the tags currently known in the Mantis system
	# Based on MantisBT function print_tag_option_list
	function ERP_custom_function_print_tag_attach_option_list( $p_sel_value )
	{
		require_once( 'tag_api.php' );

		$t_rows = tag_get_candidates_for_bug( 0 );

		foreach ( $t_rows as $row )
		{
			$t_string = $row[ 'name' ];
			if ( !empty( $row[ 'description' ] ) )
			{
				$t_string .= ' - ' . utf8_substr( $row[ 'description' ], 0, 20 );
			}
			echo '<option value="', $row[ 'id' ], '" title="', string_attribute( $row[ 'name' ] ), '"', check_selected( $p_sel_value, $row[ 'id' ] ), '>', string_attribute( $t_string ), '</option>';
		}
	}

	# --------------------
	# output a option list based on an array with an index called "description" or a variable with a string value
	function ERP_custom_function_print_mailbox_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array )
	{
		$t_actions_list = array(
			0 => array( 'add' ),
			1 => array( 'copy', 'edit', 'delete', 'test', 'complete_test' ),
		);

		ERP_print_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array, $t_actions_list );
	}

	function ERP_custom_function_print_rule_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array )
	{
		$t_actions_list = array(
			0 => array( 'add' ),
			1 => array( 'copy', 'edit', 'delete' ),
		);

		ERP_print_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array, $t_actions_list );
	}

	function ERP_print_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array, $p_actions_list )
	{
		foreach ( $p_actions_list AS $t_action_key => $t_actions )
		{
			if ( is_array( $p_variable_array ) && count( $p_variable_array ) >= $t_action_key )
			{
				foreach ( $t_actions AS $t_action )
				{
?>
		<label><input <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $p_input_name ?>" value="<?php echo string_attribute( $t_action ) ?>"<?php check_checked( $p_sel_value, $t_action ) ?>/><?php echo plugin_lang_get( $t_action . '_action' )?></label>
<?php
				}
			}
		}
	}

?>
