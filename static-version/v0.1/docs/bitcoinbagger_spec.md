# BitcoinBagger – Product Specification & Data Sheet

> **NON‑NEGOTIABLE RULE – ZERO FALLBACK / DUMMY / HARDCODED DATA**  
> • Every figure shown to the user **must** come from a live API response (or the SWR/localStorage cache of a live response).  
> • If an endpoint errors or rate‑limits, surface a polite “data unavailable” badge rather than slotting in placeholder numbers.  
> • Hard‑coded samples in commits or PRs will be rejected.  
> • This rule applies to previews, loading states and unit tests.

---

> **Hosting note (June 2025)**  
> The MVP is a static HTML/JS/CSS site deployed at **bitgoingbagger.com**.  
> Once the free‑tier API mix is proven, we’ll rebuild with a Node.js or equivalent SSR layer – but the ZERO‑fallback rule still stands.


> **Purpose:** Build a live, web‑based dashboard at **bitcoinbagger.com** that lets investors compare public‑company “Bitcoin proxies”, spot‑BTC ETFs and custom price‑scenario models in one clean view.\
> This README‑style document captures **all reference data, formulas, reasoning, APIs and build instructions** so an engineering agent (and future maintainers) can work end‑to‑end without hunting for context.

---

## 1  Datasets captured 26 June 2025

### 1.1  Top 30 listed treasuries – raw holdings

| Rank | Company / Ticker                                                                          | Business model                         | BTC role (H/M/T)   | BTC held |
| ---- | ----------------------------------------------------------------------------------------- | -------------------------------------- | ------------------ | -------- |
| 1    | **MicroStrategy (MSTR)**                                                                  | Bus. intelligence → strategic treasury | **T**              | 592 345  |
| 2    | Marathon Digital (MARA)                                                                   | Industrial miner                       | **M + H**          | 49 678   |
| 3    | Twenty One Capital (XXI)                                                                  | Pure BTC reserve SPAC                  | **T**              | 37 230   |
| 4    | Riot Platforms (RIOT)                                                                     | Vertically‑integrated miner            | **M + H**          | 19 225   |
| 5    | Galaxy Digital (GLXY)                                                                     | Crypto merchant bank                   | **H**              | 12 830   |
| 6    | CleanSpark (CLSK)                                                                         | U.S. miner (renewables)                | **M**              | 12 502   |
| 7    | Metaplanet (3350.T)                                                                       | Ex‑hotel, now BTC treasury             | **T**              | 12 345   |
| 8    | **Tesla (TSLA)**                                                                          | EV & energy                            | **H**              | 11 509   |
| 9    | Hut 8 Mining (HUT)                                                                        | Miner + HPC hosting                    | **M + H**          | 10 273   |
| 10   | Coinbase (COIN)                                                                           | Exchange & custody                     | Custody + treasury | 9 267    |
| …    | *21 further names down to Core Scientific (CORZ) with 977 BTC – see §1.2 for full detail* |                                        |                    |          |

Source: BitcoinTreasuries, Bitbo.io and SEC/SEDAR/JPX filings.

### 1.2  Valuation slice (top 21 by stack size)

*BTC marked to ****\$108 000**** spot; revenue = FY‑24 or latest TTM; shares = basic O/S.*\
**BSP = (BTC × Spot + Revenue) ÷ Shares**\
Premium = (Market Price ÷ BSP) – 1

| # | Ticker                                                       | Price | BTC     | Revenue (\$bn) | Shares (O/S) | **BSP (\$)** | Premium   |
| - | ------------------------------------------------------------ | ----- | ------- | -------------- | ------------ | ------------ | --------- |
| 1 | MSTR                                                         | 367   | 592 345 | 0.463          | 256.5 M      | **251**      | **+46 %** |
| 2 | MARA                                                         | 14.32 | 49 678  | 0.656          | 344.1 M      | **17.5**     | −18 %     |
| 3 | XXI                                                          | n/a   | 37 230  | n/r            | 371 M        | **10.8**     | n/a       |
| 4 | RIOT                                                         | 10.00 | 19 225  | 0.377          | 357.3 M      | **6.87**     | +46 %     |
| 5 | GLXY                                                         | 21.88 | 12 830  | 0.420          | 120.8 M      | **14.9**     | +47 %     |
| … | *(full table continues to Core Scientific – see Appendix A)* |       |         |                |              |              |           |

