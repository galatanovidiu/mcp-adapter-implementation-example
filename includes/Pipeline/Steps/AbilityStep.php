<?php
/**
 * Ability Step
 *
 * Executes a registered WordPress ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Pipeline\Steps
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Pipeline\Steps;

use OvidiuGalatan\McpAdapterExample\Pipeline\ContextManager;

/**
 * Class AbilityStep
 *
 * Executes a registered WordPress ability with the provided input.
 */
class AbilityStep extends AbstractStep {
	/**
	 * Execute the ability
	 *
	 * @param array          $config  Step configuration
	 * @param ContextManager $context Execution context
	 * @return mixed Ability execution result
	 * @throws \Exception If ability execution fails
	 */
	public function execute( array $config, ContextManager $context ) {
		$this->validate( $config );

		$ability_name = $config['ability'];

		// Check if ability exists
		if ( ! function_exists( 'wp_get_ability' ) ) {
			throw new \RuntimeException( 'WordPress Abilities API not available' );
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability || ! ( $ability instanceof \WP_Ability ) ) {
			throw new \RuntimeException( "Ability not found: {$ability_name}" );
		}

		// Resolve input from context
		$input = isset( $config['input'] ) ? $this->resolve_inputs( $config['input'], $context ) : null;

		// Execute ability using WP_Ability::execute() which handles validation and permissions
		$result = $ability->execute( $input );

		// Check for WP_Error
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException(
				sprintf(
					'Ability "%s" failed: %s',
					$ability_name,
					$result->get_error_message()
				)
			);
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
		return 'ability';
	}

	/**
	 * Get required configuration keys
	 *
	 * @return array<string>
	 */
	public function get_required_keys(): array {
		return [ 'ability' ];
	}

	/**
	 * Get optional configuration keys
	 *
	 * @return array<string>
	 */
	public function get_optional_keys(): array {
		return array_merge( parent::get_optional_keys(), [ 'input' ] );
	}
}
