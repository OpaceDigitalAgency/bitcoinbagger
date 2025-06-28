'use client'

import { CompaniesTable } from './CompaniesTable'
import { ETFsTable } from './ETFsTable'
import { Watchlist } from './Watchlist'

interface MainContentProps {
  activeTab: 'companies' | 'etfs' | 'watchlist'
  onTickerSelect: (ticker: string) => void
  onScenarioOpen: (ticker: string) => void
}

export function MainContent({ activeTab, onTickerSelect, onScenarioOpen }: MainContentProps) {
  return (
    <main className="flex-1 p-6">
      <div className="max-w-7xl mx-auto">
        {activeTab === 'companies' && (
          <CompaniesTable 
            onTickerSelect={onTickerSelect}
            onScenarioOpen={onScenarioOpen}
          />
        )}
        {activeTab === 'etfs' && <ETFsTable />}
        {activeTab === 'watchlist' && <Watchlist />}
      </div>
    </main>
  )
}