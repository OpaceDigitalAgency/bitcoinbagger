import { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { CompanyDetailClient } from '@/components/CompanyDetailClient'
import { formatCurrency, formatNumber, formatPercentage } from '@/lib/utils'
import { Navigation } from '@/components/Navigation'

interface Props {
  params: { ticker: string }
}

async function getCompanyData(ticker: string) {
  try {
    const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000'
    const response = await fetch(`${baseUrl}/api/companies`, {
      next: { revalidate: 300 }
    })
    
    if (!response.ok) {
      throw new Error('Failed to fetch companies data')
    }
    
    const companies = await response.json()
    return companies.find((c: any) => c.ticker.toLowerCase() === ticker.toLowerCase())
  } catch (error) {
    console.error('Error fetching company data:', error)
    return null
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const company = await getCompanyData(params.ticker)
  
  if (!company) {
    return {
      title: 'Company Not Found | BitcoinBagger',
      description: 'The requested company could not be found.'
    }
  }

  return {
    title: `${company.name} (${company.ticker}) Bitcoin Holdings | BitcoinBagger`,
    description: `${company.name} holds ${formatNumber(company.btcHeld)} BTC worth ${formatCurrency(company.btcValue)}. Track live BSP ratio, premium, and investment metrics.`,
    keywords: `${company.ticker}, ${company.name}, bitcoin holdings, BSP ratio, premium discount`,
    openGraph: {
      title: `${company.name} - Bitcoin Holdings Analysis`,
      description: `Live tracking of ${company.name}'s ${formatNumber(company.btcHeld)} BTC holdings and investment metrics.`,
      type: 'website',
      url: `https://bitcoinbagger.com/companies/${company.ticker.toLowerCase()}`
    },
    twitter: {
      card: 'summary_large_image',
      title: `${company.name} Bitcoin Holdings`,
      description: `${formatNumber(company.btcHeld)} BTC • ${formatCurrency(company.btcValue)} value • ${formatPercentage(company.premium || 0)} premium`
    }
  }
}

export default async function CompanyPage({ params }: Props) {
  const company = await getCompanyData(params.ticker)
  
  if (!company) {
    notFound()
  }

  const structuredData = {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": company.name,
    "tickerSymbol": company.ticker,
    "description": `${company.name} is a ${company.businessModel} that holds ${formatNumber(company.btcHeld)} Bitcoin as a treasury asset.`,
    "url": `https://bitcoinbagger.com/companies/${company.ticker.toLowerCase()}`,
    "sameAs": [
      `https://finance.yahoo.com/quote/${company.ticker}`,
      `https://www.google.com/finance/quote/${company.ticker}`
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
              <li><a href="/companies" className="hover:text-foreground">Companies</a></li>
              <li>•</li>
              <li className="text-foreground">{company.ticker}</li>
            </ol>
          </nav>
          
          <div className="flex items-start justify-between mb-6">
            <div>
              <h1 className="text-4xl font-bold text-foreground mb-2">
                {company.name}
              </h1>
              <p className="text-xl text-muted-foreground mb-4">
                Ticker: {company.ticker} • Rank #{company.rank || 'N/A'}
              </p>
              <p className="text-lg text-muted-foreground">
                {company.businessModel}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Bitcoin Holdings</h3>
              <p className="text-2xl font-bold">{formatNumber(company.btcHeld)} BTC</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Holdings Value</h3>
              <p className="text-2xl font-bold">{formatCurrency(company.btcValue)}</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Stock Price</h3>
              <p className="text-2xl font-bold">{formatCurrency(company.marketPrice || 0)}</p>
            </div>
            <div className="bg-card p-6 rounded-lg border">
              <h3 className="text-sm font-medium text-muted-foreground mb-2">Premium</h3>
              <p className={`text-2xl font-bold ${(company.premium || 0) >= 0 ? 'text-red-600' : 'text-green-600'}`}>
                {formatPercentage(company.premium || 0)}
              </p>
            </div>
          </div>
        </header>

        <div className="prose prose-lg max-w-none mb-8">
          <h2>About {company.name}</h2>
          <p>
            {company.name} ({company.ticker}) is a {company.businessModel.toLowerCase()} that uses Bitcoin 
            as part of its {company.btcRole.toLowerCase()} strategy. The company currently holds{' '}
            {formatNumber(company.btcHeld)} Bitcoin, making it the #{company.rank || 'top'} public company 
            by Bitcoin holdings.
          </p>
          
          <h3>Investment Metrics</h3>
          <ul>
            <li><strong>BSP (Bitcoin per Share):</strong> {formatCurrency(company.bsp || 0)} per share</li>
            <li><strong>Market Premium:</strong> The stock trades at a {formatPercentage(Math.abs(company.premium || 0))} {(company.premium || 0) >= 0 ? 'premium' : 'discount'} to its Bitcoin value</li>
            <li><strong>Shares Outstanding:</strong> {formatNumber((company.sharesOutstanding || 0) * 1000000)} shares</li>
            <li><strong>Bitcoin Strategy:</strong> {company.btcRole}</li>
          </ul>

          <h3>Key Considerations</h3>
          <p>
            When investing in {company.name}, consider that the stock price may be influenced by both 
            the company's operational performance and Bitcoin price movements. The current{' '}
            {(company.premium || 0) >= 0 ? 'premium' : 'discount'} suggests the market is valuing 
            the company {(company.premium || 0) >= 0 ? 'above' : 'below'} its underlying Bitcoin holdings.
          </p>
        </div>

        <CompanyDetailClient company={company} />
      </div>
    </div>
  )
}