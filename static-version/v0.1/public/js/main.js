// Main application logic for homepage
document.addEventListener('DOMContentLoaded', async function() {
    // Initialize theme
    Utils.initTheme();

    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    try {
        // Fetch all data
        const data = await window.bitcoinAPI.fetchAllData();
        
        updateHomepage(data);

        // Start auto-updating data
        window.bitcoinAPI.startAutoUpdate((data) => {
            if (data) {
                updateHomepage(data);
            }
        }, 60000);

    } catch (error) {
        console.error('Error initializing homepage:', error);
        Utils.showError(error.message || 'Failed to load Bitcoin data from APIs');
        return; // Don't hide loading screen on error
    } finally {
        // Hide loading screen
        Utils.hideLoadingScreen();
    }
});

function updateHomepage(data) {
    if (!data) return;

    const { bitcoin, companies, etfs } = data;

    // Update Bitcoin price in navigation
    updateBitcoinPriceNav(bitcoin);

    // Update hero stats
    updateHeroStats(bitcoin, companies, etfs);

    // Update top companies section
    updateTopCompanies(companies);

    // Update top ETFs section
    updateTopETFs(etfs);
}

function updateBitcoinPriceNav(bitcoin) {
    Utils.updateElement('nav-btc-price', Utils.formatCurrency(bitcoin.usd));
    
    const changeElement = document.getElementById('nav-btc-change');
    if (changeElement) {
        changeElement.textContent = Utils.formatPercentage(bitcoin.usd_24h_change);
        changeElement.className = `btc-change ${bitcoin.usd_24h_change >= 0 ? 'positive' : 'negative'}`;
    }
}

function updateHeroStats(bitcoin, companies, etfs) {
    // Bitcoin price and change
    Utils.updateElement('btc-price-hero', Utils.formatCurrency(bitcoin.usd));
    
    const heroChangeElement = document.getElementById('btc-change-hero');
    if (heroChangeElement) {
        heroChangeElement.textContent = `${Utils.formatPercentage(bitcoin.usd_24h_change)} 24h`;
        heroChangeElement.className = `stat-change ${bitcoin.usd_24h_change >= 0 ? 'positive' : 'negative'}`;
    }

    // Company holdings
    const totalCompanyBTC = companies.reduce((sum, company) => sum + company.btcHeld, 0);
    const totalCompanyValue = totalCompanyBTC * bitcoin.usd;
    
    Utils.updateElement('total-company-btc', `${Utils.formatNumber(totalCompanyBTC)} BTC`);
    Utils.updateElement('company-value', `${Utils.formatCurrency(totalCompanyValue)} Value`);

    // ETF holdings
    const totalETFBTC = etfs.reduce((sum, etf) => sum + etf.btcHeld, 0);
    const totalETFValue = totalETFBTC * bitcoin.usd;
    
    Utils.updateElement('total-etf-btc', `${Utils.formatNumber(totalETFBTC)} BTC`);
    Utils.updateElement('etf-value', `${Utils.formatCurrency(totalETFValue)} AUM`);
}

function updateTopCompanies(companies) {
    const container = document.getElementById('top-companies');
    if (!container) return;

    // Sort by BTC holdings and take top 6
    const topCompanies = Utils.sortBy(companies.slice(), 'btcHeld', false).slice(0, 6);
    
    const cardsHTML = topCompanies.map(company => Utils.generateCompanyCard(company)).join('');
    container.innerHTML = cardsHTML;

    // Re-initialize icons after adding new content
    if (window.lucide) {
        window.lucide.createIcons();
    }
}

function updateTopETFs(etfs) {
    const container = document.getElementById('top-etfs');
    if (!container) return;

    // Sort by BTC holdings and take top 5
    const topETFs = Utils.sortBy(etfs.slice(), 'btcHeld', false).slice(0, 5);
    
    const cardsHTML = topETFs.map(etf => Utils.generateETFCard(etf)).join('');
    container.innerHTML = cardsHTML;

    // Re-initialize icons after adding new content
    if (window.lucide) {
        window.lucide.createIcons();
    }
}

// Cleanup when page unloads
window.addEventListener('beforeunload', function() {
    if (window.bitcoinAPI) {
        window.bitcoinAPI.stopAutoUpdate();
    }
});