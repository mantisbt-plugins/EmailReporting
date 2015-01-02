<?php
auth_reauthenticate( );
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

plugin_require_api( 'core/config_api.php' );
require_api( 'custom_field_api.php' );

$f_rule_action = gpc_get_string( 'rule_action' );
$f_select_rule = gpc_get_int( 'select_rule' );

$t_rules = plugin_config_get( 'rules' );

if ( $f_rule_action === 'add' || $f_rule_action === 'copy' || ( ( $f_rule_action === 'edit' ) && $f_select_rule >= 0 ) )
{
	$t_rule = array(
		'enabled'				=> gpc_get_bool( 'enabled' ),
		'description'			=> gpc_get_string( 'description' ),

// code for retrieving custom fields ids for loop gpc
/*		$t_custom_fields = custom_field_get_ids();
		foreach( $t_custom_fields as $t_field_id )
		{

	$t_related_custom_field_ids = custom_field_get_linked_ids( $t_bug_data->project_id );
	foreach( $t_related_custom_field_ids as $t_id ) {
		$t_def = custom_field_get_definition( $t_id );

		# Only update the field if it would have been display for editing
		if( !( ( !$f_update_mode && $t_def['require_' . $t_custom_status_label] ) ||
						( !$f_update_mode && $t_def['display_' . $t_custom_status_label] && in_array( $t_custom_status_label, array( "resolved", "closed" ), TRUE ) ) ||
						( $f_update_mode && $t_def['display_update'] ) ||
						( $f_update_mode && $t_def['require_update'] ) ) ) {
			continue;
		}

		# Do not set custom field value if user has no write access.
		if( !custom_field_has_write_access( $t_id, $f_bug_id ) ) {
			continue;
		}

		# Produce an error if the field is required but wasn't posted
		if ( !gpc_isset_custom_field( $t_id, $t_def['type'] ) &&
			( $t_def['require_' . $t_custom_status_label] ) ) {
			error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
			trigger_error( ERROR_EMPTY_FIELD, ERROR );
		}

		$t_new_custom_field_value = gpc_get_custom_field( "custom_field_$t_id", $t_def['type'], '' );
		$t_old_custom_field_value = custom_field_get_value( $t_id, $f_bug_id );

		# Don't update the custom field if the new value both matches the old value and is valid
		# This ensures that changes to custom field validation will force the update of old invalid custom field values
		if( $t_new_custom_field_value === $t_old_custom_field_value &&
			custom_field_validate( $t_id, $t_new_custom_field_value ) ) {
			continue;
		}

		# Attempt to set the new custom field value
		if ( !custom_field_set_value( $t_id, $f_bug_id, $t_new_custom_field_value ) ) {
			error_parameters( lang_get_defaulted( custom_field_get_field( $t_id, 'name' ) ) );
			trigger_error( ERROR_CUSTOM_FIELD_INVALID_VALUE, ERROR );
		}
	}
*/
	);
}

if ( $f_rule_action === 'add' || $f_rule_action === 'copy' )
{
	$t_rules[] = $t_rule;
}
elseif ( $f_rule_action === 'edit' && $f_select_rule >= 0 )
{
	$t_rules[ $f_select_rule ] = $t_rule;
}
elseif ( $f_rule_action === 'delete' && $f_select_rule >= 0 )
{
	unset( $t_rules[ $f_select_rule ] );
}

if( plugin_config_get( 'rules' ) != $t_rules && ( $f_rule_action === 'add' || $f_rule_action === 'copy' || ( ( $f_rule_action === 'edit' || $f_rule_action === 'delete' ) && $f_select_rule >= 0 ) ) )
{
	plugin_config_set( 'rules', $t_rules );
}

if ( !isset( $t_no_redirect ) )
{
	print_successful_redirect( plugin_page( 'manage_rule', TRUE ) );
}
