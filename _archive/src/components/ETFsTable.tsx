'use client'

import { TrendingUp, TrendingDown } from 'lucide-react'
import { useETFData } from '@/hooks/useETFData'
import { formatCurrency, formatNumber, formatPercentage, getPremiumColor } from '@/lib/utils'
import { clsx } from 'clsx'

export function ETFsTable() {
  const { data: etfs, isLoading } = useETFData()

  if (isLoading) {
    return (
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <h2 className="text-2xl font-bold">Bitcoin Spot ETFs</h2>
        </div>
        <div className="bg-card rounded-lg border border-border overflow-hidden">
          <div className="p-8 text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
            <p className="mt-4 text-muted-foreground">Loading ETF data...</p>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold">Bitcoin Spot ETFs</h2>
          <p className="text-muted-foreground">Exchange-traded funds holding Bitcoin directly</p>
        </div>
      </div>

      <div className="bg-card rounded-lg border border-border overflow-hidden slide-in">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-muted/50">
              <tr>
                <th className="px-6 py-4 text-left text-sm font-medium text-muted-foreground">ETF</th>
                <th className="px-6 py-4 text-right text-sm font-medium text-muted-foreground">BTC Held</th>
                <th className="px-6 py-4 text-right text-sm font-medium text-muted-foreground">Shares O/S</th>
                <th className="px-6 py-4 text-right text-sm font-medium text-muted-foreground">BTC/Share</th>
                <th className="px-6 py-4 text-right text-sm font-medium text-muted-foreground">Price</th>
                <th className="px-6 py-4 text-right text-sm font-medium text-muted-foreground">Premium/Discount</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {etfs?.map((etf, index) => {
                const premiumColor = getPremiumColor(etf.premiumDiscount || 0)
                return (
                  <tr key={etf.ticker} className="hover:bg-muted/30 transition-colors">
                    <td className="px-6 py-4">
                      <div>
                        <div className="font-medium">{etf.name}</div>
                        <div className="text-sm text-muted-foreground flex items-center space-x-2">
                          <span>{etf.ticker}</span>
                          <span className="text-xs bg-muted px-2 py-1 rounded">
                            #{index + 1}
                          </span>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatNumber(etf.btcHeld)}</div>
                      <div className="text-sm text-muted-foreground">BTC</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatNumber(etf.sharesOutstanding)}</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{etf.btcPerShare?.toFixed(6) || 'N/A'}</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatCurrency(etf.price || 0)}</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className={clsx('font-medium flex items-center justify-end space-x-1', premiumColor)}>
                        {(etf.premiumDiscount || 0) >= 0 ? (
                          <TrendingUp className="h-4 w-4" />
                        ) : (
                          <TrendingDown className="h-4 w-4" />
                        )}
                        <span>{formatPercentage(etf.premiumDiscount || 0)}</span>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}