<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DetachPostTerms implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/detach-post-terms',
			array(
				'label'               => 'Detach Post Terms',
				'description'         => 'Detach specific terms from a post in a supported taxonomy.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id', 'taxonomy', 'terms' ),
					'properties' => array(
						'id'       => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
						'taxonomy' => array( 'type' => 'string' ),
						'terms'    => array(
							'type'        => 'array',
							'description' => 'Array of term IDs to detach from the post.',
							'items'       => array(
								'type'        => 'integer',
								'description' => 'Term ID',
								'minimum'     => 1,
							),
							'minItems'    => 1,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'remaining_ids' ),
					'properties' => array(
						'remaining_ids' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(),
			)
		);
	}

	/**
	 * Check permission for detaching post terms.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id = (int) ( $input['id'] ?? 0 );
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
	 * Execute the detach post terms operation.
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
		$current = \wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( \is_wp_error( $current ) ) {
			return new \WP_Error( 'terms_error', 'Failed to retrieve current terms.' );
		}
		$to_remove = array();
		$terms_in = is_array( $input['terms'] ) ? $input['terms'] : array( $input['terms'] );
		foreach ( $terms_in as $t ) {
			$to_remove[] = (int) $t;
		}
		$remaining = array_values( array_diff( array_map( 'intval', $current ), $to_remove ) );
		\wp_set_post_terms( $post_id, $remaining, $taxonomy, false );
		return array( 'remaining_ids' => $remaining );
	}
}
