<?php
/**
 * REST API controller for item-to-folder assignments.
 *
 * @package WPFolderBoss\Rest
 */

declare(strict_types=1);

namespace WPFolderBoss\Rest;

use WPFolderBoss\Services\AssignmentService;
use WPFolderBoss\Helpers\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AssignmentController
 */
class AssignmentController extends \WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp-folder-boss/v1';

	/**
	 * @var AssignmentService
	 */
	private AssignmentService $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new AssignmentService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/assign',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'assign_items' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'folder_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'item_ids'  => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
					'item_type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/unassign',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unassign_items' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'item_ids'  => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
					'item_type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Assign items to a folder.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function assign_items( $request ) {
		$folder_id = (int) $request->get_param( 'folder_id' );
		$item_ids  = array_map( 'absint', (array) $request->get_param( 'item_ids' ) );
		$item_type = (string) $request->get_param( 'item_type' );

		$result = $this->service->assign( $folder_id, $item_ids, $item_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'assigned' => count( $item_ids ) ) );
	}

	/**
	 * Unassign items from any folder.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function unassign_items( $request ) {
		$item_ids  = array_map( 'absint', (array) $request->get_param( 'item_ids' ) );
		$item_type = (string) $request->get_param( 'item_type' );

		$result = $this->service->unassign( $item_ids, $item_type );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'unassigned' => count( $item_ids ) ) );
	}

	/**
	 * Permission check for assignment endpoints.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function permissions_check( $request ) {
		return Utils::current_user_can_assign();
	}
}
