<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class SystemCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'system',
			array(
				'label'       => 'System',
				'description' => 'Abilities related to system monitoring, updates, database, and debugging.',
			)
		);
	}
}
