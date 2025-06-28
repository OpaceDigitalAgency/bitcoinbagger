// BitcoinBagger API loader - SECURE VERSION (No API keys)
class BitcoinAPI {
  constructor() {
    this.bitcoinPrice = 0;
    this.bitcoinChange = 0;
    this.updateInterval = null;
    // SECURITY: No API keys in frontend - all calls go through backend
  }

  async fetchBitcoinPrice() {
    // SECURITY: Use backend API instead of direct external calls
    const res = await fetch('/api/btc-price.php?t=' + Date.now());
    if (!res.ok) throw new Error('Bitcoin price API failed');
    const response = await res.json();
    
    if (response.data) {
      this.bitcoinPrice = response.data.price;
      this.bitcoinChange = response.data.change_24h || 0;
      return {
        usd: response.data.price,
        usd_24h_change: response.data.change_24h || 0
      };
    }
    
    throw new Error('Invalid Bitcoin price response');
  }

  async fetchCompanyBitcoinHoldings() {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 45000); // 45 seconds for initial cache build
    
    try {
      const res = await fetch('/api/treasuries.php?t=' + Date.now(), {
        signal: controller.signal
      });
      clearTimeout(timeoutId);
      
      if (!res.ok) throw new Error('Treasuries API error');
      const response = await res.json();

      // Handle new API response format with metadata
      if (response.data && Array.isArray(response.data)) {
        console.log('Live treasuries data loaded:', response.meta);
        return response.data;
      }

      // Fallback for old format
      return Array.isArray(response) ? response : [];
    } catch (error) {
      clearTimeout(timeoutId);
      if (error.name === 'AbortError') {
        throw new Error('Request timeout - API building cache, please refresh in a moment...');
      }
      throw error;
    }
  }

  // REMOVED: fetchStockPrice - all stock data now comes from backend APIs
  // This prevents API key exposure and rate limit issues

  // REMOVED: fetchOverview - all company data now comes from backend APIs
  // This prevents API key exposure and rate limit issues

  async fetchCompaniesData() {
    const holdings = await this.fetchCompanyBitcoinHoldings();
    const btcPrice = this.bitcoinPrice || (await this.fetchBitcoinPrice()).usd;

    // Use the data from the backend API directly - now includes real stock prices
    const companies = holdings.map(h => {
      const btcValue = h.btcHeld * btcPrice;

      // Calculate premium/discount if we have stock price and Bitcoin per share
      let premium = 0;
      if (h.stockPrice > 0 && h.bitcoinPerShare > 0) {
        const impliedBtcPrice = h.stockPrice / h.bitcoinPerShare;
        premium = ((impliedBtcPrice - btcPrice) / btcPrice) * 100;
      }

      return {
        ticker: h.ticker,
        name: h.name,
        businessModel: h.businessModel,
        btcHeld: h.btcHeld,
        btcValue,
        stockPrice: h.stockPrice || 0,
        changePercent: this.bitcoinChange,
        sharesOutstanding: h.sharesOutstanding || 0,
        marketCap: h.marketCap || 0,
        bsp: h.bitcoinPerShare || 0, // Bitcoin per share from backend
        premium: premium, // Premium/discount calculation
        sector: h.sector || 'Technology',
        type: h.type || 'stock'
      };
    });

    companies.sort((a, b) => b.btcHeld - a.btcHeld);
    companies.forEach((x, i) => x.rank = i + 1);
    return companies;
  }

  // REMOVED: fetchNAV - all ETF data now comes from backend APIs
  // This prevents API key exposure and rate limit issues

  async fetchETFData() {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 45000); // 45 seconds for initial cache build
    
    try {
      // Load real-time ETF holdings from backend
      const res = await fetch('/api/etf-holdings.php?t=' + Date.now(), {
        signal: controller.signal
      });
      clearTimeout(timeoutId);
      
      if (!res.ok) throw new Error('ETF holdings API error');
      const response = await res.json();

      // Handle new API response format with metadata
      let etfs;
      if (response.data && Array.isArray(response.data)) {
        console.log('Live ETF data loaded:', response.meta);
        etfs = response.data;
      } else {
        // Fallback for old format
        etfs = Array.isArray(response) ? response : [];
      }

      // Ensure we have a current BTC price
      const btcPrice = this.bitcoinPrice || (await this.fetchBitcoinPrice()).usd;

      // Use the data from backend directly - avoid additional API calls that cause rate limits
      const enriched = etfs.map(e => {
        const btcPerShare = e.sharesOutstanding > 0 ? e.btcHeld / e.sharesOutstanding : 0;
        return {
          ...e,
          price: 0, // Will be populated by separate service if needed
          nav: 0, // Will be populated by separate service if needed
          btcPerShare,
          premium: 0 // Will be calculated when price/nav data available
        };
      });

      // Sort by BTC held
      enriched.sort((a, b) => b.btcHeld - a.btcHeld);
      enriched.forEach((x, i) => x.rank = i + 1);
      return enriched;
    } catch (error) {
      clearTimeout(timeoutId);
      if (error.name === 'AbortError') {
        throw new Error('Request timeout - ETF API building cache, please refresh in a moment...');
      }
      throw error;
    }
  }

  async fetchAllData() {
    const [bitcoin, companies, etfs] = await Promise.all([
      this.fetchBitcoinPrice(),
      this.fetchCompaniesData(),
      this.fetchETFData?.() || Promise.resolve([])
    ]);
    return { bitcoin, companies, etfs };
  }

  /**
   * Start auto-refresh every interval ms
   */
  startAutoUpdate(callback, interval = 60000) {
    if (this.updateInterval) clearInterval(this.updateInterval);
    this.updateInterval = setInterval(async () => {
      try {
        const data = await this.fetchAllData();
        callback(data);
      } catch (err) {
        console.error('Auto-update error:', err);
      }
    }, interval);
  }

  /** Stop auto-update loop */
  stopAutoUpdate() {
    if (this.updateInterval) {
      clearInterval(this.updateInterval);
      this.updateInterval = null;
    }
  }
}

window.bitcoinAPI = new BitcoinAPI();
