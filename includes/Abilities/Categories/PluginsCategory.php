<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class PluginsCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'plugins',
			array(
				'label'       => 'Plugins',
				'description' => 'Abilities related to plugin installation, activation, and management.',
			)
		);
	}
}
