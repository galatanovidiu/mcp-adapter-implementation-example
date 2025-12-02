<?php
/**
 * Conditional Step
 *
 * Executes steps based on conditions (if/else).
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;
use OvidiuGalatan\McpAdapterExample\Pipeline\Transformations\TransformationRegistry;

/**
 * Class ConditionalStep
 *
 * Implements if/then/else logic for pipeline control flow.
 */
class ConditionalStep extends AbstractStep {
	/**
	 * Pipeline executor instance
	 *
	 * @var \OvidiuGalatan\McpAdapterExample\Pipeline\PipelineExecutor
	 */
	private $executor;

	/**
	 * Set pipeline executor
	 *
	 * @param \OvidiuGalatan\McpAdapterExample\Pipeline\PipelineExecutor $executor Executor instance
	 * @return void
	 */
	public function set_executor( $executor ): void {
		$this->executor = $executor;
	}

	/**
	 * Execute the conditional logic
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return mixed Result from executed branch
	 * @throws \Exception If execution fails
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		// Evaluate condition
		$condition_met = $this->evaluate_condition( $config['condition'], $context );

		// Execute appropriate branch
		if ( $condition_met && isset( $config['then'] ) ) {
			return $this->execute_steps( $config['then'], $context );
		} elseif ( ! $condition_met && isset( $config['else'] ) ) {
			return $this->execute_steps( $config['else'], $context );
		}

		return null;
	}

	/**
	 * Evaluate a condition
	 *
	 * @param array          $condition Condition configuration
	 * @param ContextManager $context   Execution context
	 * @return bool True if condition met
	 */
	private function evaluate_condition( array $condition, ContextManager $context ): bool {
		$field = $this->resolve_value( $condition['field'], $context );
		$operator = $condition['operator'] ?? 'equals';
		$value = isset( $condition['value'] ) ? $this->resolve_value( $condition['value'], $context ) : null;

		switch ( $operator ) {
			case 'equals':
			case '==':
			case '===':
				return $field === $value;

			case 'not_equals':
			case '!=':
			case '!==':
				return $field !== $value;

			case 'greater_than':
			case '>':
				return $field > $value;

			case 'less_than':
			case '<':
				return $field < $value;

			case 'greater_than_or_equal':
			case '>=':
				return $field >= $value;

			case 'less_than_or_equal':
			case '<=':
				return $field <= $value;

			case 'contains':
				return is_string( $field ) && is_string( $value ) && str_contains( $field, $value );

			case 'starts_with':
				return is_string( $field ) && is_string( $value ) && str_starts_with( $field, $value );

			case 'ends_with':
				return is_string( $field ) && is_string( $value ) && str_ends_with( $field, $value );

			case 'in':
				return is_array( $value ) && in_array( $field, $value, true );

			case 'not_in':
				return is_array( $value ) && ! in_array( $field, $value, true );

			case 'empty':
				return empty( $field );

			case 'not_empty':
				return ! empty( $field );

			case 'null':
				return $field === null;

			case 'not_null':
				return $field !== null;

			case 'and':
				// Multiple conditions with AND
				if ( ! isset( $condition['conditions'] ) || ! is_array( $condition['conditions'] ) ) {
					throw new \InvalidArgumentException( 'AND operator requires "conditions" array' );
				}
				foreach ( $condition['conditions'] as $sub_condition ) {
					if ( ! $this->evaluate_condition( $sub_condition, $context ) ) {
						return false;
					}
				}
				return true;

			case 'or':
				// Multiple conditions with OR
				if ( ! isset( $condition['conditions'] ) || ! is_array( $condition['conditions'] ) ) {
					throw new \InvalidArgumentException( 'OR operator requires "conditions" array' );
				}
				foreach ( $condition['conditions'] as $sub_condition ) {
					if ( $this->evaluate_condition( $sub_condition, $context ) ) {
						return true;
					}
				}
				return false;

			default:
				throw new \InvalidArgumentException( "Unknown operator: {$operator}" );
		}
	}

	/**
	 * Execute a list of steps
	 *
	 * @param array          $steps   Steps to execute
	 * @param ContextManager $context Execution context
	 * @return mixed Result from last step
	 */
	private function execute_steps( array $steps, ContextManager $context ) {
		if ( ! $this->executor ) {
			throw new \RuntimeException( 'Pipeline executor not set on ConditionalStep' );
		}

		$result = null;
		foreach ( $steps as $step ) {
			$result = $this->executor->execute_step( $step, $context );
		}
		return $result;
	}

	/**
	 * Get step type identifier
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'conditional';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'condition' ];
	}

	/**
	 * Get optional configuration keys
	 *
	 * @return array<string>
	 */
	public function get_optional_keys(): array {
		return array_merge( parent::get_optional_keys(), [ 'then', 'else' ] );
	}
}
