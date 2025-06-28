'use client'

import { useState } from 'react'
import { CompaniesTable } from './CompaniesTable'
import { ScenarioModal } from './ScenarioModal'

interface CompaniesPageClientProps {
  initialData: {
    companies: any[]
    bitcoinData: any
  }
}

export function CompaniesPageClient({ initialData }: CompaniesPageClientProps) {
  const [showScenario, setShowScenario] = useState(false)
  const [selectedTicker, setSelectedTicker] = useState<string | null>(null)

  return (
    <>
      <CompaniesTable 
        onTickerSelect={(ticker) => setSelectedTicker(ticker)}
        onScenarioOpen={(ticker) => {
          setSelectedTicker(ticker)
          setShowScenario(true)
        }}
      />

      {showScenario && (
        <ScenarioModal
          ticker={selectedTicker}
          onClose={() => {
            setShowScenario(false)
            setSelectedTicker(null)
          }}
        />
      )}
    </>
  )
}