<?php
/**
 * Plugin Name: WP Folder Boss
 * Plugin URI: https://github.com/dedebastard/wp-folder-boss
 * Description: Organize your media library, pages, posts, custom post types, users, plugins, WooCommerce orders, products, coupons, and more using folders. Drag and drop, bulk move, and auto-assign uploads.
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: dedebastard
 * Author URI: https://github.com/dedebastard
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-folder-boss
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFB_VERSION', '1.0.2' );
define( 'WPFB_PLUGIN_FILE', __FILE__ );
define( 'WPFB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPFB_TAXONOMY', 'wpfb_folder' );

require_once WPFB_PLUGIN_DIR . 'includes/Plugin.php';
require_once WPFB_PLUGIN_DIR . 'includes/Activator.php';
require_once WPFB_PLUGIN_DIR . 'includes/Deactivator.php';
require_once WPFB_PLUGIN_DIR . 'includes/Helpers/Utils.php';
require_once WPFB_PLUGIN_DIR . 'includes/Models/Folder.php';
require_once WPFB_PLUGIN_DIR . 'includes/Services/FolderService.php';
require_once WPFB_PLUGIN_DIR . 'includes/Services/AssignmentService.php';
require_once WPFB_PLUGIN_DIR . 'includes/Services/ImportService.php';
require_once WPFB_PLUGIN_DIR . 'includes/Rest/FolderController.php';
require_once WPFB_PLUGIN_DIR . 'includes/Rest/AssignmentController.php';
require_once WPFB_PLUGIN_DIR . 'includes/Rest/SettingsController.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/AdminMenu.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/Settings.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/MediaLibrary.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/PostTypeScreen.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/WooCommerceScreen.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/UsersScreen.php';
require_once WPFB_PLUGIN_DIR . 'includes/Admin/PluginsScreen.php';

register_activation_hook( __FILE__, array( 'WPFolderBoss\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPFolderBoss\\Deactivator', 'deactivate' ) );

WPFolderBoss\Plugin::get_instance()->init();