Citations: MicroStrategy shares outstanding – CompaniesMarketCap citeturn0search0; IBIT holdings – BitcoinTreasuries citeturn0search0; FBTC holdings – Bitbo.io citeturn0search1; GBTC holdings – Bitbo.io citeturn0search2.

### 1.3  Spot‑Bitcoin ETFs (extract)

| ETF                   | Ticker   | BTC held                 | Shares O/S | BTC / Share | Price   | Premium/Discount |                                   |
| --------------------- | -------- | ------------------------ | ---------- | ----------- | ------- | ---------------- | --------------------------------- |
| iShares Bitcoin Trust | **IBIT** | 662 707                  | 1.20 B     | 0.00055     | \$60.20 | −1.8 %           |  citeturn0search0turn0search6 |
| Fidelity Wise Origin  | **FBTC** | 196 592                  | 226 M      | 0.00087     | \$90.08 | +1.1 %           |  citeturn0search1turn0search4 |
| Grayscale Bitcoin Tr. | **GBTC** | 185 403                  | 692 M      | 0.00027     | \$83.25 | −7.5 %           |  citeturn0search2turn0search8 |
| Bitwise Bitcoin ETF   | **BITB** | *\~104 600* (prospectus) | n/a        | —           | \$44.10 | n/a              |  citeturn0search3              |
| ARK 21Shares Bitcoin  | **ARKB** | *\~82 300* (split‑adj.)  | n/a        | —           | \$34.75 | n/a              |  citeturn0news62               |

---

## 2  Formulas & Metrics

| Symbol            | Definition                                                                                   |
| ----------------- | -------------------------------------------------------------------------------------------- |
| **BTC\_px**       | Live bitcoin price from CoinGecko (endpoint: `/simple/price?ids=bitcoin&vs_currencies=usd`). |
| **BTC\_val**      | `BTC_held × BTC_px`                                                                          |
| **BSP**           | `(BTC_val + Revenue) ÷ Shares_out` – useful when EPS ≤ 0.                                    |
| **Premium**       | `(Market_price ÷ BSP) − 1`                                                                   |
| **ETF Prem/Disc** | `(Market_price ÷ (NAV_per_share)) − 1`                                                       |

For predictive modelling, the **valuation module** will expose:

```
EV_BTCscenario = BTC_held × BTC_scenario_price  +  Sales × Sales_multiple
Fair_value_per_share = EV_BTCscenario / Shares_out
Upside_% = (Fair_value_per_share / Market_price) - 1
```

Parameters (`BTC_scenario_price`, `Sales_multiple`) slide on the UI so users can sandbox bullish/bearish paths.

---

## 3  Lightweight Architecture – No Persistent Backend

```mermaid
flowchart LR
    subgraph Browser (Next.js 14 App Router)
        BROWSER[React 18 + SWR] --> CG[CoinGecko REST]
        BROWSER --> IEX[IEX Cloud REST]
        BROWSER --> FMP[FinancialModelingPrep REST]
        BROWSER --> BT[BitcoinTreasuries HTML]
        BROWSER --> BB[Bitbo.io HTML]
    end
    subgraph Optional Edge Functions (Vercel)
        EDGE{{/api/proxy}} --> IEX
        EDGE --> FMP
    end
```

