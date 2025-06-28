const fetch = require('node-fetch');

exports.handler = async (event, context) => {
  // Set CORS headers
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Content-Type': 'application/json'
  };

  // Handle preflight requests
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers,
      body: ''
    };
  }

  if (event.httpMethod !== 'GET') {
    return {
      statusCode: 405,
      headers,
      body: JSON.stringify({ error: 'Method not allowed' })
    };
  }

  try {
    console.log('Attempting to fetch Bitcoin price from CoinGecko...');
    
    // Try multiple endpoints for maximum reliability
    let response;
    let data;
    
    // Primary: CoinGecko simple price API
    try {
      response = await fetch(
        'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true&include_24hr_vol=true',
        {
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept': 'application/json'
          },
          timeout: 10000
        }
      );

      if (response.ok) {
        data = await response.json();
        console.log('CoinGecko success:', data);
        
        return {
          statusCode: 200,
          headers: {
            ...headers,
            'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
          },
          body: JSON.stringify(data)
        };
      }
      console.log('CoinGecko failed:', response.status);
    } catch (error) {
      console.log('CoinGecko error:', error.message);
    }

    // Fallback: CoinCap API
    try {
      response = await fetch('https://api.coincap.io/v2/assets/bitcoin', {
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
          'Accept': 'application/json'
        },
        timeout: 10000
      });

      if (response.ok) {
        const coincapData = await response.json();
        const price = parseFloat(coincapData.data.priceUsd);
        const change24h = parseFloat(coincapData.data.changePercent24Hr);
        const marketCap = parseFloat(coincapData.data.marketCapUsd);
        const volume = parseFloat(coincapData.data.volumeUsd24Hr);
        
        // Convert to CoinGecko format
        data = {
          bitcoin: {
            usd: price,
            usd_market_cap: marketCap,
            usd_24h_vol: volume,
            usd_24h_change: change24h
          }
        };
        
        console.log('CoinCap success:', data);
        
        return {
          statusCode: 200,
          headers: {
            ...headers,
            'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
          },
          body: JSON.stringify(data)
        };
      }
      console.log('CoinCap failed:', response.status);
    } catch (error) {
      console.log('CoinCap error:', error.message);
    }

    throw new Error('All Bitcoin price APIs failed');
  } catch (error) {
    console.error('Error fetching Bitcoin price:', error);
    
    // Return error - NO FALLBACK DATA
    return {
      statusCode: 503,
      headers,
      body: JSON.stringify({
        error: 'Unable to fetch live Bitcoin price data',
        message: 'API temporarily unavailable'
      })
    };
  }
};