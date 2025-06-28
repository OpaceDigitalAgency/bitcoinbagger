import { ETFPageClient } from '@/components/ETFPageClient'

interface PageProps {
  params: {
    ticker: string
  }
}

// Generate static params for static export
export async function generateStaticParams() {
  // Return a few common ETF tickers for static generation
  return [
    { ticker: 'ibit' },
    { ticker: 'fbtc' },
    { ticker: 'arkb' },
    { ticker: 'bitb' },
    { ticker: 'btco' }
  ]
}

export default function ETFDetailPage({ params }: PageProps) {
  return <ETFPageClient ticker={params.ticker} />
}