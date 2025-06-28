// BitcoinBagger API loader - SECURE VERSION (No API keys)
class BitcoinAPI {
  constructor() {
    this.bitcoinPrice = 0;
    this.bitcoinChange = 0;
    this.updateInterval = null;
    // SECURITY: No API keys in frontend - all calls go through backend
  }

  async fetchBitcoinPrice() {
    try {
      const res = await fetch('/api/btc-price.php?t=' + Date.now());

      if (!res.ok) {
        throw new Error(`Bitcoin price API returned ${res.status}: ${res.statusText}`);
      }

      const response = await res.json();

      // Handle both success and fallback responses
      if (response.success && response.data) {
        this.bitcoinPrice = response.data.price;
        this.bitcoinChange = response.data.change_24h || 0;

        // Log warnings for stale or fallback data
        if (response.data.stale || response.data.emergency_fallback) {
          console.warn('Bitcoin price warning:', response.data.warning || 'Using cached/fallback data');
        }

        return {
          usd: response.data.price,
          usd_24h_change: response.data.change_24h || 0,
          source: response.data.source || 'Unknown',
          stale: response.data.stale || false
        };
      }

      // Handle fallback data in error responses
      if (!response.success && response.fallback_data) {
        console.warn('Using emergency Bitcoin price fallback:', response.error);
        this.bitcoinPrice = response.fallback_data.price;
        this.bitcoinChange = 0;

        return {
          usd: response.fallback_data.price,
          usd_24h_change: 0,
          source: 'EMERGENCY_FALLBACK',
          stale: true,
          warning: response.fallback_data.warning
        };
      }

      throw new Error(response.error || 'Invalid Bitcoin price response');

    } catch (error) {
      console.error('Bitcoin price fetch error:', error);

      // Return last known price if available
      if (this.bitcoinPrice > 0) {
        console.warn('Using last known Bitcoin price:', this.bitcoinPrice);
        return {
          usd: this.bitcoinPrice,
          usd_24h_change: this.bitcoinChange,
          source: 'LAST_KNOWN',
          stale: true,
          warning: 'Using last known price - API unavailable'
        };
      }

      // Absolute fallback
      const fallbackPrice = 100000;
      this.bitcoinPrice = fallbackPrice;
      this.bitcoinChange = 0;

      return {
        usd: fallbackPrice,
        usd_24h_change: 0,
        source: 'HARDCODED_FALLBACK',
        stale: true,
        warning: 'All price sources failed - using conservative estimate'
      };
    }
  }

  async fetchCompanyBitcoinHoldings() {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 45000); // 45 seconds for initial cache build

