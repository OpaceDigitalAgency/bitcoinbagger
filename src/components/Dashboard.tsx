'use client'

import { useState } from 'react'
import { Sidebar } from './Sidebar'
import { MainContent } from './MainContent'
import { BitcoinHeader } from './BitcoinHeader'
import { ScenarioModal } from './ScenarioModal'

export function Dashboard() {
  const [activeTab, setActiveTab] = useState<'companies' | 'etfs' | 'watchlist'>('companies')
  const [showScenario, setShowScenario] = useState(false)
  const [selectedTicker, setSelectedTicker] = useState<string | null>(null)

  return (
    <div className="min-h-screen bg-background">
      <BitcoinHeader />
      
      <div className="flex">
        <Sidebar 
          activeTab={activeTab} 
          onTabChange={setActiveTab}
          onScenarioOpen={() => setShowScenario(true)}
        />
        
        <MainContent 
          activeTab={activeTab}
          onTickerSelect={setSelectedTicker}
          onScenarioOpen={(ticker) => {
            setSelectedTicker(ticker)
            setShowScenario(true)
          }}
        />
      </div>

      {showScenario && (
        <ScenarioModal
          ticker={selectedTicker}
          onClose={() => {
            setShowScenario(false)
            setSelectedTicker(null)
          }}
        />
      )}
    </div>
  )
}