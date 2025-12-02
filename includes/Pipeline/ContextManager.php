<?php
/**
 * Context Manager
 *
 * Manages variable storage and resolution during pipeline execution.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline;

/**
 * Class ContextManager
 *
 * Handles variable storage, scoping, and resolution for pipeline execution.
 * Supports nested scopes for loops and sub-pipelines.
 */
class ContextManager {
	/**
	 * Variable storage stack (for nested scopes)
	 *
	 * @var array<array<string,mixed>>
	 */
	private array $scopes = [];

	/**
	 * Current scope index
	 *
	 * @var int
	 */
	private int $current_scope = 0;

	/**
	 * Constructor
	 *
	 * @param array<string,mixed> $initial_variables Initial context variables
	 */
	public function __construct( array $initial_variables = [] ) {
		$this->scopes[0] = $initial_variables;
	}

	/**
	 * Set a variable in the current scope
	 *
	 * @param string $name  Variable name (without $ prefix)
	 * @param mixed  $value Variable value
	 * @return void
	 */
	public function set( string $name, $value ): void {
		// Remove $ prefix if present
		$name = ltrim( $name, '$' );
		$this->scopes[ $this->current_scope ][ $name ] = $value;
	}

	/**
	 * Get a variable from the current or parent scopes
	 *
	 * @param string $name Variable name (without $ prefix)
	 * @param mixed  $default Default value if not found
	 * @return mixed Variable value or default
	 */
	public function get( string $name, $default = null ) {
		// Remove $ prefix if present
		$name = ltrim( $name, '$' );

		// Search from current scope up to root scope
		for ( $i = $this->current_scope; $i >= 0; $i-- ) {
			if ( array_key_exists( $name, $this->scopes[ $i ] ) ) {
				return $this->scopes[ $i ][ $name ];
			}
		}

		return $default;
	}

	/**
	 * Check if a variable exists in any scope
	 *
	 * @param string $name Variable name (without $ prefix)
	 * @return bool True if variable exists
	 */
	public function has( string $name ): bool {
		$name = ltrim( $name, '$' );

		for ( $i = $this->current_scope; $i >= 0; $i-- ) {
			if ( array_key_exists( $name, $this->scopes[ $i ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve a variable reference
	 *
	 * Supports:
	 * - Simple variables: $posts
	 * - Array access: $posts[0]
	 * - Object/array property access: $post.title or $post['title']
	 * - Nested access: $post.meta.author.name
	 *
	 * @param string $reference Variable reference (with $ prefix)
	 * @return mixed Resolved value
	 * @throws \RuntimeException If variable not found
	 */
	public function resolve( string $reference ) {
		if ( ! str_starts_with( $reference, '$' ) ) {
			throw new \InvalidArgumentException( "Variable reference must start with $: {$reference}" );
		}

		// Parse the reference into parts
		$parts = $this->parse_reference( $reference );
		$var_name = array_shift( $parts );

		// Get the base variable
		if ( ! $this->has( $var_name ) ) {
			throw new \RuntimeException( "Undefined variable: \${$var_name}" );
		}

		$value = $this->get( $var_name );

		// Resolve nested access
		foreach ( $parts as $part ) {
			$value = $this->resolve_property( $value, $part );
		}

		return $value;
	}

	/**
	 * Parse a variable reference into parts
	 *
	 * Examples:
	 * - "$posts" -> ["posts"]
	 * - "$post.title" -> ["post", "title"]
	 * - "$post.meta[0].value" -> ["post", "meta", "0", "value"]
	 *
	 * @param string $reference Variable reference
	 * @return array<string> Parts
	 */
	private function parse_reference( string $reference ): array {
		// Remove $ prefix
		$reference = ltrim( $reference, '$' );

		// Split by dots and brackets
		$parts = [];
		$current = '';

		for ( $i = 0; $i < strlen( $reference ); $i++ ) {
			$char = $reference[ $i ];

			if ( $char === '.' ) {
				if ( $current !== '' ) {
					$parts[] = $current;
					$current = '';
				}
			} elseif ( $char === '[' ) {
				if ( $current !== '' ) {
					$parts[] = $current;
					$current = '';
				}
				// Find closing bracket
				$close = strpos( $reference, ']', $i );
				if ( $close === false ) {
					throw new \InvalidArgumentException( "Unclosed bracket in reference: \${$reference}" );
				}
				$key = substr( $reference, $i + 1, $close - $i - 1 );
				$parts[] = trim( $key, '\'"' );
				$i = $close;
			} elseif ( $char === ']' ) {
				// Skip - already handled
			} else {
				$current .= $char;
			}
		}

		if ( $current !== '' ) {
			$parts[] = $current;
		}

		return $parts;
	}

	/**
	 * Resolve a property or array key from a value
	 *
	 * @param mixed  $value Value to access
	 * @param string $key   Property or array key
	 * @return mixed Resolved value
	 * @throws \RuntimeException If property/key not found
	 */
	private function resolve_property( $value, string $key ) {
		// Array access
		if ( is_array( $value ) ) {
			if ( ! array_key_exists( $key, $value ) ) {
				throw new \RuntimeException( "Undefined array key: {$key}" );
			}
			return $value[ $key ];
		}

		// Object property access
		if ( is_object( $value ) ) {
			if ( ! property_exists( $value, $key ) && ! isset( $value->$key ) ) {
				throw new \RuntimeException( "Undefined object property: {$key}" );
			}
			return $value->$key;
		}

		throw new \RuntimeException( "Cannot access property '{$key}' on non-object/non-array value" );
	}

	/**
	 * Push a new scope onto the stack
	 *
	 * Used for loops and sub-pipelines to create isolated variable scopes.
	 *
	 * @param array<string,mixed> $variables Initial variables for new scope
	 * @return void
	 */
	public function push_scope( array $variables = [] ): void {
		$this->current_scope++;
		$this->scopes[ $this->current_scope ] = $variables;
	}

	/**
	 * Pop the current scope from the stack
	 *
	 * @return void
	 * @throws \RuntimeException If attempting to pop root scope
	 */
	public function pop_scope(): void {
		if ( $this->current_scope === 0 ) {
			throw new \RuntimeException( 'Cannot pop root scope' );
		}

		unset( $this->scopes[ $this->current_scope ] );
		$this->current_scope--;
	}

	/**
	 * Get all variables in the current scope
	 *
	 * @param bool $include_parent Include variables from parent scopes
	 * @return array<string,mixed> Variables
	 */
	public function get_all( bool $include_parent = true ): array {
		if ( ! $include_parent ) {
			return $this->scopes[ $this->current_scope ];
		}

		// Merge all scopes from root to current
		$all = [];
		for ( $i = 0; $i <= $this->current_scope; $i++ ) {
			$all = array_merge( $all, $this->scopes[ $i ] );
		}
		return $all;
	}

	/**
	 * Clear all variables in the current scope
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->scopes[ $this->current_scope ] = [];
	}

	/**
	 * Get the current scope level
	 *
	 * @return int Scope level (0 = root)
	 */
	public function get_scope_level(): int {
		return $this->current_scope;
	}
}
