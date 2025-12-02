# Pipeline Examples

This directory contains example pipeline definitions demonstrating various features of the declarative pipeline system.

## What is a Pipeline?

A pipeline is a declarative JSON definition that orchestrates multiple WordPress abilities and data transformations without requiring custom PHP code. Pipelines achieve 90%+ token reduction compared to traditional MCP by processing data locally instead of passing it through the AI model.

## Running Pipelines

### Via WP-CLI

```bash
# Execute a pipeline
wp mcp-pipeline execute examples/pipelines/content-processing.json

# Validate without executing
wp mcp-pipeline validate examples/pipelines/content-processing.json

# Dry-run to see execution plan
wp mcp-pipeline dry-run examples/pipelines/content-processing.json

# Execute with custom context
wp mcp-pipeline execute examples/pipelines/content-processing.json --context='{"user_id": 1}'
```

### Via MCP Ability

Use the `mcp-adapter/execute-pipeline` ability through any MCP client:

```json
{
  "ability": "mcp-adapter/execute-pipeline",
  "input": {
    "pipeline": { ... },
    "context": {},
    "tokenize_sensitive": true
  }
}
```

## Example Pipelines

### 1. content-processing.json
**Purpose**: Analyze published posts and update metadata

**Features demonstrated**:
- Fetching posts with `list-posts` ability
- Filtering data with transformations
- Looping over arrays
- Conditional logic
- Updating post metadata

**Use case**: Batch process content to add analytics metadata

### 2. user-management.json
**Purpose**: Find inactive users and extract user data

**Features demonstrated**:
- Parallel execution of independent steps
- Multiple data transformations (pluck, filter, slice)
- Working with user data

**Use case**: User analytics and segmentation

### 3. error-handling.json
**Purpose**: Demonstrate error recovery patterns

**Features demonstrated**:
- Try/catch blocks
- Fallback creation on errors
- Finally blocks that always execute

**Use case**: Robust pipelines that handle failures gracefully

### 4. woocommerce-inventory.json
**Purpose**: Update product stock levels based on conditions

**Features demonstrated**:
- WooCommerce integration
- Complex conditionals (if/else)
- Looping with conditions
- Product updates

**Use case**: Automated inventory management

### 5. data-aggregation.json
**Purpose**: Demonstrate various data transformation operations

**Features demonstrated**:
- Parallel data extraction
- Multiple transformations (count, pluck, unique, sort, slice, join)
- Data pipeline patterns

**Use case**: Reporting and analytics

## Pipeline Structure

All pipelines follow this basic structure:

```json
{
  "description": "Optional description",
  "steps": [
    {
      "type": "ability|transform|conditional|loop|parallel|try_catch|sub_pipeline",
      "output": "variable_name",
      "description": "Optional step description",
      ... step-specific configuration ...
    }
  ]
}
```

## Step Types

### ability
Execute a registered WordPress ability:

```json
{
  "type": "ability",
  "ability": "core/list-posts",
  "input": {
    "posts_per_page": 10
  },
  "output": "posts"
}
```

### transform
Apply data transformations:

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

**Available operations**: filter, map, pluck, unique, sort, reverse, slice, chunk, flatten, merge, count, sum, average, min, max, join, split, trim, uppercase, lowercase

### conditional
If/then/else logic:

```json
{
  "type": "conditional",
  "condition": {
    "field": "$post.post_status",
    "operator": "equals",
    "value": "draft"
  },
  "then": [ ... steps ... ],
  "else": [ ... steps ... ]
}
```

### loop
Iterate over arrays:

```json
{
  "type": "loop",
  "input": "$posts",
  "itemVar": "post",
  "indexVar": "index",
  "steps": [ ... steps to execute for each item ... ]
}
```

### parallel
Execute steps concurrently:

```json
{
  "type": "parallel",
  "steps": [
    { "type": "ability", ... },
    { "type": "transform", ... }
  ]
}
```

### try_catch
Error handling:

```json
{
  "type": "try_catch",
  "try": [ ... steps ... ],
  "catch": [ ... error handler steps ... ],
  "finally": [ ... cleanup steps ... ]
}
```

### sub_pipeline
Nested pipelines:

```json
{
  "type": "sub_pipeline",
  "pipeline": {
    "steps": [ ... ]
  },
  "inputs": {
    "var1": "$parent_var"
  }
}
```

## Variable References

Reference variables from context using `$` prefix:

- Simple: `$posts`
- Array access: `$posts[0]`
- Property access: `$post.title` or `$post['title']`
- Nested: `$post.meta.author.name`

## Security Features

- **No arbitrary code execution** - Only registered abilities can be called
- **Permission checks** - Each ability enforces WordPress permissions
- **Sensitive data tokenization** - Automatic tokenization of passwords, emails, etc.
- **Resource limits** - Maximum steps (1000), depth (10), timeout (5min)

## Performance Benefits

Compared to traditional MCP tool chaining:

- **90%+ token reduction** - Process data locally, only return summaries
- **No intermediate model passes** - Data flows directly between steps
- **Parallel execution** - Independent steps run concurrently
- **Efficient control flow** - Loops and conditionals execute without model interaction

## Best Practices

1. **Use descriptive output names**: `$published_posts` instead of `$result1`
2. **Add descriptions**: Help future readers understand each step
3. **Handle errors**: Use try/catch for operations that might fail
4. **Filter early**: Reduce data volume as early as possible
5. **Parallelize**: Use parallel steps for independent operations
6. **Test with dry-run**: Always validate before executing

## Creating Custom Pipelines

1. Start with a simple linear flow
2. Add error handling where needed
3. Optimize with parallel execution
4. Test with validation and dry-run
5. Execute with appropriate permissions

## Support

For issues or questions:
- Validate your pipeline: `wp mcp-pipeline validate your-pipeline.json`
- Check available transformations: `wp mcp-pipeline list-transforms`
- Review error messages carefully - they include the path to the problematic step
