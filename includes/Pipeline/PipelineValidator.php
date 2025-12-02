<?php
/**
 * Pipeline Validator
 *
 * Validates pipeline structure and configuration.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline;

/**
 * Class PipelineValidator
 *
 * Validates pipeline definitions before execution.
 */
class PipelineValidator {
	/**
	 * Validation errors
	 *
	 * @var array<string>
	 */
	private array $errors = [];

	/**
	 * Valid step types
	 *
	 * @var array<string>
	 */
	private array $valid_types = [
		'ability',
		'transform',
		'conditional',
		'loop',
		'parallel',
		'try_catch',
		'sub_pipeline',
	];

	/**
	 * Validate a pipeline
	 *
	 * @param array $pipeline Pipeline definition
	 * @return bool True if valid
	 */
	public function validate( array $pipeline ): bool {
		$this->errors = [];

		// Check required fields
		if ( ! isset( $pipeline['steps'] ) ) {
			$this->errors[] = 'Pipeline must have "steps" field';
			return false;
		}

		if ( ! is_array( $pipeline['steps'] ) ) {
			$this->errors[] = 'Pipeline "steps" must be an array';
			return false;
		}

		if ( empty( $pipeline['steps'] ) ) {
			$this->errors[] = 'Pipeline must have at least one step';
			return false;
		}

		// Validate each step
		foreach ( $pipeline['steps'] as $index => $step ) {
			$this->validate_step( $step, "steps[{$index}]" );
		}

		// Check for circular dependencies
		$this->check_circular_dependencies( $pipeline );

		return empty( $this->errors );
	}

