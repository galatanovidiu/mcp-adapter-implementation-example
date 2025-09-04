<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetPost implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/get-post',
			array(
				'label'               => 'Get Post',
				'description'         => 'Retrieve a WordPress post by ID, including HTML content and attached taxonomies.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id', 'post_type', 'status', 'link', 'title' ),
					'properties' => array(
						'id'         => array( 'type' => 'integer' ),
						'post_type'  => array( 'type' => 'string' ),
						'status'     => array( 'type' => 'string' ),
						'link'       => array( 'type' => 'string' ),
						'title'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'excerpt'    => array( 'type' => 'string' ),
						'taxonomies' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(),
			)
		);
	}

	/**
	 * Check permission for reading a post.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id = (int) ( $input['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return false;
		}
		return \current_user_can( 'read_post', $post_id );
	}

	/**
	 * Execute the get post operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id = (int) $input['id'];
		$post    = \get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found.' );
		}

		// Build attached taxonomies mapping for this post.
		$tax_map = array();
		$supported_taxonomies = \get_object_taxonomies( $post->post_type, 'names' );
		foreach ( $supported_taxonomies as $tax ) {
			$terms = \wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'all' ) );
			$tax_map[ $tax ] = array();
			if ( \is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $t ) {
				if ( ! ( $t instanceof \WP_Term ) ) {
					continue;
				}

				$tax_map[ $tax ][] = array(
					'id'     => (int) $t->term_id,
					'name'   => (string) $t->name,
					'slug'   => (string) $t->slug,
					'parent' => (int) $t->parent,
				);
			}
		}

		return array(
			'id'         => $post->ID,
			'post_type'  => $post->post_type,
			'status'     => $post->post_status,
			'link'       => (string) \get_permalink( $post->ID ),
			'title'      => (string) $post->post_title,
			'content'    => (string) $post->post_content,

			'excerpt'    => (string) $post->post_excerpt,
			'taxonomies' => $tax_map,
		);
	}
}
