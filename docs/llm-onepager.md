# MCP Adapter Example Server (LLM One‑Pager)

- Server ID: `mcp-adapter-example-server`
- Purpose: Demonstrate safe WordPress content management via MCP tools: posts (any post type), taxonomies/terms, Gutenberg blocks, and whitelisted post meta.

## Key conventions
- Prefer Gutenberg `blocks` over raw HTML `content`.
- Discover before acting: list taxonomies, terms, and meta keys first.
- Taxonomy rules: only attach taxonomies supported by a post type; choose append vs replace; optional auto‑create if permitted.
- Meta rules: default to registered `show_in_rest` keys; validate per schema.
- Permissions: tools enforce WordPress caps (e.g., `edit_post`, `delete_post`, `assign_terms`, `manage_terms`).

## Tool map (summary)
- Posts: `wpmcp-example-example-create-post`, `wpmcp-example-example-get-post`, `wpmcp-example-example-update-post`, `wpmcp-example-example-delete-post`
- Post terms: `wpmcp-example-example-attach-post-terms`, `wpmcp-example-example-detach-post-terms`
- Taxonomies/terms: `wpmcp-example-example-list-taxonomies`, `wpmcp-example-example-get-terms`, `wpmcp-example-example-create-term`, `wpmcp-example-example-update-term`, `wpmcp-example-example-delete-term`
- Post meta: `wpmcp-example-example-list-post-meta-keys`, `wpmcp-example-example-get-post-meta`, `wpmcp-example-example-update-post-meta`, `wpmcp-example-example-delete-post-meta`
- Blocks: `wpmcp-example-example-list-block-types`

Note: MCP tool names replace `/` with `-` (e.g., `wpmcp-example-example/create-post` → tool `wpmcp-example-example-create-post`).

## Typical flows
- Create post with blocks and categories
  1. `wpmcp-example-list-taxonomies { post_type }`
  2. `wpmcp-example-get-terms { taxonomy }`
  3. `wpmcp-example-create-post { post_type, blocks, tax_input, append_terms }`
- Update post content and terms
  1. `wpmcp-example-get-post { id }`
  2. `wpmcp-example-update-post { id, blocks, tax_input, append_terms }`
- Read post meta safely
  1. `wpmcp-example-list-post-meta-keys { post_type }`
  2. `wpmcp-example-get-post-meta { id, keys }`

## Minimal example: create post with blocks
```json
{
  "post_type": "post",
  "title": "Hello Blocks",
  "status": "draft",
  "blocks": [
    { "blockName": "core/heading", "attrs": {"level": 2}, "innerBlocks": [], "innerHTML": "", "innerContent": ["<h2>Welcome</h2>"] },
    { "blockName": "core/paragraph", "attrs": {}, "innerBlocks": [], "innerHTML": "", "innerContent": ["<p>Content.</p>"] }
  ],
  "tax_input": { "category": ["news"] },
  "append_terms": true
}
```

## Guardrails
- Do not invent post types/taxonomies; discover first.
- Prefer `blocks` over `content`.
- Default to `show_in_rest` meta keys.
- Expect permission and validation errors; iterate with small, safe changes.
