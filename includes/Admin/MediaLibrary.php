<?php
/**
 * Hooks into Media Library grid & list views.
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
 * Class MediaLibrary
 */
class MediaLibrary {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! Utils::is_screen_enabled( 'media' ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// List view sidebar (rendered inside the filter bar area).
		add_action( 'restrict_manage_posts', array( $this, 'render_list_sidebar' ) );

		// Grid view sidebar (rendered in the footer, positioned via CSS).
		add_action( 'admin_footer-upload.php', array( $this, 'render_grid_sidebar' ) );

		// Filter query when a folder is selected (list view).
		add_action( 'pre_get_posts', array( $this, 'filter_by_folder' ) );

		// Filter AJAX query for grid view.
		add_filter( 'ajax_query_attachments_args', array( $this, 'filter_ajax_attachments' ) );

		// Add Folder column to media list view.
		add_filter( 'manage_upload_columns', array( $this, 'add_folder_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_folder_column' ), 10, 2 );

		// Attachment edit screen — folder dropdown.
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_folder_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_folder_field' ), 10, 2 );

		// Auto-assign uploads.
		add_action( 'add_attachment', array( $this, 'auto_assign_upload' ) );

		// Media library script for grid view.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_script' ) );
	}

	/**
	 * Enqueue assets for the media library screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		Utils::enqueue_admin_assets();
	}

	/**
	 * Enqueue media-library.js on the media screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_media_script( string $hook ): void {
		if ( 'upload.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wpfb-media-library',
			WPFB_PLUGIN_URL . 'assets/js/media-library.js',
			array( 'wpfb-folder-tree', 'media', 'jquery' ),
			WPFB_VERSION,
			true
		);

		// Pass folders for the media context.
		$service = new FolderService();
		$folders = $service->get_folders( 'media' );
		$data    = array_map( fn( $f ) => $f->to_array(), $folders );

		wp_localize_script(
			'wpfb-media-library',
			'wpfbMediaData',
			array(
				'folders'       => $data,
				'defaultFolder' => $this->get_default_folder(),
			)
		);
	}

	/**
	 * Render the folder tree sidebar for the list view.
	 *
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function render_list_sidebar( string $post_type ): void {
		if ( 'attachment' !== $post_type ) {
			return;
		}
		$service = new FolderService();
		$folders = $service->get_folders( 'media' );
		$context = 'media';
		require WPFB_PLUGIN_DIR . 'templates/folder-tree-sidebar.php';
	}

	/**
	 * Render the folder tree sidebar for the grid view (in footer).
	 * Uses a different ID so it doesn't conflict with the list view sidebar.
	 *
	 * @return void
	 */
	public function render_grid_sidebar(): void {
		$service = new FolderService();
		$folders = $service->get_folders( 'media' );
		$context = 'media';

		// Output a grid-specific sidebar with a different wrapper ID.
		?>
		<div id="wpfb-grid-sidebar" class="wpfb-sidebar" data-context="<?php echo esc_attr( $context ); ?>" style="display:none">
			<div class="wpfb-sidebar-header">
				<span class="wpfb-sidebar-title"><?php esc_html_e( 'Folders', 'wp-folder-boss' ); ?></span>
				<button type="button" class="wpfb-add-folder-btn button button-small" title="<?php esc_attr_e( 'Add Folder', 'wp-folder-boss' ); ?>">+</button>
			</div>

			<ul class="wpfb-folder-tree" id="wpfb-grid-folder-tree" role="tree">
				<li class="wpfb-folder-item wpfb-virtual" data-id="-1" role="treeitem" aria-selected="false">
					<span class="wpfb-folder-node">
						<span class="wpfb-toggle-placeholder"></span>
						<img src="<?php echo esc_url( WPFB_PLUGIN_URL . 'assets/images/icon-folder.svg' ); ?>" class="wpfb-folder-icon" alt="" />
						<span class="wpfb-folder-name"><?php esc_html_e( 'All Items', 'wp-folder-boss' ); ?></span>
					</span>
				</li>
				<li class="wpfb-folder-item wpfb-virtual" data-id="0" role="treeitem" aria-selected="false">
					<span class="wpfb-folder-node">
						<span class="wpfb-toggle-placeholder"></span>
						<img src="<?php echo esc_url( WPFB_PLUGIN_URL . 'assets/images/icon-folder.svg' ); ?>" class="wpfb-folder-icon" alt="" />
						<span class="wpfb-folder-name"><?php esc_html_e( 'Uncategorized', 'wp-folder-boss' ); ?></span>
					</span>
				</li>
				<?php
				foreach ( $folders as $folder ) {
					if ( 0 === $folder->parent ) {
						\wpfb_render_folder_node( $folder, $folders );
					}
				}
				?>
			</ul>

			<div class="wpfb-resize-handle" title="<?php esc_attr_e( 'Drag to resize', 'wp-folder-boss' ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Filter the media query by the selected folder (list view).
	 *
	 * @param \WP_Query $query Main WP_Query.
	 * @return void
	 */
	public function filter_by_folder( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'upload' !== $screen->id ) {
			return;
		}

		$folder_id = isset( $_GET['wpfb_folder'] ) ? absint( wp_unslash( $_GET['wpfb_folder'] ) ) : -1; // phpcs:ignore WordPress.Security.NonceVerification

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
	 * Filter the AJAX attachments query for the grid view.
	 *
	 * @param array<string,mixed> $query Query arguments.
	 * @return array<string,mixed>
	 */
	public function filter_ajax_attachments( array $query ): array {
		// phpcs:ignore WordPress.Security.NonceVerification
		$folder_id = isset( $_REQUEST['query']['wpfb_folder'] ) ? absint( $_REQUEST['query']['wpfb_folder'] ) : -1;

		if ( $folder_id < 0 ) {
			return $query;
		}

		if ( 0 === $folder_id ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => WPFB_TAXONOMY,
					'operator' => 'NOT EXISTS',
				),
			);
		} else {
			$query['tax_query'] = array(
				array(
					'taxonomy' => WPFB_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $folder_id,
				),
			);
		}

