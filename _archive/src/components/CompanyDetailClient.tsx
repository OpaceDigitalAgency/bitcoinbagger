'use client'

import { useState } from 'react'
import { Calculator, TrendingUp, TrendingDown } from 'lucide-react'
import { formatCurrency, formatPercentage } from '@/lib/utils'

interface CompanyDetailClientProps {
  company: any
}

export function CompanyDetailClient({ company }: CompanyDetailClientProps) {
  const [btcPrice, setBtcPrice] = useState(0)
  
  const projectedBtcValue = company.btcHeld * btcPrice
  const projectedBsp = company.sharesOutstanding ? projectedBtcValue / (company.sharesOutstanding * 1000000) : 0
  const projectedPremium = projectedBsp > 0 ? ((company.marketPrice / projectedBsp) - 1) * 100 : 0

  return (
    <div className="space-y-8">
      <section className="bg-card p-8 rounded-lg border">
        <h2 className="text-2xl font-bold mb-6 flex items-center">
          <Calculator className="h-6 w-6 mr-3" />
          Bitcoin Price Scenario Analysis
        </h2>
        
        <div className="mb-6">
          <label className="block text-sm font-medium mb-3">
            Bitcoin Price Scenario: {formatCurrency(btcPrice)}
          </label>
          <input
            type="range"
            min="50000"
            max="500000"
            step="5000"
            value={btcPrice}
            onChange={(e) => setBtcPrice(Number(e.target.value))}
            className="w-full h-2 bg-muted rounded-lg appearance-none cursor-pointer"
          />
          <div className="flex justify-between text-sm text-muted-foreground mt-2">
            <span>$50K</span>
            <span>$500K</span>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="text-center p-4 bg-muted/30 rounded-lg">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Projected BTC Value</h4>
            <p className="text-2xl font-bold">{formatCurrency(projectedBtcValue)}</p>
          </div>
          <div className="text-center p-4 bg-muted/30 rounded-lg">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Projected BSP</h4>
            <p className="text-2xl font-bold">{formatCurrency(projectedBsp)}</p>
          </div>
          <div className="text-center p-4 bg-muted/30 rounded-lg">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Implied Premium</h4>
            <p className={`text-2xl font-bold flex items-center justify-center ${projectedPremium >= 0 ? 'text-red-600' : 'text-green-600'}`}>
              {projectedPremium >= 0 ? <TrendingUp className="h-5 w-5 mr-1" /> : <TrendingDown className="h-5 w-5 mr-1" />}
              {formatPercentage(projectedPremium)}
            </p>
          </div>
        </div>
      </section>

      <section className="bg-card p-8 rounded-lg border">
        <h2 className="text-2xl font-bold mb-6">Historical Context</h2>
        <div className="prose prose-lg max-w-none">
          <p>
            Understanding {company.name}'s Bitcoin strategy and historical performance can help 
            inform investment decisions. The company's approach to Bitcoin as {company.btcRole.toLowerCase()} 
            reflects their long-term commitment to the digital asset.
          </p>
          
          <h4>Key Investment Considerations:</h4>
          <ul>
            <li>Bitcoin holdings represent a significant portion of the company's value</li>
            <li>Stock price may exhibit higher volatility than Bitcoin due to leverage effects</li>
            <li>Premium/discount to BSP can provide entry/exit opportunities</li>
            <li>Consider the company's operational business alongside Bitcoin exposure</li>
          </ul>
        </div>
      </section>
    </div>
  )
}