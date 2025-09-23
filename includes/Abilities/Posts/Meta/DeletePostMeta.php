<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeletePostMeta implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-post-meta',
			array(
				'label'               => 'Delete Post Meta',
				'description'         => 'Delete a specific meta value or all values for a key on a given post.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id', 'key' ),
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
						'key'        => array(
							'type'        => 'string',
							'description' => 'Meta key to delete.',
						),
						'value'      => array(
							'description' => 'Optional specific value to delete. If omitted and all_values=true (or meta is single), all values will be deleted.',
						),
						'all_values' => array(
							'type'        => 'boolean',
							'description' => 'Delete all values for the given key.',
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
					'public_mcp'  => true,
					'categories' => array( 'content', 'metadata' ),
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
	 * Check permission for deleting post meta.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id = (int) ( $input['id'] ?? 0 );
		$key     = isset( $input['key'] ) ? (string) $input['key'] : '';
		if ( 0 >= $post_id || '' === $key ) {
			return false;
		}
		return \current_user_can( 'edit_post_meta', $post_id, $key );
	}

	/**
	 * Execute the delete post meta operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id = (int) $input['id'];
		$key     = (string) $input['key'];
		$all     = ! empty( $input['all_values'] );

		if ( $all ) {
			$deleted_any = false;
			$values      = (array) \get_post_meta( $post_id, $key, false );
			foreach ( $values as $v ) {
				if ( ! \delete_post_meta( $post_id, $key, $v ) ) {
					continue;
				}

				$deleted_any = true;
			}
			return array( 'deleted' => $deleted_any || empty( $values ) );
		}

		if ( array_key_exists( 'value', $input ) ) {
			return array( 'deleted' => (bool) \delete_post_meta( $post_id, $key, $input['value'] ) );
		}

		$current = \get_post_meta( $post_id, $key, false );
		if ( empty( $current ) ) {
			return array( 'deleted' => true );
		}
		$deleted_any = false;
		foreach ( $current as $v ) {
			if ( ! \delete_post_meta( $post_id, $key, $v ) ) {
				continue;
			}

			$deleted_any = true;
		}
		return array( 'deleted' => $deleted_any );
	}
}
