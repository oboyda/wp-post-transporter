<?php
/*
Plugin Name: WP Post Transporter
Description: This plugin transports posts.
Version:     1.0
Author:      Oleksiy Boyda
License:     GPL2
Domain Path: /languages
Text Domain: wppt
*/

add_action('plugins_loaded', 'wppt_load_textomain');

function wppt_load_textomain(){
	load_plugin_textdomain('wppt', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('admin_enqueue_scripts', 'wppt_enqueue_admin_scripts');

function wppt_enqueue_admin_scripts($hook){	
	if($hook != 'tools_page_wppt-tool'){
		return;
	}
	wp_enqueue_style('wppt-admin-css', plugins_url('/admin.css', __FILE__), array());
	wp_enqueue_script('wppt-admin-js', plugins_url('/admin.js', __FILE__), array('jquery'));
}

require(dirname(__FILE__) . '/helpers.php');
require(dirname(__FILE__) . '/helpers-html.php');
require(dirname(__FILE__) . '/admin.php');
require(dirname(__FILE__) . '/admin-actions.php');
