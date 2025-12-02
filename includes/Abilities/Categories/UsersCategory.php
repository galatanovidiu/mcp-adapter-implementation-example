<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class UsersCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'users',
			array(
				'label'       => 'Users',
				'description' => 'Abilities related to user management, profiles, roles, and permissions.',
			)
		);
	}
}
