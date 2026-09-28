<?php
/**
 * Uninstall Cachelume.
 *
 * Removes the plugin's settings, scheduled cleanup event and cache directory
 * when the plugin is deleted from the Plugins screen.
 *
 * @package Cachelume
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/**
 * Remove the per-site data: settings and scheduled cleanup event.
 */
function cachelume_uninstall_site() {
	delete_option('cachelume_settings');
	wp_clear_scheduled_hook('cachelume_purge_expired');
}

if (is_multisite()) {
	foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $cachelume_site_id) {
		switch_to_blog($cachelume_site_id);
		cachelume_uninstall_site();
		restore_current_blog();
	}
} else {
	cachelume_uninstall_site();
}

// Remove the cache directory (shared by all sites on a multisite network).
require_once ABSPATH . 'wp-admin/includes/file.php';
if (WP_Filesystem()) {
	global $wp_filesystem;
	$cachelume_cache_dir = WP_CONTENT_DIR . '/cache/cachelume/';
	if ($wp_filesystem->exists($cachelume_cache_dir)) {
		$wp_filesystem->delete($cachelume_cache_dir, true);
	}
}
