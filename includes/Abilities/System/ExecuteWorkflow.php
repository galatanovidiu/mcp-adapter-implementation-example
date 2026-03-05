<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ExecuteWorkflow implements RegistersAbility {

	public const MAX_STEPS = 25;
	public const MAX_PAYLOAD_BYTES = 65536;
	private const DEFAULT_VERSION = '1.0';
	private const DEFAULT_MODE = 'execute';
	private const MODE_VALIDATE = 'validate';
	private const MODE_DRY_RUN = 'dry_run';
	private const MODE_EXECUTE = 'execute';
	public const DEFAULT_ALLOWED_PREFIXES = array( 'core/', 'woo/' );
	public const FILE_MOD_ABILITIES = array(
		'core/install-plugin',
		'core/delete-plugin',
		'core/install-theme',
		'core/delete-theme',
		'core/run-updates',
	);

	public static function register(): void {
		\wp_register_ability(
			'core/execute-workflow',
			array(
				'label'               => 'Execute Workflow',
				'description'         => 'Execute a structured workflow that orchestrates existing WordPress abilities.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'steps' ),
					'properties' => array(
						'version' => array(
							'type'        => 'string',
							'description' => 'Workflow schema version. Default: 1.0.',
							'default'     => self::DEFAULT_VERSION,
						),
						'mode'    => array(
							'type'        => 'string',
							'enum'        => array( self::MODE_VALIDATE, self::MODE_DRY_RUN, self::MODE_EXECUTE ),
							'description' => 'Execution mode: validate, dry_run, or execute.',
							'default'     => self::DEFAULT_MODE,
						),
						'allow'   => array(
							'type'        => 'array',
							'description' => 'Requested ability prefixes to allow. Server-side allowlist is authoritative.',
							'items'       => array( 'type' => 'string' ),
						),
						'steps'   => array(
							'type'        => 'array',
							'description' => 'Ordered workflow steps.',
							'minItems'    => 1,
							'items'       => array(
								'type'                 => 'object',
								'required'             => array( 'id', 'ability' ),
								'properties'           => array(
									'id'         => array( 'type' => 'string' ),
									'ability'    => array( 'type' => 'string' ),
									'args'       => array(
										'type'                 => 'object',
										'additionalProperties' => true,
									),
									'when'       => array(
										'type'                 => 'object',
										'additionalProperties' => true,
									),
									'on_error'   => array(
										'type'        => 'string',
										'enum'        => array( 'abort', 'continue' ),
										'default'     => 'abort',
									),
									'store'      => array( 'type' => 'string' ),
									'confirm'    => array( 'type' => 'boolean' ),
									'timeout_ms' => array( 'type' => 'integer' ),
								),
								'additionalProperties' => false,
							),
						),
						'return'  => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'context' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'status', 'mode', 'steps', 'workflow_id' ),
					'properties' => array(
						'status'      => array( 'type' => 'string' ),
						'mode'        => array( 'type' => 'string' ),
						'workflow_id' => array( 'type' => 'string' ),
						'steps'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'             => array( 'type' => 'string' ),
									'ability'        => array( 'type' => 'string' ),
									'status'         => array( 'type' => 'string' ),
									'duration_ms'    => array( 'type' => 'integer' ),
									'output'         => array(
										'type'                 => 'object',
										'additionalProperties' => true,
									),
									'error'          => array(
										'type'                 => 'object',
										'additionalProperties' => true,
									),
									'skipped_reason' => array( 'type' => 'string' ),
								),
							),
						),
						'return'      => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'warnings'    => array(
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
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.4,
						'readonly'             => false,
						'destructive'          => true,
						'idempotent'           => false,
						'requiresConfirmation' => true,
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
		return \current_user_can( 'manage_options' );
	}

	public static function execute( ?array $input ) {
		$input = $input ?? array();

		if ( ! \defined( 'MCP_ALLOW_WORKFLOWS' ) || \constant( 'MCP_ALLOW_WORKFLOWS' ) !== true ) {
			return self::error_response( 'workflow_disabled', 'Workflow execution is disabled by configuration.' );
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			return self::error_response( 'permission_denied', 'You do not have permission to execute workflows.' );
		}

		$workflow = self::normalize_workflow_input( $input );
		$errors   = self::validate_workflow_shape( $workflow );

		if ( ! empty( $errors ) ) {
			return self::error_response( 'invalid_workflow', implode( ' ', $errors ) );
		}

		$mode         = $workflow['mode'];
		$allowed      = self::resolve_allowed_prefixes( $workflow['allow'] );
		$steps_output = array();
		$warnings     = array();
		$context      = array(
			'input' => $workflow,
			'steps' => array(),
			'last'  => null,
		);
		$has_success  = false;
		$has_failure  = false;
		$has_skipped  = false;

		foreach ( $workflow['steps'] as $step ) {
			$step_id     = (string) ( $step['id'] ?? '' );
			$ability_raw = (string) ( $step['ability'] ?? '' );
			$start_time  = microtime( true );
			$step_result = array(
				'id'             => $step_id,
				'ability'        => $ability_raw,
				'status'         => 'skipped',
				'duration_ms'    => 0,
				'output'         => array(),
				'error'          => array(),
				'skipped_reason' => '',
			);

			$ability_name = WorkflowAbilityCatalog::normalize_ability_name( $ability_raw );
			$ability      = $ability_name !== '' ? \wp_get_ability( $ability_name ) : null;

			if ( ! $ability ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'ability_not_found',
					'message' => 'Ability not found: ' . $ability_raw,
				);
				$has_failure             = true;
				$steps_output[]           = self::finalize_step_result( $step_result, $start_time );
				$context['steps'][ $step_id ] = array();
				$context['last']              = array();

				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			$step_result['ability'] = $ability_name;

			if ( $ability_name === 'core/execute-workflow' ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'recursive_workflow',
					'message' => 'Workflows cannot call core/execute-workflow.',
				);
				$has_failure   = true;
				$steps_output[] = self::finalize_step_result( $step_result, $start_time );
				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			if ( ! WorkflowAbilityCatalog::is_ability_allowed( $ability_name, $ability, $allowed ) ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'ability_not_allowed',
					'message' => 'Ability is not allowed by workflow policy: ' . $ability_name,
				);
				$has_failure   = true;
				$steps_output[] = self::finalize_step_result( $step_result, $start_time );
				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			$args       = isset( $step['args'] ) && is_array( $step['args'] ) ? $step['args'] : array();
			$when       = isset( $step['when'] ) && is_array( $step['when'] ) ? $step['when'] : array();
			$ref_errors = array();

			$args = self::resolve_refs( $args, $context, $ref_errors, 'steps.' . $step_id . '.args' );
			$when = self::resolve_refs( $when, $context, $ref_errors, 'steps.' . $step_id . '.when', true );

			if ( ! empty( $ref_errors ) ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'missing_reference',
					'message' => implode( ' ', $ref_errors ),
				);
				$has_failure   = true;
				$steps_output[] = self::finalize_step_result( $step_result, $start_time );
				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			if ( ! empty( $when ) && ! self::evaluate_when( $when, $context, $warnings ) ) {
				$step_result['status']         = 'skipped';
				$step_result['skipped_reason'] = 'condition_not_met';
				$has_skipped                    = true;
				$steps_output[]                 = self::finalize_step_result( $step_result, $start_time );
				continue;
			}

			if ( self::requires_confirmation( $ability ) && empty( $step['confirm'] ) ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'confirmation_required',
					'message' => 'This step requires confirmation.',
				);
				$has_failure   = true;
				$steps_output[] = self::finalize_step_result( $step_result, $start_time );
				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			if ( self::is_file_mod_blocked( $ability_name ) ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'file_mods_disallowed',
					'message' => 'File modifications are disabled by DISALLOW_FILE_MODS.',
				);
				$has_failure   = true;
				$steps_output[] = self::finalize_step_result( $step_result, $start_time );
				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			if ( method_exists( $ability, 'has_permission' ) && ! $ability->has_permission( $args ) ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = array(
					'code'    => 'permission_denied',
					'message' => 'Permission denied for ability: ' . $ability_name,
				);
				$has_failure   = true;
				$steps_output[] = self::finalize_step_result( $step_result, $start_time );
				if ( ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
					break;
				}
				continue;
			}

			if ( $mode === self::MODE_VALIDATE ) {
				$step_result['status']         = 'success';
				$step_result['skipped_reason'] = 'validation_only';
				$has_success                   = true;
				$steps_output[]                = self::finalize_step_result( $step_result, $start_time );
				continue;
			}

			if ( $mode === self::MODE_DRY_RUN && ! self::should_execute_in_dry_run( $ability, $args ) ) {
				$step_result['status']         = 'skipped';
				$step_result['skipped_reason'] = 'dry_run';
				$has_skipped                   = true;
				$steps_output[]                = self::finalize_step_result( $step_result, $start_time );
				continue;
			}

			if ( $mode === self::MODE_DRY_RUN ) {
				$args = self::apply_dry_run_argument( $ability, $args );
			}

			$execution = self::execute_ability( $ability, $args );
			if ( $execution['status'] === 'failed' ) {
				$step_result['status'] = 'failed';
				$step_result['error']  = $execution['error'];
				$has_failure           = true;
			} else {
				$normalized_output     = self::normalize_step_output( $execution['output'] );
				$step_result['status'] = 'success';
				$step_result['output'] = $normalized_output;
				$has_success           = true;
				$context_output        = is_object( $normalized_output ) ? (array) $normalized_output : $normalized_output;
				$context['steps'][ $step_id ] = is_array( $context_output ) ? $context_output : array( 'result' => $context_output );
				$context['last']              = $context['steps'][ $step_id ];
				if ( isset( $step['store'] ) && is_string( $step['store'] ) && $step['store'] !== '' ) {
					$context['steps'][ $step['store'] ] = $context['steps'][ $step_id ];
				}
			}

			$steps_output[] = self::finalize_step_result( $step_result, $start_time );

			if ( $step_result['status'] === 'failed' && ( $step['on_error'] ?? 'abort' ) !== 'continue' ) {
				break;
			}
		}

		$return_errors = array();
		$return_map    = self::resolve_refs( $workflow['return'], $context, $return_errors, 'return' );
		if ( ! empty( $return_errors ) ) {
			foreach ( $return_errors as $warning ) {
				$warnings[] = $warning;
			}
		}

		$status = self::determine_workflow_status( $has_success, $has_failure, $has_skipped );

		return array(
			'status'      => $status,
			'mode'        => $mode,
			'workflow_id' => $workflow['workflow_id'],
			'steps'       => $steps_output,
			'return'      => self::normalize_return_map( $return_map ),
			'warnings'    => $warnings,
		);
	}

	private static function normalize_workflow_input( array $input ): array {
		$workflow = $input;
		$workflow['version']     = isset( $workflow['version'] ) ? (string) $workflow['version'] : self::DEFAULT_VERSION;
		$workflow['mode']        = isset( $workflow['mode'] ) ? (string) $workflow['mode'] : self::DEFAULT_MODE;
		$workflow['steps']       = isset( $workflow['steps'] ) && is_array( $workflow['steps'] ) ? $workflow['steps'] : array();
		$workflow['return']      = isset( $workflow['return'] ) && is_array( $workflow['return'] ) ? $workflow['return'] : array();
		$workflow['context']     = isset( $workflow['context'] ) && is_array( $workflow['context'] ) ? $workflow['context'] : array();
		$workflow['allow']       = isset( $workflow['allow'] ) && is_array( $workflow['allow'] ) ? $workflow['allow'] : array();
		$workflow['workflow_id'] = function_exists( 'wp_generate_uuid4' ) ? \wp_generate_uuid4() : uniqid( 'workflow_', true );

		return $workflow;
	}

	private static function validate_workflow_shape( array $workflow ): array {
		$errors = array();

		if ( ! in_array( $workflow['mode'], array( self::MODE_VALIDATE, self::MODE_DRY_RUN, self::MODE_EXECUTE ), true ) ) {
			$errors[] = sprintf( 'Invalid mode: %s.', $workflow['mode'] );
		}

		if ( empty( $workflow['steps'] ) || ! is_array( $workflow['steps'] ) ) {
			$errors[] = 'Workflow steps must be a non-empty array.';
			return $errors;
		}

		if ( count( $workflow['steps'] ) > self::MAX_STEPS ) {
			$errors[] = sprintf( 'Workflow exceeds maximum step count of %d.', self::MAX_STEPS );
		}

		$payload_size = strlen( (string) \wp_json_encode( $workflow ) );
		if ( $payload_size > self::MAX_PAYLOAD_BYTES ) {
			$errors[] = sprintf( 'Workflow payload exceeds %d bytes.', self::MAX_PAYLOAD_BYTES );
		}

		$seen_ids = array();
		foreach ( $workflow['steps'] as $index => $step ) {
			if ( ! is_array( $step ) ) {
				$errors[] = sprintf( 'Step %d must be an object.', $index + 1 );
				continue;
			}
			$step_id = isset( $step['id'] ) ? (string) $step['id'] : '';
			if ( $step_id === '' ) {
				$errors[] = sprintf( 'Step %d is missing an id.', $index + 1 );
				continue;
			}
			if ( isset( $seen_ids[ $step_id ] ) ) {
				$errors[] = sprintf( 'Duplicate step id: %s.', $step_id );
				continue;
			}
			$seen_ids[ $step_id ] = true;

			$ability = isset( $step['ability'] ) ? (string) $step['ability'] : '';
			if ( $ability === '' ) {
				$errors[] = sprintf( 'Step %s is missing an ability.', $step_id );
			}
		}

		return $errors;
	}

	private static function resolve_allowed_prefixes( array $requested ): array {
		$allowed = self::DEFAULT_ALLOWED_PREFIXES;
		if ( empty( $requested ) ) {
			return $allowed;
		}

		$normalized = array();
		foreach ( $requested as $prefix ) {
			if ( ! is_string( $prefix ) ) {
				continue;
			}
			$normalized[] = self::normalize_prefix( $prefix );
		}

		$normalized = array_filter( array_unique( $normalized ) );
		$allowed    = array_values( array_intersect( $allowed, $normalized ) );
		return empty( $allowed ) ? self::DEFAULT_ALLOWED_PREFIXES : $allowed;
	}

	private static function normalize_prefix( string $prefix ): string {
		$prefix = trim( $prefix );
		$prefix = rtrim( $prefix, '*' );
		if ( $prefix !== '' && substr( $prefix, -1 ) !== '/' ) {
			$prefix .= '/';
		}
		return $prefix;
	}

	private static function requires_confirmation( $ability ): bool {
		$annotations = WorkflowAbilityCatalog::get_ability_annotations( $ability );
		return ! empty( $annotations['requiresConfirmation'] );
	}

	private static function is_file_mod_blocked( string $ability_name ): bool {
		if ( ! \defined( 'DISALLOW_FILE_MODS' ) || \constant( 'DISALLOW_FILE_MODS' ) !== true ) {
			return false;
		}

		return in_array( $ability_name, self::FILE_MOD_ABILITIES, true );
	}

	private static function evaluate_when( array $condition, array $context, array &$warnings ): bool {
		if ( empty( $condition ) ) {
			return true;
		}

		if ( isset( $condition['all'] ) && is_array( $condition['all'] ) ) {
			foreach ( $condition['all'] as $entry ) {
				if ( ! is_array( $entry ) || ! self::evaluate_when( $entry, $context, $warnings ) ) {
					return false;
				}
			}
			return true;
		}

		if ( isset( $condition['any'] ) && is_array( $condition['any'] ) ) {
			foreach ( $condition['any'] as $entry ) {
				if ( is_array( $entry ) && self::evaluate_when( $entry, $context, $warnings ) ) {
					return true;
				}
			}
			return false;
		}

		if ( array_key_exists( 'exists', $condition ) ) {
			return self::value_exists( $condition['exists'] );
		}

		if ( array_key_exists( 'not_exists', $condition ) ) {
			return ! self::value_exists( $condition['not_exists'] );
		}

		if ( array_key_exists( 'truthy', $condition ) ) {
			return self::value_truthy( $condition['truthy'] );
		}

		if ( isset( $condition['equals'] ) && is_array( $condition['equals'] ) && count( $condition['equals'] ) === 2 ) {
			$left  = $condition['equals'][0];
			$right = $condition['equals'][1];
			return $left === $right;
		}

		if ( isset( $condition['not_equals'] ) && is_array( $condition['not_equals'] ) && count( $condition['not_equals'] ) === 2 ) {
			$left  = $condition['not_equals'][0];
			$right = $condition['not_equals'][1];
			return $left !== $right;
		}

		if ( isset( $condition['gt'] ) && is_array( $condition['gt'] ) && count( $condition['gt'] ) === 2 ) {
			return self::compare_values( $condition['gt'][0], $condition['gt'][1], 'gt', $warnings );
		}

		if ( isset( $condition['gte'] ) && is_array( $condition['gte'] ) && count( $condition['gte'] ) === 2 ) {
			return self::compare_values( $condition['gte'][0], $condition['gte'][1], 'gte', $warnings );
		}

		if ( isset( $condition['lt'] ) && is_array( $condition['lt'] ) && count( $condition['lt'] ) === 2 ) {
			return self::compare_values( $condition['lt'][0], $condition['lt'][1], 'lt', $warnings );
		}

		if ( isset( $condition['lte'] ) && is_array( $condition['lte'] ) && count( $condition['lte'] ) === 2 ) {
			return self::compare_values( $condition['lte'][0], $condition['lte'][1], 'lte', $warnings );
		}

		if ( isset( $condition['contains'] ) && is_array( $condition['contains'] ) && count( $condition['contains'] ) === 2 ) {
			return self::value_contains( $condition['contains'][0], $condition['contains'][1] );
		}

		if ( isset( $condition['not_contains'] ) && is_array( $condition['not_contains'] ) && count( $condition['not_contains'] ) === 2 ) {
			return ! self::value_contains( $condition['not_contains'][0], $condition['not_contains'][1] );
		}

		if ( isset( $condition['missing_plugin'] ) ) {
			return self::missing_plugin( (string) $condition['missing_plugin'], $context );
		}

		$warnings[] = 'Unknown condition type encountered.';
		return false;
	}

	private static function value_exists( $value ): bool {
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		return $value !== null && $value !== '';
	}

	private static function value_truthy( $value ): bool {
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}

		return (bool) $value;
	}

	private static function compare_values( $left, $right, string $operator, array &$warnings ): bool {
		$comparison = self::compare_scalar_values( $left, $right );
		if ( $comparison === null ) {
			$warnings[] = sprintf( 'Comparison operator %s requires scalar values.', $operator );
			return false;
		}

		switch ( $operator ) {
			case 'gt':
				return $comparison > 0;
			case 'gte':
				return $comparison >= 0;
			case 'lt':
				return $comparison < 0;
			case 'lte':
				return $comparison <= 0;
			default:
				$warnings[] = sprintf( 'Unknown comparison operator %s.', $operator );
				return false;
		}
	}

	private static function compare_scalar_values( $left, $right ): ?int {
		if ( is_bool( $left ) || is_bool( $right ) ) {
			$left_value  = $left ? 1 : 0;
			$right_value = $right ? 1 : 0;
			return $left_value <=> $right_value;
		}

		if ( is_numeric( $left ) && is_numeric( $right ) ) {
			return (float) $left <=> (float) $right;
		}

		if ( is_scalar( $left ) && is_scalar( $right ) ) {
			return strcmp( (string) $left, (string) $right );
		}

		return null;
	}

	private static function value_contains( $container, $needle ): bool {
		if ( is_array( $container ) ) {
			foreach ( $container as $entry ) {
				if ( is_array( $entry ) && isset( $entry['plugin_file'] ) && $entry['plugin_file'] === $needle ) {
					return true;
				}
				if ( $entry === $needle ) {
					return true;
				}
			}
			return false;
		}

		if ( is_string( $container ) && is_string( $needle ) ) {
			return strpos( $container, $needle ) !== false;
		}

		return false;
	}

	private static function missing_plugin( string $plugin_file, array $context ): bool {
		$plugins = $context['steps']['plugins']['plugins'] ?? null;
		if ( ! is_array( $plugins ) ) {
			return false;
		}

		foreach ( $plugins as $plugin ) {
			if ( is_array( $plugin ) && ( $plugin['plugin_file'] ?? '' ) === $plugin_file ) {
				return false;
			}
		}

		return true;
	}

	private static function should_execute_in_dry_run( $ability, array $args ): bool {
		$annotations = WorkflowAbilityCatalog::get_ability_annotations( $ability );
		if ( isset( $annotations['readonly'] ) && $annotations['readonly'] ) {
			return true;
		}

		$schema = is_object( $ability ) && method_exists( $ability, 'get_input_schema' )
			? (array) $ability->get_input_schema()
			: array();
		if ( isset( $schema['properties']['dry_run'] ) ) {
			return true;
		}

		return false;
	}

	private static function apply_dry_run_argument( $ability, array $args ): array {
		$schema = is_object( $ability ) && method_exists( $ability, 'get_input_schema' )
			? (array) $ability->get_input_schema()
			: array();
		if ( isset( $schema['properties']['dry_run'] ) && ! array_key_exists( 'dry_run', $args ) ) {
			$args['dry_run'] = true;
		}

		return $args;
	}

	private static function execute_ability( $ability, array $args ): array {
		try {
			$result = $ability->execute( $args );
		} catch ( \Throwable $throwable ) {
			return array(
				'status' => 'failed',
				'error'  => array(
					'code'    => 'execution_error',
					'message' => $throwable->getMessage(),
				),
				'output' => array(),
			);
		}

		if ( \is_wp_error( $result ) ) {
			return array(
				'status' => 'failed',
				'error'  => array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				'output' => array(),
			);
		}

		if ( is_array( $result ) && isset( $result['error'] ) && is_array( $result['error'] ) ) {
			return array(
				'status' => 'failed',
				'error'  => $result['error'],
				'output' => array(),
			);
		}

		return array(
			'status' => 'success',
			'output' => $result,
			'error'  => array(),
		);
	}

	private static function finalize_step_result( array $step_result, float $start_time ): array {
		$step_result['duration_ms'] = (int) round( ( microtime( true ) - $start_time ) * 1000 );
		$step_result['output'] = self::normalize_step_output( $step_result['output'] ?? null );
		$step_result['error']  = self::normalize_step_error( $step_result['error'] ?? null );

		return $step_result;
	}

	private static function normalize_step_output( $output ) {
		if ( is_array( $output ) ) {
			if ( $output === array() ) {
				return new \stdClass();
			}
			if ( self::is_list_array( $output ) ) {
				return array( 'items' => $output );
			}
			return $output;
		}

		if ( $output === null ) {
			return new \stdClass();
		}

		return array( 'result' => $output );
	}

	private static function normalize_step_error( $error ) {
		if ( is_array( $error ) ) {
			return $error === array() ? new \stdClass() : $error;
		}
		if ( $error === null ) {
			return new \stdClass();
		}

		return array( 'message' => (string) $error );
	}

	private static function normalize_return_map( $return_map ) {
		if ( is_array( $return_map ) ) {
			return $return_map === array() ? new \stdClass() : $return_map;
		}

		return new \stdClass();
	}

	private static function is_list_array( array $value ): bool {
		if ( $value === array() ) {
			return false;
		}
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	private static function determine_workflow_status( bool $has_success, bool $has_failure, bool $has_skipped ): string {
		if ( $has_failure ) {
			return $has_success ? 'partial' : 'failed';
		}

		if ( $has_success ) {
			return 'success';
		}

		return $has_skipped ? 'partial' : 'failed';
	}

	private static function resolve_refs( $value, array $context, array &$errors, string $path = '', bool $allow_missing = false ) {
		if ( is_array( $value ) && self::is_ref_array( $value ) ) {
			$ref_path = (string) $value['ref'];
			$found    = false;
			$resolved = self::get_ref_value( $ref_path, $context, $found );
			if ( ! $found ) {
				if ( ! $allow_missing ) {
					$errors[] = sprintf( 'Missing reference at %s (%s).', $path, $ref_path );
				}
				return null;
			}
			return $resolved;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				$child_path      = $path === '' ? (string) $key : $path . '.' . $key;
				$value[ $key ] = self::resolve_refs( $child, $context, $errors, $child_path, $allow_missing );
			}
		}

		return $value;
	}

	private static function is_ref_array( array $value ): bool {
		return count( $value ) === 1 && array_key_exists( 'ref', $value );
	}

	private static function get_ref_value( string $path, array $context, bool &$found ) {
		$found  = false;
		$parts  = array_filter( explode( '.', $path ), 'strlen' );
		$cursor = $context;

		foreach ( $parts as $index => $part ) {
			if ( is_array( $cursor ) && array_key_exists( $part, $cursor ) ) {
				$cursor = $cursor[ $part ];
				continue;
			}
			if ( $index === 0 && isset( $context['steps'] ) && is_array( $context['steps'] ) && array_key_exists( $part, $context['steps'] ) ) {
				$cursor = $context['steps'][ $part ];
				continue;
			}
			$found = false;
			return null;
		}

		$found = true;
		return $cursor;
	}

	private static function error_response( string $code, string $message ): \WP_Error {
		return new \WP_Error( $code, $message );
	}
}
