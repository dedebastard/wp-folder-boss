<?php
/**
 * REST API controller for plugin settings.
 *
 * @package WPFolderBoss\Rest
 */

declare(strict_types=1);

namespace WPFolderBoss\Rest;

use WPFolderBoss\Helpers\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsController
 */
class SettingsController extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp-folder-boss/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_settings( $request ) {
		return rest_ensure_response( Utils::get_settings() );
	}

	/**
	 * Update settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( $request ) {
		$current  = Utils::get_settings();
		$incoming = $request->get_json_params();

		if ( ! is_array( $incoming ) ) {
			return new \WP_Error( 'wpfb_invalid_data', __( 'Invalid settings data.', 'wp-folder-boss' ), array( 'status' => 400 ) );
		}

		// Sanitize enabled_screens.
		if ( isset( $incoming['enabled_screens'] ) && is_array( $incoming['enabled_screens'] ) ) {
			$current['enabled_screens'] = array_map( 'boolval', $incoming['enabled_screens'] );
		}

		// Sanitize enabled_post_types.
		if ( isset( $incoming['enabled_post_types'] ) && is_array( $incoming['enabled_post_types'] ) ) {
			$current['enabled_post_types'] = array_map( 'sanitize_key', $incoming['enabled_post_types'] );
		}

		// Sanitize default_folder.
		if ( isset( $incoming['default_folder'] ) ) {
			$current['default_folder'] = absint( $incoming['default_folder'] );
		}

		update_option( 'wpfb_settings', $current );

		return rest_ensure_response( $current );
	}

	/**
	 * Permission check.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( $request ) {
		return Utils::current_user_can_manage();
	}
}
