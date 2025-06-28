'use client'

import { useState } from 'react'
import { Calculator, TrendingUp, TrendingDown } from 'lucide-react'
import { formatCurrency, formatPercentage } from '@/lib/utils'

interface ETFDetailClientProps {
  etf: any
}

export function ETFDetailClient({ etf }: ETFDetailClientProps) {
  const [btcPrice, setBtcPrice] = useState(0)
  
  const projectedNav = (etf.btcPerShare || 0) * btcPrice
  const projectedPremium = projectedNav > 0 ? (((etf.price || 0) - projectedNav) / projectedNav) * 100 : 0

  return (
    <div className="space-y-8">
      <section className="bg-card p-8 rounded-lg border">
        <h2 className="text-2xl font-bold mb-6 flex items-center">
          <Calculator className="h-6 w-6 mr-3" />
          NAV Analysis by Bitcoin Price
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
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Projected NAV</h4>
            <p className="text-2xl font-bold">{formatCurrency(projectedNav)}</p>
          </div>
          <div className="text-center p-4 bg-muted/30 rounded-lg">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Current Price</h4>
            <p className="text-2xl font-bold">{formatCurrency(etf.price || 0)}</p>
          </div>
          <div className="text-center p-4 bg-muted/30 rounded-lg">
            <h4 className="text-sm font-medium text-muted-foreground mb-2">Premium/Discount</h4>
            <p className={`text-2xl font-bold flex items-center justify-center ${projectedPremium >= 0 ? 'text-red-600' : 'text-green-600'}`}>
              {projectedPremium >= 0 ? <TrendingUp className="h-5 w-5 mr-1" /> : <TrendingDown className="h-5 w-5 mr-1" />}
              {formatPercentage(projectedPremium)}
            </p>
          </div>
        </div>
      </section>

      <section className="bg-card p-8 rounded-lg border">
        <h2 className="text-2xl font-bold mb-6">ETF Trading Strategy</h2>
        <div className="prose prose-lg max-w-none">
          <p>
            {etf.name} provides direct Bitcoin exposure through a traditional ETF structure. 
            Understanding the premium/discount to NAV can help optimize entry and exit points.
          </p>
          
          <h4>Trading Considerations:</h4>
          <ul>
            <li><strong>Premium Trading:</strong> ETF trading above NAV may indicate high demand</li>
            <li><strong>Discount Opportunities:</strong> ETF trading below NAV may present buying opportunities</li>
            <li><strong>Arbitrage Mechanism:</strong> Authorized participants help keep prices close to NAV</li>
            <li><strong>Market Hours:</strong> ETF trades during market hours while Bitcoin trades 24/7</li>
          </ul>

          <h4>Risk Factors:</h4>
          <ul>
            <li>ETF may not perfectly track Bitcoin due to fees and tracking error</li>
            <li>Premiums and discounts can persist during volatile markets</li>
            <li>Management fees reduce returns compared to direct Bitcoin ownership</li>
            <li>Regulatory changes could impact ETF operations</li>
          </ul>
        </div>
      </section>
    </div>
  )
}