'use client'

import { ETFsTable } from './ETFsTable'

interface ETFsPageClientProps {
  initialData: {
    etfs: any[]
    bitcoinData: any
  }
}

export function ETFsPageClient({ initialData }: ETFsPageClientProps) {
  return <ETFsTable />
}