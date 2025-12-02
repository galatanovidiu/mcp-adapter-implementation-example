<?php
/**
 * Parallel Step
 *
 * Executes multiple steps in parallel (simulated).
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Class ParallelStep
 *
 * Executes independent steps concurrently.
 * Note: PHP doesn't have true parallelism without extensions,
 * so this is simulated by executing steps sequentially but
 * collecting results as if they were parallel.
 */
class ParallelStep extends AbstractStep {
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
	 * Execute steps in parallel
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return array Results from all parallel steps
	 * @throws \Exception If execution fails
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		$steps = $config['steps'] ?? [];
		$results = [];

		// Execute each step and collect results
		// In a future enhancement, this could use async processing
		foreach ( $steps as $key => $step ) {
			try {
				$result = $this->executor->execute_step( $step, $context );

				// Store with key if step has output variable
				if ( isset( $step['output'] ) ) {
					$results[ $step['output'] ] = $result;
				} else {
					$results[ $key ] = $result;
				}
			} catch ( \Exception $e ) {
				// In parallel execution, we collect errors but continue
				$results[ $key ] = [
					'error' => $e->getMessage(),
					'step' => $step,
				];
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
		return 'parallel';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'steps' ];
	}
}
