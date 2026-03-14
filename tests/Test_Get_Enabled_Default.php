<?php
/**
 * Tests for csme_get_enabled_default().
 *
 * @package ClientSideMediaExperiments
 */

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_Get_Enabled_Default extends WP_UnitTestCase {

	/**
	 * Returns 1 when no Chrome version function exists (Firefox/Safari).
	 */
	public function test_returns_1_when_no_chrome_version_function() {
		$this->assertSame( 1, csme_get_enabled_default() );
	}

	/**
	 * Returns 0 when Chrome version is 137+.
	 */
	public function test_returns_0_when_chrome_137_or_higher() {
		if ( ! function_exists( 'wp_get_chrome_major_version' ) ) {
			function wp_get_chrome_major_version() {
				return 140;
			}
		}

		$this->assertSame( 0, csme_get_enabled_default() );
	}

	/**
	 * Returns 1 when Chrome version is below 137.
	 */
	public function test_returns_1_when_chrome_below_137() {
		if ( ! function_exists( 'wp_get_chrome_major_version' ) ) {
			function wp_get_chrome_major_version() {
				return 130;
			}
		}

		$this->assertSame( 1, csme_get_enabled_default() );
	}

	/**
	 * Returns 0 when Chrome version is exactly 137.
	 */
	public function test_returns_0_when_chrome_exactly_137() {
		if ( ! function_exists( 'wp_get_chrome_major_version' ) ) {
			function wp_get_chrome_major_version() {
				return 137;
			}
		}

		$this->assertSame( 0, csme_get_enabled_default() );
	}
}
