# Transform Operations Guide

Complete reference for all transform operations in the pipeline system.

## Transform Step Syntax

```json
{
  "type": "transform",
  "operation": "operation_name",
  "input": "$variable_reference",
  "params": { /* operation-specific parameters */ },
  "output": "result_variable"
}
```

## Array Operations

### filter
Filter array elements based on conditions.

```json
{
  "type": "transform",
  "operation": "filter",
  "input": "$posts",
  "params": {
    "condition": {
      "field": "post_status",
      "operator": "equals",
      "value": "publish"
    }
  },
  "output": "published_posts"
}
```

**Available operators:**
- `equals`, `==`, `===` - Exact match
- `not_equals`, `!=`, `!==` - Not equal
- `greater_than`, `>` - Numeric greater than
- `less_than`, `<` - Numeric less than
- `greater_than_or_equal`, `>=` - Greater or equal
- `less_than_or_equal`, `<=` - Less or equal
- `contains` - String contains substring
- `starts_with` - String starts with
- `ends_with` - String ends with
- `in` - Value in array
- `not_in` - Value not in array
- `empty` - Value is empty
- `not_empty` - Value is not empty
- `null` - Value is null
- `not_null` - Value is not null

### map
Extract a specific field from each array element.

```json
{
  "type": "transform",
  "operation": "map",
  "input": "$posts",
  "params": {
    "field": "post_title"
  },
  "output": "titles"
}
```

### pluck
Similar to map, extracts a column from array of arrays/objects.

```json
{
  "type": "transform",
  "operation": "pluck",
  "input": "$posts",
  "params": {
    "field": "ID"
  },
  "output": "post_ids"
}
```

### unique
Remove duplicate values.

```json
{
  "type": "transform",
  "operation": "unique",
  "input": "$author_ids",
  "output": "unique_authors"
}
```

### sort
Sort array elements.

```json
{
  "type": "transform",
  "operation": "sort",
  "input": "$posts",
  "params": {
    "direction": "asc",  // or "desc"
    "field": "post_date"  // optional, for sorting by field
  },
  "output": "sorted_posts"
}
```

### reverse
Reverse array order.

```json
{
  "type": "transform",
  "operation": "reverse",
  "input": "$posts",
  "output": "reversed_posts"
}
```

### slice
Extract a portion of an array.

```json
{
  "type": "transform",
  "operation": "slice",
  "input": "$posts",
  "params": {
    "offset": 0,
    "length": 10
  },
  "output": "first_10_posts"
}
```

### chunk
Split array into chunks.

```json
{
  "type": "transform",
  "operation": "chunk",
  "input": "$posts",
  "params": {
    "size": 5
  },
  "output": "post_chunks"
}
```

### flatten
Flatten multi-dimensional array.

```json
{
  "type": "transform",
  "operation": "flatten",
  "input": "$nested_array",
  "params": {
    "depth": 1  // optional, null = unlimited
  },
  "output": "flat_array"
}
```

### merge
Merge two arrays.

```json
{
  "type": "transform",
  "operation": "merge",
  "input": "$array1",
  "params": {
    "with": "$array2"
  },
  "output": "merged_array"
}
```

## Aggregation Operations

### count
Count array elements.

```json
{
  "type": "transform",
  "operation": "count",
  "input": "$posts",
  "output": "post_count"
}
```

### sum
Sum numeric values.

```json
{
  "type": "transform",
  "operation": "sum",
  "input": "$numbers",
  "output": "total"
}
```

Or sum a field from array of objects:

```json
{
  "type": "transform",
  "operation": "sum",
  "input": "$products",
  "params": {
    "field": "price"
  },
  "output": "total_price"
}
```

### average
Calculate average.

```json
{
  "type": "transform",
  "operation": "average",
  "input": "$scores",
  "output": "avg_score"
}
```

Or average a field:

```json
{
  "type": "transform",
  "operation": "average",
  "input": "$products",
  "params": {
    "field": "rating"
  },
  "output": "avg_rating"
}
```

### min
Find minimum value.

```json
{
  "type": "transform",
  "operation": "min",
  "input": "$prices",
  "output": "lowest_price"
}
```

### max
Find maximum value.

```json
{
  "type": "transform",
  "operation": "max",
  "input": "$prices",
  "output": "highest_price"
}
```

## String Operations

### join
Join array into string.

```json
{
  "type": "transform",
  "operation": "join",
  "input": "$titles",
  "params": {
    "separator": ", "
  },
  "output": "titles_string"
}
```

### split
Split string into array.

```json
{
  "type": "transform",
  "operation": "split",
  "input": "$csv_string",
  "params": {
    "separator": ","
  },
  "output": "values_array"
}
```

### trim
Remove whitespace from string.

```json
{
  "type": "transform",
  "operation": "trim",
  "input": "$text",
  "output": "trimmed_text"
}
```

### uppercase
Convert to uppercase.

```json
{
  "type": "transform",
  "operation": "uppercase",
  "input": "$text",
  "output": "upper_text"
}
```

### lowercase
Convert to lowercase.

```json
{
  "type": "transform",
  "operation": "lowercase",
  "input": "$text",
  "output": "lower_text"
}
```

## Complete Example Pipeline

```json
{
  "steps": [
    {
      "type": "ability",
      "ability": "core/list-posts",
      "input": {"posts_per_page": 100},
      "output": "all_posts"
    },
    {
      "type": "transform",
      "operation": "filter",
      "input": "$all_posts.posts",
      "params": {
        "condition": {
          "field": "post_status",
          "operator": "equals",
          "value": "publish"
        }
      },
      "output": "published_posts"
    },
    {
      "type": "transform",
      "operation": "pluck",
      "input": "$published_posts",
      "params": {"field": "post_author"},
      "output": "author_ids"
    },
    {
      "type": "transform",
      "operation": "unique",
      "input": "$author_ids",
      "output": "unique_authors"
    },
    {
      "type": "transform",
      "operation": "count",
      "input": "$unique_authors",
      "output": "author_count"
    }
  ]
}
```
