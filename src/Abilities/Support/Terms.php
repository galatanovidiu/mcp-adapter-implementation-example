<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Support;

final class Terms {

	/**
	 * Resolve a list of mixed term identifiers (IDs or slugs/names) to term IDs, optionally creating missing terms.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param array $input_terms Mixed array of term IDs or strings (slugs/names).
	 * @param bool $create_if_missing Whether to create missing terms if user has capability.
	 * @return int[] List of resolved term IDs.
	 */
	public static function resolve_to_ids( string $taxonomy, array $input_terms, bool $create_if_missing = false ): array {
		$term_ids = array();
		foreach ( $input_terms as $t ) {
			if ( is_numeric( $t ) ) {
				$term_ids[] = (int) $t;
				continue;
			}
			if ( ! is_string( $t ) ) {
				continue;
			}

			$term = \get_term_by( 'slug', $t, $taxonomy );
			if ( ! $term ) {
				$term = \get_term_by( 'name', $t, $taxonomy );
			}
			if ( $term instanceof \WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			} elseif ( $create_if_missing && \current_user_can( 'manage_terms' ) ) {
				$created = \wp_insert_term( $t, $taxonomy );
				if ( ! \is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$term_ids[] = (int) $created['term_id'];
				}
			}
		}
		return array_values( array_unique( array_map( 'intval', $term_ids ) ) );
	}
}
