# Policy and Safety

- Capabilities enforced
  - Posts: `create_posts`, `edit_post`, `delete_post`
  - Taxonomies: `assign_terms`, `manage_terms`, `edit_terms`, `delete_terms`
  - Meta: `edit_post_meta` per key
- Safe defaults
  - Prefer `blocks` over raw HTML `content`
  - Post meta defaults to registered `show_in_rest` keys only
  - Term auto‑creation is opt‑in and requires capability
- Discovery before action
  - List taxonomies/terms/meta keys to avoid invalid input
- Error handling
  - Expect `WP_Error` responses; adjust inputs accordingly
