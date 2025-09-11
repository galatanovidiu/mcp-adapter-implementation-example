const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

async function globalSetup() {
  console.log('🧹 Cleaning test database before running e2e tests...');
  
  try {
    // Reset the test database
    execSync('wp-env run tests-cli wp db reset --yes', { 
      stdio: 'inherit',
      timeout: 30000 
    });
    
    // Reinstall WordPress with fresh data
    execSync('wp-env run tests-cli wp core install --url=http://localhost:8891 --title="Test Site" --admin_user=admin --admin_password=password --admin_email=admin@example.com --skip-email', { 
      stdio: 'inherit',
      timeout: 30000 
    });
    
    console.log('✅ Test database cleaned and WordPress reinstalled');
    
    // Activate the MCP Adapter Implementation Example plugin
    console.log('🔌 Activating MCP Adapter Implementation Example plugin...');
    
    execSync('wp-env run tests-cli wp plugin activate mcp-adapter-implementation-example', { 
      stdio: 'inherit',
      timeout: 15000 
    });
    
    console.log('✅ Plugin activated successfully');
    
    // Generate API password for the admin user
    console.log('🔑 Generating API password for admin user...');
    
    const apiPasswordOutput = execSync('wp-env run tests-cli wp user application-password create admin "E2E Test App" --porcelain', { 
      encoding: 'utf8',
      timeout: 15000 
    });
    
    const apiPassword = apiPasswordOutput.trim();
    
    if (apiPassword) {
      // Save the API password to a file that tests can read
      const testDataDir = path.join(__dirname, 'test-data');
      if (!fs.existsSync(testDataDir)) {
        fs.mkdirSync(testDataDir, { recursive: true });
      }
      
      const testCredentials = {
        username: 'admin',
        password: 'password',
        apiPassword: apiPassword,
        basicAuth: Buffer.from(`admin:${apiPassword}`).toString('base64')
      };
      
      fs.writeFileSync(
        path.join(testDataDir, 'credentials.json'),
        JSON.stringify(testCredentials, null, 2)
      );
      
      console.log('✅ API password generated and saved to test-data/credentials.json');
    } else {
      console.warn('⚠️  Failed to generate API password, falling back to basic auth');
    }
    
    // Wait a moment for the database to be fully ready
    await new Promise(resolve => setTimeout(resolve, 2000));
    
  } catch (error) {
    console.error('❌ Failed to clean test database:', error.message);
    process.exit(1);
  }
}

module.exports = globalSetup;