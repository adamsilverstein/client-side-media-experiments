<?php
/**
 * Tests for csme_enqueue_scripts().
 *
 * @package ClientSideMediaExperiments
 */

class Test_Enqueue_Scripts extends WP_UnitTestCase {

	/**
	 * Tear down after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'csme_use_coep_coop' );
		wp_dequeue_script( 'csme-cross-origin-isolation-coep' );
		wp_deregister_script( 'csme-cross-origin-isolation-coep' );
		parent::tear_down();
	}

	/**
	 * Script is not enqueued when csme_should_use_coep_coop() returns false.
	 */
	public function test_script_not_enqueued_when_coep_coop_disabled() {
		add_filter( 'csme_use_coep_coop', '__return_false' );

		csme_enqueue_scripts( 'post.php' );

		$this->assertFalse( wp_script_is( 'csme-cross-origin-isolation-coep', 'enqueued' ) );
	}

	/**
	 * Script is not enqueued on irrelevant admin pages.
	 */
	public function test_script_not_enqueued_on_irrelevant_pages() {
		add_filter( 'csme_use_coep_coop', '__return_true' );

		csme_enqueue_scripts( 'options-general.php' );

		$this->assertFalse( wp_script_is( 'csme-cross-origin-isolation-coep', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on post.php.
	 */
	public function test_script_enqueued_on_post_php() {
		add_filter( 'csme_use_coep_coop', '__return_true' );

		csme_enqueue_scripts( 'post.php' );

		$this->assertTrue( wp_script_is( 'csme-cross-origin-isolation-coep', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on post-new.php.
	 */
	public function test_script_enqueued_on_post_new_php() {
		add_filter( 'csme_use_coep_coop', '__return_true' );

		csme_enqueue_scripts( 'post-new.php' );

		$this->assertTrue( wp_script_is( 'csme-cross-origin-isolation-coep', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on site-editor.php.
	 */
	public function test_script_enqueued_on_site_editor_php() {
		add_filter( 'csme_use_coep_coop', '__return_true' );

		csme_enqueue_scripts( 'site-editor.php' );

		$this->assertTrue( wp_script_is( 'csme-cross-origin-isolation-coep', 'enqueued' ) );
	}

	/**
	 * Script is enqueued on widgets.php.
	 */
	public function test_script_enqueued_on_widgets_php() {
		add_filter( 'csme_use_coep_coop', '__return_true' );

		csme_enqueue_scripts( 'widgets.php' );

		$this->assertTrue( wp_script_is( 'csme-cross-origin-isolation-coep', 'enqueued' ) );
	}
}
