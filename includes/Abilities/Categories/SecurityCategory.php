<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class SecurityCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'security',
			array(
				'label'       => 'Security',
				'description' => 'Abilities related to security monitoring, backups, and authentication.',
			)
		);
	}
}
