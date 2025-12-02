<?php
/**
 * Pipeline WP-CLI Command
 *
 * CLI commands for pipeline execution and management.
 *
 * @package OvidiuGalatan\McpAdapterExample\Cli
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Cli;

use OvidiuGalatan\McpAdapterExample\Pipeline\PipelineExecutor;
use OvidiuGalatan\McpAdapterExample\Pipeline\PipelineValidator;
use OvidiuGalatan\McpAdapterExample\Pipeline\DataTokenizer;
use WP_CLI;

/**
 * Class PipelineCommand
 *
 * WP-CLI commands for declarative pipelines.
 */
class PipelineCommand {
	/**
	 * Execute a pipeline from a JSON file
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to pipeline JSON file
	 *
	 * [--context=<json>]
	 * : Initial context variables as JSON string
	 *
	 * [--no-tokenize]
	 * : Disable sensitive data tokenization
	 *
	 * [--max-steps=<number>]
	 * : Maximum number of steps (default: 1000)
	 *
	 * [--max-depth=<number>]
	 * : Maximum nesting depth (default: 10)
	 *
	 * [--timeout=<seconds>]
	 * : Execution timeout in seconds (default: 300)
	 *
	 * [--format=<format>]
	 * : Output format (json, yaml, table) (default: json)
	 *
	 * ## EXAMPLES
	 *
	 *     # Execute a pipeline
	 *     wp mcp-pipeline execute pipeline.json
	 *
	 *     # Execute with context
	 *     wp mcp-pipeline execute pipeline.json --context='{"post_id": 123}'
	 *
	 *     # Execute with custom limits
	 *     wp mcp-pipeline execute pipeline.json --max-steps=500 --timeout=60
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative arguments
	 * @return void
	 */
	public function execute( array $args, array $assoc_args ): void {
		$file = $args[0];

		// Check file exists
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "Pipeline file not found: {$file}" );
			return;
		}

		// Load pipeline
		$pipeline_json = file_get_contents( $file );
		$pipeline = json_decode( $pipeline_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			WP_CLI::error( 'Invalid JSON in pipeline file: ' . json_last_error_msg() );
			return;
		}

		// Parse context
		$context = [];
		if ( isset( $assoc_args['context'] ) ) {
			$context = json_decode( $assoc_args['context'], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				WP_CLI::error( 'Invalid JSON in context: ' . json_last_error_msg() );
				return;
			}
		}

		// Build limits
		$limits = [];
		if ( isset( $assoc_args['max-steps'] ) ) {
			$limits['max_steps'] = (int) $assoc_args['max-steps'];
		}
		if ( isset( $assoc_args['max-depth'] ) ) {
			$limits['max_depth'] = (int) $assoc_args['max-depth'];
		}
		if ( isset( $assoc_args['timeout'] ) ) {
			$limits['timeout'] = (int) $assoc_args['timeout'];
		}

		$tokenize = ! isset( $assoc_args['no-tokenize'] );
		$format = $assoc_args['format'] ?? 'json';

		WP_CLI::log( WP_CLI::colorize( '%BExecuting pipeline...%n' ) );
		WP_CLI::log( "File: {$file}" );
		WP_CLI::log( "Context variables: " . count( $context ) );
		WP_CLI::log( '' );

		// Validate
		$validator = new PipelineValidator();
		if ( ! $validator->validate( $pipeline ) ) {
			WP_CLI::error( "Pipeline validation failed:\n" . $validator->get_errors_string() );
			return;
		}

		WP_CLI::success( 'Pipeline validated successfully' );

		// Tokenize if needed
		$tokenizer = null;
		if ( $tokenize ) {
			$tokenizer = new DataTokenizer();
			$context = $tokenizer->tokenize( $context );
		}

		// Execute
		try {
			$executor = new PipelineExecutor( $limits );
			$start = microtime( true );

			$result = $executor->execute( $pipeline, $context );

			$duration = microtime( true ) - $start;

			// Detokenize if needed
			if ( $tokenizer ) {
				$result['result'] = $tokenizer->detokenize( $result['result'] );
				$result['context'] = $tokenizer->detokenize( $result['context'] );
			}

			WP_CLI::log( '' );
			WP_CLI::success( sprintf(
				'Pipeline executed successfully in %.3f seconds',
				$duration
			) );

			// Display stats
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%BExecution Statistics:%n' ) );
			WP_CLI::log( sprintf( '  Steps executed: %d', $result['stats']['steps_executed'] ) );
			WP_CLI::log( sprintf( '  Duration: %.3f seconds', $result['stats']['duration'] ) );
			WP_CLI::log( sprintf( '  Memory peak: %s', size_format( $result['stats']['memory_peak'] ) ) );

			if ( ! empty( $result['stats']['steps_by_type'] ) ) {
				WP_CLI::log( '  Steps by type:' );
				foreach ( $result['stats']['steps_by_type'] as $type => $count ) {
					WP_CLI::log( sprintf( '    - %s: %d', $type, $count ) );
				}
			}

			if ( $tokenizer ) {
				WP_CLI::log( sprintf( '  Sensitive fields tokenized: %d', $tokenizer->get_token_count() ) );
			}

			// Display result
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%BPipeline Result:%n' ) );

			if ( $format === 'json' ) {
				WP_CLI::log( json_encode( $result['result'], JSON_PRETTY_PRINT ) );
			} elseif ( $format === 'yaml' ) {
				WP_CLI::log( \Symfony\Component\Yaml\Yaml::dump( $result['result'], 4, 2 ) );
			} else {
				print_r( $result['result'] );
			}

		} catch ( \Exception $e ) {
			WP_CLI::error( 'Pipeline execution failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Validate a pipeline without executing it
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to pipeline JSON file
	 *
	 * ## EXAMPLES
	 *
	 *     wp mcp-pipeline validate pipeline.json
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative arguments
	 * @return void
	 */
	public function validate( array $args, array $assoc_args ): void {
		$file = $args[0];

		// Check file exists
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "Pipeline file not found: {$file}" );
			return;
		}

		// Load pipeline
		$pipeline_json = file_get_contents( $file );
		$pipeline = json_decode( $pipeline_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			WP_CLI::error( 'Invalid JSON in pipeline file: ' . json_last_error_msg() );
			return;
		}

		// Validate
		WP_CLI::log( "Validating pipeline: {$file}" );
		WP_CLI::log( '' );

		$validator = new PipelineValidator();
		$is_valid = $validator->validate( $pipeline );

		if ( $is_valid ) {
			WP_CLI::success( 'Pipeline is valid' );

			// Show step count
			$step_count = count( $pipeline['steps'] ?? [] );
			WP_CLI::log( "Total steps: {$step_count}" );

			// Show step types
			$types = [];
			foreach ( $pipeline['steps'] ?? [] as $step ) {
				$type = $step['type'] ?? 'unknown';
				$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
			}

			if ( ! empty( $types ) ) {
				WP_CLI::log( 'Steps by type:' );
				foreach ( $types as $type => $count ) {
					WP_CLI::log( "  - {$type}: {$count}" );
				}
			}
		} else {
			WP_CLI::error( "Pipeline validation failed:\n" . $validator->get_errors_string() );
		}
	}

	/**
	 * Dry-run a pipeline (validate and show execution plan)
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to pipeline JSON file
	 *
	 * [--context=<json>]
	 * : Initial context variables as JSON string
	 *
	 * ## EXAMPLES
	 *
	 *     wp mcp-pipeline dry-run pipeline.json
	 *     wp mcp-pipeline dry-run pipeline.json --context='{"user_id": 1}'
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative arguments
	 * @return void
	 */
	public function dry_run( array $args, array $assoc_args ): void {
		$file = $args[0];

		// Check file exists
		if ( ! file_exists( $file ) ) {
			WP_CLI::error( "Pipeline file not found: {$file}" );
			return;
		}

		// Load pipeline
		$pipeline_json = file_get_contents( $file );
		$pipeline = json_decode( $pipeline_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			WP_CLI::error( 'Invalid JSON in pipeline file: ' . json_last_error_msg() );
			return;
		}

		// Parse context
		$context = [];
		if ( isset( $assoc_args['context'] ) ) {
			$context = json_decode( $assoc_args['context'], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				WP_CLI::error( 'Invalid JSON in context: ' . json_last_error_msg() );
				return;
			}
		}

		WP_CLI::log( WP_CLI::colorize( '%BDry-run mode: Validating without execution%n' ) );
		WP_CLI::log( '' );

		// Validate
		$validator = new PipelineValidator();
		if ( ! $validator->validate( $pipeline ) ) {
			WP_CLI::error( "Pipeline validation failed:\n" . $validator->get_errors_string() );
			return;
		}

		WP_CLI::success( 'Pipeline is valid' );
		WP_CLI::log( '' );

		// Show execution plan
		WP_CLI::log( WP_CLI::colorize( '%BExecution Plan:%n' ) );
		WP_CLI::log( '' );

		$this->display_execution_plan( $pipeline['steps'], 0, $context );

		WP_CLI::log( '' );
		WP_CLI::success( 'Dry-run complete. Pipeline is ready to execute.' );
	}

	/**
	 * Display execution plan recursively
	 *
	 * @param array $steps Steps to display
	 * @param int   $level Indentation level
	 * @param array $context Current context
	 * @return void
	 */
	private function display_execution_plan( array $steps, int $level, array $context ): void {
		$indent = str_repeat( '  ', $level );

		foreach ( $steps as $index => $step ) {
			$type = $step['type'] ?? 'unknown';
			$output = $step['output'] ?? null;

			// Format step description
			$desc = $this->get_step_description( $step );
			$output_str = $output ? WP_CLI::colorize( " %G→ \${$output}%n" ) : '';

			WP_CLI::log( sprintf(
				'%s%d. [%s] %s%s',
				$indent,
				$index + 1,
				WP_CLI::colorize( "%C{$type}%n" ),
				$desc,
				$output_str
			) );

			// Show nested steps for control flow
			if ( isset( $step['steps'] ) && is_array( $step['steps'] ) ) {
				$this->display_execution_plan( $step['steps'], $level + 1, $context );
			}

			if ( isset( $step['then'] ) && is_array( $step['then'] ) ) {
				WP_CLI::log( "{$indent}  ↳ THEN:" );
				$this->display_execution_plan( $step['then'], $level + 2, $context );
			}

			if ( isset( $step['else'] ) && is_array( $step['else'] ) ) {
				WP_CLI::log( "{$indent}  ↳ ELSE:" );
				$this->display_execution_plan( $step['else'], $level + 2, $context );
			}
		}
	}

	/**
	 * Get human-readable step description
	 *
	 * @param array $step Step configuration
	 * @return string Description
	 */
	private function get_step_description( array $step ): string {
		$type = $step['type'] ?? 'unknown';

		switch ( $type ) {
			case 'ability':
				return $step['ability'] ?? 'unknown ability';

			case 'transform':
				$op = $step['operation'] ?? 'unknown';
				return "transform: {$op}";

			case 'conditional':
				$field = $step['condition']['field'] ?? '?';
				$operator = $step['condition']['operator'] ?? '?';
				return "if {$field} {$operator}";

			case 'loop':
				$item_var = $step['itemVar'] ?? 'item';
				return "foreach \${$item_var}";

			case 'parallel':
				$count = count( $step['steps'] ?? [] );
				return "parallel ({$count} steps)";

			case 'try_catch':
				return 'try-catch';

			case 'sub_pipeline':
				return 'sub-pipeline';

			default:
				return $type;
		}
	}

	/**
	 * List available pipeline transformations
	 *
	 * ## EXAMPLES
	 *
	 *     wp mcp-pipeline list-transforms
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative arguments
	 * @return void
	 */
	public function list_transforms( array $args, array $assoc_args ): void {
		\OvidiuGalatan\McpAdapterExample\Pipeline\Transformations\TransformationRegistry::init();

		$transforms = \OvidiuGalatan\McpAdapterExample\Pipeline\Transformations\TransformationRegistry::get_all();

		WP_CLI::log( WP_CLI::colorize( '%BAvailable Transformations:%n' ) );
		WP_CLI::log( '' );

		$categories = [
			'Array Operations' => [ 'filter', 'map', 'pluck', 'unique', 'sort', 'reverse', 'slice', 'chunk', 'flatten', 'merge' ],
			'Aggregations' => [ 'count', 'sum', 'average', 'min', 'max' ],
			'String Operations' => [ 'join', 'split', 'trim', 'uppercase', 'lowercase' ],
		];

		foreach ( $categories as $category => $ops ) {
			WP_CLI::log( WP_CLI::colorize( "%Y{$category}:%n" ) );
			foreach ( $ops as $op ) {
				if ( in_array( $op, $transforms, true ) ) {
					WP_CLI::log( "  - {$op}" );
				}
			}
			WP_CLI::log( '' );
		}

		WP_CLI::log( sprintf( 'Total: %d transformations', count( $transforms ) ) );
	}
}

// Register WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'mcp-pipeline execute', [ new PipelineCommand(), 'execute' ] );
	WP_CLI::add_command( 'mcp-pipeline validate', [ new PipelineCommand(), 'validate' ] );
	WP_CLI::add_command( 'mcp-pipeline dry-run', [ new PipelineCommand(), 'dry_run' ] );
	WP_CLI::add_command( 'mcp-pipeline list-transforms', [ new PipelineCommand(), 'list_transforms' ] );
}
