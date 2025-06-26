import { NextRequest, NextResponse } from 'next/server'

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const symbol = searchParams.get('symbol')
    const endpoint = searchParams.get('endpoint')
    
    if (!symbol || !endpoint) {
      return NextResponse.json(
        { error: 'Missing required parameters: symbol and endpoint' },
        { status: 400 }
      )
    }
    
    const token = process.env.IEX_TOKEN
    if (!token) {
      return NextResponse.json(
        { error: 'IEX API token not configured' },
        { status: 500 }
      )
    }
    
    // Use sandbox for development, production for live
    const baseUrl = process.env.NODE_ENV === 'production' 
      ? 'https://cloud.iexapis.com/stable'
      : 'https://sandbox.iexapis.com/stable'
    
    const iexUrl = `${baseUrl}/stock/${symbol}/${endpoint}?token=${token}`
    
    const response = await fetch(iexUrl, {
      headers: {
        'User-Agent': 'BitcoinBagger/1.0'
      },
      next: { revalidate: 300 } // Cache for 5 minutes
    })
    
    if (!response.ok) {
      throw new Error(`IEX API error: ${response.status}`)
    }
    
    const data = await response.json()
    
    return NextResponse.json(data, {
      headers: {
        'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
      }
    })
  } catch (error) {
    console.error('IEX proxy error:', error)
    
    return NextResponse.json(
      { error: 'Failed to fetch data from IEX Cloud' },
      { status: 503 }
    )
  }
}
