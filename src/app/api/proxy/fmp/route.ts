import { NextRequest, NextResponse } from 'next/server'

export const dynamic = 'force-dynamic'

export async function GET(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url)
    const symbol = searchParams.get('symbol')
    const endpoint = searchParams.get('endpoint')
    const period = searchParams.get('period') || 'annual'
    const limit = searchParams.get('limit') || '1'
    
    if (!symbol || !endpoint) {
      return NextResponse.json(
        { error: 'Missing required parameters: symbol and endpoint' },
        { status: 400 }
      )
    }
    
    const apiKey = process.env.FMP_API_KEY
    if (!apiKey) {
      return NextResponse.json(
        { error: 'FinancialModelingPrep API key not configured' },
        { status: 500 }
      )
    }
    
    const baseUrl = 'https://financialmodelingprep.com/api/v3'
    let fmpUrl = `${baseUrl}/${endpoint}/${symbol}?apikey=${apiKey}`
    
    // Add additional parameters based on endpoint
    if (endpoint.includes('income-statement') || endpoint.includes('balance-sheet')) {
      fmpUrl += `&period=${period}&limit=${limit}`
    }
    
    const response = await fetch(fmpUrl, {
      headers: {
        'User-Agent': 'BitcoinBagger/1.0'
      },
      next: { revalidate: 3600 } // Cache for 1 hour (fundamentals don't change often)
    })
    
    if (!response.ok) {
      throw new Error(`FMP API error: ${response.status}`)
    }
    
    const data = await response.json()
    
    return NextResponse.json(data, {
      headers: {
        'Cache-Control': 'public, s-maxage=3600, stale-while-revalidate=7200'
      }
    })
  } catch (error) {
    console.error('FMP proxy error:', error)
    
    return NextResponse.json(
      { error: 'Failed to fetch data from FinancialModelingPrep' },
      { status: 503 }
    )
  }
}
