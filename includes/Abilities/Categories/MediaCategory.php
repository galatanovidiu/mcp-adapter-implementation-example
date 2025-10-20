<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class MediaCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'media',
			array(
				'label'       => 'Media',
				'description' => 'Abilities related to media library, attachments, and file uploads.',
			)
		);
	}
}
