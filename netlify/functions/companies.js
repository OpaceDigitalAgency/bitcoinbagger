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

    // Fetch company data from BitcoinTreasuries.net
    const treasuryResponse = await fetch('https://bitcointreasuries.net/', {
      headers: {
        'User-Agent': 'BitcoinBagger/1.0',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
      }
    });

    if (!treasuryResponse.ok) {
      throw new Error(`Failed to fetch treasury data: ${treasuryResponse.status}`);
    }

    const html = await treasuryResponse.text();
    const $ = cheerio.load(html);
    
    const companies = [];
    
    // Parse the table data
    $('table tbody tr').each((index, element) => {
      const cells = $(element).find('td');
      if (cells.length >= 6) {
        const name = $(cells[0]).text().trim();
        const ticker = $(cells[1]).text().trim();
        const btcHeldText = $(cells[2]).text().trim();
        const btcHeld = parseFloat(btcHeldText.replace(/,/g, '')) || 0;
        
        if (ticker && btcHeld > 0) {
          companies.push({
            ticker: ticker.toUpperCase(),
            name,
            btcHeld,
            btcValue: btcHeld * bitcoinPrice,
            businessModel: 'Public Company',
            btcRole: 'Treasury Asset',
            rank: companies.length + 1
          });
        }
      }
    });

    return {
      statusCode: 200,
      headers,
      body: JSON.stringify({
        companies: companies.slice(0, 20), // Return top 20
        bitcoinPrice,
        lastUpdated: new Date().toISOString()
      })
    };
  } catch (error) {
    console.error('Error fetching companies data:', error);
    
    return {
      statusCode: 503,
      headers,
      body: JSON.stringify({ 
        error: 'Unable to fetch companies data. Please try again later.' 
      })
    };
  }
};
