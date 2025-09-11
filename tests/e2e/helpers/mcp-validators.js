/**
 * MCP Protocol Response Validators
 * Based on MCP specification schema types
 */

/**
 * Validates a JSON-RPC response structure
 * Note: The MCP REST endpoint returns only the result portion, not the full JSON-RPC wrapper
 */
function validateJSONRPCResponse(response, expectedId) {
  if (!response || typeof response !== 'object') {
    throw new Error('Response must be an object');
  }
  
  // The REST endpoint returns the result directly, not wrapped in JSON-RPC structure
  // So we just return the response as-is for further validation
  return response;
}

/**
 * Validates MCP Initialize Result
 */
function validateInitializeResult(result) {
  if (!result || typeof result !== 'object') {
    throw new Error('Initialize result must be an object');
  }
  
  // Required fields
  if (typeof result.protocolVersion !== 'string') {
    throw new Error('protocolVersion must be a string');
  }
  
  if (!result.capabilities || typeof result.capabilities !== 'object') {
    throw new Error('capabilities must be an object');
  }
  
  if (!result.serverInfo || typeof result.serverInfo !== 'object') {
    throw new Error('serverInfo must be an object');
  }
  
  // Validate serverInfo
  if (typeof result.serverInfo.name !== 'string') {
    throw new Error('serverInfo.name must be a string');
  }
  
  if (typeof result.serverInfo.version !== 'string') {
    throw new Error('serverInfo.version must be a string');
  }
  
  // Optional instructions field
  if (result.instructions && typeof result.instructions !== 'string') {
    throw new Error('instructions must be a string if present');
  }
  
  return result;
}

/**
 * Validates Tools List Result
 */
function validateListToolsResult(result) {
  if (!result || typeof result !== 'object') {
    throw new Error('Tools list result must be an object');
  }
  
  if (!Array.isArray(result.tools)) {
    throw new Error('tools must be an array');
  }
  
  // Validate each tool
  result.tools.forEach((tool, index) => {
    try {
      validateTool(tool);
    } catch (error) {
      throw new Error(`Tool ${index}: ${error.message}`);
    }
  });
  
  // Optional pagination fields
  if (result.nextCursor && typeof result.nextCursor !== 'string') {
    throw new Error('nextCursor must be a string if present');
  }
  
  return result;
}

/**
 * Validates a single Tool definition
 */
function validateTool(tool) {
  if (!tool || typeof tool !== 'object') {
    throw new Error('Tool must be an object');
  }
  
  // Required: name
  if (typeof tool.name !== 'string') {
    throw new Error('Tool name must be a string');
  }
  
  // Required: inputSchema
  if (!tool.inputSchema || typeof tool.inputSchema !== 'object') {
    throw new Error('Tool inputSchema must be an object');
  }
  
  if (tool.inputSchema.type !== 'object') {
    throw new Error('Tool inputSchema.type must be "object"');
  }
  
  // Optional: description
  if (tool.description && typeof tool.description !== 'string') {
    throw new Error('Tool description must be a string if present');
  }
  
  // Optional: title (in BaseMetadata)
  if (tool.title && typeof tool.title !== 'string') {
    throw new Error('Tool title must be a string if present');
  }
  
  // Optional: outputSchema
  if (tool.outputSchema) {
    if (typeof tool.outputSchema !== 'object') {
      throw new Error('Tool outputSchema must be an object if present');
    }
    if (tool.outputSchema.type !== 'object') {
      throw new Error('Tool outputSchema.type must be "object" if present');
    }
  }
  
  // Optional: annotations
  if (tool.annotations) {
    validateToolAnnotations(tool.annotations);
  }
  
  return tool;
}

/**
 * Validates Tool Annotations
 */
function validateToolAnnotations(annotations) {
  if (typeof annotations !== 'object') {
    throw new Error('Tool annotations must be an object');
  }
  
  if (annotations.title && typeof annotations.title !== 'string') {
    throw new Error('Tool annotations.title must be a string if present');
  }
  
  if (annotations.readOnlyHint && typeof annotations.readOnlyHint !== 'boolean') {
    throw new Error('Tool annotations.readOnlyHint must be a boolean if present');
  }
  
  if (annotations.destructiveHint && typeof annotations.destructiveHint !== 'boolean') {
    throw new Error('Tool annotations.destructiveHint must be a boolean if present');
  }
  
  if (annotations.idempotentHint && typeof annotations.idempotentHint !== 'boolean') {
    throw new Error('Tool annotations.idempotentHint must be a boolean if present');
  }
  
  if (annotations.openWorldHint && typeof annotations.openWorldHint !== 'boolean') {
    throw new Error('Tool annotations.openWorldHint must be a boolean if present');
  }
}

/**
 * Validates Tool Call Result
 */
