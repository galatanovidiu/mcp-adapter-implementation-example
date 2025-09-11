const fs = require('fs');
const path = require('path');

/**
 * Load test credentials generated during global setup
 * @returns {Object} Test credentials object
 */
function loadTestCredentials() {
  const credentialsPath = path.join(__dirname, '..', 'test-data', 'credentials.json');
  
  if (!fs.existsSync(credentialsPath)) {
    console.warn('⚠️  No credentials file found, using fallback basic auth');
    return {
      username: 'admin',
      password: 'password',
      apiPassword: null,
      basicAuth: Buffer.from('admin:password').toString('base64')
    };
  }
  
  try {
    const credentials = JSON.parse(fs.readFileSync(credentialsPath, 'utf8'));
    return credentials;
  } catch (error) {
    console.warn('⚠️  Failed to parse credentials file, using fallback:', error.message);
    return {
      username: 'admin',
      password: 'password',
      apiPassword: null,
      basicAuth: Buffer.from('admin:password').toString('base64')
    };
  }
}

/**
 * Get authorization header for API requests
 * @returns {string} Authorization header value
 */
function getAuthHeader() {
  const credentials = loadTestCredentials();
  return `Basic ${credentials.basicAuth}`;
}

module.exports = {
  loadTestCredentials,
  getAuthHeader
};