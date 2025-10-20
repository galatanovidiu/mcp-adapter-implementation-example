<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class AppearanceCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'appearance',
			array(
				'label'       => 'Appearance',
				'description' => 'Abilities related to theme management, customization, and site appearance.',
			)
		);
	}
}
