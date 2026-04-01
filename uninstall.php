<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package WPFolderBoss
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all wpfb_folder taxonomy terms and relationships.
$terms = get_terms(
	array(
		'taxonomy'   => 'wpfb_folder',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( (int) $term_id, 'wpfb_folder' );
	}
}

// Remove all plugin options.
delete_option( 'wpfb_settings' );
delete_option( 'wpfb_plugin_folders' );

// Remove all user meta.
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'wpfb_user_folder' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Drop custom tables if any were created during activation.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpfb_folder_order" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
