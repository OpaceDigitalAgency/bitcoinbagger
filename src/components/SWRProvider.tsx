'use client'

import { SWRConfig } from 'swr'

const fetcher = (url: string) => fetch(url).then((res) => res.json())

export function SWRProvider({ children }: { children: React.ReactNode }) {
  return (
    <SWRConfig
      value={{
        fetcher,
        refreshInterval: 60000, // 1 minute
        revalidateOnFocus: false,
        dedupingInterval: 30000, // 30 seconds
        errorRetryInterval: 5000,
        onError: (error) => {
          console.error('SWR Error:', error)
        },
      }}
    >
      {children}
    </SWRConfig>
  )
}