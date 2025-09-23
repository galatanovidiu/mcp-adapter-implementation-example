<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetCommentMeta implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-comment-meta',
			array(
				'label'               => 'Get Comment Meta',
				'description'         => 'Retrieve and manage WordPress comment metadata.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'comment_id' ),
					'properties' => array(
						'comment_id' => array(
							'type'        => 'integer',
							'description' => 'The comment ID to get metadata for.',
						),
						'meta_key' => array(
							'type'        => 'string',
							'description' => 'Specific meta key to retrieve. If not provided, returns all metadata.',
						),
						'single' => array(
							'type'        => 'boolean',
							'description' => 'Whether to return a single value (true) or array of values (false). Default: false.',
							'default'     => false,
						),
						'action' => array(
							'type'        => 'string',
							'description' => 'Action to perform: get, add, update, delete. Default: get.',
							'enum'        => array( 'get', 'add', 'update', 'delete' ),
							'default'     => 'get',
						),
						'meta_value' => array(
							'type'        => 'string',
							'description' => 'Meta value for add/update actions.',
						),
						'prev_value' => array(
							'type'        => 'string',
							'description' => 'Previous value for update action (optional).',
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
						'meta_key'   => array( 'type' => 'string' ),
						'meta_data'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'key'   => array( 'type' => 'string' ),
									'value' => array( 'type' => 'string' ),
								),
							),
						),
						'single_value' => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'engagement', 'metadata' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
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
	 * Check permission for managing comment metadata.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$action = $input['action'] ?? 'get';
		
		// Read operations require moderate_comments
		if ( $action === 'get' ) {
			return \current_user_can( 'moderate_comments' );
		}
		
		// Write operations require edit_comment permission
		$comment_id = (int) ( $input['comment_id'] ?? 0 );
		if ( $comment_id > 0 ) {
			return \current_user_can( 'edit_comment', $comment_id );
		}
		
		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the get comment meta operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$comment_id = (int) $input['comment_id'];
		$meta_key = isset( $input['meta_key'] ) ? \sanitize_key( (string) $input['meta_key'] ) : '';
		$single = (bool) ( $input['single'] ?? false );
		$action = \sanitize_text_field( (string) ( $input['action'] ?? 'get' ) );
		$meta_value = isset( $input['meta_value'] ) ? \sanitize_text_field( (string) $input['meta_value'] ) : '';
		$prev_value = isset( $input['prev_value'] ) ? \sanitize_text_field( (string) $input['prev_value'] ) : '';

		// Verify comment exists
		$comment = \get_comment( $comment_id );
		if ( ! $comment ) {
			return array(
				'success'    => false,
				'comment_id' => $comment_id,
				'action'     => $action,
				'message'    => 'Comment not found.',
			);
		}

		$result = false;
		$message = '';
		$meta_data = array();
		$single_value = '';

		switch ( $action ) {
			case 'get':
				if ( ! empty( $meta_key ) ) {
					// Get specific meta key
					$value = \get_comment_meta( $comment_id, $meta_key, $single );
					
					if ( $single ) {
						$single_value = (string) $value;
						$meta_data = array(
							array(
								'key'   => $meta_key,
								'value' => $single_value,
							),
						);
					} else {
						$meta_data = array();
						if ( is_array( $value ) ) {
							foreach ( $value as $v ) {
								$meta_data[] = array(
									'key'   => $meta_key,
									'value' => (string) $v,
								);
							}
						}
					}
					
					$message = 'Comment metadata retrieved successfully.';
					$result = true;
				} else {
					// Get all metadata
					$all_meta = \get_comment_meta( $comment_id );
					$meta_data = array();
					
					foreach ( $all_meta as $key => $values ) {
						foreach ( $values as $value ) {
							$meta_data[] = array(
								'key'   => $key,
								'value' => (string) $value,
							);
						}
					}
					
					$message = 'All comment metadata retrieved successfully.';
					$result = true;
				}
				break;

			case 'add':
				if ( empty( $meta_key ) ) {
					return array(
						'success'    => false,
						'comment_id' => $comment_id,
						'action'     => $action,
						'message'    => 'Meta key is required for add action.',
					);
				}
				
				$result = \add_comment_meta( $comment_id, $meta_key, $meta_value );
				
				if ( $result ) {
					$meta_data = array(
						array(
							'key'   => $meta_key,
							'value' => $meta_value,
						),
					);
					$message = 'Comment metadata added successfully.';
				} else {
					$message = 'Failed to add comment metadata. Key may already exist.';
				}
				break;

			case 'update':
				if ( empty( $meta_key ) ) {
					return array(
						'success'    => false,
						'comment_id' => $comment_id,
						'action'     => $action,
						'message'    => 'Meta key is required for update action.',
					);
				}
				
				if ( ! empty( $prev_value ) ) {
					$result = \update_comment_meta( $comment_id, $meta_key, $meta_value, $prev_value );
				} else {
					$result = \update_comment_meta( $comment_id, $meta_key, $meta_value );
				}
				
				if ( $result ) {
					$meta_data = array(
						array(
							'key'   => $meta_key,
							'value' => $meta_value,
						),
					);
					$message = 'Comment metadata updated successfully.';
				} else {
					$message = 'Failed to update comment metadata. Key may not exist or value unchanged.';
				}
				break;

			case 'delete':
				if ( empty( $meta_key ) ) {
					return array(
						'success'    => false,
						'comment_id' => $comment_id,
						'action'     => $action,
						'message'    => 'Meta key is required for delete action.',
					);
				}
				
				if ( ! empty( $meta_value ) ) {
					$result = \delete_comment_meta( $comment_id, $meta_key, $meta_value );
				} else {
					$result = \delete_comment_meta( $comment_id, $meta_key );
				}
				
				if ( $result ) {
					$message = 'Comment metadata deleted successfully.';
				} else {
					$message = 'Failed to delete comment metadata. Key may not exist.';
				}
				break;

			default:
				return array(
					'success'    => false,
					'comment_id' => $comment_id,
					'action'     => $action,
					'message'    => 'Invalid action specified.',
				);
		}

		return array(
			'success'      => (bool) $result,
			'comment_id'   => $comment_id,
			'action'       => $action,
			'meta_key'     => $meta_key,
			'meta_data'    => $meta_data,
			'single_value' => $single_value,
			'message'      => $message,
		);
	}
}
