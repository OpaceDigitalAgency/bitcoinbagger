/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    domains: ['api.coingecko.com'],
    unoptimized: true
  },
  env: {
    NEXT_PUBLIC_BASE_URL: process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000',
  },
  // Enable experimental features for better Netlify compatibility
  experimental: {
    serverComponentsExternalPackages: ['cheerio']
  },
  // Ensure proper trailing slash handling
  trailingSlash: false,
  // Netlify-specific configuration
  output: 'standalone'
}

module.exports = nextConfig