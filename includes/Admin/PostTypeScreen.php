<?php
/**
 * Hooks into post type list table screens.
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
 * Class PostTypeScreen
 */
class PostTypeScreen {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_sidebar_and_filter' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'filter_by_folder' ) );
		add_filter( 'manage_posts_columns', array( $this, 'add_folder_column' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_folder_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_folder_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_folder_column' ), 10, 2 );
	}

	/**
	 * Get the list of post types that have folder support enabled.
	 *
	 * @return string[]
	 */
	private function get_enabled_post_types(): array {
		$settings    = Utils::get_settings();
		$all_types   = array();
		$woo_types   = array( 'product', 'shop_order', 'shop_coupon' );

		$screens = $settings['enabled_screens'] ?? array();

		if ( ! empty( $screens['post'] ) ) {
			$all_types[] = 'post';
		}
		if ( ! empty( $screens['page'] ) ) {
			$all_types[] = 'page';
		}

		// Add CPTs from enabled_post_types.
		if ( ! empty( $settings['enabled_post_types'] ) ) {
			foreach ( $settings['enabled_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( $pt && ! in_array( $pt, array_merge( $all_types, $woo_types ), true ) ) {
					$all_types[] = $pt;
				}
			}
		}

		return $all_types;
	}

	/**
	 * Enqueue assets only on enabled post type list table screens.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function maybe_enqueue_assets( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		if ( in_array( $screen->post_type, $this->get_enabled_post_types(), true ) ) {
			Utils::enqueue_admin_assets();
		}
	}

	/**
	 * Render the folder tree sidebar and filter dropdown on enabled list tables.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_sidebar_and_filter( string $post_type ): void {
		if ( ! in_array( $post_type, $this->get_enabled_post_types(), true ) ) {
			return;
		}

		$service = new FolderService();
		$folders = $service->get_folders( $post_type );
		$context = $post_type;
		require WPFB_PLUGIN_DIR . 'templates/folder-tree-sidebar.php';

		// Render a folder filter dropdown.
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
	 * Filter the post query by the selected folder.
	 *
	 * @param \WP_Query $query Main WP_Query.
	 * @return void
	 */
	public function filter_by_folder( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base ) {
			return;
		}

		if ( ! in_array( $screen->post_type, $this->get_enabled_post_types(), true ) ) {
			return;
		}

		$folder_id = isset( $_GET['wpfb_folder'] ) ? (int) wp_unslash( $_GET['wpfb_folder'] ) : - 1; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $folder_id < 0 ) {
			return;
		}

		if ( 0 === $folder_id ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => WPFB_TAXONOMY,
						'operator' => 'NOT EXISTS',
					),
				)
			);
		} else {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => WPFB_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $folder_id,
					),
				)
			);
		}
	}

	/**
	 * Add Folder column.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_folder_column( array $columns ): array {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->post_type, $this->get_enabled_post_types(), true ) ) {
			$columns['wpfb_folder'] = __( 'Folder', 'wp-folder-boss' );
		}
		return $columns;
	}

	/**
	 * Render the Folder column value.
	 *
	 * @param string $column_name Column ID.
	 * @param int    $post_id     Post ID.
	 * @return void
	 */
	public function render_folder_column( string $column_name, int $post_id ): void {
		if ( 'wpfb_folder' !== $column_name ) {
			return;
		}

		$assign  = new AssignmentService();
		$term_id = $assign->get_post_folder( $post_id );

		if ( $term_id ) {
			$term = get_term( $term_id, WPFB_TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				echo esc_html( $term->name );
				return;
			}
		}

		echo esc_html__( '—', 'wp-folder-boss' );
	}
}
