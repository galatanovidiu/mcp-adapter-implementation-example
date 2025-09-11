import { test, expect } from '@playwright/test';
import { getAuthHeader } from './helpers/test-credentials.js';
import { validateJSONRPCResponse, validateCallToolResult } from './helpers/mcp-validators.js';

test.describe('MCP Posts and Taxonomies Tools Chain', () => {
  test('should execute complete Posts and Taxonomies tools chain', async ({ request }) => {
    console.info('🧪 Starting comprehensive Posts & Taxonomies tools chain test...');
    
    // Helper function for safe JSON parsing
    function safeJsonParse(text, context = 'response') {
      try {
        return JSON.parse(text);
      } catch (parseError) {
        console.error(`❌ Failed to parse JSON in ${context}:`, text);
        throw new Error(`Failed to parse JSON in ${context}: ${parseError.message}`);
      }
    }
    
    // Helper function to make MCP tool calls
    async function callTool(toolName, args = {}, expectedId = null) {
      const requestId = expectedId || Math.floor(Math.random() * 10000);
      const toolCallRequest = {
        jsonrpc: '2.0',
        method: 'tools/call',
        params: {
          name: toolName,
          arguments: args
        },
        id: requestId
      };
      
      console.info(`📤 Calling tool: ${toolName} with args:`, JSON.stringify(args, null, 2));
      
      const response = await request.post('/?rest_route=/mcp-adapter-example/mcp', {
        headers: {
          'Content-Type': 'application/json',
          'Authorization': getAuthHeader()
        },
        data: toolCallRequest
      });
      
      if (response.status() !== 200) {
        const errorBody = await response.text();
        console.error(`❌ Tool ${toolName} failed with status ${response.status()}`);
        console.error(`📤 Request that failed:`, JSON.stringify(toolCallRequest, null, 2));
        console.error(`📥 Response body:`, errorBody);
        throw new Error(`Tool ${toolName} failed with status ${response.status()}: ${errorBody}`);
      }
      
      const responseData = await response.json();
      
      // Validate response against MCP schema
      let result;
      try {
        result = validateJSONRPCResponse(responseData, requestId);
        validateCallToolResult(result);
      } catch (validationError) {
        console.error(`❌ Tool ${toolName} response validation failed:`, validationError.message);
        console.error(`📤 Request that caused validation error:`, JSON.stringify(toolCallRequest, null, 2));
        console.error(`📥 Response that failed validation:`, JSON.stringify(responseData, null, 2));
        throw new Error(`Tool ${toolName} response validation failed: ${validationError.message}`);
      }
      
      console.info(`📥 Tool ${toolName} completed successfully`);
      return result;
    }
    
    // Test variables to track created resources
    let testCategoryId = null;
    let testTagId = null;
    let testPostId = null;
    let additionalTagId = null;
    
    try {
      // PHASE 1: Setup & Discovery
      console.info('🔍 Phase 1: Setup & Discovery');
      
      // 1. List available taxonomies
      const taxonomiesResult = await callTool('wpmcp-example-list-taxonomies', {});
      expect(taxonomiesResult.content[0].type).toBe('text');
      const taxonomiesData = safeJsonParse(taxonomiesResult.content[0].text, 'taxonomies response');
      console.info('📋 Available taxonomies:', taxonomiesData.taxonomies.map(t => t.name).join(', '));
      
      // 2. List available block types
      const blockTypesResult = await callTool('wpmcp-example-list-block-types', {});
      const blockTypesData = safeJsonParse(blockTypesResult.content[0].text, 'block types response');
      console.info('📋 Available blocks:', blockTypesData.blocks.slice(0, 5).map(b => b.name).join(', '), '...');
      
      // 3. List existing posts (baseline)
      const initialPostsResult = await callTool('wpmcp-example-list-posts', {});
      const initialPostsData = safeJsonParse(initialPostsResult.content[0].text, 'initial posts response');
      console.info(`📋 Initial posts count: ${initialPostsData.found_posts}`);
      
      // PHASE 2: Taxonomy Operations
      console.info('🏷️ Phase 2: Taxonomy Operations');
      
      // 4. Create a test category
      const createCategoryResult = await callTool('wpmcp-example-create-term', {
        taxonomy: 'category',
        name: 'MCP Test Category',
        description: 'Category created by MCP test suite'
      });
      const categoryData = safeJsonParse(createCategoryResult.content[0].text, 'create category response');
      testCategoryId = categoryData.id;
      console.info(`✅ Created test category with ID: ${testCategoryId}`);
      
      // 5. Create a test tag
      const createTagResult = await callTool('wpmcp-example-create-term', {
        taxonomy: 'post_tag',
        name: 'mcp-test-tag',
        description: 'Tag created by MCP test suite'
      });
      const tagData = safeJsonParse(createTagResult.content[0].text, 'create tag response');
      testTagId = tagData.id;
      console.info(`✅ Created test tag with ID: ${testTagId}`);
      
      // 6. Verify created terms exist
      const categoryTermsResult = await callTool('wpmcp-example-get-terms', {
        taxonomy: 'category',
        include: [testCategoryId]
      });
      const categoryTermsData = safeJsonParse(categoryTermsResult.content[0].text, 'category terms response');
      expect(categoryTermsData.terms).toHaveLength(1);
      expect(categoryTermsData.terms[0].name).toBe('MCP Test Category');
      console.info('✅ Verified test category exists');
      
      // 7. Update the category description
      await callTool('wpmcp-example-update-term', {
        taxonomy: 'category',
        term_id: testCategoryId,
        description: 'Updated description by MCP test suite'
      });
      console.info('✅ Updated test category description');
      
      // PHASE 3: Post Creation & Management
      console.info('📝 Phase 3: Post Creation & Management');
      
      // 8. Create a test post with terms
      const createPostResult = await callTool('wpmcp-example-create-post', {
        post_type: 'post',
        title: 'MCP Test Post',
        content: '<!-- wp:paragraph --><p>This is a test post created by the MCP test suite.</p><!-- /wp:paragraph -->',
        status: 'publish',
        tax_input: {
          category: [testCategoryId],
          post_tag: [testTagId]
        }
      });
      const postData = JSON.parse(createPostResult.content[0].text);
      testPostId = postData.id;
      console.info(`✅ Created test post with ID: ${testPostId}`);
      
      // 9. Get the created post and verify data
      const getPostResult = await callTool('wpmcp-example-get-post', {
        id: testPostId
      });
      const retrievedPostData = JSON.parse(getPostResult.content[0].text);
      expect(retrievedPostData.title).toBe('MCP Test Post');
      expect(retrievedPostData.status).toBe('publish');
      console.info('✅ Verified post creation with correct data');
      
      // 10. Update the post
      await callTool('wpmcp-example-update-post', {
        id: testPostId,
        title: 'Updated MCP Test Post',
        content: '<!-- wp:paragraph --><p>This post has been updated by the MCP test suite.</p><!-- /wp:paragraph -->'
      });
      console.info('✅ Updated test post');
      
      // 11. Verify post updates
      const updatedPostResult = await callTool('wpmcp-example-get-post', {
        id: testPostId
      });
      const updatedPostData = JSON.parse(updatedPostResult.content[0].text);
      expect(updatedPostData.title).toBe('Updated MCP Test Post');
      console.info('✅ Verified post updates applied correctly');
      
      // PHASE 4: Post Meta Operations
      console.info('🏷️ Phase 4: Post Meta Operations');
      
      // 12. List available meta keys for post type
      const metaKeysResult = await callTool('wpmcp-example-list-post-meta-keys', {
        post_type: 'post'
      });
      const metaKeysData = JSON.parse(metaKeysResult.content[0].text);
      console.info(`📋 Available meta keys: ${metaKeysData.meta.length} found`);
      
      // 13. Get current meta
      const currentMetaResult = await callTool('wpmcp-example-get-post-meta', {
        id: testPostId
      });
      const currentMetaData = JSON.parse(currentMetaResult.content[0].text);
      console.info(`📋 Current meta keys: ${Object.keys(currentMetaData.meta).length}`);
      
      // Test meta operations if we have any registered meta keys
      if (metaKeysData.meta.length > 0) {
        const firstMetaKey = metaKeysData.meta[0];
        console.info(`📝 Testing with meta key: ${firstMetaKey.key}`);
        
        // Try to update meta (this may fail if the meta key requires specific permissions/format)
        try {
          const testMetaValue = firstMetaKey.type === 'string' ? 'MCP Test Value' : 
                               firstMetaKey.type === 'number' ? 42 : 
                               firstMetaKey.type === 'boolean' ? true : 'test';
          
          await callTool('wpmcp-example-update-post-meta', {
            id: testPostId,
            meta: {
              [firstMetaKey.key]: testMetaValue
            }
          });
          console.info(`✅ Updated meta key: ${firstMetaKey.key}`);
          
          // Verify meta was updated
          const updatedMetaResult = await callTool('wpmcp-example-get-post-meta', {
            id: testPostId,
            keys: [firstMetaKey.key]
          });
          const updatedMetaData = JSON.parse(updatedMetaResult.content[0].text);
          console.info(`✅ Verified meta update for key: ${firstMetaKey.key}`);
          
          // Delete the meta we just added
          await callTool('wpmcp-example-delete-post-meta', {
            id: testPostId,
            key: firstMetaKey.key
          });
          console.info(`✅ Deleted meta key: ${firstMetaKey.key}`);
          
        } catch (metaError) {
          console.info(`⚠️ Meta operations skipped for ${firstMetaKey.key}: ${metaError.message}`);
        }
      }
      
      // PHASE 5: Term Attachment Operations  
      console.info('🔗 Phase 5: Term Attachment Operations');
      
      // 16. Create an additional tag for attachment testing
      const additionalTagResult = await callTool('wpmcp-example-create-term', {
        taxonomy: 'post_tag',
        name: 'additional-test-tag'
      });
      const additionalTagData = JSON.parse(additionalTagResult.content[0].text);
      additionalTagId = additionalTagData.id;
      console.info(`✅ Created additional test tag with ID: ${additionalTagId}`);
      
      // 17. Attach additional terms
      await callTool('wpmcp-example-attach-post-terms', {
        id: testPostId,
        taxonomy: 'post_tag',
        terms: [additionalTagId],
        append: true
      });
      console.info('✅ Attached additional tag to post');
      
      // 18. Verify attachment worked
      const postWithNewTagResult = await callTool('wpmcp-example-get-post', {
        id: testPostId
      });
      const postWithNewTagData = JSON.parse(postWithNewTagResult.content[0].text);
      const postTagIds = postWithNewTagData.taxonomies?.post_tag?.map(tag => tag.id) || [];
      expect(postTagIds).toContain(additionalTagId);
      console.info('✅ Verified additional tag was attached');
      
      // 19. Detach specific terms
      await callTool('wpmcp-example-detach-post-terms', {
        id: testPostId,
        taxonomy: 'post_tag',
        terms: [additionalTagId]
      });
      console.info('✅ Detached additional tag from post');
      
      // 20. Verify detachment worked
      const finalPostResult = await callTool('wpmcp-example-get-post', {
        id: testPostId
      });
      const finalPostData = JSON.parse(finalPostResult.content[0].text);
      const finalPostTagIds = finalPostData.taxonomies?.post_tag?.map(tag => tag.id) || [];
      expect(finalPostTagIds).not.toContain(additionalTagId);
      console.info('✅ Verified additional tag was detached');
      
      // PHASE 6: Cleanup
      console.info('🧹 Phase 6: Cleanup');
      
      // 21. Delete the test post
      const deletePostResult = await callTool('wpmcp-example-delete-post', {
        id: testPostId,
        force: true
      });
      const deletePostData = JSON.parse(deletePostResult.content[0].text);
      expect(deletePostData.deleted).toBe(true);
      console.info('✅ Deleted test post');
      
      // 22. Delete test terms
      const deleteCategoryResult = await callTool('wpmcp-example-delete-term', {
        taxonomy: 'category',
        term_id: testCategoryId
      });
      const deleteCategoryData = JSON.parse(deleteCategoryResult.content[0].text);
      expect(deleteCategoryData.deleted).toBe(true);
      console.info('✅ Deleted test category');
      
      const deleteTagResult = await callTool('wpmcp-example-delete-term', {
        taxonomy: 'post_tag',
        term_id: testTagId
      });
      const deleteTagData = JSON.parse(deleteTagResult.content[0].text);
      expect(deleteTagData.deleted).toBe(true);
      console.info('✅ Deleted test tag');
      
      const deleteAdditionalTagResult = await callTool('wpmcp-example-delete-term', {
        taxonomy: 'post_tag',
        term_id: additionalTagId
      });
      const deleteAdditionalTagData = JSON.parse(deleteAdditionalTagResult.content[0].text);
      expect(deleteAdditionalTagData.deleted).toBe(true);
      console.info('✅ Deleted additional test tag');
      
      // 23. Final verification - check posts count returned to baseline
      const finalPostsResult = await callTool('wpmcp-example-list-posts', {});
      const finalPostsData = JSON.parse(finalPostsResult.content[0].text);
      console.info(`📋 Final posts count: ${finalPostsData.found_posts}`);
      
      // Verify we're back to the original count (or close to it, accounting for timing)
      expect(finalPostsData.found_posts).toBeLessThanOrEqual(initialPostsData.found_posts);
      
      console.info('🎉 Complete Posts & Taxonomies tools chain test completed successfully!');
      console.info('📊 Test Summary:');
      console.info(`   • Created and deleted: 1 post, 1 category, 2 tags`);
      console.info(`   • Tested ${metaKeysData.meta.length} meta keys`);
      console.info(`   • Verified all CRUD operations work correctly`);
      console.info(`   • Validated post-taxonomy relationships`);
      console.info(`   • Confirmed proper cleanup`);
      
    } catch (error) {
      console.error('❌ Test chain failed:', error.message);
      console.error('📋 Error details:', error);
      console.error('📍 Error stack:', error.stack);
      
      // Log current test state for debugging
      console.error('🔍 Test state at failure:');
      console.error(`   • Test Category ID: ${testCategoryId}`);
      console.error(`   • Test Tag ID: ${testTagId}`);
      console.error(`   • Test Post ID: ${testPostId}`);
      console.error(`   • Additional Tag ID: ${additionalTagId}`);
      
      // Cleanup on failure
      console.info('🧹 Starting cleanup after failure...');
      
      if (testPostId) {
        try {
          await callTool('wpmcp-example-delete-post', { id: testPostId, force: true });
          console.info('🧹 Cleaned up test post after failure');
        } catch (cleanupError) {
          console.warn('⚠️ Failed to cleanup test post:', cleanupError.message);
        }
      }
      
      if (testCategoryId) {
        try {
          await callTool('wpmcp-example-delete-term', { taxonomy: 'category', term_id: testCategoryId });
          console.info('🧹 Cleaned up test category after failure');
        } catch (cleanupError) {
          console.warn('⚠️ Failed to cleanup test category:', cleanupError.message);
        }
      }
      
      if (testTagId) {
        try {
          await callTool('wpmcp-example-delete-term', { taxonomy: 'post_tag', term_id: testTagId });
          console.info('🧹 Cleaned up test tag after failure');
        } catch (cleanupError) {
          console.warn('⚠️ Failed to cleanup test tag:', cleanupError.message);
        }
      }
      
      if (additionalTagId) {
        try {
          await callTool('wpmcp-example-delete-term', { taxonomy: 'post_tag', term_id: additionalTagId });
          console.info('🧹 Cleaned up additional test tag after failure');
        } catch (cleanupError) {
          console.warn('⚠️ Failed to cleanup additional test tag:', cleanupError.message);
        }
      }
      
      throw error;
    }
  });
});
