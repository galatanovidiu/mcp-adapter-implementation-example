<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateAttachment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-attachment',
			array(
				'label'               => 'Update Attachment',
				'description'         => 'Update attachment metadata including title, caption, description, and alt text.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Attachment ID.',
						),
						'title' => array(
							'type'        => 'string',
							'description' => 'Attachment title.',
						),
						'caption' => array(
							'type'        => 'string',
							'description' => 'Attachment caption.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Attachment description.',
						),
						'alt_text' => array(
							'type'        => 'string',
							'description' => 'Alternative text for images (accessibility).',
						),
						'parent' => array(
							'type'        => 'integer',
							'description' => 'Parent post ID to attach this media to.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id', 'updated_fields' ),
					'properties' => array(
						'id'             => array( 'type' => 'integer' ),
						'updated_fields' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'media', 'attachments' ),
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
	 * Check permission for updating attachments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$attachment_id = (int) ( $input['id'] ?? 0 );
		
		// Check if user can edit this specific attachment
		return \current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Execute the update attachment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$attachment_id = (int) $input['id'];

		// Check if attachment exists
		$attachment = \get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return new \WP_Error( 'attachment_not_found', 'Attachment not found.' );
		}

		$updated_fields = array();
		$post_data = array( 'ID' => $attachment_id );

		// Update title
		if ( array_key_exists( 'title', $input ) ) {
			$post_data['post_title'] = \sanitize_text_field( (string) $input['title'] );
			$updated_fields[] = 'title';
		}

		// Update caption (excerpt)
		if ( array_key_exists( 'caption', $input ) ) {
			$post_data['post_excerpt'] = \sanitize_textarea_field( (string) $input['caption'] );
			$updated_fields[] = 'caption';
		}

		// Update description (content)
		if ( array_key_exists( 'description', $input ) ) {
			$post_data['post_content'] = \sanitize_textarea_field( (string) $input['description'] );
			$updated_fields[] = 'description';
		}

		// Update parent
		if ( array_key_exists( 'parent', $input ) ) {
			$parent_id = (int) $input['parent'];
			
			// Validate parent post exists if not 0
			if ( $parent_id > 0 ) {
				$parent_post = \get_post( $parent_id );
				if ( ! $parent_post ) {
					return new \WP_Error( 'invalid_parent', 'Parent post not found.' );
				}
			}
			
			$post_data['post_parent'] = $parent_id;
			$updated_fields[] = 'parent';
		}

		// Update post data if there are changes
		if ( count( $post_data ) > 1 ) { // More than just ID
			$result = \wp_update_post( $post_data, true );
			if ( \is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update alt text (stored as meta)
		if ( array_key_exists( 'alt_text', $input ) ) {
			$alt_text = \sanitize_text_field( (string) $input['alt_text'] );
			\update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
			$updated_fields[] = 'alt_text';
		}

		$message = count( $updated_fields ) > 0 
			? 'Attachment updated successfully.' 
			: 'No fields were updated.';

		return array(
			'id'             => $attachment_id,
			'updated_fields' => $updated_fields,
			'message'        => $message,
		);
	}
}
