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
						'if_exists'   => array(
							'type'        => 'string',
							'description' => 'How to handle an existing term (error, use_existing, create_duplicate).',
							'enum'        => array( 'error', 'use_existing', 'create_duplicate' ),
							'default'     => 'error',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'id' ),
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'exists'  => array( 'type' => 'boolean' ),
						'action'  => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
						'term'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'taxonomy'    => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'parent'      => array( 'type' => 'integer' ),
								'count'       => array( 'type' => 'integer' ),
							),
						),
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
			return self::error_response( 'Taxonomy is required.' );
		}
		if ( ! \taxonomy_exists( $taxonomy ) ) {
			return self::error_response( 'Invalid taxonomy.' );
		}
		$name = isset( $input['name'] ) ? \sanitize_text_field( (string) $input['name'] ) : '';
		if ( '' === $name ) {
			return self::error_response( 'Name is required.' );
		}
		$if_exists = self::normalize_if_exists( $input['if_exists'] ?? 'error' );
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
					return self::error_response( 'Parent term not found.' );
				}
			}
			$args['parent'] = $parent_id;
		}

		$existing_term = self::find_existing_term( $taxonomy, $args['slug'] ?? '', $name );
		if ( $existing_term ) {
			$existing_payload = self::build_term_payload( $existing_term );
			if ( 'use_existing' === $if_exists ) {
				return self::success_response( $existing_term->term_id, $existing_payload, true, 'existing', 'Term already exists. Using existing term.' );
			}
			if ( 'create_duplicate' !== $if_exists ) {
				return self::exists_response( $existing_payload, 'Term already exists. Set if_exists to use_existing or create_duplicate.' );
			}
			$base_slug   = $args['slug'] ?? $name;
			$args['slug'] = self::unique_term_slug( $base_slug, $taxonomy );
		}

		$created = \wp_insert_term( $name, $taxonomy, $args );
		if ( \is_wp_error( $created ) ) {
			return self::error_response( $created->get_error_message() );
		}
		$term_id = (int) $created['term_id'];
		$term    = \get_term( $term_id, $taxonomy );
		$payload = $term instanceof \WP_Term ? self::build_term_payload( $term ) : self::empty_term_payload();
		$action  = $existing_term ? 'duplicate' : 'created';
		return self::success_response( $term_id, $payload, (bool) $existing_term, $action, 'Term successfully created.' );
	}

	private static function error_response( string $message ): array {
		return array(
			'success' => false,
			'id'      => 0,
			'exists'  => false,
			'action'  => 'error',
			'message' => $message,
			'term'    => self::empty_term_payload(),
		);
	}

	private static function exists_response( array $term, string $message ): array {
		return array(
			'success' => false,
			'id'      => (int) ( $term['id'] ?? 0 ),
			'exists'  => true,
			'action'  => 'exists',
			'message' => $message,
			'term'    => $term,
		);
	}

	private static function success_response( int $id, array $term, bool $exists, string $action, string $message ): array {
		return array(
			'success' => true,
			'id'      => $id,
			'exists'  => $exists,
			'action'  => $action,
			'message' => $message,
			'term'    => $term,
		);
	}

	private static function normalize_if_exists( $value ): string {
		$value = \sanitize_key( (string) $value );
		$allowed = array( 'error', 'use_existing', 'create_duplicate' );
		return in_array( $value, $allowed, true ) ? $value : 'error';
	}

	private static function find_existing_term( string $taxonomy, string $slug, string $name ): ?\WP_Term {
		$term_id = 0;
		if ( $slug !== '' ) {
			$term_exists = \term_exists( $slug, $taxonomy );
			$term_id     = is_array( $term_exists ) ? (int) $term_exists['term_id'] : (int) $term_exists;
		}
		if ( ! $term_id && $name !== '' ) {
			$term_exists = \term_exists( $name, $taxonomy );
			$term_id     = is_array( $term_exists ) ? (int) $term_exists['term_id'] : (int) $term_exists;
		}
		if ( ! $term_id ) {
			return null;
		}
		$term = \get_term( $term_id, $taxonomy );
		return $term instanceof \WP_Term ? $term : null;
	}

	private static function build_term_payload( \WP_Term $term ): array {
		return array(
			'id'          => (int) $term->term_id,
			'name'        => (string) $term->name,
			'slug'        => (string) $term->slug,
			'taxonomy'    => (string) $term->taxonomy,
			'description' => (string) $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
		);
	}

	private static function empty_term_payload(): array {
		return array(
			'id'          => 0,
			'name'        => '',
			'slug'        => '',
			'taxonomy'    => '',
			'description' => '',
			'parent'      => 0,
			'count'       => 0,
		);
	}

	private static function unique_term_slug( string $slug, string $taxonomy ): string {
		$slug = \sanitize_title( $slug );
		if ( $slug === '' ) {
			return $slug;
		}
		return \wp_unique_term_slug( $slug, (object) array( 'taxonomy' => $taxonomy ) );
	}
}
