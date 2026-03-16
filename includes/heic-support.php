<?php
/**
 * HEIC/HEIF upload support.
 *
 * Adds client-side HEIC to JPEG conversion for the block editor,
 * using a dynamically loaded library from an external CDN.
 *
 * @package ClientSideMediaExperiments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether HEIC support is enabled.
 *
 * @return bool
 */
function csme_is_heic_enabled() {
	return (bool) get_option( 'csme_heic_enabled', 1 );
}

/**
 * Adds HEIC and HEIF MIME types to the allowed upload types.
 *
 * @param array $mime_types Existing MIME types.
 * @return array Modified MIME types.
 */
function csme_add_heic_mime_types( $mime_types ) {
	if ( ! csme_is_heic_enabled() ) {
		return $mime_types;
	}

	$mime_types['heic'] = 'image/heic';
	$mime_types['heif'] = 'image/heif';

	return $mime_types;
}
add_filter( 'upload_mimes', 'csme_add_heic_mime_types' );

/**
 * Corrects file type detection for HEIC/HEIF files.
 *
 * WordPress may not recognize HEIC/HEIF files by their extension.
 * This filter ensures proper type detection.
 *
 * @param array  $data     File data array containing ext, type, and proper_filename.
 * @param string $file     Full path to the file.
 * @param string $filename The file name.
 * @param array  $mimes    Allowed MIME types keyed by extension.
 * @param string $real_mime Real MIME type of the file.
 * @return array Modified file data.
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
function csme_heic_check_filetype( $data, $file, $filename, $mimes, $real_mime ) {
	if ( ! csme_is_heic_enabled() ) {
		return $data;
	}

	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
		return $data;
	}

	$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

	if ( in_array( $extension, array( 'heic', 'heif' ), true ) ) {
		$data['ext']             = $extension;
		$data['type']            = 'image/' . $extension;
		$data['proper_filename'] = false;
	}

	return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'csme_heic_check_filetype', 10, 5 );

/**
 * Enqueues the HEIC support JavaScript on block editor pages.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function csme_enqueue_heic_scripts( $hook_suffix ) {
	if ( ! csme_is_heic_enabled() ) {
		return;
	}

	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php' ), true ) ) {
		return;
	}

	/**
	 * Filters the URL of the HEIC conversion library loaded from CDN.
	 *
	 * The default library is heic2any, which uses libheif (LGPL-3.0)
	 * for HEIC decoding. It is loaded at runtime from an external CDN
	 * as a separate work.
	 *
	 * @since 0.2.0
	 *
	 * @param string $url CDN URL for the heic2any library.
	 */
	$cdn_url = apply_filters(
		'csme_heic_library_url',
		'https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js'
	);

	wp_enqueue_script(
		'csme-heic-support',
		CSME_PLUGIN_URL . 'js/heic-support.js',
		array( 'wp-data', 'wp-dom-ready', 'wp-block-editor', 'wp-notices' ),
		CSME_VERSION,
		true
	);

	/**
	 * Filters the SRI integrity hash for the HEIC conversion library.
	 *
	 * @since 0.2.0
	 *
	 * @param string $integrity SRI hash for the heic2any library.
	 */
	$cdn_integrity = apply_filters(
		'csme_heic_library_integrity',
		'sha384-OTofQ0MEeiSgh62havBcemCIK0gqj809wX6UA0uPISNMRnR6NZyCdGzX3SbLrgwL'
	);

	/**
	 * Filters the JPEG quality used when converting HEIC images.
	 *
	 * @since 0.2.0
	 *
	 * @param float $quality JPEG quality between 0 and 1. Default 0.92.
	 */
	$jpeg_quality = apply_filters( 'csme_heic_jpeg_quality', 0.92 );
	$jpeg_quality = max( 0.0, min( 1.0, (float) $jpeg_quality ) );

	wp_localize_script(
		'csme-heic-support',
		'csmeHeicSupport',
		array(
			'cdnUrl'       => esc_url( $cdn_url ),
			'cdnIntegrity' => sanitize_text_field( $cdn_integrity ),
			'jpegQuality'  => $jpeg_quality,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'csme_enqueue_heic_scripts' );
