'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Bitcoin, Building2, PieChart, Home, Moon, Sun } from 'lucide-react'
import { useTheme } from './ThemeProvider'
import { useBitcoinPrice } from '@/hooks/useBitcoinPrice'
import { formatCurrency, formatPercentage } from '@/lib/utils'
import { clsx } from 'clsx'

export function Navigation() {
  const pathname = usePathname()
  const { theme, setTheme } = useTheme()
  const { data: bitcoinData, isLoading } = useBitcoinPrice()

  const bitcoin = bitcoinData?.bitcoin
  const change24h = bitcoin?.usd_24h_change || 0
  const isPositive = change24h >= 0

  const navItems = [
    {
      href: '/',
      label: 'Home',
      icon: Home,
      active: pathname === '/'
    },
    {
      href: '/companies',
      label: 'Companies',
      icon: Building2,
      active: pathname === '/companies' || pathname.startsWith('/companies/')
    },
    {
      href: '/etfs',
      label: 'ETFs',
      icon: PieChart,
      active: pathname === '/etfs' || pathname.startsWith('/etfs/')
    }
  ]

  return (
    <nav className="bg-card border-b border-border shadow-sm sticky top-0 z-50 backdrop-blur-sm bg-card/95">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Logo and Brand */}
          <div className="flex items-center space-x-8">
            <Link href="/" className="flex items-center space-x-3 hover:opacity-80 transition-opacity">
              <div className="flex items-center space-x-2">
                <Bitcoin className="h-8 w-8 text-orange-500" />
                <span className="text-xl font-bold text-foreground">BitcoinBagger</span>
              </div>
            </Link>

            {/* Navigation Links */}
            <div className="hidden md:flex items-center space-x-1">
              {navItems.map((item) => {
                const Icon = item.icon
                return (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={clsx(
                      'flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                      item.active
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted'
                    )}
                  >
                    <Icon className="h-4 w-4" />
                    <span>{item.label}</span>
                  </Link>
                )
              })}
            </div>
          </div>

          {/* Bitcoin Price & Theme Toggle */}
          <div className="flex items-center space-x-6">
            {/* Bitcoin Price Display */}
            <div className="hidden sm:flex items-center space-x-4">
              <div className="flex items-center space-x-2">
                <span className="text-sm text-muted-foreground">BTC/USD</span>
                {isLoading ? (
                  <div className="w-20 h-6 bg-muted animate-pulse rounded" />
                ) : (
                  <span className="text-lg font-semibold">
                    {bitcoin?.usd ? formatCurrency(bitcoin.usd) : 'Loading...'}
                  </span>
                )}
              </div>
              
              {!isLoading && bitcoin && (
                <div className={clsx(
                  'flex items-center space-x-1 text-sm font-medium',
                  isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                )}>
                  <span>
                    {formatPercentage(change24h)}
                  </span>
                  <span className="text-xs text-muted-foreground">24h</span>
                </div>
              )}
            </div>

            {/* Theme Toggle */}
            <button
              onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
              className="p-2 rounded-lg hover:bg-muted transition-colors"
              title="Toggle theme"
            >
              {theme === 'dark' ? (
                <Sun className="h-5 w-5" />
              ) : (
                <Moon className="h-5 w-5" />
              )}
            </button>
          </div>
        </div>

        {/* Mobile Navigation */}
        <div className="md:hidden border-t border-border">
          <div className="flex items-center justify-around py-2">
            {navItems.map((item) => {
              const Icon = item.icon
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={clsx(
                    'flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-xs font-medium transition-colors',
                    item.active
                      ? 'text-primary'
                      : 'text-muted-foreground hover:text-foreground'
                  )}
                >
                  <Icon className="h-5 w-5" />
                  <span>{item.label}</span>
                </Link>
              )
            })}
          </div>
        </div>
      </div>
    </nav>
  )
}