function validateCallToolResult(result) {
  if (!result || typeof result !== 'object') {
    throw new Error('Tool call result must be an object');
  }
  
  // Required: content array
  if (!Array.isArray(result.content)) {
    throw new Error('content must be an array');
  }
  
  // Validate each content block
  result.content.forEach((contentBlock, index) => {
    try {
      validateContentBlock(contentBlock);
    } catch (error) {
      throw new Error(`Content block ${index}: ${error.message}`);
    }
  });
  
  // Optional: structuredContent
  if (result.structuredContent && typeof result.structuredContent !== 'object') {
    throw new Error('structuredContent must be an object if present');
  }
  
  // Optional: isError
  if (result.isError !== undefined && typeof result.isError !== 'boolean') {
    throw new Error('isError must be a boolean if present');
  }
  
  return result;
}

/**
 * Validates a Content Block (union type)
 */
function validateContentBlock(contentBlock) {
  if (!contentBlock || typeof contentBlock !== 'object') {
    throw new Error('Content block must be an object');
  }
  
  if (typeof contentBlock.type !== 'string') {
    throw new Error('Content block type must be a string');
  }
  
  switch (contentBlock.type) {
    case 'text':
      validateTextContent(contentBlock);
      break;
    case 'image':
      validateImageContent(contentBlock);
      break;
    case 'audio':
      validateAudioContent(contentBlock);
      break;
    case 'resource':
      validateEmbeddedResource(contentBlock);
      break;
    case 'resource_link':
      validateResourceLink(contentBlock);
      break;
    default:
      throw new Error(`Unknown content block type: ${contentBlock.type}`);
  }
  
  // Optional: annotations
  if (contentBlock.annotations) {
    validateAnnotations(contentBlock.annotations);
  }
  
  return contentBlock;
}

/**
 * Validates Text Content
 */
function validateTextContent(content) {
  if (typeof content.text !== 'string') {
    throw new Error('Text content must have a text string property');
  }
}

/**
 * Validates Image Content
 */
function validateImageContent(content) {
  if (typeof content.data !== 'string') {
    throw new Error('Image content must have a data string property');
  }
  
  if (typeof content.mimeType !== 'string') {
    throw new Error('Image content must have a mimeType string property');
  }
}

/**
 * Validates Audio Content
 */
function validateAudioContent(content) {
  if (typeof content.data !== 'string') {
    throw new Error('Audio content must have a data string property');
  }
  
  if (typeof content.mimeType !== 'string') {
    throw new Error('Audio content must have a mimeType string property');
  }
}

/**
 * Validates Embedded Resource
 */
function validateEmbeddedResource(content) {
  if (!content.resource || typeof content.resource !== 'object') {
    throw new Error('Embedded resource must have a resource object');
  }
  
  if (typeof content.resource.uri !== 'string') {
    throw new Error('Embedded resource must have a uri string');
  }
}

/**
 * Validates Resource Link
 */
function validateResourceLink(content) {
  if (typeof content.uri !== 'string') {
    throw new Error('Resource link must have a uri string property');
  }
  
  if (typeof content.name !== 'string') {
    throw new Error('Resource link must have a name string property');
  }
}

/**
 * Validates Annotations
 */
function validateAnnotations(annotations) {
  if (typeof annotations !== 'object') {
    throw new Error('Annotations must be an object');
  }
  
  if (annotations.audience) {
    if (!Array.isArray(annotations.audience)) {
      throw new Error('Annotations.audience must be an array if present');
    }
    annotations.audience.forEach(role => {
      if (!['user', 'assistant'].includes(role)) {
        throw new Error(`Invalid audience role: ${role}`);
      }
    });
  }
  
  if (annotations.priority !== undefined) {
    if (typeof annotations.priority !== 'number' || annotations.priority < 0 || annotations.priority > 1) {
      throw new Error('Annotations.priority must be a number between 0 and 1 if present');
    }
  }
  
  if (annotations.lastModified && typeof annotations.lastModified !== 'string') {
    throw new Error('Annotations.lastModified must be a string if present');
  }
}

/**
 * Validates JSON-RPC Error response
 * Note: The MCP REST endpoint may return errors in different formats
 */
function validateJSONRPCError(response, expectedId) {
  if (!response || typeof response !== 'object') {
    throw new Error('Response must be an object');
  }
  
  // Handle direct error object (REST endpoint format)
  if (response.code !== undefined && response.message !== undefined) {
    if (typeof response.code !== 'number') {
      throw new Error('Error code must be a number');
    }
    
    if (typeof response.message !== 'string') {
      throw new Error('Error message must be a string');
    }
    
    return response;
  }
  
  // Handle wrapped error object (full JSON-RPC format)
  if (response.error) {
    if (typeof response.error.code !== 'number') {
      throw new Error('Error code must be a number');
    }
    
    if (typeof response.error.message !== 'string') {
      throw new Error('Error message must be a string');
    }
    
    return response.error;
  }
  
  throw new Error('Error response must have error information');
}

module.exports = {
  validateJSONRPCResponse,
  validateInitializeResult,
  validateListToolsResult,
  validateTool,
  validateCallToolResult,
  validateContentBlock,
  validateJSONRPCError
};
