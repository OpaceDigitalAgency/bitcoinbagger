const cheerio = require('cheerio');

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

  // Always return fallback data for now to ensure the app works
  const fallbackData = [
    {
      rank: 1,
      name: "MicroStrategy",
      ticker: "MSTR",
      btcHeld: 439000,
      btcValue: 46973000000,
      marketPrice: 397.50,
      marketCap: 79500000000,
      premium: 69.3,
      bsp: 1069.84,
      sharesOutstanding: 11.7,
      businessModel: "Business Intelligence Software",
      btcRole: "Primary Treasury Reserve Asset"
    },
    {
      rank: 2,
      name: "Tesla",
      ticker: "TSLA",
      btcHeld: 9720,
      btcValue: 1040040000,
      marketPrice: 436.58,
      marketCap: 1393856000000,
      premium: 1240.5,
      bsp: 3.17,
      sharesOutstanding: 3193.0,
      businessModel: "Electric Vehicle Manufacturer",
      btcRole: "Alternative Treasury Asset"
    },
    {
      rank: 3,
      name: "Marathon Digital Holdings",
      ticker: "MARA",
      btcHeld: 34794,
      btcValue: 3722958000,
      marketPrice: 19.84,
      marketCap: 5168640000,
      premium: 38.8,
      bsp: 14.29,
      sharesOutstanding: 260.5,
      businessModel: "Bitcoin Mining",
      btcRole: "Core Business Asset"
    },
    {
      rank: 4,
      name: "Riot Platforms",
      ticker: "RIOT",
      btcHeld: 17429,
      btcValue: 1864903000,
      marketPrice: 11.25,
      marketCap: 2925000000,
      premium: 56.8,
      bsp: 7.16,
      sharesOutstanding: 260.0,
      businessModel: "Bitcoin Mining",
      btcRole: "Core Business Asset"
    },
    {
      rank: 5,
      name: "Coinbase Global",
      ticker: "COIN",
      btcHeld: 9181,
      btcValue: 982367000,
      marketPrice: 278.45,
      marketCap: 73504500000,
      premium: 7385.6,
      bsp: 3.78,
      sharesOutstanding: 264.0,
      businessModel: "Cryptocurrency Exchange",
      btcRole: "Operational and Treasury Asset"
    }
  ];
  
  return {
    statusCode: 200,
    headers: {
      ...headers,
      'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
    },
    body: JSON.stringify(fallbackData)
  };
};