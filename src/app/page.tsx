import { Metadata } from 'next'
import Link from 'next/link'
import dynamic from 'next/dynamic'
import { Bitcoin, TrendingUp, Building2, PieChart } from 'lucide-react'
import { formatCurrency, formatNumber } from '@/lib/utils'

export const dynamic = 'force-dynamic'

const Navigation = dynamic(() => import('@/components/Navigation').then(mod => ({ default: mod.Navigation })), {
  ssr: false
})

export const metadata: Metadata = {
  title: 'BitcoinBagger - Bitcoin Proxy Companies & ETF Tracker',
  description: 'Track Bitcoin proxy companies and ETFs with live data. Monitor MicroStrategy, Marathon Digital, Tesla Bitcoin holdings plus IBIT, FBTC, GBTC premiums and discounts.',
  keywords: 'bitcoin companies, bitcoin ETF, MSTR, MARA, TSLA, IBIT, FBTC, GBTC, bitcoin tracker, proxy stocks',
  openGraph: {
    title: 'BitcoinBagger - Bitcoin Investment Tracker',
    description: 'Live tracking of Bitcoin proxy companies and ETFs. Get real-time data on holdings, premiums, and investment opportunities.',
    type: 'website',
    url: 'https://bitcoinbagger.com'
  },
  twitter: {
    card: 'summary_large_image',
    title: 'BitcoinBagger - Bitcoin Investment Tracker',
    description: 'Track Bitcoin proxy companies and ETFs with live data and analysis.',
  }
}

async function getBitcoinData() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/api/bitcoin-price`, {
      next: { revalidate: 300 }
    })

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    const data = await response.json()

    return {
      usd: data.bitcoin?.usd || data.usd,
      usd_24h_change: data.bitcoin?.usd_24h_change || data.usd_24h_change || 0,
      usd_market_cap: data.bitcoin?.usd_market_cap || data.usd_market_cap
    }
  } catch (error) {
    console.error('Error fetching Bitcoin data:', error)
    return null
  }
}

async function getTopCompanies() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/api/companies`, {
      next: { revalidate: 300 }
    })
    
    if (!response.ok) {
      throw new Error('Failed to fetch companies data')
    }
    
    const companies = await response.json()
    return companies.slice(0, 3) // Top 3 companies
  } catch (error) {
    console.error('Error fetching companies:', error)
    return []
  }
}

