<?php
/**
 * Tests for HEIC support functions.
 *
 * @package ClientSideMediaExperiments
 */

class Test_Heic_Support extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		delete_option( 'csme_heic_enabled' );
		remove_all_filters( 'upload_mimes' );
		remove_all_filters( 'wp_check_filetype_and_ext' );
		remove_all_filters( 'csme_heic_library_url' );
		wp_dequeue_script( 'csme-heic-support' );
		wp_deregister_script( 'csme-heic-support' );
		parent::tear_down();
	}

	/**
	 * HEIC support is enabled by default.
	 */
	public function test_heic_enabled_by_default() {
		$this->assertTrue( csme_is_heic_enabled() );
	}

	/**
	 * HEIC support can be enabled via option.
	 */
	public function test_heic_enabled_via_option() {
		update_option( 'csme_heic_enabled', 1 );

		$this->assertTrue( csme_is_heic_enabled() );
	}

	/**
	 * HEIC MIME types are not added when disabled.
	 */
	public function test_mime_types_not_added_when_disabled() {
		update_option( 'csme_heic_enabled', 0 );

		$mime_types = csme_add_heic_mime_types( array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );

		$this->assertArrayNotHasKey( 'heic', $mime_types );
		$this->assertArrayNotHasKey( 'heif', $mime_types );
	}

	/**
	 * HEIC MIME types are added when enabled.
	 */
	public function test_mime_types_added_when_enabled() {
		update_option( 'csme_heic_enabled', 1 );

		$mime_types = csme_add_heic_mime_types( array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );

		$this->assertArrayHasKey( 'heic', $mime_types );
		$this->assertArrayHasKey( 'heif', $mime_types );
		$this->assertSame( 'image/heic', $mime_types['heic'] );
		$this->assertSame( 'image/heif', $mime_types['heif'] );
	}

	/**
	 * Existing MIME types are preserved when HEIC types are added.
	 */
	public function test_existing_mime_types_preserved() {
		update_option( 'csme_heic_enabled', 1 );

		$mime_types = csme_add_heic_mime_types( array( 'jpg|jpeg|jpe' => 'image/jpeg' ) );

		$this->assertArrayHasKey( 'jpg|jpeg|jpe', $mime_types );
	}

	/**
	 * File type detection returns early when HEIC is disabled.
	 */
	public function test_filetype_check_returns_early_when_disabled() {
		update_option( 'csme_heic_enabled', 0 );

		$data = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$result = csme_heic_check_filetype( $data, '/tmp/test.heic', 'test.heic', array(), '' );

		$this->assertFalse( $result['ext'] );
		$this->assertFalse( $result['type'] );
	}

	/**
	 * File type detection does not override already-detected types.
	 */
	public function test_filetype_check_does_not_override_existing() {
		update_option( 'csme_heic_enabled', 1 );

		$data = array(
			'ext'             => 'jpg',
			'type'            => 'image/jpeg',
			'proper_filename' => false,
		);

		$result = csme_heic_check_filetype( $data, '/tmp/test.jpg', 'test.jpg', array(), '' );

		$this->assertSame( 'jpg', $result['ext'] );
		$this->assertSame( 'image/jpeg', $result['type'] );
	}

	/**
	 * File type detection correctly identifies HEIC files.
	 */
	public function test_filetype_check_detects_heic() {
		update_option( 'csme_heic_enabled', 1 );

		$data = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$result = csme_heic_check_filetype( $data, '/tmp/test.heic', 'test.heic', array(), '' );

		$this->assertSame( 'heic', $result['ext'] );
		$this->assertSame( 'image/heic', $result['type'] );
	}

	/**
	 * File type detection correctly identifies HEIF files.
	 */
	public function test_filetype_check_detects_heif() {
		update_option( 'csme_heic_enabled', 1 );

		$data = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$result = csme_heic_check_filetype( $data, '/tmp/test.heif', 'test.heif', array(), '' );

		$this->assertSame( 'heif', $result['ext'] );
		$this->assertSame( 'image/heif', $result['type'] );
	}

	/**
	 * Script is not enqueued when HEIC is disabled.
	 */
	public function test_script_not_enqueued_when_disabled() {
		update_option( 'csme_heic_enabled', 0 );

		csme_enqueue_heic_scripts( 'post.php' );

		$this->assertFalse( wp_script_is( 'csme-heic-support', 'enqueued' ) );
	}

	/**
	 * Script is not enqueued on irrelevant admin pages.
	 */
	public function test_script_not_enqueued_on_irrelevant_pages() {
		update_option( 'csme_heic_enabled', 1 );

		csme_enqueue_heic_scripts( 'options-general.php' );

		$this->assertFalse( wp_script_is( 'csme-heic-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on post.php when HEIC is enabled.
	 */
	public function test_script_enqueued_on_post_php() {
		update_option( 'csme_heic_enabled', 1 );

		csme_enqueue_heic_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-heic-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on post-new.php when HEIC is enabled.
	 */
	public function test_script_enqueued_on_post_new_php() {
		update_option( 'csme_heic_enabled', 1 );

		csme_enqueue_heic_scripts( 'post-new.php' );

		$this->assertTrue( wp_script_is( 'csme-heic-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on site-editor.php when HEIC is enabled.
	 */
	public function test_script_enqueued_on_site_editor_php() {
		update_option( 'csme_heic_enabled', 1 );

		csme_enqueue_heic_scripts( 'site-editor.php' );

		$this->assertTrue( wp_script_is( 'csme-heic-support', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on widgets.php when HEIC is enabled.
	 */
	public function test_script_enqueued_on_widgets_php() {
		update_option( 'csme_heic_enabled', 1 );

		csme_enqueue_heic_scripts( 'widgets.php' );

		$this->assertTrue( wp_script_is( 'csme-heic-support', 'enqueued' ) );
	}

	/**
	 * CDN URL can be filtered and is used in localized script data.
	 */
	public function test_heic_library_url_filterable() {
		update_option( 'csme_heic_enabled', 1 );

		add_filter(
			'csme_heic_library_url',
			function () {
				return 'https://example.com/heic2any.min.js';
			}
		);

		csme_enqueue_heic_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-heic-support', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'csme-heic-support', 'data' );
		$this->assertIsString( $data );
		$this->assertStringContainsString( 'https://example.com/heic2any.min.js', $data );
	}

	/**
	 * File type detection ignores non-HEIC files.
	 */
	public function test_filetype_check_ignores_non_heic() {
		update_option( 'csme_heic_enabled', 1 );

		$data = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);

		$result = csme_heic_check_filetype( $data, '/tmp/test.png', 'test.png', array(), '' );

		$this->assertFalse( $result['ext'] );
		$this->assertFalse( $result['type'] );
	}

	/**
	 * The csme_heic_enabled setting is registered correctly.
	 */
	public function test_heic_setting_registered() {
		do_action( 'admin_init' );

		$registered = get_registered_settings();
		$this->assertArrayHasKey( 'csme_heic_enabled', $registered );
		$this->assertSame( 'integer', $registered['csme_heic_enabled']['type'] );
		$this->assertSame( 1, $registered['csme_heic_enabled']['default'] );
	}

	/**
	 * The sanitize callback normalizes values to 0 or 1.
	 */
	public function test_sanitize_enabled_callback() {
		$this->assertSame( 1, csme_sanitize_enabled( 1 ) );
		$this->assertSame( 1, csme_sanitize_enabled( '1' ) );
		$this->assertSame( 1, csme_sanitize_enabled( 'yes' ) );
		$this->assertSame( 0, csme_sanitize_enabled( 0 ) );
		$this->assertSame( 0, csme_sanitize_enabled( '' ) );
		$this->assertSame( 0, csme_sanitize_enabled( null ) );
	}

	/**
	 * The HEIC enabled field callback renders a checked checkbox.
	 */
	public function test_heic_enabled_field_callback_checked() {
		update_option( 'csme_heic_enabled', 1 );

		ob_start();
		csme_heic_enabled_field_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="csme_heic_enabled"', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'checked', $output );
	}

	/**
	 * The HEIC enabled field callback renders unchecked when disabled.
	 */
	public function test_heic_enabled_field_callback_unchecked() {
		update_option( 'csme_heic_enabled', 0 );

		ob_start();
		csme_heic_enabled_field_callback();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="csme_heic_enabled"', $output );
		$this->assertStringNotContainsString( "checked='checked'", $output );
	}

	/**
	 * The upload_mimes filter includes HEIC types when hooked.
	 */
	public function test_upload_mimes_filter_integration() {
		update_option( 'csme_heic_enabled', 1 );

		add_filter( 'upload_mimes', 'csme_add_heic_mime_types' );

		$defaults = array( 'jpg|jpeg|jpe' => 'image/jpeg' );
		$mimes    = apply_filters( 'upload_mimes', $defaults );

		$this->assertArrayHasKey( 'heic', $mimes );
		$this->assertArrayHasKey( 'heif', $mimes );
		$this->assertArrayHasKey( 'jpg|jpeg|jpe', $mimes );
	}

	/**
	 * The csme_heic_library_url filter sanitizes javascript: URLs.
	 */
	public function test_malicious_library_url_sanitized() {
		update_option( 'csme_heic_enabled', 1 );

		add_filter(
			'csme_heic_library_url',
			function () {
				return 'javascript:alert(document.cookie)';
			}
		);

		csme_enqueue_heic_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-heic-support', 'enqueued' ) );

		$data = wp_scripts()->get_data( 'csme-heic-support', 'data' );
		$this->assertIsString( $data );
		$this->assertStringNotContainsString( 'javascript:', $data );
	}
}
