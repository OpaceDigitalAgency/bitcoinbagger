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
    const btcPrice = btcData.bitcoin?.usd;
    
    if (!btcPrice) {
      throw new Error('Failed to get Bitcoin price from CoinGecko');
    }

    // Fetch live ETF prices using multiple reliable free APIs
    const etfTickers = ['IBIT', 'FBTC', 'ARKB', 'BITB', 'BTCO'];
    const etfPromises = etfTickers.map(async (ticker) => {
      try {
        // Try Yahoo Finance first (most reliable)
        let response = await fetch(`https://query1.finance.yahoo.com/v8/finance/chart/${ticker}?interval=1d&range=1d&includePrePost=false`, {
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          const result = data.chart?.result?.[0];
          const price = result?.meta?.regularMarketPrice || result?.meta?.previousClose;
          if (price) {
            console.log(`${ticker}: $${price} (Yahoo)`);
            return { ticker, price: parseFloat(price) };
          }
        }
        
        // Fallback to Finnhub (reliable free API)
        response = await fetch(`https://finnhub.io/api/v1/quote?symbol=${ticker}&token=demo`, {
          headers: {
            'User-Agent': 'BitcoinBagger/1.0'
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          const price = data.c; // current price
          if (price && price > 0) {
            console.log(`${ticker}: $${price} (Finnhub)`);
            return { ticker, price: parseFloat(price) };
          }
        }
        
        console.log(`${ticker}: FAILED both APIs`);
        return { ticker, price: null };
        
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

    // ETF data with known holdings
    const etfData = [
      {
        ticker: "IBIT",
        name: "iShares Bitcoin Trust",
        btcHeld: 428000,
        btcPerShare: 0.00025,
        sharesOutstanding: 1712000000
      },
      {
        ticker: "FBTC",
        name: "Fidelity Wise Origin Bitcoin Fund",
        btcHeld: 185000,
        btcPerShare: 0.0003,
        sharesOutstanding: 616666667
      },
      {
        ticker: "ARKB",
        name: "ARK 21Shares Bitcoin ETF",
        btcHeld: 45000,
        btcPerShare: 0.00039,
        sharesOutstanding: 115384615
      },
      {
        ticker: "BITB",
        name: "Bitwise Bitcoin ETF",
        btcHeld: 38000,
        btcPerShare: 0.00021,
        sharesOutstanding: 180952381
      },
      {
        ticker: "BTCO",
        name: "Invesco Galaxy Bitcoin ETF",
        btcHeld: 32000,
        btcPerShare: 0.00034,
        sharesOutstanding: 94117647
      }
    ];

    // Only include ETFs where we successfully fetched live prices
    const etfs = etfData
      .filter(etf => etfPrices[etf.ticker] !== null && etfPrices[etf.ticker] !== undefined)
      .map(etf => ({
        ...etf,
        price: etfPrices[etf.ticker]
      }));

    console.log(`Successfully fetched prices for ${etfs.length} ETFs:`,
      etfs.map(e => `${e.ticker}: $${e.price}`));

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
    
    // Return error - NO FALLBACK DATA
    return {
      statusCode: 503,
      headers,
      body: JSON.stringify({
        error: 'Unable to fetch live ETF data',
        message: 'API temporarily unavailable'
      })
    };
  }
};