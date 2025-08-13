<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\Contracts\RegistersAbility;

final class CreateTerm implements RegistersAbility
{
    public static function register(): void
    {
        \wp_register_ability(
            'wpmcp-example/create-term',
            array(
                'label'       => 'Create Term',
                'description' => 'Create a term in a taxonomy.',
                'input_schema'  => array(
                    'type'       => 'object',
                    'required'   => array('taxonomy', 'name'),
                    'properties' => array(
                        'taxonomy'   => array('type' => 'string'),
                        'name'       => array('type' => 'string'),
                        'slug'       => array('type' => 'string'),
                        'description'=> array('type' => 'string'),
                        'parent'     => array('type' => 'integer'),
                    ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'required'   => array('id'),
                    'properties' => array(
                        'id' => array('type' => 'integer'),
                    ),
                ),
                'permission_callback' => static function (array $input): bool {
                    $taxonomy = isset($input['taxonomy']) ? \sanitize_key((string) $input['taxonomy']) : '';
                    if (! \taxonomy_exists($taxonomy)) {
                        return false;
                    }
                    $tax = \get_taxonomy($taxonomy);
                    return $tax && isset($tax->cap->manage_terms) ? \current_user_can($tax->cap->manage_terms) : \current_user_can('manage_categories');
                },
                'execute_callback'    => static function (array $input) {
                    $taxonomy = \sanitize_key((string) $input['taxonomy']);
                    $args = array();
                    if (! empty($input['slug'])) { $args['slug'] = \sanitize_title((string) $input['slug']); }
                    if (! empty($input['description'])) { $args['description'] = (string) $input['description']; }
                    if (! empty($input['parent'])) { $args['parent'] = (int) $input['parent']; }
                    $created = \wp_insert_term((string) $input['name'], $taxonomy, $args);
                    if (\is_wp_error($created)) { return $created; }
                    return array('id' => (int) $created['term_id']);
                },
                'meta' => array(),
            )
        );
    }
}


