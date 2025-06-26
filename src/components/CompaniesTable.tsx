'use client'

import { useState } from 'react'
import { TrendingUp, TrendingDown, Calculator, Eye, Star } from 'lucide-react'
import { useCompaniesData } from '@/hooks/useCompaniesData'
import { useBitcoinPrice } from '@/hooks/useBitcoinPrice'
import { formatCurrency, formatNumber, formatPercentage, getPremiumColor } from '@/lib/utils'
import { CompanyData } from '@/types'
import { clsx } from 'clsx'

interface CompaniesTableProps {
  onTickerSelect: (ticker: string) => void
  onScenarioOpen: (ticker: string) => void
}

export function CompaniesTable({ onTickerSelect, onScenarioOpen }: CompaniesTableProps) {
  const { data: companies, isLoading } = useCompaniesData()
  const { data: bitcoinData } = useBitcoinPrice()
  const [sortBy, setSortBy] = useState<keyof CompanyData>('btcHeld')
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc')

  const bitcoinPrice = bitcoinData?.bitcoin?.usd || 0

  const handleSort = (key: keyof CompanyData) => {
    if (sortBy === key) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')
    } else {
      setSortBy(key)
      setSortOrder('desc')
    }
  }

  const sortedCompanies = companies?.slice().sort((a, b) => {
    const aVal = a[sortBy] || 0
    const bVal = b[sortBy] || 0
    return sortOrder === 'asc' ? Number(aVal) - Number(bVal) : Number(bVal) - Number(aVal)
  })

  if (isLoading) {
    return (
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <h2 className="text-2xl font-bold">Bitcoin Proxy Companies</h2>
        </div>
        <div className="bg-card rounded-lg border border-border overflow-hidden">
          <div className="p-8 text-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
            <p className="mt-4 text-muted-foreground">Loading company data...</p>
          </div>
        </div>
      </div>
    )
  }

  if (!companies || companies.length === 0) {
    return (
      <div className="space-y-4">
        <div className="flex justify-between items-center">
          <h2 className="text-2xl font-bold">Bitcoin Proxy Companies</h2>
        </div>
        <div className="bg-card rounded-lg border border-border overflow-hidden">
          <div className="p-8 text-center">
            <p className="text-muted-foreground">No company data available</p>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold">Bitcoin Proxy Companies</h2>
          <p className="text-muted-foreground">Public companies holding significant Bitcoin reserves</p>
        </div>
        <div className="text-right">
          <div className="text-sm text-muted-foreground">Bitcoin Price</div>
          <div className="text-xl font-semibold">{formatCurrency(bitcoinPrice)}</div>
        </div>
      </div>

      <div className="bg-card rounded-lg border border-border overflow-hidden slide-in">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-muted/50">
              <tr>
                <th className="px-6 py-4 text-left text-sm font-medium text-muted-foreground">Company</th>
                <th 
                  className="px-6 py-4 text-right text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                  onClick={() => handleSort('btcHeld')}
                >
                  BTC Held {sortBy === 'btcHeld' && (sortOrder === 'asc' ? '↑' : '↓')}
                </th>
                <th 
                  className="px-6 py-4 text-right text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                  onClick={() => handleSort('btcValue')}
                >
                  BTC Value {sortBy === 'btcValue' && (sortOrder === 'asc' ? '↑' : '↓')}
                </th>
                <th 
                  className="px-6 py-4 text-right text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                  onClick={() => handleSort('marketPrice')}
                >
                  Stock Price {sortBy === 'marketPrice' && (sortOrder === 'asc' ? '↑' : '↓')}
                </th>
                <th 
                  className="px-6 py-4 text-right text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                  onClick={() => handleSort('bsp')}
                >
                  BSP {sortBy === 'bsp' && (sortOrder === 'asc' ? '↑' : '↓')}
                </th>
                <th 
                  className="px-6 py-4 text-right text-sm font-medium text-muted-foreground cursor-pointer hover:text-foreground"
                  onClick={() => handleSort('premium')}
                >
                  Premium {sortBy === 'premium' && (sortOrder === 'asc' ? '↑' : '↓')}
                </th>
                <th className="px-6 py-4 text-center text-sm font-medium text-muted-foreground">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {sortedCompanies?.map((company, index) => {
                const premiumColor = getPremiumColor(company.premium || 0)
                return (
                  <tr key={company.ticker} className="hover:bg-muted/30 transition-colors">
                    <td className="px-6 py-4">
                      <div>
                        <div className="font-medium">{company.name}</div>
                        <div className="text-sm text-muted-foreground flex items-center space-x-2">
                          <span>{company.ticker}</span>
                          <span className="text-xs bg-muted px-2 py-1 rounded">
                            #{index + 1}
                          </span>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatNumber(company.btcHeld)}</div>
                      <div className="text-sm text-muted-foreground">BTC</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatCurrency(company.btcValue || 0)}</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatCurrency(company.marketPrice || 0)}</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className="font-medium">{formatCurrency(company.bsp || 0)}</div>
                    </td>
                    <td className="px-6 py-4 text-right">
                      <div className={clsx('font-medium flex items-center justify-end space-x-1', premiumColor)}>
                        {(company.premium || 0) >= 0 ? (
                          <TrendingUp className="h-4 w-4" />
                        ) : (
                          <TrendingDown className="h-4 w-4" />
                        )}
                        <span>{formatPercentage(company.premium || 0)}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center justify-center space-x-2">
                        <button
                          onClick={() => onTickerSelect(company.ticker)}
                          className="p-2 rounded-lg hover:bg-muted transition-colors text-muted-foreground hover:text-foreground"
                          title="View details"
                        >
                          <Eye className="h-4 w-4" />
                        </button>
                        <button
                          onClick={() => onScenarioOpen(company.ticker)}
                          className="p-2 rounded-lg hover:bg-muted transition-colors text-muted-foreground hover:text-foreground"
                          title="Run scenario"
                        >
                          <Calculator className="h-4 w-4" />
                        </button>
                        <button
                          className="p-2 rounded-lg hover:bg-muted transition-colors text-muted-foreground hover:text-foreground"
                          title="Add to watchlist"
                        >
                          <Star className="h-4 w-4" />
                        </button>
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