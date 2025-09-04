<?php
/**
 * Unit tests for CreatePost ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Posts
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test CreatePost ability functionality.
 */
final class CreatePostTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/create-post' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'post_type' => 'post' );
		$has_permission = CreatePost::check_permission( $input );

		$this->assertTrue( $has_permission, 'Editor should have permission to create posts' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'post_type' => 'post' );
		$has_permission = CreatePost::check_permission( $input );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to create posts' );
	}

	/**
	 * Test permission checking with invalid post type.
	 */
	public function test_permission_check_with_invalid_post_type(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'post_type' => 'invalid_type' );
		$has_permission = CreatePost::check_permission( $input );

		$this->assertFalse( $has_permission, 'Should not have permission for invalid post type' );
	}

	/**
	 * Test successful post creation.
	 */
	public function test_successful_post_creation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input = array(
			'post_type' => 'post',
			'title'     => 'Test Post Title',
			'content'   => 'Test post content',
			'status'    => 'draft',
		);

		$result = CreatePost::execute( $input );

		$this->assertIsArray( $result, 'Should return array result' );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'post_type', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'title', $result );
		$this->assertArrayHasKey( 'link', $result );

		$this->assertEquals( 'post', $result['post_type'] );
		$this->assertEquals( 'draft', $result['status'] );
		$this->assertEquals( 'Test Post Title', $result['title'] );

		// Verify post exists in database.
		$post = get_post( $result['id'] );
		$this->assertNotNull( $post );
		$this->assertEquals( 'Test Post Title', $post->post_title );
		$this->assertEquals( 'Test post content', $post->post_content );
	}

	/**
	 * Test post creation with invalid post type.
	 */
	public function test_post_creation_with_invalid_post_type(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$input = array(
			'post_type' => 'invalid_type',
			'title'     => 'Test Post',
		);

		$result = CreatePost::execute( $input );

		$this->assertArrayHasKey( 'error', $result, 'Should return error array for invalid post type' );
		$this->assertEquals( 'invalid_post_type', $result['error']['code'] );
	}

	/**
	 * Test post creation with meta fields.
	 */
	public function test_post_creation_with_meta_fields(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input = array(
			'post_type' => 'post',
			'title'     => 'Test Post with Meta',
			'content'   => 'Test content',
			'meta'      => array(
				'custom_field_1' => 'value1',
				'custom_field_2' => 'value2',
			),
		);

		$result = CreatePost::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );

		$post_id = $result['id'];

		// Verify meta fields were set.
		$this->assertEquals( 'value1', get_post_meta( $post_id, 'custom_field_1', true ) );
		$this->assertEquals( 'value2', get_post_meta( $post_id, 'custom_field_2', true ) );
	}

	/**
	 * Test post creation with taxonomy terms.
	 */
	public function test_post_creation_with_taxonomy_terms(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create a test category.
		$term_id = $this->create_test_term( 'Test Category', 'category' );

		$input = array(
			'post_type' => 'post',
			'title'     => 'Test Post with Terms',
			'content'   => 'Test content',
			'tax_input' => array(
				'category' => array( $term_id ),
			),
		);

		$result = CreatePost::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );

		$post_id = $result['id'];

		// Verify terms were assigned.
		$post_terms = wp_get_post_terms( $post_id, 'category' );
		$this->assertNotEmpty( $post_terms );
		$this->assertEquals( $term_id, $post_terms[0]->term_id );
	}

	/**
	 * Test post creation with term creation.
	 */
	public function test_post_creation_with_term_creation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		
		// Ensure the user has the manage_terms capability.
		$user = new \WP_User( $user_id );
		$user->add_cap( 'manage_terms' );

		$input = array(
			'post_type'               => 'post',
			'title'                   => 'Test Post with New Terms',
			'content'                 => 'Test content',
			'tax_input'               => array(
				'category' => array( 'New Test Category' ),
			),
			'create_terms_if_missing' => true,
		);

		$result = CreatePost::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );

		$post_id = $result['id'];

		// Verify terms were created and assigned.
		$post_terms = wp_get_post_terms( $post_id, 'category' );
		$this->assertNotEmpty( $post_terms );
		$this->assertEquals( 'New Test Category', $post_terms[0]->name );

		// Verify term exists independently.
		$term = get_term_by( 'name', 'New Test Category', 'category' );
		$this->assertNotFalse( $term );
	}

	/**
	 * Test post creation with HTML content and block comments.
	 */
	public function test_post_creation_with_block_content(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$block_content = '<!-- wp:paragraph --><p>This is a paragraph block.</p><!-- /wp:paragraph -->' .
						'<!-- wp:heading {"level":2} --><h2>This is a heading</h2><!-- /wp:heading -->';

		$input = array(
			'post_type' => 'post',
			'title'     => 'Test Post with Blocks',
			'content'   => $block_content,
		);

		$result = CreatePost::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );

		$post_id = $result['id'];
		$post    = get_post( $post_id );

		$this->assertEquals( $block_content, $post->post_content );
	}

	/**
	 * Test ability through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input = array(
			'post_type' => 'post',
			'title'     => 'API Test Post',
			'content'   => 'Created via Abilities API',
		);

		$result = $this->execute_ability( 'wpmcp-example/create-post', $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertEquals( 'API Test Post', $result['title'] );

		// Verify post exists.
		$post = get_post( $result['id'] );
		$this->assertNotNull( $post );
		$this->assertEquals( 'API Test Post', $post->post_title );
	}
}
