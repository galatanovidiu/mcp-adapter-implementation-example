<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class CreateTerm implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/create-term',
			array(
				'label'               => 'Create Term',
				'description'         => 'Create a term in a taxonomy.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'taxonomy', 'name' ),
					'properties' => array(
						'taxonomy'    => array( 'type' => 'string' ),
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

	/**
	 * Check permission for creating a term.
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
		return $tax && isset( $tax->cap->manage_terms ) ? \current_user_can( $tax->cap->manage_terms ) : \current_user_can( 'manage_categories' );
	}

	/**
	 * Execute the create term operation.
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
		$name = isset( $input['name'] ) ? \sanitize_text_field( (string) $input['name'] ) : '';
		if ( '' === $name ) {
			return array(
				'error' => array(
					'code'    => 'missing_name',
					'message' => 'Name is required.',
				),
			);
		}
		$args = array();
		if ( ! empty( $input['slug'] ) ) {
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
		$created = \wp_insert_term( $name, $taxonomy, $args );
		if ( \is_wp_error( $created ) ) {
			return $created;
		}
		return array( 'id' => (int) $created['term_id'] );
	}
}
