<?php
function wppt_get_export_dest(){
	$type = 'leave';
	if(isset($_POST['wppt_export_dest'])){
		$type = $_POST['wppt_export_dest'];
	}
	return $type;
}

function wppt_get_file_rel($file){
	return substr($file, strlen(wppt_get_uploads_basedir()));
}

function wppt_get_file_rel_abs($file){
	return substr($file, strlen(ABSPATH)-1);
}

function wppt_get_uploads_basedir(){
	$upload_dir = wp_upload_dir();
	return $upload_dir['basedir'];
}

function wppt_get_uploads_dir(){
	return wppt_get_uploads_basedir() . '/wppt-export';
}

function wppt_get_data_file(){
	return wppt_get_uploads_dir() . '/export-data.json';
}

function wppt_get_zip_file(){
	return wppt_get_uploads_basedir() . '/wppt-export.zip';
}

function wppt_get_data(){
	$import_data = array();
	if(file_exists(wppt_get_data_file()) && ($data = file_get_contents(wppt_get_data_file()))){
		$import_data = @json_decode($data, true);
	}
	return $import_data;
}

function wppt_fix_serialized($data){
	return preg_replace_callback ('|s:(\d+):"(.*?)";|', function($match){
			return ($match[1] == strlen($match[2])) ? $match[0] : 's:' . strlen($match[2]) . ':"' . $match[2] . '";';
		}, $data);
}

function wppt_save_data($export_data){
	if(empty($export_data['posts_data'])){
		return false;
	}

	if(!file_exists(wppt_get_uploads_dir())){
		mkdir(wppt_get_uploads_dir());
	}

	if(!empty($export_data['files_list']) && (wppt_get_export_dest() == 'download' || wppt_get_export_dest() == 'transport')){
		if(file_put_contents(wppt_get_data_file(), json_encode($export_data))
		&& wppt_zip_files($export_data['files_list'], wppt_get_zip_file())){
			wppt_rmdir_r(wppt_get_uploads_dir());
			return true;
		}
		return false;
	}

	return file_put_contents(wppt_get_data_file(), json_encode($export_data));
}

function wppt_get_progress_file(){
	return wppt_get_uploads_dir() . '/import-progress';
}

function wppt_get_progress(){
	if(file_exists(wppt_get_progress_file())){
		return json_decode(file_get_contents(wppt_get_progress_file()), true);
	}
	return false;
}

function wppt_save_progress($import_progress){
	if(empty($import_progress)){
		return false;
	}
	return file_put_contents(wppt_get_progress_file(), json_encode($import_progress));
}

function wppt_del_progress(){
	if(file_exists(wppt_get_progress_file())){
		return unlink(wppt_get_progress_file());
	}
	return false;
}

function wppt_get_img_dir(){
	return wppt_get_uploads_dir() . '/images';
}

function wppt_posts_arr_to_id_keys($posts){
	if(!$posts){
		return array();
	}
	$pids = array();
	foreach($posts as $post){
		$pids[] = $post['ID'];
	}
	return array_combine($pids, $posts);
}

function wppt_extract_content_urls($content, $unique=false){
	preg_match_all('%src="(.*?)"%', $content, $urls, PREG_SET_ORDER);
	$urls_real = array();
	if($urls){
		foreach($urls as $url){
			if(isset($url[1]) && strpos($url[1], get_site_url()) === 0){
				$urls_real[] = $url[1];
			}
		}
	}
	if($unique){
		return array_unique($urls_real);
	}
	return $urls_real;
}

function wppt_get_post_meta($post_id, $meta_key, $all_metas, $single=true){
	if(isset($all_metas[$post_id][$meta_key])){
		if($single && isset($all_metas[$post_id][$meta_key][0])){
			return $all_metas[$post_id][$meta_key][0];
		}else{
			return $all_metas[$post_id][$meta_key];
		}
	}
	return false;
}

