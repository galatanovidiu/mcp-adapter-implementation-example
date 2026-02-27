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
						'status'     => array(
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
						'success'      => array( 'type' => 'boolean' ),
						'comment_id'   => array( 'type' => 'integer' ),
						'old_status'   => array( 'type' => 'string' ),
						'new_status'   => array( 'type' => 'string' ),
						'action_taken' => array( 'type' => 'string' ),
						'comment'      => array(
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
						'message'      => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'engagement',
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Check permission for moderating comments.
	 *
	 * @param array|null $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( ?array $input = null ): bool {
		$input = $input ?? array();
		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the approve comment operation.
	 *
	 * Handles idempotent calls gracefully: if the comment is already in the
	 * requested status, returns success with action_taken = 'no_change'.
	 *
	 * @param array|null $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( ?array $input = null ) {
		$input      = $input ?? array();
		$comment_id = (int) $input['comment_id'];
		$status     = \sanitize_text_field( (string) $input['status'] );

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

		// Map requested status to expected comment_approved DB value for idempotency detection.
		$status_db_map = array(
			'approve' => '1',
			'hold'    => '0',
			'spam'    => 'spam',
			'unspam'  => null, // Target is "not spam" — any non-spam value means already unspammed
			'trash'   => 'trash',
			'untrash' => null, // Target is "not trash" — any non-trash value means already untrashed
		);

		// Check if the comment is already in the requested target status.
		$already_in_target = false;
		if ( array_key_exists( $status, $status_db_map ) ) {
			$target_value = $status_db_map[ $status ];
			if ( null === $target_value ) {
				// For inverse operations, "already done" means NOT in the source status.
				$already_in_target = ( 'unspam' === $status && 'spam' !== $old_status )
					|| ( 'untrash' === $status && 'trash' !== $old_status );
			} else {
				$already_in_target = ( $old_status === $target_value );
			}
		}

		if ( $already_in_target ) {
			return self::build_success_response( $comment_id, $comment, $old_status, $old_status, 'no_change' );
		}

		$action_taken = '';
		$new_status   = '';
		$result       = false;

		// Perform the requested action
		switch ( $status ) {
			case 'approve':
				$result       = \wp_set_comment_status( $comment_id, 'approve' );
				$new_status   = '1';
				$action_taken = 'approved';
				break;

			case 'hold':
				$result       = \wp_set_comment_status( $comment_id, 'hold' );
				$new_status   = '0';
				$action_taken = 'held_for_moderation';
				break;

			case 'spam':
				$result       = \wp_spam_comment( $comment_id );
				$new_status   = 'spam';
				$action_taken = 'marked_as_spam';
				break;

			case 'unspam':
				$result = \wp_unspam_comment( $comment_id );
				// After unspam, comment goes back to its previous status or pending
				$updated_comment = \get_comment( $comment_id );
				$new_status      = $updated_comment ? $updated_comment->comment_approved : '0';
				$action_taken    = 'unmarked_as_spam';
				break;

			case 'trash':
				$result       = \wp_trash_comment( $comment_id );
				$new_status   = 'trash';
				$action_taken = 'moved_to_trash';
				break;

			case 'untrash':
				$result = \wp_untrash_comment( $comment_id );
				// After untrash, comment goes back to its previous status
				$updated_comment = \get_comment( $comment_id );
				$new_status      = $updated_comment ? $updated_comment->comment_approved : '0';
				$action_taken    = 'restored_from_trash';
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

		// Get the updated comment for the response
		$refreshed_comment = \get_comment( $comment_id );
		if ( $refreshed_comment ) {
			$new_status = $refreshed_comment->comment_approved;
		}

		return self::build_success_response( $comment_id, $comment, $old_status, $new_status, $action_taken );
	}

	/**
	 * Build a standardised success response array.
	 *
	 * @param int         $comment_id  The comment ID.
	 * @param \WP_Comment $original    The comment object captured before the operation.
	 * @param string      $old_status  The comment_approved value before the operation.
	 * @param string      $new_status  The comment_approved value after the operation.
	 * @param string      $action_taken The action label (e.g. 'approved', 'no_change').
	 * @return array Success response.
	 */
	private static function build_success_response(
		int $comment_id,
		\WP_Comment $original,
		string $old_status,
		string $new_status,
		string $action_taken
	): array {
		// Fetch the current state of the comment for the response payload.
		$updated_comment = \get_comment( $comment_id );

		if ( ! $updated_comment ) {
			$comment_data = array(
				'comment_ID'       => (int) $original->comment_ID,
				'comment_author'   => $original->comment_author,
				'comment_content'  => $original->comment_content,
				'comment_date'     => $original->comment_date,
				'comment_approved' => $new_status,
				'comment_post_ID'  => (int) $original->comment_post_ID,
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
		}

		$messages = array(
			'approved'            => 'Comment approved successfully.',
			'held_for_moderation' => 'Comment held for moderation.',
			'marked_as_spam'      => 'Comment marked as spam.',
			'unmarked_as_spam'    => 'Comment unmarked as spam.',
			'moved_to_trash'      => 'Comment moved to trash.',
			'restored_from_trash' => 'Comment restored from trash.',
			'no_change'           => 'Comment is already in the requested status.',
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
