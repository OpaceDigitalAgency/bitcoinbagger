'use client'

import { Bitcoin, TrendingUp, TrendingDown, Moon, Sun } from 'lucide-react'
import { useBitcoinPrice } from '@/hooks/useBitcoinPrice'
import { useTheme } from './ThemeProvider'
import { formatCurrency, formatPercentage } from '@/lib/utils'

export function BitcoinHeader() {
  const { data: bitcoinData, isLoading } = useBitcoinPrice()
  const { theme, setTheme } = useTheme()

  const bitcoin = bitcoinData?.bitcoin
  const change24h = bitcoin?.price_change_percentage_24h || 0
  const isPositive = change24h >= 0

  return (
    <header className="bg-card border-b border-border shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center space-x-4">
            <div className="flex items-center space-x-2">
              <Bitcoin className="h-8 w-8 text-orange-500" />
              <h1 className="text-2xl font-bold text-foreground">BitcoinBagger</h1>
            </div>
            
            <div className="hidden md:flex items-center space-x-6 ml-8">
              <div className="flex items-center space-x-2">
                <span className="text-sm text-muted-foreground">BTC/USD</span>
                {isLoading ? (
                  <div className="w-20 h-6 bg-muted animate-pulse rounded" />
                ) : (
                  <span className="text-xl font-semibold">
                    {formatCurrency(bitcoin?.usd || 0)}
                  </span>
                )}
              </div>
              
              {!isLoading && bitcoin && (
                <div className={`flex items-center space-x-1 ${isPositive ? 'positive' : 'negative'}`}>
                  {isPositive ? (
                    <TrendingUp className="h-4 w-4" />
                  ) : (
                    <TrendingDown className="h-4 w-4" />
                  )}
                  <span className="font-medium">
                    {formatPercentage(change24h)}
                  </span>
                  <span className="text-sm text-muted-foreground">24h</span>
                </div>
              )}
            </div>
          </div>

          <div className="flex items-center space-x-4">
            <button
              onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
              className="p-2 rounded-lg hover:bg-muted transition-colors"
            >
              {theme === 'dark' ? (
                <Sun className="h-5 w-5" />
              ) : (
                <Moon className="h-5 w-5" />
              )}
            </button>
            
            <div className="text-right">
              <div className="text-sm text-muted-foreground">Last updated</div>
              <div className="text-sm font-medium">
                {new Date().toLocaleTimeString()}
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>
  )
}