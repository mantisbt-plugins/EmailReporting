<?php
	# Mantis - a php based bugtracking system
	# This program is distributed under the terms and conditions of the GPL
	# See the README and LICENSE files for details

	# --------------------
	# Return the default mailbox options
	function ERP_get_default_mailbox()
	{
		$t_mailbox = array(
			'enabled'				=> ON,
			'mailbox_type'			=> 'POP3',
			'encryption'			=> 'None',
			'ssl_cert_verify'		=> ON,
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
	function ERP_output_table_open( $p_headertitle = NULL )
	{
?>
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
		echo ' <a href="http://www.mantisbt.org/wiki/doku.php/mantisbt:plugins:emailreporting#' . $t_a_name . '" target="_blank">[?]</a>';
	}

	# This overwrites a specific configuration option for the current request
	function ERP_set_temporary_overwrite( $p_config_name, $p_value )
	{
		global $g_cache_bypass_lookup;

		$g_cache_bypass_lookup[ $p_config_name ] = TRUE;
		config_set_global( $p_config_name, $p_value );
	}

	/**
	 * Copy of the function in /admin/check/check_api.php (MantisBT 2.25)
	 * ERP - Removed the global variable
	 * ERP - Changed output method
	 */
	/**
	 * Print Check Test Result
	 * @param integer $p_result One of BAD|GOOD|WARN.
	 * @return void
	 */
	function ERP_check_print_test_result( $p_result ) {
		$t_output = NULL;

		switch( $p_result ) {
			case BAD:
				$t_output .= "\t\t" . '<td class="alert alert-danger">FAIL</td>' . "\n";
				break;
			case GOOD:
				$t_output .= "\t\t" . '<td class="alert alert-success">PASS</td>' . "\n";
				break;
			case WARN:
				$t_output .= "\t\t" . '<td class="alert alert-warning">WARN</td>' . "\n";
				break;
		}

		return $t_output;
	}

	/**
	 * Copy of the function in /admin/check/check_api.php (MantisBT 2.25)
	 * ERP - Changed global $g_showall to local variable and forced it to FALSE
	 * ERP - suppress p_pass return value
	 * ERP - Changed output method
	 */
	/**
	 * Print Check Test Row
	 * @param string  $p_description Description.
	 * @param boolean $p_pass        Whether test passed.
	 * @param string  $p_info        Information.
	 * @return boolean
	 */
	function ERP_check_print_test_row( $p_description, $p_pass, $p_info = null ) {
		$t_output = NULL;

		$g_show_all = FALSE;
		$t_unhandled = FALSE;//check_unhandled_errors_exist();
		if( !$g_show_all && $p_pass && !$t_unhandled ) {
			return NULL;//$p_pass;
		}

		$t_output .= "\t<tr>\n\t\t<td>$p_description";
		if( $p_info !== null ) {
			if( is_array( $p_info ) && isset( $p_info[$p_pass] ) ) {
				$t_output .= '<br /><em>' . $p_info[$p_pass] . '</em>';
			} else if( !is_array( $p_info ) ) {
				$t_output .= '<br /><em>' . $p_info . '</em>';
			}
		}
		$t_output .= "</td>\n";

		if( $p_pass && !$t_unhandled ) {
			$t_result = GOOD;
		} elseif( $t_unhandled == E_DEPRECATED ) {
			$t_result = WARN;
		} else {
			$t_result = BAD;
		}
		$t_output .= ERP_check_print_test_result( $t_result );
		$t_output .= "\t</tr>\n";

		if( $t_unhandled ) {
			ERP_check_print_error_rows();
		}
//		return $p_pass;

		return $t_output;
	}

	/**
	 * Copy of the function in /admin/check/check_api.php (MantisBT 2.25)
	 */
	/**
	 * Verifies that the given collation is UTF-8
	 * @param string $p_collation
	 * @return boolean True if UTF-8
	 */
if ( !function_exists( 'check_is_collation_utf8' ) )
{
	function check_is_collation_utf8( $p_collation ) {
		return substr( $p_collation, 0, 4 ) === 'utf8';
	}
}

	/**
	 * Copy of the code in /admin/check/check_database_inc.php (MantisBT 2.25)
	 * ERP - Changed output method
	 * ERP - Changed config variable method
	 */
	/**
	 * Check the DB colation if its MySQL
	 */
	function ERP_test_database_utf8() {
		$t_output = NULL;

		$t_table_prefix = config_get_global( 'db_table_prefix' );
		$t_table_suffix = config_get_global( 'db_table_suffix' );

		if( db_is_mysql() ) {
			# Check DB's default collation
			$t_query = 'SELECT default_collation_name
				FROM information_schema.schemata
				WHERE schema_name = ' . db_param();
			$t_collation = db_result( db_query( $t_query, array( config_get_global( 'database_name' ) ) ) );
			$t_output .= ERP_check_print_test_row(
				'Database default collation is UTF-8',
				check_is_collation_utf8( $t_collation ),
				array( false => 'Database is using '
					. htmlentities( $t_collation )
					. ' collation where UTF-8 collation is required.' )
			);

			$t_table_regex = '/^'
				. preg_quote( $t_table_prefix, '/' ) . '.+?'
				. preg_quote( $t_table_suffix, '/' ) . '$/';

			$t_result = db_query( 'SHOW TABLE STATUS' );
			while( $t_row = db_fetch_array( $t_result ) ) {
				if( $t_row['comment'] !== 'VIEW' &&
					preg_match( $t_table_regex, $t_row['name'] )
				) {
					$t_output .= ERP_check_print_test_row(
						'Table <em>' . htmlentities( $t_row['name'] ) . '</em> is using UTF-8 collation',
						check_is_collation_utf8( $t_row['collation'] ),
						array( false => 'Table ' . htmlentities( $t_row['name'] )
							. ' is using ' . htmlentities( $t_row['collation'] )
							. ' collation where UTF-8 collation is required.' )
					);
				}
			}

			foreach( db_get_table_list() as $t_table ) {
				if( preg_match( $t_table_regex, $t_table ) ) {
					$t_result = db_query( 'SHOW FULL FIELDS FROM ' . $t_table );
					while( $t_row = db_fetch_array( $t_result ) ) {
						if( $t_row['collation'] === null ) {
							continue;
						}
						$t_output .= ERP_check_print_test_row(
							'Text column <em>' . htmlentities( $t_row['field'] )
							. '</em> of type <em>' . $t_row['type']
							. '</em> on table <em>' . htmlentities( $t_table )
							. '</em> is using UTF-8 collation',
							check_is_collation_utf8( $t_row['collation'] ),
							array( false => 'Text column ' . htmlentities( $t_row['field'] )
								. ' of type ' . $t_row['type']
								. ' on table ' . htmlentities( $t_table )
								. ' is using ' . htmlentities( $t_row['collation'] )
								. ' collation where UTF-8 collation is required.' )
						);
					}
				}
			}
		}

		return $t_output ;
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
			trigger_error( plugin_lang_get( 'input_name_not_allowed' ), ERROR );
		}

		$t_function_name = 'ERP_custom_function_' . $p_function_name;

		switch ( $p_type )
		{
			case 'hidden':
?>
<input type="hidden" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_value ) ?>"/>
<?php

				break;

			case 'radio_buttons':
?>
<tr>
	<td class="center" colspan="3">
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
	<input <?php echo helper_get_tab_index() ?> type="submit" class="btn btn-primary btn-white btn-round" value="<?php echo plugin_lang_get( $p_name ) ?>" />
</div>
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
<tr>
	<td class="category width-50">
<?php
				ERP_print_documentation_link( $p_name );
?>
	</td>
<?php

				switch ( $p_type )
				{
					case 'boolean':
?>
<td class="center" width="25%">
	<label>
		<input class="ace" <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $t_input_name ?>" value="<?php echo ON ?>" <?php
						check_checked( (int) $t_value, ON );
?>/><span class="lbl"><?php echo lang_get( 'yes' ) ?></span>
	</label>
</td>
<td class="center" width="25%">
	<label>
		<input class="ace" <?php echo helper_get_tab_index() ?> type="radio" name="<?php echo $t_input_name ?>" value="<?php echo OFF ?>" <?php
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

					case 'directory_string':
						$t_dir = $t_value;
						if ( is_dir( $t_dir ) )
						{
							$t_result_is_dir_color = 'green postive';
							$t_result_is_dir_text = plugin_lang_get( 'directory_exists', 'EmailReporting' );

							if ( is_writable( $t_dir ) )
							{
								$t_result_is_writable_color = 'green positive';
								$t_result_is_writable_text = plugin_lang_get( 'directory_writable', 'EmailReporting' );
							}
							else
							{
								$t_result_is_writable_color = 'red negative';
								$t_result_is_writable_text = plugin_lang_get( 'directory_unwritable', 'EmailReporting' );
							}
						}
						else
						{
							$t_result_is_dir_color = 'red negative';
							$t_result_is_dir_text = plugin_lang_get( 'directory_unavailable', 'EmailReporting' );
							$t_result_is_writable_color = NULL;
							$t_result_is_writable_text = NULL;
						}
?>
<td width="25%">
	<input class="input-sm" <?php echo helper_get_tab_index() ?> type="text" size="32" maxlength="200" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_dir ) ?>"/>
</td>
<td width="25%">
	<span class="<?php echo $t_result_is_dir_color ?>"><?php echo $t_result_is_dir_text ?></span><br />
	<span class="<?php echo $t_result_is_writable_color ?>"><?php echo $t_result_is_writable_text ?></span>
</td>
<?php

						break;

					case 'disabled':
?>
<td colspan="2">
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
<td colspan="2">
	<input class="input-sm" <?php echo helper_get_tab_index() ?> type="text" size="64" maxlength="100" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( $t_value ) ?>"/>
</td>
<?php

						break;

					case 'string_multiline':
					case 'string_multiline_array':
?>
<td colspan="2">
	<textarea class="form-control" <?php echo helper_get_tab_index() ?> cols="64" rows="6" name="<?php echo $t_input_name ?>"><?php

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
<td colspan="2">
	<input class="input-sm" <?php echo helper_get_tab_index() ?> type="password" size="64" name="<?php echo $t_input_name ?>" value="<?php echo string_attribute( base64_decode( (string) $t_value ) ) ?>"/>
</td>
<?php

						break;

					case 'dropdown':
					case 'dropdown_any':
					case 'dropdown_multiselect':
					case 'dropdown_multiselect_any':
?>
<td colspan="2">
<?php

						if ( function_exists( $t_function_name ) )
						{
?>
	<select class="input-sm" <?php echo helper_get_tab_index() ?> name="<?php
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
<td colspan="2">
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
					$t_function_name( $p_name, $t_value, $p_function_parameter );
				}
				else
				{
?>
<tr>
	<td colspan="3">
		<span class="red negative"><?php echo plugin_lang_get( 'function_not_found', 'EmailReporting' ) . ': ' . $t_function_name ?></span>
	</td>
</tr>
<?php
				}
				break;

			default:
?>
<tr>
	<td colspan="3">
		<span class="red negative"><?php echo plugin_lang_get( 'unknown_setting', 'EmailReporting' ) . $p_name ?> -> level 1</span>
	</td>
</tr>
<?php
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
<tr>
	<td class="category">
<?php
			ERP_print_documentation_link( $p_name );
			echo ': ' . string_display( lang_get_defaulted( $t_def[ 'name' ] ) );
?>
	</td>
	<td colspan="2">
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
		//require_once( 'Net/POP3.php' );
		plugin_require_api( 'core_pear/Net/POP3.php' );
		//require_once( 'Net/IMAPProtocol.php' );
		plugin_require_api( 'core_pear/Net/IMAPProtocol.php' );

		$t_mailbox_connection_pop3 = new Net_POP3();
		$t_mailbox_connection_imap = new Net_IMAPProtocol();

		$t_supported_auth_methods = array_unique( array_merge( $t_mailbox_connection_pop3->supportedAuthMethods, $t_mailbox_connection_imap->supportedAuthMethods ) );
		natcasesort( $t_supported_auth_methods );

		foreach ( $t_supported_auth_methods AS $t_supported_auth_method )
		{
			echo '<option';
			check_selected( (string) $p_sel_value, $t_supported_auth_method );
			echo '>' . string_attribute( $t_supported_auth_method ) . '</option>';
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
				$t_option_array = array( 'description' => ERP_plugin_lang_get_defaulted( $t_option_array ) );
			}

			$t_options_sorted[ $t_option_key ] = $t_option_array[ 'description' ];
		}

		natcasesort( $t_options_sorted );

		foreach ( $t_options_sorted AS $t_option_key => $t_description )
		{
			echo '<option value="' . string_attribute( $t_option_key ) . '"';
			check_selected( $t_sel_value, $t_option_key );
			echo '>' . ( ( isset( $p_options_array[ $t_option_key ][ 'enabled' ] ) && $p_options_array[ $t_option_key ][ 'enabled' ] == FALSE ) ? '* ' : NULL ) . string_attribute( $t_description ) . '</option>';
		}
	}

	# --------------------
	# output a option list with supported encryptions
	function ERP_custom_function_print_encryption_option_list( $p_sel_value )
	{
		if ( extension_loaded( 'openssl' ) )
		{
			$t_socket_transports = stream_get_transports();
			$t_supported_encryptions = array( 'None', 'SSL', 'SSLv2', 'SSLv3', 'TLS', 'TLSv1.0', 'TLSv1.1', 'TLSv1.2', 'STARTTLS' );
			foreach ( $t_supported_encryptions AS $t_encryption )
			{
				if ( $t_encryption === 'None' || ( $t_encryption === 'STARTTLS' && function_exists('stream_socket_enable_crypto') ) || in_array( strtolower( $t_encryption ), $t_socket_transports, TRUE ) )
				{
					echo '<option';
					check_selected( (string) $p_sel_value, $t_encryption );
					echo '>' . string_attribute( $t_encryption ) . '</option>';
				}
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
		// Need to disable allow_no_category for a moment
		ERP_set_temporary_overwrite( 'allow_no_category', OFF );

		// Need to disable inherit projects for one moment.
		ERP_set_temporary_overwrite( 'subprojects_inherit_categories', OFF );

		$t_sel_value = (array) $p_sel_value;

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
			print_category_option_list( $t_sel_value, (int) $t_project_id );
			echo '</optgroup>';
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
			check_selected( (array) $p_sel_value, (int) $t_all_projects[ $t_project_id ][ 'id' ] );
			echo '>' . ( ( $t_all_projects[ $t_project_id ][ 'enabled' ] == FALSE ) ? '* ' : NULL ) . string_attribute( $t_all_projects[ $t_project_id ][ 'name' ] ) . '</option>' . "\n";
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
	# output a option list with the tags currently known in the Mantis system
	# Based on MantisBT function print_tag_option_list
	function ERP_custom_function_print_tag_attach_option_list( $p_sel_value )
	{
		require_api( 'tag_api.php' );

		$t_rows = tag_get_candidates_for_bug( 0 );

		foreach ( $t_rows as $row )
		{
			$t_string = $row[ 'name' ];
			if ( !empty( $row[ 'description' ] ) )
			{
				$t_string .= ' - ' . mb_substr( $row[ 'description' ], 0, 20 );
			}

			echo '<option value="', $row[ 'id' ], '" title="', string_attribute( $row[ 'name' ] ), '"';
			check_selected( (array) $p_sel_value, (int) $row[ 'id' ] );
			echo '>', string_attribute( $t_string ), '</option>';
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

		ERP_print_action_radio_buttons( $p_input_name, (string) $p_sel_value, $p_variable_array, $t_actions_list );
	}

	function ERP_custom_function_print_rule_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array )
	{
		$t_actions_list = array(
			0 => array( 'add' ),
			1 => array( 'copy', 'edit', 'delete' ),
		);

		ERP_print_action_radio_buttons( $p_input_name, (string) $p_sel_value, $p_variable_array, $t_actions_list );
	}

	function ERP_print_action_radio_buttons( $p_input_name, $p_sel_value, $p_variable_array, $p_actions_list )
	{
		echo '<table border=0 width="100%"><tr>';

		foreach ( $p_actions_list AS $t_action_key => $t_actions )
		{
			if ( is_array( $p_variable_array ) && count( $p_variable_array ) >= $t_action_key )
			{
				foreach ( $t_actions AS $t_action )
				{
					echo '<td><label><input class="ace" ' . helper_get_tab_index() . ' type="radio" name="' . $p_input_name . '" value="' . string_attribute( $t_action ) . '"';
					check_checked( $p_sel_value, $t_action );
					echo '/><span class="lbl">' . plugin_lang_get( $t_action . '_action' ) . '</span></label></td>';
				}
			}
		}

		echo '</tr></table>';
	}

?>
