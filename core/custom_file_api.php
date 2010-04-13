<?php
/**
 * Add a file to the system using the configured storage method
 *
 * @param integer $p_bug_id the bug id
 * @param array $p_file the uploaded file info, as retrieved from gpc_get_file()
 * 
 * almost the same as its counterpart in the file_api.php from MantisBT version 1.2.0, but with small adjustments to the disk upload part
 */
function ERP_custom_file_add( $p_bug_id, $p_file, $p_table = 'bug', $p_title = '', $p_desc = '', $p_user_id = null ) {

	file_ensure_uploaded( $p_file );
	$t_file_name = $p_file['name'];
	$t_tmp_file = $p_file['tmp_name'];

	if( !file_type_check( $t_file_name ) ) {
		trigger_error( ERROR_FILE_NOT_ALLOWED, ERROR );
	}

	if( !file_is_name_unique( $t_file_name, $p_bug_id ) ) {
		trigger_error( ERROR_DUPLICATE_FILE, ERROR );
	}

	if( 'bug' == $p_table ) {
		$t_project_id = bug_get_field( $p_bug_id, 'project_id' );
		$t_bug_id = bug_format_id( $p_bug_id );
	} else {
		$t_project_id = helper_get_current_project();
		$t_bug_id = 0;
	}

	if( $p_user_id === null ) {
		$c_user_id = auth_get_current_user_id();
	} else {
		$c_user_id = (int)$p_user_id;
	}

	# prepare variables for insertion
	$c_bug_id = db_prepare_int( $p_bug_id );
	$c_project_id = db_prepare_int( $t_project_id );
	$c_file_type = db_prepare_string( $p_file['type'] );
	$c_title = db_prepare_string( $p_title );
	$c_desc = db_prepare_string( $p_desc );

	if( $t_project_id == ALL_PROJECTS ) {
		$t_file_path = config_get( 'absolute_path_default_upload_folder' );
	} else {
		$t_file_path = project_get_field( $t_project_id, 'file_path' );
		if( $t_file_path == '' ) {
			$t_file_path = config_get( 'absolute_path_default_upload_folder' );
		}
	}
	$c_file_path = db_prepare_string( $t_file_path );
	$c_new_file_name = db_prepare_string( $t_file_name );

	$t_file_hash = ( 'bug' == $p_table ) ? $t_bug_id : config_get( 'document_files_prefix' ) . '-' . $t_project_id;
	$t_unique_name = file_generate_unique_name( $t_file_hash . '-' . $t_file_name, $t_file_path );
	$t_disk_file_name = $t_file_path . $t_unique_name;
	$c_unique_name = db_prepare_string( $t_unique_name );

	$t_file_size = filesize( $t_tmp_file );
	if( 0 == $t_file_size ) {
		trigger_error( ERROR_FILE_NO_UPLOAD_FAILURE, ERROR );
	}
	$t_max_file_size = (int) min( ini_get_number( 'upload_max_filesize' ), ini_get_number( 'post_max_size' ), config_get( 'max_file_size' ) );
	if( $t_file_size > $t_max_file_size ) {
		trigger_error( ERROR_FILE_TOO_BIG, ERROR );
	}
	$c_file_size = db_prepare_int( $t_file_size );

	$t_method = config_get( 'file_upload_method' );

	switch( $t_method ) {
		case FTP:
		case DISK:
			file_ensure_valid_upload_path( $t_file_path );

			if( !file_exists( $t_disk_file_name ) ) {
				if( FTP == $t_method ) {
					$conn_id = file_ftp_connect();
					file_ftp_put( $conn_id, $t_disk_file_name, $t_tmp_file );
					file_ftp_disconnect( $conn_id );
				}

				// move_uploaded_file replaced with rename function. Needed since files added through the EmailReporting method are not seen as such
				if( !rename( $t_tmp_file, $t_disk_file_name ) ) {
					// Corrected trigger error message name, FILE_MOVE_FAILED should have been ERROR_FILE_MOVE_FAILED
					trigger_error( ERROR_FILE_MOVE_FAILED, ERROR );
				}

				chmod( $t_disk_file_name, config_get( 'attachments_file_permissions' ) );

				$c_content = "''";
			} else {
				trigger_error( ERROR_FILE_DUPLICATE, ERROR );
			}
			break;
		case DATABASE:
			$c_content = db_prepare_binary_string( fread( fopen( $t_tmp_file, 'rb' ), $t_file_size ) );
			break;
		default:
			trigger_error( ERROR_GENERIC, ERROR );
	}

	$t_file_table = db_get_table( 'mantis_' . $p_table . '_file_table' );
	$c_id = ( 'bug' == $p_table ) ? $c_bug_id : $c_project_id;

	$query = "INSERT INTO $t_file_table
						(" . $p_table . "_id, title, description, diskfile, filename, folder, filesize, file_type, date_added, content, user_id)
					  VALUES
						($c_id, '$c_title', '$c_desc', '$c_unique_name', '$c_new_file_name', '$c_file_path', $c_file_size, '$c_file_type', '" . db_now() . "', $c_content, $c_user_id)";
	db_query( $query );

	if( 'bug' == $p_table ) {

		# updated the last_updated date
		$result = bug_update_date( $p_bug_id );

		# log new bug
		history_log_event_special( $p_bug_id, FILE_ADDED, $t_file_name );
	}
}
?>
