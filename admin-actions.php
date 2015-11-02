<?php

add_action('wp_ajax_wppt_export', 'wppt_export_ajax');
function wppt_export_ajax(){
	$status = wppt_export();
	$response = array(
						'status' => (int)$status,
						'html' => base64_encode(wppt_get_export_progress_html($status)),
						'downloadready' => 0
						);
	if($status && wppt_get_export_dest() == 'download'){
		$response['downloadready'] = 1;
	}
	wppt_respond_json($response);
}

add_action('wp_ajax_wppt_export_download', 'wppt_export_download_ajax');
function wppt_export_download_ajax(){
	if(file_exists(wppt_get_zip_file())){
//		header('Content-Description: File Transfer');
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . basename(wppt_get_zip_file()) . '"');
		header('Content-Transfer-Encoding: binary');
//		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Content-Length: ' . filesize(wppt_get_zip_file()));
//		ob_clean();
//		flush();
		readfile(wppt_get_zip_file());
		unlink(wppt_get_zip_file());
		wp_die();
	}
	echo '';
	wp_die();
}

add_action('wp_ajax_wppt_preimport', 'wppt_preimport_ajax');
function wppt_preimport_ajax(){
	$check = wppt_preimport_check();
	wppt_respond_json(array('status' => $check['status'], 'html' => base64_encode(wppt_get_preimport_html($check))));
}

add_action('wp_ajax_wppt_import', 'wppt_import_ajax');
function wppt_import_ajax(){
	$import_progress = wppt_import();
	if(!$import_progress['report']['finished']){
		wppt_save_progress($import_progress);
	}else{
		wppt_rmdir_r(wppt_get_uploads_dir());
//		wppt_del_progress();
	}
	wppt_respond_json(array(
		'status' => $import_progress['report']['status'],
		'html' => base64_encode(wppt_get_import_progress_html($import_progress)),
		'finished' => (int)$import_progress['report']['finished']
	));
}

function wppt_export(){
//	global $sitepress;

	$post_type = 'post';
	if(isset($_POST['wppt_export_type'])
	&& array_key_exists($_POST['wppt_export_type'], wppt_get_valid_export_types())){
		$post_type = $_POST['wppt_export_type'];
	}
	$export_data = array(
							'export_source' => get_site_url(), 
							'users_data' => array(),
//							'users_meta' => array(),
							'posts_data' => array(), 
							'posts_meta' => array(), 
							'posts_terms' => array(), 
							'terms_data' => array(),
							'attachments_data' => array(), 
							'attachments_meta' => array(),
							'files_list' => array(wppt_get_data_file()),
//							'wpml_langs' => array(),
							'wpml_posts' => array(), 
							'wpml_terms' => array() 
						);

	$export_data['posts_data'] = wppt_objects_to_arrs(get_posts(array(
						'post_type' => $post_type,
						'post_status' => 'any',
//						'date_query' => array('after' => array('year' => '2015', 'month' => '3', 'day' => '30')),
						'posts_per_page' => -1
						)));
	$export_data['posts_data'] = wppt_posts_arr_to_id_keys($export_data['posts_data']);

	$export_data['users_data'] = wppt_get_users($export_data['posts_data']);

/*
	// Get registered languages
	if(function_exists('icl_get_languages') && ($wpml_langs = icl_get_languages())){
		$export_data['wpml_langs'] = array_keys($wpml_langs);
	}
*/

	wppt_rmdir_r(wppt_get_uploads_dir());

	if($export_data['posts_data']){
		foreach($export_data['posts_data'] as &$post){
			if($pmeta = get_post_meta($post['ID'])){
				$export_data['posts_meta'][$post['ID']] = $pmeta;
			}
			if($ptaxes = get_post_taxonomies($post['ID'])){
				foreach($ptaxes as $ptax){
					if(($pterms = wppt_objects_to_arrs(get_the_terms($post['ID'], $ptax))) && !is_wp_error($pterms)){
						foreach($pterms as $pterm){
							$tid = (int)$pterm['term_id'];
							if(!isset($export_data['terms_data'][$tid])){
								$export_data['terms_data'][$tid] = $pterm;

/*
								// Get term language
								if(isset($sitepress) && ($term_lang = $sitepress->get_language_for_element($tid, 'tax_' . $ptax))){
									$export_data['wpml_terms'][$tid]['lang_code'] = $term_lang;
									$export_data['wpml_terms'][$tid]['orig_id'] = $sitepress->get_original_element_id($tid, 'tax_' . $ptax, false, true);
								}
*/
							}
							$export_data['posts_terms'][$post['ID']][] = $tid;
						}
					}
				}
			}

/*
			// Get post language
			if(isset($sitepress) && ($post_lang = $sitepress->get_language_for_element($post['ID'], 'post_' . $post['post_type']))){
				$export_data['wpml_posts'][$post['ID']]['lang_code'] = $post_lang;
				$export_data['wpml_posts'][$post['ID']]['orig_id'] = $sitepress->get_original_element_id($post['ID'], 'post_' . $post['post_type'], false, true);
			}
*/
		}

		if(isset($_POST['wppt_export_attachments'])){
			$export_data = wppt_export_attachments($export_data);
		}
	}

	return wppt_save_data($export_data);
}

