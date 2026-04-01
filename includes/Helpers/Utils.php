<?php
/**
 * Utility helper functions.
 *
 * @package WPFolderBoss\Helpers
 */

declare(strict_types=1);

namespace WPFolderBoss\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Utils
 */
class Utils {

	/**
	 * Check if current user can manage folders.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can edit posts (assign folders).
	 *
	 * @return bool
	 */
	public static function current_user_can_assign(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitize a folder name.
	 *
	 * @param string $name Raw folder name.
	 * @return string
	 */
	public static function sanitize_folder_name( string $name ): string {
		return sanitize_text_field( $name );
	}

	/**
	 * Sanitize a context string.
	 *
	 * @param string $context Raw context.
	 * @return string
	 */
	public static function sanitize_context( string $context ): string {
		return sanitize_key( $context );
	}

	/**
	 * Get the nonce action for a given operation.
	 *
	 * @param string $action Operation name.
	 * @return string
	 */
	public static function nonce_action( string $action ): string {
		return 'wpfb_' . sanitize_key( $action );
	}

	/**
	 * Verify a nonce and return bool.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Action name.
	 * @return bool
	 */
	public static function verify_nonce( string $nonce, string $action ): bool {
		return (bool) wp_verify_nonce( $nonce, self::nonce_action( $action ) );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings(): array {
		$settings = get_option( 'wpfb_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Check whether a specific screen is enabled in settings.
	 *
	 * @param string $screen Screen key (media, post, page, users, plugins, etc.).
	 * @return bool
	 */
	public static function is_screen_enabled( string $screen ): bool {
		$settings = self::get_settings();
		return ! empty( $settings['enabled_screens'][ $screen ] );
	}

	/**
	 * Build folder tree array from flat list of Folder models.
	 *
	 * @param \WPFolderBoss\Models\Folder[] $folders Flat array of Folder objects.
	 * @param int                           $parent  Parent ID to start from.
	 * @return array<int,mixed>
	 */
	public static function build_tree( array $folders, int $parent = 0 ): array {
		$tree = array();
		foreach ( $folders as $folder ) {
			if ( (int) $folder->parent === $parent ) {
				$children         = self::build_tree( $folders, $folder->id );
				$node             = $folder->to_array();
				$node['children'] = $children;
				$tree[]           = $node;
			}
		}
		return $tree;
	}

	/**
	 * Enqueue shared admin assets.
	 *
	 * @return void
	 */
	public static function enqueue_admin_assets(): void {
		wp_enqueue_style(
			'wpfb-admin',
			WPFB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPFB_VERSION
		);

		wp_enqueue_script(
			'wpfb-folder-tree',
			WPFB_PLUGIN_URL . 'assets/js/folder-tree.js',
			array(),
			WPFB_VERSION,
			true
		);

		wp_enqueue_script(
			'wpfb-drag-drop',
			WPFB_PLUGIN_URL . 'assets/js/drag-drop.js',
			array( 'wpfb-folder-tree' ),
			WPFB_VERSION,
			true
		);

		wp_enqueue_script(
			'wpfb-bulk-actions',
			WPFB_PLUGIN_URL . 'assets/js/bulk-actions.js',
			array( 'wpfb-folder-tree' ),
			WPFB_VERSION,
			true
		);

		wp_localize_script(
			'wpfb-folder-tree',
			'wpfbData',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'wp-folder-boss/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'folderIcon' => esc_url( WPFB_PLUGIN_URL . 'assets/images/icon-folder.svg' ),
				'i18n'      => array(
					'allItems'      => __( 'All Items', 'wp-folder-boss' ),
					'uncategorized' => __( 'Uncategorized', 'wp-folder-boss' ),
					'newFolder'     => __( 'New Folder', 'wp-folder-boss' ),
					'newSubfolder'  => __( 'New Subfolder', 'wp-folder-boss' ),
					'rename'        => __( 'Rename', 'wp-folder-boss' ),
					'delete'        => __( 'Delete', 'wp-folder-boss' ),
					'confirmDelete' => __( 'Delete this folder? Items inside will become uncategorized.', 'wp-folder-boss' ),
					'moveToFolder'  => __( 'Move to Folder', 'wp-folder-boss' ),
				),
			)
		);
	}
}
