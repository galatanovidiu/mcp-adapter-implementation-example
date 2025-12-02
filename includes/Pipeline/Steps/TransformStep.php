<?php
/**
 * Transform Step
 *
 * Executes data transformations.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;
use OvidiuGalatan\McpAdapterExample\Pipeline\Transformations\TransformationRegistry;

/**
 * Class TransformStep
 *
 * Executes data transformations like filter, map, pluck, etc.
 */
class TransformStep extends AbstractStep {
	/**
	 * Execute the transformation
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return mixed Transformation result
	 * @throws \Exception If transformation fails
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		$operation = $config['operation'];
		$input = $this->resolve_value( $config['input'], $context );
		$params = isset( $config['params'] ) ? $this->resolve_inputs( $config['params'], $context ) : [];

		// Execute transformation
		$result = TransformationRegistry::execute( $operation, $input, $params );

		// Store result if output variable specified
		return $this->store_result( $config, $context, $result );
	}

	/**
	 * Get step type identifier
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'transform';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'operation', 'input' ];
	}

	/**
	 * Get optional configuration keys
	 *
	 * @return array<string>
	 */
	public function get_optional_keys(): array {
		return array_merge( parent::get_optional_keys(), [ 'params' ] );
	}
}
