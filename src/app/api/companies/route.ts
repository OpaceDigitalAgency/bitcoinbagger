import { NextRequest, NextResponse } from 'next/server'
import * as cheerio from 'cheerio'

interface CompanyData {
  ticker: string
  name: string
  btcHeld: number
  btcValue: number
  revenue: number
  sharesOutstanding: number
  marketPrice: number
  bsp: number
  premium: number
  businessModel: string
  btcRole: string
  rank: number
}

interface BitcoinTreasuryData {
  ticker: string
  name: string
  btcHeld: number
  businessModel: string
  btcRole: string
}

export async function GET(request: NextRequest) {
  try {
    // Get Bitcoin price - REQUIRED, no fallback
    const bitcoinPrice = await getBitcoinPrice()
    if (!bitcoinPrice) {
      return NextResponse.json(
        { error: 'Unable to fetch Bitcoin price. Please try again later.' },
        { status: 503 }
      )
    }

    // Fetch live company data from multiple sources
    const [treasuryData, stockData] = await Promise.all([
      fetchBitcoinTreasuryData(),
      fetchStockMarketData()
    ])

    if (!treasuryData || treasuryData.length === 0) {
      return NextResponse.json(
        { error: 'Unable to fetch Bitcoin treasury data. Please try again later.' },
        { status: 503 }
      )
    }

    // Combine and enrich data
    const companies = await enrichCompanyData(treasuryData, stockData, bitcoinPrice)

    return NextResponse.json(companies, {
      headers: {
        'Cache-Control': 'public, s-maxage=300, stale-while-revalidate=600'
      }
    })
  } catch (error) {
    console.error('Error fetching companies data:', error)

    return NextResponse.json(
      { error: 'Unable to fetch live company data. Please try again later.' },
      { status: 503 }
    )
  }
}

async function getBitcoinPrice(): Promise<number | null> {
  try {
    const response = await fetch(
      'https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd',
      {
        next: { revalidate: 300 },
        headers: {
          'User-Agent': 'BitcoinBagger/1.0'
        }
      }
    )

    if (!response.ok) {
      throw new Error(`CoinGecko API error: ${response.status}`)
    }

    const data = await response.json()
    return data.bitcoin?.usd || null
  } catch (error) {
    console.error('Failed to fetch Bitcoin price:', error)
    return null
  }
}

async function fetchBitcoinTreasuryData(): Promise<BitcoinTreasuryData[] | null> {
  try {
    const response = await fetch('https://bitcointreasuries.net/', {
      headers: {
        'User-Agent': 'BitcoinBagger/1.0'
      },
      next: { revalidate: 3600 } // Cache for 1 hour
    })

    if (!response.ok) {
      throw new Error(`BitcoinTreasuries.net error: ${response.status}`)
    }

    const html = await response.text()
    return parseBitcoinTreasuryHTML(html)
  } catch (error) {
    console.error('Failed to fetch Bitcoin treasury data:', error)
    return null
  }
}

function parseBitcoinTreasuryHTML(html: string): BitcoinTreasuryData[] {
  const $ = cheerio.load(html) as any
  const companies: BitcoinTreasuryData[] = []

  // Parse the main table - adjust selectors based on actual HTML structure
  $('table tbody tr').each((index, element) => {
    const row = $(element)
    const cells = row.find('td')

    if (cells.length >= 4) {
      const name = cells.eq(0).text().trim()
      const ticker = extractTicker(name)
      const btcHeldText = cells.eq(1).text().trim()
      const btcHeld = parseBTCAmount(btcHeldText)

      if (ticker && btcHeld > 0) {
        companies.push({
          ticker,
          name: cleanCompanyName(name),
          btcHeld,
          businessModel: cells.eq(2)?.text().trim() || '',
          btcRole: cells.eq(3)?.text().trim() || ''
        })
      }
    }
  })

  return companies.slice(0, 30) // Top 30 as per spec
}

function extractTicker(nameText: string): string | null {
  // Extract ticker from formats like "MicroStrategy (MSTR)" or "MSTR"
  const tickerMatch = nameText.match(/\(([A-Z]{2,5})\)/) || nameText.match(/^([A-Z]{2,5})$/)
  return tickerMatch ? tickerMatch[1] : null
}

