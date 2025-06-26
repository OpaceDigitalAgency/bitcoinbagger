const cheerio = require('cheerio');

exports.handler = async (event, context) => {
  // Set CORS headers
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE',
    'Content-Type': 'application/json',
    'Cache-Control': 'public, s-maxage=600, stale-while-revalidate=1200'
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
    // Fetch Bitcoin price first
    const bitcoinResponse = await fetch(
      'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd',
      {
        headers: {
          'User-Agent': 'BitcoinBagger/1.0',
          'Accept': 'application/json'
        }
      }
    );

    if (!bitcoinResponse.ok) {
      throw new Error(`Failed to fetch Bitcoin price: ${bitcoinResponse.status}`);
    }

    const bitcoinData = await bitcoinResponse.json();
    const bitcoinPrice = bitcoinData.bitcoin.usd;

    // Fetch ETF data from Bitbo.io
    const etfResponse = await fetch('https://bitbo.io/', {
      headers: {
        'User-Agent': 'BitcoinBagger/1.0',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
      }
    });

    if (!etfResponse.ok) {
      throw new Error(`Failed to fetch ETF data: ${etfResponse.status}`);
    }

    const html = await etfResponse.text();
    const $ = cheerio.load(html);
    
    const etfs = [];
    
    // Parse the ETF table data
    $('table tbody tr').each((index, element) => {
      const cells = $(element).find('td');
      if (cells.length >= 5) {
        const ticker = $(cells[0]).text().trim();
        const name = $(cells[1]).text().trim();
        const btcHeldText = $(cells[2]).text().trim();
        const btcHeld = parseFloat(btcHeldText.replace(/,/g, '')) || 0;
        const sharesText = $(cells[3]).text().trim();
        const sharesOutstanding = parseFloat(sharesText.replace(/,/g, '')) || 0;
        
        if (ticker && btcHeld > 0 && sharesOutstanding > 0) {
          const btcPerShare = btcHeld / sharesOutstanding;
          
          etfs.push({
            ticker: ticker.toUpperCase(),
            name,
            btcHeld,
            sharesOutstanding,
            btcPerShare,
            price: 0, // Would need additional API call to get current price
            premiumDiscount: 0 // Would be calculated with current price
          });
        }
      }
    });

    return {
      statusCode: 200,
      headers,
      body: JSON.stringify({
        etfs: etfs.slice(0, 15), // Return top 15
        bitcoinPrice,
        lastUpdated: new Date().toISOString()
      })
    };
  } catch (error) {
    console.error('Error fetching ETFs data:', error);
    
    return {
      statusCode: 503,
      headers,
      body: JSON.stringify({ 
        error: 'Unable to fetch ETFs data. Please try again later.' 
      })
    };
  }
};
