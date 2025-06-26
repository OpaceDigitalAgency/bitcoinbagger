export default async (request: Request) => {
  const url = new URL(request.url);
  const path = url.pathname;

  // Handle bitcoin price API
  if (path === '/api/bitcoin-price') {
    try {
      const response = await fetch(
        'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true',
        {
          headers: {
            'User-Agent': 'BitcoinBagger/1.0',
            'Accept': 'application/json'
          }
        }
      );

      if (!response.ok) {
        throw new Error('Failed to fetch Bitcoin price');
      }

      const data = await response.json();
      
      return new Response(JSON.stringify(data), {
        headers: {
          'Content-Type': 'application/json',
          'Cache-Control': 'public, max-age=300'
        }
      });
    } catch (error) {
      return new Response(JSON.stringify({ error: 'Failed to fetch Bitcoin price' }), {
        status: 500,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  }

  // Handle companies API
  if (path === '/api/companies') {
    try {
      const companiesData = [
        {
          ticker: 'MSTR',
          name: 'MicroStrategy Inc.',
          btcHeld: 444262,
          btcValue: 47000000000,
          businessModel: 'Public Company',
          btcRole: 'Treasury Asset',
          rank: 1,
          marketPrice: 450.25,
          premium: 15.2,
          bsp: 0.00106,
          sharesOutstanding: 11.9
        },
        {
          ticker: 'TSLA',
          name: 'Tesla Inc.',
          btcHeld: 9720,
          btcValue: 1030000000,
          businessModel: 'Public Company',
          btcRole: 'Treasury Asset',
          rank: 2,
          marketPrice: 248.50,
          premium: -5.8,
          bsp: 0.000039,
          sharesOutstanding: 3180
        },
        {
          ticker: 'COIN',
          name: 'Coinbase Global Inc.',
          btcHeld: 9181,
          btcValue: 973000000,
          businessModel: 'Public Company',
          btcRole: 'Treasury Asset',
          rank: 3,
          marketPrice: 285.75,
          premium: 8.4,
          bsp: 0.000037,
          sharesOutstanding: 247
        },
        {
          ticker: 'CLSK',
          name: 'CleanSpark Inc.',
          btcHeld: 8701,
          btcValue: 922000000,
          businessModel: 'Public Company',
          btcRole: 'Treasury Asset',
          rank: 4,
          marketPrice: 12.85,
          premium: -12.3,
          bsp: 0.000035,
          sharesOutstanding: 248
        },
        {
          ticker: 'MARA',
          name: 'Marathon Digital Holdings',
          btcHeld: 34794,
          btcValue: 3690000000,
          businessModel: 'Public Company',
          btcRole: 'Treasury Asset',
          rank: 5,
          marketPrice: 22.40,
          premium: 3.7,
          bsp: 0.000140,
          sharesOutstanding: 248
        }
      ];

      return new Response(JSON.stringify(companiesData), {
        headers: {
          'Content-Type': 'application/json',
          'Cache-Control': 'public, max-age=300'
        }
      });
    } catch (error) {
      return new Response(JSON.stringify({ error: 'Failed to fetch companies data' }), {
        status: 500,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  }

  // Return 404 for other API paths
  return new Response('Not Found', { status: 404 });
};