	/**
	 * Validate a single step
	 *
	 * @param mixed  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_step( $step, string $path ): void {
		if ( ! is_array( $step ) ) {
			$this->errors[] = "{$path}: Step must be an array";
			return;
		}

		// Check required type field
		if ( ! isset( $step['type'] ) ) {
			$this->errors[] = "{$path}: Step must have 'type' field";
			return;
		}

		if ( ! is_string( $step['type'] ) ) {
			$this->errors[] = "{$path}: Step 'type' must be a string";
			return;
		}

		// Validate step type
		if ( ! in_array( $step['type'], $this->valid_types, true ) ) {
			$this->errors[] = "{$path}: Unknown step type '{$step['type']}'";
			return;
		}

		// Validate type-specific requirements
		switch ( $step['type'] ) {
			case 'ability':
				$this->validate_ability_step( $step, $path );
				break;

			case 'transform':
				$this->validate_transform_step( $step, $path );
				break;

			case 'conditional':
				$this->validate_conditional_step( $step, $path );
				break;

			case 'loop':
				$this->validate_loop_step( $step, $path );
				break;

			case 'parallel':
				$this->validate_parallel_step( $step, $path );
				break;

			case 'try_catch':
				$this->validate_try_catch_step( $step, $path );
				break;

			case 'sub_pipeline':
				$this->validate_sub_pipeline_step( $step, $path );
				break;
		}
	}

	/**
	 * Validate ability step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_ability_step( array $step, string $path ): void {
		if ( ! isset( $step['ability'] ) ) {
			$this->errors[] = "{$path}: Ability step must have 'ability' field";
			return;
		}

		if ( ! is_string( $step['ability'] ) ) {
			$this->errors[] = "{$path}: Ability name must be a string";
			return;
		}

		// Validate ability exists (if WordPress is available)
		if ( function_exists( 'wp_get_ability' ) ) {
			$ability = wp_get_ability( $step['ability'] );
			if ( ! $ability ) {
				$this->errors[] = "{$path}: Ability '{$step['ability']}' not found";
			}
		}

		// Validate input if present
		if ( isset( $step['input'] ) && ! is_array( $step['input'] ) ) {
			$this->errors[] = "{$path}: Ability 'input' must be an array";
		}
	}

	/**
	 * Validate transform step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_transform_step( array $step, string $path ): void {
		if ( ! isset( $step['operation'] ) ) {
			$this->errors[] = "{$path}: Transform step must have 'operation' field";
			return;
		}

		if ( ! is_string( $step['operation'] ) ) {
			$this->errors[] = "{$path}: Transform 'operation' must be a string";
			return;
		}

		if ( ! isset( $step['input'] ) ) {
			$this->errors[] = "{$path}: Transform step must have 'input' field";
		}
	}

	/**
	 * Validate conditional step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_conditional_step( array $step, string $path ): void {
		if ( ! isset( $step['condition'] ) ) {
			$this->errors[] = "{$path}: Conditional step must have 'condition' field";
			return;
		}

		if ( ! is_array( $step['condition'] ) ) {
			$this->errors[] = "{$path}: Condition must be an array";
			return;
		}

		// Validate then/else branches
		if ( isset( $step['then'] ) ) {
			if ( ! is_array( $step['then'] ) ) {
				$this->errors[] = "{$path}: 'then' branch must be an array of steps";
			} else {
				foreach ( $step['then'] as $index => $sub_step ) {
					$this->validate_step( $sub_step, "{$path}.then[{$index}]" );
				}
			}
		}

		if ( isset( $step['else'] ) ) {
			if ( ! is_array( $step['else'] ) ) {
				$this->errors[] = "{$path}: 'else' branch must be an array of steps";
			} else {
				foreach ( $step['else'] as $index => $sub_step ) {
					$this->validate_step( $sub_step, "{$path}.else[{$index}]" );
				}
			}
		}
	}

	/**
	 * Validate loop step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_loop_step( array $step, string $path ): void {
		if ( ! isset( $step['input'] ) ) {
			$this->errors[] = "{$path}: Loop step must have 'input' field";
		}

		if ( ! isset( $step['steps'] ) ) {
			$this->errors[] = "{$path}: Loop step must have 'steps' field";
			return;
		}

		if ( ! is_array( $step['steps'] ) ) {
			$this->errors[] = "{$path}: Loop 'steps' must be an array";
			return;
		}

		// Validate loop body steps
		foreach ( $step['steps'] as $index => $sub_step ) {
			$this->validate_step( $sub_step, "{$path}.steps[{$index}]" );
		}
	}

	/**
	 * Validate parallel step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_parallel_step( array $step, string $path ): void {
		if ( ! isset( $step['steps'] ) ) {
			$this->errors[] = "{$path}: Parallel step must have 'steps' field";
			return;
		}

		if ( ! is_array( $step['steps'] ) ) {
			$this->errors[] = "{$path}: Parallel 'steps' must be an array";
			return;
		}

		// Validate parallel steps
		foreach ( $step['steps'] as $index => $sub_step ) {
			$this->validate_step( $sub_step, "{$path}.steps[{$index}]" );
		}
	}

	/**
	 * Validate try-catch step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_try_catch_step( array $step, string $path ): void {
		if ( ! isset( $step['try'] ) ) {
			$this->errors[] = "{$path}: Try-catch step must have 'try' field";
			return;
		}

		if ( ! is_array( $step['try'] ) ) {
			$this->errors[] = "{$path}: 'try' block must be an array";
			return;
		}

		// Validate try block
		foreach ( $step['try'] as $index => $sub_step ) {
			$this->validate_step( $sub_step, "{$path}.try[{$index}]" );
		}

		// Validate catch block if present
		if ( isset( $step['catch'] ) ) {
			if ( ! is_array( $step['catch'] ) ) {
				$this->errors[] = "{$path}: 'catch' block must be an array";
			} else {
				foreach ( $step['catch'] as $index => $sub_step ) {
					$this->validate_step( $sub_step, "{$path}.catch[{$index}]" );
				}
			}
		}

		// Validate finally block if present
		if ( isset( $step['finally'] ) ) {
			if ( ! is_array( $step['finally'] ) ) {
				$this->errors[] = "{$path}: 'finally' block must be an array";
			} else {
				foreach ( $step['finally'] as $index => $sub_step ) {
					$this->validate_step( $sub_step, "{$path}.finally[{$index}]" );
				}
			}
		}
	}

	/**
	 * Validate sub-pipeline step
	 *
	 * @param array  $step Step configuration
	 * @param string $path Path for error messages
	 * @return void
	 */
	private function validate_sub_pipeline_step( array $step, string $path ): void {
		if ( ! isset( $step['pipeline'] ) ) {
			$this->errors[] = "{$path}: Sub-pipeline step must have 'pipeline' field";
			return;
		}

		if ( ! is_array( $step['pipeline'] ) ) {
			$this->errors[] = "{$path}: Sub-pipeline must be an array";
			return;
		}

		// Recursively validate sub-pipeline
		$this->validate( $step['pipeline'] );
	}

