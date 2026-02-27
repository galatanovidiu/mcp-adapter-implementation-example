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
								'if_exists'   => array(
									'type'        => 'string',
									'description' => 'How to handle an existing tag (error, use_existing, create_duplicate).',
									'enum'        => array( 'error', 'use_existing', 'create_duplicate' ),
									'default'     => 'error',
								),
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
						'exists'        => array( 'type' => 'boolean' ),
						'action'        => array( 'type' => 'string' ),
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
							'exists'    => array( 'type' => 'boolean' ),
							'action'    => array( 'type' => 'string' ),
							'tag_id'    => array( 'type' => 'integer' ),
							'tag'       => array(
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
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
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
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
					'exists'        => false,
					'action'        => 'error',
					'tag'           => self::empty_tag_payload(),
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Invalid operation specified.',
				);
		}
	}

	private static function create_tag( array $input ): array {
		$tag_data = $input['tag_data'] ?? array();

		$name      = isset( $tag_data['name'] ) ? sanitize_text_field( (string) $tag_data['name'] ) : '';
		$if_exists = self::normalize_if_exists( $tag_data['if_exists'] ?? 'error' );

		if ( '' === $name ) {
			return array(
				'success'       => false,
				'operation'     => 'create',
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag name is required.',
			);
		}

		$slug        = isset( $tag_data['slug'] ) ? sanitize_title( (string) $tag_data['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}
		$description = isset( $tag_data['description'] ) ? sanitize_textarea_field( (string) $tag_data['description'] ) : '';

		$existing_tag = self::find_existing_tag( $slug, $name );
		if ( $existing_tag ) {
			$existing_payload = self::build_tag_payload( $existing_tag );
			if ( 'use_existing' === $if_exists ) {
				return array(
					'success'       => true,
					'operation'     => 'create',
					'exists'        => true,
					'action'        => 'existing',
					'tag'           => $existing_payload,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Tag already exists. Using existing tag.',
				);
			}
			if ( 'create_duplicate' !== $if_exists ) {
				return array(
					'success'       => false,
					'operation'     => 'create',
					'exists'        => true,
					'action'        => 'exists',
					'tag'           => $existing_payload,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Tag already exists. Set if_exists to use_existing or create_duplicate.',
				);
			}

			$slug = self::unique_term_slug( $slug !== '' ? $slug : $name, 'product_tag' );
		}

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
					'exists'        => false,
					'action'        => 'error',
					'tag'           => self::empty_tag_payload(),
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error creating tag: ' . $result->get_error_message(),
				);
			}

			$created_tag = get_term( $result['term_id'], 'product_tag' );
			if ( ! $created_tag || is_wp_error( $created_tag ) ) {
				return array(
					'success'       => false,
					'operation'     => 'create',
					'exists'        => false,
					'action'        => 'error',
					'tag'           => self::empty_tag_payload(),
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error retrieving created tag.',
				);
			}

			$action = $existing_tag ? 'duplicate' : 'created';
			return array(
				'success'       => true,
				'operation'     => 'create',
				'exists'        => (bool) $existing_tag,
				'action'        => $action,
				'tag'           => self::build_tag_payload( $created_tag ),
				'batch_results' => array(),
				'changes_made'  => array( 'created' ),
				'message'       => sprintf( 'Successfully %s tag "%s".', $action === 'duplicate' ? 'duplicated' : 'created', $name ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'operation'     => 'create',
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Tag not found.',
			);
		}

		$changes_made = array();
		$update_args  = array();

		if ( isset( $tag_data['name'] ) ) {
			$update_args['name'] = sanitize_text_field( (string) $tag_data['name'] );
			$changes_made[]      = 'name';
		}

		if ( isset( $tag_data['slug'] ) ) {
			$update_args['slug'] = sanitize_title( (string) $tag_data['slug'] );
			$changes_made[]      = 'slug';
		}

		if ( isset( $tag_data['description'] ) ) {
			$update_args['description'] = sanitize_textarea_field( (string) $tag_data['description'] );
			$changes_made[]             = 'description';
		}

		if ( empty( $update_args ) ) {
			return array(
				'success'       => false,
				'operation'     => 'update',
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
					'exists'        => false,
					'action'        => 'error',
					'tag'           => self::empty_tag_payload(),
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error updating tag: ' . $result->get_error_message(),
				);
			}

			$updated_tag = get_term( $tag_id, 'product_tag' );

			return array(
				'success'       => true,
				'operation'     => 'update',
				'exists'        => false,
				'action'        => 'updated',
				'tag'           => self::build_tag_payload( $updated_tag ),
				'batch_results' => array(),
				'changes_made'  => $changes_made,
				'message'       => sprintf( 'Successfully updated tag "%s". Changes: %s', $updated_tag->name, implode( ', ', $changes_made ) ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'operation'     => 'update',
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
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
				'exists'        => true,
				'action'        => 'blocked',
				'tag'           => self::build_tag_payload( $tag ),
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => sprintf( 'Tag "%s" has %d products. Use force_delete to delete anyway.', $tag->name, $tag->count ),
			);
		}

		$tag_info = self::build_tag_payload( $tag );

		try {
			$result = wp_delete_term( $tag_id, 'product_tag' );

			if ( is_wp_error( $result ) || ! $result ) {
				return array(
					'success'       => false,
					'operation'     => 'delete',
					'exists'        => false,
					'action'        => 'error',
					'tag'           => $tag_info,
					'batch_results' => array(),
					'changes_made'  => array(),
					'message'       => 'Error deleting tag.',
				);
			}

			return array(
				'success'       => true,
				'operation'     => 'delete',
				'exists'        => true,
				'action'        => 'deleted',
				'tag'           => $tag_info,
				'batch_results' => array(),
				'changes_made'  => array( 'deleted' ),
				'message'       => sprintf( 'Successfully deleted tag "%s".', $tag_info['name'] ),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'operation'     => 'delete',
				'exists'        => false,
				'action'        => 'error',
				'tag'           => $tag_info,
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'Error deleting tag: ' . $e->getMessage(),
			);
		}
	}

	private static function normalize_if_exists( $value ): string {
		$value = sanitize_key( (string) $value );
		$allowed = array( 'error', 'use_existing', 'create_duplicate' );
		return in_array( $value, $allowed, true ) ? $value : 'error';
	}

	private static function find_existing_tag( string $slug, string $name ): ?\WP_Term {
		$term_id = 0;
		if ( $slug !== '' ) {
			$term_exists = term_exists( $slug, 'product_tag' );
			$term_id     = is_array( $term_exists ) ? (int) $term_exists['term_id'] : (int) $term_exists;
		}
		if ( ! $term_id && $name !== '' ) {
			$term_exists = term_exists( $name, 'product_tag' );
			$term_id     = is_array( $term_exists ) ? (int) $term_exists['term_id'] : (int) $term_exists;
		}
		if ( ! $term_id ) {
			return null;
		}
		$term = get_term( $term_id, 'product_tag' );
		return $term instanceof \WP_Term ? $term : null;
	}

	private static function build_tag_payload( \WP_Term $tag ): array {
		$link = get_term_link( $tag );
		if ( is_wp_error( $link ) ) {
			$link = '';
		}
		return array(
			'id'          => $tag->term_id,
			'name'        => $tag->name,
			'slug'        => $tag->slug,
			'description' => $tag->description,
			'count'       => $tag->count,
			'link'        => $link,
		);
	}

	private static function empty_tag_payload(): array {
		return array(
			'id'          => 0,
			'name'        => '',
			'slug'        => '',
			'description' => '',
			'count'       => 0,
			'link'        => '',
		);
	}

	private static function unique_term_slug( string $slug, string $taxonomy ): string {
		$slug = sanitize_title( $slug );
		if ( $slug === '' ) {
			return $slug;
		}
		return wp_unique_term_slug( $slug, (object) array( 'taxonomy' => $taxonomy ) );
	}

	private static function batch_operations( array $input ): array {
		$operations = $input['batch_operations'] ?? array();

		if ( empty( $operations ) ) {
			return array(
				'success'       => false,
				'operation'     => 'batch',
				'exists'        => false,
				'action'        => 'error',
				'tag'           => self::empty_tag_payload(),
				'batch_results' => array(),
				'changes_made'  => array(),
				'message'       => 'No batch operations provided.',
			);
		}

		$batch_results = array();
		$total_success = 0;
		$total_errors  = 0;

		foreach ( $operations as $index => $op ) {
			if ( ! is_array( $op ) ) {
				$batch_results[] = array(
					'operation' => 'invalid',
					'success'   => false,
					'exists'    => false,
					'action'    => 'error',
					'tag_id'    => 0,
					'tag'       => self::empty_tag_payload(),
					'message'   => sprintf( 'Batch operation at index %d must be an object.', $index ),
				);
				++$total_errors;
				continue;
			}

			$operation = isset( $op['operation'] ) ? (string) $op['operation'] : '';
			if ( ! in_array( $operation, array( 'create', 'update', 'delete' ), true ) ) {
				$batch_results[] = array(
					'operation' => $operation ?: 'invalid',
					'success'   => false,
					'exists'    => false,
					'action'    => 'error',
					'tag_id'    => 0,
					'tag'       => self::empty_tag_payload(),
					'message'   => sprintf( 'Invalid batch operation at index %d.', $index ),
				);
				++$total_errors;
				continue;
			}

			$op_input = array(
				'operation'    => $operation,
				'tag_data'     => $op['tag_data'] ?? array(),
				'tag_id'       => $op['tag_id'] ?? 0,
				'force_delete' => $input['force_delete'] ?? false,
			);

			$result = null;
			switch ( $operation ) {
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
				$batch_results[] = array(
					'operation' => $operation,
					'success'   => false,
					'exists'    => false,
					'action'    => 'error',
					'tag_id'    => 0,
					'tag'       => self::empty_tag_payload(),
					'message'   => sprintf( 'Batch operation at index %d failed to execute.', $index ),
				);
				++$total_errors;
				continue;
			}

			$batch_results[] = array(
				'operation' => $operation,
				'success'   => $result['success'],
				'exists'    => $result['exists'] ?? false,
				'action'    => $result['action'] ?? ( $result['success'] ? 'ok' : 'error' ),
				'tag_id'    => $result['tag'] ? $result['tag']['id'] : 0,
				'tag'       => $result['tag'] ?? self::empty_tag_payload(),
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
			'exists'        => false,
			'action'        => 'batch',
			'tag'           => self::empty_tag_payload(),
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
