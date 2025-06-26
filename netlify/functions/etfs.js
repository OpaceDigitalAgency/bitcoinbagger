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
    // Return fallback ETF data for now
    // In a real implementation, this would fetch from a Bitcoin ETF data source
    const etfData = [
      {
        ticker: "IBIT",
        name: "iShares Bitcoin Trust",
        btcHeld: 428000,
        price: 42.50,
        btcPerShare: 0.00025,
        sharesOutstanding: 1712000000,
        premiumDiscount: 0.15
      },
      {
        ticker: "FBTC",
        name: "Fidelity Wise Origin Bitcoin Fund",
        btcHeld: 185000,
        price: 52.30,
        btcPerShare: 0.0003,
        sharesOutstanding: 616666667,
        premiumDiscount: -0.08
      },
      {
        ticker: "ARKB",
        name: "ARK 21Shares Bitcoin ETF",
        btcHeld: 45000,
        price: 68.20,
        btcPerShare: 0.00039,
        sharesOutstanding: 115384615,
        premiumDiscount: 0.22
      },
      {
        ticker: "BITB",
        name: "Bitwise Bitcoin ETF",
        btcHeld: 38000,
        price: 35.80,
        btcPerShare: 0.00021,
        sharesOutstanding: 180952381,
        premiumDiscount: -0.12
      },
      {
        ticker: "BTCO",
        name: "Invesco Galaxy Bitcoin ETF",
        btcHeld: 32000,
        price: 58.90,
        btcPerShare: 0.00034,
        sharesOutstanding: 94117647,
        premiumDiscount: 0.05
      }
    ];

    return {
      statusCode: 200,
      headers: {
        ...headers,
        'Cache-Control': 'public, s-maxage=3600, stale-while-revalidate=7200'
      },
      body: JSON.stringify(etfData)
    };

  } catch (error) {
    console.error('Error fetching ETFs data:', error);
    
    // Return fallback data on error
    const fallbackData = [
      {
        ticker: "IBIT",
        name: "iShares Bitcoin Trust",
        btcHeld: 428000,
        price: 42.50,
        btcPerShare: 0.00025,
        sharesOutstanding: 1712000000,
        premiumDiscount: 0.15
      },
      {
        ticker: "FBTC",
        name: "Fidelity Wise Origin Bitcoin Fund",
        btcHeld: 185000,
        price: 52.30,
        btcPerShare: 0.0003,
        sharesOutstanding: 616666667,
        premiumDiscount: -0.08
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
  }
};