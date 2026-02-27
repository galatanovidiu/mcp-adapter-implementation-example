<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

final class WorkflowAbilityCatalog {

	public const SUMMARY_VERSION = '1.0';
	private const SUMMARY_MAX_LENGTH = 160;

	public static function list_summaries( string $search = '', string $category = '' ): array {
		$abilities = \wp_get_abilities();
		$results   = array();

		foreach ( $abilities as $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
				continue;
			}
			$ability_name = (string) $ability->get_name();
			if ( ! self::is_ability_allowed( $ability_name, $ability ) ) {
				continue;
			}

			$ability_category = self::get_category( $ability );
			if ( $category !== '' && $category !== $ability_category ) {
				continue;
			}

			if ( $search !== '' && ! self::matches_search( $ability, $search ) ) {
				continue;
			}

			$results[] = self::build_summary( $ability );
		}

		usort(
			$results,
			static function ( array $left, array $right ): int {
				return strcmp( $left['name'], $right['name'] );
			}
		);

		return $results;
	}

	public static function normalize_ability_name( string $ability_name ): string {
		$ability = \wp_get_ability( $ability_name );
		if ( $ability ) {
			return $ability_name;
		}

		if ( strpos( $ability_name, '-' ) === false ) {
			return $ability_name;
		}

		return (string) preg_replace( '/-/', '/', $ability_name, 1 );
	}

	public static function build_summary( $ability ): array {
		$name        = (string) $ability->get_name();
		$label       = method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : '';
		$description = method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '';
		$summary     = self::truncate_summary( $description );
		$category    = self::get_category( $ability );
		$tags        = self::extract_tags( $ability, $category );
		$policy      = self::extract_policy_flags( $ability );
		$input_info  = self::extract_input_info( $ability );

		return array(
			'id'       => $name,
			'name'     => $name,
			'label'    => $label,
			'summary'  => $summary,
			'category' => $category,
			'tags'     => $tags,
			'version'  => self::SUMMARY_VERSION,
			'policy'   => $policy,
			'input'    => $input_info,
		);
	}

	public static function is_ability_allowed( string $ability_name, $ability, ?array $allowed_prefixes = null ): bool {
		if ( $ability_name === 'core/execute-workflow' ) {
			return false;
		}

		if ( $allowed_prefixes === null ) {
			$allowed_prefixes = ExecuteWorkflow::DEFAULT_ALLOWED_PREFIXES;
		}

		$matches_prefix = false;
		foreach ( $allowed_prefixes as $prefix ) {
			if ( $prefix !== '' && strpos( $ability_name, $prefix ) === 0 ) {
				$matches_prefix = true;
				break;
			}
		}
		if ( ! $matches_prefix ) {
			return false;
		}

		$meta = self::get_meta( $ability );
		if ( isset( $meta['mcp']['type'] ) && $meta['mcp']['type'] !== 'tool' ) {
			return false;
		}
		if ( isset( $meta['mcp']['public'] ) && $meta['mcp']['public'] === false ) {
			return false;
		}

		$input_schema = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : null;
		if ( ! is_array( $input_schema ) || ! isset( $input_schema['type'] ) ) {
			return false;
		}

		return true;
	}

	private static function get_category( $ability ): string {
		if ( method_exists( $ability, 'get_category' ) ) {
			return self::sanitize_key( (string) $ability->get_category() );
		}

		$meta = self::get_meta( $ability );
		return isset( $meta['category'] ) ? self::sanitize_key( (string) $meta['category'] ) : '';
	}

	private static function matches_search( $ability, string $search ): bool {
		$search      = trim( $search );
		$ability_key = method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '';
		$label       = method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : '';
		$description = method_exists( $ability, 'get_description' ) ? (string) $ability->get_description() : '';
		$haystack    = strtolower( $ability_key . ' ' . $label . ' ' . $description );

		return strpos( $haystack, strtolower( $search ) ) !== false;
	}

	private static function extract_tags( $ability, string $category ): array {
		$meta = self::get_meta( $ability );
		$tags = array();

		if ( isset( $meta['tags'] ) && is_array( $meta['tags'] ) ) {
			$tags = array_merge( $tags, $meta['tags'] );
		}
		if ( isset( $meta['mcp']['tags'] ) && is_array( $meta['mcp']['tags'] ) ) {
			$tags = array_merge( $tags, $meta['mcp']['tags'] );
		}
		if ( $category !== '' ) {
			$tags[] = $category;
		}

		$tags = array_filter(
			array_map(
				static function ( $tag ) {
					return is_string( $tag ) ? $tag : null;
				},
				$tags
			)
		);

		return array_values( array_unique( $tags ) );
	}

	private static function extract_policy_flags( $ability ): array {
		$annotations = self::get_ability_annotations( $ability );

		return array(
			'readonly'              => ! empty( $annotations['readonly'] ),
			'destructive'           => ! empty( $annotations['destructive'] ),
			'idempotent'            => ! empty( $annotations['idempotent'] ),
			'requires_confirmation' => ! empty( $annotations['requiresConfirmation'] ),
		);
	}

	private static function extract_input_info( $ability ): array {
		$input_schema = method_exists( $ability, 'get_input_schema' ) ? (array) $ability->get_input_schema() : array();
		$required     = isset( $input_schema['required'] ) && is_array( $input_schema['required'] ) ? $input_schema['required'] : array();
		$properties   = isset( $input_schema['properties'] ) && is_array( $input_schema['properties'] ) ? array_keys( $input_schema['properties'] ) : array();
		$required     = array_values(
			array_filter(
				array_map(
					static function ( $field ) {
						return is_string( $field ) ? $field : null;
					},
					$required
				)
			)
		);
		$optional_count = max( 0, count( $properties ) - count( $required ) );

		return array(
			'required'       => $required,
			'optional_count' => $optional_count,
		);
	}

	private static function truncate_summary( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( $text === '' ) {
			return '';
		}
		if ( strlen( $text ) <= self::SUMMARY_MAX_LENGTH ) {
			return $text;
		}

		return substr( $text, 0, self::SUMMARY_MAX_LENGTH - 3 ) . '...';
	}

	public static function get_ability_annotations( $ability ): array {
		$meta = self::get_meta( $ability );
		if ( isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ) {
			return $meta['annotations'];
		}
		if ( isset( $meta['mcp']['annotations'] ) && is_array( $meta['mcp']['annotations'] ) ) {
			return $meta['mcp']['annotations'];
		}

		return array();
	}

	private static function get_meta( $ability ): array {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_meta' ) ) {
			return array();
		}
		$meta = $ability->get_meta();

		return is_array( $meta ) ? $meta : array();
	}

	private static function sanitize_key( string $value ): string {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '';
	}
}
