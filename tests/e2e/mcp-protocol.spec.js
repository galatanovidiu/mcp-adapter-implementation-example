// @ts-check
const { test, expect } = require('@playwright/test');
const { getAuthHeader } = require('./helpers/test-credentials');
const { 
  validateJSONRPCResponse, 
  validateInitializeResult, 
  validateListToolsResult,
  validateCallToolResult,
  validateJSONRPCError 
} = require('./helpers/mcp-validators');

test.describe('MCP Protocol Endpoint', () => {
  
  test('should handle MCP initialize request with Basic Auth', async ({ request }) => {
    console.info('🧪 Starting MCP initialize test...');
    
    // Create MCP initialize request according to MCP protocol spec
    const mcpRequest = {
      jsonrpc: '2.0',
      method: 'initialize',
      params: {
        protocolVersion: '2025-06-18',
        capabilities: {
          roots: {
            listChanged: true
          }
        },
        clientInfo: {
          name: 'test-client',
          version: '1.0.0'
        }
      },
      id: 1
    };
    
    console.info('📤 Sending MCP initialize request:', JSON.stringify(mcpRequest, null, 2));
    console.info('🔐 Using authorization header:', getAuthHeader().substring(0, 20) + '...');
    
    // Make authenticated request to MCP endpoint
    const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': getAuthHeader()
      },
      data: mcpRequest
    });
    
    console.info('📥 Response status:', response.status());
    
    // Check response status
    if (response.status() !== 200) {
      const errorBody = await response.text();
      console.error('❌ Request failed with status:', response.status());
      console.error('📋 Error response body:', errorBody);
      console.error('📤 Request that failed:', JSON.stringify(mcpRequest, null, 2));
      console.error('🔍 Response headers:', response.headers());
    }
    expect(response.status()).toBe(200);
    
    // Parse response
    const responseData = await response.json();
    
    // Validate response against MCP schema
    console.info('🔍 Validating initialize response against MCP schema...');
    try {
      const result = validateJSONRPCResponse(responseData, 1);
      validateInitializeResult(result);
    } catch (error) {
      console.error('❌ Validation failed:', error.message);
      console.error('📋 Full response received:', JSON.stringify(responseData, null, 2));
      throw error;
    }
    const result = responseData;
    
    console.info('✅ Response structure is valid according to MCP spec');
    console.info('🗂️ Protocol version:', result.protocolVersion);
    console.info('🏷️ Server info:', `${result.serverInfo.name} v${result.serverInfo.version}`);
    
    console.log('MCP Initialize Response:', JSON.stringify(responseData, null, 2));
  });
  
  test('should reject unauthenticated requests', async ({ request }) => {
    console.info('🧪 Starting unauthenticated request test...');
    
    const mcpRequest = {
      jsonrpc: '2.0',
      method: 'initialize',
      params: {
        protocolVersion: '2025-06-18',
        capabilities: {},
        clientInfo: {
          name: 'test-client',
          version: '1.0.0'
        }
      },
      id: 1
    };
    
    console.info('📤 Sending unauthenticated MCP request...');
    
    // Make unauthenticated request
    const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json'
      },
      data: mcpRequest
    });
    
    console.info('📥 Unauthenticated response status:', response.status());
    
    // Should return 401 or 403
    expect([401, 403]).toContain(response.status());
    console.info('✅ Correctly rejected unauthenticated request');
  });
  
  test('should handle tools/list request', async ({ request }) => {
    console.info('🧪 Starting tools/list test...');
    
    // First initialize the session
    console.info('📤 Initializing MCP session...');
    const initRequest = {
      jsonrpc: '2.0',
      method: 'initialize',
      params: {
        protocolVersion: '2025-06-18',
        capabilities: {},
        clientInfo: {
          name: 'test-client',
          version: '1.0.0'
        }
      },
      id: 1
    };
    
    const initResponse = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': getAuthHeader()
      },
      data: initRequest
    });
    
    console.info('📥 Initialize response status:', initResponse.status());
    expect(initResponse.status()).toBe(200);
    
    // Now request tools list
    console.info('📤 Requesting tools list...');
    const toolsRequest = {
      jsonrpc: '2.0',
      method: 'tools/list',
      params: {},
      id: 2
    };
    
    const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': getAuthHeader()
      },
      data: toolsRequest
    });
    
    console.info('📥 Tools list response status:', response.status());
    if (response.status() !== 200) {
      const errorBody = await response.text();
      console.error('❌ Tools list request failed with status:', response.status());
      console.error('📋 Error response body:', errorBody);
      console.error('📤 Request that failed:', JSON.stringify(toolsRequest, null, 2));
    }
    expect(response.status()).toBe(200);
    
    const responseData = await response.json();
    
    // Validate response against MCP schema
    console.info('🔍 Validating tools/list response against MCP schema...');
    let result;
    try {
      result = validateJSONRPCResponse(responseData, 2);
      validateListToolsResult(result);
    } catch (error) {
      console.error('❌ Validation failed:', error.message);
      console.error('📋 Full response received:', JSON.stringify(responseData, null, 2));
      throw error;
    }
    
    console.info('✅ Response structure is valid according to MCP spec');
    console.info('🔧 Found', result.tools.length, 'tools');
    
    // Log details about first tool if available
    if (result.tools.length > 0) {
      const firstTool = result.tools[0];
      console.info('🔧 First tool name:', firstTool.name);
      if (firstTool.description) {
        console.info('🔧 First tool description:', firstTool.description);
      }
    }
    
    console.log('Tools List Response:', JSON.stringify(responseData, null, 2));
  });
  
  test('should handle tools/call request for wpmcp-example/list-posts with no arguments', async ({ request }) => {
    console.info('🧪 Starting tools/call test for wpmcp-example/list-posts...');
    
    // Test calling the wpmcp-example/list-posts tool with no arguments
    const toolName = 'wpmcp-example-list-posts';
    const testArguments = {}; // Empty arguments to test default behavior
    
    console.info('📤 Testing tool call:', toolName);
    console.info('📤 Calling tool with no arguments (testing defaults)');
    
    const toolCallRequest = {
      jsonrpc: '2.0',
      method: 'tools/call',
      params: {
        name: toolName,
        arguments: testArguments
      },
      id: 3
    };
    
    const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': getAuthHeader()
      },
      data: toolCallRequest
    });
    
    console.info('📥 Tools/call response status:', response.status());
    if (response.status() !== 200) {
      const errorBody = await response.text();
      console.error('❌ Tool call request failed with status:', response.status());
      console.error('📋 Error response body:', errorBody);
      console.error('📤 Request that failed:', JSON.stringify(toolCallRequest, null, 2));
      console.error('🔧 Tool that was being tested:', toolName);
    }
    expect(response.status()).toBe(200);
    
    const responseData = await response.json();
    
    // Validate response against MCP schema
    console.info('🔍 Validating tools/call response against MCP schema...');
    let result;
    try {
      result = validateJSONRPCResponse(responseData, 3);
      validateCallToolResult(result);
    } catch (error) {
      console.error('❌ Validation failed:', error.message);
      console.error('📋 Full response received:', JSON.stringify(responseData, null, 2));
      console.error('📤 Request that caused the error:', JSON.stringify(toolCallRequest, null, 2));
      throw error;
    }
    
    console.info('✅ Response structure is valid according to MCP spec');
    console.info('📄 Tool call successful, got', result.content.length, 'content items');
    
    // Log details about first content if available
    if (result.content.length > 0) {
      const firstContent = result.content[0];
      console.info('📄 First content type:', firstContent.type);
      if (firstContent.type === 'text') {
        console.info('📄 First content text preview:', firstContent.text.substring(0, 100) + '...');
      }
    }
    
    // Log error status if present
    if (result.hasOwnProperty('isError')) {
      console.info('⚠️ Tool call error status:', result.isError);
    }
    
    console.log('Tool Call Response:', JSON.stringify(responseData, null, 2));
  });
  
  test('should return error for invalid JSON-RPC request', async ({ request }) => {
    console.info('🧪 Starting invalid JSON-RPC test...');
    
    // Send invalid JSON-RPC request (missing required fields)
    const invalidRequest = {
      method: 'initialize'
      // Missing jsonrpc and id fields
    };
    
    console.info('📤 Sending invalid JSON-RPC request (missing fields)...');
    
    const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': getAuthHeader()
      },
      data: invalidRequest
    });
    
    const responseData = await response.json();
    
    console.info('📥 Invalid request response:', JSON.stringify(responseData, null, 2));
    console.info('📊 Response status:', response.status());
    
    // First check if we got an error status code
    if (response.status() >= 400) {
      console.info('✅ Correctly returned HTTP error status for invalid JSON-RPC');
      return;
    }
    
    // If status is 200, validate error response structure using schema validator
    console.info('🔍 Validating error response against MCP schema...');
    let error;
    try {
      error = validateJSONRPCError(responseData, null);
      console.info('✅ Correctly returned error for invalid JSON-RPC');
      console.info('❌ Error code:', error.code);
      console.info('❌ Error message:', error.message);
    } catch (validationError) {
      console.error('❌ Error validation failed:', validationError.message);
      console.error('📋 Full response received:', JSON.stringify(responseData, null, 2));
      console.error('📤 Request that caused the response:', JSON.stringify(invalidRequest, null, 2));
      
      // Check if this is a successful response that shouldn't be (which would be a problem)
      if (responseData.result !== undefined) {
        throw new Error('Expected error response but got successful result for invalid JSON-RPC request');
      }
      
      // If it's not a properly formatted error, that's also a problem
      throw new Error(`Invalid JSON-RPC request should return proper error format. Got: ${JSON.stringify(responseData, null, 2)}`);
    }
  });
  
  test('should return error for unknown method', async ({ request }) => {
    console.info('🧪 Starting unknown method test...');
    
    const unknownMethodRequest = {
      jsonrpc: '2.0',
      method: 'unknown/method',
      params: {},
      id: 4
    };
    
    console.info('📤 Sending request with unknown method...');
    
    const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': getAuthHeader()
      },
      data: unknownMethodRequest
    });
    
    const responseData = await response.json();
    
    console.info('📥 Unknown method response:', JSON.stringify(responseData, null, 2));
    
    // Validate JSON-RPC error response structure
    console.info('🔍 Validating JSON-RPC error response for unknown method...');
    let error;
    try {
      error = validateJSONRPCError(responseData, 4);
    } catch (validationError) {
      console.error('❌ Error validation failed:', validationError.message);
      console.error('📋 Full response received:', JSON.stringify(responseData, null, 2));
      console.error('📤 Request that caused the response:', JSON.stringify(unknownMethodRequest, null, 2));
      throw validationError;
    }
    
    console.info('✅ Correctly returned error for unknown method');
    console.info('❌ Error code:', error.code);
    console.info('❌ Error message:', error.message);
  });
});
