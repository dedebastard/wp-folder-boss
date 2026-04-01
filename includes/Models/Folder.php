<?php
/**
 * Folder model — wraps the custom taxonomy term.
 *
 * @package WPFolderBoss\Models
 */

declare(strict_types=1);

namespace WPFolderBoss\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Folder
 */
class Folder {

	/**
	 * Term ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Folder name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * URL-friendly slug.
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * Parent term ID (0 = root).
	 *
	 * @var int
	 */
	public int $parent;

	/**
	 * Sort order.
	 *
	 * @var int
	 */
	public int $order;

	/**
	 * Context (media, post, page, product, etc.).
	 *
	 * @var string
	 */
	public string $context;

	/**
	 * Number of items assigned to this folder.
	 *
	 * @var int
	 */
	public int $count;

	/**
	 * Build a Folder from a WP_Term object.
	 *
	 * @param \WP_Term $term Term object.
	 * @return self
	 */
	public static function from_term( \WP_Term $term ): self {
		$folder          = new self();
		$folder->id      = (int) $term->term_id;
		$folder->name    = (string) $term->name;
		$folder->slug    = (string) $term->slug;
		$folder->parent  = (int) $term->parent;
		$folder->count   = (int) $term->count;
		$folder->order   = (int) get_term_meta( $term->term_id, 'wpfb_folder_order', true );
		$folder->context = (string) get_term_meta( $term->term_id, 'wpfb_folder_context', true );
		return $folder;
	}

	/**
	 * Convert the folder to an associative array (for REST responses).
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'id'      => $this->id,
			'name'    => $this->name,
			'slug'    => $this->slug,
			'parent'  => $this->parent,
			'order'   => $this->order,
			'context' => $this->context,
			'count'   => $this->count,
		);
	}
}
