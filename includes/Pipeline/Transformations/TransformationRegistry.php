<?php
/**
 * Transformation Registry
 *
 * Central registry for all available data transformations.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Transformations
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Transformations;

/**
 * Class TransformationRegistry
 *
 * Manages registration and execution of data transformations.
 */
class TransformationRegistry {
	/**
	 * Registered transformations
	 *
	 * @var array<string,callable>
	 */
	private static array $transformations = [];

	/**
	 * Initialize default transformations
	 *
	 * @return void
	 */
	public static function init(): void {
		// Array operations
		self::register( 'filter', [ self::class, 'filter' ] );
		self::register( 'map', [ self::class, 'map' ] );
		self::register( 'pluck', [ self::class, 'pluck' ] );
		self::register( 'unique', [ self::class, 'unique' ] );
		self::register( 'sort', [ self::class, 'sort' ] );
		self::register( 'reverse', [ self::class, 'reverse' ] );
		self::register( 'slice', [ self::class, 'slice' ] );
		self::register( 'chunk', [ self::class, 'chunk' ] );
		self::register( 'flatten', [ self::class, 'flatten' ] );
		self::register( 'merge', [ self::class, 'merge' ] );

		// Aggregation operations
		self::register( 'count', [ self::class, 'count' ] );
		self::register( 'sum', [ self::class, 'sum' ] );
		self::register( 'average', [ self::class, 'average' ] );
		self::register( 'min', [ self::class, 'min' ] );
		self::register( 'max', [ self::class, 'max' ] );

		// String operations
		self::register( 'join', [ self::class, 'join' ] );
		self::register( 'split', [ self::class, 'split' ] );
		self::register( 'trim', [ self::class, 'trim' ] );
		self::register( 'uppercase', [ self::class, 'uppercase' ] );
		self::register( 'lowercase', [ self::class, 'lowercase' ] );
	}

	/**
	 * Register a transformation
	 *
	 * @param string   $name Transformation name
	 * @param callable $callback Transformation callback
	 * @return void
	 */
	public static function register( string $name, callable $callback ): void {
		self::$transformations[ $name ] = $callback;
	}

	/**
	 * Execute a transformation
	 *
	 * @param string $name Transformation name
	 * @param mixed  $data Data to transform
	 * @param array  $params Additional parameters
	 * @return mixed Transformed data
	 * @throws \InvalidArgumentException If transformation not found
	 */
	public static function execute( string $name, $data, array $params = [] ) {
		if ( ! isset( self::$transformations[ $name ] ) ) {
			throw new \InvalidArgumentException( "Unknown transformation: {$name}" );
		}

		return call_user_func( self::$transformations[ $name ], $data, $params );
	}

	/**
	 * Check if a transformation exists
	 *
	 * @param string $name Transformation name
	 * @return bool
	 */
	public static function has( string $name ): bool {
		return isset( self::$transformations[ $name ] );
	}

	/**
	 * Get all registered transformation names
	 *
	 * @return array<string>
	 */
	public static function get_all(): array {
		return array_keys( self::$transformations );
	}

	// ============================================================================
	// Built-in Transformations
	// ============================================================================

	/**
	 * Filter array based on condition
	 *
	 * @param array $data Array to filter
	 * @param array $params Filter parameters
	 * @return array Filtered array
	 */
	public static function filter( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Filter requires array input' );
		}

		if ( ! isset( $params['condition'] ) ) {
			// No condition = remove empty values
			return array_values( array_filter( $data ) );
		}

		$condition = $params['condition'];
		$field = $condition['field'] ?? null;
		$operator = $condition['operator'] ?? 'equals';
		$value = $condition['value'] ?? null;

