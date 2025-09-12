<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeleteComment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-comment',
			array(
				'label'               => 'Delete Comment',
				'description'         => 'Delete a WordPress comment permanently or move it to trash.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'comment_id' ),
					'properties' => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => 'The comment ID to delete.',
						),
						'force_delete' => array(
							'type'        => 'boolean',
							'description' => 'Whether to permanently delete the comment (true) or move to trash (false). Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'comment_id' ),
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'comment_id' => array( 'type' => 'integer' ),
						'action'     => array( 'type' => 'string' ),
						'comment'    => array(
							'type'       => 'object',
							'properties' => array(
								'comment_ID'      => array( 'type' => 'integer' ),
								'comment_author'  => array( 'type' => 'string' ),
								'comment_content' => array( 'type' => 'string' ),
								'comment_date'    => array( 'type' => 'string' ),
								'comment_post_ID' => array( 'type' => 'integer' ),
							),
						),
						'message'    => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => true,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for deleting comments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$comment_id = (int) ( $input['comment_id'] ?? 0 );
		
		if ( $comment_id > 0 ) {
			return \current_user_can( 'delete_comment', $comment_id );
		}
		
		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the delete comment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$comment_id = (int) $input['comment_id'];
		$force_delete = (bool) ( $input['force_delete'] ?? false );

		// Get the comment before deletion
		$comment = \get_comment( $comment_id );
		if ( ! $comment ) {
			return array(
				'success'    => false,
				'comment_id' => $comment_id,
				'action'     => 'none',
				'message'    => 'Comment not found.',
			);
		}

		// Store comment data for response
		$comment_data = array(
			'comment_ID'      => (int) $comment->comment_ID,
			'comment_author'  => $comment->comment_author,
			'comment_content' => $comment->comment_content,
			'comment_date'    => $comment->comment_date,
			'comment_post_ID' => (int) $comment->comment_post_ID,
		);

		// Determine action based on force_delete flag
		$action = $force_delete ? 'permanently_deleted' : 'moved_to_trash';

		// Delete the comment
		$result = \wp_delete_comment( $comment_id, $force_delete );

		if ( ! $result ) {
			return array(
				'success'    => false,
				'comment_id' => $comment_id,
				'action'     => 'failed',
				'comment'    => $comment_data,
				'message'    => 'Failed to delete comment.',
			);
		}

		$message = $force_delete 
			? 'Comment permanently deleted successfully.' 
			: 'Comment moved to trash successfully.';

		return array(
			'success'    => true,
			'comment_id' => $comment_id,
			'action'     => $action,
			'comment'    => $comment_data,
			'message'    => $message,
		);
	}
}
