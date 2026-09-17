<?php
# Mantis - a php based bugtracking system
# This program is distributed under the terms and conditions of the GPL
# See the README and LICENSE files for details

# Load Composer autoloader
require_once( __DIR__ . '/../vendor/autoload.php' );

# --------------------
# Return the default mailbox options
function ERP_get_default_mailbox()
{
	$t_result = ERP_select_mail_engine( plugin_config_get( 'mail_engine' ) );

	$t_mailbox_type = 'POP3';
	$t_auth_method = 'USER';

	if ( $t_result === TRUE )
	{
		$t_pop3 = new ERP_POP3_Transport();
		$t_imap = new ERP_IMAP_Transport();

		if ( empty( $t_pop3->getsupportedAuthMethods() ) )
		{
			$t_mailbox_type = 'IMAP';
			$t_auth_method = 'PLAIN';
		}
		elseif ( !in_array( $t_auth_method, $t_pop3->getsupportedAuthMethods() ) )
		{
			$t_auth_method = 'PLAIN';
		}
	}

	$t_mailbox = array(
		'enabled'               => ON,
		'mailbox_type'          => $t_mailbox_type,
		'encryption'            => 'None',
		'ssl_cert_verify'       => ON,
		'auth_method'           => $t_auth_method,
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
		if ( !$p_mailbox_plugin_content )
		{
			return( $GLOBALS[ $t_mailbox_api_index ]->_mailbox );
		}

		if ( isset( $GLOBALS[ $t_mailbox_api_index ]->_mailbox[ 'plugin_content' ][ plugin_get_current() ] ) )
		{
			return( $GLOBALS[ $t_mailbox_api_index ]->_mailbox[ 'plugin_content' ][ plugin_get_current() ] );
		}

		return( array() );
	}

	return( FALSE );
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
		if ( $p_mailbox_id === FALSE )
		{
			return( $GLOBALS[ $t_mailboxes_index ] );
		}

		if ( isset( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ] ) )
		{
			if ( !$p_mailbox_plugin_content )
			{
				return( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ] );
			}

			if ( isset( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ][ 'plugin_content' ][ plugin_get_current() ] ) )
			{
				return( $GLOBALS[ $t_mailboxes_index ][ $p_mailbox_id ][ 'plugin_content' ][ plugin_get_current() ] );
			}
		}

		return( array() );
	}

	return( FALSE );
}

# --------------------
# Select the mail engine and import the class
function ERP_select_mail_engine( $p_mail_engine )
{
	switch ( $p_mail_engine )
	{
		case 'PEAR':
			plugin_require_api( 'core/Mail/PEAR_api.php' );
			break;

		case 'DirectoryTree/ImapEngine':
			plugin_require_api( 'core/Mail/DTImapEngine_api.php' );
			break;

		case 'Horde/Imap_Client (Legacy)':
			plugin_require_api( 'core/Mail/HordeLegacy_api.php' );
			break;

		case 'Horde/Imap_Client (Modern)':
			plugin_require_api( 'core/Mail/HordeModern_api.php' );
			break;

		case 'Webklex/PHP-IMAP':
			plugin_require_api( 'core/Mail/Webklex_api.php' );
			break;

		case 'ZetaComponents/Mail':
			plugin_require_api( 'core/Mail/ZetaComponents_api.php' );
			break;

		default:
			return( 'No valid mail engine selected: ' . $p_mail_engine );
	}

	return( TRUE );
}

# --------------------
# Page header en beginning.
function ERP_page_begin( $p_page = '' )
{
	// pre-MantisBT 2.0.x
	if ( plugin_config_get( 'mantisbt_version' ) === 1 )
	{
		html_page_top( plugin_lang_get( 'plugin_title' ) );
		print_manage_menu( plugin_page( $p_page ) );
		ERP_print_menu( $p_page );
	}
	// MantisBT 2.0.x
	else
	{
		layout_page_header( plugin_lang_get( 'plugin_title' ) );
		layout_page_begin( 'manage_overview_page.php' );
		print_manage_menu( plugin_page( $p_page ) );
		ERP_print_menu( $p_page );
	}
?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<?php
}

# --------------------
# Page footer
function ERP_page_end( $p_page = '' )
{
?>
<div class="space-10"></div>
</div>
<?php
	// pre-MantisBT 2.0.x
	if ( plugin_config_get( 'mantisbt_version' ) === 1 )
	{
		html_page_bottom( $p_page );
	}
	// MantisBT 2.0.x
	else
	{
		layout_page_end();
	}
}

