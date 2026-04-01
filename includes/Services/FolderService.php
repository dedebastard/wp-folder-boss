<?php
/**
 * CRUD operations for folders.
 *
 * @package WPFolderBoss\Services
 */

declare(strict_types=1);

namespace WPFolderBoss\Services;

use WPFolderBoss\Models\Folder;
use WPFolderBoss\Helpers\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FolderService
 */
class FolderService {

	/**
	 * Get all folders for a given context, sorted by order.
	 *
	 * @param string $context Context key (media, post, page, etc.).
	 * @return Folder[]
	 */
	public function get_folders( string $context ): array {
		$context = Utils::sanitize_context( $context );

		$terms = get_terms(
			array(
				'taxonomy'   => WPFB_TAXONOMY,
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => 'wpfb_folder_context',
						'value' => $context,
					),
				),
				'orderby'    => 'meta_value_num',
				'meta_key'   => 'wpfb_folder_order', // phpcs:ignore WordPress.DB.SlowDBQuery
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return array_map( array( Folder::class, 'from_term' ), $terms );
	}

	/**
	 * Create a new folder.
	 *
	 * @param string $name    Folder name.
	 * @param string $context Context key.
	 * @param int    $parent  Parent folder ID (0 for root).
	 * @param int    $order   Sort order.
	 * @return Folder|\WP_Error
	 */
	public function create_folder( string $name, string $context, int $parent = 0, int $order = 0 ) {
		$name    = Utils::sanitize_folder_name( $name );
		$context = Utils::sanitize_context( $context );

		if ( empty( $name ) ) {
			return new \WP_Error( 'wpfb_invalid_name', __( 'Folder name cannot be empty.', 'wp-folder-boss' ) );
		}

		$result = wp_insert_term(
			$name,
			WPFB_TAXONOMY,
			array(
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = (int) $result['term_id'];
		update_term_meta( $term_id, 'wpfb_folder_context', $context );
		update_term_meta( $term_id, 'wpfb_folder_order', $order );

		$term = get_term( $term_id, WPFB_TAXONOMY );
		if ( is_wp_error( $term ) || null === $term ) {
			return new \WP_Error( 'wpfb_term_not_found', __( 'Created folder could not be retrieved.', 'wp-folder-boss' ) );
		}

		return Folder::from_term( $term );
	}

	/**
	 * Update an existing folder.
	 *
	 * @param int                 $id   Term ID.
	 * @param array<string,mixed> $data Data to update (name, parent, order).
	 * @return Folder|\WP_Error
	 */
	public function update_folder( int $id, array $data ) {
		$term = get_term( $id, WPFB_TAXONOMY );
		if ( is_wp_error( $term ) || null === $term ) {
			return new \WP_Error( 'wpfb_not_found', __( 'Folder not found.', 'wp-folder-boss' ) );
		}

		$args = array();

		if ( isset( $data['name'] ) ) {
			$args['name'] = Utils::sanitize_folder_name( (string) $data['name'] );
		}

		if ( isset( $data['parent'] ) ) {
			$args['parent'] = absint( $data['parent'] );
		}

		if ( ! empty( $args ) ) {
			$result = wp_update_term( $id, WPFB_TAXONOMY, $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $data['order'] ) ) {
			update_term_meta( $id, 'wpfb_folder_order', absint( $data['order'] ) );
		}

		$updated_term = get_term( $id, WPFB_TAXONOMY );
		if ( is_wp_error( $updated_term ) || null === $updated_term ) {
			return new \WP_Error( 'wpfb_term_not_found', __( 'Updated folder could not be retrieved.', 'wp-folder-boss' ) );
		}

		return Folder::from_term( $updated_term );
	}

	/**
	 * Delete a folder (does NOT delete items — they become uncategorized).
	 *
	 * @param int $id Term ID.
	 * @return true|\WP_Error
	 */
	public function delete_folder( int $id ) {
		$term = get_term( $id, WPFB_TAXONOMY );
		if ( is_wp_error( $term ) || null === $term ) {
			return new \WP_Error( 'wpfb_not_found', __( 'Folder not found.', 'wp-folder-boss' ) );
		}

		// Re-parent any child terms to the parent of the deleted term.
		$children = get_term_children( $id, WPFB_TAXONOMY );
		if ( ! is_wp_error( $children ) ) {
			$parent = (int) $term->parent;
			foreach ( $children as $child_id ) {
				$child = get_term( (int) $child_id, WPFB_TAXONOMY );
				if ( ! is_wp_error( $child ) && null !== $child && (int) $child->parent === $id ) {
					wp_update_term( (int) $child_id, WPFB_TAXONOMY, array( 'parent' => $parent ) );
				}
			}
		}

		$result = wp_delete_term( $id, WPFB_TAXONOMY );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get the folder tree for a context as a nested array.
	 *
	 * @param string $context Context key.
	 * @return array<int,mixed>
	 */
	public function get_folder_tree( string $context ): array {
		$folders = $this->get_folders( $context );
		return Utils::build_tree( $folders );
	}
}
