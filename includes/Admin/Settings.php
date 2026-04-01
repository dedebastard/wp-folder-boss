<?php
/**
 * Settings page handler.
 *
 * @package WPFolderBoss\Admin
 */

declare(strict_types=1);

namespace WPFolderBoss\Admin;

use WPFolderBoss\Helpers\Utils;
use WPFolderBoss\Services\ImportService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wpfb_import', array( $this, 'handle_import' ) );
	}

	/**
	 * Register WordPress settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'wpfb_settings_group',
			'wpfb_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize the settings array before saving.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return Utils::get_settings();
		}

		$clean = array();

		// Enabled screens.
		$screens = array( 'media', 'post', 'page', 'users', 'plugins', 'product', 'shop_order', 'shop_coupon' );
		foreach ( $screens as $screen ) {
			$clean['enabled_screens'][ $screen ] = ! empty( $input['enabled_screens'][ $screen ] );
		}

		// Enabled post types.
		if ( isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] ) ) {
			$clean['enabled_post_types'] = array_map( 'sanitize_key', $input['enabled_post_types'] );
		} else {
			$clean['enabled_post_types'] = array();
		}

		// Default folder.
		$clean['default_folder'] = isset( $input['default_folder'] ) ? absint( $input['default_folder'] ) : 0;

		return $clean;
	}

	/**
	 * Handle import form submission.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wp-folder-boss' ) );
		}

		check_admin_referer( 'wpfb_import', 'wpfb_import_nonce' );

		$source = isset( $_POST['wpfb_import_source'] )
			? sanitize_key( wp_unslash( $_POST['wpfb_import_source'] ) )
			: '';

		$service = new ImportService();
		$result  = array( 'imported' => 0, 'errors' => 0 );

		if ( 'filebird' === $source ) {
			$result = $service->import_from_filebird();
		} elseif ( 'real-media-library' === $source ) {
			$result = $service->import_from_real_media_library();
		}

		$redirect = add_query_arg(
			array(
				'page'           => 'wp-folder-boss',
				'wpfb_imported'  => $result['imported'],
				'wpfb_errors'    => $result['errors'],
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}
}
