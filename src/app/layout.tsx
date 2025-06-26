import type { Metadata } from 'next'
import { Inter } from 'next/font/google'
import dynamic from 'next/dynamic'
import './globals.css'
import { SWRProvider } from '@/components/SWRProvider'
import { ThemeProvider } from '@/components/ThemeProvider'
import { cn } from '@/lib/utils'

const Navigation = dynamic(() => import('@/components/Navigation').then(mod => ({ default: mod.Navigation })), {
  ssr: false
})

const inter = Inter({ subsets: ['latin'] })

export const metadata: Metadata = {
  title: 'BitcoinBagger - Bitcoin Company Dashboard',
  description: 'Live dashboard for Bitcoin proxy companies, ETFs, and scenario modeling',
  keywords: 'bitcoin, cryptocurrency, stocks, ETF, investment, dashboard',
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={cn(inter.className, 'bg-background text-foreground')}>
        <ThemeProvider>
          <SWRProvider>
            {children}
          </SWRProvider>
        </ThemeProvider>
      </body>
    </html>
  )
}