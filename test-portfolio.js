const axios = require('axios');

const BASE_URL = 'http://localhost:8000/api';

// Test portfolio API endpoints
async function testPortfolioAPI() {
    console.log('Testing Portfolio API...\n');

    try {
        // Test 1: Get portfolio (should fail without auth)
        console.log('1. Testing GET /portfolio without auth...');
        try {
            const response = await axios.get(`${BASE_URL}/portfolio`);
            console.log('❌ Should have failed without auth');
        } catch (error) {
            if (error.response?.status === 401) {
                console.log('✅ Correctly rejected without authentication');
            } else {
                console.log('❌ Unexpected error:', error.response?.status);
            }
        }

        // Test 2: Test with invalid token
        console.log('\n2. Testing GET /portfolio with invalid token...');
        try {
            const response = await axios.get(`${BASE_URL}/portfolio`, {
                headers: { 'Authorization': 'Bearer invalid-token' }
            });
            console.log('❌ Should have failed with invalid token');
        } catch (error) {
            if (error.response?.status === 401) {
                console.log('✅ Correctly rejected invalid token');
            } else {
                console.log('❌ Unexpected error:', error.response?.status);
            }
        }

        // Test 3: Check if server is running
        console.log('\n3. Testing server connectivity...');
        try {
            const response = await axios.get(`${BASE_URL.replace('/api', '')}/test-auth`);
            console.log('✅ Server is running and responding');
        } catch (error) {
            console.log('❌ Server connectivity issue:', error.message);
        }

        console.log('\n✅ Portfolio API tests completed!');
        console.log('\nTo test with real authentication:');
        console.log('1. Login to the application');
        console.log('2. Get the token from localStorage');
        console.log('3. Use the token in API requests');

    } catch (error) {
        console.error('❌ Test failed:', error.message);
    }
}

testPortfolioAPI(); 