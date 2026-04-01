<?php
/**
 * Import folders from other plugins (FileBird, Real Media Library).
 *
 * @package WPFolderBoss\Services
 */

declare(strict_types=1);

namespace WPFolderBoss\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ImportService
 */
class ImportService {

	/**
	 * Detect available import sources.
	 *
	 * @return string[] List of detectable source keys.
	 */
	public function get_available_sources(): array {
		$sources = array();

		// FileBird stores folders in the 'filebird_folder' taxonomy.
		if ( taxonomy_exists( 'filebird_folder' ) ) {
			$sources[] = 'filebird';
		}

		// Real Media Library stores in a custom table; check for the option.
		if ( get_option( 'rml_network_version' ) || defined( 'RML_VERSION' ) ) {
			$sources[] = 'real-media-library';
		}

		return $sources;
	}

	/**
	 * Import from FileBird.
	 *
	 * @return array{imported: int, errors: int} Result summary.
	 */
	public function import_from_filebird(): array {
		$imported = 0;
		$errors   = 0;

		$fb_terms = get_terms(
			array(
				'taxonomy'   => 'filebird_folder',
				'hide_empty' => false,
				'orderby'    => 'parent',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $fb_terms ) || empty( $fb_terms ) ) {
			return array( 'imported' => 0, 'errors' => 0 );
		}

		$folder_service   = new FolderService();
		$id_map           = array(); // FileBird term_id => WPFolderBoss term_id

		foreach ( $fb_terms as $fb_term ) {
			$parent_id = isset( $id_map[ (int) $fb_term->parent ] )
				? $id_map[ (int) $fb_term->parent ]
				: 0;

			$folder = $folder_service->create_folder(
				(string) $fb_term->name,
				'media',
				$parent_id,
				0
			);

			if ( is_wp_error( $folder ) ) {
				++$errors;
				continue;
			}

			$id_map[ (int) $fb_term->term_id ] = $folder->id;
			++$imported;

			// Migrate attachment relationships.
			$attachments = get_objects_in_term( (int) $fb_term->term_id, 'filebird_folder' );
			if ( ! is_wp_error( $attachments ) ) {
				$assign_service = new AssignmentService();
				$assign_service->assign(
					$folder->id,
					array_map( 'intval', $attachments ),
					'post'
				);
			}
		}

		return array( 'imported' => $imported, 'errors' => $errors );
	}

	/**
	 * Import from Real Media Library.
	 *
	 * Reads from the RML custom table if present.
	 *
	 * @return array{imported: int, errors: int} Result summary.
	 */
	public function import_from_real_media_library(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'realmedialibrary';

		// Check if the table exists.
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $exists ) {
			return array( 'imported' => 0, 'errors' => 0 );
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			"SELECT id, name, parent, ord FROM `{$table}` WHERE type = 'folder' ORDER BY parent ASC, ord ASC"
		);

		if ( empty( $rows ) ) {
			return array( 'imported' => 0, 'errors' => 0 );
		}

		$folder_service = new FolderService();
		$id_map         = array();
		$imported       = 0;
		$errors         = 0;

		foreach ( $rows as $row ) {
			$parent_id = isset( $id_map[ (int) $row->parent ] )
				? $id_map[ (int) $row->parent ]
				: 0;

			$folder = $folder_service->create_folder(
				(string) $row->name,
				'media',
				$parent_id,
				(int) $row->ord
			);

			if ( is_wp_error( $folder ) ) {
				++$errors;
				continue;
			}

			$id_map[ (int) $row->id ] = $folder->id;
			++$imported;

			// Migrate attachment-folder relationships from the posts meta table.
			$attachment_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_rml_folder' AND meta_value = %d",
					$row->id
				)
			);

			if ( ! empty( $attachment_ids ) ) {
				$assign_service = new AssignmentService();
				$assign_service->assign(
					$folder->id,
					array_map( 'intval', $attachment_ids ),
					'post'
				);
			}
		}

		return array( 'imported' => $imported, 'errors' => $errors );
	}
}