    try {
      const res = await fetch('/api/treasuries.php?t=' + Date.now(), {
        signal: controller.signal
      });
      clearTimeout(timeoutId);

      if (!res.ok) {
        throw new Error(`Treasuries API returned ${res.status}: ${res.statusText}`);
      }

      const response = await res.json();

      // Handle successful response
      if (response.success && response.data && Array.isArray(response.data)) {
        console.log('Live treasuries data loaded:', response.meta);

        // Log warnings for stale or cached data
        if (response.meta.source === 'STALE_CACHE_FALLBACK') {
          console.warn('Using stale treasury data:', response.meta.warning);
        }

        return response.data;
      }

      // Handle fallback data in error responses
      if (!response.success && response.fallback_data && Array.isArray(response.fallback_data)) {
        console.warn('Using emergency treasury fallback:', response.error);
        return response.fallback_data;
      }

      // Handle legacy format (for backward compatibility)
      if (Array.isArray(response)) {
        console.log('Legacy treasury data format detected');
        return response;
      }

      throw new Error(response.error || 'Invalid treasuries response format');

    } catch (error) {
      clearTimeout(timeoutId);

      if (error.name === 'AbortError') {
        throw new Error('Request timeout - API building cache, please refresh in a moment...');
      }

      console.error('Treasury data fetch error:', error);

      // Return minimal fallback data for critical companies
      console.warn('Using hardcoded treasury fallback data');
      return [
        {
          ticker: 'MSTR',
          name: 'MicroStrategy Inc.',
          btcHeld: 190000,
          businessModel: 'Business Intelligence & Bitcoin Treasury',
          type: 'stock',
          stockPrice: 0,
          marketCap: 0,
          sharesOutstanding: 0,
          bitcoinPerShare: 0,
          dataSource: 'EMERGENCY_HARDCODED'
        },
        {
          ticker: 'TSLA',
          name: 'Tesla Inc.',
          btcHeld: 9720,
          businessModel: 'Electric Vehicles & Energy Storage',
          type: 'stock',
          stockPrice: 0,
          marketCap: 0,
          sharesOutstanding: 0,
          bitcoinPerShare: 0,
          dataSource: 'EMERGENCY_HARDCODED'
        }
      ];
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

      if (!res.ok) {
        throw new Error(`ETF holdings API returned ${res.status}: ${res.statusText}`);
      }

      const response = await res.json();

      // Handle successful response
      let etfs = [];
      if (response.success && response.data && Array.isArray(response.data)) {
        console.log('Live ETF data loaded:', response.meta);
        etfs = response.data;

        // Log warnings for stale or cached data
        if (response.meta.source === 'STALE_CACHE_FALLBACK') {
          console.warn('Using stale ETF data:', response.meta.warning);
        }
      } else if (Array.isArray(response)) {
        // Handle legacy format
        console.log('Legacy ETF data format detected');
        etfs = response;
      } else {
        console.warn('No ETF data available, using empty array');
        etfs = [];
      }

      // Ensure we have a current BTC price
      const btcPrice = this.bitcoinPrice || (await this.fetchBitcoinPrice()).usd;

      // Enrich ETF data with calculated fields
      const enriched = etfs.map(e => {
        const btcPerShare = (e.sharesOutstanding && e.sharesOutstanding > 0) ?
          e.btcHeld / e.sharesOutstanding : 0;

        // Calculate premium/discount if we have price and nav data
        let premium = 0;
        if (e.price > 0 && e.nav > 0) {
          premium = ((e.price - e.nav) / e.nav) * 100;
        }

        return {
          ...e,
          price: e.price || 0,
          nav: e.nav || 0,
          btcPerShare,
          premium,
          premiumDiscount: premium // Alias for compatibility
        };
      });

      // Sort by BTC held (descending)
      enriched.sort((a, b) => (b.btcHeld || 0) - (a.btcHeld || 0));
      enriched.forEach((x, i) => x.rank = i + 1);

      return enriched;

    } catch (error) {
      clearTimeout(timeoutId);

      if (error.name === 'AbortError') {
        throw new Error('Request timeout - ETF API building cache, please refresh in a moment...');
      }

      console.error('ETF data fetch error:', error);

      // Return minimal fallback ETF data
      console.warn('Using hardcoded ETF fallback data');
      const fallbackETFs = [
        {
          ticker: 'IBIT',
          name: 'iShares Bitcoin Trust',
          btcHeld: 630000,
          price: 0,
          nav: 0,
          btcPerShare: 0,
          premium: 0,
          premiumDiscount: 0,
          rank: 1,
          dataSource: 'EMERGENCY_HARDCODED'
        },
        {
          ticker: 'FBTC',
          name: 'Fidelity Wise Origin Bitcoin Fund',
          btcHeld: 180000,
          price: 0,
          nav: 0,
          btcPerShare: 0,
          premium: 0,
          premiumDiscount: 0,
          rank: 2,
          dataSource: 'EMERGENCY_HARDCODED'
        },
        {
          ticker: 'GBTC',
          name: 'Grayscale Bitcoin Trust',
          btcHeld: 220000,
          price: 0,
          nav: 0,
          btcPerShare: 0,
          premium: 0,
          premiumDiscount: 0,
          rank: 3,
          dataSource: 'EMERGENCY_HARDCODED'
        }
      ];

      return fallbackETFs;
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
