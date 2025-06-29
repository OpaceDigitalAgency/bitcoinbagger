// Companies page logic
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Companies.js loaded');
    
    // Initialize theme
    Utils.initTheme();

    // Initialize Lucide icons
    if (window.lucide) {
        window.lucide.createIcons();
    }

    try {
        console.log('Fetching companies data...');
        
        // Fetch all data (which includes companies)
        const data = await window.bitcoinAPI.fetchAllData();
        
        console.log('Companies data received:', data);

        if (data && data.companies) {
            updateCompaniesPage(data);
        } else {
            throw new Error('No companies data available');
        }

        // Start auto-updating data
        window.bitcoinAPI.startAutoUpdate(updateCompaniesPage, 60000);

    } catch (error) {
        console.error('Error loading companies page:', error);
        
        // Show specific error message
        let errorMessage = 'Failed to load companies data';
        
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

function updateCompaniesPage(data) {
    if (!data) return;

    const { bitcoin, companies } = data;

    // Update Bitcoin price in navigation
    if (bitcoin) {
        updateBitcoinPriceNav(bitcoin);
    }

    // Update overview stats
    if (bitcoin && companies && companies.length > 0) {
        updateOverviewStats(bitcoin, companies);
    }

    // Update companies table
    if (companies && companies.length > 0) {
        updateCompaniesTable(companies);
    }

    // Update companies legend timestamp
    updateCompaniesLegendTimestamp();
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

function updateOverviewStats(bitcoin, companies) {
    const totalBTC = companies.reduce((sum, company) => sum + (company.btcHeld || 0), 0);
    const totalValue = totalBTC * bitcoin.usd;

    Utils.updateElement('total-btc-held', `${Utils.formatNumber(totalBTC)} BTC`);
    Utils.updateElement('combined-value', Utils.formatCurrency(totalValue));
    Utils.updateElement('bitcoin-price', Utils.formatCurrency(bitcoin.usd));
}

function updateCompaniesTable(companies) {
    const tbody = document.querySelector('#companies-table tbody');
    if (!tbody) return;

    const sortedCompanies = Utils.sortBy(companies.slice(), 'btcHeld', false);
    
    const rowsHTML = sortedCompanies.map(company => `
        <tr>
            <td>
                <div class="company-info">
                    <strong>${company.name}</strong>
                    <span class="ticker">${company.ticker}</span>
                    <small class="business-model">${company.businessModel}</small>
                </div>
            </td>
            <td class="text-right">${Utils.formatNumber(company.btcHeld)} BTC</td>
            <td class="text-right">${Utils.formatCurrency(company.btcValue || 0)}</td>
            <td class="text-right">${Utils.formatCurrency(company.marketCap || 0)}</td>
            <td class="text-right">
                <div class="price-info">
                    <span>${Utils.formatCurrency(company.stockPrice || 0)}</span>
                    <small class="${(company.changePercent || 0) >= 0 ? 'positive' : 'negative'}">
                        ${Utils.formatPercentage(company.changePercent || 0)}
                    </small>
                </div>
            </td>
            <td class="text-right">${Utils.formatCurrency(company.bsp || 0)}</td>
            <td class="text-right">
                <span class="${(company.premium || 0) >= 0 ? 'positive' : 'negative'}">
                    ${Utils.formatPercentage(company.premium || 0)}
                </span>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = rowsHTML;
}

// Sort functionality
document.addEventListener('change', function(e) {
    if (e.target.id === 'sort-select') {
        const sortBy = e.target.value;
        // Re-fetch and sort data
        window.bitcoinAPI.fetchAllData().then(data => {
            if (data && data.companies) {
                updateCompaniesTable(data.companies);
            }
        });
    }

    // Initialize companies legend functionality
    initCompaniesLegend();
});

// Companies Legend functionality
function initCompaniesLegend() {
    const legendToggle = document.getElementById('companies-legend-toggle');
    const legendPanel = document.getElementById('companies-legend-panel');
    const legendClose = document.getElementById('companies-legend-close');

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

// Update last updated timestamp in companies legend
function updateCompaniesLegendTimestamp() {
    const lastUpdatedElement = document.getElementById('companies-last-updated');
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