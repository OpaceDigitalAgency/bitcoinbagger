# BitcoinBagger API Sources and Endpoints

This document details all data sources, API providers, and their fallback chains used in BitcoinBagger.

## 📊 **Current Implementation Status (December 2024)**

### ✅ **Fully Working APIs**
- **Bitcoin Price**: CoinGecko API ✅
- **ETF Holdings**: BitcoinETFData.com API ✅ (Major ETFs working)
- **ETF Prices**: Yahoo Finance + Alpha Vantage ✅
- **Stock Prices**: Yahoo Finance + Alpha Vantage ✅
- **Market Caps**: Multiple API sources ✅
- **Shares Outstanding**: Comprehensive API system ✅

### ⚠️ **Partial Coverage Issues**
- **ETF Holdings**: Some smaller ETFs (BTCO, BRRR, EZBC, DEFI, BTCW) showing 0 BTC
- **Company Data**: Some smaller companies missing market cap/BSP calculations
- **API Rate Limits**: Managed but occasional issues remain

## Data Categories and API Fallback Chains

### 1. Bitcoin Price Data
**Endpoint**: `/api/btc-price.php`

**Fallback Chain**:
1. **CoinGecko Pro API** (Primary)
   - Endpoint: `https://pro-api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true`
   - API Key: Required (`COINGECKO_API_KEY`)
   - Cache: 2 minutes

2. **CoinGecko Free API** (Fallback)
   - Endpoint: `https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true&include_market_cap=true`
   - API Key: None required
   - Cache: 2 minutes

**Data Fields**: `price`, `change_24h`, `market_cap`, `timestamp`

---

### 2. ETF Holdings and Prices
**Endpoint**: `/api/etf-holdings.php`

#### Bitcoin Holdings Data
**Fallback Chain**:
1. **BitcoinETFData.com** (Primary)
   - Endpoint: `https://btcetfdata.com/v1/current.json`
   - API Key: None required
   - Data: Bitcoin holdings only
   - Cache: 1 hour

#### ETF Price Data
**Fallback Chain**:
1. **Yahoo Finance Chart API** (Primary)
   - Endpoint: `https://query1.finance.yahoo.com/v8/finance/chart/{ticker}`
   - API Key: None required
   - Data: Current price

2. **Yahoo Finance Quote API** (Secondary)
   - Endpoint: `https://query1.finance.yahoo.com/v7/finance/quote?symbols={ticker}`
   - API Key: None required
   - Data: Price, shares outstanding, NAV

3. **Yahoo Finance Extended Quote API** (Tertiary)
   - Endpoint: `https://query1.finance.yahoo.com/v7/finance/quote?symbols={ticker}&fields=regularMarketPrice,sharesOutstanding,marketCap,navPrice`
   - API Key: None required
   - Data: Comprehensive ETF data

4. **Financial Modeling Prep** (Fallback)
   - Endpoint: `https://financialmodelingprep.com/api/v3/quote/{ticker}?apikey={key}`
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

## Data Completeness Status

✅ **Bitcoin Price**: Complete with real-time data  
✅ **ETF Bitcoin Holdings**: Complete from BitcoinETFData.com  
✅ **ETF Prices**: Complete from Yahoo Finance (filtered for valid prices)  
✅ **Company Bitcoin Holdings**: Complete from CoinGecko  
✅ **Stock Prices**: Complete from Yahoo Finance  
🔄 **Market Cap & Shares**: Improved with Yahoo Finance extended fields  
🔄 **Premium Calculations**: Will work once shares outstanding is reliable  

## Next Steps

1. Monitor Yahoo Finance data quality for market cap and shares outstanding
2. Implement backup calculations (marketCap = price × shares) when data is partial
3. Add data validation and error reporting for missing fields
4. Consider additional free APIs if Yahoo Finance proves unreliable
