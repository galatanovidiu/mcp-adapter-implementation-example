<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class ContentCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'content',
			array(
				'label'       => 'Content',
				'description' => 'Abilities related to posts, pages, menus, taxonomies, and content management.',
			)
		);
	}
}
