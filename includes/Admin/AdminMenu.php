<?php
/**
 * Admin menu registration.
 *
 * @package WPFolderBoss\Admin
 */

declare(strict_types=1);

namespace WPFolderBoss\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminMenu
 */
class AdminMenu {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function add_menu_pages(): void {
		add_options_page(
			__( 'WP Folder Boss', 'wp-folder-boss' ),
			__( 'WP Folder Boss', 'wp-folder-boss' ),
			'manage_options',
			'wp-folder-boss',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-folder-boss' ) );
		}
		require_once WPFB_PLUGIN_DIR . 'templates/settings-page.php';
	}
}
