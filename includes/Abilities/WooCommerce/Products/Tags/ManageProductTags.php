<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Tags;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ManageProductTags implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/manage-product-tags',
			array(
				'label'               => 'Manage Product Tags',
				'description'         => 'Create, update, or delete WooCommerce product tags with batch operations support.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'operation' ),
					'properties'           => array(
						'operation'        => array(
							'type'        => 'string',
							'description' => 'Operation to perform.',
							'enum'        => array( 'create', 'update', 'delete', 'batch' ),
						),
						'tag_data'         => array(
							'type'        => 'object',
							'description' => 'Tag data for create/update operations.',
							'properties'  => array(
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
							),
						),
						'tag_id'           => array(
							'type'        => 'integer',
							'description' => 'Tag ID for update/delete operations.',
							'minimum'     => 1,
						),
						'batch_operations' => array(
							'type'        => 'array',
							'description' => 'Multiple operations for batch processing.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'operation' ),
								'properties' => array(
									'operation' => array(
										'type' => 'string',
										'enum' => array( 'create', 'update', 'delete' ),
									),
									'tag_data'  => array( 'type' => 'object' ),
									'tag_id'    => array( 'type' => 'integer' ),
								),
							),
						),
						'force_delete'     => array(
							'type'        => 'boolean',
							'description' => 'Force delete tags even if they have products.',
							'default'     => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'operation'     => array( 'type' => 'string' ),
						'tag'           => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'count'       => array( 'type' => 'integer' ),
								'link'        => array( 'type' => 'string' ),
							),
						),
						'batch_results' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'operation' => array( 'type' => 'string' ),
									'success'   => array( 'type' => 'boolean' ),
									'tag_id'    => array( 'type' => 'integer' ),
									'message'   => array( 'type' => 'string' ),
								),
							),
						),
						'changes_made'  => array( 'type' => 'array' ),
						'message'       => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'ecommerce',
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

	public static function check_permission(): bool {
		return current_user_can( 'manage_product_terms' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'       => false,
				'operation'     => $input['operation'],
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'WooCommerce is not active.',
			);
		}

		$operation = $input['operation'];

		switch ( $operation ) {
			case 'create':
				return self::create_tag( $input );

			case 'update':
				return self::update_tag( $input );

			case 'delete':
				return self::delete_tag( $input );

			case 'batch':
				return self::batch_operations( $input );

			default:
				return array(
					'success'       => false,
					'operation'     => $operation,
					'tag'           => null,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Invalid operation specified.',
				);
		}
	}

	private static function create_tag( array $input ): array {
		$tag_data = $input['tag_data'] ?? array();

		if ( empty( $tag_data['name'] ) ) {
			return array(
				'success'       => false,
				'operation'     => 'create',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag name is required.',
			);
		}

		$name        = $tag_data['name'];
		$slug        = $tag_data['slug'] ?? sanitize_title( $name );
		$description = $tag_data['description'] ?? '';

		try {
			$result = wp_insert_term(
				$name,
				'product_tag',
				array(
					'slug'        => $slug,
					'description' => $description,
				)
			);

			if ( is_wp_error( $result ) ) {
				return array(
					'success'       => false,
					'operation'     => 'create',
					'tag'           => null,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error creating tag: ' . $result->get_error_message(),
				);
			}

			$created_tag = get_term( $result['term_id'], 'product_tag' );

			return array(
				'success'       => true,
				'operation'     => 'create',
				'tag'           => array(
					'id'          => $created_tag->term_id,
					'name'        => $created_tag->name,
					'slug'        => $created_tag->slug,
					'description' => $created_tag->description,
					'count'       => $created_tag->count,
					'link'        => get_term_link( $created_tag ),
				),
				'batch_results' => array(),
				'changes_made'  => array( 'created' ),
				'message'       => sprintf( 'Successfully created tag "%s".', $name ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'operation'     => 'create',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Error creating tag: ' . $e->getMessage(),
			);
		}
	}

	private static function update_tag( array $input ): array {
		$tag_id   = $input['tag_id'] ?? 0;
		$tag_data = $input['tag_data'] ?? array();

		if ( empty( $tag_id ) ) {
			return array(
				'success'       => false,
				'operation'     => 'update',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag ID is required for update operation.',
			);
		}

		$tag = get_term( $tag_id, 'product_tag' );
		if ( is_wp_error( $tag ) || ! $tag ) {
			return array(
				'success'       => false,
				'operation'     => 'update',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag not found.',
			);
		}

		$changes_made = array();
		$update_args  = array();

		if ( isset( $tag_data['name'] ) ) {
			$update_args['name'] = $tag_data['name'];
			$changes_made[]      = 'name';
		}

		if ( isset( $tag_data['slug'] ) ) {
			$update_args['slug'] = $tag_data['slug'];
			$changes_made[]      = 'slug';
		}

		if ( isset( $tag_data['description'] ) ) {
			$update_args['description'] = $tag_data['description'];
			$changes_made[]             = 'description';
		}

		if ( empty( $update_args ) ) {
			return array(
				'success'       => false,
				'operation'     => 'update',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'No update data provided.',
			);
		}

		try {
			$result = wp_update_term( $tag_id, 'product_tag', $update_args );

			if ( is_wp_error( $result ) ) {
				return array(
					'success'       => false,
					'operation'     => 'update',
					'tag'           => null,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error updating tag: ' . $result->get_error_message(),
				);
			}

			$updated_tag = get_term( $tag_id, 'product_tag' );

			return array(
				'success'       => true,
				'operation'     => 'update',
				'tag'           => array(
					'id'          => $updated_tag->term_id,
					'name'        => $updated_tag->name,
					'slug'        => $updated_tag->slug,
					'description' => $updated_tag->description,
					'count'       => $updated_tag->count,
					'link'        => get_term_link( $updated_tag ),
				),
				'batch_results' => array(),
				'changes_made'  => $changes_made,
				'message'       => sprintf( 'Successfully updated tag "%s". Changes: %s', $updated_tag->name, implode( ', ', $changes_made ) ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'operation'     => 'update',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Error updating tag: ' . $e->getMessage(),
			);
		}
	}

	private static function delete_tag( array $input ): array {
		$tag_id       = $input['tag_id'] ?? 0;
		$force_delete = $input['force_delete'] ?? false;

		if ( empty( $tag_id ) ) {
			return array(
				'success'       => false,
				'operation'     => 'delete',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag ID is required for delete operation.',
			);
		}

		$tag = get_term( $tag_id, 'product_tag' );
		if ( is_wp_error( $tag ) || ! $tag ) {
			return array(
				'success'       => false,
				'operation'     => 'delete',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag not found.',
			);
		}

		// Check if tag has products and force_delete is false
		if ( ! $force_delete && $tag->count > 0 ) {
			return array(
				'success'       => false,
				'operation'     => 'delete',
				'tag'           => array(
					'id'    => $tag->term_id,
					'name'  => $tag->name,
					'count' => $tag->count,
				),
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => sprintf( 'Tag "%s" has %d products. Use force_delete to delete anyway.', $tag->name, $tag->count ),
			);
		}

		$tag_info = array(
			'id'          => $tag->term_id,
			'name'        => $tag->name,
			'slug'        => $tag->slug,
			'description' => $tag->description,
			'count'       => $tag->count,
			'link'        => get_term_link( $tag ),
		);

		try {
			$result = wp_delete_term( $tag_id, 'product_tag' );

			if ( is_wp_error( $result ) || ! $result ) {
				return array(
					'success'       => false,
					'operation'     => 'delete',
					'tag'           => $tag_info,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error deleting tag.',
				);
			}

			return array(
				'success'       => true,
				'operation'     => 'delete',
				'tag'           => $tag_info,
				'batch_results' => array(),
				'changes_made'  => array( 'deleted' ),
				'message'       => sprintf( 'Successfully deleted tag "%s".', $tag_info['name'] ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'operation'     => 'delete',
				'tag'           => $tag_info,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Error deleting tag: ' . $e->getMessage(),
			);
		}
	}

	private static function batch_operations( array $input ): array {
		$operations = $input['batch_operations'] ?? array();

		if ( empty( $operations ) ) {
			return array(
				'success'       => false,
				'operation'     => 'batch',
				'tag'           => null,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'No batch operations provided.',
			);
		}

		$batch_results = array();
		$total_success = 0;
		$total_errors  = 0;

		foreach ( $operations as $op ) {
			$op_input = array(
				'operation'    => $op['operation'],
				'tag_data'     => $op['tag_data'] ?? array(),
				'tag_id'       => $op['tag_id'] ?? 0,
				'force_delete' => $input['force_delete'] ?? false,
			);

			$result = null;
			switch ( $op['operation'] ) {
				case 'create':
					$result = self::create_tag( $op_input );
					break;
				case 'update':
					$result = self::update_tag( $op_input );
					break;
				case 'delete':
					$result = self::delete_tag( $op_input );
					break;
			}

			if ( ! $result ) {
				continue;
			}

			$batch_results[] = array(
				'operation' => $op['operation'],
				'success'   => $result['success'],
				'tag_id'    => $result['tag'] ? $result['tag']['id'] : 0,
				'message'   => $result['message'],
			);

			if ( $result['success'] ) {
				++$total_success;
			} else {
				++$total_errors;
			}
		}

		return array(
			'success'       => $total_success > 0,
			'operation'     => 'batch',
			'tag'           => array(),
			'batch_results' => $batch_results,
			'changes_made'  => array( 'batch_processed' ),
			'message'       => sprintf(
				'Batch operation completed. %d successful, %d errors.',
				$total_success,
				$total_errors
			),
		);
	}
}
