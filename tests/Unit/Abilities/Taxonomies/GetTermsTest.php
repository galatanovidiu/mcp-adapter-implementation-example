<?php
/**
 * Unit tests for GetTerms ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetTerms ability functionality.
 */
final class GetTermsTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/get-terms' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'category' );
		$has_permission = GetTerms::check_permission( $input );

		$this->assertTrue( $has_permission, 'Author should have permission to get terms' );
	}

	/**
	 * Test basic terms listing.
	 */
	public function test_basic_terms_listing(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create test terms.
		$term1_id = $this->create_test_term( 'Test Category 1', 'category' );
		$term2_id = $this->create_test_term( 'Test Category 2', 'category' );

		$input = array(
			'taxonomy' => 'category',
			'limit'    => 10,
		);

		$result = GetTerms::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'terms', $result );
		$this->assertArrayHasKey( 'total', $result );

		$terms = $result['terms'];
		$this->assertIsArray( $terms );
		$this->assertGreaterThanOrEqual( 2, count( $terms ), 'Should return at least our test terms' );

		// Verify term structure.
		$first_term = $terms[0];
		$this->assertArrayHasKey( 'id', $first_term );
		$this->assertArrayHasKey( 'name', $first_term );
		$this->assertArrayHasKey( 'slug', $first_term );
		$this->assertArrayHasKey( 'description', $first_term );
		$this->assertArrayHasKey( 'taxonomy', $first_term );
		$this->assertArrayHasKey( 'parent', $first_term );
		$this->assertArrayHasKey( 'count', $first_term );
	}

	/**
	 * Test terms listing with search.
	 */
	public function test_terms_listing_with_search(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create test terms with specific names.
		$this->create_test_term( 'Searchable Category', 'category' );
		$this->create_test_term( 'Different Category', 'category' );

		$input = array(
			'taxonomy' => 'category',
			'search'   => 'searchable',
		);

		$result = GetTerms::execute( $input );

		$this->assertIsArray( $result );
		$terms = $result['terms'];
		$this->assertNotEmpty( $terms, 'Should find terms matching search' );

		// Verify search worked.
		$found_searchable = false;
		foreach ( $terms as $term ) {
			if ( stripos( $term['name'], 'searchable' ) !== false ) {
				$found_searchable = true;
				break;
			}
		}
		$this->assertTrue( $found_searchable, 'Should find term with "searchable" in name' );
	}

	/**
	 * Test terms listing with parent filter.
	 */
	public function test_terms_listing_with_parent_filter(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create parent and child terms.
		$parent_id = $this->create_test_term( 'Parent Category', 'category' );
		$child_id  = $this->create_test_term(
			'Child Category',
			'category',
			array(
				'parent' => $parent_id,
			)
		);

		$input = array(
			'taxonomy' => 'category',
			'parent'   => $parent_id,
		);

		$result = GetTerms::execute( $input );

		$this->assertIsArray( $result );
		$terms = $result['terms'];
		$this->assertNotEmpty( $terms, 'Should find child terms' );

		// Verify we found the child term.
		$found_child = false;
		foreach ( $terms as $term ) {
			if ( $term['id'] === $child_id ) {
				$found_child = true;
				$this->assertEquals( $parent_id, $term['parent'] );
				break;
			}
		}
		$this->assertTrue( $found_child, 'Should find the child term' );
	}

	/**
	 * Test terms listing with invalid taxonomy.
	 */
	public function test_terms_listing_with_invalid_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'taxonomy' => 'invalid_taxonomy' );
		$result = GetTerms::execute( $input );

		$this->assertArrayHasKey( 'error', $result, 'Should return error array for invalid taxonomy' );
		$this->assertEquals( 'invalid_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test terms listing with include_empty parameter.
	 */
	public function test_terms_listing_with_include_empty(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create a term without any posts.
		$empty_term_id = $this->create_test_term( 'Empty Category', 'category' );

		// Test excluding empty terms (default).
		$input1 = array(
			'taxonomy'      => 'category',
			'include_empty' => false,
		);

		$result1 = GetTerms::execute( $input1 );
		$this->assertIsArray( $result1 );

		// Test including empty terms.
		$input2 = array(
			'taxonomy'      => 'category',
			'include_empty' => true,
		);

		$result2 = GetTerms::execute( $input2 );
		$this->assertIsArray( $result2 );

		// Including empty should return more or equal terms.
		$this->assertGreaterThanOrEqual(
			count( $result1['terms'] ),
			count( $result2['terms'] ),
			'Including empty terms should return same or more terms'
		);
	}

	/**
	 * Test pagination.
	 */
	public function test_pagination(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create multiple test terms.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->create_test_term( "Pagination Test Term {$i}", 'category' );
		}

		// Test first page.
		$input = array(
			'taxonomy' => 'category',
			'limit'    => 2,
			'offset'   => 0,
		);

		$result = GetTerms::execute( $input );

		$this->assertIsArray( $result );
		$this->assertLessThanOrEqual( 2, count( $result['terms'] ), 'Should return at most 2 terms' );

		// Test second page.
		$input['offset'] = 2;
		$result2         = GetTerms::execute( $input );

		$this->assertIsArray( $result2 );
		$this->assertLessThanOrEqual( 2, $result2['total'], 'Should return at most 2 terms on second page' );
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$this->create_test_term( 'API Test Term', 'category' );

		$input  = array( 'taxonomy' => 'category' );
		$result = $this->execute_ability( 'wpmcp-example/get-terms', $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'terms', $result );
		$this->assertNotEmpty( $result['terms'] );
	}
}
