<?php
/**
 * Install Demos page
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
class WooVina_Install_Demos {

	/**
	 * Start things up
	 */
	public function __construct() {
		add_action('admin_menu', array($this, 'add_page'), 999);
	}

	/**
	 * Add sub menu page for the custom CSS input
	 *
	 * @since 1.0.0
	 */
	public function add_page() {
		
		add_submenu_page(
			'themes.php',
			esc_html__('WooVina Starter Sites', 'woovina-sites'),
			esc_html__('WooVina Starter Sites', 'woovina-sites'),
			'manage_options',
			'woovina-starter-sites',
			array($this, 'create_admin_page')
		);
	}

	/**
	 * Settings page output
	 *
	 * @since 1.0.0
	 */
	public function create_admin_page() {
		?>

		<div class="woovina-demo-wrap wrap">

			<h2><?php esc_attr_e('WooVina Starter Sites', 'woovina-sites'); ?></h2>

			<div class="theme-browser rendered">

				<?php
				// Vars
				$demos = WooVina_Sites_Demos::get_demos_data();
				$categories = WooVina_Sites_Demos::get_demo_all_categories($demos);				
				if(! empty($categories)) :				
				asort($categories);
				?>
					<div class="woovina-header-bar">
						<nav class="woovina-navigation">
							<ul>
								<li class="active"><a href="#all" class="woovina-navigation-link"><?php esc_html_e('All', 'woovina-sites'); ?></a></li>								
								<?php foreach ($categories as $key => $name) : if($key == 'other') continue; ?>
									<li><a href="#<?php echo esc_attr($key); ?>" class="woovina-navigation-link"><?php echo esc_html($name); ?></a></li>
								<?php endforeach; ?>
								<?php if(isset($categories['other'])) : ?><li><a href="#other" class="woovina-navigation-link"><?php esc_html_e('Other', 'woovina-sites'); ?></a></li><?php endif; ?>
							</ul>
						</nav>
						<div clas="woovina-search">
							<input type="text" class="woovina-search-input" name="woovina-search" value="" placeholder="<?php esc_html_e('Search demos...', 'woovina-sites'); ?>">
						</div>
					</div>
				<?php endif; ?>

				<div class="themes wp-clearfix">

					<?php
					// Loop through all demos
					foreach ($demos as $demo => $key) {

						// Vars
						$item_categories 	= WooVina_Sites_Demos::get_demo_item_categories($key);
						$demo_class 		= isset($key['demo_class']) ? $key['demo_class'] : 'free-demo';
					?>

						<div class="theme-wrap" data-categories="<?php echo esc_attr($item_categories); ?>" data-name="<?php echo esc_attr(strtolower($demo)); ?>">

							<div class="theme woovina-open-popup <?php echo esc_attr($demo_class); ?>" data-demo-id="<?php echo esc_attr($demo); ?>">

								<div class="theme-screenshot">
									<img src="<?php echo $key['preview_image']; ?>" />

									<div class="demo-import-loader preview-all preview-all-<?php echo esc_attr($demo); ?>"></div>

									<div class="demo-import-loader preview-icon preview-<?php echo esc_attr($demo); ?>"><i class="custom-loader"></i></div>
								</div>

								<div class="theme-id-container">
		
									<h2 class="theme-name" id="<?php echo esc_attr($demo); ?>"><span><?php echo ucwords($demo); ?></span></h2>

									<div class="theme-actions">
										<a class="button button-primary" href="<?php echo $key['preview_url']; ?>" target="_blank"><?php _e('Live Preview', 'woovina-sites'); ?></a>
									</div>

								</div>

							</div>

						</div>

					<?php } ?>

				</div>

			</div>

		</div>

	<?php }
}
new WooVina_Install_Demos();