<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateTerm implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-term',
			array(
				'label'               => 'Update Term',
				'description'         => 'Update a term in a taxonomy.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'taxonomy', 'term_id' ),
					'properties' => array(
						'taxonomy'    => array( 'type' => 'string' ),
						'term_id'     => array( 'type' => 'integer' ),
						'name'        => array( 'type' => 'string' ),
						'slug'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'parent'      => array( 'type' => 'integer' ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Check permission for updating a term.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$taxonomy = isset( $input['taxonomy'] ) ? \sanitize_key( (string) $input['taxonomy'] ) : '';
		if ( ! \taxonomy_exists( $taxonomy ) ) {
			return false;
		}
		$tax = \get_taxonomy( $taxonomy );
		return $tax && isset( $tax->cap->edit_terms ) ? \current_user_can( $tax->cap->edit_terms ) : \current_user_can( 'manage_categories' );
	}

	/**
	 * Execute the update term operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$taxonomy = isset( $input['taxonomy'] ) ? \sanitize_key( (string) $input['taxonomy'] ) : '';
		if ( '' === $taxonomy ) {
			return array(
				'error' => array(
					'code'    => 'missing_taxonomy',
					'message' => 'Taxonomy is required.',
				),
			);
		}
		if ( ! \taxonomy_exists( $taxonomy ) ) {
			return array(
				'error' => array(
					'code'    => 'invalid_taxonomy',
					'message' => 'Invalid taxonomy.',
				),
			);
		}
		$term_id = isset( $input['term_id'] ) ? \absint( $input['term_id'] ) : 0;
		if ( $term_id < 1 ) {
			return array(
				'error' => array(
					'code'    => 'missing_term_id',
					'message' => 'Term ID is required.',
				),
			);
		}
		$term = \get_term( $term_id, $taxonomy );
		if ( \is_wp_error( $term ) || ! $term ) {
			return array(
				'error' => array(
					'code'    => 'term_not_found',
					'message' => 'Term not found.',
				),
			);
		}
		$args = array();
		if ( array_key_exists( 'name', $input ) ) {
			$name = \sanitize_text_field( (string) $input['name'] );
			if ( '' !== $name ) {
				$args['name'] = $name;
			}
		}
		if ( array_key_exists( 'slug', $input ) ) {
			$args['slug'] = \sanitize_title( (string) $input['slug'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$args['description'] = \sanitize_textarea_field( (string) $input['description'] );
		}
		if ( array_key_exists( 'parent', $input ) ) {
			$parent_id = \absint( $input['parent'] );
			if ( $parent_id > 0 ) {
				$parent_term = \get_term( $parent_id, $taxonomy );
				if ( \is_wp_error( $parent_term ) || ! $parent_term ) {
					return array(
						'error' => array(
							'code'    => 'invalid_parent',
							'message' => 'Parent term not found.',
						),
					);
				}
			}
			$args['parent'] = $parent_id;
		}
		$updated = \wp_update_term( $term_id, $taxonomy, $args );
		if ( \is_wp_error( $updated ) ) {
			return $updated;
		}
		return array( 'id' => (int) $updated['term_id'] );
	}
}
