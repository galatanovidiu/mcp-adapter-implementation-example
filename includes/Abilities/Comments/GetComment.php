<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetComment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-comment',
			array(
				'label'               => 'Get Comment',
				'description'         => 'Retrieve detailed information about a specific WordPress comment.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'comment_id' ),
					'properties' => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => 'The comment ID to retrieve.',
						),
						'include_meta' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include comment metadata. Default: true.',
							'default'     => true,
						),
						'include_replies' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include direct replies to this comment. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'comment_ID', 'comment_content', 'comment_author', 'comment_date' ),
					'properties' => array(
						'comment_ID'           => array( 'type' => 'integer' ),
						'comment_post_ID'      => array( 'type' => 'integer' ),
						'comment_author'       => array( 'type' => 'string' ),
						'comment_author_email' => array( 'type' => 'string' ),
						'comment_author_url'   => array( 'type' => 'string' ),
						'comment_author_IP'    => array( 'type' => 'string' ),
						'comment_date'         => array( 'type' => 'string' ),
						'comment_date_gmt'     => array( 'type' => 'string' ),
						'comment_content'      => array( 'type' => 'string' ),
						'comment_karma'        => array( 'type' => 'integer' ),
						'comment_approved'     => array( 'type' => 'string' ),
						'comment_agent'        => array( 'type' => 'string' ),
						'comment_type'         => array( 'type' => 'string' ),
						'comment_parent'       => array( 'type' => 'integer' ),
						'user_id'              => array( 'type' => 'integer' ),
						'post_info'            => array(
							'type'       => 'object',
							'properties' => array(
								'post_id'    => array( 'type' => 'integer' ),
								'post_title' => array( 'type' => 'string' ),
								'post_url'   => array( 'type' => 'string' ),
								'post_type'  => array( 'type' => 'string' ),
							),
						),
						'author_info'          => array(
							'type'       => 'object',
							'properties' => array(
								'is_registered' => array( 'type' => 'boolean' ),
								'user_login'    => array( 'type' => 'string' ),
								'display_name'  => array( 'type' => 'string' ),
								'avatar_url'    => array( 'type' => 'string' ),
							),
						),
						'comment_url'          => array( 'type' => 'string' ),
						'edit_url'             => array( 'type' => 'string' ),
						'reply_count'          => array( 'type' => 'integer' ),
						'can_edit'             => array( 'type' => 'boolean' ),
						'can_delete'           => array( 'type' => 'boolean' ),
						'can_approve'          => array( 'type' => 'boolean' ),
						'meta'                 => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'key'   => array( 'type' => 'string' ),
									'value' => array( 'type' => 'string' ),
								),
							),
						),
						'replies'              => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'comment_ID'      => array( 'type' => 'integer' ),
									'comment_author'  => array( 'type' => 'string' ),
									'comment_content' => array( 'type' => 'string' ),
									'comment_date'    => array( 'type' => 'string' ),
									'comment_approved' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'engagement', 'comments' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for getting comment details.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the get comment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$comment_id = (int) $input['comment_id'];
		$include_meta = (bool) ( $input['include_meta'] ?? true );
		$include_replies = (bool) ( $input['include_replies'] ?? false );

		// Get the comment
		$comment = \get_comment( $comment_id );

		if ( ! $comment ) {
			return array(
				'error' => array(
					'code'    => 'comment_not_found',
					'message' => 'Comment not found.',
				),
			);
		}

		// Get post information
		$post = \get_post( (int) $comment->comment_post_ID );
		$post_info = array(
			'post_id'    => (int) $comment->comment_post_ID,
			'post_title' => $post ? $post->post_title : '',
			'post_url'   => $post ? \get_permalink( $post->ID ) : '',
			'post_type'  => $post ? $post->post_type : '',
		);

		// Get author information
		$author_info = array(
			'is_registered' => (bool) $comment->user_id,
			'user_login'    => '',
			'display_name'  => $comment->comment_author,
			'avatar_url'    => \get_avatar_url( $comment->comment_author_email, array( 'size' => 96 ) ),
		);

		if ( $comment->user_id ) {
			$user = \get_user_by( 'id', $comment->user_id );
			if ( $user ) {
				$author_info['user_login'] = $user->user_login;
				$author_info['display_name'] = $user->display_name;
			}
		}

		// Get URLs
		$comment_url = \get_comment_link( $comment );
		$edit_url = \admin_url( 'comment.php?action=editcomment&c=' . $comment->comment_ID );

		// Count replies
		$reply_count = \get_comments( array(
			'parent' => $comment->comment_ID,
			'count'  => true,
		) );

		// Check permissions
		$can_edit = \current_user_can( 'edit_comment', $comment->comment_ID );
		$can_delete = \current_user_can( 'delete_comment', $comment->comment_ID );
		$can_approve = \current_user_can( 'moderate_comments' );

		$comment_data = array(
			'comment_ID'           => (int) $comment->comment_ID,
			'comment_post_ID'      => (int) $comment->comment_post_ID,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_author_url'   => $comment->comment_author_url,
			'comment_author_IP'    => $comment->comment_author_IP,
			'comment_date'         => $comment->comment_date,
			'comment_date_gmt'     => $comment->comment_date_gmt,
			'comment_content'      => $comment->comment_content,
			'comment_karma'        => (int) $comment->comment_karma,
			'comment_approved'     => $comment->comment_approved,
			'comment_agent'        => $comment->comment_agent,
			'comment_type'         => $comment->comment_type,
			'comment_parent'       => (int) $comment->comment_parent,
			'user_id'              => (int) $comment->user_id,
			'post_info'            => $post_info,
			'author_info'          => $author_info,
			'comment_url'          => $comment_url,
			'edit_url'             => $edit_url,
			'reply_count'          => (int) $reply_count,
			'can_edit'             => $can_edit,
			'can_delete'           => $can_delete,
			'can_approve'          => $can_approve,
		);

		// Include metadata if requested
		if ( $include_meta ) {
			$meta = \get_comment_meta( (int) $comment->comment_ID );
			$meta_data = array();
			foreach ( $meta as $key => $values ) {
				foreach ( $values as $value ) {
					$meta_data[] = array(
						'key'   => $key,
						'value' => $value,
					);
				}
			}
			$comment_data['meta'] = $meta_data;
		} else {
			$comment_data['meta'] = array();
		}

		// Include replies if requested
		if ( $include_replies ) {
			$replies = \get_comments( array(
				'parent'  => $comment->comment_ID,
				'orderby' => 'comment_date',
				'order'   => 'ASC',
			) );

			$replies_data = array();
			foreach ( $replies as $reply ) {
				$replies_data[] = array(
					'comment_ID'       => (int) $reply->comment_ID,
					'comment_author'   => $reply->comment_author,
					'comment_content'  => $reply->comment_content,
					'comment_date'     => $reply->comment_date,
					'comment_approved' => $reply->comment_approved,
				);
			}
			$comment_data['replies'] = $replies_data;
		} else {
			$comment_data['replies'] = array();
		}

		return $comment_data;
	}
}
