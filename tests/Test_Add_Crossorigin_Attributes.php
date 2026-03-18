<?php
/**
 * Tests for csme_add_crossorigin_attributes().
 *
 * @package ClientSideMediaExperiments
 */

class Test_Add_Crossorigin_Attributes extends WP_UnitTestCase {

	/**
	 * Adds crossorigin="anonymous" to a cross-origin img tag.
	 */
	public function test_adds_crossorigin_to_cross_origin_img() {
		$html   = '<img src="https://external.example.com/photo.jpg" alt="photo">';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringContainsString( 'crossorigin="anonymous"', $result );
	}

	/**
	 * Does not add crossorigin to a same-origin img tag.
	 */
	public function test_does_not_add_crossorigin_to_same_origin_img() {
		$site_url = site_url();
		$html     = '<img src="' . $site_url . '/wp-content/uploads/photo.jpg" alt="photo">';
		$result   = csme_add_crossorigin_attributes( $html );

		$this->assertStringNotContainsString( 'crossorigin', $result );
	}

	/**
	 * Does not add crossorigin to a root-relative URL.
	 */
	public function test_does_not_add_crossorigin_to_relative_url() {
		$html   = '<img src="/wp-content/uploads/photo.jpg" alt="photo">';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringNotContainsString( 'crossorigin', $result );
	}

	/**
	 * Does not overwrite an existing crossorigin attribute.
	 */
	public function test_does_not_overwrite_existing_crossorigin() {
		$html   = '<img src="https://external.example.com/photo.jpg" crossorigin="use-credentials">';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringContainsString( 'crossorigin="use-credentials"', $result );
		$this->assertSame( 1, substr_count( $result, 'crossorigin' ) );
	}

	/**
	 * Adds crossorigin to a cross-origin script tag.
	 */
	public function test_adds_crossorigin_to_cross_origin_script() {
		$html   = '<script src="https://cdn.example.com/app.js"></script>';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringContainsString( 'crossorigin="anonymous"', $result );
	}

	/**
	 * Adds crossorigin to a cross-origin link tag.
	 */
	public function test_adds_crossorigin_to_cross_origin_link() {
		$html   = '<link rel="stylesheet" href="https://cdn.example.com/style.css">';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringContainsString( 'crossorigin="anonymous"', $result );
	}

	/**
	 * Adds crossorigin to a cross-origin video tag.
	 */
	public function test_adds_crossorigin_to_cross_origin_video() {
		$html   = '<video src="https://cdn.example.com/video.mp4"></video>';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringContainsString( 'crossorigin="anonymous"', $result );
	}

	/**
	 * Adds crossorigin to the parent audio/video element when source has a cross-origin URL.
	 */
	public function test_adds_crossorigin_to_parent_for_cross_origin_source() {
		$html   = '<video><source src="https://cdn.example.com/video.mp4"></video>';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringContainsString( '<video crossorigin="anonymous">', $result );
	}

	/**
	 * Does not modify unrelated tags.
	 */
	public function test_does_not_modify_unrelated_tags() {
		$html   = '<div class="container"><p>Hello</p></div>';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertSame( $html, $result );
	}

	/**
	 * Handles multiple elements, only modifying cross-origin ones.
	 */
	public function test_handles_multiple_elements() {
		$site_url = site_url();
		$html     = '<img src="https://external.example.com/a.jpg"><img src="' . $site_url . '/b.jpg"><script src="https://cdn.example.com/c.js"></script>';
		$result   = csme_add_crossorigin_attributes( $html );

		// First img and script should have crossorigin.
		$this->assertSame( 2, substr_count( $result, 'crossorigin="anonymous"' ) );
	}

	/**
	 * Returns input unchanged when no tags need modification.
	 */
	public function test_returns_unchanged_when_nothing_to_modify() {
		$html   = '<p>Just some text</p>';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertSame( $html, $result );
	}

	/**
	 * Does not add crossorigin to an img tag without a src attribute.
	 */
	public function test_does_not_add_crossorigin_when_no_src() {
		$html   = '<img alt="placeholder">';
		$result = csme_add_crossorigin_attributes( $html );

		$this->assertStringNotContainsString( 'crossorigin', $result );
	}
}