async function getTopETFs() {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/api/etfs`, {
      next: { revalidate: 300 }
    })
    
    if (!response.ok) {
      throw new Error('Failed to fetch ETFs data')
    }
    
    const etfs = await response.json()
    return etfs.slice(0, 3) // Top 3 ETFs
  } catch (error) {
    console.error('Error fetching ETFs:', error)
    return []
  }
}

export default async function HomePage() {
  const [bitcoinData, topCompanies, topETFs] = await Promise.all([
    getBitcoinData(),
    getTopCompanies(),
    getTopETFs()
  ])

  // Handle data loading errors
  if (!bitcoinData) {
    return (
      <div className="min-h-screen bg-background">
        <Navigation />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
          <h1 className="text-4xl font-bold text-foreground mb-4">Service Temporarily Unavailable</h1>
          <p className="text-xl text-muted-foreground mb-8">
            Unable to fetch live Bitcoin price data. Please try again later.
          </p>
          <a
            href="/"
            className="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium inline-block"
          >
            Retry
          </a>
        </div>
      </div>
    )
  }

  const totalCompanyBtc = topCompanies.reduce((sum: number, company: any) => sum + (company.btcHeld || 0), 0)
  const totalETFBtc = topETFs.reduce((sum: number, etf: any) => sum + (etf.btcHeld || 0), 0)

  const structuredData = {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "BitcoinBagger",
    "description": "Track Bitcoin proxy companies and ETFs with live data and analysis",
    "url": "https://bitcoinbagger.com",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://bitcoinbagger.com/search?q={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }

  return (
    <div className="min-h-screen bg-background">
      <Navigation />
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(structuredData) }}
      />

      {/* Hero Section */}
      <section className="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-950/20 dark:to-orange-900/20 py-20">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <div className="flex items-center justify-center mb-6">
            <Bitcoin className="h-16 w-16 text-orange-500 mr-4" />
            <h1 className="text-5xl font-bold text-foreground">
              BitcoinBagger
            </h1>
          </div>
          <p className="text-2xl text-muted-foreground mb-8 max-w-3xl mx-auto">
            Track Bitcoin proxy companies and ETFs with live data. Monitor holdings, 
            premiums, and investment opportunities in real-time.
          </p>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div className="bg-white dark:bg-card p-8 rounded-lg shadow-lg border">
              <h3 className="text-3xl font-bold text-foreground mb-2">
                {bitcoinData?.usd ? formatCurrency(bitcoinData.usd) : 'Loading...'}
              </h3>
              <p className="text-muted-foreground mb-2">Bitcoin Price</p>
              <p className={`text-lg font-medium ${(bitcoinData?.usd_24h_change || 0) >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                {(bitcoinData?.usd_24h_change || 0) >= 0 ? '+' : ''}{(bitcoinData?.usd_24h_change || 0).toFixed(1)}% 24h
              </p>
            </div>
            
            <div className="bg-white dark:bg-card p-8 rounded-lg shadow-lg border">
              <h3 className="text-3xl font-bold text-foreground mb-2">
                {formatNumber(totalCompanyBtc)}
              </h3>
              <p className="text-muted-foreground mb-2">Company Holdings</p>
              <p className="text-lg font-medium text-orange-600">
                {formatCurrency(totalCompanyBtc * bitcoinData.usd)} Value
              </p>
            </div>
            
            <div className="bg-white dark:bg-card p-8 rounded-lg shadow-lg border">
              <h3 className="text-3xl font-bold text-foreground mb-2">
                {formatNumber(totalETFBtc)}
              </h3>
              <p className="text-muted-foreground mb-2">ETF Holdings</p>
              <p className="text-lg font-medium text-blue-600">
                {formatCurrency(totalETFBtc * bitcoinData.usd)} AUM
              </p>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Link 
              href="/companies"
              className="bg-orange-600 hover:bg-orange-700 text-white px-8 py-4 rounded-lg text-lg font-medium transition-colors inline-flex items-center"
            >
              <Building2 className="h-5 w-5 mr-2" />
              View Companies
            </Link>
            <Link 
              href="/etfs"
              className="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-lg text-lg font-medium transition-colors inline-flex items-center"
            >
              <PieChart className="h-5 w-5 mr-2" />
              View ETFs
            </Link>
          </div>
        </div>
      </section>

      {/* Top Companies Section */}
      <section className="py-16 bg-white dark:bg-background">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-foreground mb-4">
              Top Bitcoin Proxy Companies
            </h2>
            <p className="text-xl text-muted-foreground max-w-3xl mx-auto">
              Public companies holding significant Bitcoin reserves as treasury assets. 
              Track live premiums, BSP ratios, and investment opportunities.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            {topCompanies.filter((company: any) => company && company.ticker).map((company: any, index: number) => (
              <article key={company.ticker} className="bg-card p-8 rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-2xl font-bold text-foreground">{company.name}</h3>
                  <span className="text-lg font-medium text-orange-600">#{index + 1}</span>
                </div>
                <div className="space-y-3 mb-6">
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Ticker:</span>
                    <span className="font-medium">{company.ticker}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">BTC Holdings:</span>
                    <span className="font-medium">{formatNumber(company.btcHeld)} BTC</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Value:</span>
                    <span className="font-medium">{formatCurrency(company.btcValue)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Premium:</span>
                    <span className={`font-medium ${(company.premium || 0) >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {company.premium >= 0 ? '+' : ''}{(company.premium || 0).toFixed(1)}%
                    </span>
                  </div>
                </div>
                <Link 
                  href={`/companies/${company.ticker?.toLowerCase() || ''}`}
                  className="block w-full bg-orange-100 hover:bg-orange-200 dark:bg-orange-900/20 dark:hover:bg-orange-900/30 text-orange-800 dark:text-orange-200 text-center py-3 rounded-lg font-medium transition-colors"
                >
                  View Details
                </Link>
              </article>
            ))}
          </div>

          <div className="text-center">
            <Link 
              href="/companies"
              className="inline-flex items-center text-orange-600 hover:text-orange-700 text-lg font-medium"
            >
              View All Companies
              <TrendingUp className="h-5 w-5 ml-2" />
            </Link>
          </div>
        </div>
      </section>

      {/* Top ETFs Section */}
      <section className="py-16 bg-muted/30">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-foreground mb-4">
              Bitcoin Spot ETFs
            </h2>
            <p className="text-xl text-muted-foreground max-w-3xl mx-auto">
              Exchange-traded funds providing direct Bitcoin exposure. Monitor live premiums, 
              discounts, and trading metrics for optimal entry points.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            {topETFs.filter((etf: any) => etf && etf.ticker).map((etf: any, index: number) => (
              <article key={etf.ticker} className="bg-card p-8 rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="text-xl font-bold text-foreground">{etf.name}</h3>
                  <span className="text-lg font-medium text-blue-600">#{index + 1}</span>
                </div>
                <div className="space-y-3 mb-6">
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Ticker:</span>
                    <span className="font-medium">{etf.ticker}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">BTC Holdings:</span>
                    <span className="font-medium">{formatNumber(etf.btcHeld)} BTC</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Price:</span>
                    <span className="font-medium">{formatCurrency(etf.price || 0)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Premium:</span>
                    <span className={`font-medium ${(etf.premiumDiscount || 0) >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                      {etf.premiumDiscount >= 0 ? '+' : ''}{(etf.premiumDiscount || 0).toFixed(1)}%
                    </span>
                  </div>
                </div>
                <Link 
                  href={`/etfs/${etf.ticker?.toLowerCase() || ''}`}
                  className="block w-full bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 text-blue-800 dark:text-blue-200 text-center py-3 rounded-lg font-medium transition-colors"
                >
                  View Details
                </Link>
              </article>
            ))}
          </div>

          <div className="text-center">
            <Link 
              href="/etfs"
              className="inline-flex items-center text-blue-600 hover:text-blue-700 text-lg font-medium"
            >
              View All ETFs
              <TrendingUp className="h-5 w-5 ml-2" />
            </Link>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-16 bg-white dark:bg-background">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-foreground mb-4">
              Why BitcoinBagger?
            </h2>
            <p className="text-xl text-muted-foreground">
              Professional-grade Bitcoin investment tracking and analysis
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div className="text-center p-6">
              <div className="bg-orange-100 dark:bg-orange-900/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <TrendingUp className="h-8 w-8 text-orange-600" />
              </div>
              <h3 className="text-xl font-bold text-foreground mb-3">Live Data</h3>
              <p className="text-muted-foreground">
                Real-time tracking of Bitcoin holdings, stock prices, and premium/discount calculations
              </p>
            </div>

            <div className="text-center p-6">
              <div className="bg-blue-100 dark:bg-blue-900/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <Building2 className="h-8 w-8 text-blue-600" />
              </div>
              <h3 className="text-xl font-bold text-foreground mb-3">Company Analysis</h3>
              <p className="text-muted-foreground">
                Deep dive into business models, BSP ratios, and Bitcoin strategies of public companies
              </p>
            </div>

            <div className="text-center p-6">
              <div className="bg-green-100 dark:bg-green-900/20 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                <PieChart className="h-8 w-8 text-green-600" />
              </div>
              <h3 className="text-xl font-bold text-foreground mb-3">ETF Tracking</h3>
              <p className="text-muted-foreground">
                Monitor Bitcoin ETF premiums, discounts, and NAV comparisons for optimal timing
              </p>
            </div>
          </div>
        </div>
      </section>
    </div>
  )
}