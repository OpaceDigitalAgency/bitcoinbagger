/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    domains: ['api.coingecko.com'],
  },
  env: {
    NEXT_PUBLIC_BASE_URL: process.env.NEXT_PUBLIC_BASE_URL || 'http://localhost:3000',
  },
}

module.exports = nextConfig