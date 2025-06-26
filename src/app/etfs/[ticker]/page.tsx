import { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { ETFDetailClient } from '@/components/ETFDetailClient'
import { formatCurrency, formatNumber, formatPercentage } from '@/lib/utils'
import { Navigation } from '@/components/Navigation'

interface Props {
  params: { ticker: string }
}

async function getETFData(ticker: string) {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/.netlify/functions/etfs`, {
      next: { revalidate: 300 }
    })
    
    if (!response.ok) {
      throw new Error('Failed to fetch ETFs data')
    }
    
    const etfs = await response.json()
    return etfs.find((e: any) => e.ticker.toLowerCase() === ticker.toLowerCase())
  } catch (error) {
    console.error('Error fetching ETF data:', error)
    return null
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const etf = await getETFData(params.ticker)
  
  if (!etf) {
    return {
      title: 'ETF Not Found | BitcoinBagger',
      description: 'The requested Bitcoin ETF could not be found.'
    }
  }

  return {
    title: `${etf.name} (${etf.ticker}) Bitcoin ETF Analysis | BitcoinBagger`,
    description: `${etf.name} holds ${formatNumber(etf.btcHeld)} BTC. Track live premium/discount, NAV, and trading metrics for ${etf.ticker}.`,
    keywords: `${etf.ticker}, ${etf.name}, bitcoin ETF, premium discount, NAV, bitcoin holdings`,
    openGraph: {
      title: `${etf.name} - Bitcoin ETF Analysis`,
      description: `Live tracking of ${etf.name}'s ${formatNumber(etf.btcHeld)} BTC holdings and trading metrics.`,
      type: 'website',
      url: `https://bitcoinbagger.com/etfs/${etf.ticker.toLowerCase()}`
    },
    twitter: {
      card: 'summary_large_image',
      title: `${etf.name} Bitcoin ETF`,
      description: `${formatNumber(etf.btcHeld)} BTC • ${formatCurrency(etf.price || 0)} price • ${formatPercentage(etf.premiumDiscount || 0)} premium`
    }
  }
}

export default async function ETFPage({ params }: Props) {
  const etf = await getETFData(params.ticker)
  
  if (!etf) {
    notFound()
  }

  const structuredData = {
    "@context": "https://schema.org",
    "@type": "InvestmentFund",
    "name": etf.name,
    "tickerSymbol": etf.ticker,
    "description": `${etf.name} is a Bitcoin spot ETF that holds ${formatNumber(etf.btcHeld)} Bitcoin directly.`,
    "url": `https://bitcoinbagger.com/etfs/${etf.ticker.toLowerCase()}`,
    "sameAs": [
      `https://finance.yahoo.com/quote/${etf.ticker}`,
      `https://www.google.com/finance/quote/${etf.ticker}`
    ]
  }

  return (
    <div className="min-h-screen bg-background">
      <Navigation />
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(structuredData) }}
      />
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header className="mb-8">
          <nav className="mb-4">
            <ol className="flex items-center space-x-2 text-sm text-muted-foreground">
              <li><a href="/" className="hover:text-foreground">Home</a></li>
              <li>•</li>
              <li><a href="/etfs" className="hover:text-foreground">ETFs</a></li>
              <li>•</li>
              <li className="text-foreground">{etf.ticker}</li>
            </ol>
          </nav>
          
          <div className="flex items-start justify-between mb-6">
            <div>
              <h1 className="text-4xl font-bold text-foreground mb-2">
                {etf.name}
              </h1>
              <p className="text-xl text-muted-foreground mb-4">
                Ticker: {etf.ticker} • Bitcoin Spot ETF
              </p>
              <p className="text-lg text-muted-foreground">
                Exchange-traded fund providing direct Bitcoin exposure
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Bitcoin Holdings</h3>
              <p className="text-2xl font-bold">{formatNumber(etf.btcHeld)} BTC</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">ETF Price</h3>
              <p className="text-2xl font-bold">{formatCurrency(etf.price || 0)}</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">BTC per Share</h3>
              <p className="text-2xl font-bold">{etf.btcPerShare?.toFixed(6) || 'N/A'}</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Premium/Discount</h3>
              <p className={`text-2xl font-bold ${(etf.premiumDiscount || 0) >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                {formatPercentage(etf.premiumDiscount || 0)}
              </p>
            </div>
          </div>
        </header>

        <div className="prose prose-lg max-w-none mb-8">
          <h2>About {etf.name}</h2>
          <p>
            {etf.name} ({etf.ticker}) is a Bitcoin spot ETF that provides investors with direct 
            exposure to Bitcoin through a traditional exchange-traded fund structure. The fund 
            currently holds {formatNumber(etf.btcHeld)} Bitcoin and trades at a{' '}
            {(etf.premiumDiscount || 0) >= 0 ? 'premium' : 'discount'} of{' '}
            {formatPercentage(Math.abs(etf.premiumDiscount || 0))} to its net asset value.
          </p>
          
          <h3>ETF Metrics</h3>
          <ul>
            <li><strong>BTC per Share:</strong> {etf.btcPerShare?.toFixed(6) || 'N/A'} Bitcoin per share</li>
            <li><strong>Shares Outstanding:</strong> {formatNumber(etf.sharesOutstanding || 0)} shares</li>
            <li><strong>Premium/Discount:</strong> {formatPercentage(etf.premiumDiscount || 0)} to NAV</li>
            <li><strong>Current Price:</strong> {formatCurrency(etf.price || 0)} per share</li>
          </ul>

          <h3>Investment Considerations</h3>
          <p>
            When investing in {etf.name}, consider the current{' '}
            {(etf.premiumDiscount || 0) >= 0 ? 'premium' : 'discount'} to NAV. A{' '}
            {(etf.premiumDiscount || 0) >= 0 ? 'premium' : 'discount'} suggests the ETF is trading{' '}
            {(etf.premiumDiscount || 0) >= 0 ? 'above' : 'below'} the value of its underlying Bitcoin holdings.
          </p>

          <h3>How Bitcoin ETFs Work</h3>
          <p>
            Bitcoin spot ETFs like {etf.name} hold actual Bitcoin rather than Bitcoin derivatives. 
            The fund uses authorized participants and market makers to keep the ETF price close to 
            the underlying Bitcoin value, though premiums and discounts can occur during volatile markets.
          </p>
        </div>

        <ETFDetailClient etf={etf} />
      </div>
    </div>
  )
}