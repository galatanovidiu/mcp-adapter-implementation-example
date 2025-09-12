<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeleteUser implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-user',
			array(
				'label'               => 'Delete User',
				'description'         => 'Delete a WordPress user and optionally reassign their content to another user.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'User ID to delete.',
						),
						'reassign_to' => array(
							'type'        => 'integer',
							'description' => 'User ID to reassign content to. If not provided, content will be deleted.',
						),
						'force_delete_content' => array(
							'type'        => 'boolean',
							'description' => 'Force delete all user content instead of reassigning.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'deleted' ),
					'properties' => array(
						'deleted'         => array( 'type' => 'boolean' ),
						'message'         => array( 'type' => 'string' ),
						'content_action'  => array( 'type' => 'string' ),
						'reassigned_to'   => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'users', 'management' ),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.5,
						'readOnlyHint'         => false,
						'destructiveHint'      => true,
						'idempotentHint'       => true,
						'openWorldHint'        => false,
						'requiresConfirmation' => true,
					),
				),
			)
		);
	}

	/**
	 * Check permission for deleting users.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$user_id = (int) ( $input['id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Users cannot delete themselves
		if ( $current_user_id === $user_id ) {
			return false;
		}

		// Check if user has delete_users capability
		if ( ! \current_user_can( 'delete_users' ) ) {
			return false;
		}

		// Additional check: cannot delete users with higher capabilities
		$target_user = \get_user_by( 'ID', $user_id );
		if ( $target_user && ! \current_user_can( 'delete_user', $user_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Execute the delete user operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$user_id = (int) $input['id'];
		$current_user_id = \get_current_user_id();

		// Prevent self-deletion
		if ( $current_user_id === $user_id ) {
			return array(
				'error' => array(
					'code'    => 'cannot_delete_self',
					'message' => 'You cannot delete your own user account.',
				),
			);
		}

		// Check if user exists
		$user = \get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return array(
				'error' => array(
					'code'    => 'user_not_found',
					'message' => 'User not found.',
				),
			);
		}

		// Handle content reassignment
		$reassign_to = null;
		$force_delete_content = ! empty( $input['force_delete_content'] );
		$content_action = 'deleted';

		if ( ! $force_delete_content && ! empty( $input['reassign_to'] ) ) {
			$reassign_to = (int) $input['reassign_to'];
			
			// Validate reassign target user exists
			$reassign_user = \get_user_by( 'ID', $reassign_to );
			if ( ! $reassign_user ) {
				return array(
					'error' => array(
						'code'    => 'reassign_user_not_found',
						'message' => 'User to reassign content to not found.',
					),
				);
			}
			
			// Cannot reassign to the user being deleted
			if ( $reassign_to === $user_id ) {
				return array(
					'error' => array(
						'code'    => 'invalid_reassign_target',
						'message' => 'Cannot reassign content to the user being deleted.',
					),
				);
			}
			
			$content_action = 'reassigned';
		}

		// Check if user has any content that would be affected
		$post_count = \count_user_posts( $user_id );
		$comment_count = \get_comments( array(
			'user_id' => $user_id,
			'count'   => true,
		) );

		// Perform the deletion
		$deleted = \wp_delete_user( $user_id, $reassign_to );

		if ( ! $deleted ) {
			return array(
				'error' => array(
					'code'    => 'deletion_failed',
					'message' => 'Failed to delete user.',
				),
			);
		}

		// Build response message
		$message = "User '{$user->user_login}' deleted successfully.";
		
		if ( $post_count > 0 || $comment_count > 0 ) {
			if ( $content_action === 'reassigned' ) {
				$reassign_user_login = $reassign_user->user_login;
				$message .= " Content ({$post_count} posts, {$comment_count} comments) reassigned to '{$reassign_user_login}'.";
			} else {
				$message .= " Associated content ({$post_count} posts, {$comment_count} comments) was deleted.";
			}
		}

		$response = array(
			'deleted'        => true,
			'message'        => $message,
			'content_action' => $content_action,
		);

		if ( $reassign_to ) {
			$response['reassigned_to'] = $reassign_to;
		}

		return $response;
	}
}
