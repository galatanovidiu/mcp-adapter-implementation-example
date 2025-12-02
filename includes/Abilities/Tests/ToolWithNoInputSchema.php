<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Tests;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ToolWithNoInputSchema implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'test/no-input-schema',
			array(
				'label'               => 'Test Tool With No Input Schema',
				'description'         => 'A test tool that requires no input parameters and returns system information.',
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'message' ),
					'properties' => array(
						'success'     => array(
							'type'        => 'boolean',
							'description' => 'Whether the operation was successful.',
						),
						'message'     => array(
							'type'        => 'string',
							'description' => 'A message describing the result.',
						),
						'timestamp'   => array(
							'type'        => 'string',
							'description' => 'Current server timestamp.',
						),
						'php_version' => array(
							'type'        => 'string',
							'description' => 'PHP version.',
						),
						'wp_version'  => array(
							'type'        => 'string',
							'description' => 'WordPress version.',
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'system',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.5,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for executing the test tool.
	 *
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission() {
		// Allow any authenticated user to execute this test tool
		return true;
	}

	/**
	 * Execute the test tool with no input.
	 *
	 * @param array $input Input parameters (empty for this tool).
	 * @return array Result array.
	 */
	public static function execute( ) {
		global $wp_version;

		return array(
			'success'     => true,
			'message'     => 'Test tool executed successfully with no input parameters.',
			'timestamp'   => \current_time( 'mysql' ),
			'php_version' => \phpversion(),
			'wp_version'  => $wp_version,
		);
	}
}
