<?php
/**
 * Loop Step
 *
 * Iterates over an array executing steps for each item.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Class LoopStep
 *
 * Implements foreach-style iteration over arrays.
 */
class LoopStep extends AbstractStep {
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
	 * Execute the loop
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return array Results from all iterations
	 * @throws \Exception If execution fails
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		// Get array to iterate
		$items = $this->resolve_value( $config['input'], $context );
		if ( ! is_array( $items ) ) {
			throw new \InvalidArgumentException( 'Loop input must be an array' );
		}

		// Get variable names for item and index
		$item_var = $config['itemVar'] ?? 'item';
		$index_var = $config['indexVar'] ?? 'index';
		$steps = $config['steps'] ?? [];

		// Execute steps for each item
		$results = [];
		foreach ( $items as $index => $item ) {
			// Create new scope for loop iteration
			$context->push_scope( [
				$item_var => $item,
				$index_var => $index,
			] );

			try {
				// Execute all steps in this iteration
				$iteration_result = null;
				foreach ( $steps as $step ) {
					$iteration_result = $this->executor->execute_step( $step, $context );
				}
				$results[] = $iteration_result;
			} finally {
				// Always pop scope, even if error occurs
				$context->pop_scope();
			}
		}

		// Store results if output variable specified
		return $this->store_result( $config, $context, $results );
	}

	/**
	 * Get step type identifier
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'loop';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'input', 'steps' ];
	}

	/**
	 * Get optional configuration keys
	 *
	 * @return array<string>
	 */
	public function get_optional_keys(): array {
		return array_merge( parent::get_optional_keys(), [ 'itemVar', 'indexVar' ] );
	}
}
