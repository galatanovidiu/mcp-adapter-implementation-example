<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateComment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-comment',
			array(
				'label'               => 'Update Comment',
				'description'         => 'Update an existing WordPress comment.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'comment_ID' ),
					'properties' => array(
						'comment_ID'           => array(
							'type'        => 'integer',
							'description' => 'The comment ID to update.',
						),
						'comment_content'      => array(
							'type'        => 'string',
							'description' => 'The updated comment content.',
						),
						'comment_author'       => array(
							'type'        => 'string',
							'description' => 'The updated comment author name.',
						),
						'comment_author_email' => array(
							'type'        => 'string',
							'description' => 'The updated comment author email address.',
						),
						'comment_author_url'   => array(
							'type'        => 'string',
							'description' => 'The updated comment author website URL.',
						),
						'comment_approved'     => array(
							'type'        => 'string',
							'description' => 'Comment approval status (1, 0, spam, trash).',
							'enum'        => array( '1', '0', 'spam', 'trash' ),
						),
						'comment_type'         => array(
							'type'        => 'string',
							'description' => 'Comment type.',
						),
						'comment_parent'       => array(
							'type'        => 'integer',
							'description' => 'Parent comment ID.',
						),
						'comment_meta'         => array(
							'type'                 => 'object',
							'description'          => 'Comment metadata to update as key-value pairs.',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'comment_id' ),
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'comment_id'     => array( 'type' => 'integer' ),
						'comment'        => array(
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
						'updated_fields' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'        => array( 'type' => 'string' ),
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
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for updating comments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$comment_id = (int) ( $input['comment_ID'] ?? 0 );

		if ( $comment_id > 0 ) {
			return \current_user_can( 'edit_comment', $comment_id );
		}

		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the update comment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$comment_id   = (int) $input['comment_ID'];
		$comment_meta = $input['comment_meta'] ?? array();

		// Get the existing comment
		$existing_comment = \get_comment( $comment_id );
		if ( ! $existing_comment ) {
			return array(
				'success'    => false,
				'comment_id' => $comment_id,
				'message'    => 'Comment not found.',
			);
		}

		// Prepare update data
		$update_data = array(
			'comment_ID' => $comment_id,
		);

		$updated_fields = array();

		// Update comment content
		if ( isset( $input['comment_content'] ) ) {
			$comment_content = \sanitize_textarea_field( (string) $input['comment_content'] );
			if ( ! empty( $comment_content ) ) {
				$update_data['comment_content'] = $comment_content;
				$updated_fields[]               = 'comment_content';
			}
		}

		// Update comment author
		if ( isset( $input['comment_author'] ) ) {
			$comment_author = \sanitize_text_field( (string) $input['comment_author'] );
			if ( ! empty( $comment_author ) ) {
				$update_data['comment_author'] = $comment_author;
				$updated_fields[]              = 'comment_author';
			}
		}

		// Update comment author email
		if ( isset( $input['comment_author_email'] ) ) {
			$comment_author_email = \sanitize_email( (string) $input['comment_author_email'] );
			if ( ! empty( $comment_author_email ) && \is_email( $comment_author_email ) ) {
				$update_data['comment_author_email'] = $comment_author_email;
				$updated_fields[]                    = 'comment_author_email';
			}
		}

		// Update comment author URL
		if ( isset( $input['comment_author_url'] ) ) {
			$comment_author_url                = \esc_url_raw( (string) $input['comment_author_url'] );
			$update_data['comment_author_url'] = $comment_author_url;
			$updated_fields[]                  = 'comment_author_url';
		}

		// Update comment approval status
		if ( isset( $input['comment_approved'] ) ) {
			$comment_approved = \sanitize_text_field( (string) $input['comment_approved'] );
			if ( in_array( $comment_approved, array( '1', '0', 'spam', 'trash' ), true ) ) {
				$update_data['comment_approved'] = $comment_approved;
				$updated_fields[]                = 'comment_approved';
			}
		}

		// Update comment type
		if ( isset( $input['comment_type'] ) ) {
			$comment_type                = \sanitize_text_field( (string) $input['comment_type'] );
			$update_data['comment_type'] = $comment_type;
			$updated_fields[]            = 'comment_type';
		}

		// Update comment parent
		if ( isset( $input['comment_parent'] ) ) {
			$comment_parent = (int) $input['comment_parent'];

			// Validate parent comment exists if not 0
			if ( $comment_parent > 0 ) {
				$parent_comment = \get_comment( $comment_parent );
				if ( ! $parent_comment ) {
					return array(
						'success'    => false,
						'comment_id' => $comment_id,
						'message'    => 'Parent comment not found.',
					);
				}

				// Ensure parent comment is on the same post
				if ( (int) $parent_comment->comment_post_ID !== (int) $existing_comment->comment_post_ID ) {
					return array(
						'success'    => false,
						'comment_id' => $comment_id,
						'message'    => 'Parent comment is not on the same post.',
					);
				}
			}

			$update_data['comment_parent'] = $comment_parent;
			$updated_fields[]              = 'comment_parent';
		}

		// Only proceed with update if there are fields to update
		if ( count( $update_data ) === 1 ) { // Only comment_ID is set
			if ( empty( $comment_meta ) ) {
				return array(
					'success'        => true,
					'comment_id'     => $comment_id,
					'comment'        => array(
						'comment_ID'           => (int) $existing_comment->comment_ID,
						'comment_post_ID'      => (int) $existing_comment->comment_post_ID,
						'comment_author'       => $existing_comment->comment_author,
						'comment_author_email' => $existing_comment->comment_author_email,
						'comment_author_url'   => $existing_comment->comment_author_url,
						'comment_content'      => $existing_comment->comment_content,
						'comment_date'         => $existing_comment->comment_date,
						'comment_approved'     => $existing_comment->comment_approved,
						'comment_type'         => $existing_comment->comment_type,
						'comment_parent'       => (int) $existing_comment->comment_parent,
						'user_id'              => (int) $existing_comment->user_id,
						'comment_url'          => \get_comment_link( $existing_comment ),
					),
					'updated_fields' => array(),
					'message'        => 'No fields to update.',
				);
			}
		} else {
			// Update the comment
			$result = \wp_update_comment( $update_data );

			if ( is_wp_error( $result ) ) {
				return array(
					'success'    => false,
					'comment_id' => $comment_id,
					'message'    => 'Failed to update comment: ' . $result->get_error_message(),
				);
			}

			if ( ! $result ) {
				return array(
					'success'    => false,
					'comment_id' => $comment_id,
					'message'    => 'Failed to update comment.',
				);
			}
		}

		// Update comment metadata
		if ( ! empty( $comment_meta ) && is_array( $comment_meta ) ) {
			foreach ( $comment_meta as $key => $value ) {
				$sanitized_key   = \sanitize_key( $key );
				$sanitized_value = \sanitize_text_field( $value );
				\update_comment_meta( $comment_id, $sanitized_key, $sanitized_value );
				$updated_fields[] = "meta:{$sanitized_key}";
			}
		}

		// Get the updated comment
		$updated_comment = \get_comment( $comment_id );

		// Prepare response
		$comment_data = array(
			'comment_ID'           => (int) $updated_comment->comment_ID,
			'comment_post_ID'      => (int) $updated_comment->comment_post_ID,
			'comment_author'       => $updated_comment->comment_author,
			'comment_author_email' => $updated_comment->comment_author_email,
			'comment_author_url'   => $updated_comment->comment_author_url,
			'comment_content'      => $updated_comment->comment_content,
			'comment_date'         => $updated_comment->comment_date,
			'comment_approved'     => $updated_comment->comment_approved,
			'comment_type'         => $updated_comment->comment_type,
			'comment_parent'       => (int) $updated_comment->comment_parent,
			'user_id'              => (int) $updated_comment->user_id,
			'comment_url'          => \get_comment_link( $updated_comment ),
		);

		return array(
			'success'        => true,
			'comment_id'     => $comment_id,
			'comment'        => $comment_data,
			'updated_fields' => $updated_fields,
			'message'        => 'Comment updated successfully.',
		);
	}
}
