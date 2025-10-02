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
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'content', 'posts' ),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.6,
						'readOnlyHint'         => false,
						'destructiveHint'      => true,
						'idempotentHint'       => true,
						'openWorldHint'        => false,
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

		$deleted = \wp_delete_post( $post_id, $force );
		if ( false === $deleted ) {
			return new \WP_Error( 'delete_failed', 'Failed to delete the post.' );
		}

		return array(
			'deleted' => true,
		);
	}
}
