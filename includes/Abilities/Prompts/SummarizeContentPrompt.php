<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Prompts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class SummarizeContentPrompt implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'prompts/summarize-content',
			array(
				'label'               => 'Summarize Content',
				'description'         => 'Generate a summary of provided content',
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'content' => array(
							'type'        => 'string',
							'description' => 'The content to summarize',
						),
						'length'  => array(
							'type'        => 'string',
							'description' => 'Summary length in words (e.g., 50, 100, 200)',
						),
						'format'  => array(
							'type'        => 'string',
							'description' => 'Output format (paragraph, bullet-points, single-sentence)',
						),
					),
					'required'   => array( 'content' ),
				),
				'category'            => 'content',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
					'annotations' => array(
						'audience' => array( 'user', 'assistant' ),
						'priority' => 0.8,
					),
				),
			)
		);
	}

	/**
	 * Check permission for summarizing content.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'read' );
	}

	/**
	 * Execute the prompt to summarize content.
	 *
	 * @param array $input Input parameters (content, length, format).
	 * @return array Prompt messages.
	 */
	public static function execute( array $input ) {
		$content = $input['content'] ?? '';
		$length  = $input['length'] ?? '100';
		$format  = $input['format'] ?? 'paragraph';

		if ( empty( $content ) ) {
			return new \WP_Error(
				'missing_content',
				'Content parameter is required for summarization'
			);
		}

		// Build the prompt message
		$prompt = "Summarize the following content:\n\n";
		$prompt .= "---\n{$content}\n---\n\n";

		$prompt .= "Requirements:\n";
		$prompt .= "- Length: approximately {$length} words\n";
		$prompt .= "- Format: {$format}\n";
		$prompt .= "- Focus on the main points and key takeaways\n";
		$prompt .= "- Be concise and clear\n";

		if ( $format === 'bullet-points' ) {
			$prompt .= "\nProvide the summary as a bulleted list in Markdown format.";
		} elseif ( $format === 'single-sentence' ) {
			$prompt .= "\nProvide a single, comprehensive sentence summary.";
		} else {
			$prompt .= "\nProvide a paragraph summary.";
		}

		return array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
					),
				),
			),
		);
	}
}
