<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class AttachPostTerms implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/attach-post-terms',
			array(
				'label'               => 'Attach Post Terms',
				'description'         => 'Attach terms to a post in a supported taxonomy.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'taxonomy', 'terms' ),
					'properties'           => array(
						'id'                => array(
							'type'        => 'integer',
							'description' => 'The unique identifier of the WordPress post to attach terms to.',
							'minimum'     => 1,
						),
						'taxonomy'          => array(
							'type'        => 'string',
							'description' => 'The taxonomy slug (e.g., "category", "post_tag", or custom taxonomy) to attach terms to.',
							'minLength'   => 1,
							'maxLength'   => 32,
							'pattern'     => '^[a-zA-Z0-9_-]+$',
						),
						'terms'             => array(
							'type'        => 'array',
							'description' => 'Array of terms to attach. Can be term IDs (integers), slugs, or names (strings).',
							'minItems'    => 1,
							'maxItems'    => 100,
							'items'       => array(
								'oneOf' => array(
									array(
										'type'        => 'integer',
										'description' => 'Term ID',
										'minimum'     => 1,
									),
									array(
										'type'        => 'string',
										'description' => 'Term slug or name',
										'minLength'   => 1,
										'maxLength'   => 200,
									),
								),
							),
						),
						'append'            => array(
							'type'        => 'boolean',
							'description' => 'Whether to append terms to existing ones (true) or replace all existing terms (false).',
							'default'     => true,
						),
						'create_if_missing' => array(
							'type'        => 'boolean',
							'description' => 'Whether to create new terms if they don\'t exist (requires appropriate permissions).',
							'default'     => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'required'             => array( 'attached_ids' ),
					'properties'           => array(
						'attached_ids' => array(
							'type'        => 'array',
							'description' => 'Array of term IDs that were successfully attached to the post.',
							'items'       => array(
								'type'        => 'integer',
								'description' => 'Term ID',
								'minimum'     => 1,
							),
							'uniqueItems' => true,
						),
					),
					'additionalProperties' => false,
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'content', 'taxonomies' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for attaching post terms.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id  = (int) ( $input['id'] ?? 0 );
		$taxonomy = isset( $input['taxonomy'] ) ? \sanitize_key( (string) $input['taxonomy'] ) : '';
		if ( $post_id <= 0 || ! \taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$post = \get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		$tax = \get_taxonomy( $taxonomy );
		return $tax && isset( $tax->cap->assign_terms ) ? \current_user_can( $tax->cap->assign_terms ) && \current_user_can( 'edit_post', $post_id ) : \current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Execute the attach post terms operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id  = (int) $input['id'];
		$taxonomy = \sanitize_key( (string) $input['taxonomy'] );
		$post     = \get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found.' );
		}
		if ( ! \is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			return new \WP_Error( 'invalid_taxonomy', 'Taxonomy not supported by post type.' );
		}
		$append            = array_key_exists( 'append', $input ) ? (bool) $input['append'] : true;
		$create_if_missing = ! empty( $input['create_if_missing'] );
		$term_ids          = array();
		$terms_in          = is_array( $input['terms'] ) ? $input['terms'] : array( $input['terms'] );
		foreach ( $terms_in as $t ) {
			if ( is_numeric( $t ) ) {
				$term_ids[] = (int) $t;
				continue;
			}
			if ( ! is_string( $t ) ) {
				continue;
			}

			$term = \get_term_by( 'slug', $t, $taxonomy );
			if ( ! $term ) {
				$term = \get_term_by( 'name', $t, $taxonomy );
			}
			if ( $term instanceof \WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			} elseif ( $create_if_missing && \current_user_can( 'manage_terms' ) ) {
				$created = \wp_insert_term( $t, $taxonomy );
				if ( ! \is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$term_ids[] = (int) $created['term_id'];
				}
			}
		}
		if ( ! empty( $term_ids ) ) {
			\wp_set_post_terms( $post_id, array_map( 'intval', $term_ids ), $taxonomy, $append );
		}
		return array( 'attached_ids' => array_values( array_unique( array_map( 'intval', $term_ids ) ) ) );
	}
}
