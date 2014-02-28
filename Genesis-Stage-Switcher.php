<?php
/*
Plugin Name:  Genesis Stage Switcher
Plugin URI:   https://github.com/jameswlane/Genesis-Stage-Switcher
Description:  A WordPress plugin that allows you to switch between different environments from the admin bar.
Version:      1.1.0
Author:       James W. Lane
Author URI:   http://jameswlane.com/
License:      MIT License
*/

/**
 * Inspired by http://37signals.com/svn/posts/3535-beyond-the-default-rails-environments
 */

/* ------------------------------------------------------------------------ *
 * Defining Directories
 * ------------------------------------------------------------------------ */

// Get the root directory
$genesis_directory = dirname($_SERVER['DOCUMENT_ROOT']);

// Using the root directory set the staging directory
$stages_directory = $genesis_directory . '/deployment/stages';

// Using the root directory set the git directory
$git_directory = $genesis_directory . '/.git/refs/remotes/origin';

/* ------------------------------------------------------------------------ *
 * Defining strpos and preg_match patterns
 * ------------------------------------------------------------------------ */

// Set the string we will be looking for
$string = 'role :web,';

// Set the preg_match pattern to find the web url
$pattern = '/(role :web,) + \"(.*?)\"/u';

// Set the preg_match pattern to find the enviroment name
$pattern2 = '/(set :stage,) + \"(.*?)\"/u';

/* ------------------------------------------------------------------------ *
 * Defining arrays
 * ------------------------------------------------------------------------ */

// Define our enviroment array ( including branches )
$envs = array();

// Define our enviroment array ( excluding branches )
$envs2 = array();

// Define our git branches array
$git_branches = array();

/* ------------------------------------------------------------------------ *
 * Building enviroment array from /deployment/stages/ files
 * ------------------------------------------------------------------------ */

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
		$envs2[$name] = $address;
    }
}

/* ------------------------------------------------------------------------ *
 * Building git branches array from /.git/refs/remotes/origin/ files
 * ------------------------------------------------------------------------ */

// Read the directory and make a array of branches
if (is_dir($git_directory)) {
    if ($dh = opendir($git_directory)) {
        while (($file = readdir($dh)) !== false) {
			// If the file is the . or .. or HEAD dont add to array
			if ( '.' == $file || '..' == $file || 'HEAD' == $file ) {
				// Do nothing
			} else {
				$git_branches[] = $file;
			}
        }
        closedir($dh);
    }
}

/* ------------------------------------------------------------------------ *
 * Building enviroment array with git branches included
 * ------------------------------------------------------------------------ */


$options = get_option( 'gss_stage_setting' );

$set_staging_server = $options['staging_env'];

if ( !empty( $git_directory ) ) {
	if (isset($set_staging_server)) {
		foreach ($envs as $name => $url) {
			if ($name !== $set_staging_server) {
				continue;
			}
			foreach($git_branches as $branch => $twig) {
				$address2 = $twig . '.' . $url;
				$envs[$twig] = $address2;
			}
		}
	}
}

// Define ENVIRONMENTS and serialize the enviroment array
define('ENVIRONMENTS', serialize($envs));
define('ENVIRONMENTS2', serialize($envs2));

/* ------------------------------------------------------------------------ *
 * Building WordPress menu topbar
 * ------------------------------------------------------------------------ */

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

/* ------------------------------------------------------------------------ *
 * Starting the plugins settings page
 * ------------------------------------------------------------------------ */

 /**
 * Provides default values for the staging options.
 */
function gss_default_options() {
	$defaults = array(
		'staging_env'		=>	'staging'
	);
return apply_filters( 'gss_default_options', $defaults );
} // end gss_default_input_options

/**
 * This function builds the settings menu
 */
add_action( 'admin_menu', 'gss_menu' );
function gss_menu() {
	add_menu_page(
		'Genesis Stage Switcher Settings', 	// The title to be displayed in the browser window for this page.
		'GSS Settings',						// The text to be displayed for this menu item
		'administrator',					// Which type of users can see this menu item
		'gss_options',			// The unique ID - that is, the slug - for this menu item
		'gss_display'				// The name of the function to call when rendering this menu's page
	);
} // end gss_menu


/**
 * Renders a simple page to display for the theme menu defined above.
 */
function gss_display( ) {
?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
		<div id="icon-themes" class="icon32"></div>
		<h2><?php _e( 'Genesis Stage Switcher Settings', 'genesis-stage-switcher' ); ?></h2>
		<?php settings_errors(); ?>
		<?php gss_input_callback(); ?>
		<form method="post" action="options.php">
			<?php
				settings_fields( 'gss_stage_setting' );
				do_settings_sections( 'gss_stage_setting' );
				gss_select_element_callback();
				submit_button();
			?>
		</form>
	</div><!-- /.wrap -->
<?php
} // end gss_display

/* ------------------------------------------------------------------------ *
 * Setting Registration
 * ------------------------------------------------------------------------ */
function gss_initialize_input_examples() {
	add_settings_field(
		'Select Element',
		__( 'Select Element', 'genesis-stage-switcher' ),
		'gss_select_element_callback',
		'gss_stage_setting',
		'input_examples_section'
	);

/* ------------------------------------------------------------------------ *
 *
 * ------------------------------------------------------------------------
 *
 * ------------------------------------------------------------------------
 * Fix Setting Unregisteration to remove database entries
 * ------------------------------------------------------------------------
 *
 * ------------------------------------------------------------------------
 *
 * ------------------------------------------------------------------------ */
	register_setting(
		'gss_stage_setting',
		'gss_stage_setting',
		'gss_validate_input_examples'
	);

} // end gss_initialize_input_examples
add_action( 'admin_init', 'gss_initialize_input_examples' );

/* ------------------------------------------------------------------------ *
 * Section Callbacks
 * ------------------------------------------------------------------------ */
function gss_input_callback() {
	echo '<p>' . __( 'Select your staging server.', 'genesis-stage-switcher' ) . '</p>';
} // end gss_input_callback

/* ------------------------------------------------------------------------ *
 * Field Callbacks
 * ------------------------------------------------------------------------ */
function gss_select_element_callback() {
	$options = get_option( 'gss_stage_setting' );
	$html = '<select id="staging_env" name="gss_stage_setting[staging_env]">';
	    if ( defined('ENVIRONMENTS2') ) {
			$stages = unserialize(ENVIRONMENTS2);
			foreach($stages as $stage => $url) {
				$html .= '<option value="' . $stage . '"' . selected( $options['staging_env'], $stage, false) . '>' . __( ucwords($stage), 'genesis-stage-switcher' ) . '</option>';
			}
		} else {
				$html .= '<option value="staging">' . __( 'Staging', 'genesis-stage-switcher' ) . '</option>';
		}
	$html .= '</select>';
	echo $html;
} // end gss_select_element_callback

/* ------------------------------------------------------------------------ *
 * Setting Callbacks
 * ------------------------------------------------------------------------ */
function gss_validate_input_examples( $input ) {

	// Create our array for storing the validated options
	$output = array();

	// Loop through each of the incoming options
	foreach( $input as $key => $value ) {

		// Check to see if the current option has a value. If so, process it.
		if( isset( $input[$key] ) ) {

			// Strip all HTML and PHP tags and properly handle quoted strings
			$output[$key] = strip_tags( stripslashes( $input[ $key ] ) );

		} // end if

	} // end foreach

	// Return the array processing any additional functions filtered by this action
	return apply_filters( 'gss_validate_input_examples', $output, $input );

} // end gss_validate_input_examples

?>