<?php
/**
 * Tests for csme_should_use_coep_coop().
 *
 * @package ClientSideMediaExperiments
 */

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_Should_Use_Coep_Coop extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'csme_use_coep_coop' );
		parent::tear_down();
	}

	/**
	 * Returns true when no Chrome version function exists (Firefox/Safari).
	 */
	public function test_returns_true_when_no_chrome_version_function() {
		// Neither wp_get_chrome_major_version nor gutenberg_get_chrome_major_version
		// should exist in the test environment by default.
		$this->assertTrue( csme_should_use_coep_coop() );
	}

	/**
	 * Returns false when Chrome version is 137+.
	 */
	public function test_returns_false_when_chrome_137_or_higher() {
		// Create a stub that returns 140 (>= 137, so DIP is used).
		if ( ! function_exists( 'wp_get_chrome_major_version' ) ) {
			function wp_get_chrome_major_version() {
				return 140;
			}
		}

		$this->assertFalse( csme_should_use_coep_coop() );
	}

	/**
	 * Filter can override the return value to false.
	 */
	public function test_filter_can_override_to_false() {
		add_filter( 'csme_use_coep_coop', '__return_false' );

		$this->assertFalse( csme_should_use_coep_coop() );
	}

	/**
	 * Filter can override the return value to true.
	 */
	public function test_filter_can_override_to_true() {
		add_filter( 'csme_use_coep_coop', '__return_true' );

		$this->assertTrue( csme_should_use_coep_coop() );
	}
}
