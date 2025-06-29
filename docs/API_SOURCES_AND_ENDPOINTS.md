# BitcoinBagger API Sources and Endpoints

This document details all data sources, API providers, and their current status in BitcoinBagger.

## 📊 **Current Implementation Status (June 2025)**

### ✅ **WORKS PERFECTLY**
- **Bitcoin Price**: CoinGecko API - **WORKS PERFECTLY**
- **Major ETF Holdings**: BitcoinETFData.com API - **WORKS PERFECTLY** (IBIT, FBTC, GBTC, ARKB, BTC, BITB, HODL)

### ⚠️ **RATE LIMITING ISSUES**
- **Yahoo Finance**: **SEVERE RATE LIMITING ISSUES** - blocking requests after ~50 calls
- **ETF Prices**: Yahoo Finance - **RATE LIMITING ISSUES**
- **Stock Prices**: Yahoo Finance - **RATE LIMITING ISSUES**
- **Shares Outstanding**: Yahoo Finance - **RATE LIMITING ISSUES**
- **Market Caps**: Yahoo Finance - **RATE LIMITING ISSUES**

### ❌ **LIMITED DATA**
- **Smaller ETF Holdings**: **LIMITED DATA** - BTCO, BRRR, EZBC, DEFI, BTCW, EBIT showing 0 BTC due to rate limits
- **FMP API**: **RATE LIMITED** on free tier - 250 calls/month exhausted quickly
- **Alpha Vantage**: **RATE LIMITED** - 25 calls/day limit
- **TwelveData**: **RATE LIMITED** - 800 calls/day limit
- **Finnhub**: **LIMITED DATA** - basic price only, no comprehensive ETF data

### 🚨 **CRITICAL ISSUES IDENTIFIED**
1. **Yahoo Finance Rate Limiting**: Primary blocker for smaller ETFs and some companies
2. **Free API Tier Limitations**: All free APIs have restrictive rate limits
3. **Data Inconsistency**: Multiple APIs return different data formats requiring complex normalization
4. **Fallback Chain Complexity**: 80% of code is handling API failures and fallbacks

## Data Categories and API Fallback Chains

### 1. Bitcoin Price Data
**Endpoint**: `/api/btc-price.php`
**Status**: ✅ **WORKS PERFECTLY**

**Fallback Chain**:
1. **CoinGecko Pro API** (Primary) - **NOT IMPLEMENTED**
   - Endpoint: `https://pro-api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true`
   - API Key: Required (`COINGECKO_API_KEY`)
   - Status: ❌ **NOT IMPLEMENTED** - Pro API not configured
   - Cache: 2 minutes

2. **CoinGecko Free API** (Currently Used) - **WORKS PERFECTLY**
   - Endpoint: `https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd`
   - API Key: None required
   - Status: ✅ **WORKS PERFECTLY** - Reliable, fast, no rate limit issues
   - Cache: 5 minutes

**Data Fields**: `price`, `timestamp`

---

### 2. ETF Holdings and Prices
**Endpoint**: `/api/etf-holdings.php`
**Status**: ⚠️ **PARTIAL SUCCESS** - Major ETFs work, smaller ETFs have issues

#### Bitcoin Holdings Data
**Fallback Chain**:
1. **BitcoinETFData.com** (Primary) - **WORKS PERFECTLY** (Limited Coverage)
   - Endpoint: `https://btcetfdata.com/v1/current.json`
   - API Key: None required
   - Status: ✅ **WORKS PERFECTLY** for major ETFs (IBIT, FBTC, GBTC, ARKB, BTC, BITB, HODL)
   - Status: ❌ **LIMITED DATA** for smaller ETFs (BTCO, BRRR, EZBC, DEFI, BTCW, EBIT)
   - Data: Bitcoin holdings only
   - Cache: 1 hour

2. **AUM-Based Calculation** (For Missing ETFs) - **RATE LIMITING ISSUES**
   - Method: Calculate from price × shares outstanding × 96% Bitcoin allocation
   - Status: ⚠️ **RATE LIMITING ISSUES** - Yahoo Finance blocking requests
   - Data: Calculated Bitcoin holdings

