<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Resources;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListPostsResource implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'resources/posts-list',
			array(
				'label'               => 'Posts List Resource',
				'description'         => 'Access WordPress posts as a resource listing',
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'resource',
					),
					'uri'         => 'wordpress://posts',
					'mimeType'    => 'application/json',
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for listing posts resource.
	 *
	 * Note: This ability has no input_schema, so this callback is invoked with NO arguments.
	 * The parameter must have a default value to prevent PHP errors.
	 *
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission() {
		return \current_user_can( 'read' );
	}

	/**
	 * Execute the posts list resource retrieval.
	 *
	 * Note: This ability has no input_schema, so this callback is invoked with NO arguments.
	 * The parameter must have a default value to prevent PHP errors.
	 *
	 * @return array|\\WP_Error Resource content or error.
	 */
	public static function execute() {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return array(
				'uri'      => 'wordpress://posts',
				'mimeType' => 'application/json',
				'text'     => \wp_json_encode(
					array(
						'posts' => array(),
						'total' => 0,
					)
				),
			);
		}

		$posts = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post = \get_post();
			if ( ! $post ) {
				continue;
			}

			$posts[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'excerpt'  => \wp_trim_words( $post->post_content, 30 ),
				'link'     => \get_permalink( $post->ID ),
				'date'     => $post->post_date,
				'uri'      => "wordpress://post/{$post->ID}",
			);
		}

		\wp_reset_postdata();

		return array(
			'uri'      => 'wordpress://posts',
			'mimeType' => 'application/json',
			'text'     => \wp_json_encode(
				array(
					'posts' => $posts,
					'total' => count( $posts ),
				)
			),
		);
	}
}