		return array_values(
			array_filter(
				$data,
				function ( $item ) use ( $field, $operator, $value ) {
					$item_value = $field ? ( is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null ) ) : $item;
					return self::evaluate_condition( $item_value, $operator, $value );
				}
			)
		);
	}

	/**
	 * Map array values to a specific field
	 *
	 * @param array $data Array to map
	 * @param array $params Map parameters
	 * @return array Mapped array
	 */
	public static function map( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Map requires array input' );
		}

		$field = $params['field'] ?? null;
		if ( ! $field ) {
			throw new \InvalidArgumentException( 'Map requires "field" parameter' );
		}

		return array_map(
			function ( $item ) use ( $field ) {
				if ( is_array( $item ) ) {
					return $item[ $field ] ?? null;
				}
				if ( is_object( $item ) ) {
					return $item->$field ?? null;
				}
				return null;
			},
			$data
		);
	}

	/**
	 * Pluck a column from array of arrays/objects
	 *
	 * @param array $data Array to pluck from
	 * @param array $params Pluck parameters
	 * @return array Plucked values
	 */
	public static function pluck( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Pluck requires array input' );
		}

		$field = $params['field'] ?? null;
		if ( ! $field ) {
			throw new \InvalidArgumentException( 'Pluck requires "field" parameter' );
		}

		return array_values( array_column( $data, $field ) );
	}

	/**
	 * Get unique values
	 *
	 * @param array $data Array to filter
	 * @param array $params Parameters (unused)
	 * @return array Unique values
	 */
	public static function unique( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Unique requires array input' );
		}

		return array_values( array_unique( $data, SORT_REGULAR ) );
	}

	/**
	 * Sort array
	 *
	 * @param array $data Array to sort
	 * @param array $params Sort parameters
	 * @return array Sorted array
	 */
	public static function sort( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Sort requires array input' );
		}

		$direction = $params['direction'] ?? 'asc';
		$field = $params['field'] ?? null;

		if ( $field ) {
			// Sort by field
			usort(
				$data,
				function ( $a, $b ) use ( $field, $direction ) {
					$a_val = is_array( $a ) ? ( $a[ $field ] ?? null ) : ( $a->$field ?? null );
					$b_val = is_array( $b ) ? ( $b[ $field ] ?? null ) : ( $b->$field ?? null );
					$cmp = $a_val <=> $b_val;
					return $direction === 'desc' ? -$cmp : $cmp;
				}
			);
		} else {
			// Simple sort
			if ( $direction === 'desc' ) {
				rsort( $data );
			} else {
				sort( $data );
			}
		}

		return array_values( $data );
	}

	/**
	 * Reverse array
	 *
	 * @param array $data Array to reverse
	 * @param array $params Parameters (unused)
	 * @return array Reversed array
	 */
	public static function reverse( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Reverse requires array input' );
		}

		return array_values( array_reverse( $data ) );
	}

	/**
	 * Slice array
	 *
	 * @param array $data Array to slice
	 * @param array $params Slice parameters
	 * @return array Sliced array
	 */
	public static function slice( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Slice requires array input' );
		}

		$offset = $params['offset'] ?? 0;
		$length = $params['length'] ?? null;

		return array_values( array_slice( $data, $offset, $length ) );
	}

	/**
	 * Chunk array into smaller arrays
	 *
	 * @param array $data Array to chunk
	 * @param array $params Chunk parameters
	 * @return array Chunked array
	 */
	public static function chunk( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Chunk requires array input' );
		}

		$size = $params['size'] ?? null;
		if ( ! $size || $size < 1 ) {
			throw new \InvalidArgumentException( 'Chunk requires positive "size" parameter' );
		}

		return array_chunk( $data, $size );
	}

	/**
	 * Flatten multi-dimensional array
	 *
	 * @param array $data Array to flatten
	 * @param array $params Flatten parameters
	 * @return array Flattened array
	 */
	public static function flatten( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Flatten requires array input' );
		}

		$depth = $params['depth'] ?? null;

		return self::flatten_array( $data, $depth );
	}

	/**
	 * Merge arrays
	 *
	 * @param array $data Base array
	 * @param array $params Merge parameters
	 * @return array Merged array
	 */
	public static function merge( $data, array $params ): array {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Merge requires array input' );
		}

		$with = $params['with'] ?? [];
		if ( ! is_array( $with ) ) {
			throw new \InvalidArgumentException( 'Merge requires "with" parameter as array' );
		}

		return array_merge( $data, $with );
	}

	/**
	 * Count elements
	 *
	 * @param mixed $data Data to count
	 * @param array $params Parameters (unused)
	 * @return int Count
	 */
	public static function count( $data, array $params ): int {
		if ( is_array( $data ) || $data instanceof \Countable ) {
			return count( $data );
		}

		return 0;
	}

	/**
	 * Sum values
	 *
	 * @param array $data Array to sum
	 * @param array $params Sum parameters
	 * @return float|int Sum
	 */
	public static function sum( $data, array $params ) {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Sum requires array input' );
		}

		$field = $params['field'] ?? null;

		if ( $field ) {
			$values = array_map(
				function ( $item ) use ( $field ) {
					return is_array( $item ) ? ( $item[ $field ] ?? 0 ) : ( $item->$field ?? 0 );
				},
				$data
			);
			return array_sum( $values );
		}

		return array_sum( $data );
	}

	/**
	 * Calculate average
	 *
	 * @param array $data Array to average
	 * @param array $params Average parameters
	 * @return float Average
	 */
	public static function average( $data, array $params ): float {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return 0.0;
		}

		$sum = self::sum( $data, $params );
		return $sum / count( $data );
	}

	/**
	 * Get minimum value
	 *
	 * @param array $data Array to search
	 * @param array $params Min parameters
	 * @return mixed Minimum value
	 */
	public static function min( $data, array $params ) {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return null;
		}

		$field = $params['field'] ?? null;

		if ( $field ) {
			$values = array_map(
				function ( $item ) use ( $field ) {
					return is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null );
				},
				$data
			);
			return min( array_filter( $values, fn( $v ) => $v !== null ) );
		}

		return min( $data );
	}

	/**
	 * Get maximum value
	 *
	 * @param array $data Array to search
	 * @param array $params Max parameters
	 * @return mixed Maximum value
	 */
	public static function max( $data, array $params ) {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return null;
		}

		$field = $params['field'] ?? null;

		if ( $field ) {
			$values = array_map(
				function ( $item ) use ( $field ) {
					return is_array( $item ) ? ( $item[ $field ] ?? null ) : ( $item->$field ?? null );
				},
				$data
			);
			return max( array_filter( $values, fn( $v ) => $v !== null ) );
		}

		return max( $data );
	}

	/**
	 * Join array into string
	 *
	 * @param array $data Array to join
	 * @param array $params Join parameters
	 * @return string Joined string
	 */
	public static function join( $data, array $params ): string {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Join requires array input' );
		}

		$separator = $params['separator'] ?? ', ';
		return implode( $separator, $data );
	}

	/**
	 * Split string into array
	 *
	 * @param string $data String to split
	 * @param array  $params Split parameters
	 * @return array Split array
	 */
	public static function split( $data, array $params ): array {
		if ( ! is_string( $data ) ) {
			throw new \InvalidArgumentException( 'Split requires string input' );
		}

		$separator = $params['separator'] ?? ',';
		return explode( $separator, $data );
	}

	/**
	 * Trim string
	 *
	 * @param string $data String to trim
	 * @param array  $params Parameters (unused)
	 * @return string Trimmed string
	 */
	public static function trim( $data, array $params ): string {
		if ( ! is_string( $data ) ) {
			throw new \InvalidArgumentException( 'Trim requires string input' );
		}

		return trim( $data );
	}

	/**
	 * Convert to uppercase
	 *
	 * @param string $data String to convert
	 * @param array  $params Parameters (unused)
	 * @return string Uppercase string
	 */
	public static function uppercase( $data, array $params ): string {
		if ( ! is_string( $data ) ) {
			throw new \InvalidArgumentException( 'Uppercase requires string input' );
		}

		return strtoupper( $data );
	}

	/**
	 * Convert to lowercase
	 *
	 * @param string $data String to convert
	 * @param array  $params Parameters (unused)
	 * @return string Lowercase string
	 */
	public static function lowercase( $data, array $params ): string {
		if ( ! is_string( $data ) ) {
			throw new \InvalidArgumentException( 'Lowercase requires string input' );
		}

		return strtolower( $data );
	}

	// ============================================================================
	// Helper Methods
	// ============================================================================

	/**
	 * Evaluate a condition
	 *
	 * @param mixed  $value Value to test
	 * @param string $operator Comparison operator
	 * @param mixed  $compare Value to compare against
	 * @return bool Result
	 */
	private static function evaluate_condition( $value, string $operator, $compare ): bool {
		switch ( $operator ) {
			case 'equals':
			case '==':
			case '===':
				return $value === $compare;

			case 'not_equals':
			case '!=':
			case '!==':
				return $value !== $compare;

			case 'greater_than':
			case '>':
				return $value > $compare;

			case 'less_than':
			case '<':
				return $value < $compare;

			case 'greater_than_or_equal':
			case '>=':
				return $value >= $compare;

			case 'less_than_or_equal':
			case '<=':
				return $value <= $compare;

			case 'contains':
				return is_string( $value ) && str_contains( $value, $compare );

			case 'starts_with':
				return is_string( $value ) && str_starts_with( $value, $compare );

			case 'ends_with':
				return is_string( $value ) && str_ends_with( $value, $compare );

			case 'in':
				return is_array( $compare ) && in_array( $value, $compare, true );

			case 'not_in':
				return is_array( $compare ) && ! in_array( $value, $compare, true );

			case 'empty':
				return empty( $value );

			case 'not_empty':
				return ! empty( $value );

			case 'null':
				return $value === null;

			case 'not_null':
				return $value !== null;

			default:
				throw new \InvalidArgumentException( "Unknown operator: {$operator}" );
		}
	}

	/**
	 * Recursively flatten an array
	 *
	 * @param array    $array Array to flatten
	 * @param int|null $depth Maximum depth (null = unlimited)
	 * @param int      $current_depth Current recursion depth
	 * @return array Flattened array
	 */
	private static function flatten_array( array $array, ?int $depth = null, int $current_depth = 0 ): array {
		$result = [];

		foreach ( $array as $value ) {
			if ( is_array( $value ) && ( $depth === null || $current_depth < $depth ) ) {
				$result = array_merge( $result, self::flatten_array( $value, $depth, $current_depth + 1 ) );
			} else {
				$result[] = $value;
			}
		}

		return $result;
	}
}
