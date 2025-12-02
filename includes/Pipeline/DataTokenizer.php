<?php
/**
 * Data Tokenizer
 *
 * Tokenizes sensitive data to prevent it from being exposed in pipeline context.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline;

/**
 * Class DataTokenizer
 *
 * Replaces sensitive fields with tokens for privacy.
 */
class DataTokenizer {
	/**
	 * Token to value mapping
	 *
	 * @var array<string,mixed>
	 */
	private array $token_map = [];

	/**
	 * Sensitive field patterns to tokenize
	 *
	 * @var array<string>
	 */
	private array $sensitive_patterns = [
		'*password*',
		'*_pass',
		'*_pwd',
		'*secret*',
		'*token',
		'*api_key*',
		'*private_key*',
		'user_email',
		'billing_*',
		'payment_*',
		'credit_card*',
		'ssn',
		'social_security*',
	];

	/**
	 * Constructor
	 *
	 * @param array<string> $additional_patterns Additional field patterns to tokenize
	 */
	public function __construct( array $additional_patterns = [] ) {
		$this->sensitive_patterns = array_merge( $this->sensitive_patterns, $additional_patterns );
	}

	/**
	 * Tokenize sensitive fields in data
	 *
	 * @param mixed $data Data to tokenize (can be array, object, or scalar)
	 * @return mixed Tokenized data
	 */
	public function tokenize( $data ) {
		if ( is_array( $data ) ) {
			return $this->tokenize_array( $data );
		}

		if ( is_object( $data ) ) {
			return $this->tokenize_object( $data );
		}

		return $data;
	}

	/**
	 * Tokenize array recursively
	 *
	 * @param array $data Array to tokenize
	 * @return array Tokenized array
	 */
	private function tokenize_array( array $data ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			if ( $this->is_sensitive_field( $key ) ) {
				$token = $this->generate_token();
				$this->token_map[ $token ] = $value;
				$result[ $key ] = $token;
			} elseif ( is_array( $value ) ) {
				$result[ $key ] = $this->tokenize_array( $value );
			} elseif ( is_object( $value ) ) {
				$result[ $key ] = $this->tokenize_object( $value );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Tokenize object recursively
	 *
	 * @param object $data Object to tokenize
	 * @return object Tokenized object
	 */
	private function tokenize_object( object $data ): object {
		$result = clone $data;

		foreach ( get_object_vars( $result ) as $key => $value ) {
			if ( $this->is_sensitive_field( $key ) ) {
				$token = $this->generate_token();
				$this->token_map[ $token ] = $value;
				$result->$key = $token;
			} elseif ( is_array( $value ) ) {
				$result->$key = $this->tokenize_array( $value );
			} elseif ( is_object( $value ) ) {
				$result->$key = $this->tokenize_object( $value );
			}
		}

		return $result;
	}

	/**
	 * Detokenize data before sending to abilities
	 *
	 * @param mixed $data Data to detokenize
	 * @return mixed Detokenized data
	 */
	public function detokenize( $data ) {
		if ( is_array( $data ) ) {
			return $this->detokenize_array( $data );
		}

		if ( is_object( $data ) ) {
			return $this->detokenize_object( $data );
		}

		if ( is_string( $data ) && isset( $this->token_map[ $data ] ) ) {
			return $this->token_map[ $data ];
		}

		return $data;
	}

	/**
	 * Detokenize array recursively
	 *
	 * @param array $data Array to detokenize
	 * @return array Detokenized array
	 */
	private function detokenize_array( array $data ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) && isset( $this->token_map[ $value ] ) ) {
				$result[ $key ] = $this->token_map[ $value ];
			} elseif ( is_array( $value ) ) {
				$result[ $key ] = $this->detokenize_array( $value );
			} elseif ( is_object( $value ) ) {
				$result[ $key ] = $this->detokenize_object( $value );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Detokenize object recursively
	 *
	 * @param object $data Object to detokenize
	 * @return object Detokenized object
	 */
	private function detokenize_object( object $data ): object {
		$result = clone $data;

		foreach ( get_object_vars( $result ) as $key => $value ) {
			if ( is_string( $value ) && isset( $this->token_map[ $value ] ) ) {
				$result->$key = $this->token_map[ $value ];
			} elseif ( is_array( $value ) ) {
				$result->$key = $this->detokenize_array( $value );
			} elseif ( is_object( $value ) ) {
				$result->$key = $this->detokenize_object( $value );
			}
		}

		return $result;
	}

	/**
	 * Check if a field name matches sensitive patterns
	 *
	 * @param string $field_name Field name
	 * @return bool True if sensitive
	 */
	private function is_sensitive_field( string $field_name ): bool {
		$field_lower = strtolower( $field_name );

		foreach ( $this->sensitive_patterns as $pattern ) {
			$pattern_lower = strtolower( $pattern );

			// Convert wildcard pattern to regex
			$regex_pattern = str_replace( '*', '.*', preg_quote( $pattern_lower, '/' ) );

			if ( preg_match( "/^{$regex_pattern}$/", $field_lower ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate a unique token
	 *
	 * @return string Token
	 */
	private function generate_token(): string {
		return 'TOKEN_' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Clear all tokens
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->token_map = [];
	}

	/**
	 * Get number of tokenized values
	 *
	 * @return int Count
	 */
	public function get_token_count(): int {
		return count( $this->token_map );
	}
}
