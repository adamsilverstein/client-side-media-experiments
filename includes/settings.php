<?php
/**
 * Settings page for Client-Side Media Experiments.
 *
 * Adds a toggle under Settings > Media to enable or disable
 * client-side media processing support.
 *
 * @package ClientSideMediaExperiments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the default value for the csme_enabled setting based on browser type.
 *
 * Chromium 137+ uses Document-Isolation-Policy, so COEP/COOP headers should be
 * off by default. For all other browsers, the headers should be on by default.
 *
 * @return int 1 if COEP/COOP should be enabled by default, 0 otherwise.
 */
function csme_get_enabled_default() {
	$chrome_version = null;

	if ( function_exists( 'wp_get_chrome_major_version' ) ) {
		$chrome_version = wp_get_chrome_major_version();
	} elseif ( function_exists( 'gutenberg_get_chrome_major_version' ) ) {
		$chrome_version = gutenberg_get_chrome_major_version();
	}

	// Chromium 137+ uses Document-Isolation-Policy; COEP/COOP headers are not needed.
	$use_dip = null !== $chrome_version && $chrome_version >= 137;

	return $use_dip ? 0 : 1;
}

/**
 * Registers the plugin settings on the Media settings page.
 */
function csme_register_settings() {
	register_setting(
		'media',
		'csme_enabled',
		array(
			'type'              => 'integer',
			'default'           => csme_get_enabled_default(),
			'sanitize_callback' => 'csme_sanitize_enabled',
		)
	);

	add_settings_section(
		'csme_settings_section',
		__( 'Client-Side Media', 'client-side-media-experiments' ),
		'csme_settings_section_callback',
		'media'
	);

	add_settings_field(
		'csme_enabled_field',
		__( 'Enable', 'client-side-media-experiments' ),
		'csme_enabled_field_callback',
		'media',
		'csme_settings_section'
	);

	register_setting(
		'media',
		'csme_heic_enabled',
		array(
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'csme_sanitize_enabled',
		)
	);

	add_settings_field(
		'csme_heic_enabled_field',
		__( 'HEIC Support', 'client-side-media-experiments' ),
		'csme_heic_enabled_field_callback',
		'media',
		'csme_settings_section'
	);

	register_setting(
		'media',
		'csme_ultrahdr_enabled',
		array(
			'type'              => 'integer',
			'default'           => 1,
			'sanitize_callback' => 'csme_sanitize_enabled',
		)
	);

	add_settings_field(
		'csme_ultrahdr_enabled_field',
		__( 'UltraHDR Support', 'client-side-media-experiments' ),
		'csme_ultrahdr_enabled_field_callback',
		'media',
		'csme_settings_section'
	);
}
add_action( 'admin_init', 'csme_register_settings' );

/**
 * Sanitizes the enabled setting value.
 *
 * @param mixed $value The setting value.
 * @return int Sanitized value (1 or 0).
 */
function csme_sanitize_enabled( $value ) {
	return $value ? 1 : 0;
}

/**
 * Outputs the settings section description.
 */
function csme_settings_section_callback() {
	echo '<p>' . esc_html__( 'Configure client-side media processing via COEP/COOP cross-origin isolation headers.', 'client-side-media-experiments' ) . '</p>';
}

/**
 * Outputs the checkbox field for the enabled setting.
 */
function csme_enabled_field_callback() {
	$enabled = get_option( 'csme_enabled', csme_get_enabled_default() );
	?>
	<input type="hidden" name="csme_enabled" value="0" />
	<label for="csme_enabled">
		<input type="checkbox" id="csme_enabled" name="csme_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
		<?php esc_html_e( 'Enable client-side media processing support via COEP/COOP headers.', 'client-side-media-experiments' ); ?>
	</label>
	<?php
}

/**
 * Outputs the checkbox field for the HEIC enabled setting.
 */
function csme_heic_enabled_field_callback() {
	$enabled = get_option( 'csme_heic_enabled', 1 );
	?>
	<input type="hidden" name="csme_heic_enabled" value="0" />
	<label for="csme_heic_enabled">
		<input type="checkbox" id="csme_heic_enabled" name="csme_heic_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
		<?php esc_html_e( 'Enable HEIC/HEIF image upload support (converts to JPEG on the client side).', 'client-side-media-experiments' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'When enabled, HEIC images are converted to JPEG in the browser using the heic2any library (which uses libheif, LGPL-3.0 licensed), loaded from an external CDN at runtime.', 'client-side-media-experiments' ); ?>
	</p>
	<?php
}

/**
 * Outputs the checkbox field for the UltraHDR enabled setting.
 */
function csme_ultrahdr_enabled_field_callback() {
	$enabled = get_option( 'csme_ultrahdr_enabled', 1 );
	?>
	<input type="hidden" name="csme_ultrahdr_enabled" value="0" />
	<label for="csme_ultrahdr_enabled">
		<input type="checkbox" id="csme_ultrahdr_enabled" name="csme_ultrahdr_enabled" value="1" <?php checked( $enabled, 1 ); ?> />
		<?php esc_html_e( 'Detect UltraHDR images and preserve HDR gain map data in uploaded images and sub-sizes.', 'client-side-media-experiments' ); ?>
	</label>
	<p class="description">
		<?php esc_html_e( 'When enabled, UltraHDR images (JPEGs with embedded gain maps) are detected during upload. The original HDR file is preserved and sub-sizes are regenerated with gain map data so HDR is available at all image sizes.', 'client-side-media-experiments' ); ?>
	</p>
	<?php
}

/**
 * Disables COEP/COOP when the setting is off.
 *
 * @param bool $use_coep_coop Whether COEP/COOP should be used.
 * @return bool Filtered value.
 */
function csme_maybe_disable_coep_coop( $use_coep_coop ) {
	if ( ! get_option( 'csme_enabled', csme_get_enabled_default() ) ) {
		return false;
	}
	return $use_coep_coop;
}
add_filter( 'csme_use_coep_coop', 'csme_maybe_disable_coep_coop', 5 );
