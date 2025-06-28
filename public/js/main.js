// public/js/main.js

// Main application logic for homepage
document.addEventListener('DOMContentLoaded', async () => {
  // 1) Initialise theme & icons
  Utils.initTheme();
  if (window.lucide) window.lucide.createIcons();

  let retryCount = 0;
  const maxRetries = 3;

  async function initializeWithRetry() {
    try {
      // 2) Fetch & render everything once
      const data = await window.bitcoinAPI.fetchAllData();

      // Check if we got meaningful data
      if (!data.bitcoin || !data.companies || data.companies.length === 0) {
        throw new Error('Incomplete data received from APIs');
      }

      updateHomepage(data);

      // 3) Hide your loader now that first paint is done
      Utils.hideLoadingScreen();

      // 4) Then refresh every minute
      window.bitcoinAPI.startAutoUpdate(updateHomepage, 60000);

      // Show warnings if using fallback data
      if (data.bitcoin.stale || data.bitcoin.warning) {
        Utils.showWarning(data.bitcoin.warning || 'Using cached Bitcoin price data');
      }

    } catch (err) {
      console.error('Error initializing homepage:', err);
      retryCount++;

      if (retryCount < maxRetries) {
        console.log(`Retrying initialization (${retryCount}/${maxRetries})...`);
        Utils.showRetryMessage(`Loading failed, retrying... (${retryCount}/${maxRetries})`);

        // Wait before retry (exponential backoff)
        await new Promise(resolve => setTimeout(resolve, 2000 * retryCount));
        return initializeWithRetry();
      } else {
        // Final failure - show error with retry button
        Utils.showError(
          err.message || 'Failed to load Bitcoin data after multiple attempts',
          true // Show retry button
        );
      }
    }
  }

  // Start initialization
  await initializeWithRetry();
});

/**
 * Renders the entire homepage given fresh data.
 * @param {{ bitcoin: object, companies: object[], etfs: object[] }} data
 */
function updateHomepage({ bitcoin, companies, etfs }) {
  if (!bitcoin || !companies) return;
  updateBitcoinPriceNav(bitcoin);
  updateHeroStats(bitcoin, companies, etfs);
  updateTopCompanies(companies);
  updateTopETFs(etfs);
}

// ———————————————————————————————————————————————————————————————
// NAVIGATION BITCOIN PRICE
// ———————————————————————————————————————————————————————————————
function updateBitcoinPriceNav(bitcoin) {
  Utils.updateElement('nav-btc-price', Utils.formatCurrency(bitcoin.usd));
  const changeEl = document.getElementById('nav-btc-change');
  if (changeEl) {
    changeEl.textContent = Utils.formatPercentage(bitcoin.usd_24h_change);
    changeEl.className = `btc-change ${bitcoin.usd_24h_change >= 0 ? 'positive' : 'negative'}`;
  }
}

// ———————————————————————————————————————————————————————————————
// HERO STATS (BTC, TOTAL COMP/ETF)
// ———————————————————————————————————————————————————————————————
function updateHeroStats(bitcoin, companies, etfs) {
  // Bitcoin price + 24h
  Utils.updateElement('btc-price-hero', Utils.formatCurrency(bitcoin.usd));
  const heroChangeEl = document.getElementById('btc-change-hero');
  if (heroChangeEl) {
    heroChangeEl.textContent = `${Utils.formatPercentage(bitcoin.usd_24h_change)} 24h`;
    heroChangeEl.className = `stat-change ${bitcoin.usd_24h_change >= 0 ? 'positive' : 'negative'}`;
  }

  // Company holdings
  const totalCompanyBTC = companies.reduce((sum, c) => sum + (c.btcHeld || 0), 0);
  const totalCompanyValue = totalCompanyBTC * bitcoin.usd;
  Utils.updateElement('total-company-btc', `${Utils.formatNumber(totalCompanyBTC)} BTC`);
  Utils.updateElement('company-value', `${Utils.formatCurrency(totalCompanyValue)} Value`);

  // ETF holdings
  const totalETFBTC = etfs.reduce((sum, e) => sum + (e.btcHeld || 0), 0);
  const totalETFValue = totalETFBTC * bitcoin.usd;
  Utils.updateElement('total-etf-btc', `${Utils.formatNumber(totalETFBTC)} BTC`);
  Utils.updateElement('etf-value', `${Utils.formatCurrency(totalETFValue)} AUM`);
}

// ———————————————————————————————————————————————————————————————
// TOP COMPANIES CARDS
// ———————————————————————————————————————————————————————————————
function updateTopCompanies(companies) {
  const container = document.getElementById('top-companies');
  if (!container) return;

  // Sort by BTC held, descending, pick top 6
  const top = Utils.sortBy(companies.slice(), 'btcHeld', false).slice(0, 6);
  container.innerHTML = top.map(Utils.generateCompanyCard).join('');

  // Re-init icons for any new SVGs
  if (window.lucide) window.lucide.createIcons();
}

// ———————————————————————————————————————————————————————————————
// TOP ETFs CARDS
// ———————————————————————————————————————————————————————————————
function updateTopETFs(etfs) {
  const container = document.getElementById('top-etfs');
  if (!container) return;

  // Sort by BTC held, descending, pick top 5
  const top = Utils.sortBy(etfs.slice(), 'btcHeld', false).slice(0, 5);
  container.innerHTML = top.map(Utils.generateETFCard).join('');

  if (window.lucide) window.lucide.createIcons();
}

// ———————————————————————————————————————————————————————————————
// CLEAN UP ON PAGE UNLOAD
// ———————————————————————————————————————————————————————————————
window.addEventListener('beforeunload', () => {
  if (window.bitcoinAPI) {
    window.bitcoinAPI.stopAutoUpdate();
  }
});