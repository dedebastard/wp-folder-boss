<?php
/**
 * Assign/unassign items to folders, bulk move.
 *
 * @package WPFolderBoss\Services
 */

declare(strict_types=1);

namespace WPFolderBoss\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AssignmentService
 */
class AssignmentService {

	/**
	 * Assign an array of items to a folder.
	 *
	 * @param int      $folder_id  Term ID of the target folder (0 = uncategorized).
	 * @param int[]    $item_ids   Array of object IDs.
	 * @param string   $item_type  'post', 'user', or 'plugin'.
	 * @return true|\WP_Error
	 */
	public function assign( int $folder_id, array $item_ids, string $item_type ) {
		$item_type = sanitize_key( $item_type );

		if ( 'user' === $item_type ) {
			return $this->assign_users( $folder_id, $item_ids );
		}

		if ( 'plugin' === $item_type ) {
			return $this->assign_plugins( $folder_id, $item_ids );
		}

		// Default: post-based assignment via taxonomy.
		foreach ( $item_ids as $item_id ) {
			$item_id = absint( $item_id );
			if ( ! $item_id ) {
				continue;
			}

			if ( 0 === $folder_id ) {
				wp_set_object_terms( $item_id, array(), WPFB_TAXONOMY );
			} else {
				wp_set_object_terms( $item_id, array( $folder_id ), WPFB_TAXONOMY );
			}
		}

		return true;
	}

	/**
	 * Unassign items from any folder.
	 *
	 * @param int[]  $item_ids  Array of object IDs.
	 * @param string $item_type 'post', 'user', or 'plugin'.
	 * @return true|\WP_Error
	 */
	public function unassign( array $item_ids, string $item_type ) {
		return $this->assign( 0, $item_ids, $item_type );
	}

	/**
	 * Assign users to a folder via user meta.
	 *
	 * @param int   $folder_id Folder term ID.
	 * @param int[] $user_ids  Array of user IDs.
	 * @return true
	 */
	private function assign_users( int $folder_id, array $user_ids ): bool {
		foreach ( $user_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				continue;
			}

			if ( 0 === $folder_id ) {
				delete_user_meta( $user_id, 'wpfb_user_folder' );
			} else {
				update_user_meta( $user_id, 'wpfb_user_folder', $folder_id );
			}
		}

		return true;
	}

	/**
	 * Assign plugins (by file path) to a folder via options.
	 *
	 * @param int   $folder_id   Folder term ID.
	 * @param int[] $plugin_keys Array of plugin file keys (encoded as IDs for consistency).
	 * @return true
	 */
	private function assign_plugins( int $folder_id, array $plugin_keys ): bool {
		$plugin_folders = get_option( 'wpfb_plugin_folders', array() );
		if ( ! is_array( $plugin_folders ) ) {
			$plugin_folders = array();
		}

		foreach ( $plugin_keys as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( empty( $key ) ) {
				continue;
			}

			if ( 0 === $folder_id ) {
				unset( $plugin_folders[ $key ] );
			} else {
				$plugin_folders[ $key ] = $folder_id;
			}
		}

		update_option( 'wpfb_plugin_folders', $plugin_folders );

		return true;
	}

	/**
	 * Get the folder assigned to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Folder term ID or 0 if uncategorized.
	 */
	public function get_post_folder( int $post_id ): int {
		$terms = wp_get_object_terms( $post_id, WPFB_TAXONOMY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}
		return (int) reset( $terms );
	}

	/**
	 * Get the folder assigned to a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Folder term ID or 0 if uncategorized.
	 */
	public function get_user_folder( int $user_id ): int {
		return (int) get_user_meta( $user_id, 'wpfb_user_folder', true );
	}
}
