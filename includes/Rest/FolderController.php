<?php
/**
 * REST API controller for folder CRUD.
 *
 * @package WPFolderBoss\Rest
 */

declare(strict_types=1);

namespace WPFolderBoss\Rest;

use WPFolderBoss\Services\FolderService;
use WPFolderBoss\Helpers\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FolderController
 */
class FolderController extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp-folder-boss/v1';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'folders';

	/**
	 * @var FolderService
	 */
	private FolderService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new FolderService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'context_key' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'name'    => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'context_key' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
						'parent'  => array(
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
						'order'   => array(
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'id'     => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'name'   => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'parent' => array(
							'sanitize_callback' => 'absint',
						),
						'order'  => array(
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Get folders list.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$context = $request->get_param( 'context_key' );
		$folders = $this->service->get_folders( (string) $context );

		$data = array();
		foreach ( $folders as $folder ) {
			$data[] = $folder->to_array();
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Create a folder.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$folder = $this->service->create_folder(
			(string) $request->get_param( 'name' ),
			(string) $request->get_param( 'context_key' ),
			(int) $request->get_param( 'parent' ),
			(int) $request->get_param( 'order' )
		);

		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		return rest_ensure_response( $folder->to_array() );
	}

	/**
	 * Update a folder.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$data = array();

		if ( null !== $request->get_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( null !== $request->get_param( 'parent' ) ) {
			$data['parent'] = $request->get_param( 'parent' );
		}
		if ( null !== $request->get_param( 'order' ) ) {
			$data['order'] = $request->get_param( 'order' );
		}

		$folder = $this->service->update_folder( $id, $data );

		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		return rest_ensure_response( $folder->to_array() );
	}

	/**
	 * Delete a folder.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$result = $this->service->delete_folder( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return Utils::current_user_can_assign();
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return Utils::current_user_can_manage();
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return Utils::current_user_can_manage();
	}

	/**
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return Utils::current_user_can_manage();
	}
}
