<?php
/**
 * Hooks into the Plugins list screen.
 *
 * @package WPFolderBoss\Admin
 */

declare(strict_types=1);

namespace WPFolderBoss\Admin;

use WPFolderBoss\Helpers\Utils;
use WPFolderBoss\Services\FolderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginsScreen
 */
class PluginsScreen {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! Utils::is_screen_enabled( 'plugins' ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'pre_current_active_plugins', array( $this, 'render_sidebar' ) );
		add_filter( 'all_plugins', array( $this, 'filter_by_folder' ) );
	}

	/**
	 * Enqueue assets on plugins.php.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'plugins.php' !== $hook ) {
			return;
		}
		Utils::enqueue_admin_assets();

		wp_localize_script(
			'wpfb-folder-tree',
			'wpfbPluginsData',
			array(
				'pluginFolders' => get_option( 'wpfb_plugin_folders', array() ),
			)
		);
	}

	/**
	 * Render the folder tree sidebar on the plugins screen.
	 *
	 * @return void
	 */
	public function render_sidebar(): void {
		$service = new FolderService();
		$folders = $service->get_folders( 'plugins' );
		$context = 'plugins';
		require WPFB_PLUGIN_DIR . 'templates/folder-tree-sidebar.php';
	}

	/**
	 * Filter the plugins list by the selected folder.
	 *
	 * @param array<string,mixed> $plugins All plugins.
	 * @return array<string,mixed>
	 */
	public function filter_by_folder( array $plugins ): array {
		$folder_id = isset( $_GET['wpfb_folder'] ) ? (int) wp_unslash( $_GET['wpfb_folder'] ) : - 1; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $folder_id < 0 ) {
			return $plugins;
		}

		$plugin_folders = get_option( 'wpfb_plugin_folders', array() );
		if ( ! is_array( $plugin_folders ) ) {
			$plugin_folders = array();
		}

		if ( 0 === $folder_id ) {
			// Uncategorized: plugins NOT in any folder.
			return array_filter(
				$plugins,
				static function ( $plugin_file ) use ( $plugin_folders ) {
					return ! isset( $plugin_folders[ $plugin_file ] );
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		return array_filter(
			$plugins,
			static function ( $plugin_file ) use ( $plugin_folders, $folder_id ) {
				return isset( $plugin_folders[ $plugin_file ] ) && (int) $plugin_folders[ $plugin_file ] === $folder_id;
			},
			ARRAY_FILTER_USE_KEY
		);
	}
}
