<?php
/**
 * Hooks into the Users list screen.
 *
 * @package WPFolderBoss\Admin
 */

declare(strict_types=1);

namespace WPFolderBoss\Admin;

use WPFolderBoss\Helpers\Utils;
use WPFolderBoss\Services\FolderService;
use WPFolderBoss\Services\AssignmentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UsersScreen
 */
class UsersScreen {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! Utils::is_screen_enabled( 'users' ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'restrict_manage_users', array( $this, 'render_sidebar_and_filter' ) );
		add_filter( 'manage_users_columns', array( $this, 'add_folder_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_folder_column' ), 10, 3 );
		add_action( 'pre_get_users', array( $this, 'filter_by_folder' ) );
	}

	/**
	 * Enqueue assets on users.php.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'users.php' !== $hook ) {
			return;
		}
		Utils::enqueue_admin_assets();
	}

	/**
	 * Render the sidebar and filter dropdown on the Users screen.
	 *
	 * @return void
	 */
	public function render_sidebar_and_filter(): void {
		$service = new FolderService();
		$folders = $service->get_folders( 'users' );
		$context = 'users';
		require WPFB_PLUGIN_DIR . 'templates/folder-tree-sidebar.php';

		$selected = isset( $_GET['wpfb_folder'] ) ? absint( wp_unslash( $_GET['wpfb_folder'] ) ) : - 1; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="wpfb_folder" id="wpfb_folder_filter" style="margin-left:4px">';
		echo '<option value="-1">' . esc_html__( 'All Folders', 'wp-folder-boss' ) . '</option>';
		echo '<option value="0"' . selected( $selected, 0, false ) . '>' . esc_html__( 'Uncategorized', 'wp-folder-boss' ) . '</option>';
		foreach ( $folders as $folder ) {
			echo '<option value="' . esc_attr( (string) $folder->id ) . '"' . selected( $selected, $folder->id, false ) . '>'
				. esc_html( $folder->name )
				. '</option>';
		}
		echo '</select>';
	}

	/**
	 * Add Folder column.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_folder_column( array $columns ): array {
		$columns['wpfb_folder'] = __( 'Folder', 'wp-folder-boss' );
		return $columns;
	}

	/**
	 * Render the Folder column value.
	 *
	 * @param string $value       Current column value.
	 * @param string $column_name Column ID.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_folder_column( string $value, string $column_name, int $user_id ): string {
		if ( 'wpfb_folder' !== $column_name ) {
			return $value;
		}

		$assign    = new AssignmentService();
		$folder_id = $assign->get_user_folder( $user_id );

		if ( $folder_id ) {
			$term = get_term( $folder_id, WPFB_TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				return esc_html( $term->name );
			}
		}

		return esc_html__( '—', 'wp-folder-boss' );
	}

	/**
	 * Filter user list by the selected folder.
	 *
	 * @param \WP_User_Query $query User query.
	 * @return void
	 */
	public function filter_by_folder( \WP_User_Query $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		$folder_id = isset( $_GET['wpfb_folder'] ) ? (int) wp_unslash( $_GET['wpfb_folder'] ) : - 1; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $folder_id < 0 ) {
			return;
		}

		if ( 0 === $folder_id ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => 'wpfb_user_folder',
						'compare' => 'NOT EXISTS',
					),
				)
			);
		} else {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => 'wpfb_user_folder',
						'value' => $folder_id,
					),
				)
			);
		}
	}
}
