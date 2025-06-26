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
    const btcPrice = btcData.bitcoin?.usd || 107000;

    // Fetch live stock prices for major Bitcoin companies
    const tickers = ['MSTR', 'TSLA', 'MARA', 'RIOT', 'COIN'];
    const stockPromises = tickers.map(async (ticker) => {
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

    const stockData = await Promise.all(stockPromises);
    const stockPrices = stockData.reduce((acc, stock) => {
      acc[stock.ticker] = stock.price;
      return acc;
    }, {});

    // Company data with live Bitcoin holdings (these would ideally come from a real data source)
    // For now, using known holdings with live price calculations
    const companies = [
      {
        rank: 1,
        name: "MicroStrategy",
        ticker: "MSTR",
        btcHeld: 439000, // This would come from their latest SEC filings
        marketPrice: stockPrices.MSTR || 397.50,
        sharesOutstanding: 11.7,
        businessModel: "Business Intelligence Software",
        btcRole: "Primary Treasury Reserve Asset"
      },
      {
        rank: 2,
        name: "Tesla",
        ticker: "TSLA",
        btcHeld: 9720,
        marketPrice: stockPrices.TSLA || 436.58,
        sharesOutstanding: 3193.0,
        businessModel: "Electric Vehicle Manufacturer",
        btcRole: "Alternative Treasury Asset"
      },
      {
        rank: 3,
        name: "Marathon Digital Holdings",
        ticker: "MARA",
        btcHeld: 34794,
        marketPrice: stockPrices.MARA || 19.84,
        sharesOutstanding: 260.5,
        businessModel: "Bitcoin Mining",
        btcRole: "Core Business Asset"
      },
      {
        rank: 4,
        name: "Riot Platforms",
        ticker: "RIOT",
        btcHeld: 17429,
        marketPrice: stockPrices.RIOT || 11.25,
        sharesOutstanding: 260.0,
        businessModel: "Bitcoin Mining",
        btcRole: "Core Business Asset"
      },
      {
        rank: 5,
        name: "Coinbase Global",
        ticker: "COIN",
        btcHeld: 9181,
        marketPrice: stockPrices.COIN || 278.45,
        sharesOutstanding: 264.0,
        businessModel: "Cryptocurrency Exchange",
        btcRole: "Operational and Treasury Asset"
      }
    ];

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
    
    // Only use fallback if live data completely fails
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
      }
    ];
    
    return {
      statusCode: 200,
      headers: {
        ...headers,
        'Cache-Control': 'public, s-maxage=60, stale-while-revalidate=300'
      },
      body: JSON.stringify(fallbackData)
    };
  }
};