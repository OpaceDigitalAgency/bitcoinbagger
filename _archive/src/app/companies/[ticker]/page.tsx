import { CompanyPageClient } from '@/components/CompanyPageClient'

interface PageProps {
  params: {
    ticker: string
  }
}

// Generate static params for static export
export async function generateStaticParams() {
  // Return a few common tickers for static generation
  return [
    { ticker: 'mstr' },
    { ticker: 'tsla' },
    { ticker: 'mara' },
    { ticker: 'riot' },
    { ticker: 'coin' }
  ]
}

export default function CompanyDetailPage({ params }: PageProps) {
  return <CompanyPageClient ticker={params.ticker} />
}