import useSWR from 'swr'
import { ETFData } from '@/types'


export function useETFData() {
  return useSWR('/.netlify/functions/etfs', {
    refreshInterval: 60000,
    revalidateOnFocus: false,
  })
}