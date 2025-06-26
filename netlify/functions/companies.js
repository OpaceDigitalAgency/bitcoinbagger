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
    // Fetch the Bitcoin treasury page
    const response = await fetch('https://bitcointreasuries.net/', {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
      }
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch data: ${response.status}`);
    }

    const html = await response.text();
    const $ = cheerio.load(html);
    
    const companies = [];
    
    // Parse the table data
    $('table tbody tr').each((index, element) => {
      const $row = $(element);
      const cells = $row.find('td');
      
      if (cells.length >= 4) {
        const company = cells.eq(0).text().trim();
        const bitcoinHeld = cells.eq(1).text().trim();
        const marketValue = cells.eq(2).text().trim();
        const percentOfSupply = cells.eq(3).text().trim();
        
        if (company && bitcoinHeld && marketValue) {
          companies.push({
            company,
            bitcoinHeld,
            marketValue,
            percentOfSupply,
            ticker: company.toLowerCase().replace(/[^a-z0-9]/g, '')
          });
        }
      }
    });

    // If no companies found, return fallback data
    if (companies.length === 0) {
      const fallbackData = [
        {
          company: "MicroStrategy",
          bitcoinHeld: "190,000 BTC",
          marketValue: "$20.4B",
          percentOfSupply: "0.90%",
          ticker: "mstr"
        },
        {
          company: "Tesla",
          bitcoinHeld: "9,720 BTC",
          marketValue: "$1.04B",
          percentOfSupply: "0.05%",
          ticker: "tsla"
        },
        {
          company: "Marathon Digital",
          bitcoinHeld: "15,174 BTC",
          marketValue: "$1.63B",
          percentOfSupply: "0.07%",
          ticker: "mara"
        }
      ];
      
      return {
        statusCode: 200,
        headers: {
          ...headers,
          'Cache-Control': 'public, s-maxage=3600, stale-while-revalidate=7200'
        },
        body: JSON.stringify(fallbackData)
      };
    }

    return {
      statusCode: 200,
      headers: {
        ...headers,
        'Cache-Control': 'public, s-maxage=3600, stale-while-revalidate=7200'
      },
      body: JSON.stringify(companies)
    };

  } catch (error) {
    console.error('Error fetching companies data:', error);
    
    // Return fallback data on error
    const fallbackData = [
      {
        company: "MicroStrategy",
        bitcoinHeld: "190,000 BTC",
        marketValue: "$20.4B",
        percentOfSupply: "0.90%",
        ticker: "mstr"
      },
      {
        company: "Tesla",
        bitcoinHeld: "9,720 BTC",
        marketValue: "$1.04B",
        percentOfSupply: "0.05%",
        ticker: "tsla"
      },
      {
        company: "Marathon Digital",
        bitcoinHeld: "15,174 BTC",
        marketValue: "$1.63B",
        percentOfSupply: "0.07%",
        ticker: "mara"
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