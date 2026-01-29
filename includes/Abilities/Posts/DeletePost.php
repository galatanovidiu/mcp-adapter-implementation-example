<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeletePost implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-post',
			array(
				'label'               => 'Delete Post',
				'description'         => 'Delete a WordPress post by ID.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => 'Post ID to delete.',
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Permanently delete (bypass trash).',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'deleted' ),
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.6,
						'readonly'         => false,
						'destructive'      => true,
						'idempotent'       => true,
						'requiresConfirmation' => true,
					),
					'elicitation' => array(
						'message' => 'You are about to delete the post "{post_title}". The post will be {action}. Do you want to continue?',
						'impact'  => 'medium',
						'schema'  => array(
							'type'       => 'object',
							'properties' => array(
								'confirm' => array(
									'type'        => 'boolean',
									'title'       => 'Confirm Deletion',
									'description' => 'Confirm that you want to delete this post',
								),
								'reason'  => array(
									'type'        => 'string',
									'title'       => 'Reason (Optional)',
									'description' => 'Why are you deleting this post?',
									'maxLength'   => 200,
								),
							),
							'required'   => array( 'confirm' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Check permission for deleting a post.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id = (int) ( $input['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return false;
		}
		return \current_user_can( 'delete_post', $post_id );
	}

	/**
	 * Execute the delete post operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id = (int) $input['id'];
		$force   = ! empty( $input['force'] );
		$post    = \get_post( $post_id );
		if ( ! $post ) {
			return array(
				'error' => array(
					'code'    => 'post_not_found',
					'message' => 'Post not found.',
				),
			);
		}

		$deleted = \wp_delete_post( $post_id, $force );
		if ( \is_wp_error( $deleted ) ) {
			return array(
				'error' => array(
					'code'    => $deleted->get_error_code(),
					'message' => $deleted->get_error_message(),
				),
			);
		}
		if ( false === $deleted ) {
			return array(
				'error' => array(
					'code'    => 'delete_failed',
					'message' => $force
						? 'Failed to permanently delete the post.'
						: 'Failed to move the post to the trash.',
				),
			);
		}

		return array(
			'deleted' => true,
		);
	}
}