# --------------------
# output html table open elements
# 
# $p_data_visible_when can accept something like: [ 'mailbox_type' => [ 'IMAP', 'POP3' ] ] or [ 'mailbox_type' => [ '!=' => [ 'IMAP' ] ] ]
# 
function ERP_output_table_open( $p_headertitle = NULL, ?array $p_data_visible_when = NULL )
{
	$j_data_visible_when = json_encode( $p_data_visible_when );
?>
<div<?php echo ( ( $p_headertitle !== NULL ) ? ' id="' . $p_headertitle . '"' . ( ( $p_data_visible_when !== NULL ) ? ' data-visible-when=\'' . $j_data_visible_when . '\'' : NULL ) : NULL ) ?>>
<div class="widget-box widget-color-blue2">
<?php
	if ( $p_headertitle !== NULL )
	{
?>
<div class="widget-header widget-header-small">
	<h4 class="widget-title lighter">
		<i class="ace-icon fa fa-envelope"></i>
		<?php echo plugin_lang_get( $p_headertitle ) ?>
	</h4>
</div>
<?php
	}
?>
<div class="widget-body">
<div class="widget-main no-padding">
<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
<?php
//<div class="form-container" >
}

# --------------------
# output html table close elements
# 
function ERP_output_table_close( $p_submitbutton = NULL )
{
?>
	</table>
</div>
</div>
<?php
	if ( $p_submitbutton !== NULL )
	{
		ERP_output_config_option( $p_submitbutton, 'submit' );
	}
?>
</div>
</div>
<div class="space-10"></div>
</div>
<?php
}

# --------------------
# output html note open elements
# 
function ERP_output_note_open()
{
?>
<div class="well">
<?php
}

