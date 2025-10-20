<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class CreateComment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/create-comment',
			array(
				'label'               => 'Create Comment',
				'description'         => 'Create a new WordPress comment on a post.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'comment_post_ID', 'comment_content', 'comment_author', 'comment_author_email' ),
					'properties' => array(
						'comment_post_ID'      => array(
							'type'        => 'integer',
							'description' => 'The post ID to comment on.',
						),
						'comment_content'      => array(
							'type'        => 'string',
							'description' => 'The comment content.',
						),
						'comment_author'       => array(
							'type'        => 'string',
							'description' => 'The comment author name.',
						),
						'comment_author_email' => array(
							'type'        => 'string',
							'description' => 'The comment author email address.',
						),
						'comment_author_url'   => array(
							'type'        => 'string',
							'description' => 'The comment author website URL.',
						),
						'comment_parent'       => array(
							'type'        => 'integer',
							'description' => 'Parent comment ID for replies. Default: 0.',
							'default'     => 0,
						),
						'user_id'              => array(
							'type'        => 'integer',
							'description' => 'User ID if comment is from a registered user. Default: 0.',
							'default'     => 0,
						),
						'comment_approved'     => array(
							'type'        => 'string',
							'description' => 'Comment approval status (1, 0, spam, trash). Default: 0 (pending).',
							'enum'        => array( '1', '0', 'spam', 'trash' ),
							'default'     => '0',
						),
						'comment_type'         => array(
							'type'        => 'string',
							'description' => 'Comment type. Default: comment.',
							'default'     => 'comment',
						),
						'comment_meta'         => array(
							'type'                 => 'object',
							'description'          => 'Comment metadata as key-value pairs.',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'comment_id' ),
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'comment_id' => array( 'type' => 'integer' ),
						'comment'    => array(
							'type'       => 'object',
							'properties' => array(
								'comment_ID'           => array( 'type' => 'integer' ),
								'comment_post_ID'      => array( 'type' => 'integer' ),
								'comment_author'       => array( 'type' => 'string' ),
								'comment_author_email' => array( 'type' => 'string' ),
								'comment_author_url'   => array( 'type' => 'string' ),
								'comment_content'      => array( 'type' => 'string' ),
								'comment_date'         => array( 'type' => 'string' ),
								'comment_approved'     => array( 'type' => 'string' ),
								'comment_type'         => array( 'type' => 'string' ),
								'comment_parent'       => array( 'type' => 'integer' ),
								'user_id'              => array( 'type' => 'integer' ),
								'comment_url'          => array( 'type' => 'string' ),
							),
						),
						'message'    => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'engagement',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => true,
					),
				),
			)
		);
	}

	/**
	 * Check permission for creating comments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		// Check if user can publish comments or moderate comments
		return \current_user_can( 'publish_posts' ) || \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the create comment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$comment_post_ID      = (int) $input['comment_post_ID'];
		$comment_content      = \sanitize_textarea_field( (string) $input['comment_content'] );
		$comment_author       = \sanitize_text_field( (string) $input['comment_author'] );
		$comment_author_email = \sanitize_email( (string) $input['comment_author_email'] );
		$comment_author_url   = \esc_url_raw( (string) ( $input['comment_author_url'] ?? '' ) );
		$comment_parent       = (int) ( $input['comment_parent'] ?? 0 );
		$user_id              = (int) ( $input['user_id'] ?? 0 );
		$comment_approved     = \sanitize_text_field( (string) ( $input['comment_approved'] ?? '0' ) );
		$comment_type         = \sanitize_text_field( (string) ( $input['comment_type'] ?? 'comment' ) );
		$comment_meta         = $input['comment_meta'] ?? array();

		// Validate required fields
		if ( empty( $comment_content ) ) {
			return array(
				'success'    => false,
				'comment_id' => 0,
				'message'    => 'Comment content is required.',
			);
		}

		if ( empty( $comment_author ) ) {
			return array(
				'success'    => false,
				'comment_id' => 0,
				'message'    => 'Comment author name is required.',
			);
		}

		if ( empty( $comment_author_email ) || ! \is_email( $comment_author_email ) ) {
			return array(
				'success'    => false,
				'comment_id' => 0,
				'message'    => 'Valid comment author email is required.',
			);
		}

		// Check if post exists
		$post = \get_post( $comment_post_ID );
		if ( ! $post ) {
			return array(
				'success'    => false,
				'comment_id' => 0,
				'message'    => 'Post not found.',
			);
		}

		// Check if post allows comments
		if ( ! \comments_open( $comment_post_ID ) ) {
			return array(
				'success'    => false,
				'comment_id' => 0,
				'message'    => 'Comments are not allowed on this post.',
			);
		}

		// Check if parent comment exists (for replies)
		if ( $comment_parent > 0 ) {
			$parent_comment = \get_comment( $comment_parent );
			if ( ! $parent_comment ) {
				return array(
					'success'    => false,
					'comment_id' => 0,
					'message'    => 'Parent comment not found.',
				);
			}

			// Ensure parent comment is on the same post
			if ( (int) $parent_comment->comment_post_ID !== $comment_post_ID ) {
				return array(
					'success'    => false,
					'comment_id' => 0,
					'message'    => 'Parent comment is not on the specified post.',
				);
			}
		}

		// Validate user ID if provided
		if ( $user_id > 0 ) {
			$user = \get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return array(
					'success'    => false,
					'comment_id' => 0,
					'message'    => 'User not found.',
				);
			}
		}

		// Prepare comment data
		$comment_data = array(
			'comment_post_ID'      => $comment_post_ID,
			'comment_content'      => $comment_content,
			'comment_author'       => $comment_author,
			'comment_author_email' => $comment_author_email,
			'comment_author_url'   => $comment_author_url,
			'comment_parent'       => $comment_parent,
			'user_id'              => $user_id,
			'comment_approved'     => $comment_approved,
			'comment_type'         => $comment_type,
			'comment_author_IP'    => \wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'comment_agent'        => \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
			'comment_date'         => \current_time( 'mysql' ),
			'comment_date_gmt'     => \current_time( 'mysql', 1 ),
		);

		// Insert the comment
		$comment_id = \wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			return array(
				'success'    => false,
				'comment_id' => 0,
				'message'    => 'Failed to create comment.',
			);
		}

		// Add comment metadata
		if ( ! empty( $comment_meta ) && is_array( $comment_meta ) ) {
			foreach ( $comment_meta as $key => $value ) {
				\add_comment_meta( $comment_id, \sanitize_key( $key ), \sanitize_text_field( $value ) );
			}
		}

		// Get the created comment
		$comment = \get_comment( $comment_id );

		// Prepare response
		$comment_data = array(
			'comment_ID'           => (int) $comment->comment_ID,
			'comment_post_ID'      => (int) $comment->comment_post_ID,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_author_url'   => $comment->comment_author_url,
			'comment_content'      => $comment->comment_content,
			'comment_date'         => $comment->comment_date,
			'comment_approved'     => $comment->comment_approved,
			'comment_type'         => $comment->comment_type,
			'comment_parent'       => (int) $comment->comment_parent,
			'user_id'              => (int) $comment->user_id,
			'comment_url'          => \get_comment_link( $comment ),
		);

		return array(
			'success'    => true,
			'comment_id' => $comment_id,
			'comment'    => $comment_data,
			'message'    => 'Comment created successfully.',
		);
	}
}
