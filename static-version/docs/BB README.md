# BitcoinBagger – Static-Stub & Live Data Tracker

**This repo contains two modes**:

1. **Static-stub (default)**  
   A fully static HTML/JS/CSS site that uses embedded dummy data for rapid UI iteration.  
2. **Live mode**  
   Fetches real APIs only—no hardcoded data or fallbacks allowed.

---

## 🚀 Quick Start

1. **Clone & open** `index.html`.  
2. By default you’re in **Static-stub** mode (no API keys needed).  
3. To switch into **Live** mode:
   - Open `public/js/api.js`
   - Replace the three `API_KEY` constants with your own keys:
     ```js
     this.TWELVEDATA_KEY = '<YOUR_TWELVEDATA_KEY>';
     this.ALPHA_VANTAGE_KEY = '<YOUR_API_KEY>';
     this.FMP_KEY         = '<YOUR_API_KEY>';
     ```
   - Ensure `useDummyData = false;` (see config at top of the file).

---

## 📄 README Structure

- **Static-stub mode**  
  - Uses built-in JSON for company holdings and ETF examples  
  - No real network calls except CoinGecko price (optional)  
  - Perfect for layout, styling, QA without rate limits  
- **Live mode**  
  - **ZERO dummy data**—every number must come from a live API or an SWR/localStorage cache  
  - On any fetch error or rate-limit, surfaces a “Data unavailable” badge  
  - Auto-refreshes every 60 s  

---

## 📝 License

For educational and internal use only. Respect each API’s rate limits.