function cleanCompanyName(nameText: string): string {
  // Remove ticker from name
  return nameText.replace(/\s*\([A-Z]{2,5}\)/, '').trim()
}

function parseBTCAmount(text: string): number {
  // Parse amounts like "331,200" or "331200" or "331.2k"
  const cleanText = text.replace(/[,\s]/g, '')
  const match = cleanText.match(/([\d.]+)([kK])?/)

  if (match) {
    const number = parseFloat(match[1])
    const multiplier = match[2] ? 1000 : 1
    return number * multiplier
  }

  return 0
}

async function fetchStockMarketData(): Promise<Map<string, any>> {
  const stockData = new Map()

  // Get list of known Bitcoin treasury companies
  const knownTickers = ['MSTR', 'MARA', 'TSLA', 'RIOT', 'CLSK', 'COIN', 'HUT', 'GLXY', 'BITF', 'CORZ']

  // Fetch data for each ticker
  await Promise.all(
    knownTickers.map(async (ticker) => {
      try {
        const [stockInfo, fundamentals] = await Promise.all([
          fetchIEXStockData(ticker),
          fetchFMPFundamentals(ticker)
        ])

        if (stockInfo || fundamentals) {
          stockData.set(ticker, {
            ...stockInfo,
            ...fundamentals
          })
        }
      } catch (error) {
        console.warn(`Failed to fetch data for ${ticker}:`, error)
      }
    })
  )

  return stockData
}

async function fetchIEXStockData(ticker: string): Promise<any> {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'

    const [quoteResponse, statsResponse] = await Promise.all([
      fetch(`${baseUrl}/api/proxy/iex?symbol=${ticker}&endpoint=quote`),
      fetch(`${baseUrl}/api/proxy/iex?symbol=${ticker}&endpoint=stats`)
    ])

    const quote = quoteResponse.ok ? await quoteResponse.json() : null
    const stats = statsResponse.ok ? await statsResponse.json() : null

    return {
      marketPrice: quote?.latestPrice || 0,
      sharesOutstanding: stats?.sharesOutstanding || 0,
      marketCap: quote?.marketCap || 0
    }
  } catch (error) {
    console.warn(`IEX API error for ${ticker}:`, error)
    return null
  }
}

async function fetchFMPFundamentals(ticker: string): Promise<any> {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'

    const response = await fetch(
      `${baseUrl}/api/proxy/fmp?symbol=${ticker}&endpoint=income-statement&period=annual&limit=1`
    )

    if (!response.ok) {
      throw new Error(`FMP API error: ${response.status}`)
    }

    const data = await response.json()
    const latest = data[0]

    return {
      revenue: latest?.revenue ? latest.revenue / 1000000000 : 0, // Convert to billions
      netIncome: latest?.netIncome ? latest.netIncome / 1000000000 : 0
    }
  } catch (error) {
    console.warn(`FMP API error for ${ticker}:`, error)
    return null
  }
}

async function enrichCompanyData(
  treasuryData: BitcoinTreasuryData[],
  stockData: Map<string, any>,
  bitcoinPrice: number
): Promise<CompanyData[]> {
  return treasuryData.map((company, index) => {
    const btcValue = company.btcHeld * bitcoinPrice
    const stockInfo = stockData.get(company.ticker) || {}

    // Calculate BSP and premium when we have stock data
    const marketPrice = stockInfo.marketPrice || 0
    const sharesOutstanding = stockInfo.sharesOutstanding || 0
    const revenue = stockInfo.revenue || 0

    const bsp = sharesOutstanding > 0 ?
      (btcValue + revenue * 1000000000) / (sharesOutstanding * 1000000) : 0
    const premium = (bsp > 0 && marketPrice > 0) ?
      ((marketPrice / bsp) - 1) * 100 : 0

    return {
      ticker: company.ticker,
      name: company.name,
      btcHeld: company.btcHeld,
      btcValue,
      revenue,
      sharesOutstanding,
      marketPrice,
      bsp,
      premium,
      businessModel: company.businessModel,
      btcRole: company.btcRole,
      rank: index + 1
    }
  })
}