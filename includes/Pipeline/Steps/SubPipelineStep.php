<?php
/**
 * Sub-Pipeline Step
 *
 * Executes a nested pipeline.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Class SubPipelineStep
 *
 * Allows execution of nested pipelines for modularity.
 */
class SubPipelineStep extends AbstractStep {
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
	 * Execute sub-pipeline
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return mixed Result from sub-pipeline
	 * @throws \Exception If execution fails
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		// Get pipeline definition
		$pipeline = $config['pipeline'] ?? [];

		// Resolve inputs for sub-pipeline
		$inputs = isset( $config['inputs'] ) ? $this->resolve_inputs( $config['inputs'], $context ) : [];

		// Create new scope for sub-pipeline with inputs
		$context->push_scope( $inputs );

		try {
			// Execute sub-pipeline steps
			$result = null;
			if ( isset( $pipeline['steps'] ) ) {
				foreach ( $pipeline['steps'] as $step ) {
					$result = $this->executor->execute_step( $step, $context );
				}
			}

			return $this->store_result( $config, $context, $result );
		} finally {
			// Always pop scope
			$context->pop_scope();
		}
	}

	/**
	 * Get step type identifier
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'sub_pipeline';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'pipeline' ];
	}

	/**
	 * Get optional configuration keys
	 *
	 * @return array<string>
	 */
	public function get_optional_keys(): array {
		return array_merge( parent::get_optional_keys(), [ 'inputs' ] );
	}
}
