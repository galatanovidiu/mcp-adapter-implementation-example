<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Tests;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

/**
 * Demo abilities with flattened input/output schemas for MCP adapter testing.
 */
final class FlattenedSchemaDemos implements RegistersAbility {

	public static function register(): void {
		self::register_echo_string();
		self::register_add_ten();
		self::register_toggle_boolean();
		self::register_pick_first();
		self::register_random_quote();
		self::register_square_integer();
		self::register_get_post();
	}

	private static function base_meta(): array {
		return array(
			'category'            => 'system',
			'permission_callback' => static fn() => true,
			'meta'                => array(
				'mcp' => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		);
	}

	private static function register_echo_string(): void {
		\wp_register_ability(
			'test/flat-echo-string',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Echo String',
					'description'      => 'Echoes a string input with flattened schema.',
					'input_schema'     => array( 'type' => 'string', 'description' => 'Text to echo.' ),
					'output_schema'    => array( 'type' => 'string', 'description' => 'Echoed text.' ),
					'execute_callback' => static function ( $input ) {
						return (string) ( $input ?? '' );
					},
				)
			)
		);
	}

	private static function register_add_ten(): void {
		\wp_register_ability(
			'test/flat-add-ten',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Add Ten',
					'description'      => 'Adds ten to a provided number.',
					'input_schema'     => array( 'type' => 'number', 'description' => 'Base number.' ),
					'output_schema'    => array( 'type' => 'number', 'description' => 'Number plus ten.' ),
					'execute_callback' => static function ( $input ) {
						$number = is_numeric( $input ) ? (float) $input : 0.0;
						return $number + 10;
					},
				)
			)
		);
	}

	private static function register_toggle_boolean(): void {
		\wp_register_ability(
			'test/flat-toggle-boolean',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Toggle Boolean',
					'description'      => 'Inverts a boolean.',
					'input_schema'     => array( 'type' => 'boolean', 'description' => 'Value to invert.' ),
					'output_schema'    => array( 'type' => 'boolean', 'description' => 'Inverted value.' ),
					'execute_callback' => static function ( $input ) {
						return ! (bool) $input;
					},
				)
			)
		);
	}

	private static function register_pick_first(): void {
		\wp_register_ability(
			'test/flat-pick-first',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Pick First',
					'description'      => 'Returns the first element of an array of strings.',
					'input_schema'     => array(
						'type'        => 'array',
						'description' => 'Array of strings.',
						'items'       => array( 'type' => 'string' ),
					),
					'output_schema'    => array( 'type' => 'string', 'description' => 'First element or empty string.' ),
					'execute_callback' => static function ( $input ) {
						if ( is_array( $input ) && isset( $input[0] ) && is_scalar( $input[0] ) ) {
							return (string) $input[0];
						}
						return '';
					},
				)
			)
		);
	}

	private static function register_random_quote(): void {
		$quotes = array(
			'Stay hungry, stay foolish.',
			'Code is like humor. When you have to explain it, it’s bad.',
			'Simplicity is the soul of efficiency.',
			'Premature optimization is the root of all evil.',
			'Testing leads to failure, and failure leads to understanding.',
			'Programs must be written for people to read.',
		);

		\wp_register_ability(
			'test/flat-random-quote',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Random Quote',
					'description'      => 'Returns a random quote; no input required.',
					'input_schema'     => array(),
					'output_schema'    => array( 'type' => 'string', 'description' => 'Random quote.' ),
					'execute_callback' => static function () use ( $quotes ) {
						return $quotes[ array_rand( $quotes ) ];
					},
				)
			)
		);
	}

	private static function register_square_integer(): void {
		\wp_register_ability(
			'test/flat-square-integer',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Square Integer',
					'description'      => 'Squares an integer.',
					'input_schema'     => array( 'type' => 'integer', 'description' => 'Integer to square.' ),
					'output_schema'    => array( 'type' => 'integer', 'description' => 'Squared result.' ),
					'execute_callback' => static function ( $input ) {
						$value = is_numeric( $input ) ? (int) $input : 0;
						return $value * $value;
					},
				)
			)
		);
	}

	private static function register_get_post(): void {
		\wp_register_ability(
			'test/flat-get-post',
			array_merge(
				self::base_meta(),
				array(
					'label'            => 'Flat Get Post',
					'description'      => 'Retrieves a WordPress post by ID.',
					'input_schema'     => array( 'type' => 'integer', 'description' => 'Post ID to retrieve.' ),
					'output_schema'    => array(
						'type'       => 'object',
						'description' => 'Post object with basic information.',
						'properties' => array(
							'id'      => array( 'type' => 'integer', 'description' => 'Post ID.' ),
							'title'   => array( 'type' => 'string', 'description' => 'Post title.' ),
							'content' => array( 'type' => 'string', 'description' => 'Post content.' ),
							'status'  => array( 'type' => 'string', 'description' => 'Post status.' ),
							'author'  => array( 'type' => 'integer', 'description' => 'Author ID.' ),
							'date'    => array( 'type' => 'string', 'description' => 'Publication date.' ),
							'type'    => array( 'type' => 'string', 'description' => 'Post type.' ),
							'slug'    => array( 'type' => 'string', 'description' => 'Post slug.' ),
						),
					),
					'execute_callback' => static function ( $input ) {
						$post_id = is_numeric( $input ) ? (int) $input : 0;
						
						if ( $post_id <= 0 ) {
							return new \WP_Error( 'invalid_post_id', 'Invalid post ID provided.' );
						}

						$post = \get_post( $post_id );

						if ( ! $post ) {
							return new \WP_Error( 'post_not_found', sprintf( 'Post with ID %d not found.', $post_id ) );
						}

						return array(
							'id'      => $post->ID,
							'title'   => $post->post_title,
							'content' => $post->post_content,
							'status'  => $post->post_status,
							'author'  => $post->post_author,
							'date'    => $post->post_date,
							'type'    => $post->post_type,
							'slug'    => $post->post_name,
						);
					},
				)
			)
		);
	}
}
