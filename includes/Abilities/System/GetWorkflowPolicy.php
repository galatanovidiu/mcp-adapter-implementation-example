<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetWorkflowPolicy implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-workflow-policy',
			array(
				'label'               => 'Get Workflow Policy',
				'description'         => 'Return workflow execution policy details, limits, and safety constraints.',
				'input_schema'        => array(
					'type' => 'object',
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'enabled', 'required_capability', 'allowed_prefixes', 'limits', 'file_mods' ),
					'properties' => array(
						'enabled'             => array( 'type' => 'boolean' ),
						'required_capability' => array( 'type' => 'string' ),
						'allowed_prefixes'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'limits'              => array(
							'type'       => 'object',
							'properties' => array(
								'max_steps'         => array( 'type' => 'integer' ),
								'max_payload_bytes' => array( 'type' => 'integer' ),
							),
						),
						'file_mods'           => array(
							'type'       => 'object',
							'properties' => array(
								'blocked'   => array( 'type' => 'boolean' ),
								'abilities' => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
						'notes'               => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'system',
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	public static function check_permission( ?array $input ): bool {
		$input = $input ?? [];
		return \current_user_can( 'manage_options' );
	}

	public static function execute( ?array $input ) {
		$input = $input ?? [];
		$enabled = \defined( 'MCP_ALLOW_WORKFLOWS' ) && \constant( 'MCP_ALLOW_WORKFLOWS' ) === true;
		$blocked = \defined( 'DISALLOW_FILE_MODS' ) && \constant( 'DISALLOW_FILE_MODS' ) === true;

		return array(
			'enabled'             => $enabled,
			'required_capability' => 'manage_options',
			'allowed_prefixes'    => ExecuteWorkflow::DEFAULT_ALLOWED_PREFIXES,
			'limits'              => array(
				'max_steps'         => ExecuteWorkflow::MAX_STEPS,
				'max_payload_bytes' => ExecuteWorkflow::MAX_PAYLOAD_BYTES,
			),
			'file_mods'           => array(
				'blocked'   => $blocked,
				'abilities' => ExecuteWorkflow::FILE_MOD_ABILITIES,
			),
			'notes'               => array(
				'Workflow execution is allowed only for users with manage_options.',
				'Workflow steps must target abilities within the allowed prefixes.',
				'Workflow steps cannot call core/execute-workflow recursively.',
			),
		);
	}
}