function wppt_export_attachments($export_data){
	$attachments_export_ids = array();
	if($export_data['posts_meta']){
		foreach($export_data['posts_meta'] as $pid => $pmeta){
			if(isset($pmeta['_thumbnail_id']) && isset($pmeta['_thumbnail_id'][0])){
				$attachments_export_ids[] = $pmeta['_thumbnail_id'][0];
			}
		}
	}
	$content_urls = array();
	if($export_data['posts_data']){
		foreach($export_data['posts_data'] as $pid => $pdata){
			$post_attachments = get_posts(array('post_type' => 'attachment', 'posts_per_page' => -1, 'post_parent' => $pid));
			if($post_attachments){
				foreach($post_attachments as $p_att){
					$attachments_export_ids[] = $p_att->ID;
				}
			}
			if($urls = wppt_extract_content_urls($pdata['post_content'], true)){
				$content_urls[$pid] = $urls;
			}
		}
	}

	$attachments_all = wppt_objects_to_arrs(get_posts(array('post_type' => 'attachment', 'posts_per_page' => -1)));
	$attachments_all = wppt_posts_arr_to_id_keys($attachments_all);
	if($content_urls && $attachments_all){
		foreach($content_urls as $urls){
			foreach($urls as $url){
				foreach($attachments_all as $attach){
					$attach_guid = substr($attach['guid'], 0, strrpos($attach['guid'], '.'));
					if($attach['guid'] == $url){
						$attachments_export_ids[] = $attach['ID'];
						break 1;
					}elseif(strpos($url, $attach_guid) === 0){
						$attachment_file_sized_s = str_replace('//', '/', ABSPATH . substr($url, strlen(get_site_url())));
						$attachment_file_sized_d = wppt_get_img_dir() . substr($attachment_file_sized_s, strlen(wppt_get_uploads_basedir()));
						if(file_exists($attachment_file_sized_s) && wppt_mkdir_r(dirname($attachment_file_sized_d))){
							@copy($attachment_file_sized_s, $attachment_file_sized_d);
							$export_data['files_list'][] = $attachment_file_sized_d;
						}
						$attachments_export_ids[] = $attach['ID'];
						break 1;
					}
				}
			}
		}
	}
	$attachments_export_ids = array_values(array_unique($attachments_export_ids));

	if($attachments_export_ids && $attachments_all){
		foreach($attachments_export_ids as $aid){
			if(isset($attachments_all[$aid])){
				$export_data['attachments_data'][$aid] = $attachments_all[$aid];
			}
			if($meta = get_post_meta($aid)){
				$export_data['attachments_meta'][$aid] = $meta;
			}
		}
	}

	foreach($export_data['attachments_data'] as $adata){
		$attachment_file_s = wppt_get_uploads_basedir() . '/' . $export_data['attachments_meta'][$adata['ID']]['_wp_attached_file'][0];
		$attachment_file_d = wppt_get_img_dir() . '/' . $export_data['attachments_meta'][$adata['ID']]['_wp_attached_file'][0];
		if(file_exists($attachment_file_s) && wppt_mkdir_r(dirname($attachment_file_d))){
			@copy($attachment_file_s, $attachment_file_d);
			$export_data['files_list'][] = $attachment_file_d;
		}
	}

	return $export_data;
}

