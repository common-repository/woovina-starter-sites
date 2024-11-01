<?php
/**
 * Plugin Name: WooVina Starter Sites
 * Plugin URI: http://woovina.com
 * Description: Import free starter sites build with WordPress, WooCommerce, Elementor and WooVina theme.
 * Version: 1.2.0
 * Author: WooVina Team
 * Author URI: https://woovina.com
 * Text Domain: woovina-sites
 *
 * @package WooVina Sites
 */

if(!function_exists('woovina_sites_pro_setup')) :

	/**
	 * Set constants.
	 */
	if(! defined('WOOVINA_SITES_NAME')) {
		define('WOOVINA_SITES_NAME', __('WooVina Starter Sites', 'woovina-sites'));
	}

	if(! defined('WOOVINA_SITES_VER')) {
		define('WOOVINA_SITES_VER', '1.2.0' );
	}

	if(! defined('WOOVINA_SITES_FILE')) {
		define('WOOVINA_SITES_FILE', __FILE__);
	}

	if(! defined('WOOVINA_SITES_BASE')) {
		define('WOOVINA_SITES_BASE', plugin_basename(WOOVINA_SITES_FILE));
	}

	if(! defined('WOOVINA_SITES_DIR')) {
		define('WOOVINA_SITES_DIR', plugin_dir_path(WOOVINA_SITES_FILE));
	}

	if(! defined('WOOVINA_SITES_URI')) {
		define('WOOVINA_SITES_URI', plugins_url('/', WOOVINA_SITES_FILE));
	}
	
	/**
	 * WooVina Sites Setup
	 *
	 * @since 1.0.0
	 */
	function woovina_sites_setup() {
		require_once WOOVINA_SITES_DIR . 'inc/demos.php';
		
		// Activate License Key
		if(class_exists('WooVina_Theme_Licenses') && !defined('WOOVINA_SINGLE_PACKAGE')) {
			$license = new WooVina_Theme_Licenses('Starter Sites', 'Starter Sites');		
		}
	}
	add_action('plugins_loaded', 'woovina_sites_setup');

endif;
