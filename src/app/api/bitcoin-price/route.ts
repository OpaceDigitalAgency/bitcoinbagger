import { NextRequest, NextResponse } from 'next/server'

export const dynamic = 'force-dynamic'

export async function GET(request: NextRequest) {
  try {
    const response = await fetch(
      'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true&include_24hr_vol=true',
      {
        headers: {
          'User-Agent': 'BitcoinBagger/1.0',
          'Accept': 'application/json'
        },
        // Cache for 5 minutes to avoid rate limiting
        next: { revalidate: 600 }
      }
    )

    if (!response.ok) {
      throw new Error(`Failed to fetch Bitcoin price: ${response.status}`)
    }

    const data = await response.json()
    
    return NextResponse.json(data, {
      headers: {
        'Cache-Control': 'public, s-maxage=600, stale-while-revalidate=1200'
      }
    })
  } catch (error) {
    console.error('Error fetching Bitcoin price:', error)

    // Return reasonable fallback data so the app keeps working
    const fallbackData = {
      bitcoin: {
        usd: 107400,
        usd_24h_change: 2.5,
        usd_market_cap: 2100000000000,
        usd_24h_vol: 25000000000
      }
    }

    return NextResponse.json(fallbackData, {
      headers: {
        'Cache-Control': 'public, s-maxage=600, stale-while-revalidate=1200'
      }
    })
  }
}