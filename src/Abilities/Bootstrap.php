<?php
/**
 * Abilities bootstrapper: registers all abilities on abilities_api_init.
 */
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

final class Bootstrap
{
    public static function init(): void
    {
        \add_action(
            'abilities_api_init',
            static function (): void {
                // Post CRUD abilities
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\GetPost::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\UpdatePost::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\DeletePost::register();

                // Post Meta abilities
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\ListPostMetaKeys::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\GetPostMeta::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\UpdatePostMeta::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta\DeletePostMeta::register();

                // Blocks discovery
                \OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes::register();

                // Taxonomy & Terms abilities
                \OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\ListTaxonomies::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\CreateTerm::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\UpdateTerm::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\DeleteTerm::register();

                // Attach/Detach helpers
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms\AttachPostTerms::register();
                \OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms\DetachPostTerms::register();
            },
            10
        );
    }
}