		return $query;
	}

	/**
	 * Add Folder column to media list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_folder_column( array $columns ): array {
		$columns['wpfb_folder'] = __( 'Folder', 'wp-folder-boss' );
		return $columns;
	}

	/**
	 * Render the Folder column for each attachment.
	 *
	 * @param string $column_name Column ID.
	 * @param int    $post_id     Attachment post ID.
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

		echo esc_html__( 'Uncategorized', 'wp-folder-boss' );
	}

	/**
	 * Add folder field to the attachment edit modal.
	 *
	 * @param array<string,mixed> $fields     Existing fields.
	 * @param \WP_Post            $attachment Attachment post.
	 * @return array<string,mixed>
	 */
	public function add_folder_field( array $fields, \WP_Post $attachment ): array {
		$service = new FolderService();
		$folders = $service->get_folders( 'media' );

		$assign     = new AssignmentService();
		$current_id = $assign->get_post_folder( (int) $attachment->ID );

		$html  = '<select name="attachments[' . esc_attr( (string) $attachment->ID ) . '][wpfb_folder]">';
		$html .= '<option value="0">' . esc_html__( 'Uncategorized', 'wp-folder-boss' ) . '</option>';

		foreach ( $folders as $folder ) {
			$html .= '<option value="' . esc_attr( (string) $folder->id ) . '"' . selected( $current_id, $folder->id, false ) . '>'
				. esc_html( $folder->name )
				. '</option>';
		}

		$html .= '</select>';

		$fields['wpfb_folder'] = array(
			'label' => __( 'Folder', 'wp-folder-boss' ),
			'input' => 'html',
			'html'  => $html,
		);

		return $fields;
	}

	/**
	 * Save folder field when attachment is updated.
	 *
	 * @param array<string,mixed> $post       Post data array.
	 * @param array<string,mixed> $attachment Attachment data from POST.
	 * @return array<string,mixed>
	 */
	public function save_folder_field( array $post, array $attachment ): array {
		if ( ! isset( $attachment['wpfb_folder'] ) ) {
			return $post;
		}

		$folder_id = absint( $attachment['wpfb_folder'] );
		$assign    = new AssignmentService();
		$assign->assign( $folder_id, array( (int) $post['ID'] ), 'post' );

		return $post;
	}

	/**
	 * Auto-assign a newly uploaded attachment to the default folder (if set).
	 *
	 * @param int $attachment_id New attachment post ID.
	 * @return void
	 */
	public function auto_assign_upload( int $attachment_id ): void {
		$default = $this->get_default_folder();
		if ( ! $default ) {
			return;
		}

		$assign = new AssignmentService();
		$assign->assign( $default, array( $attachment_id ), 'post' );
	}

	/**
	 * Get the configured default folder ID.
	 *
	 * @return int
	 */
	private function get_default_folder(): int {
		$settings = Utils::get_settings();
		return isset( $settings['default_folder'] ) ? absint( $settings['default_folder'] ) : 0;
	}
}