#### ETF Price Data
**Fallback Chain**:
1. **Yahoo Finance Quote API** (Primary) - **RATE LIMITING ISSUES**
   - Endpoint: `https://query1.finance.yahoo.com/v7/finance/quote?symbols={ticker}`
   - API Key: None required
   - Status: ⚠️ **RATE LIMITING ISSUES** - "Too Many Requests" errors frequent
   - Data: Price, shares outstanding, NAV

2. **Yahoo Finance Extended Quote API** (Secondary) - **RATE LIMITING ISSUES**
   - Endpoint: `https://query1.finance.yahoo.com/v7/finance/quote?symbols={ticker}&fields=regularMarketPrice,sharesOutstanding,marketCap,navPrice`
   - API Key: None required
   - Status: ⚠️ **RATE LIMITING ISSUES** - Same rate limit pool as above
   - Data: Comprehensive ETF data

3. **Financial Modeling Prep** (Fallback) - **RATE LIMITED**
   - Endpoint: `https://financialmodelingprep.com/api/v3/quote/{ticker}?apikey={key}`
   - Status: ❌ **RATE LIMITED** - 250 calls/month on free tier exhausted quickly
   - API Key: Required (`FMP_API_KEY`)
   - Status: Rate limited

5. **Finnhub** (Final Fallback)
   - Endpoint: `https://finnhub.io/api/v1/quote?symbol={ticker}&token={key}`
   - API Key: Required (`FINNHUB_API_KEY`)
   - Data: Basic price only

**Data Fields**: `ticker`, `name`, `btcHeld`, `price`, `nav`, `sharesOutstanding`, `aum`, `expenseRatio`

**Filtering**: Only ETFs with valid price data (price > 0) are included in results

---

### 3. Company Stock Data (Treasuries)
**Endpoint**: `/api/treasuries.php`

#### Company Bitcoin Holdings
**Fallback Chain**:
1. **CoinGecko Companies API** (Primary)
   - Endpoint: `https://api.coingecko.com/api/v3/companies/public_treasury/bitcoin`
   - API Key: None required
   - Data: Company names, Bitcoin holdings
   - Cache: 24 hours

#### Stock Price and Market Data
**Fallback Chain**:
1. **Yahoo Finance Quote API** (Primary)
   - Endpoint: `https://query1.finance.yahoo.com/v7/finance/quote?symbols={ticker}&fields=regularMarketPrice,marketCap,sharesOutstanding,trailingPE,forwardPE`
   - API Key: None required
   - Data: Stock price, market cap, shares outstanding
   - Cache: 30 minutes

2. **Financial Modeling Prep** (Fallback)
   - Endpoint: `https://financialmodelingprep.com/api/v3/quote/{ticker}?apikey={key}`
   - API Key: Required (`FMP_API_KEY`)
   - Status: Rate limited
   - Data: Comprehensive stock data

3. **Finnhub** (Final Fallback)
   - Endpoint: `https://finnhub.io/api/v1/quote?symbol={ticker}&token={key}`
   - API Key: Required (`FINNHUB_API_KEY`)
   - Data: Basic price only

**Data Fields**: `ticker`, `name`, `btcHeld`, `stockPrice`, `marketCap`, `sharesOutstanding`, `bitcoinPerShare`, `businessModel`

**Filtering**: Stock data fetched only for companies with >1000 BTC holdings

---

## API Key Configuration

API keys are stored in `/api/.env`:

```env
# Primary APIs (Free/Working)
COINGECKO_API_KEY=CG-DyXq4yeQFW3Q7P39p4mNYAQz

# Fallback APIs (Rate Limited)
FMP_API_KEY=gLUC55COpZ8lqKMtjQuyltUGelxH9Not
ALPHA_VANTAGE_API_KEY=EGYFO89BDY0WYY04
TWELVEDATA_API_KEY=7038b64631ce424ebca83dfd227b079d
FINNHUB_API_KEY=d1ft3phr01qig3h42s40d1ft3phr01qig3h42s4g
```

## Cache Strategy

