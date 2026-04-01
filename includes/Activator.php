<?php
/**
 * Activation hook handler.
 *
 * @package WPFolderBoss
 */

declare(strict_types=1);

namespace WPFolderBoss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Set default settings if not already present.
		if ( false === get_option( 'wpfb_settings' ) ) {
			$woo_active = class_exists( 'WooCommerce' );

			$defaults = array(
				'enabled_screens'    => array(
					'media'       => true,
					'post'        => true,
					'page'        => true,
					'users'       => false,
					'plugins'     => false,
					'product'     => $woo_active,
					'shop_order'  => $woo_active,
					'shop_coupon' => $woo_active,
				),
				'enabled_post_types' => array( 'post', 'page' ),
				'default_folder'     => 0,
			);

			update_option( 'wpfb_settings', $defaults );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
