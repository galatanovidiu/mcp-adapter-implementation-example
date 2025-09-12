<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeleteTerm implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/delete-term',
			array(
				'label'               => 'Delete Term',
				'description'         => 'Delete a term from a taxonomy.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'taxonomy', 'term_id' ),
					'properties' => array(
						'taxonomy' => array( 'type' => 'string' ),
						'term_id'  => array( 'type' => 'integer' ),
						'reassign' => array(
							'type'        => 'integer',
							'description' => 'Optional post to reassign content where applicable.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'deleted' ),
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(),
			)
		);
	}

	/**
	 * Check permission for deleting a term.
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
		return $tax && isset( $tax->cap->delete_terms ) ? \current_user_can( $tax->cap->delete_terms ) : \current_user_can( 'manage_categories' );
	}

	/**
	 * Execute the delete term operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$taxonomy = \sanitize_key( (string) $input['taxonomy'] );
		$term_id  = (int) $input['term_id'];
		$args     = array();
		if ( ! empty( $input['reassign'] ) ) {
			$args['default'] = (int) $input['reassign'];
		}
		$deleted = \wp_delete_term( $term_id, $taxonomy, $args );
		if ( \is_wp_error( $deleted ) ) {
			return $deleted;
		}
		return array( 'deleted' => ( false !== $deleted ) );
	}
}
