<?php
/**
 * Plugin Name: Cachelume
 * Description: A powerful caching plugin for WordPress to improve website performance by caching pages, minifying HTML/CSS/JS, and more.
 * Version: 1.0.0
 * Author: Samrat Hossen
 * Author URI: https://samratemily.netlify.app/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cachelume
 *
 * @package Cachelume
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Define Constants.
define('CACHELUME_VERSION', '1.0.0');
define('CACHELUME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CACHELUME_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CACHELUME_PLUGIN_FILE', __FILE__);
// Cache stored outside the plugin directory so it is not bundled with plugin
// updates and sits at the conventional wp-content/cache/ location.
// On Apache, a .htaccess inside the directory denies direct HTTP access.
// On nginx, add: location ~* /cache/cachelume/ { deny all; }
define('CACHELUME_CACHE_DIR', WP_CONTENT_DIR . '/cache/cachelume/');

/**
 * Get the default plugin settings.
 *
 * @return array
 */
function cachelume_get_default_settings() {
	return array(
		'enable_page_cache'  => 1,
		'cache_logged_users' => 0,
		'cache_expiry'       => 86400,
		'minify_html'        => 0,
		'minify_css'         => 0,
		'minify_js'          => 0,
		'exclude_pages'      => "/cart/\n/checkout/\n/my-account/",
		'exclude_cookies'    => "woocommerce_cart_hash\nwoocommerce_items_in_cart",
	);
}

/**
 * Initialize the plugin.
 */
function cachelume_init() {
	// Load Cache Handler.
	require_once CACHELUME_PLUGIN_DIR . 'includes/CacheHandler.php';
	new Cachelume_Cache_Handler();

	// Schedule the daily cleanup of expired cache files. Checked on load rather
	// than only on activation so sites that already had the plugin active get it.
	if (!wp_next_scheduled('cachelume_purge_expired')) {
		wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'cachelume_purge_expired');
	}

	// Load Admin Menu. The full admin UI loads only in the admin area; on the
	// frontend only the admin bar "Cache" menu is registered.
	require_once CACHELUME_PLUGIN_DIR . 'includes/AdminMenu.php';
	if (is_admin()) {
		new Cachelume_Admin_Menu();
	} else {
		add_action('admin_bar_menu', array('Cachelume_Admin_Menu', 'add_admin_bar_menu'), 100);
	}
}
add_action('plugins_loaded', 'cachelume_init');

/**
 * Activation hook - set default options.
 */
function cachelume_activate() {
	if (!get_option('cachelume_settings')) {
		add_option('cachelume_settings', cachelume_get_default_settings());
	}

	// Create cache directory and security files using WP_Filesystem.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if (!WP_Filesystem()) {
		return; // Filesystem not available (e.g. requires FTP credentials); skip silently.
	}
	global $wp_filesystem;

	$cache_dir = CACHELUME_CACHE_DIR;
	if (!$wp_filesystem->exists($cache_dir)) {
		$wp_filesystem->mkdir($cache_dir, FS_CHMOD_DIR, true);
	}

	// Create .htaccess for cache directory (deny direct access).
	$htaccess_file = $cache_dir . '.htaccess';
	if (!$wp_filesystem->exists($htaccess_file)) {
		$htaccess_content = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>";
		$wp_filesystem->put_contents($htaccess_file, $htaccess_content);
	}

	// Create index.php to prevent directory listing.
	$index_file = $cache_dir . 'index.php';
	if (!$wp_filesystem->exists($index_file)) {
		$wp_filesystem->put_contents($index_file, '<?php // Silence is golden.');
	}
}
register_activation_hook(__FILE__, 'cachelume_activate');

/**
 * Deactivation hook - clear cache.
 */
function cachelume_deactivate() {
	wp_clear_scheduled_hook('cachelume_purge_expired');

	// Clear all cache on deactivation using WP_Filesystem.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	if (!WP_Filesystem()) {
		return; // Filesystem not available; skip cache clear.
	}
	global $wp_filesystem;

	$cache_dir = CACHELUME_CACHE_DIR;

	if ($wp_filesystem->exists($cache_dir)) {
		// Use wp_filesystem->dirlist to clear files.
		$file_list = $wp_filesystem->dirlist($cache_dir);
		if ($file_list) {
			foreach ($file_list as $file_name => $file_info) {
				if ('f' === $file_info['type'] && 'html' === pathinfo($file_name, PATHINFO_EXTENSION)) {
					$wp_filesystem->delete($cache_dir . $file_name);
				}
			}
		}
	}
}
register_deactivation_hook(__FILE__, 'cachelume_deactivate');

/**
 * Add settings link on plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function cachelume_settings_link($links) {
	$settings_link = '<a href="' . admin_url('admin.php?page=cachelume-settings') . '">' . __('Settings', 'cachelume') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'cachelume_settings_link');

/**
 * Load admin bar script for the "Clear Cache" admin bar button.
 *
 * Runs on the frontend and on admin screens. The plugin's own admin pages are
 * skipped because admin.js defines the handler there.
 *
 * @param string $hook Admin page hook suffix (empty on the frontend).
 */
function cachelume_admin_bar_scripts($hook = '') {
	if (is_admin() && strpos($hook, 'cachelume') !== false) {
		return;
	}

	if (is_admin_bar_showing() && current_user_can('manage_options')) {
		wp_enqueue_script('jquery');
		wp_add_inline_script('jquery', '
			var cachelumeCache = {
				ajaxUrl: "' . esc_js(admin_url('admin-ajax.php')) . '",
				nonce: "' . esc_js(wp_create_nonce('cachelume_nonce')) . '",
				clearingText: "' . esc_js(__('Clearing...', 'cachelume')) . '",
				clearedText: "' . esc_js(__('Cache Cleared!', 'cachelume')) . '",
				errorText: "' . esc_js(__('Error clearing cache', 'cachelume')) . '"
			};
			function cachelumeClearCacheFromBar() {
				jQuery.ajax({
					url: cachelumeCache.ajaxUrl,
					type: "POST",
					data: {
						action: "cachelume_clear_cache",
						nonce: cachelumeCache.nonce
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
						} else {
							alert(response.data.message || cachelumeCache.errorText);
						}
					},
					error: function() {
						alert(cachelumeCache.errorText);
					}
				});
			}
		');
	}
}
add_action('wp_enqueue_scripts', 'cachelume_admin_bar_scripts');
add_action('admin_enqueue_scripts', 'cachelume_admin_bar_scripts');