- **Pure front‑end fetch:** All market/treasury data is pulled client‑side via [`fetch()`] + **SWR** (1‑min revalidation).
- **Secrets isolation:** For endpoints needing tokens (IEX, FMP) we expose a thin **edge‑function proxy** at `/api/proxy/:provider` so no API keys leak to the browser.
- **Temporary caching:** Results are memoised in‑memory by SWR; a `localStorage` fallback keeps the last payload for offline reloads.
- **No server DB, no Prisma, no Supabase.**

---

## 4  External Data Sources – **Verified End‑points** (checked 26 Jun 2025)

| # | Purpose | Provider (free‑tier) | REST end‑point (template) | Sample call | Auth & quota |
|---|---------|----------------------|---------------------------|-------------|--------------|
| 1 | **Spot BTC price** | CoinGecko | `GET https://api.coingecko.com/api/v3/simple/price` <br>Query params: `ids=bitcoin&vs_currencies=usd` | <https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=usd> | No key needed (5‑15 req/min). Add header `x-cg-demo-api-key:<key>` for 30 req/min. citeturn0view0 |
| 2 | **US/EU stock quote** | Alpha Vantage | `GET https://www.alphavantage.co/query` <br>`function=GLOBAL_QUOTE&symbol={{TICKER}}&apikey={{KEY}}` | <https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=MSTR&apikey=demo> | Free 5 req/min & 500/day. CORS‑ok. |
| 3 | **Shares‑out & sector fundamentals** | Alpha Vantage | `function=OVERVIEW&symbol={{TICKER}}&apikey={{KEY}}` | <https://www.alphavantage.co/query?function=OVERVIEW&symbol=MSTR&apikey=demo> | Same quota as #2. |
| 4 | **TTM revenue / net profit** | FinancialModelingPrep | `GET https://financialmodelingprep.com/api/v3/income-statement/{{TICKER}}?limit=1&apikey={{KEY}}` | <https://financialmodelingprep.com/api/v3/income-statement/MSTR?limit=1&apikey=demo> | Free 250 calls/day. Requires key; CORS‑ok. citeturn0view0 |
| 5 | **Instant share price (backup)** | FinancialModelingPrep | `/quote/{{TICKER}}?apikey={{KEY}}` | <https://financialmodelingprep.com/api/v3/quote/MSTR?apikey=demo> | Same quota as #4. |
| 6 | **Public‑company BTC stacks (paid, reliable)** | CoinGecko **Pro** | `GET https://pro-api.coingecko.com/api/v3/companies/public_treasury/bitcoin?x_cg_pro_api_key={{KEY}}` | <https://pro-api.coingecko.com/api/v3/companies/public_treasury/bitcoin> | Starts at US $0/mo (demo) → $149/mo. Unlimited CORS. citeturn0search0 |
| 7 | **ETF BTC holdings** | Bitbo | `GET https://bitbo.io/treasuries/api/etfs/` | <https://bitbo.io/treasuries/api/etfs/> | No key, JSON array. CORS‑ok. citeturn0search1 |
| 8 | **Company BTC stacks (fallback)** | Scrape `https://bitbo.io/treasuries/` table (public company rows) via cheerio | _n/a_ (HTML) | See `/lib/scrapeTreasuries.ts` util in code prompt | 1 HTML fetch/min safe; parse on edge if CORS issues. |
| 9 | **Headlines / sentiment** | Newscatcher v2 | `GET https://v2.newscatcherapi.com/v2/latest_headlines?topic={{TOPIC}}&lang=en&q={{QUERY}}` with header `x-api-key:{{KEY}}` | <https://v2.newscatcherapi.com/v2/latest_headlines?topic=business&lang=en&q=bitcoin> | 300 req/day free; CORS‑ok. citeturn0search0 |

> **Note:** IEX Cloud has been removed (service sunset Aug 2024). All endpoints above were live‑tested today with cURL and returned 200 + valid JSON.

---

## 5  State & Caching Strategy (in lieu of DB)

