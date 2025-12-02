<?php
/**
 * Abstract Step
 *
 * Base implementation for all pipeline steps.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Abstract class AbstractStep
 *
 * Provides common functionality for all step types.
 */
abstract class AbstractStep implements StepInterface {
	/**
	 * Validate step configuration
	 *
	 * @param array $config Step configuration
	 * @return bool True if valid
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function validate( array $config ): bool {
		// Check required keys
		$missing = array_diff( $this->get_required_keys(), array_keys( $config ) );
		if ( ! empty( $missing ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Step type "%s" missing required keys: %s',
					$this->get_type(),
					implode( ', ', $missing )
				)
			);
		}

		// Check for unknown keys
		$allowed = array_merge( $this->get_required_keys(), $this->get_optional_keys(), [ 'type', 'output' ] );
		$unknown = array_diff( array_keys( $config ), $allowed );
		if ( ! empty( $unknown ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Step type "%s" has unknown keys: %s',
					$this->get_type(),
					implode( ', ', $unknown )
				)
			);
		}

		return true;
	}

	/**
	 * Get optional configuration keys for this step type
	 *
	 * @return array<string> Optional keys
	 */
	public function get_optional_keys(): array {
		return [ 'output', 'description' ];
	}

	/**
	 * Store step result in context if output variable is specified
	 *
	 * @param array          $config Step configuration
	 * @param ContextManager $context Execution context
	 * @param mixed          $result Step execution result
	 * @return mixed The result (passthrough)
	 */
	protected function store_result( array $config, ContextManager $context, $result ) {
		if ( isset( $config['output'] ) && is_string( $config['output'] ) ) {
			$context->set( $config['output'], $result );
		}
		return $result;
	}

	/**
	 * Resolve input value from context or return literal value
	 *
	 * @param mixed          $value   Value to resolve (can be "$var" reference or literal)
	 * @param ContextManager $context Execution context
	 * @return mixed Resolved value
	 */
	protected function resolve_value( $value, ContextManager $context ) {
		// Handle variable references like "$posts" or "$post.title"
		if ( is_string( $value ) && str_starts_with( $value, '$' ) ) {
			return $context->resolve( $value );
		}

		// Handle arrays recursively
		if ( is_array( $value ) ) {
			return array_map( fn( $v ) => $this->resolve_value( $v, $context ), $value );
		}

		// Return literal values as-is
		return $value;
	}

	/**
	 * Resolve all inputs in configuration
	 *
	 * @param array          $inputs  Input configuration
	 * @param ContextManager $context Execution context
	 * @return array Resolved inputs
	 */
	protected function resolve_inputs( array $inputs, ContextManager $context ): array {
		$resolved = [];
		foreach ( $inputs as $key => $value ) {
			$resolved[ $key ] = $this->resolve_value( $value, $context );
		}
		return $resolved;
	}
}
