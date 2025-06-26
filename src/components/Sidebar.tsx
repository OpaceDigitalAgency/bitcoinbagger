'use client'

import { Building2, PieChart, BookmarkIcon, Calculator, Download } from 'lucide-react'
import { clsx } from 'clsx'

interface SidebarProps {
  activeTab: 'companies' | 'etfs' | 'watchlist'
  onTabChange: (tab: 'companies' | 'etfs' | 'watchlist') => void
  onScenarioOpen: () => void
}

export function Sidebar({ activeTab, onTabChange, onScenarioOpen }: SidebarProps) {
  const menuItems = [
    {
      id: 'companies' as const,
      label: 'Companies',
      icon: Building2,
      description: 'Bitcoin proxy stocks'
    },
    {
      id: 'etfs' as const,
      label: 'Bitcoin ETFs',
      icon: PieChart,
      description: 'Spot Bitcoin ETFs'
    },
    {
      id: 'watchlist' as const,
      label: 'Watchlist',
      icon: BookmarkIcon,
      description: 'Your tracked picks'
    }
  ]

  return (
    <aside className="w-64 bg-card border-r border-border h-screen sticky top-0">
      <div className="p-6">
        <nav className="space-y-2">
          {menuItems.map((item) => {
            const Icon = item.icon
            return (
              <button
                key={item.id}
                onClick={() => onTabChange(item.id)}
                className={clsx(
                  'w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-left transition-colors',
                  activeTab === item.id
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-muted text-muted-foreground hover:text-foreground'
                )}
              >
                <Icon className="h-5 w-5" />
                <div>
                  <div className="font-medium">{item.label}</div>
                  <div className="text-xs opacity-70">{item.description}</div>
                </div>
              </button>
            )
          })}
        </nav>

        <div className="mt-8 pt-8 border-t border-border space-y-2">
          <button
            onClick={onScenarioOpen}
            className="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-left hover:bg-muted transition-colors text-muted-foreground hover:text-foreground"
          >
            <Calculator className="h-5 w-5" />
            <div>
              <div className="font-medium">Scenario Model</div>
              <div className="text-xs opacity-70">Price predictions</div>
            </div>
          </button>

          <button className="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-left hover:bg-muted transition-colors text-muted-foreground hover:text-foreground">
            <Download className="h-5 w-5" />
            <div>
              <div className="font-medium">Export CSV</div>
              <div className="text-xs opacity-70">Download data</div>
            </div>
          </button>
        </div>

        <div className="mt-8 pt-8 border-t border-border">
          <div className="text-xs text-muted-foreground space-y-1">
            <div>Data sources:</div>
            <div>• CoinGecko (BTC price)</div>
            <div>• BitcoinTreasuries.net</div>
            <div>• Bitbo.io (ETF data)</div>
            <div className="mt-2">
              Updates every minute
            </div>
          </div>
        </div>
      </div>
    </aside>
  )
}