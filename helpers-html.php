<?php

function wppt_get_export_progress_html($status){
	if($status){
		$destination = wppt_get_uploads_dir();
		if(wppt_get_export_dest() == 'download' || wppt_get_export_dest() == 'transport'){
			$destination = wppt_get_zip_file();
		}
		return '<p style="color: green;">' . __('Data exported to', 'wppt') . ': ' . wppt_get_file_rel_abs($destination) . '</p>';
	}
	return wppt_get_errors_html(array(__('Failed to export data!', 'wppt')));
}

function wppt_get_preimport_html($check){

	$html  = '';

	if($check['errors']){
		$html .= wppt_get_errors_html($check['errors']);
	}

	if(!$check['status']){
		return $html;
	}

	$html .= wppt_get_usermatch_form($check['summary']['users_source'], $check['summary']['users_dest']);

	$html .= '<h4>' . __('Pre-import data summary', 'wppt') . ':</h4>';
	$html .= '<p>';
	$html .= '<span>' . __('Posts to import', 'wppt') . ':</span> <strong>' . $check['summary']['posts_count'] . '</strong><br />';
	$html .= '<span>' . __('Terms to import', 'wppt') . ':</span> <strong>' . $check['summary']['terms_count'] . '</strong><br />';
	$html .= '<span>' . __('Attachments to import', 'wppt') . ':</span> <strong>' . $check['summary']['attachments_count'] . '</strong>';
	$html .= '</p>';

	return $html;
}

function wppt_get_usermatch_form($users_source, $users_dest){

	$form = '<form id="wppt-preimport-form" action="">';
	if($users_source && $users_dest){ 
		$form .= '<h4>' . __('Match users', 'wppt') . ':</h4>';
		$form .= '<p>';
		foreach($users_source as $suser){ 
			$form .= '<label class="username-source">' . $suser['user_login'] . '</label>';
			$form .= '<select class="username-dest" name="wppt_users_match[' . $suser['user_login'] . ']">';
			$form .= '<option value="--current_user--">- ' . __('Current user (You)', 'wppt') . ' -</option>';
			$form .= '<option value="--create_user--">- ' . __('Create this user', 'wppt') . ' -</option>';
			foreach($users_dest as $duser){ 
				$selected = '';
				if($suser['user_login'] == $duser['user_login']){
					$selected = ' selected';
				}
				$form .= '<option value="' . $duser['user_login'] . '"' . $selected . '>' . $duser['user_login'] . '</option>';
			}
			$form .= '</select><br />';
		}
		$form .= '</p>';
	}
	$form .= '<input type="hidden" name="action" value="wppt_import" />';
	$form .= '</form>';

	return $form;
}

function wppt_get_import_progress_html($import_progress){

	$html  = '<h4>' . __('Import progress', 'wppt') . ':</h4>';
	$html .= '<p>';
	$html .= '<span>' . __('Importing posts', 'wppt') . ':</span> ';
	$html .= '<strong>' . $import_progress['report']['posts_data_progress'] . '/' . $import_progress['report']['posts_data_length'] . '</strong><br />';

	$html .= '<span>' . __('Post relations', 'wppt') . ':</span> ';
	$html .= '<strong>';
	if($import_progress['report']['post_relations_ok']){
		$html .= '<span style="color: green;">' . __('OK', 'wppt') . '</span>';
	}else{
		$html .= __('waiting...', 'wppt');
	}
	$html .= '</strong><br />';

	$html .= '<span>' . __('Importing users', 'wppt') . ':</span> ';
	$html .= '<strong>' . $import_progress['report']['users_data_progress'] . '/' . $import_progress['report']['users_data_length'] . '</strong><br />';

	$html .= '<span>' . __('Importing terms', 'wppt') . ':</span> ';
	$html .= '<strong>' . $import_progress['report']['terms_data_progress'] . '/' . $import_progress['report']['terms_data_length'] . '</strong><br />';

	$html .= '<span>' . __('Setting term relations', 'wppt') . ':</span> ';
	$html .= '<strong>';
	if($import_progress['report']['term_relations_ok']){
		$html .= '<span style="color: green;">' . __('OK', 'wppt') . '</span>';
	}else{
		$html .= __('waiting...', 'wppt');
	}
	$html .= '</strong><br />';

	$html .= '<span>' . __('Assigning terms to posts', 'wppt') . ':</span> ';
	$html .= '<strong>';
	if($import_progress['report']['assigning_terms_ok']){
		$html .= '<span style="color: green;">' . __('OK', 'wppt') . '</span>';
	}else{
		$html .= __('waiting...', 'wppt');
	}
	$html .= '</strong><br />';

	$html .= '<span>' . __('Importing attachments', 'wppt') . ':</span> ';
	$html .= '<strong>' . $import_progress['report']['attachments_data_progress'] . '/' . $import_progress['report']['attachments_data_length'] . '</strong><br />';

	$html .= '<span>' . __('Assigning thumbnails to posts', 'wppt') . ':</span> ';
	$html .= '<strong>';
	if($import_progress['report']['assigning_thumbnails_ok']){
		$html .= '<span style="color: green;">' . __('OK', 'wppt') . '</span>';
	}else{
		$html .= __('waiting...', 'wppt');
	}
	$html .= '</strong>';
	$html .= '<p>';

	$html .= wppt_get_errors_html($import_progress['report']['errors']);

	if($import_progress['report']['finished']){
		$html .= '<p style="color: green; font-weight: bold;">' . __('Import finished!', 'wppt') . '</p>';
	}

	return $html;
}

function wppt_get_errors_html($errors){
	$html = '';
	if($errors){
		$html .= '<p style="color: red;">';
		foreach($errors as $error){
			$html .= '<span>' . $error . '</span><br />';
		}
		$html .= '</p>';
	}

	return $html;
}
