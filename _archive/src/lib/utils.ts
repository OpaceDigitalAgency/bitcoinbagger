import { clsx, type ClassValue } from 'clsx'

export function cn(...inputs: ClassValue[]) {
  return clsx(inputs)
}

export function formatCurrency(value: number): string {
  if (value == null || isNaN(value)) {
    return '$0.00'
  }
  
  if (value >= 1e9) {
    return `$${(value / 1e9).toFixed(1)}B`
  }
  if (value >= 1e6) {
    return `$${(value / 1e6).toFixed(1)}M`
  }
  if (value >= 1e3) {
    return `$${(value / 1e3).toFixed(1)}K`
  }
  return `$${value.toFixed(2)}`
}

export function formatNumber(value: number): string {
  if (value == null || isNaN(value)) {
    return '0'
  }
  
  if (value >= 1e6) {
    return `${(value / 1e6).toFixed(1)}M`
  }
  if (value >= 1e3) {
    return `${(value / 1e3).toFixed(1)}K`
  }
  return value.toLocaleString()
}

export function formatPercentage(value: number): string {
  return `${value >= 0 ? '+' : ''}${value.toFixed(1)}%`
}

export function getPremiumColor(premium: number): string {
  if (premium <= 0) return 'text-green-600 dark:text-green-400'
  if (premium <= 100) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-red-600 dark:text-red-400'
}