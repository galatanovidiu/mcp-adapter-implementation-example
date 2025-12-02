<?php
/**
 * Step Interface
 *
 * Defines the contract for all pipeline steps.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Interface StepInterface
 *
 * All pipeline steps must implement this interface.
 */
interface StepInterface {
	/**
	 * Execute the step
	 *
	 * @param array          $config  Step configuration from pipeline JSON
	 * @param ContextManager $context Execution context with variables
	 * @return mixed Step execution result
	 * @throws \Exception If step execution fails
	 */
	public function execute( array $config, ContextManager $context );

	/**
	 * Validate step configuration
	 *
	 * @param array $config Step configuration
	 * @return bool True if valid
	 * @throws \InvalidArgumentException If configuration is invalid
	 */
	public function validate( array $config ): bool;

	/**
	 * Get step type identifier
	 *
	 * @return string Step type (e.g., 'ability', 'transform', 'conditional')
	 */
	public function get_type(): string;

	/**
	 * Get required configuration keys for this step type
	 *
	 * @return array<string> Required keys
	 */
	public function get_required_keys(): array;

	/**
	 * Get optional configuration keys for this step type
	 *
	 * @return array<string> Optional keys
	 */
	public function get_optional_keys(): array;
}
