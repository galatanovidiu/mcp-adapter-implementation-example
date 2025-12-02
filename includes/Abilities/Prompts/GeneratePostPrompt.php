<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Prompts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GeneratePostPrompt implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'prompts/generate-post',
			array(
				'label'               => 'Generate Post Content',
				'description'         => 'Generate WordPress post content based on a topic and optional parameters',
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'topic'    => array(
							'type'        => 'string',
							'description' => 'The topic or subject for the post',
						),
						'tone'     => array(
							'type'        => 'string',
							'description' => 'Writing tone (professional, casual, friendly, technical, etc.)',
						),
						'length'   => array(
							'type'        => 'string',
							'description' => 'Desired length (short, medium, long)',
						),
						'keywords' => array(
							'type'        => 'array',
							'description' => 'Keywords to include in the content',
							'items'       => array(
								'type' => 'string',
							),
						),
					),
					'required'   => array( 'topic' ),
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
	 * Check permission for generating post content.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_posts' );
	}

	/**
	 * Execute the prompt to generate post content.
	 *
	 * @param array $input Input parameters (topic, tone, length, keywords).
	 * @return array Prompt messages.
	 */
	public static function execute( array $input ) {
		$topic    = $input['topic'] ?? '';
		$tone     = $input['tone'] ?? 'professional';
		$length   = $input['length'] ?? 'medium';
		$keywords = $input['keywords'] ?? '';

		// Build the prompt message
		$prompt = "Write a WordPress blog post about: {$topic}\n\n";

		$prompt .= "Requirements:\n";
		$prompt .= "- Tone: {$tone}\n";
		$prompt .= "- Length: {$length}\n";

		if ( ! empty( $keywords ) ) {
			$prompt .= "- Include these keywords: {$keywords}\n";
		}

		$prompt .= "\n";
		$prompt .= "Structure:\n";
		$prompt .= "1. Compelling title\n";
		$prompt .= "2. Engaging introduction\n";
		$prompt .= "3. Main content with clear sections\n";
		$prompt .= "4. Conclusion with call-to-action\n";
		$prompt .= "\n";
		$prompt .= 'Format the response in Markdown with proper headings and formatting.';

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
