<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class SettingsCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'settings',
			array(
				'label'       => 'Settings',
				'description' => 'Abilities related to site settings, options, and configuration.',
			)
		);
	}
}