function wppt_preimport_check(){

	$check = array('summary' => array(), 'errors' => array(), 'status' => 1);

	if(!$import_data = wppt_get_data()){
		$check['errors'][] = __('Could not load data file!', 'wppt');
		$check['status'] = 0;
		return $check;
	}

	if($import_data['export_source'] == get_site_url()){
		$check['errors'][] = __('Export source is the same as destination!', 'wppt');
//		$check['status'] = 0;
//		return $check;
	}

	if(!$import_data['posts_data']){
		$check['errors'][] = __('No posts to import!', 'wppt');
		$check['status'] = 0;
		return $check;
	}

	$users = wppt_get_users();
	if(!$import_data['users_data'] || !$users){
		$check['errors'][] = __('No users to match but you can still continue with the import. Post will be assigned the current user.', 'wppt');
	}

	$check['summary']['users_source'] = $import_data['users_data'];
	$check['summary']['users_dest'] = $users;
	$check['summary']['posts_count'] = count($import_data['posts_data']);
	$check['summary']['terms_count'] = count($import_data['terms_data']);
	$check['summary']['attachments_count'] = count($import_data['attachments_data']);

	return $check;
}

add_action('admin_init', 'wppt_import_upload');
function wppt_import_upload(){
	if(wppt_is_source_uploaded() && move_uploaded_file($_FILES['wppt_import_upload']['tmp_name'], wppt_get_zip_file())
	&& file_exists(wppt_get_zip_file())){
		WP_Filesystem();
		unzip_file(wppt_get_zip_file(), wppt_get_uploads_basedir());
		unlink(wppt_get_zip_file());
	}
}

