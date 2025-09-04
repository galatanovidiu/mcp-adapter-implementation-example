<?php
/**
 * End-to-end tests simulating real AI agent interactions.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test complete workflows that an AI agent might perform.
 */
final class EndToEndWorkflowTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		BootstrapAbilities::init();
	}

	/**
	 * Test complete blog post creation workflow.
	 *
	 * Simulates an AI agent creating a blog post with categories and tags.
	 */
	public function test_complete_blog_post_creation_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Step 1: AI agent discovers available block types.
		$blocks_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $blocks_response->get_status() );
		$blocks_data = $blocks_response->get_data();
		$this->assertArrayHasKey( 'content', $blocks_data );
		$this->assertArrayHasKey( 'blocks', $blocks_data['content'] );

		// Step 2: AI agent creates a category.
		$category_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-term',
				'arguments' => array(
					'taxonomy'    => 'category',
					'name'        => 'AI Generated Content',
					'description' => 'Content created by AI agents',
				),
			)
		);

		$this->assertEquals( 200, $category_response->get_status() );
		$category_data = $category_response->get_data();
		$category_id   = $category_data['content']['id'];

		// Step 3: AI agent creates a tag.
		$tag_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-term',
				'arguments' => array(
					'taxonomy' => 'post_tag',
					'name'     => 'ai-generated',
				),
			)
		);

		$this->assertEquals( 200, $tag_response->get_status() );
		$tag_data = $tag_response->get_data();

		// Step 4: AI agent creates a comprehensive blog post.
		$post_content = '<!-- wp:heading {"level":2} --><h2>AI Generated Article</h2><!-- /wp:heading -->' .
						'<!-- wp:paragraph --><p>This article was created by an AI agent using the MCP Adapter.</p><!-- /wp:paragraph -->' .
						'<!-- wp:list --><ul><li>First point</li><li>Second point</li><li>Third point</li></ul><!-- /wp:list -->';

		$post_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Complete AI Generated Blog Post',
					'content'   => $post_content,
					'excerpt'   => 'This is an excerpt for the AI generated post.',
					'status'    => 'draft',
					'meta'      => array(
						'_ai_generated'  => 'true',
						'_creation_date' => date( 'Y-m-d H:i:s' ),
						'_workflow_test' => 'end-to-end',
					),
					'tax_input' => array(
						'category' => array( $category_id ),
						'post_tag' => array( 'ai-generated' ),
					),
				),
			)
		);

		$this->assertEquals( 200, $post_response->get_status() );
		$post_data = $post_response->get_data();
		$post_id   = $post_data['content']['id'];

		// Step 5: AI agent verifies the post was created correctly.
		$get_post_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-get-post',
				'arguments' => array(
					'post_id'            => $post_id,
					'include_meta'       => true,
					'include_taxonomies' => true,
				),
			)
		);

		$this->assertEquals( 200, $get_post_response->get_status() );
		$retrieved_post = $get_post_response->get_data()['content'];

		// Verify all data was preserved.
		$this->assertEquals( 'Complete AI Generated Blog Post', $retrieved_post['title'] );
		$this->assertEquals( 'draft', $retrieved_post['status'] );
		$this->assertStringContains( 'wp:heading', $retrieved_post['content'] );
		$this->assertStringContains( 'AI Generated Article', $retrieved_post['content'] );

		// Verify meta fields.
		$this->assertArrayHasKey( 'meta', $retrieved_post );
		$meta = $retrieved_post['meta'];
		$this->assertEquals( 'true', $meta['_ai_generated'][0] ?? '' );
		$this->assertEquals( 'end-to-end', $meta['_workflow_test'][0] ?? '' );

		// Verify taxonomies.
		$this->assertArrayHasKey( 'taxonomies', $retrieved_post );
		$taxonomies = $retrieved_post['taxonomies'];
		$this->assertArrayHasKey( 'category', $taxonomies );
		$this->assertArrayHasKey( 'post_tag', $taxonomies );

		$this->assertEquals( 'AI Generated Content', $taxonomies['category'][0]['name'] );
		$this->assertEquals( 'ai-generated', $taxonomies['post_tag'][0]['slug'] );

		// Step 6: AI agent lists posts to verify it appears in listings.
		$list_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'          => array( 'post' ),
					'post_status'        => array( 'draft' ),
					'search'             => 'AI Generated',
					'include_taxonomies' => true,
				),
			)
		);

		$this->assertEquals( 200, $list_response->get_status() );
		$list_data = $list_response->get_data();
		$posts     = $list_data['content']['posts'];

		$this->assertNotEmpty( $posts, 'Should find the created post in listings' );

		$found_post = null;
		foreach ( $posts as $post ) {
			if ( $post['id'] === $post_id ) {
				$found_post = $post;
				break;
			}
		}

		$this->assertNotNull( $found_post, 'Should find our created post in the list' );
		$this->assertEquals( 'Complete AI Generated Blog Post', $found_post['title'] );
	}

	/**
	 * Test content management workflow.
	 *
	 * Simulates an AI agent managing existing content.
	 */
	public function test_content_management_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Step 1: Create initial post.
		$create_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Draft Article',
					'content'   => '<!-- wp:paragraph --><p>Initial draft content.</p><!-- /wp:paragraph -->',
					'status'    => 'draft',
				),
			)
		);

		$this->assertEquals( 200, $create_response->get_status() );
		$post_id = $create_response->get_data()['content']['id'];

		// Step 2: Update the post with more content.
		$update_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-update-post',
				'arguments' => array(
					'post_id' => $post_id,
					'title'   => 'Updated Article Title',
					'content' => '<!-- wp:heading --><h2>Updated Content</h2><!-- /wp:heading -->' .
								'<!-- wp:paragraph --><p>This content has been updated by an AI agent.</p><!-- /wp:paragraph -->',
					'status'  => 'publish',
				),
			)
		);

		$this->assertEquals( 200, $update_response->get_status() );

		// Step 3: Add meta data.
		$meta_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-update-post-meta',
				'arguments' => array(
					'post_id'    => $post_id,
					'meta_key'   => 'ai_revision_count',
					'meta_value' => '1',
				),
			)
		);

		$this->assertEquals( 200, $meta_response->get_status() );

		// Step 4: Verify all changes.
		$get_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-get-post',
				'arguments' => array(
					'post_id'      => $post_id,
					'include_meta' => true,
				),
			)
		);

		$this->assertEquals( 200, $get_response->get_status() );
		$final_post = $get_response->get_data()['content'];

		$this->assertEquals( 'Updated Article Title', $final_post['title'] );
		$this->assertEquals( 'publish', $final_post['status'] );
		$this->assertStringContains( 'Updated Content', $final_post['content'] );
		$this->assertEquals( '1', $final_post['meta']['ai_revision_count'][0] ?? '' );
	}

	/**
	 * Test discovery and exploration workflow.
	 *
	 * Simulates an AI agent exploring available functionality.
	 */
	public function test_discovery_and_exploration_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Step 1: Initialize connection and discover capabilities.
		$init_response = $this->make_mcp_request( 'initialize' );
		$this->assertEquals( 200, $init_response->get_status() );

		// Step 2: List available tools.
		$tools_response = $this->make_mcp_request( 'tools/list' );
		$this->assertEquals( 200, $tools_response->get_status() );

		$tools = $tools_response->get_data()['tools'];
		$this->assertNotEmpty( $tools );

		// Step 3: Explore available post types by listing posts.
		$posts_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'any' ),
					'limit'     => 1,
				),
			)
		);

		$this->assertEquals( 200, $posts_response->get_status() );

		// Step 4: Discover available block types.
		$blocks_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $blocks_response->get_status() );

		// Step 5: Explore taxonomies.
		$taxonomies_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-taxonomies',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $taxonomies_response->get_status() );

		// Verify all discovery calls succeeded and returned expected data.
		$this->assertArrayHasKey( 'tools', $tools_response->get_data() );
		$this->assertArrayHasKey( 'content', $posts_response->get_data() );
		$this->assertArrayHasKey( 'content', $blocks_response->get_data() );
		$this->assertArrayHasKey( 'content', $taxonomies_response->get_data() );
	}

	/**
	 * Test bulk content creation workflow.
	 *
	 * Simulates an AI agent creating multiple related posts.
	 */
	public function test_bulk_content_creation_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$created_post_ids = array();

		// Create a series of related posts.
		$post_topics = array(
			'Introduction to WordPress',
			'WordPress Themes Guide',
			'WordPress Plugins Overview',
		);

		foreach ( $post_topics as $index => $topic ) {
			$response = $this->make_mcp_request(
				'tools/call',
				array(
					'name'      => 'wpmcp-example-create-post',
					'arguments' => array(
						'post_type' => 'post',
						'title'     => $topic,
						'content'   => "<!-- wp:paragraph --><p>This is content about {$topic}.</p><!-- /wp:paragraph -->",
						'status'    => 'draft',
						'meta'      => array(
							'series_order' => (string) ( $index + 1 ),
							'series_name'  => 'WordPress Guide Series',
						),
					),
				)
			);

			$this->assertEquals( 200, $response->get_status(), "Post creation for '{$topic}' should succeed" );
			$created_post_ids[] = $response->get_data()['content']['id'];
		}

		$this->assertCount( 3, $created_post_ids, 'Should create 3 posts' );

		// Verify all posts were created and can be listed.
		$list_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'    => array( 'post' ),
					'post_status'  => array( 'draft' ),
					'meta_query'   => array(
						array(
							'key'   => 'series_name',
							'value' => 'WordPress Guide Series',
						),
					),
					'include_meta' => true,
				),
			)
		);

		$this->assertEquals( 200, $list_response->get_status() );
		$posts = $list_response->get_data()['content']['posts'];
		$this->assertCount( 3, $posts, 'Should find all 3 posts in the series' );

		// Verify posts are properly ordered.
		$series_orders = array();
		foreach ( $posts as $post ) {
			$series_orders[] = $post['meta']['series_order'][0] ?? '';
		}

		$this->assertContains( '1', $series_orders );
		$this->assertContains( '2', $series_orders );
		$this->assertContains( '3', $series_orders );
	}

	/**
	 * Test content analysis and metadata workflow.
	 *
	 * Simulates an AI agent analyzing existing content and adding metadata.
	 */
	public function test_content_analysis_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create some initial content.
		$post_id = $this->create_test_post(
			array(
				'post_title'   => 'Article to Analyze',
				'post_content' => 'This is an article about WordPress development and best practices.',
				'post_status'  => 'publish',
			)
		);

		$adapter = $this->get_mcp_adapter();

		// Step 1: AI agent retrieves the post for analysis.
		$get_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-get-post',
				'arguments' => array(
					'post_id' => $post_id,
				),
			)
		);

		$this->assertEquals( 200, $get_response->get_status() );
		$post_data = $get_response->get_data()['content'];

		// Step 2: AI agent analyzes content and adds metadata.
		$analysis_meta = array(
			'word_count'       => str_word_count( $post_data['content'] ),
			'reading_time'     => ceil( str_word_count( $post_data['content'] ) / 200 ), // Assume 200 wpm.
			'content_topics'   => 'wordpress,development,best-practices',
			'ai_analysis_date' => date( 'Y-m-d H:i:s' ),
			'content_quality'  => 'good',
		);

		foreach ( $analysis_meta as $key => $value ) {
			$meta_response = $this->make_mcp_request(
				'tools/call',
				array(
					'name'      => 'wpmcp-example-update-post-meta',
					'arguments' => array(
						'post_id'    => $post_id,
						'meta_key'   => $key,
						'meta_value' => (string) $value,
					),
				)
			);

			$this->assertEquals( 200, $meta_response->get_status(), "Meta update for '{$key}' should succeed" );
		}

		// Step 3: AI agent verifies the analysis was stored.
		$verify_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-get-post',
				'arguments' => array(
					'post_id'      => $post_id,
					'include_meta' => true,
				),
			)
		);

		$this->assertEquals( 200, $verify_response->get_status() );
		$analyzed_post = $verify_response->get_data()['content'];

		// Verify all analysis metadata was stored.
		$meta = $analyzed_post['meta'];
		$this->assertArrayHasKey( 'word_count', $meta );
		$this->assertArrayHasKey( 'reading_time', $meta );
		$this->assertArrayHasKey( 'content_topics', $meta );
		$this->assertArrayHasKey( 'ai_analysis_date', $meta );
		$this->assertArrayHasKey( 'content_quality', $meta );

		$this->assertEquals( 'WordPress,development,best-practices', $meta['content_topics'][0] );
		$this->assertEquals( 'good', $meta['content_quality'][0] );
	}

	/**
	 * Test taxonomy management workflow.
	 *
	 * Simulates an AI agent organizing content with taxonomies.
	 */
	public function test_taxonomy_management_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Step 1: Create a hierarchical category structure.
		$parent_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-term',
				'arguments' => array(
					'taxonomy'    => 'category',
					'name'        => 'Technology',
					'description' => 'Technology related articles',
				),
			)
		);

		$this->assertEquals( 200, $parent_response->get_status() );
		$parent_id = $parent_response->get_data()['content']['id'];

		$child_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-term',
				'arguments' => array(
					'taxonomy'    => 'category',
					'name'        => 'Web Development',
					'description' => 'Web development articles',
					'parent'      => $parent_id,
				),
			)
		);

		$this->assertEquals( 200, $child_response->get_status() );
		$child_id = $child_response->get_data()['content']['id'];

		// Step 2: Create posts and assign to categories.
		$post1_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'WordPress Development Tips',
					'content'   => '<!-- wp:paragraph --><p>Tips for WordPress development.</p><!-- /wp:paragraph -->',
					'tax_input' => array(
						'category' => array( $child_id ),
					),
				),
			)
		);

		$this->assertEquals( 200, $post1_response->get_status() );
		$post1_id = $post1_response->get_data()['content']['id'];

		// Step 3: Verify taxonomy assignments.
		$get_post_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-get-post',
				'arguments' => array(
					'post_id'            => $post1_id,
					'include_taxonomies' => true,
				),
			)
		);

		$this->assertEquals( 200, $get_post_response->get_status() );
		$post_with_tax = $get_post_response->get_data()['content'];

		$this->assertArrayHasKey( 'taxonomies', $post_with_tax );
		$this->assertArrayHasKey( 'category', $post_with_tax['taxonomies'] );
		$this->assertEquals( 'Web Development', $post_with_tax['taxonomies']['category'][0]['name'] );

		// Step 4: List terms to verify hierarchy.
		$terms_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-get-terms',
				'arguments' => array(
					'taxonomy' => 'category',
					'parent'   => $parent_id,
				),
			)
		);

		$this->assertEquals( 200, $terms_response->get_status() );
		$child_terms = $terms_response->get_data()['content']['terms'];

		$this->assertNotEmpty( $child_terms );
		$this->assertEquals( 'Web Development', $child_terms[0]['name'] );
		$this->assertEquals( $parent_id, $child_terms[0]['parent'] );
	}

	/**
	 * Test error recovery workflow.
	 *
	 * Simulates an AI agent handling errors gracefully.
	 */
	public function test_error_recovery_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Step 1: Try to create a post with invalid data.
		$invalid_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'invalid_type',
					'title'     => 'Test Post',
				),
			)
		);

		$this->assertEquals( 500, $invalid_response->get_status() );

		// Step 2: AI agent recovers by using valid data.
		$valid_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Recovery Test Post',
					'content'   => '<!-- wp:paragraph --><p>This post was created after error recovery.</p><!-- /wp:paragraph -->',
				),
			)
		);

		$this->assertEquals( 200, $valid_response->get_status() );
		$post_data = $valid_response->get_data()['content'];

		$this->assertArrayHasKey( 'id', $post_data );
		$this->assertEquals( 'Recovery Test Post', $post_data['title'] );

		// Step 3: Verify the post exists.
		$post = get_post( $post_data['id'] );
		$this->assertNotNull( $post );
		$this->assertEquals( 'Recovery Test Post', $post->post_title );
	}

	/**
	 * Test complex search and filtering workflow.
	 *
	 * Simulates an AI agent performing complex content queries.
	 */
	public function test_complex_search_workflow(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Create test content with specific characteristics.
		$tech_category_id = $this->create_test_term( 'Technology', 'category' );
		$tutorial_tag_id  = $this->create_test_term( 'tutorial', 'post_tag' );

		$post_id = $this->create_test_post(
			array(
				'post_title'   => 'Advanced WordPress Tutorial',
				'post_content' => 'This is a comprehensive tutorial about WordPress development.',
				'post_status'  => 'publish',
				'meta_input'   => array(
					'difficulty_level' => 'advanced',
					'estimated_time'   => '30',
				),
			)
		);

		wp_set_post_terms( $post_id, array( $tech_category_id ), 'category' );
		wp_set_post_terms( $post_id, array( $tutorial_tag_id ), 'post_tag' );

		// Step 1: Search by content.
		$search_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'search'             => 'WordPress tutorial',
					'post_type'          => array( 'post' ),
					'post_status'        => array( 'publish' ),
					'include_meta'       => true,
					'include_taxonomies' => true,
				),
			)
		);

		$this->assertEquals( 200, $search_response->get_status() );
		$search_posts = $search_response->get_data()['content']['posts'];
		$this->assertNotEmpty( $search_posts );

		// Step 2: Filter by meta query.
		$meta_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'    => array( 'post' ),
					'post_status'  => array( 'publish' ),
					'meta_query'   => array(
						array(
							'key'   => 'difficulty_level',
							'value' => 'advanced',
						),
					),
					'include_meta' => true,
				),
			)
		);

		$this->assertEquals( 200, $meta_response->get_status() );
		$meta_posts = $meta_response->get_data()['content']['posts'];
		$this->assertNotEmpty( $meta_posts );

		// Step 3: Filter by taxonomy.
		$tax_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'          => array( 'post' ),
					'post_status'        => array( 'publish' ),
					'tax_query'          => array(
						array(
							'taxonomy' => 'category',
							'field'    => 'term_id',
							'terms'    => array( $tech_category_id ),
						),
					),
					'include_taxonomies' => true,
				),
			)
		);

		$this->assertEquals( 200, $tax_response->get_status() );
		$tax_posts = $tax_response->get_data()['content']['posts'];
		$this->assertNotEmpty( $tax_posts );

		// Verify our test post appears in all filtered results.
		$found_in_search = false;
		$found_in_meta   = false;
		$found_in_tax    = false;

		foreach ( $search_posts as $post ) {
			if ( $post['id'] === $post_id ) {
				$found_in_search = true;
				break;
			}
		}

		foreach ( $meta_posts as $post ) {
			if ( $post['id'] === $post_id ) {
				$found_in_meta = true;
				break;
			}
		}

		foreach ( $tax_posts as $post ) {
			if ( $post['id'] === $post_id ) {
				$found_in_tax = true;
				break;
			}
		}

		$this->assertTrue( $found_in_search, 'Post should be found in search results' );
		$this->assertTrue( $found_in_meta, 'Post should be found in meta query results' );
		$this->assertTrue( $found_in_tax, 'Post should be found in taxonomy query results' );
	}

	/**
	 * Test performance with large datasets.
	 *
	 * Simulates an AI agent working with larger amounts of data.
	 */
	public function test_performance_with_large_datasets(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Create multiple posts for performance testing.
		$created_posts = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$response = $this->make_mcp_request(
				'tools/call',
				array(
					'name'      => 'wpmcp-example-create-post',
					'arguments' => array(
						'post_type' => 'post',
						'title'     => "Performance Test Post {$i}",
						'content'   => "<!-- wp:paragraph --><p>Content for post number {$i}.</p><!-- /wp:paragraph -->",
						'status'    => 'publish',
						'meta'      => array(
							'test_number' => (string) $i,
						),
					),
				)
			);

			$this->assertEquals( 200, $response->get_status(), "Post {$i} creation should succeed" );
			$created_posts[] = $response->get_data()['content']['id'];
		}

		$this->assertCount( 20, $created_posts, 'Should create 20 posts' );

		// Test pagination with the large dataset.
		$page1_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'   => array( 'post' ),
					'post_status' => array( 'publish' ),
					'limit'       => 10,
					'offset'      => 0,
					'orderby'     => 'date',
					'order'       => 'DESC',
				),
			)
		);

		$this->assertEquals( 200, $page1_response->get_status() );
		$page1_data = $page1_response->get_data()['content'];
		$this->assertEquals( 10, $page1_data['total'] );
		$this->assertGreaterThanOrEqual( 20, $page1_data['found_posts'] );

		// Test second page.
		$page2_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'   => array( 'post' ),
					'post_status' => array( 'publish' ),
					'limit'       => 10,
					'offset'      => 10,
					'orderby'     => 'date',
					'order'       => 'DESC',
				),
			)
		);

		$this->assertEquals( 200, $page2_response->get_status() );
		$page2_data = $page2_response->get_data()['content'];
		$this->assertGreaterThan( 0, $page2_data['total'] );

		// Verify no overlap between pages.
		$page1_ids = array_column( $page1_data['posts'], 'id' );
		$page2_ids = array_column( $page2_data['posts'], 'id' );
		$overlap   = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not have overlapping posts' );
	}
}
