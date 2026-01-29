<?php
/**
 * Unit tests for CreateComment ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Comments
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\Comments\CreateComment;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test CreateComment ability functionality.
 */
final class CreateCommentTest extends TestCase {

	/**
	 * Test ability registration.
	 */
	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'wpmcp-example/create-comment' );
	}

	/**
	 * Test invalid comment approval status handling.
	 */
	public function test_invalid_comment_approved_returns_error(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->create_test_post(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'comment_status' => 'open',
			)
		);

		$result = CreateComment::execute(
			array(
				'comment_post_ID'      => $post_id,
				'comment_content'      => 'Test comment content.',
				'comment_author'       => 'Test Author',
				'comment_author_email' => 'test@example.com',
				'comment_approved'     => 'invalid',
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['comment_id'] );
		$this->assertSame( 'Invalid comment approval status.', $result['message'] );
	}
}
