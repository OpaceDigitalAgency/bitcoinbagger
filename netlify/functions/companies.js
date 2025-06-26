const fetch = require('node-fetch');
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

  try {
    // Fetch live Bitcoin price for calculations
    const btcResponse = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd');
    const btcData = await btcResponse.json();
    const btcPrice = btcData.bitcoin?.usd;
    
    if (!btcPrice) {
      throw new Error('Failed to get Bitcoin price from CoinGecko');
    }

    // Fetch live stock prices using Yahoo Finance API (more reliable than Alpha Vantage demo)
    const tickers = ['MSTR', 'TSLA', 'MARA', 'RIOT', 'COIN'];
    const stockPromises = tickers.map(async (ticker) => {
      try {
        // Using Yahoo Finance API via query1.finance.yahoo.com
        const response = await fetch(`https://query1.finance.yahoo.com/v8/finance/chart/${ticker}?interval=1d&range=1d`);
        const data = await response.json();
        const result = data.chart?.result?.[0];
        const price = result?.meta?.regularMarketPrice || result?.meta?.previousClose;
        return {
          ticker,
          price: price ? parseFloat(price) : null
        };
      } catch (error) {
        console.error(`Error fetching ${ticker}:`, error);
        return { ticker, price: null };
      }
    });

    const stockData = await Promise.all(stockPromises);
    const stockPrices = stockData.reduce((acc, stock) => {
      acc[stock.ticker] = stock.price;
      return acc;
    }, {});

    // Company data with known Bitcoin holdings
    const companyData = [
      {
        rank: 1,
        name: "MicroStrategy",
        ticker: "MSTR",
        btcHeld: 439000,
        sharesOutstanding: 11.7,
        businessModel: "Business Intelligence Software",
        btcRole: "Primary Treasury Reserve Asset"
      },
      {
        rank: 2,
        name: "Tesla",
        ticker: "TSLA",
        btcHeld: 9720,
        sharesOutstanding: 3193.0,
        businessModel: "Electric Vehicle Manufacturer",
        btcRole: "Alternative Treasury Asset"
      },
      {
        rank: 3,
        name: "Marathon Digital Holdings",
        ticker: "MARA",
        btcHeld: 34794,
        sharesOutstanding: 260.5,
        businessModel: "Bitcoin Mining",
        btcRole: "Core Business Asset"
      },
      {
        rank: 4,
        name: "Riot Platforms",
        ticker: "RIOT",
        btcHeld: 17429,
        sharesOutstanding: 260.0,
        businessModel: "Bitcoin Mining",
        btcRole: "Core Business Asset"
      },
      {
        rank: 5,
        name: "Coinbase Global",
        ticker: "COIN",
        btcHeld: 9181,
        sharesOutstanding: 264.0,
        businessModel: "Cryptocurrency Exchange",
        btcRole: "Operational and Treasury Asset"
      }
    ];

    // Only include companies where we successfully fetched live stock prices
    const companies = companyData
      .filter(company => stockPrices[company.ticker] !== null && stockPrices[company.ticker] !== undefined)
      .map(company => ({
        ...company,
        marketPrice: stockPrices[company.ticker]
      }));

    console.log(`Successfully fetched prices for ${companies.length} companies:`,
      companies.map(c => `${c.ticker}: $${c.marketPrice}`));

    // Calculate live metrics
    const enrichedCompanies = companies.map(company => {
      const btcValue = company.btcHeld * btcPrice;
      const marketCap = company.marketPrice * company.sharesOutstanding * 1000000;
      const bsp = (btcValue / 1000000) / company.sharesOutstanding;
      const premium = ((marketCap - btcValue) / btcValue) * 100;

      return {
        ...company,
        btcValue: Math.round(btcValue),
        marketCap: Math.round(marketCap),
        premium: Math.round(premium * 10) / 10,
        bsp: Math.round(bsp * 100) / 100
      };
    });

    return {
      statusCode: 200,
      headers: {
        ...headers,
        'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
      },
      body: JSON.stringify(enrichedCompanies)
    };

  } catch (error) {
    console.error('Error fetching live companies data:', error);
    
    // Return error - NO FALLBACK DATA
    return {
      statusCode: 503,
      headers,
      body: JSON.stringify({
        error: 'Unable to fetch live companies data',
        message: 'API temporarily unavailable'
      })
    };
  }
};