# --------------------
# output html note close elements
# 
function ERP_output_note_close()
{
?>
</div>
<div class="space-10"></div>
<?php
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
			'documentation_link'    => 'view_readme',
			'changelog_link'        => 'view_changelog',
		),
	);

	if ( plugin_config_get( 'mail_rule_system' ) == TRUE )
	{
		$t_pages[ 'plugin_lang_get' ] = array_merge( $t_pages[ 'plugin_lang_get' ], array( 'manage_rule' ) );
	}

	if( access_has_global_level( config_get( 'manage_plugin_threshold' ) ) )
	{
?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<div class="center">
<div class="btn-toolbar inline">
<div class="btn-group">
<?php
		foreach( $t_pages AS $t_lang_function => $t_pageset )
		{
			foreach( $t_pageset AS $t_page_lang => $t_page_name )
			{
				if ( is_int( $t_page_lang ) )
				{
					$t_page_lang = $t_page_name;
				}

				// pre-MantisBT 2.0.x
				if ( plugin_config_get( 'mantisbt_version' ) === 1 )
				{
					$t_page = ( ( $p_page !== $t_page_name ) ? plugin_page( $t_page_name ) : NULL );

					print_bracket_link( $t_page, $t_lang_function( $t_page_lang ) );
				}
				// MantisBT 2.0.x
				else
				{
					$t_active = ( ( $t_page_name === $p_page ) ? ' active' : '' );
?>
<a class="btn btn-sm btn-white btn-primary<?php echo $t_active ?>" href="<?php echo plugin_page( $t_page_name ) ?>">
<?php echo $t_lang_function( $t_page_lang ) ?>
</a>
<?php
				}
			}
		}
?>
</div>
</div>
</div>
</div>
<?php
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

// Copy of MantisBT 2.29.0 plugin_lang_get_defaulted
/**
 * Get a defaulted language string for the plugin.
 *
 * Automatically prepends `plugin_<basename>` to the string requested.
 * - If found, return the appropriate string.
 * - If not found, no default supplied, return the supplied string as is.
 * - If not found, default supplied, return default.
 * @see lang_get_defaulted()
 *
 * @param string $p_name     Language string name.
 * @param string $p_default  The default value to return.
 * @param string $p_basename Plugin basename.
 *
 * @return string Language string
 */
if ( !function_exists( 'plugin_lang_get_defaulted' ) )
{
	function plugin_lang_get_defaulted( $p_name, $p_default = NULL, $p_basename = NULL ) {
		if( !is_null( $p_basename ) ) {
			plugin_push_current( $p_basename );
		}
		$t_basename = plugin_get_current();
		$t_name = 'plugin_' . $t_basename . '_' . $p_name;
		$t_string = lang_get_defaulted( $t_name, $p_default ?? $p_name );

		if( !is_null( $p_basename ) ) {
			plugin_pop_current();
		}
		return $t_string;
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
# $p_other_description allows you to use another variable as the description of the link
function ERP_print_documentation_link( $p_a_name = '', $p_other_description = FALSE )
{
	$t_a_name = preg_replace( '/[^a-zA-Z0-9_]/u', '', $p_a_name );

	if ( $p_other_description === FALSE )
	{
		$t_description = $p_a_name;
	}
	else
	{
		$t_description = $p_other_description;
	}

	echo plugin_lang_get( $t_description );

	if ( plugin_get_current() === 'EmailReporting' )
	{
		echo ' <a href="http://www.mantisbt.org/wiki/doku.php/mantisbt:plugins:emailreporting#' . strtolower( $t_a_name ) . '" target="_blank">[?]</a>';
	}
}

# This overwrites a specific configuration option for the current request
function ERP_set_temporary_overwrite( $p_config_name, $p_value )
{
	global $g_cache_bypass_lookup;

	$g_cache_bypass_lookup[ $p_config_name ] = TRUE;
	config_set_global( $p_config_name, $p_value );
}

# --------------------
# output a configuration option
# This function is only meant to be used by the EmailReporting plugin or by other plugins within the EVENT_ERP_OUTPUT_MAILBOX_FIELDS event
# 
# @param string $p_name The name of the input variable
# @param string $p_type The type of input field that should be outputted
# @param varies $p_def_value The default value for the input field.
# - If NULL $p_name will be checked with plugin_config_get
# - If array then the index with $p_name will be used
# - If string then it will be used as-is
# @param string $p_function_name The function that should be called to handle this customized input field
# @param varies $p_function_parameter The parameter that will be passed to $p_function_name
function ERP_output_config_option( $p_name, $p_type, $p_def_value = NULL, $p_function_name = NULL, $p_function_parameter = NULL )
{
	// $p_def_value has special purposes when it contains certain values. See below
	if ( $p_def_value === NULL && !is_blank( $p_name ) && !in_array( $p_type, array( 'submit' ), TRUE ) )
	{
		$t_value = plugin_config_get( $p_name );
	}
	// Need to catch the instance where $p_def_value is an array for dropdown_multiselect (_any)
	// @TODO code verification needed for the new rule system
	elseif ( is_array( $p_def_value ) &&
		(
			// Use $p_name index when not multiselect or custom
			( !in_array( $p_type, array( 'dropdown_multiselect', 'dropdown_multiselect_any', 'custom' ), TRUE ) ) ||
			// Use $p_name index when multiselect or custom
			// and
			//     $p_def_value is empty
			//     or
			//         $p_def_value has named indexes
			//         or
			//         $p_def_value has out of order numeric indexes
			// This is because a multiselect array will use ordered numeric indexes which should stay as-is
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
		throw new RuntimeException( plugin_lang_get( 'input_name_not_allowed' ) );
		return;
	}

	$t_function_name = 'ERP_custom_function_' . $p_function_name;

	switch ( $p_type )
	{
		case 'hidden':
?>
<input id="<?php echo $t_input_name ?>" type="hidden" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_value ) ?>"/>
<?php

			break;

		case 'radio_buttons':
?>
<tr>
	<td class="center" colspan="3" class="width-100">
<?php

			if ( function_exists( $t_function_name ) )
			{
				$t_function_name( $t_input_name, $t_value, $p_function_parameter );
			}
			else
			{
?>
		<span class="red negative"><?php echo plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name ?></span>
<?php
			}

?>
	</td>
</tr>
<?php

			break;

		case 'submit':
?>
<div class="widget-toolbox clearfix center">
	<input id="<?php echo $t_input_name ?>" <?php echo helper_get_tab_index() ?> type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( $p_name ) ?>" />
</div>
<?php

			break;

		case 'boolean':
		case 'file_string':
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
<tr>
	<td class="category width-50 align-middle">
<?php
			ERP_print_documentation_link( $p_name );
?>
	</td>
<?php

			switch ( $p_type )
			{
				case 'boolean':
?>
<td class="center width-25">
	<label>
		<input id="<?php echo $t_input_name ?>" class="ace" <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $t_input_name ?>" value="<?php echo ON ?>" <?php
					check_checked( (int) $t_value, ON );
?>/><span class="lbl"><?php echo lang_get( 'yes' ) ?></span>
	</label>
</td>
<td class="center width-25">
	<label>
		<input id="<?php echo $t_input_name ?>" class="ace" <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $t_input_name ?>" value="<?php echo OFF ?>" <?php
					// NULL can also be interpreted as 0. But in this case NULL means no option chosen
					if ( $t_value !== NULL )
					{
						check_checked( (int) $t_value, OFF );
					}
?>/><span class="lbl"><?php echo lang_get( 'no' ) ?></span>
	</label>
</td>
<?php

					break;

				case 'file_string':
				case 'directory_string':
					$t_dirfile = (string) $t_value;
					$t_dirfile_type = str_replace( '_string', '', $p_type );

					if ( !empty( $t_dirfile ) )
					{
						if ( ( $t_dirfile_type === 'directory' && is_dir( $t_dirfile ) ) || ( $t_dirfile_type === 'file' && is_file( $t_dirfile ) ) )
						{
							$t_result_is_dir_color = 'green postive';
							$t_result_is_dir_text = plugin_lang_get( $t_dirfile_type . '_exists', 'EmailReporting' );

							if ( ( $t_dirfile_type === 'directory' && is_writable( $t_dirfile ) ) || ( $t_dirfile_type === 'file' && is_readable( $t_dirfile ) ) )
							{
								$t_result_is_writable_color = 'green positive';
								$t_result_is_writable_text = plugin_lang_get( $t_dirfile_type . '_access', 'EmailReporting' );
							}
							else
							{
								$t_result_is_writable_color = 'red negative';
								$t_result_is_writable_text = plugin_lang_get( $t_dirfile_type . '_notaccess', 'EmailReporting' );
							}
						}
						else
						{
							$t_result_is_dir_color = 'red negative';
							$t_result_is_dir_text = plugin_lang_get( $t_dirfile_type . '_unavailable', 'EmailReporting' );
							$t_result_is_writable_color = NULL;
							$t_result_is_writable_text = NULL;
						}
					}
					else
					{
						$t_result_is_dir_color = NULL;
						$t_result_is_dir_text = NULL;
						$t_result_is_writable_color = NULL;
						$t_result_is_writable_text = NULL;
					}
?>
<td class="width-25">
	<input id="<?php echo $t_input_name ?>" class="input-sm" <?php echo helper_get_tab_index() ?> type="text" size="64" maxlength="250" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_dirfile ) ?>"/>
</td>
<td class="width-25">
	<span class="<?php echo $t_result_is_dir_color ?>"><?php echo $t_result_is_dir_text ?></span><br />
	<span class="<?php echo $t_result_is_writable_color ?>"><?php echo $t_result_is_writable_text ?></span>
</td>
<?php

					break;

				case 'disabled':
?>
<td colspan="2" class="width-50">
<?php
					echo plugin_lang_get( 'disabled' );
					ERP_output_config_option( $t_input_name, 'hidden', $t_value );
?>
</td>
<?php

					break;

				case 'integer':
				case 'string':
?>
<td colspan="2" class="width-50">
	<input id="<?php echo $t_input_name ?>" class="input-sm" <?php echo helper_get_tab_index() ?> type="text" size="80" maxlength="100" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_value ) ?>"/>
</td>
<?php

					break;

				case 'string_multiline':
				case 'string_multiline_array':
?>
<td colspan="2" class="width-50">
	<textarea id="<?php echo $t_input_name ?>" class="form-control" <?php echo helper_get_tab_index() ?> cols="70" rows="6" name="<?php echo $t_input_name ?>"><?php

					if ( is_array( $t_value ) )
					{
						if ( $p_type === 'string_multiline_array' )
						{
							$t_string_array = var_export( $t_value, TRUE );
							$t_string_array = array_map( 'trim', explode( "\n", $t_string_array ) );
							
							// remove the array opening and closing character
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
<td colspan="2" class="width-50">
	<input id="<?php echo $t_input_name ?>" class="input-sm" <?php echo helper_get_tab_index() ?> type="password" size="80" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( base64_decode( (string) $t_value ) ) ?>"/>
</td>
<?php

					break;

				case 'dropdown':
				case 'dropdown_any':
				case 'dropdown_multiselect':
				case 'dropdown_multiselect_any':
?>
<td colspan="2" class="width-50">
<?php

					if ( function_exists( $t_function_name ) )
					{
						$t_vis_controller = FALSE;
						if ( isset( $p_function_parameter[ 'visibility-controller' ] ) && $p_function_parameter[ 'visibility-controller' ] === TRUE )
						{
							$t_vis_controller = TRUE;
							unset( $p_function_parameter[ 'visibility-controller' ] );
						}
?>
	<select id="<?php echo $t_input_name ?>" <?php echo ( ( $t_vis_controller ) ? 'data-visibility-controller ' : NULL ) ?>class="input-sm" <?php echo helper_get_tab_index() ?> name="<?php
							echo $t_input_name . ( ( in_array( $p_type, array( 'dropdown_multiselect', 'dropdown_multiselect_any' ), TRUE ) ) ? '[]" multiple size="6' : NULL );
	?>">
<?php

						if ( in_array( $p_type, array( 'dropdown_any', 'dropdown_multiselect_any' ), TRUE ) )
						{
?>
		<option value="<?php echo META_FILTER_ANY ?>" <?php check_selected( (array) $t_value, META_FILTER_ANY ) ?>>[<?php echo lang_get( 'any' ) ?>]</option>
<?php
						}

						$t_function_name( $t_value, $p_function_parameter );

?>
	</select>
<?php
					}
					else
					{
?>
	<span class="red negative"><?php echo plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name ?></span>
<?php
					}

?>
</td>
<?php

					break;

				default:
?>
<td colspan="2" class="width-50">
	<span class="red negative"><?php echo plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name ?> -> level 2</span>
</td>
<?php
			}

?>
</tr>
<?php

			break;

		case 'custom':
			if ( function_exists( $t_function_name ) )
			{
				$t_function_name( $p_name, $t_input_name, $t_value, $p_function_parameter );
			}
			else
			{
?>
<tr>
	<td colspan="3" class="width-100">
		<span class="red negative"><?php echo plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name ?></span>
	</td>
</tr>
<?php
			}
			break;

		default:
?>
<tr>
	<td colspan="3" class="width-100">
		<span class="red negative"><?php echo plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name ?> -> level 1</span>
	</td>
</tr>
<?php
	}
}

# --------------------
# output all custom fields
function ERP_custom_function_print_custom_fields( $p_name, $p_input_name, $p_sel_value )
{
	# Custom Fields
	$t_custom_fields = custom_field_get_ids();
	foreach( $t_custom_fields as $t_field_id )
	{
		$t_def = custom_field_get_definition( $t_field_id );

?>
<tr>
	<td class="category" class="width-50">
<?php
		ERP_print_documentation_link( $p_name );
		echo ': ' . string_display( lang_get_defaulted( $t_def[ 'name' ] ) );
?>
	</td>
	<td colspan="2" class="width-50">
<?php
		ERP_print_custom_field_input( ( ( is_array( $p_sel_value ) && isset( $p_sel_value[ $t_field_id ] ) ) ? $p_sel_value[ $t_field_id ] : NULL ), $t_def );
?>
	</td>
</tr>
<?php
	}
}

# --------------------
# output a single custom field row
# Based on MantisBT 2.28.4 function print_custom_field_input
function ERP_print_custom_field_input( $p_sel_value, $p_field_def )
{
	if( $p_sel_value === NULL )
	{
		$t_custom_field_value = '';//custom_field_default_to_value( $p_field_def[ 'default_value' ], $p_field_def[ 'type' ] );
	}
	else
	{
		$t_custom_field_value = $p_sel_value;
	}

	global $g_custom_field_type_definition;
	if( isset( $g_custom_field_type_definition[ $p_field_def[ 'type' ] ][ '#function_print_input' ] ) )
	{
		call_user_func( $g_custom_field_type_definition[ $p_field_def[ 'type' ] ][ '#function_print_input' ], $p_field_def, $t_custom_field_value );
		print_hidden_input( custom_field_presence_field_name( $p_field_def['id'] ), '1' );
	}
	else
	{
		throw new InvalidArgumentException( ERROR_CUSTOM_FIELD_INVALID_DEFINITION );
	}
}

# --------------------
# output a option list for authentication methods for POP3 and IMAP
function ERP_custom_function_print_auth_method_option_list( $p_sel_value )
{
	$t_result = ERP_select_mail_engine( plugin_config_get( 'mail_engine' ) );

	if ( $t_result !== TRUE )
	{
		echo '<option>' . $t_result . '</option>';
		return;
	}

	$t_pop3 = new ERP_POP3_Transport();
	$t_imap = new ERP_IMAP_Transport();

	$t_supported_auth_methods = array(
		'BOTH' => array_intersect( $t_pop3->getsupportedAuthMethods(), $t_imap->getsupportedAuthMethods() ),
		'IMAP' => array_diff( $t_imap->getsupportedAuthMethods(), $t_pop3->getsupportedAuthMethods() ),
		'POP3' => array_diff( $t_pop3->getsupportedAuthMethods(), $t_imap->getsupportedAuthMethods() ),
	);

	if ( !empty( $p_sel_value ) && !in_array( $p_sel_value, $t_imap->getsupportedAuthMethods(), TRUE ) && !in_array( $p_sel_value, $t_pop3->getsupportedAuthMethods(), TRUE ) )
	{
		$t_supported_auth_methods[ 'NOT FOUND' ][] = $p_sel_value;
	}

	foreach ( $t_supported_auth_methods AS $t_key => $t_auth_methods )
	{
		if ( !empty( $t_auth_methods ) )
		{
			if ( $t_key !== 'BOTH' )
			{
				echo '<optgroup label="' . string_attribute( $t_key ) . '">';
			}

			natcasesort( $t_auth_methods );

			foreach ( $t_auth_methods AS $t_auth_method )
			{
				echo '<option';
				check_selected( (string) $p_sel_value, $t_auth_method );
				if ( $t_key === 'NOT FOUND' )
				{
					echo ' class="red negative"';
				}
				echo '>' . string_attribute( $t_auth_method ) . '</option>';
			}

			if ( $t_key !== 'BOTH' )
			{
				echo '</optgroup>';
			}
		}
	}
}

# --------------------
# output a option list based on an array with an index called "description"
function ERP_custom_function_print_descriptions_option_list( $p_sel_value, $p_options_array )
{
	$t_sel_value = (array) $p_sel_value;

	$t_options_sorted = array();
	foreach( $p_options_array AS $t_option_key => $t_option_array )
	{
		if ( !is_array( $t_option_array ) )
		{
			$t_option_key = $t_option_array;
			// Need to supply a default as plugin_lang_get_defaulted had an issue which was fixed in 2.29.0
			$t_option_array = array( 'description' => plugin_lang_get_defaulted( $t_option_array, $t_option_array ) );
		}

		$t_options_sorted[ $t_option_key ] = $t_option_array[ 'description' ];
	}

	natcasesort( $t_options_sorted );

	foreach ( $t_sel_value AS $t_value )
	{
		if ( !isset( $t_options_sorted[ $t_value ] ) )
		{
			$t_options_sorted[ $t_value ] = $t_value;
			$t_not_found[ $t_value ] = TRUE;
		}
	}

	foreach ( $t_options_sorted AS $t_option_key => $t_description )
	{
		$t_enabled = ( ( isset( $p_options_array[ $t_option_key ][ 'enabled' ] ) ) ? $p_options_array[ $t_option_key ][ 'enabled' ] : TRUE );
		echo '<option value="' . string_attribute( $t_option_key ) . '"';
		check_selected( $t_sel_value, $t_option_key );
		if ( isset( $t_not_found[ $t_option_key ] ) )
		{
			echo ' class="red negative"';
		}
		echo '>' . ( ( $t_enabled == FALSE ) ? '* ' : NULL ) . string_attribute( $t_description ) . '</option>';
	}
}

# --------------------
# Output a duedate adjuster
function ERP_custom_function_print_duedate( $p_name, $p_input_name, $p_sel_value )
{
	if ( !is_array( $p_sel_value ) )
	{
		$t_sel_value[ 'modifier' ] = '';
		$t_sel_value[ 'amount' ] = '';
	}
	else
	{
		$t_sel_value = $p_sel_value;
	}
?>
<tr>
	<td class="category" class="width-50">
<?php
	ERP_print_documentation_link( $p_name );
?>
	</td>
	<td colspan="2" class="width-50">
		<select id="<?php echo $p_input_name ?>_modifier" class="input-sm" <?php echo helper_get_tab_index() ?> name="<?php echo $p_input_name . '[modifier]'; ?>">
<?php
	$t_options = array( '', 'current', 'now' );
	foreach ( $t_options AS $t_option )
	{
		echo '<option value="' . string_attribute( $t_option ) . '"';
		check_selected( $t_sel_value[ 'modifier' ], $t_option );
		echo '>' . string_attribute( $t_option ) . '</option>';
	}
?>
		</select>
		+
		<input id="<?php echo $p_input_name ?>_amount" class="input-sm" <?php echo helper_get_tab_index() ?> type="text" size="20" maxlength="10" name="<?php echo $p_input_name ?>[amount]" value="<?php echo string_attribute( $t_sel_value[ 'amount' ] ) ?>"/>
	</td>
</tr>
<?php
}

# --------------------
# output a option list with supported encryptions
function ERP_custom_function_print_encryption_option_list( $p_sel_value )
{
	if ( extension_loaded( 'openssl' ) )
	{
		$t_socket_transports = array_intersect( stream_get_transports(), array( 'ssl', 'tls' ) );
		$t_supported_encryptions = array_map( 'strtoupper', $t_socket_transports );
		array_unshift( $t_supported_encryptions, 'None' );
		if ( function_exists( 'stream_socket_enable_crypto' ) )
		{
			array_push( $t_supported_encryptions, 'STARTTLS' );
		}

		if ( !empty( $p_sel_value ) && !in_array( $p_sel_value, $t_supported_encryptions, TRUE ) )
		{
			$t_supported_encryptions[ 'NOT FOUND' ] = $p_sel_value;
		}

		foreach ( $t_supported_encryptions AS $t_key => $t_encryption )
		{
			echo '<option';
			check_selected( (string) $p_sel_value, $t_encryption );
			if ( $t_key === 'NOT FOUND' )
			{
				echo ' class="red negative"';
			}
			echo '>' . string_attribute( $t_encryption ) . '</option>';
		}
	}
	else
	{
		echo '<option value="None" selected class="red negative">' . plugin_lang_get( 'openssl_unavailable', 'EmailReporting' ) . '</option>';
	}
}

# --------------------
# output a option list with global categories available in the mantisbt system
function ERP_custom_function_print_global_category_option_list( $p_sel_value )
{
	// Fix for older MantisBT pre 2.27 versions
	// Set to 1 because old mantisbt would return 0 as enabled
	if ( !defined( "CATEGORY_STATUS_DISABLED" ) )
	{
		define( "CATEGORY_STATUS_DISABLED", 1 );
	}

	$t_sel_values = (array) $p_sel_value;

	foreach ( $t_sel_values AS $t_sel_value )
	{
		if ( !category_exists( $t_sel_value ) )
		{
			$t_error = lang_get( 'MANTIS_ERROR' );
			echo '<option value="' . $t_sel_value . '" selected class="red negative">';
			echo string_attribute( $t_error[ ERROR_CATEGORY_NOT_FOUND ] ), ': ' . $t_sel_value . '</option>', PHP_EOL;
		}
	}

	$t_all_projects = project_get_all_rows();
	$t_projects_info = array( ALL_PROJECTS => "" );
	foreach( $t_all_projects AS $t_project )
	{
		$t_projects_info[ $t_project[ 'id' ] ] = $t_project[ 'name' ];
	}

	natcasesort( $t_projects_info );

	foreach( $t_projects_info AS $t_project_id => $t_project_name )
	{
		if ( $t_project_id !== 0 )
		{
			echo '<optgroup label="' . string_attribute( $t_project_name ) . '">';
		}

		$t_cat_arr = category_get_all_rows( $t_project_id, FALSE, FALSE, FALSE );
		foreach( $t_cat_arr as $t_category_row )
		{
			$t_category_id = (int) $t_category_row[ 'id' ];
			$t_disabled = $t_category_row[ 'status' ] == CATEGORY_STATUS_DISABLED;
			$t_category_name = category_full_name( $t_category_id, FALSE );
			echo '<option value="' . $t_category_id . '"';
			check_selected( $t_sel_values, $t_category_id );
			if ( $t_disabled )
			{
				$t_category_name = '* ' . $t_category_name;
			}
			echo '>';
			echo string_attribute( $t_category_name ), '</option>', PHP_EOL;
		}

		if ( $t_project_id !== 0 )
		{
			echo '</optgroup>';
		}
	}
}

# --------------------
# output a option list with all priorities in the MantisBT system
function ERP_custom_function_print_priority_option_list( $p_sel_value )
{
	print_enum_string_option_list( 'priority', (array) $p_sel_value );
}

# --------------------
# output a option list with all the projects in the MantisBT system
# Based on MantisBT 2.28.4 function: print_project_option_list
function ERP_custom_function_print_projects_option_list( $p_sel_value )
{
	$t_sel_values = (array) $p_sel_value;

	$t_all_projects = project_get_all_rows();

	$t_projects_sorted = array();
	foreach( $t_all_projects AS $t_project_key => $t_project )
	{
		if ( project_hierarchy_is_toplevel( $t_project[ 'id' ], TRUE ) )
		{
			$t_projects_sorted[ $t_project_key ] = $t_project[ 'name' ];
		}
	}

	natcasesort( $t_projects_sorted );

	foreach ( $t_projects_sorted AS $t_key => $t_project_name )
	{
		$t_project_id = (int) $t_all_projects[ $t_key ][ 'id' ];
		$t_disabled = $t_all_projects[ $t_key ][ 'enabled' ] == FALSE;
		echo '<option value="' . $t_project_id . '"';
		check_selected( $t_sel_values, $t_project_id );
		if ( $t_disabled )
		{
			$t_project_name = '* ' . $t_project_name;
		}
		echo '>' . string_attribute( $t_project_name ) . '</option>' . "\n";
		print_subproject_option_list( $t_project_id );
	}
}

# --------------------
# output a option list with all users who have at least global reporter rights
function ERP_custom_function_print_reporter_option_list( $p_sel_value )
{
	$t_user_ids = (array) $p_sel_value;

	if ( !empty( $t_user_ids ) )
	{
		foreach ( $t_user_ids AS $t_single_user_id )
		{
			$t_user_exists = user_exists( $t_single_user_id );

			if ( $t_user_exists )
			{
				$t_user_get_accessible_projects = user_get_accessible_projects( $t_single_user_id );
			}

			if ( !$t_user_exists || empty( $t_user_get_accessible_projects ) )
			{
				echo '<option value="' . $t_single_user_id . '" selected class="red negative">' . plugin_lang_get( 'missing_user', 'EmailReporting' ) . ': ' . $t_single_user_id . '</option>';
			}
		}
	}

	print_user_option_list( $t_user_ids, ALL_PROJECTS );
}

# --------------------
# output a option list with all priorities in the MantisBT system
function ERP_custom_function_print_severity_option_list( $p_sel_value )
{
	print_enum_string_option_list( 'severity', (array) $p_sel_value );
}

# --------------------
# output a option list with all priorities in the MantisBT system
function ERP_custom_function_print_status_option_list( $p_sel_value )
{
	print_enum_string_option_list( 'status', (array) $p_sel_value );
}

# --------------------
# output a option list with the tags currently known in the Mantis system
# Based on MantisBT 2.28.4 function print_tag_option_list
function ERP_custom_function_print_tag_option_list( $p_sel_value )
{
	require_api( 'tag_api.php' );

	$t_rows = tag_get_candidates_for_bug( 0 );

	foreach ( $t_rows as $t_row )
	{
		echo '<option value="', $t_row[ 'id' ], '" title="', string_attribute( $t_row[ 'description' ] ), '"';
		check_selected( (array) $p_sel_value, (int) $t_row[ 'id' ] );
		echo '>', string_attribute( $t_row[ 'name' ] ), '</option>';
	}
}

# --------------------
# output a option list with all priorities in the MantisBT system
function ERP_custom_function_print_view_state_option_list( $p_sel_value )
{
	print_enum_string_option_list( 'view_state', (array) $p_sel_value );
}

# --------------------
# output a option list based on an array with an index called "description" or a variable with a string value
function ERP_custom_function_print_mailbox_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array )
{
	$t_actions_list = array(
		0 => array( 'add' ),
		1 => array( 'copy', 'edit', 'delete', 'test', 'complete_test' ),
	);

	ERP_print_action_radio_buttons( $p_input_name, (string) $p_sel_value, $p_variable_array, $t_actions_list );
}

function ERP_custom_function_print_rule_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array )
{
	$t_actions_list = array(
		0 => array( 'add' ),
		1 => array( 'copy', 'edit', 'delete', 'describe' ),
	);

	ERP_print_action_radio_buttons( $p_input_name, (string) $p_sel_value, $p_variable_array, $t_actions_list );
}

function ERP_print_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array, $p_actions_list )
{
	echo '<table border=0 class="width-100"><tr>';

	foreach ( $p_actions_list AS $t_action_key => $t_actions )
	{
		if ( is_array( $p_variable_array ) && count( $p_variable_array ) >= $t_action_key )
		{
			foreach ( $t_actions AS $t_action )
			{
				echo '<td><label><input id="' . $p_input_name . '" class="ace" ' . helper_get_tab_index() . ' type="radio" name="' . $p_input_name . '" value="' . string_attribute( $t_action ) . '"';
				check_checked( $p_sel_value, $t_action );
				echo '/><span class="lbl">' . plugin_lang_get( $t_action . '_action' ) . '</span></label></td>';
			}
		}
	}

	echo '</tr></table>';
}

?>
