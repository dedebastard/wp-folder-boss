<?php
/**
 * Settings page template.
 *
 * @package WPFolderBoss
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings    = \WPFolderBoss\Helpers\Utils::get_settings();
$screens     = $settings['enabled_screens'] ?? array();
$post_types  = get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' );

$imported = isset( $_GET['wpfb_imported'] ) ? absint( wp_unslash( $_GET['wpfb_imported'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
$errors   = isset( $_GET['wpfb_errors'] ) ? absint( wp_unslash( $_GET['wpfb_errors'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

$import_service = new \WPFolderBoss\Services\ImportService();
$sources        = $import_service->get_available_sources();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'WP Folder Boss — Settings', 'wp-folder-boss' ); ?></h1>

	<?php if ( null !== $imported ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %d: number of imported folders */
					esc_html__( 'Import complete. %d folder(s) imported.', 'wp-folder-boss' ),
					(int) $imported
				);
				if ( $errors ) {
					printf(
						/* translators: %d: number of errors */
						' ' . esc_html__( '%d error(s) occurred.', 'wp-folder-boss' ),
						(int) $errors
					);
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'wpfb_settings_group' ); ?>

		<h2><?php esc_html_e( 'Enable Folders For', 'wp-folder-boss' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Built-in Screens', 'wp-folder-boss' ); ?></th>
					<td>
						<?php
						$builtin = array(
							'media'   => __( 'Media Library', 'wp-folder-boss' ),
							'post'    => __( 'Posts', 'wp-folder-boss' ),
							'page'    => __( 'Pages', 'wp-folder-boss' ),
							'users'   => __( 'Users', 'wp-folder-boss' ),
							'plugins' => __( 'Plugins', 'wp-folder-boss' ),
						);

						foreach ( $builtin as $key => $label ) :
							$checked = ! empty( $screens[ $key ] );
							?>
							<label style="display:block;margin-bottom:6px">
								<input type="checkbox"
									name="wpfb_settings[enabled_screens][<?php echo esc_attr( $key ); ?>]"
									value="1"
									<?php checked( $checked ); ?>
								/>
								<?php echo esc_html( $label ); ?>
							</label>
							<?php
						endforeach;
						?>
					</td>
				</tr>

				<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'WooCommerce', 'wp-folder-boss' ); ?></th>
					<td>
						<?php
						$woo = array(
							'product'      => __( 'Products', 'wp-folder-boss' ),
							'shop_order'   => __( 'Orders', 'wp-folder-boss' ),
							'shop_coupon'  => __( 'Coupons', 'wp-folder-boss' ),
						);

						foreach ( $woo as $key => $label ) :
							$checked = ! empty( $screens[ $key ] );
							?>
							<label style="display:block;margin-bottom:6px">
								<input type="checkbox"
									name="wpfb_settings[enabled_screens][<?php echo esc_attr( $key ); ?>]"
									value="1"
									<?php checked( $checked ); ?>
								/>
								<?php echo esc_html( $label ); ?>
							</label>
							<?php
						endforeach;
						?>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( ! empty( $post_types ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Custom Post Types', 'wp-folder-boss' ); ?></th>
					<td>
						<?php
						$enabled_cpts = $settings['enabled_post_types'] ?? array();
						foreach ( $post_types as $pt ) :
							$is_enabled = in_array( $pt->name, $enabled_cpts, true );
							?>
							<label style="display:block;margin-bottom:6px">
								<input type="checkbox"
									name="wpfb_settings[enabled_post_types][]"
									value="<?php echo esc_attr( $pt->name ); ?>"
									<?php checked( $is_enabled ); ?>
								/>
								<?php echo esc_html( $pt->label ); ?> <code><?php echo esc_html( $pt->name ); ?></code>
							</label>
							<?php
						endforeach;
						?>
					</td>
				</tr>
				<?php endif; ?>

				<tr>
					<th scope="row">
						<label for="wpfb_default_folder"><?php esc_html_e( 'Default Folder for New Uploads', 'wp-folder-boss' ); ?></label>
					</th>
					<td>
						<?php
						$folder_service = new \WPFolderBoss\Services\FolderService();
						$media_folders  = $folder_service->get_folders( 'media' );
						$default_folder = absint( $settings['default_folder'] ?? 0 );
						?>
						<select name="wpfb_settings[default_folder]" id="wpfb_default_folder">
							<option value="0"><?php esc_html_e( '— None —', 'wp-folder-boss' ); ?></option>
							<?php foreach ( $media_folders as $folder ) : ?>
								<option value="<?php echo esc_attr( (string) $folder->id ); ?>" <?php selected( $default_folder, $folder->id ); ?>>
									<?php echo esc_html( $folder->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Automatically assign new uploads to this folder.', 'wp-folder-boss' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>

	<?php if ( ! empty( $sources ) ) : ?>
	<hr />
	<h2><?php esc_html_e( 'Import Folders', 'wp-folder-boss' ); ?></h2>
	<p><?php esc_html_e( 'Import your existing folder structure from another plugin.', 'wp-folder-boss' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpfb_import" />
		<?php wp_nonce_field( 'wpfb_import', 'wpfb_import_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="wpfb_import_source"><?php esc_html_e( 'Import From', 'wp-folder-boss' ); ?></label>
					</th>
					<td>
						<select name="wpfb_import_source" id="wpfb_import_source">
							<?php foreach ( $sources as $source ) : ?>
								<option value="<?php echo esc_attr( $source ); ?>">
									<?php echo esc_html( ucwords( str_replace( '-', ' ', $source ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Import', 'wp-folder-boss' ) ); ?>
	</form>
	<?php endif; ?>
</div>
