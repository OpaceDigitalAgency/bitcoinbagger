import useSWR from 'swr'

const BITCOIN_PRICE_API = '/api/bitcoin-price'

export function useBitcoinPrice() {
  return useSWR(BITCOIN_PRICE_API, {
    refreshInterval: 60000, // 1 minute
    revalidateOnFocus: false,
  })
}