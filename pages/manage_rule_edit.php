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
		'enabled'          => gpc_get_int( 'enabled', OFF ),
		'description'      => gpc_get_string( 'description', '' ),
		'add_note_actions' => gpc_get_int( 'add_note_actions', OFF ),

		'conditions' => array(
			'cond_issue_creation_source'         => gpc_get_string_array( 'cond_issue_creation_source', array() ),
			'cond_mailbox'                       => gpc_get_int_array( 'cond_mailbox', array() ),
			'cond_event_type'                    => gpc_get_string_array( 'cond_event_type', array() ),
			'cond_issue_reporter'                => gpc_get_int_array( 'cond_issue_reporter', array() ),
			'cond_issue_handler'                 => gpc_get_int_array( 'cond_issue_handler', array() ),
			'cond_email_from'                    => gpc_get_string( 'cond_email_from', '' ),
			'cond_email_to'                      => gpc_get_string( 'cond_email_to', '' ),
			'cond_issue_project'                 => gpc_get_int_array( 'cond_issue_project', array() ),
			'cond_issue_category'                => gpc_get_int_array( 'cond_issue_category', array() ),
			'cond_issue_priority'                => gpc_get_int_array( 'cond_issue_priority', array() ),
			'cond_issue_summary'                 => gpc_get_string( 'cond_issue_summary', '' ),
			'cond_issuenote_description'         => gpc_get_string( 'cond_issuenote_description', '' ),
			'cond_issuenote_attachments'         => gpc_get_string( 'cond_issuenote_attachments', '' ),
			'cond_issuenote_attachment_filename' => gpc_get_string( 'cond_issuenote_attachment_filename', '' ),
		),

		'actions' => array(
			'act_issue_category'         => gpc_get_int( 'act_issue_category', 0 ),
			'act_issue_severity'         => gpc_get_int( 'act_issue_severity', 0 ),
			'act_issue_status'           => gpc_get_int( 'act_issue_status', 0 ),
			'act_issue_view_state'       => gpc_get_int( 'act_issue_view_state', 0 ),
			'act_issue_reproducibility'  => gpc_get_int( 'act_issue_reproducibility', 0 ),
			'act_issue_reporter'         => gpc_get_int( 'act_issue_reporter', 0 ),
			'act_issue_handler'          => gpc_get_int( 'act_issue_handler', 0 ),
			'act_issue_duedate'          => array(
				'modifier'               => gpc_get_string( 'act_issue_duedate_modifier', '' ),
				'amount'                 => gpc_get_int( 'act_issue_duedate_amount', 0 ),
			),
			'act_issue_priority'         => gpc_get_int( 'act_issue_priority', 0 ),
			'act_issue_tag'              => gpc_get_int_array( 'act_issue_tag', array() ),
			'act_issue_custom_fields'    => array(), // to be filled later
		),

		'exceptions' => array(
			'excep_email_from'                    => gpc_get_string( 'excep_email_from', '' ),
			'excep_issue_summary'                 => gpc_get_string( 'excep_issue_summary', '' ),
			'excep_issuenote_description'         => gpc_get_string( 'excep_issuenote_description', '' ),
			'excep_issuenote_attachment_filename' => gpc_get_string( 'excep_issuenote_attachment_filename', '' ),
		),
	);

	// code for retrieving custom fields ids for loop gpc
	$t_custom_fields = custom_field_get_ids();
	foreach ( $t_custom_fields AS $t_field_id )
	{
		$t_def = custom_field_get_definition( $t_field_id );

		$t_new_custom_field_value = gpc_get_custom_field( "custom_field_$t_field_id", $t_def['type'], '' );

		if ( !empty( $t_new_custom_field_value ) )
		{
			$t_rule[ 'actions' ][ 'act_issue_custom_fields' ][ $t_field_id ] = $t_new_custom_field_value;
		}
	}

	$t_rule[ 'conditions' ] = array_filter( $t_rule[ 'conditions' ] );
	$t_rule[ 'actions' ][ 'act_issue_duedate' ] = array_filter( $t_rule[ 'actions' ][ 'act_issue_duedate' ] );
	$t_rule[ 'actions' ] = array_filter( $t_rule[ 'actions' ] );
	$t_rule[ 'exceptions' ] = array_filter( $t_rule[ 'exceptions' ] );
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
elseif ( $f_rule_action === 'describe' && $f_select_rule >= 0 )
{
	// @TODO
}

if( plugin_config_get( 'rules' ) !== $t_rules && ( $f_rule_action === 'add' || $f_rule_action === 'copy' || ( ( $f_rule_action === 'edit' || $f_rule_action === 'delete' ) && $f_select_rule >= 0 ) ) )
{
	plugin_config_set( 'rules', $t_rules );
}

if ( !isset( $t_no_redirect ) )
{
	print_header_redirect( plugin_page( 'manage_rule', TRUE ) );
}
