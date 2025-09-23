<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ApproveComment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/approve-comment',
			array(
				'label'               => 'Approve Comment',
				'description'         => 'Moderate WordPress comments by changing their approval status.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'comment_id', 'status' ),
					'properties' => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => 'The comment ID to moderate.',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'The approval status to set.',
							'enum'        => array( 'approve', 'hold', 'spam', 'unspam', 'trash', 'untrash' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'comment_id' ),
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'comment_id'  => array( 'type' => 'integer' ),
						'old_status'  => array( 'type' => 'string' ),
						'new_status'  => array( 'type' => 'string' ),
						'action_taken' => array( 'type' => 'string' ),
						'comment'     => array(
							'type'       => 'object',
							'properties' => array(
								'comment_ID'       => array( 'type' => 'integer' ),
								'comment_author'   => array( 'type' => 'string' ),
								'comment_content'  => array( 'type' => 'string' ),
								'comment_date'     => array( 'type' => 'string' ),
								'comment_approved' => array( 'type' => 'string' ),
								'comment_post_ID'  => array( 'type' => 'integer' ),
								'comment_url'      => array( 'type' => 'string' ),
							),
						),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'engagement', 'moderation' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for moderating comments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the approve comment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$comment_id = (int) $input['comment_id'];
		$status = \sanitize_text_field( (string) $input['status'] );

		// Get the comment
		$comment = \get_comment( $comment_id );
		if ( ! $comment ) {
			return array(
				'success'    => false,
				'comment_id' => $comment_id,
				'message'    => 'Comment not found.',
			);
		}

		$old_status = $comment->comment_approved;
		$action_taken = '';
		$new_status = '';
		$result = false;

		// Perform the requested action
		switch ( $status ) {
			case 'approve':
				$result = \wp_set_comment_status( $comment_id, 'approve' );
				$new_status = '1';
				$action_taken = 'approved';
				break;

			case 'hold':
				$result = \wp_set_comment_status( $comment_id, 'hold' );
				$new_status = '0';
				$action_taken = 'held_for_moderation';
				break;

			case 'spam':
				$result = \wp_spam_comment( $comment_id );
				$new_status = 'spam';
				$action_taken = 'marked_as_spam';
				break;

			case 'unspam':
				$result = \wp_unspam_comment( $comment_id );
				// After unspam, comment goes back to its previous status or pending
				$updated_comment = \get_comment( $comment_id );
				$new_status = $updated_comment ? $updated_comment->comment_approved : '0';
				$action_taken = 'unmarked_as_spam';
				break;

			case 'trash':
				$result = \wp_trash_comment( $comment_id );
				$new_status = 'trash';
				$action_taken = 'moved_to_trash';
				break;

			case 'untrash':
				$result = \wp_untrash_comment( $comment_id );
				// After untrash, comment goes back to its previous status
				$updated_comment = \get_comment( $comment_id );
				$new_status = $updated_comment ? $updated_comment->comment_approved : '0';
				$action_taken = 'restored_from_trash';
				break;

			default:
				return array(
					'success'    => false,
					'comment_id' => $comment_id,
					'message'    => 'Invalid status provided.',
				);
		}

		if ( ! $result ) {
			return array(
				'success'    => false,
				'comment_id' => $comment_id,
				'old_status' => $old_status,
				'message'    => 'Failed to update comment status.',
			);
		}

		// Get the updated comment
		$updated_comment = \get_comment( $comment_id );
		if ( ! $updated_comment ) {
			// Comment might have been permanently deleted
			$comment_data = array(
				'comment_ID'       => (int) $comment->comment_ID,
				'comment_author'   => $comment->comment_author,
				'comment_content'  => $comment->comment_content,
				'comment_date'     => $comment->comment_date,
				'comment_approved' => $new_status,
				'comment_post_ID'  => (int) $comment->comment_post_ID,
				'comment_url'      => '',
			);
		} else {
			$comment_data = array(
				'comment_ID'       => (int) $updated_comment->comment_ID,
				'comment_author'   => $updated_comment->comment_author,
				'comment_content'  => $updated_comment->comment_content,
				'comment_date'     => $updated_comment->comment_date,
				'comment_approved' => $updated_comment->comment_approved,
				'comment_post_ID'  => (int) $updated_comment->comment_post_ID,
				'comment_url'      => \get_comment_link( $updated_comment ),
			);
			$new_status = $updated_comment->comment_approved;
		}

		// Generate appropriate message
		$messages = array(
			'approved'             => 'Comment approved successfully.',
			'held_for_moderation'  => 'Comment held for moderation.',
			'marked_as_spam'       => 'Comment marked as spam.',
			'unmarked_as_spam'     => 'Comment unmarked as spam.',
			'moved_to_trash'       => 'Comment moved to trash.',
			'restored_from_trash'  => 'Comment restored from trash.',
		);

		$message = $messages[ $action_taken ] ?? 'Comment status updated successfully.';

		return array(
			'success'      => true,
			'comment_id'   => $comment_id,
			'old_status'   => $old_status,
			'new_status'   => $new_status,
			'action_taken' => $action_taken,
			'comment'      => $comment_data,
			'message'      => $message,
		);
	}
}
