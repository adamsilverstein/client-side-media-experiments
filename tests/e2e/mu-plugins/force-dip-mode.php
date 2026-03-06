<?php
/**
 * Plugin Name: Force DIP Mode for E2E Tests
 * Description: Allows tests to simulate Document-Isolation-Policy mode via query parameter.
 */

/**
 * Disables COEP/COOP when the csme_force_dip query parameter is set.
 *
 * This simulates the behavior of Chrome 137+ where DIP takes over
 * and COEP/COOP headers should not be sent.
 *
 * @param bool $use_coep_coop Whether COEP/COOP should be used.
 * @return bool Filtered value.
 */
function csme_e2e_force_dip_mode( $use_coep_coop ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['csme_force_dip'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['csme_force_dip'] ) ) ) {
		return false;
	}
	return $use_coep_coop;
}
add_filter( 'csme_use_coep_coop', 'csme_e2e_force_dip_mode', 1 );
