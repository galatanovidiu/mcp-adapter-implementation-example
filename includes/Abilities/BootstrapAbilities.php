<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

use OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\DeletePost;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\GetPost;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\DeletePostMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\GetPostMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\ListPostMetaKeys;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\UpdatePostMeta;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms\AttachPostTerms;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms\DetachPostTerms;
use OvidiuGalatan\McpAdapterExample\Abilities\Posts\UpdatePost;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\CreateTerm;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\DeleteTerm;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\ListTaxonomies;
use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\UpdateTerm;

final class BootstrapAbilities {

	public static function init(): void {
		\add_action(
			'abilities_api_init',
			static function (): void {
				// Post CRUD abilities
				CreatePost::register();
				GetPost::register();
				ListPosts::register();
				UpdatePost::register();
				DeletePost::register();

				// Post Meta abilities
				ListPostMetaKeys::register();
				GetPostMeta::register();
				UpdatePostMeta::register();
				DeletePostMeta::register();

				// Blocks discovery
				ListBlockTypes::register();

				// Taxonomy & Terms abilities
				ListTaxonomies::register();
				GetTerms::register();
				CreateTerm::register();
				UpdateTerm::register();
				DeleteTerm::register();

				// Attach/Detach helpers
				AttachPostTerms::register();
				DetachPostTerms::register();
			}
		);
	}
}
