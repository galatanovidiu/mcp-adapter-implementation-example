<?php
/**
 * Unit tests for ListBlockTypes ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Blocks
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Blocks;

use OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ListBlockTypes ability functionality.
 */
final class ListBlockTypesTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		// Ability registration is handled by the base TestCase via BootstrapAbilities::init()
	}

	/**
	 * Test ability registration.
	 */
	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'wpmcp-example/list-block-types' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$has_permission = ListBlockTypes::check_permission( array() );

		$this->assertTrue( $has_permission, 'Author should have permission to list block types' );
	}

	/**
	 * Test permission checking with subscriber.
	 */
	public function test_permission_check_with_subscriber(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$has_permission = ListBlockTypes::check_permission( array() );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to list block types' );
	}

	/**
	 * Test basic block types listing.
	 */
	public function test_basic_block_types_listing(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$result = ListBlockTypes::execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );

		$blocks = $result['blocks'];
		$this->assertIsArray( $blocks );
		$this->assertNotEmpty( $blocks, 'Should return available block types' );

		// Verify block structure.
		$first_block = $blocks[0];
		$this->assertArrayHasKey( 'name', $first_block );
		$this->assertArrayHasKey( 'title', $first_block );
		$this->assertArrayHasKey( 'description', $first_block );
		$this->assertArrayHasKey( 'category', $first_block );
		$this->assertArrayHasKey( 'keywords', $first_block );
		$this->assertArrayHasKey( 'attributes', $first_block );
		$this->assertArrayHasKey( 'supports', $first_block );

		$this->assertIsString( $first_block['name'] );
		$this->assertIsString( $first_block['title'] );
		$this->assertIsArray( $first_block['keywords'] );
	}

	/**
	 * Test block types listing with category filter.
	 */
	public function test_block_types_listing_with_category_filter(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// First, get all blocks to find available categories.
		$all_result = ListBlockTypes::execute( array() );
		$all_blocks = $all_result['blocks'];
		$this->assertNotEmpty( $all_blocks );

		// Find a category that exists.
		$test_category = '';
		foreach ( $all_blocks as $block ) {
			if ( ! empty( $block['category'] ) ) {
				$test_category = $block['category'];
				break;
			}
		}

		if ( empty( $test_category ) ) {
			$this->markTestSkipped( 'No block categories available for testing' );
		}

		// Test filtering by category.
		$input  = array( 'category' => $test_category );
		$result = ListBlockTypes::execute( $input );

		$this->assertIsArray( $result );
		$blocks = $result['blocks'];
		$this->assertNotEmpty( $blocks, 'Should find blocks in the specified category' );

		// Verify all returned blocks are in the requested category.
		foreach ( $blocks as $block ) {
			$this->assertEquals( $test_category, $block['category'], 'All blocks should be in the requested category' );
		}
	}

	/**
	 * Test block types listing with search filter.
	 */
	public function test_block_types_listing_with_search_filter(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// Search for 'paragraph' which should exist in core blocks.
		$input  = array( 'search' => 'paragraph' );
		$result = ListBlockTypes::execute( $input );

		$this->assertIsArray( $result );
		$blocks = $result['blocks'];

		if ( empty( $blocks ) ) {
			return;
		}

		// Verify search worked - at least one block should contain 'paragraph'.
		$found_paragraph = false;
		foreach ( $blocks as $block ) {
			$searchable = strtolower( $block['name'] . ' ' . $block['title'] . ' ' . $block['description'] );
			if ( strpos( $searchable, 'paragraph' ) !== false ) {
				$found_paragraph = true;
				break;
			}
		}
		$this->assertTrue( $found_paragraph, 'Should find blocks matching search term' );
	}

	/**
	 * Test that core WordPress blocks are included.
	 */
	public function test_core_wordpress_blocks_included(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$result = ListBlockTypes::execute( array() );
		$blocks = $result['blocks'];

		// Look for common core blocks.
		$block_names = array_column( $blocks, 'name' );
		$core_blocks = array( 'core/paragraph', 'core/heading', 'core/image', 'core/list' );

		$found_core_blocks = array_intersect( $core_blocks, $block_names );
		$this->assertNotEmpty( $found_core_blocks, 'Should find some core WordPress blocks' );
	}

	/**
	 * Test block attributes are properly formatted.
	 */
	public function test_block_attributes_formatting(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$result = ListBlockTypes::execute( array() );
		$blocks = $result['blocks'];
		$this->assertNotEmpty( $blocks );

		foreach ( $blocks as $block ) {
			// Attributes should be an object (array or stdClass).
			$this->assertTrue(
				is_array( $block['attributes'] ) || $block['attributes'] instanceof \stdClass,
				'Block attributes should be an array or object'
			);

			// Supports should be an object (array or stdClass).
			$this->assertTrue(
				is_array( $block['supports'] ) || $block['supports'] instanceof \stdClass,
				'Block supports should be an array or object'
			);

			// Keywords should be an array.
			$this->assertIsArray( $block['keywords'], 'Block keywords should be an array' );
		}
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$result = $this->execute_ability( 'wpmcp-example/list-block-types', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertIsArray( $result['blocks'] );
	}

	/**
	 * Test search with no results.
	 */
	public function test_search_with_no_results(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'search' => 'nonexistent_block_name_12345' );
		$result = ListBlockTypes::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertEmpty( $result['blocks'], 'Should return empty array for non-matching search' );
	}

	/**
	 * Test category filter with non-existent category.
	 */
	public function test_category_filter_with_non_existent_category(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'category' => 'nonexistent_category' );
		$result = ListBlockTypes::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertEmpty( $result['blocks'], 'Should return empty array for non-matching category' );
	}
}
