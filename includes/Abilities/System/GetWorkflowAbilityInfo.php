<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetWorkflowAbilityInfo implements RegistersAbility {

	private const DETAIL_SUMMARY = 'summary';
	private const DETAIL_SCHEMA = 'schema';
	private const DETAIL_FULL = 'full';

	public static function register(): void {
		\wp_register_ability(
			'core/get-workflow-ability-info',
			array(
				'label'               => 'Get Workflow Ability Info',
				'description'         => 'Fetch detailed information for a workflow ability by name.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'ability' ),
					'properties' => array(
						'ability' => array(
							'type'        => 'string',
							'description' => 'Ability name (core/create-post) or tool name (core-create-post).',
						),
						'detail'  => array(
							'type'        => 'string',
							'enum'        => array( self::DETAIL_SUMMARY, self::DETAIL_SCHEMA, self::DETAIL_FULL ),
							'default'     => self::DETAIL_SUMMARY,
							'description' => 'Detail level to return: summary, schema, or full.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'ability', 'detail' ),
					'properties' => array(
						'detail'  => array( 'type' => 'string' ),
						'ability' => array(
							'type'       => 'object',
							'properties' => array(
								'name'        => array( 'type' => 'string' ),
								'label'       => array( 'type' => 'string' ),
								'summary'     => array( 'type' => 'string' ),
								'category'    => array( 'type' => 'string' ),
								'input_schema' => array( 'type' => 'object' ),
								'output_schema' => array( 'type' => 'object' ),
								'meta'        => array( 'type' => 'object' ),
							),
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

	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	public static function execute( array $input ) {
		$ability_name = isset( $input['ability'] ) ? (string) $input['ability'] : '';
		$detail       = isset( $input['detail'] ) ? (string) $input['detail'] : self::DETAIL_SUMMARY;

		if ( $ability_name === '' ) {
			return new \WP_Error( 'missing_ability', 'Ability name is required.' );
		}

		if ( ! in_array( $detail, array( self::DETAIL_SUMMARY, self::DETAIL_SCHEMA, self::DETAIL_FULL ), true ) ) {
			return new \WP_Error( 'invalid_detail', 'Detail must be summary, schema, or full.' );
		}

		$normalized = WorkflowAbilityCatalog::normalize_ability_name( $ability_name );
		$ability    = \wp_get_ability( $normalized );
		if ( ! $ability ) {
			return new \WP_Error( 'ability_not_found', 'Ability not found.' );
		}

		if ( ! WorkflowAbilityCatalog::is_ability_allowed( (string) $ability->get_name(), $ability ) ) {
			return new \WP_Error( 'ability_not_allowed', 'Ability is not allowed by workflow policy.' );
		}

		$summary = WorkflowAbilityCatalog::build_summary( $ability );

		if ( $detail !== self::DETAIL_SUMMARY ) {
			$summary['input_schema']  = method_exists( $ability, 'get_input_schema' ) ? $ability->get_input_schema() : array();
			$summary['output_schema'] = method_exists( $ability, 'get_output_schema' ) ? $ability->get_output_schema() : array();
		}

		if ( $detail === self::DETAIL_FULL ) {
			$summary['meta'] = method_exists( $ability, 'get_meta' ) ? $ability->get_meta() : array();
		}

		return array(
			'detail'  => $detail,
			'ability' => $summary,
		);
	}
}
