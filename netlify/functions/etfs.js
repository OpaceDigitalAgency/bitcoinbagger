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
    const fetch = require('node-fetch');
    
    // Fetch live Bitcoin price for calculations
    const btcResponse = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd');
    const btcData = await btcResponse.json();
    const btcPrice = btcData.bitcoin?.usd || 107000;

    // Fetch live ETF prices
    const etfTickers = ['IBIT', 'FBTC', 'ARKB', 'BITB', 'BTCO'];
    const etfPromises = etfTickers.map(async (ticker) => {
      try {
        // Using Alpha Vantage free API (you can replace with your preferred stock API)
        const response = await fetch(`https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=${ticker}&apikey=demo`);
        const data = await response.json();
        return {
          ticker,
          price: parseFloat(data['Global Quote']?.['05. price']) || null
        };
      } catch (error) {
        console.error(`Error fetching ${ticker}:`, error);
        return { ticker, price: null };
      }
    });

    const etfPriceData = await Promise.all(etfPromises);
    const etfPrices = etfPriceData.reduce((acc, etf) => {
      acc[etf.ticker] = etf.price;
      return acc;
    }, {});

    // ETF data with known holdings (these would ideally come from official ETF data sources)
    const etfs = [
      {
        ticker: "IBIT",
        name: "iShares Bitcoin Trust",
        btcHeld: 428000, // This would come from official ETF holdings data
        price: etfPrices.IBIT || 42.50,
        btcPerShare: 0.00025,
        sharesOutstanding: 1712000000
      },
      {
        ticker: "FBTC",
        name: "Fidelity Wise Origin Bitcoin Fund",
        btcHeld: 185000,
        price: etfPrices.FBTC || 52.30,
        btcPerShare: 0.0003,
        sharesOutstanding: 616666667
      },
      {
        ticker: "ARKB",
        name: "ARK 21Shares Bitcoin ETF",
        btcHeld: 45000,
        price: etfPrices.ARKB || 68.20,
        btcPerShare: 0.00039,
        sharesOutstanding: 115384615
      },
      {
        ticker: "BITB",
        name: "Bitwise Bitcoin ETF",
        btcHeld: 38000,
        price: etfPrices.BITB || 35.80,
        btcPerShare: 0.00021,
        sharesOutstanding: 180952381
      },
      {
        ticker: "BTCO",
        name: "Invesco Galaxy Bitcoin ETF",
        btcHeld: 32000,
        price: etfPrices.BTCO || 58.90,
        btcPerShare: 0.00034,
        sharesOutstanding: 94117647
      }
    ];

    // Calculate live premium/discount based on current prices
    const enrichedETFs = etfs.map(etf => {
      const nav = etf.btcPerShare * btcPrice; // Net Asset Value per share
      const premiumDiscount = ((etf.price - nav) / nav) * 100;

      return {
        ...etf,
        premiumDiscount: Math.round(premiumDiscount * 100) / 100
      };
    });

    return {
      statusCode: 200,
      headers: {
        ...headers,
        'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
      },
      body: JSON.stringify(enrichedETFs)
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