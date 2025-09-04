<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateTerm implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/update-term',
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
				'meta'                => array(),
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
		$taxonomy = \sanitize_key( (string) $input['taxonomy'] );
		$term_id  = (int) $input['term_id'];
		$args = array();
		if ( array_key_exists( 'name', $input ) ) {
			$args['name'] = (string) $input['name'];
		}
		if ( array_key_exists( 'slug', $input ) ) {
			$args['slug'] = \sanitize_title( (string) $input['slug'] );
		}
		if ( array_key_exists( 'description', $input ) ) {
			$args['description'] = (string) $input['description'];
		}
		if ( array_key_exists( 'parent', $input ) ) {
			$args['parent'] = (int) $input['parent'];
		}
		$updated = \wp_update_term( $term_id, $taxonomy, $args );
		if ( \is_wp_error( $updated ) ) {
			return $updated;
		}
		return array( 'id' => (int) $updated['term_id'] );
	}
}
