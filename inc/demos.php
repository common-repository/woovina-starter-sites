<?php
/**
 * Demos
 *
 * @package WooVina_Sites
 * @category Core
 * @author WooVina
 */

// Exit if accessed directly
if(! defined('ABSPATH')) {
	exit;
}

// Start Class
if(! class_exists('WooVina_Sites_Demos')) {

	class WooVina_Sites_Demos {
				
		public $woovina_theme;
		public $woovina_plugins;
		public $woovina_activate;
		
		/**
		 * Start things up
		 */
		public function __construct() {

			// Return if not in admin
			if(! is_admin() || is_customize_preview()) {
				return;
			}
			
			// Display message to deactivate WooVina Starter Sites (free)
			add_action('admin_notices', array($this, 'deactivate_notice'));
			
			if(defined('WOOVINA_SINGLE_PACKAGE')) return;
			
			// Define variables
			$this->woovina_theme   = 'https://woovina.com/free-downloads/woovina.zip';
			$this->woovina_plugins = array(
				array(
					'name'					=> 'WooVina Extra',
					'slug'					=> 'woovina-extra',
					'source'    			=> 'https://woovina.com/free-downloads/woovina-extra.zip',
					'external_url'    		=> 'https://woovina.com/extensions/woovina-extra',
				),
				
				array(
					'name'					=> 'WooVina Elementor Widgets',
					'slug'					=> 'woovina-elementor-widgets',
					'source'    			=> 'https://woovina.com/free-downloads/woovina-elementor-widgets.zip',
					'external_url'    		=> 'https://woovina.com/extensions/woovina-elementor-widgets',

				),
				
				array(
					'name'					=> 'WooVina Custom Sidebar',
					'slug'					=> 'woovina-custom-sidebar',
					'source'    			=> 'https://woovina.com/free-downloads/woovina-custom-sidebar.zip',
					'external_url'    		=> 'https://woovina.com/extensions/woovina-custom-sidebar',
				),
			);
			$this->woovina_activate = true;
			
			// Import demos page
			if(version_compare(PHP_VERSION, '5.4', '>=')) {
				require_once(WOOVINA_SITES_DIR .'inc/classes/importers/class-helpers.php');
				require_once(WOOVINA_SITES_DIR .'inc/classes/class-install-demos.php');
			}

			// Disable Woo Wizard
			add_filter('woocommerce_enable_setup_wizard', '__return_false');
			add_filter('woocommerce_show_admin_notice', '__return_false');
			add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_false');

			// Start things
			add_action('admin_init', array($this, 'init'));

			// Demos scripts
			add_action('admin_enqueue_scripts', array($this, 'scripts'));

			// Allows xml uploads
			add_filter('upload_mimes', array($this, 'allow_xml_uploads'));

			// Demos popup
			add_action('admin_footer', array($this, 'popup'));
			
			// Core install & active status
			add_action('admin_init', array($this, 'woovina_core_status'));
			
			// Display notice if the WooVina theme and plugins aren't activated
			add_action('admin_notices', array($this, 'require_notice'));
			
			// Display activate license key notice message
			add_action('admin_notices', array($this, 'activate_notice'));
						
		}

		/**
		 * Register the AJAX methods
		 *
		 * @since 1.0.0
		 */
		public function init() {

			// Demos popup ajax
			add_action('wp_ajax_woovina_ajax_get_demo_data', array($this, 'ajax_demo_data'));
			add_action('wp_ajax_woovina_ajax_required_plugins_activate', array($this, 'ajax_required_plugins_activate'));

			// Get data to import
			add_action('wp_ajax_woovina_ajax_get_import_data', array($this, 'ajax_get_import_data'));

			// Import XML file
			add_action('wp_ajax_woovina_ajax_import_xml', array($this, 'ajax_import_xml'));

			// Import customizer settings
			add_action('wp_ajax_woovina_ajax_import_theme_settings', array($this, 'ajax_import_theme_settings'));

			// Import widgets
			add_action('wp_ajax_woovina_ajax_import_widgets', array($this, 'ajax_import_widgets'));
			
			// Import forms
			add_action('wp_ajax_woovina_ajax_import_forms', array($this, 'ajax_import_forms'));
			
			// After import
			add_action('wp_ajax_woovina_after_import', array($this, 'ajax_after_import'));
			
			// Install WooVina
			add_action('wp_ajax_woovina_ajax_install_theme', array($this, 'ajax_install_woovina'));
			
			// Install Premium Plugins
			add_action('wp_ajax_woovina_ajax_install_plugin', array($this, 'ajax_install_plugin'));
		}
		
		/**
		 * Check status of WooVina Theme & Plugins
		 *
		 * @since 1.0.0
		 */
		public function woovina_core_status() {
			// Check Install & Activate Theme
			$theme 				= wp_get_theme();
			$woovina_theme_path = WP_CONTENT_DIR . '/themes/woovina/style.css';
			if(!file_exists($woovina_theme_path) || ('WooVina' != $theme->name && 'WooVina' != $theme->parent_theme)) {
				$this->woovina_activate = true;
			}
			
			// Check Install & Activate Core Plugins
			for($i = 0; $i < count($this->woovina_plugins); $i++) {
				$plugin = $this->woovina_plugins[$i];
				$slug	= $plugin['slug'];
				$pPath  = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
				
				if(!file_exists($pPath)) $this->woovina_activate = false;
				
				if(is_plugin_inactive($slug . '/' . $slug . '.php')) $this->woovina_activate = false;
			}
			
			return $this->woovina_activate;
		}
		
		/**
		 * AJAX Install WooVina Theme & Plugins
		 *
		 * @since 1.0.0
		 */
		public function ajax_install_woovina() {			
			
			if(!current_user_can('install_plugins') || !current_user_can('install_themes') || $this->woovina_activate) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => __('No plugin specified', 'woovina-sites'),
					)
				);
			};
			
			require_once ABSPATH .'wp-admin/includes/misc.php';
			require_once ABSPATH .'wp-admin/includes/file.php';
			require_once ABSPATH .'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH .'wp-admin/includes/class-plugin-upgrader.php';
			require_once ABSPATH .'wp-admin/includes/class-theme-upgrader.php';
			
			// Install & Activate Plugins
			for($i = 0; $i < count($this->woovina_plugins); $i++) {
				$plugin = $this->woovina_plugins[$i];
				$slug	= $plugin['slug'];
				$source = $plugin['source'];
				$pPath  = WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php';
				
				if(!file_exists($pPath)) {
					$upgrader = new Plugin_Upgrader(new Plugin_Installer_Skin(compact('type', 'title', 'nonce', 'url')));
					$result   = $upgrader->install($source);					
				}
				
				activate_plugin($pPath);
			}			
			
			// Install & Activate WooVina Theme
			$woovina_theme_path = WP_CONTENT_DIR . '/themes/woovina/style.css';
			if(!file_exists($woovina_theme_path)) {
				$upgrader = new Theme_Upgrader(new Theme_Installer_Skin(compact('type', 'title', 'nonce', 'url')));
				$upgrader->install($this->woovina_theme);
			}
			
			if('WooVina' != $theme->name || 'WooVina' != $theme->parent_theme) {
				switch_theme('woovina');
			}
			
			$this->woovina_activate = true;
			
			wp_send_json_success(
				array(
					'success' => true,
					'message' => __('Plugin Successfully Activated', 'woovina-sites'),
				)
			);
		}
		
		/**
		 * AJAX Install WooVina Premium Plugins
		 *
		 * @since 1.0.4
		 */
		public function ajax_install_plugin() {
			if(! current_user_can('install_plugins') || ! isset($_POST['init']) || ! $_POST['init']) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => __('No plugin specified', 'woovina-sites'),
					)
				);
			}

			require_once ABSPATH .'wp-admin/includes/misc.php';
			require_once ABSPATH .'wp-admin/includes/file.php';
			require_once ABSPATH .'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH .'wp-admin/includes/class-plugin-upgrader.php';
			require_once ABSPATH .'wp-admin/includes/class-theme-upgrader.php';
			
			$license 			= get_option('edd_license_details');
			$license_details 	= (isset($license) && isset($license['woovina_starter_sites'])) ? $license['woovina_starter_sites'] : false;
			$license_key		= $license_details->checksum;
			
			$plugin_slug = (isset($_POST['slug'])) ? esc_attr($_POST['slug']) : '';
			$plugin_init = (isset($_POST['init'])) ? esc_attr($_POST['init']) : '';
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_init;
			
			if(!file_exists($plugin_path)) {
				
				// Data to send to the API
				$api_params = array(
					'edd_action'  => 'install_plugin',
					'license'     => $license_key,					
					'plugin_slug' => $plugin_slug,
					'url'         => home_url()
				);
				
				// Call the API
				$response = wp_remote_post(
					'https://woovina.com/',
					array(
						'timeout'   => 15,
						'sslverify' => false,
						'body'      => $api_params
					)
				);
								
				$response_data = json_decode(wp_remote_retrieve_body($response));
				$plugin_source = $response_data->download_url;
				
				if(is_wp_error($response) || empty($plugin_source)) {
					wp_send_json_error(
						array(
							'success' => false,
							'message' => __('No plugin specified', 'woovina-sites'),
						)
					);
				}
				
				$upgrader = new Plugin_Upgrader(new Plugin_Installer_Skin(compact('type', 'title', 'nonce', 'url')));
				$result   = $upgrader->install($plugin_source);				
			}
			
			activate_plugin($plugin_path);
			
			wp_send_json_success(
				array(
					'success' => true,
					'message' => __('Plugin Successfully Activated', 'woovina-sites'),
				)
			);
		}
		
		/**
		 * Load scripts
		 *
		 * @since 1.0
		 */
		public function scripts($hook_suffix) {

			if('appearance_page_woovina-starter-sites' == $hook_suffix) {

				// CSS
				wp_enqueue_style('woovina-demos-style', plugins_url('/assets/css/demos.min.css', __FILE__));

				// JS
				wp_enqueue_script('woovina-demos-js', plugins_url('/assets/js/demos.min.js', __FILE__), array('jquery', 'wp-util', 'updates'), '1.0', true);

				wp_localize_script('woovina-demos-js', 'woovinaDemos', array(
					'ajaxurl' 					=> admin_url('admin-ajax.php'),
					'demo_data_nonce' 			=> wp_create_nonce('get-demo-data'),
					'woovina_import_data_nonce' => wp_create_nonce('woovina_import_data_nonce'),
					'content_importing_error' 	=> esc_html__('There was a problem during the importing process resulting in the following error from your server:', 'woovina-sites'),
					'button_activating' 		=> esc_html__('Activating', 'woovina-sites') . '&hellip;',
					'button_active' 			=> esc_html__('Active', 'woovina-sites'),
				));

			}

		}

		/**
		 * Allows xml uploads so we can import from github
		 *
		 * @since 1.0.0
		 */
		public function allow_xml_uploads($mimes) {
			$mimes = array_merge($mimes, array(
				'xml' 	=> 'application/xml'
			));
			return $mimes;
		}

	    /**
	     * Display notice if the Demo Import and Pro Demos are activatede
	     *
		 * @since 1.0
	     */
	    public function require_notice() {
	    	global $pagenow;

	        if(! current_user_can('manage_options') || $this->woovina_activate) {
	            return;
	        }

	        // Display on the plugins and demos pages
	        if('themes.php' === $pagenow && (isset($_GET['page']) && 'woovina-starter-sites' == $_GET['page'] && !$this->woovina_activate)) {
		    ?>
		        
			<div class="notice notice-warning woovina-demos-notice">
				<p><?php echo sprintf(
					esc_html__('%1$sWooVina Theme & Plugins%2$s needs to be install & active for you to use currently installed %3$s"WooVina Starter Sites"%4$s plugin. %5$sInstall & Activate Now%6$s', 'woovina-sites'),
					'<strong>', '</strong>',
					'<strong>', '</strong>',
					'<a class="install-woovina-package button button-primary" href="#">', '</a>'
					); ?></p>
			</div>

	    	<?php
	    	}
	    }
		
		/**
	     * Display notice if customer import a demo (need activate license)
	     *
		 * @since 1.0
	     */
	    public function activate_notice() {			
			$license 			= get_option('edd_license_details');
			$license_details 	= (isset($license) && isset($license['woovina_starter_sites'])) ? $license['woovina_starter_sites'] : false;
			
			if(!$this->woovina_activate || 'niche-00.css' == get_theme_mod('woovina_css_file') || ($license_details && $license_details->success)) {
	            return;
	        }
			
			?>
			<div id="woovina-admin-notice" class="updated notice is-dismissible woovina-demos-notice" style="padding-top: 10px;">
				<strong><?php _e('Thanks for using WooVina Starter Sites', 'woovina-sites'); ?></strong>
				<p><?php _e('Please activate your license to get feature updates, premium support and branding/copyright removal!', 'woovina-sites'); ?>
					<br><?php echo sprintf(
						__('If you don\'t have any license key, you can %1$sget a FREE license here%2$s.', 'woovina-sites'),
						'<a href="https://woovina.com/member/register" target="_blank" title="Click to get FREE license key!">',
						'</a>'
					); ?></p>
				<p><a class="btn button-primary" href="<?php echo admin_url('admin.php?page=woovina-panel-licenses'); ?>"><?php _e('Activate Now', 'woovina-sites'); ?></a></p>
			</div>
			<?php
		}
		
		/**
	     * Display notice message if free version is activate
	     *
		 * @since 1.0
	     */
	    public function deactivate_notice() {		
			if(!defined('WOOVINA_SINGLE_PACKAGE')) {
	            return;
	        }
			?>
			<div class="notice notice-warning woovina-demos-notice">
				<p><?php echo sprintf(
					esc_html__('You have installed a Single Demo Package. So please deactivate the plugin %1$sWooVina Starter Sites%2$s %3$sDeactivate Now%4$s', 'woovina-sites'),
					'<strong>', '</strong>',
					'<a class="button button-primary" href="'.admin_url('plugins.php?s=WooVina+Starter+Sites&plugin_status=all').'">', '</a>'
					); ?></p>
			</div>
		<?php
		}
		
		/**
		 * Get demos data to add them in the Demo Import and Pro Demos plugins
		 *
		 * @since 1.0
		 */
		public static function get_demos_data() {

			// Demos url
			$url  = WOOVINA_SITES_URI . 'demos/';
			$dir  = WOOVINA_SITES_DIR . 'demos/';
			$data = array(
				'Faster' => array(
					'categories'        => array('FREE', 'Clothing Fashion', 'Electronics', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_30_contents.xml',
					'theme_settings' 	=> $url . 'niche_30_customizer.json',
					'widgets_file'  	=> $url . 'niche_30_widgets.wie',
					'form_file'  		=> $url . 'niche_30_form.json',
					'preview_image'		=> $url . 'niche-30.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-30/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-30.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(		
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),					
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'eBuilder' => array(
					'categories'        => array('FREE', 'Home Garden'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_29_contents.xml',
					'theme_settings' 	=> $url . 'niche_29_customizer.json',
					'widgets_file'  	=> $url . 'niche_29_widgets.wie',
					'form_file'  		=> $url . 'niche_29_form.json',
					'preview_image'		=> $url . 'niche-29.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-29/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-29.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '450',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(		
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),					
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
						),
					),
				),

				'Afela' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_28_contents.xml',
					'theme_settings' 	=> $url . 'niche_28_customizer.json',
					'widgets_file'  	=> $url . 'niche_28_widgets.wie',
					'form_file'  		=> $url . 'niche_28_form.json',
					'preview_image'		=> $url . 'niche-28.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-28/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-28.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'Sofasy' => array(
					'categories'        => array('FREE', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_27_contents.xml',
					'theme_settings' 	=> $url . 'niche_27_customizer.json',
					'widgets_file'  	=> $url . 'niche_27_widgets.wie',
					'form_file'  		=> $url . 'niche_27_form.json',
					'preview_image'		=> $url . 'niche-27.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-27/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-27.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '450',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'T90' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_26_contents.xml',
					'theme_settings' 	=> $url . 'niche_26_customizer.json',
					'widgets_file'  	=> $url . 'niche_26_widgets.wie',
					'form_file'  		=> $url . 'niche_26_form.json',
					'preview_image'		=> $url . 'niche-26.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-26/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-26.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'Amadea' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_25_contents.xml',
					'theme_settings' 	=> $url . 'niche_25_customizer.json',
					'widgets_file'  	=> $url . 'niche_25_widgets.wie',
					'form_file'  		=> $url . 'niche_25_form.json',
					'preview_image'		=> $url . 'niche-25.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-25/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-25.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'Genius' => array(
					'categories'        => array('FREE', 'Furniture', 'Electronics', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_24_contents.xml',
					'theme_settings' 	=> $url . 'niche_24_customizer.json',
					'widgets_file'  	=> $url . 'niche_24_widgets.wie',
					'form_file'  		=> $url . 'niche_24_form.json',
					'preview_image'		=> $url . 'niche-24.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-24/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-24.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'Pomer' => array(
					'categories'        => array('FREE', 'Health Beauty', 'Jewelry Accessories'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_23_contents.xml',
					'theme_settings' 	=> $url . 'niche_23_customizer.json',
					'widgets_file'  	=> $url . 'niche_23_widgets.wie',
					'form_file'  		=> $url . 'niche_23_form.json',
					'preview_image'		=> $url . 'niche-23.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-23/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-23.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'Cendo' => array(
					'categories'        => array('FREE', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_22_contents.xml',
					'theme_settings' 	=> $url . 'niche_22_customizer.json',
					'widgets_file'  	=> $url . 'niche_22_widgets.wie',
					'form_file'  		=> $url . 'niche_22_form.json',
					'preview_image'		=> $url . 'niche-22.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-22/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-22.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '450',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),				
				
				'Pet Shop' => array(
					'categories'        => array('FREE', 'Other'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_21_contents.xml',
					'theme_settings' 	=> $url . 'niche_21_customizer.json',
					'widgets_file'  	=> $url . 'niche_21_widgets.wie',
					'form_file'  		=> $url . 'niche_21_form.json',
					'preview_image'		=> $url . 'niche-21.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-21/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-21.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),

				'Alice Mart' => array(
					'categories'        => array('FREE', 'Electronics', 'Clothing Fashion', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_20_contents.xml',
					'theme_settings' 	=> $url . 'niche_20_customizer.json',
					'widgets_file'  	=> $url . 'niche_20_widgets.wie',
					'form_file'  		=> $url . 'niche_20_form.json',
					'preview_image'		=> $url . 'niche-20.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-20/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-20.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Beta Shop' => array(
					'categories'        => array('FREE', 'Electronics'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_19_contents.xml',
					'theme_settings' 	=> $url . 'niche_19_customizer.json',
					'widgets_file'  	=> $url . 'niche_19_widgets.wie',
					'form_file'  		=> $url . 'niche_19_form.json',
					'preview_image'		=> $url . 'niche-19.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-19/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-19.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Book Shop' => array(
					'categories'        => array('FREE', 'Other'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_18_contents.xml',
					'theme_settings' 	=> $url . 'niche_18_customizer.json',
					'widgets_file'  	=> $url . 'niche_18_widgets.wie',
					'form_file'  		=> $url . 'niche_18_form.json',
					'preview_image'		=> $url . 'niche-18.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-18/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-18.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '259',
					'woo_crop_height'	=> '400',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Sport' => array(
					'categories'        => array('FREE', 'Clothing Fashion', 'Sports Recreation'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_17_contents.xml',
					'theme_settings' 	=> $url . 'niche_17_customizer.json',
					'widgets_file'  	=> $url . 'niche_17_widgets.wie',
					'form_file'  		=> $url . 'niche_17_form.json',
					'preview_image'		=> $url . 'niche-17.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-17/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '9',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-17.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '270',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Fashi' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_16_contents.xml',
					'theme_settings' 	=> $url . 'niche_16_customizer.json',
					'widgets_file'  	=> $url . 'niche_16_widgets.wie',
					'form_file'  		=> $url . 'niche_16_form.json',
					'preview_image'		=> $url . 'niche-16.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-16/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '9',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-16.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(		
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),					
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Wedens' => array(
					'categories'        => array('FREE', 'Jewelry Accessories'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_15_contents.xml',
					'theme_settings' 	=> $url . 'niche_15_customizer.json',
					'widgets_file'  	=> $url . 'niche_15_widgets.wie',
					'form_file'  		=> $url . 'niche_15_form.json',
					'preview_image'		=> $url . 'niche-15.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-15/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1440',
					'css_file'			=> 'niche-15.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '330',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Meuble' => array(
					'categories'        => array('FREE', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_14_contents.xml',
					'theme_settings' 	=> $url . 'niche_14_customizer.json',
					'widgets_file'  	=> $url . 'niche_14_widgets.wie',
					'form_file'  		=> $url . 'niche_14_form.json',
					'preview_image'		=> $url . 'niche-14.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-14/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1170',
					'css_file'			=> 'niche-14.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Elite Fitness' => array(
					'categories'        => array('FREE', 'Health Beauty'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_13_contents.xml',
					'theme_settings' 	=> $url . 'niche_13_customizer.json',
					'widgets_file'  	=> $url . 'niche_13_widgets.wie',
					'form_file'  		=> $url . 'niche_13_form.json',
					'preview_image'		=> $url . 'niche-13.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-13/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-13.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
						),
					),
				),
				
				'Ekko Center' => array(
					'categories'        => array('FREE', 'Toys Games', 'Electronics'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_12_contents.xml',
					'theme_settings' 	=> $url . 'niche_12_customizer.json',
					'widgets_file'  	=> $url . 'niche_12_widgets.wie',
					'form_file'  		=> $url . 'niche_12_form.json',
					'preview_image'		=> $url . 'niche-12.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-12/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1200',
					'css_file'			=> 'niche-12.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '262',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
						),
					),
				),
				
				'Green Market' => array(
					'categories'        => array('FREE', 'Food Drink'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_11_contents.xml',
					'theme_settings' 	=> $url . 'niche_11_customizer.json',
					'widgets_file'  	=> $url . 'niche_11_widgets.wie',
					'preview_image'		=> $url . 'niche-11.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-11/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1170',
					'css_file'			=> 'niche-11.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
							array(
								'slug'  	=> 'strong-testimonials',
								'init'  	=> 'strong-testimonials/strong-testimonials.php',
								'name'  	=> 'Strong Testimonials',
							),
						),
						'premium' => array(	
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),						
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
						),
					),
				),
				
				'Varus Tech' => array(
					'categories'        => array('FREE', 'Electronics'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_10_contents.xml',
					'theme_settings' 	=> $url . 'niche_10_customizer.json',
					'widgets_file'  	=> $url . 'niche_10_widgets.wie',
					'form_file'  		=> $url . 'niche_10_form.json',
					'preview_image'		=> $url . 'niche-10.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-10/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-10.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Xerox Shop' => array(
					'categories'        => array('FREE', 'Electronics'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_09_contents.xml',
					'theme_settings' 	=> $url . 'niche_09_customizer.json',
					'widgets_file'  	=> $url . 'niche_09_widgets.wie',
					'preview_image'		=> $url . 'niche-09.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-09/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-09.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '1',
					'woo_crop_height'	=> '1',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Pet Food' => array(
					'categories'        => array('FREE', 'Food Drink', 'Other'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_08_contents.xml',
					'theme_settings' 	=> $url . 'niche_08_customizer.json',
					'widgets_file'  	=> $url . 'niche_08_widgets.wie',
					'form_file'  		=> $url . 'niche_08_form.json',
					'preview_image'		=> $url . 'niche-08.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-08/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-08.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Ani Design' => array(
					'categories'        => array('FREE', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_07_contents.xml',
					'theme_settings' 	=> $url . 'niche_07_customizer.json',
					'widgets_file'  	=> $url . 'niche_07_widgets.wie',
					'preview_image'		=> $url . 'niche-07.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-07/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-07.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '210',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Wine Shop' => array(
					'categories'        => array('FREE', 'Food Drink'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_06_contents.xml',
					'theme_settings' 	=> $url . 'niche_06_customizer.json',
					'widgets_file'  	=> $url . 'niche_06_widgets.wie',
					'preview_image'		=> $url . 'niche-06.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-06/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-06.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Cat Ba' => array(
					'categories'        => array('FREE', 'Jewelry Accessories'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_05_contents.xml',
					'theme_settings' 	=> $url . 'niche_05_customizer.json',
					'widgets_file'  	=> $url . 'niche_05_widgets.wie',
					'preview_image'		=> $url . 'niche-05.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-05/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1740',
					'css_file'			=> 'niche-05.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '320',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Truong Sa' => array(
					'categories'        => array('FREE', 'Electronics'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_04_contents.xml',
					'theme_settings' 	=> $url . 'niche_04_customizer.json',
					'widgets_file'  	=> $url . 'niche_04_widgets.wie',
					'preview_image'		=> $url . 'niche-04.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-04/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1170',
					'css_file'			=> 'niche-04.css',
					'woo_image_size'	=> '636',
					'woo_thumb_size'	=> '318',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Hoang Sa' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_03_contents.xml',
					'theme_settings' 	=> $url . 'niche_03_customizer.json',
					'widgets_file'  	=> $url . 'niche_03_widgets.wie',
					'preview_image'		=> $url . 'niche-03.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-03/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-03.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Sweet House' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_02_contents.xml',
					'theme_settings' 	=> $url . 'niche_02_customizer.json',
					'widgets_file'  	=> $url . 'niche_02_widgets.wie',
					'preview_image'		=> $url . 'niche-02.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-02/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-02.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Lupus Men' => array(
					'categories'        => array('FREE', 'Clothing Fashion'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_01_contents.xml',
					'theme_settings' 	=> $url . 'niche_01_customizer.json',
					'widgets_file'  	=> $url . 'niche_01_widgets.wie',
					'form_file'  		=> $url . 'niche_01_form.json',
					'preview_image'		=> $url . 'niche-01.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-01/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-01.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'wpforms-lite',
								'init'  	=> 'wpforms-lite/wpforms.php',
								'name'  	=> 'WPForms',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				),
				
				'Main Demo' => array(
					'categories'        => array('FREE', 'Furniture'),
					'demo_class'        => 'free-demo',
					'xml_file'     		=> $dir . 'niche_00_contents.xml',
					'theme_settings' 	=> $url . 'niche_00_customizer.json',
					'widgets_file'  	=> $url . 'niche_00_widgets.wie',
					'preview_image'		=> $url . 'niche-00.jpg',
					'preview_url'		=> 'https://demo.woovina.net/niche-00/',
					'home_title'  		=> 'Home',
					'blog_title'  		=> 'Blog',
					'posts_to_show'  	=> '12',
					'elementor_width'  	=> '1190',
					'css_file'			=> 'niche-00.css',
					'woo_image_size'	=> '600',
					'woo_thumb_size'	=> '300',
					'woo_crop_width'	=> '3',
					'woo_crop_height'	=> '4',
					'required_plugins'  => array(
						'free' => array(
							array(
								'slug'  	=> 'elementor',
								'init'  	=> 'elementor/elementor.php',
								'name'  	=> 'Elementor',
							),
							array(
								'slug'  	=> 'woocommerce',
								'init'  	=> 'woocommerce/woocommerce.php',
								'name'  	=> 'WooCommerce',
							),
							array(
								'slug'  	=> 'contact-form-7',
								'init'  	=> 'contact-form-7/wp-contact-form-7.php',
								'name'  	=> 'Contact Form 7',
							),
						),
						'premium' => array(							
							array(
								'slug' 		=> 'woovina-product-sharing',
								'init' 		=> 'woovina-product-sharing/woovina-product-sharing.php',
								'name' 		=> 'WooVina Product Sharing',
							),
							array(
								'slug' 		=> 'woovina-popup-login',
								'init' 		=> 'woovina-popup-login/woovina-popup-login.php',
								'name' 		=> 'WooVina Popup Login',
							),
							array(
								'slug' 		=> 'woovina-woo-popup',
								'init' 		=> 'woovina-woo-popup/woovina-woo-popup.php',
								'name' 		=> 'WooVina Woo Popup',
							),
							array(
								'slug' 		=> 'woovina-sticky-header',
								'init' 		=> 'woovina-sticky-header/woovina-sticky-header.php',
								'name' 		=> 'WooVina Sticky Header',
							),
							array(
								'slug' 		=> 'woovina-preloader',
								'init'  	=> 'woovina-preloader/woovina-preloader.php',
								'name' 		=> 'WooVina Preloader',
							),
							array(
								'slug'  	=> 'woovina-variation-swatches',
								'init'  	=> 'woovina-variation-swatches/woovina-variation-swatches.php',
								'name'  	=> 'WooVina Variation Swatches',
							),
						),
					),
				)
			);

			// Return
			return apply_filters('woovina_demos_data', $data);

		}

		/**
		 * Get the category list of all categories used in the predefined demo imports array.
		 *
		 * @since 1.0
		 */
		public static function get_demo_all_categories($demo_imports) {
			$categories = array();

			foreach ($demo_imports as $item) {
				if(! empty($item['categories']) && is_array($item['categories'])) {
					foreach ($item['categories'] as $category) {
						$categories[ sanitize_key($category) ] = $category;
					}
				}
			}

			if(empty($categories)) {
				return false;
			}

			return $categories;
		}

		/**
		 * Return the concatenated string of demo import item categories.
		 * These should be separated by comma and sanitized properly.
		 *
		 * @since 1.0
		 */
		public static function get_demo_item_categories($item) {
			$sanitized_categories = array();

			if(isset($item['categories'])) {
				foreach ($item['categories'] as $category) {
					$sanitized_categories[] = sanitize_key($category);
				}
			}

			if(! empty($sanitized_categories)) {
				return implode(',', $sanitized_categories);
			}

			return false;
		}

	    /**
	     * Demos popup
	     *
		 * @since 1.0
	     */
	    public function popup() {
	    	global $pagenow;

	        // Display on the demos pages
	        if('themes.php' == $pagenow && (isset($_GET['page']) && 'woovina-starter-sites' == $_GET['page'])) { ?>
		        
		        <div id="woovina-demo-popup-wrap">
					<div class="woovina-demo-popup-container">
						<div class="woovina-demo-popup-content-wrap">
							<div class="woovina-demo-popup-content-inner">
								<a href="#" class="woovina-demo-popup-close">×</a>
								<div id="woovina-demo-popup-content"></div>
							</div>
						</div>
					</div>
					<div class="woovina-demo-popup-overlay"></div>
				</div>

	    	<?php
	    	}
	    }
		
		/**
		 * Demos popup ajax.
		 *
		 * @since 1.0
		 */
		public function ajax_demo_data() {

			if(! wp_verify_nonce($_GET['demo_data_nonce'], 'get-demo-data')) {
				die('This action was stopped for security purposes.');
			}

			// Database reset url
			if(is_plugin_active('wordpress-database-reset/wp-reset.php')) {
				$plugin_link 	= admin_url('tools.php?page=database-reset');
			} else {
				$plugin_link 	= admin_url('plugin-install.php?s=Wordpress+Database+Reset&tab=search');
			}

			// Get all demos
			$demos = self::get_demos_data();

			// Get selected demo
			$demo = sanitize_text_field($_GET['demo_name']);

			// Get required plugins
			$plugins = $demos[$demo][ 'required_plugins' ];

			// Get free plugins
			$free = $plugins[ 'free' ];

			// Get premium plugins
			$premium = $plugins[ 'premium' ];
			
			// Check demo access
			$demo_class 	= isset($demos[$demo]['demo_class']) ? $demos[$demo]['demo_class'] : 'free-demo';			
			$premium_access = self::premium_access();
			?>

			<div id="woovina-demo-plugins">

				<h2 class="title"><?php echo sprintf(esc_html__('Import the %1$s demo', 'woovina-sites'), esc_attr($demo)); ?></h2>
				
				<div class="woovina-popup-text">

					<p><?php echo
						sprintf(
							esc_html__('For your site to look exactly like this demo, the plugins below need to be activated. %1$sNOTE: You should install or activate only one plugin at a time!', 'woovina-sites'),
							'<br>'						
						); ?></p>					
					<div class="woovina-required-plugins-wrap">
						<h3 class="status-ready"><?php esc_html_e('WooVina Core', 'woovina-sites'); ?> <span><?php esc_html_e('Ready', 'woovina-sites'); ?></span></h3>
						<div class="woovina-required-plugins we-plugin-installer">							
							<?php self::required_woovina_core(); ?>							
						</div>
						
						<h3 class="status-ready"><?php esc_html_e('Required Plugins', 'woovina-sites'); ?> <span><?php esc_html_e('Ready', 'woovina-sites'); ?></span></h3>
						<div class="woovina-required-plugins we-plugin-installer">
							<?php self::required_plugins($free, 'free'); ?>
						</div>
						
						<?php if($premium_access){ ?>
						<h3 class="status-ready"><?php esc_html_e('Premium Plugins', 'woovina-sites'); ?> <span><?php esc_html_e('Ready', 'woovina-sites'); ?></span></h3>
						<?php } else { ?>
						<h3 class="status-need-activate"><?php esc_html_e('Premium Plugins', 'woovina-sites'); ?> <span><?php esc_html_e('Need Activate', 'woovina-sites'); ?></span></h3>
						<?php } ?>
						<div class="woovina-required-plugins we-plugin-installer">
							<?php self::required_plugins($premium, 'premium'); ?>
						</div>
					</div>

				</div>
				
				<a class="woovina-button woovina-plugins-next" href="#"><?php esc_html_e('Go to the next step', 'woovina-sites'); ?></a>
			</div>

			<form method="post" id="woovina-demo-import-form">

				<input id="woovina_import_demo" type="hidden" name="woovina_import_demo" value="<?php echo esc_attr($demo); ?>" />

				<div class="woovina-demo-import-form-types">

					<h2 class="title"><?php esc_html_e('Select what you want to import:', 'woovina-sites'); ?></h2>
					
					<ul class="woovina-popup-text">
						<li>
							<label for="woovina_import_xml">
								<input id="woovina_import_xml" type="checkbox" name="woovina_import_xml" checked="checked" />
								<strong><?php esc_html_e('Import XML Data', 'woovina-sites'); ?></strong> (<?php esc_html_e('pages, posts, images, menus, etc...', 'woovina-sites'); ?>)
							</label>
						</li>

						<li>
							<label for="woovina_theme_settings">
								<input id="woovina_theme_settings" type="checkbox" name="woovina_theme_settings" checked="checked" />
								<strong><?php esc_html_e('Import Customizer Settings', 'woovina-sites'); ?></strong>
							</label>
						</li>

						<li>
							<label for="woovina_import_widgets">
								<input id="woovina_import_widgets" type="checkbox" name="woovina_import_widgets" checked="checked" />
								<strong><?php esc_html_e('Import Widgets', 'woovina-sites'); ?></strong>
							</label>
						</li>
						
						<?php if(isset($demos[$demo]['form_file'])): ?>
						<li>
							<label for="woovina_import_forms">
								<input id="woovina_import_forms" type="checkbox" name="woovina_import_forms" checked="checked" />
								<strong><?php esc_html_e('Import Contact Form', 'woovina-sites'); ?></strong>
							</label>
						</li>
						<?php endif; ?>
					</ul>

				</div>
				
				<?php wp_nonce_field('woovina_import_demo_data_nonce', 'woovina_import_demo_data_nonce'); ?>
				<input type="submit" name="submit" class="woovina-button woovina-import" value="<?php esc_html_e('Install this demo', 'woovina-sites'); ?>"  />

			</form>

			<div class="woovina-loader">
				<h2 class="title"><?php esc_html_e('The import process could take some time, please be patient', 'woovina-sites'); ?></h2>
				<div class="woovina-import-status woovina-popup-text"></div>
			</div>

			<div class="woovina-last woovina-success">
				<div class="woovina-notice">
					<h3><?php esc_html_e('Demo Imported!', 'woovina-sites'); ?></h3>
					<p><?php esc_html_e('But you need to replace URLs in Elementor', 'woovina-sites'); ?></p>
					<ul>
						<li><?php esc_html_e('Old URL:', 'woovina-sites'); ?> <strong><?php echo $demos[$demo]['preview_url']; ?></strong></li>
						<li><?php esc_html_e('New URL:', 'woovina-sites'); ?> <strong><?php echo home_url('/'); ?></strong></li>
					</ul>
					<a href="<?php echo admin_url('admin.php?page=elementor-tools#tab-replace_url'); ?>" target="_blank"><?php esc_html_e('Replace URLs', 'woovina-sites'); ?></a>
				</div>
				
				<div class="woovina-checkmark">
					<svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"></circle><path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"></path></svg>					
				</div>
			</div>
			
			<div class="woovina-last woovina-error">				
				<h3><?php esc_html_e('Demo is not Fully Imported!', 'woovina-sites'); ?></h3>
				<a href="https://woovina.com/docs/getting-started/demo-is-not-fully-imported" target="_blank"><?php esc_html_e('See how to fix', 'woovina-sites'); ?></a>
			</div>

			<?php
			die();
		}
		
		/**
		 * WooVina Core.
		 *
		 * @since 1.0
		 */
		public function required_woovina_core() {
			$button_classes = 'button button-primary install-woovina-package';
			$button_text 	= esc_html__('Install & Activate', 'woovina-sites');
			
			if($this->woovina_activate) {
				$button_classes = 'button disabled';
				$button_text 	= esc_html__('Activated', 'woovina-sites');
			}
			?>
			<div class="woovina-plugin">
				<h2><?php esc_html_e('WooVina Theme & Plugins', 'woovina-sites'); ?></h2>
				<button class="<?php echo $button_classes; ?>"><?php echo $button_text; ?></button>
			</div>
			<?php
		}
		
		/**
		 * Required plugins.
		 *
		 * @since 1.0
		 */
		public function required_plugins($plugins, $return) {

			foreach ($plugins as $key => $plugin) {

				$api = array(
					'slug' 	=> isset($plugin['slug']) ? $plugin['slug'] : '',
					'init' 	=> isset($plugin['init']) ? $plugin['init'] : '',
					'name' 	=> isset($plugin['name']) ? $plugin['name'] : '',
				);

				if(! is_wp_error($api)) { // confirm error free

					// Installed but Inactive.
					if(file_exists(WP_PLUGIN_DIR . '/' . $plugin['init']) && is_plugin_inactive($plugin['init'])) {

						$button_classes = 'button activate-now button-primary';
						$button_text 	= esc_html__('Activate', 'woovina-sites');

					// Not Installed.
					} elseif(! file_exists(WP_PLUGIN_DIR . '/' . $plugin['init'])) {

						$button_classes = 'button install-now';
						$button_text 	= esc_html__('Install Now', 'woovina-sites');

					// Active.
					} else {
						$button_classes = 'button disabled';
						$button_text 	= esc_html__('Activated', 'woovina-sites');
					} ?>

					<div class="woovina-plugin woovina-clr woovina-plugin-<?php echo $api['slug']; ?>" data-slug="<?php echo $api['slug']; ?>" data-init="<?php echo $api['init']; ?>">
						<h2><?php echo $api['name']; ?></h2>

						<?php
						// If premium plugins and not installed
						if('premium' == $return) { 							
						$premium_access = self::premium_access();
						$button_classes = ($premium_access) ? $button_classes : 'button disabled';
						?>
							<button class="<?php echo $button_classes; ?>" data-type="premium" data-init="<?php echo $api['init']; ?>" data-slug="<?php echo $api['slug']; ?>" data-name="<?php echo $api['name']; ?>"><?php echo $button_text; ?></button>
						<?php
						} else { ?>
							<button class="<?php echo $button_classes; ?>" data-type="free" data-init="<?php echo $api['init']; ?>" data-slug="<?php echo $api['slug']; ?>" data-name="<?php echo $api['name']; ?>"><?php echo $button_text; ?></button>
						<?php
						} ?>
					</div>

				<?php
				}
			}

		}

		/**
		 * Required plugins activate
		 *
		 * @since 1.0
		 */
		public function ajax_required_plugins_activate() {

			if(! current_user_can('install_plugins') || ! isset($_POST['init']) || ! $_POST['init']) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => __('No plugin specified', 'woovina-sites'),
					)
				);
			}

			$plugin_init = (isset($_POST['init'])) ? esc_attr($_POST['init']) : '';
			$activate 	 = activate_plugin($plugin_init, '', false, true);

			if(is_wp_error($activate)) {
				wp_send_json_error(
					array(
						'success' => false,
						'message' => $activate->get_error_message(),
					)
				);
			}

			wp_send_json_success(
				array(
					'success' => true,
					'message' => __('Plugin Successfully Activated', 'woovina-sites'),
				)
			);

		}

		/**
		 * Returns an array containing all the importable content
		 *
		 * @since 1.0
		 */
		public function ajax_get_import_data() {
			check_ajax_referer('woovina_import_data_nonce', 'security');

			echo json_encode(
				array(
					array(
						'input_name' 	=> 'woovina_import_xml',
						'action' 		=> 'woovina_ajax_import_xml',
						'method' 		=> 'ajax_import_xml',
						'loader' 		=> esc_html__('Importing XML Data', 'woovina-sites')
					),

					array(
						'input_name' 	=> 'woovina_theme_settings',
						'action' 		=> 'woovina_ajax_import_theme_settings',
						'method' 		=> 'ajax_import_theme_settings',
						'loader' 		=> esc_html__('Importing Customizer Settings', 'woovina-sites')
					),

					array(
						'input_name' 	=> 'woovina_import_widgets',
						'action' 		=> 'woovina_ajax_import_widgets',
						'method' 		=> 'ajax_import_widgets',
						'loader' 		=> esc_html__('Importing Widgets', 'woovina-sites')
					),
					
					array(
						'input_name' 	=> 'woovina_import_forms',
						'action' 		=> 'woovina_ajax_import_forms',
						'method' 		=> 'ajax_import_forms',
						'loader' 		=> esc_html__('Importing Form', 'woovina-sites')
					)
				)
			);

			die();
		}

		/**
		 * Import XML file
		 *
		 * @since 1.0
		 */
		public function ajax_import_xml() {
			if(! wp_verify_nonce($_POST['woovina_import_demo_data_nonce'], 'woovina_import_demo_data_nonce')) {
				die('This action was stopped for security purposes.');
			}

			// Get the selected demo
			$demo_type 			= sanitize_text_field($_POST['woovina_import_demo']);

			// Get demos data
			$demo 				= WooVina_Sites_Demos::get_demos_data()[ $demo_type ];

			// Content file
			$xml_file 			= isset($demo['xml_file']) ? $demo['xml_file'] : '';

			// Delete the default post and page
			$sample_page 		= get_page_by_path('sample-page', OBJECT, 'page');
			$hello_world_post 	= get_page_by_path('hello-world', OBJECT, 'post');

			if(! is_null($sample_page)) {
				wp_delete_post($sample_page->ID, true);
			}

			if(! is_null($hello_world_post)) {
				wp_delete_post($hello_world_post->ID, true);
			}

			// Import Posts, Pages, Images, Menus.
			$result = $this->process_xml($xml_file);

			if(is_wp_error($result)) {
				echo json_encode($result->errors);
			} else {
				echo 'successful import';
			}

			die();
		}

		/**
		 * Import customizer settings
		 *
		 * @since 1.0
		 */
		public function ajax_import_theme_settings() {
			if(! wp_verify_nonce($_POST['woovina_import_demo_data_nonce'], 'woovina_import_demo_data_nonce')) {
				die('This action was stopped for security purposes.');
			}

			// Include settings importer
			include WOOVINA_SITES_DIR .'inc/classes/importers/class-settings-importer.php';

			// Get the selected demo
			$demo_type 			= sanitize_text_field($_POST['woovina_import_demo']);

			// Get demos data
			$demo 				= WooVina_Sites_Demos::get_demos_data()[ $demo_type ];

			// Settings file
			$theme_settings 	= isset($demo['theme_settings']) ? $demo['theme_settings'] : '';

			// Import settings.
			$settings_importer = new WOOVINA_Settings_Importer();
			$result = $settings_importer->process_import_file($theme_settings);
			
			// Set default CSS
			set_theme_mod('woovina_css_file', $demo['css_file']);
			
			if(is_wp_error($result)) {
				echo json_encode($result->errors);
			} else {
				echo 'successful import';
			}

			die();
		}

		/**
		 * Import widgets
		 *
		 * @since 1.0
		 */
		public function ajax_import_widgets() {
			if(! wp_verify_nonce($_POST['woovina_import_demo_data_nonce'], 'woovina_import_demo_data_nonce')) {
				die('This action was stopped for security purposes.');
			}

			// Include widget importer
			include WOOVINA_SITES_DIR .'inc/classes/importers/class-widget-importer.php';

			// Get the selected demo
			$demo_type 			= sanitize_text_field($_POST['woovina_import_demo']);

			// Get demos data
			$demo 				= WooVina_Sites_Demos::get_demos_data()[ $demo_type ];

			// Widgets file
			$widgets_file 		= isset($demo['widgets_file']) ? $demo['widgets_file'] : '';

			// Import settings.
			$widgets_importer = new WOOVINA_Widget_Importer();
			$result = $widgets_importer->process_import_file($widgets_file);
			
			// Set default CSS
			set_theme_mod('woovina_css_file', $demo['css_file']);
			
			if(is_wp_error($result)) {
				echo json_encode($result->errors);
			} else {
				echo 'successful import';
			}

			die();
		}
		
		/**
		 * Import forms
		 *
		 * @since 1.4.5
		 */
		public function ajax_import_forms() {
			if(!current_user_can('manage_options') ||! wp_verify_nonce($_POST['woovina_import_demo_data_nonce'], 'woovina_import_demo_data_nonce')) {
				die('This action was stopped for security purposes.');
			}

			// Include form importer
			include WOOVINA_SITES_DIR .'inc/classes/importers/class-wpforms-importer.php';

			// Get the selected demo
			$demo_type 			= sanitize_text_field($_POST['woovina_import_demo']);

			// Get demos data
			$demo 				= WooVina_Sites_Demos::get_demos_data()[$demo_type];

			// Widgets file
			$form_file 			= isset($demo['form_file'] ) ? $demo['form_file'] : '';

			// Import form 2
			$form_file_2 		= isset($demo['form_file_2'] ) ? $demo['form_file_2'] : '';

			// Import settings.
			$forms_importer = new WOOVINA_WPForms_Importer();
			$result  = $forms_importer->process_import_file( $form_file );
			$result2 = $forms_importer->process_import_file( $form_file_2 );

			if(is_wp_error($result) || (!empty($form_file_2) && is_wp_error($result2))) {
				echo json_encode($result->errors);
			} else {
				echo 'successful import';
			}

			die();
		}
		
		/**
		 * After import
		 *
		 * @since 1.0
		 */
		public function ajax_after_import() {
			if(! wp_verify_nonce($_POST['woovina_import_demo_data_nonce'], 'woovina_import_demo_data_nonce')) {
				die('This action was stopped for security purposes.');
			}

			// If XML file is imported
			if($_POST['woovina_import_is_xml'] === 'true') {

				// Get the selected demo
				$demo_type 			= sanitize_text_field($_POST['woovina_import_demo']);

				// Get demos data
				$demo 				= WooVina_Sites_Demos::get_demos_data()[ $demo_type ];

				// Elementor width setting
				$elementor_width 		= isset($demo['elementor_width']) ? $demo['elementor_width'] : '';
				$elementor_viewport_md	= isset($demo['elementor_viewport_md']) ? $demo['elementor_viewport_md'] : '';
				
				// Reading settings
				$homepage_title 	= isset($demo['home_title']) ? $demo['home_title'] : 'Home';
				$blog_title 		= isset($demo['blog_title']) ? $demo['blog_title'] : '';

				// Posts to show on the blog page
				$posts_to_show 		= isset($demo['posts_to_show']) ? $demo['posts_to_show'] : '';

				// If shop demo
				$shop_demo 			= isset($demo['is_shop']) ? $demo['is_shop'] : true;

				// Product image size
				$image_size 		= isset($demo['woo_image_size']) ? $demo['woo_image_size'] : '';
				$thumbnail_size 	= isset($demo['woo_thumb_size']) ? $demo['woo_thumb_size'] : '';
				$crop_width 		= isset($demo['woo_crop_width']) ? $demo['woo_crop_width'] : '';
				$crop_height 		= isset($demo['woo_crop_height']) ? $demo['woo_crop_height'] : '';

				// Assign WooCommerce pages if WooCommerce Exists
				if(class_exists('WooCommerce') && true == $shop_demo) {

					$woopages = array(
						'woocommerce_shop_page_id' 				=> 'Shop',
						'woocommerce_cart_page_id' 				=> 'Cart',
						'woocommerce_checkout_page_id' 			=> 'Checkout',
						'woocommerce_pay_page_id' 				=> 'Checkout &#8594; Pay',
						'woocommerce_thanks_page_id' 			=> 'Order Received',
						'woocommerce_myaccount_page_id' 		=> 'My Account',
						'woocommerce_edit_address_page_id' 		=> 'Edit My Address',
						'woocommerce_view_order_page_id' 		=> 'View Order',
						'woocommerce_change_password_page_id' 	=> 'Change Password',
						'woocommerce_logout_page_id' 			=> 'Logout',
						'woocommerce_lost_password_page_id' 	=> 'Lost Password'
					);

					foreach ($woopages as $woo_page_name => $woo_page_title) {

						$woopage = get_page_by_title($woo_page_title);
						if(isset($woopage) && $woopage->ID) {
							update_option($woo_page_name, $woopage->ID);
						}

					}

					// We no longer need to install pages
					delete_option('_wc_needs_pages');
					delete_transient('_wc_activation_redirect');

					// Get products image size
					update_option('woocommerce_single_image_width', $image_size);
					update_option('woocommerce_thumbnail_image_width', $thumbnail_size);
					update_option('woocommerce_thumbnail_cropping', 'custom');
					update_option('woocommerce_thumbnail_cropping_custom_width', $crop_width);
					update_option('woocommerce_thumbnail_cropping_custom_height', $crop_height);
					
					// Fix bug Sale Products doesn't show after import
					delete_transient('wc_products_onsale');
					wc_update_product_lookup_tables();
				}

				// Set imported menus to registered theme locations
				$locations 	= get_theme_mod('nav_menu_locations');
				$menus 		= wp_get_nav_menus();

				if($menus) {
					
					foreach ($menus as $menu) {

						if($menu->name == 'Main Menu') {
							$locations['main_menu'] = $menu->term_id;
						} else if($menu->name == 'Top Menu') {
							$locations['topbar_menu'] = $menu->term_id;
						} else if($menu->name == 'Footer Menu') {
							$locations['footer_menu'] = $menu->term_id;
						} else if($menu->name == 'Sticky Footer') {
							$locations['sticky_footer_menu'] = $menu->term_id;
						} else if($menu->name == 'Mobile Menu') {
							$locations['mobile_menu'] = $menu->term_id;
						} else if($menu->name == 'Categories') {
							$locations['mobile_categories'] = $menu->term_id;						
						} else if($menu->name == 'Mobile Navbar') {
							$locations['mobile_navbar'] = $menu->term_id;
						}
					}

				}
				
				// Set default CSS
				set_theme_mod('woovina_css_file', $demo['css_file']);
				
				// Set menus to locations
				set_theme_mod('nav_menu_locations', $locations);

				// Disable Elementor default settings
				update_option('elementor_disable_color_schemes', 'yes');
				update_option('elementor_disable_typography_schemes', 'yes');
			    if(! empty($elementor_width)) {
					update_option('elementor_container_width', $elementor_width);
				}
				if(! empty($elementor_viewport_md)) {
					update_option('elementor_viewport_md', $elementor_viewport_md);
				}				
				
				// Load Font Awesome 4 Support
				update_option('elementor_load_fa4_shim', 'yes');
				
				// Update Default Kit
				$default_kit = get_page_by_title('Default Kit', OBJECT, 'elementor_library');
				update_option('elementor_active_kit', $default_kit->ID);
				
				// Assign front page and posts page (blog page).
			    $home_page = get_page_by_title($homepage_title);
			    $blog_page = get_page_by_title($blog_title);

			    update_option('show_on_front', 'page');

			    if(is_object($home_page)) {
					update_option('page_on_front', $home_page->ID);
				}

				if(is_object($blog_page)) {
					update_option('page_for_posts', $blog_page->ID);
				}

				// Posts to show on the blog page
			    if(! empty($posts_to_show)) {
					update_option('posts_per_page', $posts_to_show);
				}
				
			}

			die();
		}

		/**
		 * Import XML data
		 *
		 * @since 1.0.0
		 */
		public function process_xml($file) {
			
			// Set temp xml to attachment url for use
			$attachment_url = $file;

			// If file exists lets import it
			if(file_exists($attachment_url)) {
				$this->import_xml($attachment_url);
			} else {
				// Import file can't be imported - we should die here since this is core for most people.
				return new WP_Error('xml_import_error', __('The xml import file could not be accessed. Please try again or contact the theme developer.', 'woocommerce-starter-sites'));
			}

		}
		
		/**
		 * Import XML file
		 *
		 * @since 1.0.0
		 */
		private function import_xml($file) {

			// Make sure importers constant is defined
			if(! defined('WP_LOAD_IMPORTERS')) {
				define('WP_LOAD_IMPORTERS', true);
			}

			// Import file location
			$import_file = ABSPATH . 'wp-admin/includes/import.php';

			// Include import file
			if(! file_exists($import_file)) {
				return;
			}

			// Include import file
			require_once($import_file);

			// Define error var
			$importer_error = false;

			if(! class_exists('WP_Importer')) {
				$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';

				if(file_exists($class_wp_importer)) {
					require_once $class_wp_importer;
				} else {
					$importer_error = __('Can not retrieve class-wp-importer.php', 'woovina-sites');
				}
			}

			if(! class_exists('WP_Import')) {
				$class_wp_import = WOOVINA_SITES_DIR . 'inc/classes/importers/class-wordpress-importer.php';

				if(file_exists($class_wp_import)) {
					require_once $class_wp_import;
				} else {
					$importer_error = __('Can not retrieve wordpress-importer.php', 'woovina-sites');
				}
			}

			// Display error
			if($importer_error) {
				return new WP_Error('xml_import_error', $importer_error);
			} else {

				// No error, lets import things...
				if(! is_file($file)) {
					$importer_error = __('Sample data file appears corrupt or can not be accessed.', 'woovina-sites');
					return new WP_Error('xml_import_error', $importer_error);
				} else {
					$importer = new WP_Import();
					$importer->fetch_attachments = true;
					$importer->import($file);
				}
			}
		}
		
		/**
		 * Check install premium plugins access
		 *
		 * @since 1.0.4
		 */
		public function premium_access() {
			$license 			= get_option('edd_license_details');
			$license_details 	= (isset($license) && isset($license['woovina_starter_sites'])) ? $license['woovina_starter_sites'] : false;
			
			$now        	= current_time('timestamp');
			$expire_date	= isset($license_details->expires) && trim($license_details->expires) != '' ? $license_details->expires : '';
			$expiration 	= strtotime($expire_date, current_time('timestamp'));
			
			if(!$license_details) 	return false;
			if($now > $expiration) 	return false;
			
			return $license_details->pro_plugins;			
		}
	}
}
new WooVina_Sites_Demos();