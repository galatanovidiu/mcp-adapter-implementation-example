# Conditional Steps Guide

Complete reference for conditional logic in pipelines.

## Basic Conditional Syntax

```json
{
  "type": "conditional",
  "condition": {
    "field": "$variable_or_value",
    "operator": "comparison_operator",
    "value": "expected_value"
  },
  "then": [ /* steps to execute if true */ ],
  "else": [ /* steps to execute if false */ ]
}
```

## Simple Conditional Examples

### Check if variable equals value

```json
{
  "type": "conditional",
  "condition": {
    "field": "$post.post_status",
    "operator": "equals",
    "value": "draft"
  },
  "then": [
    {
      "type": "ability",
      "ability": "core/update-post",
      "input": {
        "post_id": "$post.ID",
        "post_status": "publish"
      }
    }
  ]
}
```

### Check if value is empty

```json
{
  "type": "conditional",
  "condition": {
    "field": "$post.post_excerpt",
    "operator": "empty"
  },
  "then": [
    {
      "type": "ability",
      "ability": "core/update-post",
      "input": {
        "post_id": "$post.ID",
        "post_excerpt": "Auto-generated excerpt"
      }
    }
  ],
  "else": [
    {
      "type": "ability",
      "ability": "core/update-post-meta",
      "input": {
        "post_id": "$post.ID",
        "meta_key": "has_excerpt",
        "meta_value": true
      }
    }
  ]
}
```

### Numeric comparison

```json
{
  "type": "conditional",
  "condition": {
    "field": "$product.stock_quantity",
    "operator": "less_than",
    "value": 10
  },
  "then": [
    {
      "type": "ability",
      "ability": "core/update-post-meta",
      "input": {
        "post_id": "$product.ID",
        "meta_key": "low_stock_alert",
        "meta_value": true
      }
    }
  ]
}
```

## Available Comparison Operators

| Operator | Alias | Description | Example |
|----------|-------|-------------|---------|
| `equals` | `==`, `===` | Exact match | `"draft" == "draft"` |
| `not_equals` | `!=`, `!==` | Not equal | `"publish" != "draft"` |
| `greater_than` | `>` | Numeric greater than | `10 > 5` |
| `less_than` | `<` | Numeric less than | `5 < 10` |
| `greater_than_or_equal` | `>=` | Greater or equal | `10 >= 10` |
| `less_than_or_equal` | `<=` | Less or equal | `5 <= 10` |
| `contains` | - | String contains substring | `"hello world"` contains `"world"` |
| `starts_with` | - | String starts with | `"draft-post"` starts_with `"draft"` |
| `ends_with` | - | String ends with | `"image.jpg"` ends_with `".jpg"` |
| `in` | - | Value in array | `"draft"` in `["draft", "pending"]` |
| `not_in` | - | Value not in array | `"publish"` not_in `["draft", "pending"]` |
| `empty` | - | Value is empty | `""` is empty |
| `not_empty` | - | Value is not empty | `"text"` is not_empty |
| `null` | - | Value is null | `null` is null |
| `not_null` | - | Value is not null | `"value"` is not_null |

## Complex Conditionals

### AND Logic (all conditions must be true)

```json
{
  "type": "conditional",
  "condition": {
    "operator": "and",
    "conditions": [
      {
        "field": "$post.post_status",
        "operator": "equals",
        "value": "publish"
      },
      {
        "field": "$post.comment_status",
        "operator": "equals",
        "value": "open"
      }
    ]
  },
  "then": [
    {
      "type": "ability",
      "ability": "core/update-post-meta",
      "input": {
        "post_id": "$post.ID",
        "meta_key": "accepting_comments",
        "meta_value": true
      }
    }
  ]
}
```

### OR Logic (any condition can be true)

```json
{
  "type": "conditional",
  "condition": {
    "operator": "or",
    "conditions": [
      {
        "field": "$post.post_status",
        "operator": "equals",
        "value": "draft"
      },
      {
        "field": "$post.post_status",
        "operator": "equals",
        "value": "pending"
      }
    ]
  },
  "then": [
    {
      "type": "ability",
      "ability": "core/update-post",
      "input": {
        "post_id": "$post.ID",
        "post_status": "publish"
      }
    }
  ]
}
```

### Nested Conditionals

```json
{
  "type": "conditional",
  "condition": {
    "field": "$post.post_type",
    "operator": "equals",
    "value": "post"
  },
  "then": [
    {
      "type": "conditional",
      "condition": {
        "field": "$post.post_status",
        "operator": "equals",
        "value": "draft"
      },
      "then": [
        {
          "type": "ability",
          "ability": "core/update-post",
          "input": {
            "post_id": "$post.ID",
            "post_status": "pending"
          }
        }
      ],
      "else": [
        {
          "type": "ability",
          "ability": "core/update-post-meta",
          "input": {
            "post_id": "$post.ID",
            "meta_key": "reviewed",
            "meta_value": true
          }
        }
      ]
    }
  ]
}
```

## Conditionals in Loops

```json
{
  "type": "loop",
  "input": "$posts",
  "itemVar": "post",
  "steps": [
    {
      "type": "conditional",
      "condition": {
        "field": "$post.post_status",
        "operator": "equals",
        "value": "draft"
      },
      "then": [
        {
          "type": "ability",
          "ability": "core/delete-post",
          "input": {"post_id": "$post.ID"}
        }
      ],
      "else": [
        {
          "type": "ability",
          "ability": "core/update-post-meta",
          "input": {
            "post_id": "$post.ID",
            "meta_key": "kept",
            "meta_value": true
          }
        }
      ]
    }
  ]
}
```

## Complete Example: Multi-Stage Content Workflow

```json
{
  "steps": [
    {
      "type": "ability",
      "ability": "core/list-posts",
      "input": {"posts_per_page": 50},
      "output": "all_posts"
    },
    {
      "type": "loop",
      "input": "$all_posts.posts",
      "itemVar": "post",
      "steps": [
        {
          "type": "conditional",
          "condition": {
            "operator": "and",
            "conditions": [
              {
                "field": "$post.post_status",
                "operator": "equals",
                "value": "draft"
              },
              {
                "field": "$post.post_content",
                "operator": "not_empty"
              }
            ]
          },
          "then": [
            {
              "type": "conditional",
              "condition": {
                "field": "$post.post_excerpt",
                "operator": "empty"
              },
              "then": [
                {
                  "type": "ability",
                  "ability": "core/update-post",
                  "input": {
                    "post_id": "$post.ID",
                    "post_excerpt": "Auto-generated",
                    "post_status": "pending"
                  }
                }
              ],
              "else": [
                {
                  "type": "ability",
                  "ability": "core/update-post",
                  "input": {
                    "post_id": "$post.ID",
                    "post_status": "publish"
                  }
                }
              ]
            }
          ],
          "else": [
            {
              "type": "ability",
              "ability": "core/update-post-meta",
              "input": {
                "post_id": "$post.ID",
                "meta_key": "needs_review",
                "meta_value": true
              }
            }
          ]
        }
      ]
    }
  ]
}
```

## Testing Conditionals

Use validation mode to test conditional logic without executing:

```json
{
  "pipeline": { /* your pipeline with conditionals */ },
  "validate_only": true
}
```

This will validate the structure without actually running the abilities.
