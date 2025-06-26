import { Metadata } from 'next'
import { CompaniesPageClient } from '@/components/CompaniesPageClient'
import { formatCurrency } from '@/lib/utils'
import { Navigation } from '@/components/Navigation'

export const dynamic = 'force-dynamic'

export const metadata: Metadata = {
  title: 'Bitcoin Proxy Companies | BitcoinBagger',
  description: 'Track public companies holding Bitcoin as treasury assets. Live data on MicroStrategy, Marathon Digital, Tesla, and other Bitcoin proxy stocks.',
  keywords: 'bitcoin companies, bitcoin stocks, MSTR, MARA, TSLA, bitcoin treasury, proxy stocks',
  openGraph: {
    title: 'Bitcoin Proxy Companies - Live Holdings & Analysis',
    description: 'Real-time tracking of public companies holding Bitcoin. Get insights on premiums, BSP ratios, and investment opportunities.',
    type: 'website',
    url: 'https://bitcoinbagger.com/companies'
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Bitcoin Proxy Companies | BitcoinBagger',
    description: 'Track public companies holding Bitcoin as treasury assets. Live data and analysis.',
  }
}

async function getCompaniesData() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/api/companies`, {
      next: { revalidate: 300 } // 5 minute ISR
    })
    
    if (!response.ok) {
      throw new Error('Failed to fetch companies data')
    }
    
    const companies = await response.json()
    return Array.isArray(companies) ? companies : []
  } catch (error) {
    console.error('Error fetching companies:', error)
    return []
  }
}

async function getBitcoinPrice() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/api/bitcoin-price`, {
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

export default async function CompaniesPage() {
  const [companies, bitcoinData] = await Promise.all([
    getCompaniesData(),
    getBitcoinPrice()
  ])

  // Handle API errors gracefully
  if (!bitcoinData || !companies || companies.length === 0) {
    return (
      <div className="min-h-screen bg-background">
        <Navigation />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
          <h1 className="text-4xl font-bold text-foreground mb-4">Service Temporarily Unavailable</h1>
          <p className="text-xl text-muted-foreground mb-8">
            {!bitcoinData ? 'Unable to fetch live Bitcoin price data.' : 'Unable to fetch company data.'} Please try again later.
          </p>
          <a
            href="/companies"
            className="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium inline-block"
          >
            Retry
          </a>
        </div>
      </div>
    )
  }

  const totalBtcHeld = companies.reduce((sum: number, company: any) => sum + (company.btcHeld || 0), 0)
  const totalValue = bitcoinData?.bitcoin?.usd ? totalBtcHeld * bitcoinData.bitcoin.usd : 0

  return (
    <div className="min-h-screen bg-background">
      <Navigation />
      {/* SEO-friendly static content */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <header className="mb-8">
          <h1 className="text-4xl font-bold text-foreground mb-4">
            Bitcoin Proxy Companies
          </h1>
          <p className="text-xl text-muted-foreground mb-6">
            Track public companies holding Bitcoin as treasury assets. Monitor live holdings, 
            premiums, and BSP (Bitcoin per Share) ratios for strategic investment insights.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Total BTC Held</h3>
              <p className="text-2xl font-bold">{totalBtcHeld.toLocaleString()} BTC</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Combined Value</h3>
              <p className="text-2xl font-bold">{formatCurrency(totalValue)}</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Bitcoin Price</h3>
              <p className="text-2xl font-bold">
                {bitcoinData?.bitcoin?.usd ? formatCurrency(bitcoinData.bitcoin.usd) : 'Loading...'}
              </p>
            </div>
          </div>

          <div className="prose prose-lg max-w-none mb-8">
            <h2>What are Bitcoin Proxy Companies?</h2>
            <p>
              Bitcoin proxy companies are publicly traded companies that hold significant amounts of 
              Bitcoin on their balance sheets. These companies offer investors exposure to Bitcoin 
              through traditional stock markets, often trading at premiums or discounts to their 
              underlying Bitcoin value.
            </p>
            
            <h3>Key Metrics We Track:</h3>
            <ul>
              <li><strong>BTC Holdings:</strong> Total Bitcoin held by the company</li>
              <li><strong>BSP (Bitcoin per Share):</strong> Bitcoin value divided by shares outstanding</li>
              <li><strong>Premium/Discount:</strong> How much the stock trades above or below its BSP</li>
              <li><strong>Business Model:</strong> Primary revenue source and Bitcoin strategy</li>
            </ul>
          </div>
        </header>

        {/* Client-side interactive component */}
        <CompaniesPageClient initialData={{ companies, bitcoinData }} />
        
        <section className="mt-16">
          <h2 className="text-3xl font-bold mb-6">Top Bitcoin Holdings by Company</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {companies.slice(0, 6).map((company: any) => (
              <article key={company.ticker} className="bg-card p-6 rounded-lg border">
                <h3 className="text-xl font-semibold mb-2">{company.name}</h3>
                <p className="text-muted-foreground mb-4">Ticker: {company.ticker}</p>
                <div className="space-y-2">
                  <div className="flex justify-between">
                    <span>BTC Held:</span>
                    <span className="font-medium">{company.btcHeld?.toLocaleString()} BTC</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Value:</span>
                    <span className="font-medium">{formatCurrency(company.btcValue)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Business:</span>
                    <span className="font-medium text-sm">{company.businessModel}</span>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>
    </div>
  )
}