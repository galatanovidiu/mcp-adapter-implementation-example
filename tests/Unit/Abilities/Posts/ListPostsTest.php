<?php
/**
 * Unit tests for ListPosts ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Posts
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ListPosts ability functionality.
 */
final class ListPostsTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/list-posts' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'post_type' => array( 'post' ) );
		$has_permission = ListPosts::check_permission( $input );

		$this->assertTrue( $has_permission, 'Author should have permission to list posts' );
	}

	/**
	 * Test permission checking with subscriber.
	 */
	public function test_permission_check_with_subscriber(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'post_type' => array( 'post' ) );
		$has_permission = ListPosts::check_permission( $input );

		$this->assertTrue( $has_permission, 'Subscriber should have permission to read published posts' );
	}

	/**
	 * Test basic post listing.
	 */
	public function test_basic_post_listing(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create test posts.
		$post1_id = $this->create_test_post(
			array(
				'post_title'  => 'Test Post 1',
				'post_status' => 'publish',
			)
		);

		$post2_id = $this->create_test_post(
			array(
				'post_title'  => 'Test Post 2',
				'post_status' => 'publish',
			)
		);

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'limit'       => 10,
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'found_posts', $result );
		$this->assertArrayHasKey( 'max_pages', $result );

		$posts = $result['posts'];
		$this->assertIsArray( $posts );
		$this->assertGreaterThanOrEqual( 2, count( $posts ), 'Should return at least our test posts' );

		// Verify post structure.
		$first_post = $posts[0];
		$this->assertArrayHasKey( 'id', $first_post );
		$this->assertArrayHasKey( 'post_type', $first_post );
		$this->assertArrayHasKey( 'status', $first_post );
		$this->assertArrayHasKey( 'title', $first_post );
		$this->assertArrayHasKey( 'content', $first_post );
		$this->assertArrayHasKey( 'link', $first_post );
		$this->assertArrayHasKey( 'date', $first_post );
		$this->assertArrayHasKey( 'author', $first_post );
		$this->assertArrayHasKey( 'slug', $first_post );
	}

	/**
	 * Test post listing with search.
	 */
	public function test_post_listing_with_search(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create test posts with specific titles.
		$this->create_test_post(
			array(
				'post_title'  => 'Searchable Post About Cats',
				'post_status' => 'publish',
			)
		);

		$this->create_test_post(
			array(
				'post_title'  => 'Different Post About Dogs',
				'post_status' => 'publish',
			)
		);

		$input = array(
			'search'      => 'cats',
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts, 'Should find posts matching search term' );

		// Verify search worked.
		$found_cat_post = false;
		foreach ( $posts as $post ) {
			if ( stripos( $post['title'], 'cats' ) !== false ) {
				$found_cat_post = true;
				break;
			}
		}
		$this->assertTrue( $found_cat_post, 'Should find post with "cats" in title' );
	}

	/**
	 * Test post listing with pagination.
	 */
	public function test_post_listing_with_pagination(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create multiple test posts.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->create_test_post(
				array(
					'post_title'  => "Pagination Test Post {$i}",
					'post_status' => 'publish',
				)
			);
		}

		// Test first page.
		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'limit'       => 2,
			'offset'      => 0,
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$this->assertEquals( 2, $result['total'], 'Should return 2 posts on first page' );
		$this->assertGreaterThanOrEqual( 5, $result['found_posts'], 'Should find at least 5 total posts' );

		// Test second page.
		$input['offset'] = 2;
		$result2         = ListPosts::execute( $input );

		$this->assertIsArray( $result2 );
		$this->assertLessThanOrEqual( 2, $result2['total'], 'Should return up to 2 posts on second page' );
	}

	/**
	 * Test post listing with meta inclusion.
	 */
	public function test_post_listing_with_meta_inclusion(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->create_test_post(
			array(
				'post_title'  => 'Post with Meta',
				'post_status' => 'publish',
				'meta_input'  => array(
					'test_meta_key' => 'test_meta_value',
				),
			)
		);

		$input = array(
			'post_type'    => array( 'post' ),
			'post_status'  => array( 'publish' ),
			'include_meta' => true,
			'limit'        => 1,
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts );

		$first_post = $posts[0];
		$this->assertArrayHasKey( 'meta', $first_post );
		$this->assertIsArray( $first_post['meta'] );
	}

	/**
	 * Test post listing with taxonomy inclusion.
	 */
	public function test_post_listing_with_taxonomy_inclusion(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create a test category.
		$term_id = $this->create_test_term( 'Test Category', 'category' );

		$post_id = $this->create_test_post(
			array(
				'post_title'  => 'Post with Category',
				'post_status' => 'publish',
			)
		);

		// Assign category to post.
		wp_set_post_terms( $post_id, array( $term_id ), 'category' );

		$input = array(
			'post_type'          => array( 'post' ),
			'post_status'        => array( 'publish' ),
			'include_taxonomies' => true,
			'limit'              => 1,
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts );

		$first_post = $posts[0];
		$this->assertArrayHasKey( 'taxonomies', $first_post );
		$this->assertIsArray( $first_post['taxonomies'] );
		$this->assertArrayHasKey( 'category', $first_post['taxonomies'] );
	}

	/**
	 * Test post listing with date query.
	 */
	public function test_post_listing_with_date_query(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create a post from a specific year.
		$this->create_test_post(
			array(
				'post_title'  => 'Old Post',
				'post_status' => 'publish',
				'post_date'   => '2020-01-01 12:00:00',
			)
		);

		$this->create_test_post(
			array(
				'post_title'  => 'Recent Post',
				'post_status' => 'publish',
				'post_date'   => date( 'Y-m-d H:i:s' ),
			)
		);

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'date_query'  => array(
				'year' => 2020,
			),
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts, 'Should find posts from 2020' );

		// Verify the found post is from 2020.
		$found_old_post = false;
		foreach ( $posts as $post ) {
			if ( $post['title'] === 'Old Post' ) {
				$found_old_post = true;
				$this->assertStringStartsWith( '2020-', $post['date'] );
				break;
			}
		}
		$this->assertTrue( $found_old_post, 'Should find the old post from 2020' );
	}

	/**
	 * Test post listing with meta query.
	 */
	public function test_post_listing_with_meta_query(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create posts with specific meta.
		$this->create_test_post(
			array(
				'post_title'  => 'Featured Post',
				'post_status' => 'publish',
				'meta_input'  => array(
					'featured' => 'yes',
				),
			)
		);

		$this->create_test_post(
			array(
				'post_title'  => 'Regular Post',
				'post_status' => 'publish',
				'meta_input'  => array(
					'featured' => 'no',
				),
			)
		);

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'meta_query'  => array(
				array(
					'key'   => 'featured',
					'value' => 'yes',
				),
			),
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts, 'Should find featured posts' );

		// Verify we found the featured post.
		$found_featured = false;
		foreach ( $posts as $post ) {
			if ( $post['title'] === 'Featured Post' ) {
				$found_featured = true;
				break;
			}
		}
		$this->assertTrue( $found_featured, 'Should find the featured post' );
	}

	/**
	 * Test post listing with taxonomy query.
	 */
	public function test_post_listing_with_taxonomy_query(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create a test category.
		$term_id = $this->create_test_term( 'Test Category', 'category' );

		// Create posts with and without the category.
		$categorized_post_id = $this->create_test_post(
			array(
				'post_title'  => 'Categorized Post',
				'post_status' => 'publish',
			)
		);
		wp_set_post_terms( $categorized_post_id, array( $term_id ), 'category' );

		$this->create_test_post(
			array(
				'post_title'  => 'Uncategorized Post',
				'post_status' => 'publish',
			)
		);

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'tax_query'   => array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
				),
			),
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts, 'Should find categorized posts' );

		// Verify we found the categorized post.
		$found_categorized = false;
		foreach ( $posts as $post ) {
			if ( $post['title'] === 'Categorized Post' ) {
				$found_categorized = true;
				break;
			}
		}
		$this->assertTrue( $found_categorized, 'Should find the categorized post' );
	}

	/**
	 * Test post listing with 'any' post type.
	 */
	public function test_post_listing_with_any_post_type(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create posts of different types.
		$this->create_test_post(
			array(
				'post_title'  => 'Regular Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$this->create_test_post(
			array(
				'post_title'  => 'Test Page',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$input = array(
			'post_type'   => array( 'any' ),
			'post_status' => array( 'publish' ),
			'limit'       => 10,
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts, 'Should find posts of any type' );

		// Verify we have different post types.
		$post_types = array_unique( array_column( $posts, 'post_type' ) );
		$this->assertGreaterThan( 1, count( $post_types ), 'Should find multiple post types' );
	}

	/**
	 * Test post listing with ordering.
	 */
	public function test_post_listing_with_ordering(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		// Create posts with specific titles for ordering.
		$this->create_test_post(
			array(
				'post_title'  => 'Alpha Post',
				'post_status' => 'publish',
			)
		);

		$this->create_test_post(
			array(
				'post_title'  => 'Beta Post',
				'post_status' => 'publish',
			)
		);

		$this->create_test_post(
			array(
				'post_title'  => 'Gamma Post',
				'post_status' => 'publish',
			)
		);

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'orderby'     => 'title',
			'order'       => 'ASC',
			'limit'       => 10,
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$posts = $result['posts'];
		$this->assertNotEmpty( $posts );

		// Find our test posts and verify they're in alphabetical order.
		$test_posts = array_filter(
			$posts,
			static function ( $post ) {
				return in_array( $post['title'], array( 'Alpha Post', 'Beta Post', 'Gamma Post' ), true );
			}
		);

		$this->assertCount( 3, $test_posts, 'Should find all 3 test posts' );

		$titles = array_column( $test_posts, 'title' );
		$this->assertEquals( array( 'Alpha Post', 'Beta Post', 'Gamma Post' ), array_values( $titles ) );
	}

	/**
	 * Test empty result handling.
	 */
	public function test_empty_result_handling(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'search'      => 'nonexistent_search_term_12345',
		);

		$result = ListPosts::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'found_posts', $result );
		$this->assertArrayHasKey( 'max_pages', $result );

		$this->assertEmpty( $result['posts'] );
		$this->assertEquals( 0, $result['total'] );
		$this->assertEquals( 0, $result['found_posts'] );
		$this->assertEquals( 0, $result['max_pages'] );
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$this->create_test_post(
			array(
				'post_title'  => 'API Test Post',
				'post_status' => 'publish',
			)
		);

		$input = array(
			'post_type'   => array( 'post' ),
			'post_status' => array( 'publish' ),
			'limit'       => 5,
		);

		$result = $this->execute_ability( 'wpmcp-example/list-posts', $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertNotEmpty( $result['posts'] );
	}
}
