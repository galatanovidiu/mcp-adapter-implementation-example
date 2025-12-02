<?php
/**
 * Pipeline Executor
 *
 * Core engine for executing declarative pipelines.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline;

use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\StepInterface;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\AbilityStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\TransformStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\ConditionalStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\LoopStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\ParallelStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\TryCatchStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Steps\SubPipelineStep;
use OvidiuGalatan\McpAdapterExample\Pipeline\Transformations\TransformationRegistry;

/**
 * Class PipelineExecutor
 *
 * Executes declarative pipelines with advanced control flow.
 */
class PipelineExecutor {
	/**
	 * Registered step types
	 *
	 * @var array<string,StepInterface>
	 */
	private array $step_types = [];

	/**
	 * Execution statistics
	 *
	 * @var array
	 */
	private array $stats = [];

	/**
	 * Resource limits
	 *
	 * @var array
	 */
	private array $limits = [
		'max_steps' => 1000,
		'max_depth' => 10,
		'timeout' => 300, // 5 minutes
	];

	/**
	 * Current execution depth
	 *
	 * @var int
	 */
	private int $current_depth = 0;

	/**
	 * Execution start time
	 *
	 * @var float
	 */
	private float $start_time;

	/**
	 * Total steps executed
	 *
	 * @var int
	 */
	private int $steps_executed = 0;

	/**
	 * Constructor
	 *
	 * @param array $limits Optional resource limits override
	 */
	public function __construct( array $limits = [] ) {
		$this->limits = array_merge( $this->limits, $limits );
		$this->register_default_steps();
		TransformationRegistry::init();
	}

	/**
	 * Register default step types
	 *
	 * @return void
	 */
	private function register_default_steps(): void {
		$this->register_step( new AbilityStep() );
		$this->register_step( new TransformStep() );

		// Control flow steps need executor reference
		$conditional = new ConditionalStep();
		$conditional->set_executor( $this );
		$this->register_step( $conditional );

		$loop = new LoopStep();
		$loop->set_executor( $this );
		$this->register_step( $loop );

		$parallel = new ParallelStep();
		$parallel->set_executor( $this );
		$this->register_step( $parallel );

		$try_catch = new TryCatchStep();
		$try_catch->set_executor( $this );
		$this->register_step( $try_catch );

		$sub_pipeline = new SubPipelineStep();
		$sub_pipeline->set_executor( $this );
		$this->register_step( $sub_pipeline );
	}

	/**
	 * Register a step type
	 *
	 * @param StepInterface $step Step implementation
	 * @return void
	 */
	public function register_step( StepInterface $step ): void {
		$this->step_types[ $step->get_type() ] = $step;
	}

	/**
	 * Execute a pipeline
	 *
	 * @param array $pipeline Pipeline definition
	 * @param array $initial_context Initial context variables
	 * @return array Execution result with data and statistics
	 * @throws \Exception If pipeline execution fails
	 */
	public function execute( array $pipeline, array $initial_context = [] ): array {
		$this->start_time = microtime( true );
		$this->steps_executed = 0;
		$this->current_depth = 0;

		// Initialize statistics
		$this->stats = [
			'steps_executed' => 0,
			'steps_by_type' => [],
			'errors' => [],
			'start_time' => $this->start_time,
			'end_time' => null,
			'duration' => null,
			'memory_peak' => 0,
		];

		// Validate pipeline structure
		if ( ! isset( $pipeline['steps'] ) || ! is_array( $pipeline['steps'] ) ) {
			throw new \InvalidArgumentException( 'Pipeline must have "steps" array' );
		}

		// Create context manager
		$context = new ContextManager( $initial_context );

		try {
			// Execute all steps
			$result = null;
			foreach ( $pipeline['steps'] as $index => $step ) {
				$result = $this->execute_step( $step, $context );
			}

			// Finalize statistics
			$this->stats['end_time'] = microtime( true );
			$this->stats['duration'] = $this->stats['end_time'] - $this->stats['start_time'];
			$this->stats['memory_peak'] = memory_get_peak_usage( true );
			$this->stats['success'] = true;

			return [
				'success' => true,
				'result' => $result,
				'context' => $context->get_all(),
				'stats' => $this->stats,
			];
		} catch ( \Exception $e ) {
			// Record error
			$this->stats['end_time'] = microtime( true );
			$this->stats['duration'] = $this->stats['end_time'] - $this->stats['start_time'];
			$this->stats['memory_peak'] = memory_get_peak_usage( true );
			$this->stats['success'] = false;
			$this->stats['error'] = [
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			];

			throw $e;
		}
	}

	/**
	 * Execute a single step
	 *
	 * This method is public so control flow steps can recursively execute steps.
	 *
	 * @param array          $step    Step configuration
	 * @param ContextManager $context Execution context
	 * @return mixed Step result
	 * @throws \Exception If step execution fails
	 */
	public function execute_step( array $step, ContextManager $context ) {
		// Check resource limits
		$this->check_limits();

		// Increment counters
		$this->steps_executed++;
		$this->stats['steps_executed']++;

		// Get step type
		if ( ! isset( $step['type'] ) ) {
			throw new \InvalidArgumentException( 'Step must have "type" field' );
		}

		$type = $step['type'];

		// Track stats by type
		if ( ! isset( $this->stats['steps_by_type'][ $type ] ) ) {
			$this->stats['steps_by_type'][ $type ] = 0;
		}
		$this->stats['steps_by_type'][ $type ]++;

		// Get step implementation
		if ( ! isset( $this->step_types[ $type ] ) ) {
			throw new \InvalidArgumentException( "Unknown step type: {$type}" );
		}

		$step_impl = $this->step_types[ $type ];

		// Execute step
		try {
			$this->current_depth++;
			$result = $step_impl->execute( $step, $context );
			$this->current_depth--;
			return $result;
		} catch ( \Exception $e ) {
			$this->current_depth--;
			// Add step context to error
			$this->stats['errors'][] = [
				'step_type' => $type,
				'step_config' => $step,
				'error' => $e->getMessage(),
			];
			throw new \RuntimeException(
				"Error in {$type} step: {$e->getMessage()}",
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Check resource limits
	 *
	 * @return void
	 * @throws \RuntimeException If limits exceeded
	 */
	private function check_limits(): void {
		// Check step count
		if ( $this->steps_executed >= $this->limits['max_steps'] ) {
			throw new \RuntimeException(
				sprintf(
					'Pipeline exceeded maximum step count (%d)',
					$this->limits['max_steps']
				)
			);
		}

		// Check depth
		if ( $this->current_depth >= $this->limits['max_depth'] ) {
			throw new \RuntimeException(
				sprintf(
					'Pipeline exceeded maximum depth (%d)',
					$this->limits['max_depth']
				)
			);
		}

		// Check timeout
		$elapsed = microtime( true ) - $this->start_time;
		if ( $elapsed >= $this->limits['timeout'] ) {
			throw new \RuntimeException(
				sprintf(
					'Pipeline exceeded timeout (%.2f seconds)',
					$this->limits['timeout']
				)
			);
		}
	}

	/**
	 * Get execution statistics
	 *
	 * @return array Statistics
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	/**
	 * Get registered step types
	 *
	 * @return array<string> Step type names
	 */
	public function get_step_types(): array {
		return array_keys( $this->step_types );
	}

	/**
	 * Get resource limits
	 *
	 * @return array Limits configuration
	 */
	public function get_limits(): array {
		return $this->limits;
	}
}
