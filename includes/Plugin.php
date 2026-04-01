<?php
/**
 * Main Plugin class — singleton, hooks registration.
 *
 * @package WPFolderBoss
 */

declare(strict_types=1);

namespace WPFolderBoss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin — register all hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-folder-boss',
			false,
			dirname( plugin_basename( WPFB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register the wpfb_folder custom taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$settings     = get_option( 'wpfb_settings', array() );
		$post_types   = $this->get_enabled_post_types( $settings );

		$labels = array(
			'name'              => _x( 'Folders', 'taxonomy general name', 'wp-folder-boss' ),
			'singular_name'     => _x( 'Folder', 'taxonomy singular name', 'wp-folder-boss' ),
			'search_items'      => __( 'Search Folders', 'wp-folder-boss' ),
			'all_items'         => __( 'All Folders', 'wp-folder-boss' ),
			'parent_item'       => __( 'Parent Folder', 'wp-folder-boss' ),
			'parent_item_colon' => __( 'Parent Folder:', 'wp-folder-boss' ),
			'edit_item'         => __( 'Edit Folder', 'wp-folder-boss' ),
			'update_item'       => __( 'Update Folder', 'wp-folder-boss' ),
			'add_new_item'      => __( 'Add New Folder', 'wp-folder-boss' ),
			'new_item_name'     => __( 'New Folder Name', 'wp-folder-boss' ),
			'menu_name'         => __( 'Folders', 'wp-folder-boss' ),
		);

		register_taxonomy(
			WPFB_TAXONOMY,
			$post_types,
			array(
				'labels'             => $labels,
				'hierarchical'       => true,
				'public'             => false,
				'show_ui'            => false,
				'show_admin_column'  => false,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => false,
				'query_var'          => false,
				'rewrite'            => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Get enabled post types from settings.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return string[]
	 */
	private function get_enabled_post_types( array $settings ): array {
		$defaults = array( 'attachment', 'post', 'page' );

		if ( isset( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] ) ) {
			$extra = array_map( 'sanitize_key', $settings['enabled_post_types'] );
			return array_unique( array_merge( $defaults, $extra ) );
		}

		// Also include WooCommerce post types if active.
		if ( class_exists( 'WooCommerce' ) ) {
			$defaults = array_merge( $defaults, array( 'product', 'shop_order', 'shop_coupon' ) );
		}

		return $defaults;
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		( new Rest\FolderController() )->register_routes();
		( new Rest\AssignmentController() )->register_routes();
		( new Rest\SettingsController() )->register_routes();
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	private function init_admin(): void {
		( new Admin\AdminMenu() )->init();
		( new Admin\Settings() )->init();
		( new Admin\MediaLibrary() )->init();
		( new Admin\PostTypeScreen() )->init();
		( new Admin\UsersScreen() )->init();
		( new Admin\PluginsScreen() )->init();

		if ( class_exists( 'WooCommerce' ) ) {
			( new Admin\WooCommerceScreen() )->init();
		}
	}
}
