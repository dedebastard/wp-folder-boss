<?php
/**
 * Deactivation hook handler.
 *
 * @package WPFolderBoss
 */

declare(strict_types=1);

namespace WPFolderBoss;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
