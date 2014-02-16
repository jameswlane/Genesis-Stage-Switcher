<?php
/*
Plugin Name:  Genesis Stage Switcher
Plugin URI:   https://github.com/jameswlane/Genesis-Stage-Switcher
Description:  A WordPress plugin that allows you to switch between different environments from the admin bar.
Version:      1.0.0
Author:       James W. Lane
Author URI:   http://jameswlane.com/
License:      MIT License
*/
/**
 * Inspired by http://37signals.com/svn/posts/3535-beyond-the-default-rails-environments
 */
// Get the root directory
$genesis_directory = dirname($_SERVER['DOCUMENT_ROOT']);
// Using the root directory set the staging directory
$stages_directory    = $genesis_directory . '/deployment/stages';
// Set the string we will be looking for
$string = 'role :web,';
// Set the preg_match pattern to find the web url
$pattern = '/(role :web,) + \"(.*?)\"/u';
// Set the preg_match pattern to find the enviroment name
$pattern2 = '/(set :stage,) + \"(.*?)\"/u';
// Define our enviroment array
$envs = array();
// Loop through all the files in staging
foreach (glob("$stages_directory/*") as $file) {
	// Read the file and set its content as $content
    $content = file_get_contents("$file");
	// If the file contains our $string
    if (strpos($content, $string) !== false) {
		// Get the url via preg_match
		preg_match($pattern, $content, $matches);
		// Set the URL as a varible
		$address = $matches[2];
		// Get the enviroment name via preg_match
		preg_match($pattern2, $content, $matches);
		// Set the enviroment name as a varible
		$name = $matches[2];
		// Add the enviroment name and url as an entry to the enviroment array
		$envs[$name] = $address;

    }
}
// Define ENVIRONMENTS and serialize the enviroment array
define('ENVIRONMENTS', serialize($envs));

function admin_bar_stage_switcher($admin_bar) {
	//	If ENVIRONMENTS and WP_ENV are defined
	if (defined('ENVIRONMENTS') && defined('WP_ENV')) {
		// Set $stages as a unserialize array
		$stages = unserialize(ENVIRONMENTS);
		// Set $current_stage as the WP_ENV varible
		$current_stage = WP_ENV;
	} else {
		return;
	}
	// Add the $current_stage as a parent menu item to the WordPress admin top-bar
	$admin_bar->add_menu(array(
		'id'     => 'environment',
		'parent' => 'top-secondary',
		'title'  => ucwords($current_stage),
		'href'   => '#'
	));
	// Generate and add the child menu items to the parent menu item to the WordPress admin top-bar
	foreach($stages as $stage => $url) {
		if ($stage === $current_stage) {
			continue;
		}
		$url .= $_SERVER['REQUEST_URI'];
		$admin_bar->add_menu(array(
			'id'     => $stage,
			'parent' => 'environment',
			'title'  => ucwords($stage),
			'href'   => $url
		));
	}
}
add_action('admin_bar_menu', 'admin_bar_stage_switcher');