'use client'

import { useState, useEffect } from 'react'
import { X, Calculator, TrendingUp } from 'lucide-react'
import { formatCurrency, formatPercentage } from '@/lib/utils'

interface ScenarioModalProps {
  ticker: string | null
  onClose: () => void
}

export function ScenarioModal({ ticker, onClose }: ScenarioModalProps) {
  const [btcPrice, setBtcPrice] = useState(0)
  const [salesMultiple, setSalesMultiple] = useState(5)
  const [scenario, setScenario] = useState<any>(null)

  useEffect(() => {
    if (ticker) {
      // Calculate scenario based on current inputs
      calculateScenario()
    }
  }, [ticker, btcPrice, salesMultiple])

  const calculateScenario = () => {
    // Mock calculation - in real app this would use actual company data
    const mockCompanyData = {
      btcHeld: ticker === 'MSTR' ? 592345 : 20000,
      revenue: ticker === 'MSTR' ? 0.463 : 0.5,
      sharesOut: ticker === 'MSTR' ? 256.5 : 300,
      currentPrice: ticker === 'MSTR' ? 367 : 15
    }

    const btcValue = mockCompanyData.btcHeld * btcPrice
    const salesValue = mockCompanyData.revenue * 1000000000 * salesMultiple
    const fairValue = (btcValue + salesValue) / (mockCompanyData.sharesOut * 1000000)
    const upside = (fairValue / mockCompanyData.currentPrice) - 1

    setScenario({
      ...mockCompanyData,
      btcValue,
      salesValue,
      fairValue,
      upside
    })
  }

  if (!ticker) return null

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-card rounded-lg border border-border w-full max-w-2xl max-h-[80vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b border-border">
          <div className="flex items-center space-x-3">
            <Calculator className="h-6 w-6 text-primary" />
            <h2 className="text-xl font-semibold">Scenario Analysis - {ticker}</h2>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-lg hover:bg-muted transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium mb-2">Bitcoin Price Scenario</label>
              <div className="space-y-2">
                <input
                  type="range"
                  min="50000"
                  max="500000"
                  step="5000"
                  value={btcPrice}
                  onChange={(e) => setBtcPrice(Number(e.target.value))}
                  className="w-full"
                />
                <div className="flex justify-between text-sm text-muted-foreground">
                  <span>$50K</span>
                  <span className="font-medium text-foreground">{formatCurrency(btcPrice)}</span>
                  <span>$500K</span>
                </div>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium mb-2">Sales Multiple</label>
              <div className="space-y-2">
                <input
                  type="range"
                  min="1"
                  max="20"
                  step="0.5"
                  value={salesMultiple}
                  onChange={(e) => setSalesMultiple(Number(e.target.value))}
                  className="w-full"
                />
                <div className="flex justify-between text-sm text-muted-foreground">
                  <span>1x</span>
                  <span className="font-medium text-foreground">{salesMultiple}x</span>
                  <span>20x</span>
                </div>
              </div>
            </div>
          </div>

          {scenario && (
            <div className="bg-muted/30 rounded-lg p-6">
              <h3 className="text-lg font-semibold mb-4 flex items-center space-x-2">
                <TrendingUp className="h-5 w-5" />
                <span>Valuation Results</span>
              </h3>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">BTC Holdings Value:</span>
                    <span className="font-medium">{formatCurrency(scenario.btcValue)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Business Value:</span>
                    <span className="font-medium">{formatCurrency(scenario.salesValue)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Shares Outstanding:</span>
                    <span className="font-medium">{scenario.sharesOut}M</span>
                  </div>
                </div>
                
                <div className="space-y-3">
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Current Price:</span>
                    <span className="font-medium">{formatCurrency(scenario.currentPrice)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-muted-foreground">Fair Value:</span>
                    <span className="font-medium">{formatCurrency(scenario.fairValue)}</span>
                  </div>
                  <div className="flex justify-between border-t pt-3">
                    <span className="font-medium">Potential Upside:</span>
                    <span className={`font-bold ${scenario.upside >= 0 ? 'positive' : 'negative'}`}>
                      {formatPercentage(scenario.upside)}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          )}

          <div className="text-xs text-muted-foreground bg-muted/30 rounded-lg p-4">
            <strong>Disclaimer:</strong> This is a simplified scenario analysis for educational purposes. 
            Actual investment decisions should be based on comprehensive research and professional advice. 
            Past performance does not guarantee future results.
          </div>
        </div>
      </div>
    </div>
  )
}