	/**
	 * Check for circular dependencies in variable references
	 *
	 * @param array $pipeline Pipeline definition
	 * @return void
	 */
	private function check_circular_dependencies( array $pipeline ): void {
		// Build dependency graph
		$dependencies = [];
		$this->build_dependency_graph( $pipeline['steps'], $dependencies );

		// Detect cycles
		foreach ( array_keys( $dependencies ) as $var ) {
			$visited = [];
			if ( $this->has_cycle( $var, $dependencies, $visited ) ) {
				$this->errors[] = "Circular dependency detected involving variable: \${$var}";
			}
		}
	}

	/**
	 * Build dependency graph from pipeline
	 *
	 * @param array $steps Steps to analyze
	 * @param array $graph Dependency graph (output)
	 * @return void
	 */
	private function build_dependency_graph( array $steps, array &$graph ): void {
		foreach ( $steps as $step ) {
			// Extract output variable
			$output = isset( $step['output'] ) ? ltrim( $step['output'], '$' ) : null;

			if ( $output ) {
				// Extract input dependencies
				$inputs = $this->extract_variables( $step );
				$graph[ $output ] = $inputs;
			}

			// Recursively process nested steps
			$this->process_nested_steps( $step, $graph );
		}
	}

	/**
	 * Process nested steps in control flow structures
	 *
	 * @param array $step Step configuration
	 * @param array $graph Dependency graph (output)
	 * @return void
	 */
	private function process_nested_steps( array $step, array &$graph ): void {
		if ( isset( $step['steps'] ) && is_array( $step['steps'] ) ) {
			$this->build_dependency_graph( $step['steps'], $graph );
		}

		if ( isset( $step['then'] ) && is_array( $step['then'] ) ) {
			$this->build_dependency_graph( $step['then'], $graph );
		}

		if ( isset( $step['else'] ) && is_array( $step['else'] ) ) {
			$this->build_dependency_graph( $step['else'], $graph );
		}

		if ( isset( $step['try'] ) && is_array( $step['try'] ) ) {
			$this->build_dependency_graph( $step['try'], $graph );
		}

		if ( isset( $step['catch'] ) && is_array( $step['catch'] ) ) {
			$this->build_dependency_graph( $step['catch'], $graph );
		}

		if ( isset( $step['pipeline']['steps'] ) && is_array( $step['pipeline']['steps'] ) ) {
			$this->build_dependency_graph( $step['pipeline']['steps'], $graph );
		}
	}

	/**
	 * Extract variable references from step configuration
	 *
	 * @param array $step Step configuration
	 * @return array<string> Variable names (without $ prefix)
	 */
	private function extract_variables( array $step ): array {
		$variables = [];
		$this->extract_variables_recursive( $step, $variables );
		return array_unique( $variables );
	}

	/**
	 * Recursively extract variable references
	 *
	 * @param mixed $value Value to search
	 * @param array $variables Output array
	 * @return void
	 */
	private function extract_variables_recursive( $value, array &$variables ): void {
		if ( is_string( $value ) && str_starts_with( $value, '$' ) ) {
			// Extract base variable name (before any property access)
			$var_name = ltrim( $value, '$' );
			$var_name = explode( '.', $var_name )[0];
			$var_name = explode( '[', $var_name )[0];
			$variables[] = $var_name;
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$this->extract_variables_recursive( $item, $variables );
			}
		}
	}

	/**
	 * Check for cycles in dependency graph
	 *
	 * @param string $var Current variable
	 * @param array  $graph Dependency graph
	 * @param array  $visited Visited nodes
	 * @return bool True if cycle detected
	 */
	private function has_cycle( string $var, array $graph, array &$visited ): bool {
		if ( in_array( $var, $visited, true ) ) {
			return true; // Cycle detected
		}

		if ( ! isset( $graph[ $var ] ) ) {
			return false; // No dependencies
		}

		$visited[] = $var;

		foreach ( $graph[ $var ] as $dependency ) {
			if ( $this->has_cycle( $dependency, $graph, $visited ) ) {
				return true;
			}
		}

		// Remove from visited on backtrack
		array_pop( $visited );
		return false;
	}

	/**
	 * Get validation errors
	 *
	 * @return array<string> Error messages
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get errors as formatted string
	 *
	 * @return string Error messages joined by newlines
	 */
	public function get_errors_string(): string {
		return implode( "\n", $this->errors );
	}
}
