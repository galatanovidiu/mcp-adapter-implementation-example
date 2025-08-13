<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\Contracts\RegistersAbility;

final class DeletePost implements RegistersAbility
{
    public static function register(): void
    {
        \wp_register_ability(
            'wpmcp-example/delete-post',
            array(
                'label'       => 'Delete Post',
                'description' => 'Delete a WordPress post by ID.',
                'input_schema'  => array(
                    'type'       => 'object',
                    'required'   => array('id'),
                    'properties' => array(
                        'id' => array(
                            'type'        => 'integer',
                            'description' => 'Post ID to delete.',
                        ),
                        'force' => array(
                            'type'        => 'boolean',
                            'description' => 'Permanently delete (bypass trash).',
                            'default'     => false,
                        ),
                    ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'required'   => array('deleted'),
                    'properties' => array(
                        'deleted' => array('type' => 'boolean'),
                    ),
                ),
                'permission_callback' => static function (array $input): bool {
                    $post_id = (int) ($input['id'] ?? 0);
                    if ($post_id <= 0) {
                        return false;
                    }
                    return \current_user_can('delete_post', $post_id);
                },
                'execute_callback'    => static function (array $input) {
                    $post_id = (int) $input['id'];
                    $force   = ! empty($input['force']);

                    $deleted = \wp_delete_post($post_id, $force);
                    if (false === $deleted) {
                        return new \WP_Error('delete_failed', 'Failed to delete the post.');
                    }

                    return array(
                        'deleted' => true,
                    );
                },
                'meta' => array(),
            )
        );
    }
}