function wppt_mkdir_r($path){
	if(file_exists($path)){
		return true;
	}
	if(!substr_count($path, '/')){
		return false;
	}
	$path_e = explode('/', $path);
	$path_r = array();
	foreach($path_e as $ek => $e){
		if($e){
			$path_r[] = $e;
		}
	}

	if(!$path_r){
		return false;
	}

	$path_line = '';
	foreach($path_r as $d){
		$path_line .= '/' . $d;
		if(!file_exists($path_line) && !mkdir($path_line)){
			return false;
		}
	}

	return true;
}

function wppt_rmdir_r($dir){
	if(is_dir($dir) && ($objects = scandir($dir))){
		foreach($objects as $object){
			if($object != '.' && $object != '..'){
				if(filetype($dir . '/' . $object) == 'dir'){ 
					wppt_rmdir_r($dir . '/' . $object); 
				}else{ 
					unlink($dir . '/' . $object);
				}
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function wppt_get_users($posts_data=array()){
	$users = array();
	if($posts_data){
		foreach($posts_data as $post){
			if($u = get_user_by('id', $post['post_author'])){
				$users[$u->data->user_login] = (array)$u->data;
				$users[$u->data->user_login]['role'] = $u->roles[0];
			}
		}
	}elseif($users_q = get_users()){
		foreach($users_q as $u){
			if(in_array($u->roles[0], array('administrator', 'editor', 'author', 'contributor'))){ 
				$users[$u->data->user_login] = (array)$u->data;
				$users[$u->data->user_login]['role'] = $u->roles[0];
			}
		}
	}
	return $users;
}

function wppt_get_current_username(){
	if($user = wp_get_current_user()){
		return $user->user_login;
	}
	return false;
}

function wppt_objects_to_arrs($objects_arr){
	if(!$objects_arr){
		return $objects_arr;
	}
	foreach($objects_arr as $ok => &$object){
		if(is_object($object)){
			$object = (array)$object;
		}
	}
	return $objects_arr;
}

function wppt_userid_to_username($uid, $users_data){
	if(!$users_data){
		return false;
	}
	foreach($users_data as $user){
		if($user['ID'] == $uid){
			return $user['user_login'];
		}
	}
	return false;
}

function wppt_get_arr_loop_sector($loop_arr, $start=0){
	return array_slice($loop_arr, $start, 5, true);
}

function wppt_zip_files($files=array(), $destination){
	if(empty($files)){
		return false;
	}
	if(!class_exists('ZipArchive')){
		return false;
	}
	$valid_files = array();
	foreach($files as $file){
		if(file_exists($file)){
			$valid_files[] = $file;
		}
	}
	if(empty($valid_files)){
		return false;
	}
	$zip = new ZipArchive();
	if(!$zip->open($destination, ZIPARCHIVE::OVERWRITE)){
		return false;
	}
	foreach($valid_files as $file) {
		$zip->addFile($file, wppt_get_file_rel($file));
	}
	$zip->close();
	return file_exists($destination);
}
/*
function wppt_unzip_file($file, $destination){
	if(!file_exists($file) || !class_exists('ZipArchive')){
		return false;
	}
	$zip = new ZipArchive();
	if(!$zip->open($file, ZIPARCHIVE::OVERWRITE) || !$zip->extractTo($destination)){
		return false;
	}
	if($zip->extractTo($destination)){
		$zip->close();
		return true;
	}
	$zip->close();
	return false;
}
*/
function wppt_respond_json($response){
	header('Content-Type: application/json');
	echo json_encode($response);
	wp_die();
}

function wppt_is_source_uploaded(){
	if(isset($_POST['wppt_import_source']) && $_POST['wppt_import_source'] == 'upload'
	&& isset($_FILES['wppt_import_upload']) && $_FILES['wppt_import_upload']['type'] == 'application/zip'){
		return true;
	}
	return false;
}

function wppt_get_valid_export_types(){
	global $wp_post_types;
	$valid_types = array();
	$banned_post_types = array('attachment', 'revision', 'nav_menu_item');
	if($wp_post_types){
		foreach($wp_post_types as $post_type_code => $post_type){
			if(!in_array($post_type_code, $banned_post_types)){
				$valid_types[$post_type_code] = $post_type;
			}
		}
	}
	return $valid_types;
}