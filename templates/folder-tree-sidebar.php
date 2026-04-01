<?php
/**
 * Folder tree sidebar template.
 *
 * Expected variables:
 *   $folders  Folder[] — flat list of Folder objects for the current context.
 *   $context  string   — context key (media, post, page, etc.).
 *
 * @package WPFolderBoss
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Recursively renders a folder <li> node and its children.
 *
 * @param \WPFolderBoss\Models\Folder   $folder      Current folder.
 * @param \WPFolderBoss\Models\Folder[] $all_folders All folders (flat list).
 * @return void
 */
function wpfb_render_folder_node( \WPFolderBoss\Models\Folder $folder, array $all_folders ): void {
$children     = array_filter( $all_folders, static fn( $f ) => $f->parent === $folder->id );
$has_children = ! empty( $children );
?>
<li class="wpfb-folder-item <?php echo $has_children ? 'wpfb-has-children' : ''; ?>"
data-id="<?php echo esc_attr( (string) $folder->id ); ?>"
data-parent="<?php echo esc_attr( (string) $folder->parent ); ?>"
data-order="<?php echo esc_attr( (string) $folder->order ); ?>"
role="treeitem"
aria-expanded="false"
aria-selected="false"
draggable="true"
>
<span class="wpfb-folder-node">
<?php if ( $has_children ) : ?>
<button type="button" class="wpfb-toggle-btn" aria-label="<?php esc_attr_e( 'Toggle folder', 'wp-folder-boss' ); ?>">&#9658;</button>
<?php else : ?>
<span class="wpfb-toggle-placeholder"></span>
<?php endif; ?>
<img src="<?php echo esc_url( WPFB_PLUGIN_URL . 'assets/images/icon-folder.svg' ); ?>" class="wpfb-folder-icon" alt="" />
<span class="wpfb-folder-name"><?php echo esc_html( $folder->name ); ?></span>
<span class="wpfb-folder-count"><?php echo esc_html( (string) $folder->count ); ?></span>
</span>
<?php if ( $has_children ) : ?>
<ul class="wpfb-folder-children" role="group" style="display:none">
<?php foreach ( $children as $child ) : ?>
<?php wpfb_render_folder_node( $child, $all_folders ); ?>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</li>
<?php
}
?>
<div id="wpfb-sidebar" class="wpfb-sidebar" data-context="<?php echo esc_attr( $context ); ?>">
<div class="wpfb-sidebar-header">
<span class="wpfb-sidebar-title"><?php esc_html_e( 'Folders', 'wp-folder-boss' ); ?></span>
<button type="button" class="wpfb-add-folder-btn button button-small" title="<?php esc_attr_e( 'Add Folder', 'wp-folder-boss' ); ?>">+</button>
</div>

<ul class="wpfb-folder-tree" id="wpfb-folder-tree" role="tree">
<li class="wpfb-folder-item wpfb-virtual" data-id="-1" role="treeitem" aria-selected="false">
<span class="wpfb-folder-node">
<span class="wpfb-toggle-placeholder"></span>
<img src="<?php echo esc_url( WPFB_PLUGIN_URL . 'assets/images/icon-folder.svg' ); ?>" class="wpfb-folder-icon" alt="" />
<span class="wpfb-folder-name"><?php esc_html_e( 'All Items', 'wp-folder-boss' ); ?></span>
</span>
</li>
<li class="wpfb-folder-item wpfb-virtual" data-id="0" role="treeitem" aria-selected="false">
<span class="wpfb-folder-node">
<span class="wpfb-toggle-placeholder"></span>
<img src="<?php echo esc_url( WPFB_PLUGIN_URL . 'assets/images/icon-folder.svg' ); ?>" class="wpfb-folder-icon" alt="" />
<span class="wpfb-folder-name"><?php esc_html_e( 'Uncategorized', 'wp-folder-boss' ); ?></span>
</span>
</li>
<?php foreach ( $folders as $folder ) : ?>
<?php if ( 0 === $folder->parent ) : ?>
<?php wpfb_render_folder_node( $folder, $folders ); ?>
<?php endif; ?>
<?php endforeach; ?>
</ul>

<div class="wpfb-resize-handle" title="<?php esc_attr_e( 'Drag to resize', 'wp-folder-boss' ); ?>"></div>
</div>

<!-- Context menu (hidden, shown on right-click) -->
<div id="wpfb-context-menu" class="wpfb-context-menu" style="display:none" role="menu">
<ul>
<li role="menuitem" data-action="new-folder"><?php esc_html_e( 'New Folder', 'wp-folder-boss' ); ?></li>
<li role="menuitem" data-action="new-subfolder"><?php esc_html_e( 'New Subfolder', 'wp-folder-boss' ); ?></li>
<li role="menuitem" data-action="rename"><?php esc_html_e( 'Rename', 'wp-folder-boss' ); ?></li>
<li role="menuitem" data-action="delete" class="wpfb-menu-danger"><?php esc_html_e( 'Delete', 'wp-folder-boss' ); ?></li>
</ul>
</div>

<!-- Folder picker modal (for bulk move) -->
<div id="wpfb-folder-modal" class="wpfb-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="wpfb-modal-title">
<div class="wpfb-modal-inner">
<h2 id="wpfb-modal-title"><?php esc_html_e( 'Move to Folder', 'wp-folder-boss' ); ?></h2>
<ul class="wpfb-modal-tree" id="wpfb-modal-tree"></ul>
<div class="wpfb-modal-actions">
<button type="button" class="button button-primary" id="wpfb-modal-confirm"><?php esc_html_e( 'Move', 'wp-folder-boss' ); ?></button>
<button type="button" class="button" id="wpfb-modal-cancel"><?php esc_html_e( 'Cancel', 'wp-folder-boss' ); ?></button>
</div>
</div>
</div>
