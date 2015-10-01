<?php

add_action('admin_menu', 'wppt_add_submenu_page');
function wppt_add_submenu_page(){
	add_management_page(
		'WP Post Transporter',
		'WP Post Transporter',
		'manage_options',
		'wppt-tool',
		'wppt_display_tool_page'
	);
}

function wppt_display_tool_page(){ ?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"></div>
		<h1><?php _e('WP Post Transporter', 'wppt'); ?></h1>
		<table id="wppt-actions-table" class="form-table"><tbody>
			<tr>
				<th scope="row"><?php _e('Export data', 'wppt'); ?></th>
				<td><?php wppt_display_export_btn(); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Import data', 'wppt'); ?></th>
				<td><?php wppt_display_import_btn(); ?></td>
			</tr>
		</tbody></table>
	</div>
	<?php
}

function wppt_display_export_btn(){ ?>
	<form id="wppt-export-options" method="POST">
		<?php if($export_types = wppt_get_valid_export_types()){ ?>
		<p>
			<select name="wppt_export_type">
				<?php foreach($export_types as $post_type_code => $post_type){ ?>
					<option value="<?php echo $post_type_code; ?>"><?php echo $post_type_code . ' (' . $post_type->labels->name . ')'; ?></option>
				<?php } ?>
			</select>
		</p>
		<?php } ?>
		<p>
			<input type="checkbox" name="wppt_export_attachments" value="1" /> <?php _e('Export attachments', 'wppt'); ?>
		</p>
		<p>
			<select name="wppt_export_dest">
				<option value="download"><?php _e('Download', 'wppt'); ?></option>
				<option value="leave"><?php _e('Leave on server uncompressed', 'wppt'); ?></option>
				<option value="transport" disabled><?php _e('Prepare for transfer (easy way)', 'wppt'); ?></option>
			</select>
		</p>
		<p>
			<input type="hidden" name="action" value="wppt_export" />
			<button id="wppt-export-btn" class="button" type="button"><?php _e('Export', 'wppt'); ?><span id="wppt-export-loader" style="display: none;"> ...</span></button>
		</p>
	</form>
	<div id="wppt-export-results"></div>
	<?php
}

function wppt_display_import_btn(){ ?>
	<form id="wppt-import-options" action="<?php echo admin_url('tools.php?page=wppt-tool'); ?>" method="POST" enctype="multipart/form-data">
		<p>
			<select id="wppt-import-source" name="wppt_import_source">
				<option value="upload"><?php echo __('Upload', 'wppt') . ' (' . ini_get('upload_max_filesize') . ' max)'; ?></option>
				<option value="server"><?php _e('From server location', 'wppt'); ?></option>
				<option value="transfer" disabled><?php _e('Transfer (easy way)', 'wppt'); ?></option>
			</select>
		</p>
		<p>
			<input type="file" id="wppt-import-upload" name="wppt_import_upload" class="import-input" />

			<?php
			$server_input_title = __('Import data found in', 'wppr') . ': ' . wppt_get_file_rel_abs(wppt_get_uploads_dir());
			if(!file_exists(wppt_get_data_file())){
				$server_input_title = __('No import data found in', 'wppr') . ': ' . wppt_get_file_rel_abs(wppt_get_uploads_dir());
			} ?>
			<span id="wppt-import-server" class="import-input hidden"><?php echo $server_input_title; ?></span>
			<input type="text" id="wppt-import-transfer" name="wppt_import_key" class="import-input hidden" placeholder="<?php _e('Paste import key', 'wppt'); ?>" />

			<input type="hidden" name="action" value="wppt_preimport" />
			<button id="wppt-preimport-btn" class="button" type="submit"><?php _e('Import', 'wppt'); ?><span id="wppt-preimport-loader" style="display: none;"> ...</span></button>
		</p>
	</form>
	<div id="wppt-import-results">
	<?php 
	if($import_progress = wppt_get_progress()){
		echo wppt_get_import_progress_html($import_progress);
	}elseif(wppt_is_source_uploaded()){
		echo wppt_get_preimport_html(wppt_preimport_check());
	}
	?>
	</div>

	<?php
		$import_btn_title = __('Ready and import', 'wppt');
		$import_btn_hidden_class = ' hidden';
		if(wppt_get_progress()){
			$import_btn_title = __('Continue importing', 'wppt');
			$import_btn_hidden_class = '';
		}elseif(wppt_is_source_uploaded()){
			$import_btn_hidden_class = '';
		}
	?>
	<p>
		<button id="wppt-import-btn" class="button button-primary<?php echo $import_btn_hidden_class; ?>" type="button"><?php echo $import_btn_title; ?><span id="wppt-import-loader" style="display: none;"> ...</span></button>
	</p>
	<?php
}
