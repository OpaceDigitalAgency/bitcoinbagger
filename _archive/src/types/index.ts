export interface CompanyData {
  ticker: string
  name: string
  btcHeld: number
  btcValue?: number
  revenue?: number
  sharesOutstanding?: number
  marketPrice?: number
  bsp?: number
  premium?: number
  businessModel?: string
  btcRole?: string
}

export interface ETFData {
  ticker: string
  name: string
  btcHeld: number
  sharesOutstanding: number
  btcPerShare?: number
  price?: number
  premiumDiscount?: number
}

export interface BitcoinPrice {
  bitcoin: {
    usd: number
    usd_24h_change: number
    usd_market_cap: number
    usd_24h_vol: number
  }
}