import useSWR from 'swr'
import { ETFData } from '@/types'


export function useETFData() {
  return useSWR('/api/etfs', {
    refreshInterval: 60000,
    revalidateOnFocus: false,
  })
}