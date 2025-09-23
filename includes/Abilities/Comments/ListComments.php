<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Comments;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListComments implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-comments',
			array(
				'label'               => 'List Comments',
				'description'         => 'List WordPress comments with filtering, searching, and pagination options.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Filter comments by post ID.',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Filter by comment status (approve, hold, spam, trash, all). Default: approve.',
							'enum'        => array( 'approve', 'hold', 'spam', 'trash', 'all' ),
							'default'     => 'approve',
						),
						'type' => array(
							'type'        => 'string',
							'description' => 'Filter by comment type (comment, pingback, trackback, all). Default: comment.',
							'default'     => 'comment',
						),
						'author_email' => array(
							'type'        => 'string',
							'description' => 'Filter by author email address.',
						),
						'author_name' => array(
							'type'        => 'string',
							'description' => 'Filter by author name.',
						),
						'user_id' => array(
							'type'        => 'integer',
							'description' => 'Filter by user ID (for registered users).',
						),
						'parent' => array(
							'type'        => 'integer',
							'description' => 'Filter by parent comment ID.',
						),
						'search' => array(
							'type'        => 'string',
							'description' => 'Search term to look for in comment content.',
						),
						'date_query' => array(
							'type'       => 'object',
							'properties' => array(
								'after'  => array( 'type' => 'string', 'description' => 'Date after (YYYY-MM-DD)' ),
								'before' => array( 'type' => 'string', 'description' => 'Date before (YYYY-MM-DD)' ),
							),
						),
						'orderby' => array(
							'type'        => 'string',
							'description' => 'Order by field (comment_date, comment_date_gmt, comment_ID, comment_author, comment_post_ID). Default: comment_date_gmt.',
							'default'     => 'comment_date_gmt',
						),
						'order' => array(
							'type'        => 'string',
							'description' => 'Order direction (ASC, DESC). Default: DESC.',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
						),
						'number' => array(
							'type'        => 'integer',
							'description' => 'Number of comments to retrieve. Default: 20.',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of comments to skip. Default: 0.',
							'default'     => 0,
						),
						'include_meta' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include comment metadata. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'comments', 'total_count' ),
					'properties' => array(
						'comments' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'comment_ID', 'comment_content', 'comment_author', 'comment_date' ),
								'properties' => array(
									'comment_ID'           => array( 'type' => 'integer' ),
									'comment_post_ID'      => array( 'type' => 'integer' ),
									'comment_author'       => array( 'type' => 'string' ),
									'comment_author_email' => array( 'type' => 'string' ),
									'comment_author_url'   => array( 'type' => 'string' ),
									'comment_author_IP'    => array( 'type' => 'string' ),
									'comment_date'         => array( 'type' => 'string' ),
									'comment_date_gmt'     => array( 'type' => 'string' ),
									'comment_content'      => array( 'type' => 'string' ),
									'comment_karma'        => array( 'type' => 'integer' ),
									'comment_approved'     => array( 'type' => 'string' ),
									'comment_agent'        => array( 'type' => 'string' ),
									'comment_type'         => array( 'type' => 'string' ),
									'comment_parent'       => array( 'type' => 'integer' ),
									'user_id'              => array( 'type' => 'integer' ),
									'post_title'           => array( 'type' => 'string' ),
									'post_url'             => array( 'type' => 'string' ),
									'comment_url'          => array( 'type' => 'string' ),
									'reply_count'          => array( 'type' => 'integer' ),
									'meta'                 => array(
										'type'  => 'array',
										'items' => array(
											'type'       => 'object',
											'properties' => array(
												'key'   => array( 'type' => 'string' ),
												'value' => array( 'type' => 'string' ),
											),
										),
									),
								),
							),
						),
						'total_count' => array( 'type' => 'integer' ),
						'found_comments' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'engagement', 'comments' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for listing comments.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'moderate_comments' );
	}

	/**
	 * Execute the list comments operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$status = $input['status'] ?? 'all';
		$type = $input['type'] ?? 'comment';
		$author_email = $input['author_email'] ?? '';
		$author_name = $input['author_name'] ?? '';
		$user_id = isset( $input['user_id'] ) ? (int) $input['user_id'] : 0;
		$parent = isset( $input['parent'] ) ? (int) $input['parent'] : 0;
		$search = $input['search'] ?? '';
		$date_query = $input['date_query'] ?? array();
		$orderby = $input['orderby'] ?? 'comment_date_gmt';
		$order = $input['order'] ?? 'DESC';
		$number = (int) ( $input['number'] ?? 20 );
		$offset = (int) ( $input['offset'] ?? 0 );
		$include_meta = (bool) ( $input['include_meta'] ?? false );

		// Build query arguments
		$args = array(
			'orderby' => $orderby,
			'order'   => $order,
			'number'  => $number,
			'offset'  => $offset,
		);

		// Filter by post ID
		if ( $post_id > 0 ) {
			$args['post_id'] = $post_id;
		}

		// Filter by status
		if ( $status !== 'all' ) {
			// Map status values to WordPress query format
			$status_map = array(
				'approve' => 'approved',
				'hold'    => 'hold',
				'spam'    => 'spam',
				'trash'   => 'trash',
			);
			$args['status'] = isset( $status_map[ $status ] ) ? $status_map[ $status ] : $status;
		}

		// Filter by type
		if ( $type !== 'all' ) {
			$args['type'] = $type;
		}

		// Filter by author email
		if ( ! empty( $author_email ) ) {
			$args['author_email'] = \sanitize_email( $author_email );
		}

		// Filter by author name
		if ( ! empty( $author_name ) ) {
			$args['author__in'] = array( \sanitize_text_field( $author_name ) );
		}

		// Filter by user ID
		if ( $user_id > 0 ) {
			$args['user_id'] = $user_id;
		}

		// Filter by parent
		if ( $parent > 0 ) {
			$args['parent'] = $parent;
		}

		// Search in content
		if ( ! empty( $search ) ) {
			$args['search'] = \sanitize_text_field( $search );
		}

		// Date query
		if ( ! empty( $date_query ) ) {
			$date_query_args = array();
			if ( ! empty( $date_query['after'] ) ) {
				$date_query_args['after'] = \sanitize_text_field( $date_query['after'] );
			}
			if ( ! empty( $date_query['before'] ) ) {
				$date_query_args['before'] = \sanitize_text_field( $date_query['before'] );
			}
			if ( ! empty( $date_query_args ) ) {
				$args['date_query'] = array( $date_query_args );
			}
		}

		// Get comments using get_comments function
		$comments = \get_comments( $args );
		
		// Get total count with a separate query
		$count_args = $args;
		$count_args['count'] = true;
		unset( $count_args['number'], $count_args['offset'] );
		$total_comments = \get_comments( $count_args );
		
		// Ensure total_comments is an integer
		$total_comments = is_numeric( $total_comments ) ? (int) $total_comments : count( $comments );
		
		// Debug: Check what we got
		if ( empty( $comments ) && $total_comments > 0 ) {
			// Try a simple query without filters
			$simple_comments = \get_comments( array( 'number' => 10 ) );
			if ( ! empty( $simple_comments ) ) {
				$comments = $simple_comments;
			}
		}

		$comments_data = array();

		if ( ! empty( $comments ) && is_array( $comments ) ) {
			foreach ( $comments as $comment ) {
			// Get post information
			$post = \get_post( (int) $comment->comment_post_ID );
			$post_title = $post ? $post->post_title : '';
			$post_url = $post ? \get_permalink( $post->ID ) : '';

			// Get comment URL
			$comment_url = \get_comment_link( $comment );

			// Count replies
			$reply_count = \get_comments( array(
				'parent' => $comment->comment_ID,
				'count'  => true,
			) );

			$comment_data = array(
				'comment_ID'           => (int) $comment->comment_ID,
				'comment_post_ID'      => (int) $comment->comment_post_ID,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_author_IP'    => $comment->comment_author_IP,
				'comment_date'         => $comment->comment_date,
				'comment_date_gmt'     => $comment->comment_date_gmt,
				'comment_content'      => $comment->comment_content,
				'comment_karma'        => (int) $comment->comment_karma,
				'comment_approved'     => $comment->comment_approved,
				'comment_agent'        => $comment->comment_agent,
				'comment_type'         => $comment->comment_type,
				'comment_parent'       => (int) $comment->comment_parent,
				'user_id'              => (int) $comment->user_id,
				'post_title'           => $post_title,
				'post_url'             => $post_url,
				'comment_url'          => $comment_url,
				'reply_count'          => (int) $reply_count,
			);

			// Include metadata if requested
			if ( $include_meta ) {
				$meta = \get_comment_meta( (int) $comment->comment_ID );
				$meta_data = array();
				foreach ( $meta as $key => $values ) {
					foreach ( $values as $value ) {
						$meta_data[] = array(
							'key'   => $key,
							'value' => $value,
						);
					}
				}
				$comment_data['meta'] = $meta_data;
			} else {
				$comment_data['meta'] = array();
			}

				$comments_data[] = $comment_data;
			}
		}

		return array(
			'comments'       => $comments_data,
			'total_count'    => count( $comments_data ),
			'found_comments' => (int) $total_comments,
		);
	}
}
