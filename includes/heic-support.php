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
/*
 * Security note: This function validates by file extension only, without
 * inspecting file contents (magic bytes). This is acceptable because the
 * client-side JavaScript converts HEIC files to JPEG before upload, so the
 * server should normally only receive JPEG data. The HEIC/HEIF MIME types
 * are registered as a fallback to prevent upload rejection if conversion
 * is bypassed or the file metadata still references the original format.
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
 * Adds HEIC/HEIF to the client-side supported MIME types.
 *
 * Tells Gutenberg's upload-media store that HEIC/HEIF files should be
 * processed client-side rather than sent directly to the server.
 *
 * @param string[] $mime_types Array of MIME types.
 * @return string[] Modified MIME types.
 */
function csme_add_heic_client_side_mime_types( $mime_types ) {
	if ( ! csme_is_heic_enabled() ) {
		return $mime_types;
	}

	$mime_types[] = 'image/heic';
	$mime_types[] = 'image/heif';

	return $mime_types;
}
add_filter( 'client_side_supported_mime_types', 'csme_add_heic_client_side_mime_types' );

/**
 * Maps HEIC/HEIF to JPEG for image output format conversion.
 *
 * @param array $formats Source-to-output MIME type map.
 * @return array Modified formats.
 */
function csme_heic_output_format( $formats ) {
	if ( ! csme_is_heic_enabled() ) {
		return $formats;
	}

	$formats['image/heic'] = 'image/jpeg';
	$formats['image/heif'] = 'image/jpeg';

	return $formats;
}
add_filter( 'image_editor_output_format', 'csme_heic_output_format' );

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
		array( 'wp-data', 'wp-dom-ready', 'wp-notices', 'wp-upload-media', 'wp-api-fetch' ),
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

/**
 * Registers the REST route for replacing the original file on an attachment.
 *
 * After client-side HEIC→JPEG conversion, the JPEG is uploaded as the main
 * file. This endpoint replaces it with the original HEIC and cleans up the
 * orphaned JPEG so it doesn't linger on disk after attachment deletion.
 */
function csme_register_replace_original_route() {
	register_rest_route(
		'csme/v1',
		'/replace-original/(?P<id>[\d]+)',
		array(
			'methods'             => 'POST',
			'callback'            => 'csme_replace_original_file',
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', $request['id'] );
			},
			'args'                => array(
				'id' => array(
					'required'          => true,
					'validate_callback' => function ( $param ) {
						return is_numeric( $param ) && get_post( $param );
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'csme_register_replace_original_route' );

/**
 * Handles replacing the main attached file with an uploaded HEIC original.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error Response or error.
 */
function csme_replace_original_file( $request ) {
	$attachment_id = (int) $request['id'];
	$post          = get_post( $attachment_id );

	if ( ! $post || 'attachment' !== $post->post_type ) {
		return new WP_Error( 'invalid_attachment', 'Invalid attachment ID.', array( 'status' => 404 ) );
	}

	$files = $request->get_file_params();
	if ( empty( $files['file'] ) ) {
		return new WP_Error( 'no_file', 'No file uploaded.', array( 'status' => 400 ) );
	}

	$file = $files['file'];

	// Validate HEIC/HEIF MIME type.
	$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $extension, array( 'heic', 'heif' ), true ) ) {
		return new WP_Error( 'invalid_type', 'Only HEIC/HEIF files are accepted.', array( 'status' => 400 ) );
	}

	// Get the current main file path.
	$upload_dir   = wp_get_upload_dir();
	$old_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
	$old_path     = trailingslashit( $upload_dir['basedir'] ) . $old_relative;
	$target_dir   = dirname( $old_path );

	// Move the uploaded HEIC to the same directory as the attachment.
	$new_filename = wp_unique_filename( $target_dir, $file['name'] );
	$new_path     = trailingslashit( $target_dir ) . $new_filename;

	if ( ! move_uploaded_file( $file['tmp_name'], $new_path ) ) {
		return new WP_Error( 'move_failed', 'Failed to move uploaded file.', array( 'status' => 500 ) );
	}

	// Set correct permissions.
	$stat  = stat( $target_dir );
	$perms = $stat['mode'] & 0000666;
	chmod( $new_path, $perms );

	// Build the new relative path.
	$new_relative = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $new_path );

	// Delete the old JPEG main file.
	if ( file_exists( $old_path ) && $old_path !== $new_path ) {
		wp_delete_file( $old_path );
	}

	// Update the attached file meta to point to the HEIC.
	update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );

	// Update attachment metadata: set the new file path.
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( is_array( $metadata ) ) {
		$metadata['file'] = $new_relative;

		// Store the HEIC as the original_image if a scaled version exists.
		if ( ! empty( $metadata['sizes'] ) ) {
			$metadata['original_image'] = $new_filename;
		}

		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	// Update post MIME type.
	wp_update_post(
		array(
			'ID'             => $attachment_id,
			'post_mime_type' => 'image/' . $extension,
		)
	);

	return rest_ensure_response(
		array(
			'success' => true,
			'file'    => $new_relative,
		)
	);
}