function wppt_import(){
//	global $sitepress;
	global $wpdb;

	if(!$import_progress = wppt_get_progress()){
		$import_progress = array(
									'report' => array(
														'posts_data_length' => 0,
														'posts_data_progress' => 0,
														'posts_data_progress_sys' => 0,
														'post_relations_ok' => false,

														'users_data_length' => 0,
														'users_data_progress' => 0,
														'users_data_progress_sys' => 0,

														'terms_data_length' => 0,
														'terms_data_progress' => 0,
														'terms_data_progress_sys' => 0,
														'term_relations_ok' => false,
														'assigning_terms_ok' => false,

														'attachments_data_length' => 0,
														'attachments_data_progress' => 0,
														'attachments_data_progress_sys' => 0,
														'assigning_thumbnails_ok' => false,

														'errors' => array(),

														'status' => 1,
														'finished' => false
														),
									'process' => array(
														'post_transitions' => array(),
														'post_relations' => array(),
														'term_transitions' => array(),
														'term_relations' => array(),
														'attachment_transitions' => array()
														),
									'users_match' => array()
									);
	}

	if(!$import_data = wppt_get_data()){
		$import_progress['report']['errors'][] = __('Could not load data file!', 'wppt');
		$import_progress['report']['status'] = 0;
		return $import_progress;
	}

	if($import_data['export_source'] == get_site_url()){
		$import_progress['report']['errors'][] = __('Export source is the same as destination!', 'wppt');
		$import_progress['report']['status'] = 0;
		return $import_progress;
	}

	$import_progress['report']['posts_data_length'] = count($import_data['posts_data']);
	$import_progress['report']['users_data_length'] = count($import_data['users_data']);
	$import_progress['report']['terms_data_length'] = count($import_data['terms_data']);
	$import_progress['report']['attachments_data_length'] = count($import_data['attachments_data']);

	if(!$import_progress['report']['posts_data_length']){
		$import_progress['report']['errors'][] = __('No posts to import!', 'wppt');
		$import_progress['report']['status'] = 0;
		return $import_progress;
	}

	if(isset($_POST['wppt_users_match'])){
		$import_progress['users_match'] = $_POST['wppt_users_match'];
	}

	// Import post data and post meta
	if($import_progress['report']['posts_data_progress_sys'] < $import_progress['report']['posts_data_length']){

		$loop_start = $import_progress['report']['posts_data_progress_sys'];
		foreach(wppt_get_arr_loop_sector($import_data['posts_data'], $loop_start) as $pid => $post_data){
			$post_data_new = $post_data;
			unset($post_data_new['ID']);
			if($post_data_new['post_parent']){
				$import_progress['process']['post_relations'][$pid] = $post_data_new['post_parent'];
				unset($post_data_new['post_parent']);
			}
			unset($post_data_new['guid']);
			$post_data_new['post_content'] = str_replace($import_data['export_source'], get_site_url(), $post_data_new['post_content']);

			// Create/assign authors
			$post_username = wppt_userid_to_username($post_data_new['post_author'], $import_data['users_data']);
			if($post_username && isset($import_progress['users_match'][$post_username])){
				$matched_username = $import_progress['users_match'][$post_username];

				if($matched_username == '--current_user--'){
					unset($post_data_new['post_author']);
				}elseif($matched_username != '--create_user--' && ($userid = username_exists($matched_username))){
					$post_data_new['post_author'] = $userid;
				}elseif($matched_username == '--create_user--' && !email_exists($import_data['users_data'][$post_username]['user_email'])){
					$new_user = $import_data['users_data'][$post_username];
					unset($new_user['ID']);
					unset($new_user['user_registered']);
					$pass_hash = $new_user['user_pass'];
					$new_user['user_pass'] = 'passtemp';
					if($userid_new = wp_insert_user($new_user)){
						$wpdb->update($wpdb->users, array('user_pass' => $pass_hash), array('ID' => $userid_new));
						$post_data_new['post_author'] = $userid_new;
						$import_progress['report']['users_data_progress']++;
					}else{
						unset($post_data_new['post_author']);
					}
				}elseif($matched_username == '--create_user--' && ($userid = username_exists($post_username))){
					$post_data_new['post_author'] = $userid;
				}else{
					unset($post_data_new['post_author']);
				}

				$import_progress['report']['users_data_progress_sys']++;
			}else{
				unset($post_data_new['post_author']);
				$import_progress['report']['users_data_progress_sys']++;
			}

			if($pid_new = wp_insert_post($post_data_new)){

				$import_progress['process']['post_transitions'][$pid] = $pid_new;

				if(isset($import_data['posts_meta'][$pid]) && $import_data['posts_meta'][$pid]){
					foreach($import_data['posts_meta'][$pid] as $meta_key => $post_metas){
						if($post_metas){
							foreach($post_metas as $post_meta){
								add_post_meta($pid_new, $meta_key, $post_meta);
							}
						}
					}
				}

				$import_progress['report']['posts_data_progress']++;
			}
		$import_progress['report']['posts_data_progress_sys']++;
		}

		return $import_progress;
	}

	// Setting posts child-parent relations
	if(!$import_progress['process']['post_relations']){
		$import_progress['report']['post_relations_ok'] = true;
	}elseif(!$import_progress['report']['post_relations_ok'] && $import_progress['process']['post_relations']){
		foreach($import_progress['process']['post_relations'] as $pid_child => $pid_parent){
			if(isset($import_progress['process']['post_transitions'][$pid_child]) && isset($import_progress['process']['post_transitions'][$pid_parent])){
				wp_update_post(array('ID' => $import_progress['process']['post_transitions'][$pid_child], 'post_parent' => $import_progress['process']['post_transitions'][$pid_parent]));
			}
		}

		$import_progress['report']['post_relations_ok'] = true;
		return $import_progress;
	}

	// Importing terms
	if($import_progress['report']['terms_data_progress_sys'] < $import_progress['report']['terms_data_length']){

		$loop_start = $import_progress['report']['terms_data_progress_sys'];
		foreach(wppt_get_arr_loop_sector($import_data['terms_data'], $loop_start) as $tid => $term_data){
			if(($term_data_local = get_term_by('slug', $term_data['slug'], $term_data['taxonomy'], ARRAY_A)) 
			&& $term_data_local['name'] == $term_data['name']){
				$import_progress['process']['term_transitions'][$tid] = (int)$term_data_local['term_id'];
			}else{
				$term_data_new = $term_data;
				unset($term_data_new['term_id']);
				unset($term_data_new['term_group']);
				unset($term_data_new['term_taxonomy_id']);
				if(isset($term_data_new['parent']) && $term_data_new['parent']){
					$import_progress['process']['term_relations'][$tid] = $term_data_new['parent'];
					unset($term_data_new['parent']);
				}
				unset($term_data_new['count']);
				unset($term_data_new['object_id']);
				if($tid_new = wp_insert_term($term_data_new['name'], $term_data_new['taxonomy'], $term_data_new)){
					$import_progress['process']['term_transitions'][$tid] = $tid_new;
					$import_progress['report']['terms_data_progress']++;
				}
			}
			$import_progress['report']['terms_data_progress_sys']++;
		}

		return $import_progress;
	}

	// Setting terms child-parent relations
	if(!$import_progress['process']['term_relations']){
		$import_progress['report']['term_relations_ok'] = true;
	}elseif(!$import_progress['report']['term_relations_ok'] && $import_progress['process']['term_relations']){
		foreach($import_progress['process']['term_relations'] as $tid_child => $tid_parent){
			if(isset($import_progress['process']['term_transitions'][$tid_child]) && isset($import_progress['process']['term_transitions'][$tid_parent])){
				wp_update_term($import_progress['process']['term_transitions'][$tid_child], $import_data['terms_data'][$tid_child]['taxonomy'], array('parent' => $import_progress['process']['term_transitions'][$tid_parent]));
			}
		}

		$import_progress['report']['term_relations_ok'] = true;
		return $import_progress;
	}

	// Assigning terms to the posts
	if(!$import_data['posts_terms'] || !$import_progress['process']['term_transitions']){
		$import_progress['report']['assigning_terms_ok'] = true;
	}elseif(!$import_progress['report']['assigning_terms_ok'] && $import_data['posts_terms'] && $import_progress['process']['term_transitions']){
		foreach($import_data['posts_terms'] as $pid => $post_terms){
			foreach($post_terms as $tid){
				if(isset($import_progress['process']['term_transitions'][$tid])){
					wp_set_object_terms($import_progress['process']['post_transitions'][$pid], $import_progress['process']['term_transitions'][$tid], $import_data['terms_data'][$tid]['taxonomy'], true);
				}
			}
		}

		$import_progress['report']['assigning_terms_ok'] = true;
		return $import_progress;
	}

/*
	// Assigning WPML languages to posts
	if(isset($sitepress) && $import_progress['process']['post_transitions']){
		if($import_data['wpml_posts']){
			foreach($import_data['wpml_posts'] as $pid => $wpml_post){
				$pid_new = $import_progress['process']['post_transitions'][$pid];
				$p_type = $import_data['posts_data'][$pid]['post_type'];
				$sitepress->set_elem_language_details($pid_new, 'post_' . $p_type, $sitepress->get_element_trid($pid_new, $p_type), $wpml_post['lang_code']);
			}
		}
	}

	// Assigning WPML languages to terms
	if(isset($sitepress) && $import_progress['process']['term_transitions']){
		if($import_data['wpml_terms']){
			foreach($import_data['wpml_terms'] as $tid => $wpml_term){
				$tid_new = $import_progress['process']['term_transitions'][$tid];
				$tax = $import_data['terms_data'][$tid]['taxonomy'];
				$sitepress->set_elem_language_details($tid_new, 'tax_' . $tax, $sitepress->get_element_trid($tid_new, $tax), $wpml_term['lang_code']);
			}
		}
	}
*/

	// Importing images
	if($import_progress['report']['attachments_data_progress_sys'] < $import_progress['report']['attachments_data_length']){

		$loop_start = $import_progress['report']['attachments_data_progress_sys'];
		foreach(wppt_get_arr_loop_sector($import_data['attachments_data'], $loop_start) as $aid => $attachment_data){
			$attachment_data_new = $attachment_data;
			unset($attachment_data_new['ID']);
			unset($attachment_data_new['post_author']);
			if($attachment_data_new['post_parent'] && isset($import_progress['process']['post_transitions'][$attachment_data_new['post_parent']])){
				$attachment_data_new['post_parent'] = $import_progress['process']['post_transitions'][$attachment_data_new['post_parent']];
			}
			unset($attachment_data_new['guid']);
			$attachment_file_export = wppt_get_img_dir() . '/' . $import_data['attachments_meta'][$aid]['_wp_attached_file'][0];
			$attachment_file = wppt_get_uploads_basedir() . '/' . $import_data['attachments_meta'][$aid]['_wp_attached_file'][0];
			if(file_exists($attachment_file_export) && wppt_mkdir_r(dirname($attachment_file))){
				@copy($attachment_file_export, $attachment_file);
			}
			if(file_exists($attachment_file) 
			&& ($aid_new = wp_insert_attachment($attachment_data_new, $attachment_file, $attachment_data_new['post_parent']))){

				$import_progress['process']['attachment_transitions'][$aid] = $aid_new;

				if(($attachment_metas_new = wp_generate_attachment_metadata($aid_new, $attachment_file))
				&& wp_update_attachment_metadata($aid_new, $attachment_metas_new)){
					if(isset($import_data['attachments_meta'][$aid]) && $import_data['attachments_meta'][$aid]){
						foreach($import_data['attachments_meta'][$aid] as $meta_key => $attachment_metas){
							if($meta_key != '_wp_attached_file' && $meta_key != '_wp_attachment_metadata' && $attachment_metas){
								foreach($attachment_metas as $attachment_meta){
									add_post_meta($aid, $meta_key, $attachment_meta);
								}
							}
						}
					}
				}

				$import_progress['report']['attachments_data_progress']++;
			}
			$import_progress['report']['attachments_data_progress_sys']++;
		}

		return $import_progress;
	}

	// Setting post thumbnails
	if(!$import_progress['process']['post_transitions'] || !$import_progress['process']['attachment_transitions']){
		$import_progress['report']['assigning_thumbnails_ok'] = true;
	}elseif(!$import_progress['report']['assigning_thumbnails_ok'] && $import_progress['process']['post_transitions'] && $import_progress['process']['attachment_transitions']){
		foreach($import_progress['process']['post_transitions'] as $pid => $pid_new){
			if(($attachment_id_old = wppt_get_post_meta($pid, '_thumbnail_id', $import_data['attachments_meta']))
			&& isset($import_progress['process']['attachment_transitions'][$attachment_id_old])){
				update_post_meta($pid_new, '_thumbnail_id', $import_progress['process']['attachment_transitions'][$attachment_id_old]);
			}
		}

		$import_progress['report']['assigning_thumbnails_ok'] = true;
		return $import_progress;
	}

	$import_progress['report']['finished'] = true;
	return $import_progress;
}
