<?php
/**
 * Tests for UltraHDR support functions.
 *
 * @package ClientSideMediaExperiments
 */

class Test_UltraHDR_Support extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_option( 'csme_ultrahdr_enabled' );
		remove_all_filters( 'csme_ultrahdr_library_url' );
		wp_dequeue_script( 'csme-ultrahdr-support' );
		wp_deregister_script( 'csme-ultrahdr-support' );
		parent::tear_down();
	}

	/**
	 * UltraHDR support is enabled by default.
	 */
	public function test_ultrahdr_enabled_by_default() {
		$this->assertTrue( csme_is_ultrahdr_enabled() );
	}

	/**
	 * UltraHDR support can be enabled via option.
	 */
	public function test_ultrahdr_enabled_via_option() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		$this->assertTrue( csme_is_ultrahdr_enabled() );
	}

	/**
	 * UltraHDR support can be disabled via option.
	 */
	public function test_ultrahdr_disabled_via_option() {
		update_option( 'csme_ultrahdr_enabled', 0 );

		$this->assertFalse( csme_is_ultrahdr_enabled() );
	}

	/**
	 * Script is not enqueued when UltraHDR is disabled.
	 */
	public function test_script_not_enqueued_when_disabled() {
		update_option( 'csme_ultrahdr_enabled', 0 );

		csme_enqueue_ultrahdr_scripts( 'post.php' );

		$this->assertFalse( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );
	}

	/**
	 * Script is not enqueued on irrelevant admin pages.
	 */
	public function test_script_not_enqueued_on_irrelevant_pages() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		csme_enqueue_ultrahdr_scripts( 'options-general.php' );

		$this->assertFalse( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on post.php when UltraHDR is enabled.
	 */
	public function test_script_enqueued_on_post_php() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		csme_enqueue_ultrahdr_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on post-new.php when UltraHDR is enabled.
	 */
	public function test_script_enqueued_on_post_new_php() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		csme_enqueue_ultrahdr_scripts( 'post-new.php' );

		$this->assertTrue( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on site-editor.php when UltraHDR is enabled.
	 */
	public function test_script_enqueued_on_site_editor_php() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		csme_enqueue_ultrahdr_scripts( 'site-editor.php' );

		$this->assertTrue( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on widgets.php when UltraHDR is enabled.
	 */
	public function test_script_enqueued_on_widgets_php() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		csme_enqueue_ultrahdr_scripts( 'widgets.php' );

		$this->assertTrue( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );
	}

	/**
	 * Library URL can be filtered and is used in localized script data.
	 */
	public function test_ultrahdr_library_url_filterable() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		add_filter(
			'csme_ultrahdr_library_url',
			function () {
				return 'https://example.com/libultrahdr.js';
			}
		);

		csme_enqueue_ultrahdr_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'csme-ultrahdr-support', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'example.com', $data );
		$this->assertStringContainsString( 'libultrahdr.js', $data );
	}

	/**
	 * The csme_ultrahdr_library_url filter sanitizes javascript: URLs.
	 */
	public function test_malicious_library_url_sanitized() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		add_filter(
			'csme_ultrahdr_library_url',
			function () {
				return 'javascript:alert(document.cookie)';
			}
		);

		csme_enqueue_ultrahdr_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-ultrahdr-support', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'csme-ultrahdr-support', 'data' );
		$this->assertIsString( $data );
		$this->assertStringNotContainsString( 'javascript:', $data );
	}

	/**
	 * The csme_ultrahdr_enabled setting is registered correctly.
	 */
	public function test_ultrahdr_setting_registered() {
		csme_register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( 'csme_ultrahdr_enabled', $registered );
		$this->assertSame( 'integer', $registered['csme_ultrahdr_enabled']['type'] );
		$this->assertSame( 1, $registered['csme_ultrahdr_enabled']['default'] );
	}

	/**
	 * The UltraHDR enabled field callback renders a checked checkbox.
	 */
	public function test_ultrahdr_enabled_field_callback_checked() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		ob_start();
		csme_ultrahdr_enabled_field_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="csme_ultrahdr_enabled"', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'checked', $output );
	}

	/**
	 * The UltraHDR enabled field callback renders unchecked when disabled.
	 */
	public function test_ultrahdr_enabled_field_callback_unchecked() {
		update_option( 'csme_ultrahdr_enabled', 0 );

		ob_start();
		csme_ultrahdr_enabled_field_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="csme_ultrahdr_enabled"', $output );
		// Match any checked attribute format: checked, checked='checked', checked="checked".
		$this->assertDoesNotMatchRegularExpression( '/\schecked[\s=>\'"]/', $output );
	}

	/**
	 * The UltraHDR enabled field callback contains description text.
	 */
	public function test_ultrahdr_enabled_field_has_description() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		ob_start();
		csme_ultrahdr_enabled_field_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'UltraHDR', $output );
		$this->assertStringContainsString( 'gain map', $output );
	}

	/**
	 * Script localized data contains the library URL.
	 */
	public function test_script_localized_data_contains_library_url() {
		update_option( 'csme_ultrahdr_enabled', 1 );

		csme_enqueue_ultrahdr_scripts( 'post.php' );

		$data = wp_scripts()->get_data( 'csme-ultrahdr-support', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'libraryUrl', $data );
		$this->assertStringContainsString( 'csmeUltraHDRSupport', $data );
	}
}
