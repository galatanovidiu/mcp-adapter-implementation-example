<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Support;

final class Terms
{
    /**
     * Resolve a list of mixed term identifiers (IDs or slugs/names) to term IDs, optionally creating missing terms.
     *
     * @param string $taxonomy Taxonomy slug.
     * @param array $inputTerms Mixed array of term IDs or strings (slugs/names).
     * @param bool $createIfMissing Whether to create missing terms if user has capability.
     * @return int[] List of resolved term IDs.
     */
    public static function resolve_to_ids(string $taxonomy, array $inputTerms, bool $createIfMissing = false): array
    {
        $termIds = array();
        foreach ($inputTerms as $t) {
            if (is_numeric($t)) {
                $termIds[] = (int) $t;
                continue;
            }
            if (is_string($t)) {
                $term = \get_term_by('slug', $t, $taxonomy);
                if (! $term) { $term = \get_term_by('name', $t, $taxonomy); }
                if ($term instanceof \WP_Term) {
                    $termIds[] = (int) $term->term_id;
                } elseif ($createIfMissing && \current_user_can('manage_terms')) {
                    $created = \wp_insert_term($t, $taxonomy);
                    if (! \is_wp_error($created) && isset($created['term_id'])) { $termIds[] = (int) $created['term_id']; }
                }
            }
        }
        return array_values(array_unique(array_map('intval', $termIds)));
    }
}


