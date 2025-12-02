<?php
/**
 * Try-Catch Step
 *
 * Executes steps with error handling.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Class TryCatchStep
 *
 * Implements try/catch error handling for pipelines.
 */
class TryCatchStep extends AbstractStep {
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
	 * Execute with error handling
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return mixed Result from try or catch block
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		$try_steps = $config['try'] ?? [];
		$catch_steps = $config['catch'] ?? [];
		$finally_steps = $config['finally'] ?? [];

		$result = null;
		$error = null;

		// Execute try block
		try {
			foreach ( $try_steps as $step ) {
				$result = $this->executor->execute_step( $step, $context );
			}
		} catch ( \Exception $e ) {
			$error = $e;

			// Store error in context for catch block
			$context->set( 'error', [
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			] );

			// Execute catch block
			try {
				foreach ( $catch_steps as $step ) {
					$result = $this->executor->execute_step( $step, $context );
				}
			} catch ( \Exception $catch_error ) {
				// If catch block also fails, we still run finally then rethrow
				$error = $catch_error;
			}
		} finally {
			// Always execute finally block
			foreach ( $finally_steps as $step ) {
				try {
					$this->executor->execute_step( $step, $context );
				} catch ( \Exception $finally_error ) {
					// Log but don't throw - finally blocks shouldn't break flow
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Error in finally block: ' . $finally_error->getMessage() );
					}
				}
			}

			// If there was an error and catch didn't handle it, rethrow
			if ( $error && empty( $catch_steps ) ) {
				throw $error;
			}
		}

		// Store result if output variable specified
		return $this->store_result( $config, $context, $result );
	}

	/**
	 * Get step type identifier
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'try_catch';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'try' ];
	}

	/**
	 * Get optional configuration keys
	 *
	 * @return array<string>
	 */
	public function get_optional_keys(): array {
		return array_merge( parent::get_optional_keys(), [ 'catch', 'finally' ] );
	}
}
