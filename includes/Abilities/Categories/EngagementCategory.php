<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class EngagementCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'engagement',
			array(
				'label'       => 'Engagement',
				'description' => 'Abilities related to comments, feedback, and user engagement.',
			)
		);
	}
}
