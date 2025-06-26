'use client'

import { useState, useEffect } from 'react'
import { Star, X, Plus } from 'lucide-react'

export function Watchlist() {
  const [watchlist, setWatchlist] = useState<string[]>([])
  const [newTicker, setNewTicker] = useState('')

  useEffect(() => {
    const saved = localStorage.getItem('bitcoinbagger-watchlist')
    if (saved) {
      setWatchlist(JSON.parse(saved))
    }
  }, [])

  const addToWatchlist = (ticker: string) => {
    if (ticker && !watchlist.includes(ticker.toUpperCase())) {
      const updated = [...watchlist, ticker.toUpperCase()]
      setWatchlist(updated)
      localStorage.setItem('bitcoinbagger-watchlist', JSON.stringify(updated))
      setNewTicker('')
    }
  }

  const removeFromWatchlist = (ticker: string) => {
    const updated = watchlist.filter(t => t !== ticker)
    setWatchlist(updated)
    localStorage.setItem('bitcoinbagger-watchlist', JSON.stringify(updated))
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold">Your Watchlist</h2>
          <p className="text-muted-foreground">Track your favorite Bitcoin proxy companies and ETFs</p>
        </div>
      </div>

      <div className="bg-card rounded-lg border border-border p-6 slide-in">
        <div className="flex space-x-4 mb-6">
          <input
            type="text"
            placeholder="Enter ticker symbol (e.g., MSTR, IBIT)"
            value={newTicker}
            onChange={(e) => setNewTicker(e.target.value.toUpperCase())}
            className="flex-1 px-4 py-2 border border-border rounded-lg bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary"
            onKeyPress={(e) => e.key === 'Enter' && addToWatchlist(newTicker)}
          />
          <button
            onClick={() => addToWatchlist(newTicker)}
            className="px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors flex items-center space-x-2"
          >
            <Plus className="h-4 w-4" />
            <span>Add</span>
          </button>
        </div>

        {watchlist.length === 0 ? (
          <div className="text-center py-12">
            <Star className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
            <h3 className="text-lg font-medium text-muted-foreground mb-2">No items in watchlist</h3>
            <p className="text-sm text-muted-foreground">Add ticker symbols to track your favorite Bitcoin investments</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {watchlist.map((ticker) => (
              <div key={ticker} className="bg-muted/30 rounded-lg p-4 flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <Star className="h-5 w-5 text-yellow-500" />
                  <span className="font-medium">{ticker}</span>
                </div>
                <button
                  onClick={() => removeFromWatchlist(ticker)}
                  className="p-1 rounded hover:bg-muted transition-colors text-muted-foreground hover:text-foreground"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}