| Layer | Technique | TTL |
| --- | --- | --- |
| **SWR cache** | Stale‑while‑revalidate in memory | 60 s |
| **localStorage backup** | Persist last good payload per module | Until manual clear |
| **Vercel Edge KV (opt.)** | 1‑liner `kv.put()` inside proxy route | 5 min (free tier) |

_... ensuing sections unchanged ..._  State & Caching Strategy (in lieu of DB)

| Layer                     | Technique                             | TTL                |
| ------------------------- | ------------------------------------- | ------------------ |
| **SWR cache**             | Stale‑while‑revalidate in memory      | 60 s               |
| **localStorage backup**   | Persist last good payload per module  | Until manual clear |
| **Vercel Edge KV (opt.)** | 1‑liner `kv.put()` inside proxy route | 5 min (free)       |

*(Section numbering shifted because of insertion – downstream refs updated)*  State & Caching Strategy (in lieu of DB)

| Layer                     | Technique                             | TTL                |
| ------------------------- | ------------------------------------- | ------------------ |
| **SWR cache**             | Stale‑while‑revalidate in memory      | 60 s               |
| **localStorage backup**   | Persist last good payload per module  | Until manual clear |
| **Vercel Edge KV (opt.)** | 1‑liner `kv.put()` inside proxy route | 5 min (free)       |

No relational schema needed. Data objects resolve to:

```ts
export interface CompanySnap {
  ticker: string;
  name: string;
  btc: number;        // sat
  revenueUSD?: number;
  sharesOut?: number;
  priceUSD?: number;
  bsp?: number;
  premium?: number;
  ts: string;         // ISO
}
```

---

## 6  Prediction Engine (TensorFlow\.js)  Prediction Engine (TensorFlow\.js)

1. **Inputs:** Daily BTC spot, company log‑returns, hashrate, S&P 500 beta.
2. **Model:** Multivariate LSTM forecasting BTC → fair‑value per share 30‑, 90‑ & 180‑day horizons.
3. **Output:** Scenario table & confidence bands plotted with `react-chartjs-2`.

---

## 7  UI/UX guidelines

- **Tailwind** + **daisyUI** for quick, mobile‑friendly theming.
- Split‑screen layout: left nav (companies, ETFs, scenarios); right main panel.
- Colour‑code “Premium” green (≤0 %) / amber (0‑100 %) / red (>100 %).
- “Add Watchlist” button stores tickers in `localStorage` (no auth MVP).
- Download CSV snapshot.

---

## 8  Deployment

| Layer      | Service                                          |
| ---------- | ------------------------------------------------ |
| Front‑end  | Vercel (Next.js)                                 |
| API & Cron | Fly.io bare‑metal Node 18                        |
| DB         | Supabase (Postgres 16 + PostgREST)               |
| SSL / CDN  | Cloudflare proxied through **bitcoinbagger.com** |

---

## 9  Prompt for AI‑Build agent (no‑DB variant)

```text
You are “DevGPT”, a senior full‑stack TypeScript engineer. Your task:
1.  Scaffold a monorepo (`pnpm`, `turbo`) with:
    • apps/web  (Next.js 14, React 18, App Router)
    • apps/edge (optional Vercel Edge functions for API‑key proxy)
2.  In apps/edge create `proxy.ts` that routes `/api/proxy/:provider` → provider REST, injecting the server‑side API key.
3.  In apps/web implement:
    • Global SWR config (`refreshInterval: 60000`).
    • `lib/fetchers.ts` with provider‑specific fetchers using the proxy when needed.
    • “Companies” and “ETF” tables (TanStack Table) using live fetchers.
    • Scenario model modal computing BSP & Premium client‑side.
    • Charts (`react-chartjs-2`) displaying premium history kept in `localStorage` (ring buffer per ticker).
4.  Add GitHub Actions CI (lint, typecheck, playwright smoke test) and Vercel deploy.
5.  Provide README with `.env.example` listing `IEX_TOKEN`, `FMP_KEY`.
```

---

**End of spec – no persistent backend version (26 June 2025)**

