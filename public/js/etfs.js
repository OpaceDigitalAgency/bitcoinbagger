// ETFs page logic
document.addEventListener('DOMContentLoaded', async function() {
    console.log('ETFs.js loaded');
    
    // Initialize theme
    Utils.initTheme();

    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    try {
        console.log('Fetching ETFs data...');
        
        // Fetch all data (which includes ETFs)
        const data = await window.bitcoinAPI.fetchAllData();
        
        console.log('ETFs data received:', data);

        if (data && data.etfs) {
            updateETFsPage(data);
        } else {
            throw new Error('No ETFs data available');
        }

        // Start auto-updating data
        window.bitcoinAPI.startAutoUpdate(updateETFsPage, 60000);

    } catch (error) {
        console.error('Error loading ETFs page:', error);
        
        // Show specific error message
        let errorMessage = 'Failed to load ETFs data';
        
        if (error.message.includes('CORS') || error.message.includes('Load failed')) {
            errorMessage = `
                <strong>Network/CORS Error</strong><br><br>
                Some APIs are blocked by CORS policy. This is normal for localhost development.<br><br>

                <strong>Solutions:</strong><br>
                • Deploy to a proper domain (recommended)<br>
                • Use a CORS proxy service<br>
                • Configure API keys for services that support CORS
            `;
        }
        
        Utils.showError(errorMessage);
        return;
    }

    // Hide loading screen only if successful
    Utils.hideLoadingScreen();
});

function updateETFsPage(data) {
    if (!data) return;

    const { bitcoin, etfs } = data;

    // Update Bitcoin price in navigation
    if (bitcoin) {
        updateBitcoinPriceNav(bitcoin);
    }

    // Update overview stats
    if (bitcoin && etfs && etfs.length > 0) {
        updateOverviewStats(bitcoin, etfs);
    }

    // Update ETFs table
    if (etfs && etfs.length > 0) {
        updateETFsTable(etfs);
    }

    // Update legend timestamp
    updateLegendTimestamp();
}

function updateBitcoinPriceNav(bitcoin) {
    if (!bitcoin || !bitcoin.usd) return;

    Utils.updateElement('nav-btc-price', Utils.formatCurrency(bitcoin.usd));

    const changeElement = document.getElementById('nav-btc-change');
    if (changeElement && bitcoin.usd_24h_change !== undefined) {
        changeElement.textContent = Utils.formatPercentage(bitcoin.usd_24h_change);
        changeElement.className = `btc-change ${bitcoin.usd_24h_change >= 0 ? 'positive' : 'negative'}`;
    }
}

function updateOverviewStats(bitcoin, etfs) {
    const totalBTC = etfs.reduce((sum, etf) => sum + (etf.btcHeld || 0), 0);
    const totalAUM = etfs.reduce((sum, etf) => sum + (etf.aum || 0), 0);
    const avgPremium = etfs.length > 0 ? etfs.reduce((sum, etf) => sum + (etf.premium || 0), 0) / etfs.length : 0;

    Utils.updateElement('total-etf-holdings', `${Utils.formatNumber(totalBTC)} BTC`);
    Utils.updateElement('combined-aum', Utils.formatCurrency(totalAUM));
    Utils.updateElement('average-premium', Utils.formatPercentage(avgPremium));
    Utils.updateElement('bitcoin-price-etf', Utils.formatCurrency(bitcoin.usd));
}

function updateETFsTable(etfs) {
    const tbody = document.querySelector('#etfs-table tbody');
    if (!tbody) return;

    const sortedETFs = Utils.sortBy(etfs.slice(), 'btcHeld', false);
    
    const rowsHTML = sortedETFs.map(etf => `
        <tr>
            <td>
                <div class="etf-info">
                    <strong>${etf.name}</strong>
                    <span class="ticker">${etf.ticker}</span>
                </div>
            </td>
            <td class="text-right">${Utils.formatNumber(etf.btcHeld)} BTC</td>
            <td class="text-right">
                ${etf.sharesOutstanding > 0 ? Utils.formatNumber(etf.sharesOutstanding) : 'N/A'}
            </td>
            <td class="text-right">
                ${etf.btcPerShare > 0 ? Utils.formatNumber(etf.btcPerShare, 6) : 'N/A'}
            </td>
            <td class="text-right">
                <div class="price-info">
                    <span>${etf.price > 0 ? Utils.formatCurrency(etf.price) : 'N/A'}</span>
                </div>
            </td>
            <td class="text-right">
                ${etf.nav > 0 ? Utils.formatCurrency(etf.nav) : 'N/A'}
            </td>
            <td class="text-right">
                ${etf.premium !== 0 ?
                    `<span class="${etf.premium >= 0 ? 'positive' : 'negative'}">
                        ${Utils.formatPercentage(etf.premium)}
                    </span>` : 'N/A'
                }
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = rowsHTML;
}

// Sort functionality
document.addEventListener('change', function(e) {
    if (e.target.id === 'etf-sort-select') {
        const sortBy = e.target.value;
        // Re-fetch and sort data
        window.bitcoinAPI.fetchAllData().then(data => {
            if (data && data.etfs) {
                updateETFsTable(data.etfs);
            }
        });
    }

    // Initialize legend functionality
    initLegend();
});

// Legend functionality
function initLegend() {
    const legendToggle = document.getElementById('legend-toggle');
    const legendPanel = document.getElementById('legend-panel');
    const legendClose = document.getElementById('legend-close');

    if (legendToggle && legendPanel && legendClose) {
        legendToggle.addEventListener('click', function() {
            legendPanel.classList.toggle('hidden');

            // Update icon
            const icon = legendToggle.querySelector('i');
            if (legendPanel.classList.contains('hidden')) {
                icon.setAttribute('data-lucide', 'help-circle');
            } else {
                icon.setAttribute('data-lucide', 'x');
            }

            // Reinitialize icons
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });

        legendClose.addEventListener('click', function() {
            legendPanel.classList.add('hidden');

            // Reset toggle icon
            const icon = legendToggle.querySelector('i');
            icon.setAttribute('data-lucide', 'help-circle');

            // Reinitialize icons
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    }
}

// Update last updated timestamp in legend
function updateLegendTimestamp() {
    const lastUpdatedElement = document.getElementById('last-updated');
    if (lastUpdatedElement) {
        const now = new Date();
        lastUpdatedElement.textContent = now.toLocaleString();
    }
}

// Cleanup when page unloads
window.addEventListener('beforeunload', function() {
    if (window.bitcoinAPI) {
        window.bitcoinAPI.stopAutoUpdate();
    }
});