<?php
/**
 * UltraHDR image upload support.
 *
 * Detects UltraHDR images (JPEGs with embedded gain maps) during upload
 * and preserves HDR data in both the original file and generated sub-sizes
 * using a dynamically loaded WASM library.
 *
 * @package ClientSideMediaExperiments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether UltraHDR support is enabled.
 *
 * @return bool
 */
function csme_is_ultrahdr_enabled() {
	return (bool) get_option( 'csme_ultrahdr_enabled', 1 );
}

/**
 * Enqueues the UltraHDR support JavaScript on block editor pages.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function csme_enqueue_ultrahdr_scripts( $hook_suffix ) {
	if ( ! csme_is_ultrahdr_enabled() ) {
		return;
	}

	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php' ), true ) ) {
		return;
	}

	/**
	 * Filters the URL of the UltraHDR WASM library loaded from CDN.
	 *
	 * The default library is libultrahdr-wasm, a WASM port of Google's
	 * libultrahdr reference codec. It is loaded at runtime from an
	 * external CDN as a separate work.
	 *
	 * @since 0.3.0
	 *
	 * @param string $url CDN URL for the libultrahdr-wasm library.
	 */
	$library_url = apply_filters(
		'csme_ultrahdr_library_url',
		'https://cdn.jsdelivr.net/npm/@aspect-build/aspect-media-ultrahdr-wasm@0.1.0/dist/libultrahdr.js'
	);

	wp_enqueue_script(
		'csme-ultrahdr-support',
		CSME_PLUGIN_URL . 'js/ultrahdr-support.js',
		array( 'wp-data', 'wp-dom-ready', 'wp-notices', 'wp-upload-media', 'wp-api-fetch' ),
		CSME_VERSION,
		true
	);

	wp_localize_script(
		'csme-ultrahdr-support',
		'csmeUltraHDRSupport',
		array(
			'libraryUrl' => esc_url_raw( $library_url ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'csme_enqueue_ultrahdr_scripts' );