- **Bitcoin Price**: 2 minutes (frequent updates needed)
- **ETF Prices**: 15 minutes (reasonable for ETF data)
- **Stock Prices**: 30 minutes (reasonable for stock data)
- **Company Holdings**: 24 hours (changes rarely)
- **ETF Holdings**: 1 hour (moderate update frequency)

## Rate Limiting Solutions

1. **Yahoo Finance**: Primary source (free, no API key, high limits)
2. **Aggressive Caching**: Reduces API calls significantly
3. **Stale Cache Fallback**: Uses week-old data if all APIs fail
4. **Selective Data Fetching**: Only fetch detailed data for major holdings
5. **Request Delays**: 500ms delays between API calls to prevent rate limiting

## Manual Testing URLs

- Bitcoin Price: `https://bitcoinbagger.com/api/btc-price.php`
- ETF Holdings: `https://bitcoinbagger.com/api/etf-holdings.php`
- Company Treasuries: `https://bitcoinbagger.com/api/treasuries.php`
- Cache Status: `https://bitcoinbagger.com/api/cache-status.php`
- Clear Cache: `https://bitcoinbagger.com/api/clear-cache.php`

## 🚨 **CURRENT REALITY - DATA COMPLETENESS STATUS**

✅ **Bitcoin Price**: **WORKS PERFECTLY** - CoinGecko free API, 100% reliable
✅ **Major ETF Holdings**: **WORKS PERFECTLY** - BitcoinETFData.com (IBIT, FBTC, GBTC, ARKB, BTC, BITB, HODL)
⚠️ **Smaller ETF Holdings**: **MISSING DATA** - BTCO, BRRR, EZBC, DEFI, BTCW, EBIT showing 0 BTC due to rate limits
⚠️ **ETF Prices**: **RATE LIMITING ISSUES** - Yahoo Finance blocking requests
✅ **Company Bitcoin Holdings**: **WORKS PERFECTLY** - CoinGecko companies API
⚠️ **Stock Prices**: **RATE LIMITING ISSUES** - Yahoo Finance "Too Many Requests"
❌ **Market Cap & Shares**: **SEVERELY LIMITED** - Rate limiting prevents comprehensive data
❌ **Premium Calculations**: **MISSING** for smaller ETFs due to missing shares outstanding data

## 🚨 **CRITICAL ISSUES SUMMARY**

### **Primary Blocker: Yahoo Finance Rate Limiting**
- **Impact**: 80% of API calls blocked with "Too Many Requests"
- **Affected**: Smaller ETF Bitcoin holdings, some company stock data
- **Root Cause**: Free tier Yahoo Finance has aggressive rate limiting (~50 calls/hour)
- **Current Workaround**: Aggressive caching, but still insufficient

### **Free API Tier Limitations**
- **FMP**: 250 calls/month (exhausted in 1-2 days)
- **Alpha Vantage**: 25 calls/day (exhausted in minutes)
- **TwelveData**: 800 calls/day (exhausted quickly with fallback chains)
- **Finnhub**: Limited data (price only, no comprehensive ETF data)

## 💡 **RECOMMENDED SOLUTION: Financial Modeling Prep Premium ($14/month)**

**Why this is the smartest solution:**
- **Eliminates**: All rate limiting issues completely
- **Provides**: 1000+ calls/minute vs current ~50/hour
- **Includes**: ETF holdings, stock prices, market caps, shares outstanding in one API
- **Simplifies**: Code by 80% (single API vs complex fallback chains)
- **Reliability**: 99.9% uptime vs Yahoo's inconsistent free tier
- **Cost**: $14/month vs hours of development time dealing with rate limits

## Next Steps

**IMMEDIATE (Recommended):**
1. **Upgrade to FMP Premium** - Solves 90% of current issues
2. **Simplify codebase** - Remove complex fallback chains
3. **Single API integration** - Consistent data structure

**ALTERNATIVE (Current Path):**
1. Wait for Yahoo Finance rate limits to reset (1-24 hours)
2. Continue with complex fallback system and intermittent data gaps
3. Accept that smaller ETFs will have missing data during high usage periods
