'use client'

import { useEffect, useState } from 'react'
import { CompanyDetailClient } from '@/components/CompanyDetailClient'
import { formatCurrency, formatNumber, formatPercentage } from '@/lib/utils'
import { Navigation } from '@/components/Navigation'

interface CompanyPageClientProps {
  ticker: string
}

export function CompanyPageClient({ ticker }: CompanyPageClientProps) {
  const [company, setCompany] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    async function fetchCompany() {
      try {
        setLoading(true)
        const response = await fetch('/.netlify/functions/companies')
        
        if (!response.ok) {
          throw new Error('Failed to fetch companies data')
        }
        
        const companies = await response.json()
        const foundCompany = companies.find((c: any) => 
          c.ticker.toLowerCase() === ticker.toLowerCase()
        )
        
        if (!foundCompany) {
          setError('Company not found')
          return
        }
        
        setCompany(foundCompany)
      } catch (err) {
        setError('Failed to load company data')
        console.error('Error fetching company:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchCompany()
  }, [ticker])

  if (loading) {
    return (
      <div className="min-h-screen bg-background">
        <Navigation />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="flex items-center justify-center h-64">
            <div className="text-center">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
              <p className="text-muted-foreground">Loading company data...</p>
            </div>
          </div>
        </div>
      </div>
    )
  }

  if (error || !company) {
    return (
      <div className="min-h-screen bg-background">
        <Navigation />
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="flex items-center justify-center h-64">
            <div className="text-center">
              <h1 className="text-2xl font-bold text-foreground mb-4">Company Not Found</h1>
              <p className="text-muted-foreground mb-6">
                The company with ticker "{ticker.toUpperCase()}" could not be found.
              </p>
              <a 
                href="/companies" 
                className="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
              >
                View All Companies
              </a>
            </div>
          </div>
        </div>
      </div>
    )
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