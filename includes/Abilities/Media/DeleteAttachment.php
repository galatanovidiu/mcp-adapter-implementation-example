<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeleteAttachment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-attachment',
			array(
				'label'               => 'Delete Attachment',
				'description'         => 'Delete a media attachment and its associated files.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'           => array(
							'type'        => 'integer',
							'description' => 'Attachment ID to delete.',
						),
						'force_delete' => array(
							'type'        => 'boolean',
							'description' => 'Bypass trash and permanently delete the attachment.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'deleted' ),
					'properties' => array(
						'deleted'       => array( 'type' => 'boolean' ),
						'message'       => array( 'type' => 'string' ),
						'files_deleted' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'media',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
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
	 * Check permission for deleting attachments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$attachment_id = (int) ( $input['id'] ?? 0 );

		// Check if user can delete this specific attachment
		return \current_user_can( 'delete_post', $attachment_id );
	}

	/**
	 * Execute the delete attachment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$attachment_id = (int) $input['id'];
		$force_delete  = ! empty( $input['force_delete'] );

		// Check if attachment exists
		$attachment = \get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return new \WP_Error( 'attachment_not_found', 'Attachment not found.' );
		}

		// Get file information before deletion
		$file_path       = \get_attached_file( $attachment_id );
		$metadata        = \wp_get_attachment_metadata( $attachment_id );
		$files_to_delete = array();

		// Collect all files that will be deleted
		if ( $file_path ) {
			$files_to_delete[] = \basename( $file_path );

			// Add image size files if it's an image
			if ( \wp_attachment_is_image( $attachment_id ) && $metadata && isset( $metadata['sizes'] ) ) {
				$upload_dir = \wp_upload_dir();
				$file_dir   = \dirname( $file_path );

				foreach ( $metadata['sizes'] as $size => $size_data ) {
					if ( ! isset( $size_data['file'] ) ) {
						continue;
					}

					$size_file = $file_dir . '/' . $size_data['file'];
					if ( ! \file_exists( $size_file ) ) {
						continue;
					}

					$files_to_delete[] = \basename( $size_file );
				}
			}
		}

		// Delete the attachment
		$deleted = \wp_delete_attachment( $attachment_id, $force_delete );

		if ( ! $deleted ) {
			return new \WP_Error( 'deletion_failed', 'Failed to delete attachment.' );
		}

		$message = $force_delete
			? "Attachment '{$attachment->post_title}' permanently deleted."
			: "Attachment '{$attachment->post_title}' moved to trash.";

		if ( ! empty( $files_to_delete ) ) {
			$file_count = count( $files_to_delete );
			$message   .= " {$file_count} file(s) removed from server.";
		}

		return array(
			'deleted'       => true,
			'message'       => $message,
			'files_deleted' => $files_to_delete,
		);
	}
}
