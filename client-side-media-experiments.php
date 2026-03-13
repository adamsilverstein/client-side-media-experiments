<?php
/**
 * Plugin Name: Client-Side Media Experiments
 * Plugin URI:  https://github.com/adamsilverstein/client-side-media-experiments
 * Description: Enables client-side media processing on Firefox and Safari via COEP/COOP cross-origin isolation headers, and adds HEIC/HEIF upload support with client-side conversion.
 * Version:     0.1.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Author:      Adam Silverstein
 * Author URI:  https://developer.wordpress.org
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: client-side-media-experiments
 *
 * @package ClientSideMediaExperiments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bail if client-side media processing is not enabled.
if ( function_exists( 'wp_is_client_side_media_processing_enabled' ) ) {
	if ( ! wp_is_client_side_media_processing_enabled() ) {
		return;
	}
} elseif ( function_exists( 'gutenberg_is_client_side_media_processing_enabled' ) ) {
	if ( ! gutenberg_is_client_side_media_processing_enabled() ) {
		return;
	}
} else {
	// Neither core 7.0+ nor Gutenberg with the feature — nothing to do.
	return;
}

define( 'CSME_VERSION', '0.1.0' );
define( 'CSME_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CSME_PLUGIN_DIR . 'includes/settings.php';
require_once CSME_PLUGIN_DIR . 'includes/cross-origin-isolation.php';
require_once CSME_PLUGIN_DIR . 'includes/heic-support.php';
