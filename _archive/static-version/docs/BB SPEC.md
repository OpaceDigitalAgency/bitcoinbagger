# BitcoinBagger – Full Product Specification

## A. Static-Stub MVP (for fast layout & QA)

> **Purpose:**  
> - Let designers and early QA see the live UI without configuring keys, rate limits, or CORS proxies.  
> - Uses embedded hard-coded data only in `fetchCompanyBitcoinHoldings()` and `fetchETFData()` fallback.  

### A.1 Mode Switch  
- Config flag at the top of `api.js`:  
  ```js
  const useDummyData = true;
  ```
  - **true** → serve stub data  
  - **false** → live-only mode  

### A.2 Stub Data Details  
- **Company Holdings** (10 tickers)  → static JSON in code  
- **ETF Data** → small sample array if proxy fails  
- **Bitcoin price** → always fetched from CoinGecko  

---

## B. Production Mode (zero dummy or fallback)

> **NON-NEGOTIABLE RULE**  
> 1. All figures **must** come from live API responses.  
> 2. Any endpoint error → show “Data unavailable” badge (no hard-coded or sample numbers).  
> 3. Hard-coded samples or PRs containing them will be rejected.  
> 4. Applies to previews, loading states, unit tests.

### B.1 Data Sources & Endpoints

| Purpose                  | API & endpoint                                                                                     |
|--------------------------|----------------------------------------------------------------------------------------------------|
| **Bitcoin price**        | CoinGecko `/simple/price?ids=bitcoin&vs_currencies=usd&include_24hr_change=true`                   |
| **Company holdings**     | **(to be implemented)** → should point at a trusted `/api/treasuries` endpoint                     |
| **Stock prices & P&L**   | TwelveData, Alpha Vantage, FMP (in that order) via a secure proxy `/api/proxy/:provider`           |
| **ETF holdings**         | Direct API (no CORS proxies) or a secure backend proxy, with immediate fail → badge on UI          |

### B.2 Fetching & Caching

- **Client-side only** (static HTML/JS/CSS)  
- Data fetched via `fetch()` + **SWR** with 1 min revalidation  
- Temporary in-memory cache; localStorage for offline reload  
- **Rate-limiting**: All APIs comply with free-tier quotas (no arbitrary delays)

### B.3 UI Behaviour

- **Loading screen** appears while the first minute’s data is arriving; hide as soon as any *critical* data (Bitcoin price or companies) returns.  
- **Partial data**: if ETFs fail, still show companies and Bitcoin price; surface an ETF-specific badge.  
- **Error state**: if Bitcoin *and* companies both fail, show a full-page “Data unavailable” message.

---

## C. Performance & First Paint

1. **Parallelise critical fetches**  
   ```js
   await Promise.all([
     api.fetchBitcoinPrice(),
     api.fetchCompaniesData()
   ]);
   ```
2. **Eliminate serial rate-limit delays** for initial load—batch calls where possible.  
3. **Show partial UI** (hero stats) as soon as Bitcoin price is in, before all company data arrives.

---

<small>Spec last updated 27 June 2025</small>
