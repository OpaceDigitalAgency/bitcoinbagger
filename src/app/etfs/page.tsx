import { Metadata } from 'next'
import { ETFsPageClient } from '@/components/ETFsPageClient'
import { formatCurrency, formatNumber } from '@/lib/utils'
import { Navigation } from '@/components/Navigation'

export const dynamic = 'force-dynamic'

export const metadata: Metadata = {
  title: 'Bitcoin Spot ETFs | BitcoinBagger',
  description: 'Track Bitcoin spot ETFs including IBIT, FBTC, GBTC, and BITB. Live holdings, premiums, and NAV data for Bitcoin ETF investments.',
  keywords: 'bitcoin ETF, IBIT, FBTC, GBTC, BITB, spot bitcoin ETF, premium discount, NAV',
  openGraph: {
    title: 'Bitcoin Spot ETFs - Live Holdings & Premium Tracking',
    description: 'Real-time tracking of Bitcoin spot ETFs. Monitor holdings, premiums, and trading data.',
    type: 'website',
    url: 'https://bitcoinbagger.com/etfs'
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Bitcoin Spot ETFs | BitcoinBagger',
    description: 'Track Bitcoin spot ETFs with live holdings and premium data.',
  }
}

async function getETFsData() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/.netlify/functions/etfs`, {
      next: { revalidate: 300 }
    })
    
    if (!response.ok) {
      throw new Error('Failed to fetch ETFs data')
    }
    
    return await response.json()
  } catch (error) {
    console.error('Error fetching ETFs:', error)
    return []
  }
}

async function getBitcoinPrice() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/.netlify/functions/bitcoin-price`, {
      next: { revalidate: 300 }
    })

    if (!response.ok) {
      throw new Error('Failed to fetch Bitcoin price')
    }

    const data = await response.json()
    return {
      bitcoin: {
        usd: data.bitcoin?.usd || data.usd,
        usd_24h_change: data.bitcoin?.usd_24h_change || data.usd_24h_change || 0
      }
    }
  } catch (error) {
    console.error('Error fetching Bitcoin price:', error)
    return null
  }
}

export default async function ETFsPage() {
  const [etfs, bitcoinData] = await Promise.all([
    getETFsData(),
    getBitcoinPrice()
  ])

  // Handle API errors gracefully
  if (!bitcoinData || !etfs || etfs.length === 0) {
    return (
      <div className="min-h-screen bg-background">
        <Navigation />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
          <h1 className="text-4xl font-bold text-foreground mb-4">Service Temporarily Unavailable</h1>
          <p className="text-xl text-muted-foreground mb-8">
            {!bitcoinData ? 'Unable to fetch live Bitcoin price data.' : 'Unable to fetch ETF data.'} Please try again later.
          </p>
          <a
            href="/etfs"
            className="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium inline-block"
          >
            Retry
          </a>
        </div>
      </div>
    )
  }

  const totalBtcHeld = etfs.reduce((sum: number, etf: any) => sum + (etf.btcHeld || 0), 0)
  const totalValue = bitcoinData?.bitcoin?.usd ? totalBtcHeld * bitcoinData.bitcoin.usd : 0
  const averagePremium = etfs.length > 0 ?
    etfs.reduce((sum: number, etf: any) => sum + (etf.premiumDiscount || 0), 0) / etfs.length : 0

  return (
    <div className="min-h-screen bg-background">
      <Navigation />
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header className="mb-8">
          <h1 className="text-4xl font-bold text-foreground mb-4">
            Bitcoin Spot ETFs
          </h1>
          <p className="text-xl text-muted-foreground mb-6">
            Track exchange-traded funds that hold Bitcoin directly. Monitor live holdings, 
            premiums/discounts to NAV, and trading metrics for Bitcoin ETF investments.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Total ETF Holdings</h3>
              <p className="text-2xl font-bold">{formatNumber(totalBtcHeld)} BTC</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Combined AUM</h3>
              <p className="text-2xl font-bold">{formatCurrency(totalValue)}</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Average Premium</h3>
              <p className={`text-2xl font-bold ${averagePremium >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                {averagePremium >= 0 ? '+' : ''}{averagePremium.toFixed(1)}%
              </p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Bitcoin Price</h3>
              <p className="text-2xl font-bold">
                {bitcoinData?.bitcoin?.usd ? formatCurrency(bitcoinData.bitcoin.usd) : 'Loading...'}
              </p>
            </div>
          </div>

          <div className="prose prose-lg max-w-none mb-8">
            <h2>Understanding Bitcoin Spot ETFs</h2>
            <p>
              Bitcoin spot ETFs are exchange-traded funds that hold actual Bitcoin, providing 
              investors with direct exposure to Bitcoin's price movements through traditional 
              brokerage accounts. Unlike Bitcoin futures ETFs, these funds hold the underlying 
              asset directly.
            </p>
            
            <h3>Key Advantages:</h3>
            <ul>
              <li><strong>Direct Bitcoin Exposure:</strong> ETFs hold actual Bitcoin, not derivatives</li>
              <li><strong>No Wallet Management:</strong> ETF custodians handle Bitcoin storage and security</li>
              <li><strong>Traditional Brokerage:</strong> Buy and sell through regular investment accounts</li>
              <li><strong>Tax Efficiency:</strong> May offer more favorable tax treatment than direct Bitcoin ownership</li>
            </ul>

            <h3>Premium/Discount Analysis:</h3>
            <p>
              Bitcoin ETFs can trade at premiums or discounts to their Net Asset Value (NAV). 
              A premium means the ETF price is higher than the underlying Bitcoin value per share, 
              while a discount means it's trading below NAV.
            </p>
          </div>
        </header>

        <ETFsPageClient initialData={{ etfs, bitcoinData }} />
        
        <section className="mt-16">
          <h2 className="text-3xl font-bold mb-6">Bitcoin ETF Comparison</h2>
          <div className="overflow-x-auto">
            <table className="w-full bg-card rounded-lg border">
              <thead className="bg-muted/50">
                <tr>
                  <th className="px-6 py-4 text-left font-medium">ETF</th>
                  <th className="px-6 py-4 text-right font-medium">BTC Holdings</th>
                  <th className="px-6 py-4 text-right font-medium">Price</th>
                  <th className="px-6 py-4 text-right font-medium">Premium/Discount</th>
                  <th className="px-6 py-4 text-right font-medium">BTC per Share</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {etfs.map((etf: any, index: number) => (
                  <tr key={etf.ticker} className="hover:bg-muted/30">
                    <td className="px-6 py-4">
                      <div>
                        <div className="font-medium">{etf.name}</div>
                        <div className="text-sm text-muted-foreground">{etf.ticker}</div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-right font-medium">
                      {formatNumber(etf.btcHeld)} BTC
                    </td>
                    <td className="px-6 py-4 text-right font-medium">
                      {formatCurrency(etf.price || 0)}
                    </td>
                    <td className={`px-6 py-4 text-right font-medium ${(etf.premiumDiscount || 0) >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {etf.premiumDiscount >= 0 ? '+' : ''}{(etf.premiumDiscount || 0).toFixed(1)}%
                    </td>
                    <td className="px-6 py-4 text-right font-medium">
                      {etf.btcPerShare?.toFixed(6) || 'N/A'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  )
}