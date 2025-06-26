import useSWR from 'swr'
import { CompanyData } from '@/types'


export function useCompaniesData() {
  return useSWR('/api/companies', {
    refreshInterval: 60000,
    revalidateOnFocus: false,
  })
}