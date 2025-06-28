// Utility functions for formatting and calculations

class Utils {
    // Format currency values with better handling of zero/missing values
    static formatCurrency(value, showZeroAs = 'N/A') {
        if (value == null || isNaN(value)) return showZeroAs;
        if (value === 0) return showZeroAs;

        if (value >= 1e12) {
            return `$${(value / 1e12).toFixed(1)}T`;
        }
        if (value >= 1e9) {
            return `$${(value / 1e9).toFixed(1)}B`;
        }
        if (value >= 1e6) {
            return `$${(value / 1e6).toFixed(1)}M`;
        }
        if (value >= 1e3) {
            return `$${(value / 1e3).toFixed(1)}K`;
        }
        return `$${value.toFixed(2)}`;
    }

    // Format numbers with appropriate suffixes and better zero handling
    static formatNumber(value, showZeroAs = '0') {
        if (value == null || isNaN(value)) return showZeroAs;
        if (value === 0) return showZeroAs;

        if (value >= 1e6) {
            return `${(value / 1e6).toFixed(1)}M`;
        }
        if (value >= 1e3) {
            return `${(value / 1e3).toFixed(1)}K`;
        }
        return value.toLocaleString();
    }

    // Format percentage values with better zero handling
    static formatPercentage(value, showZeroAs = '0.0%') {
        if (value == null || isNaN(value)) return showZeroAs;
        if (value === 0) return showZeroAs;
        return `${value >= 0 ? '+' : ''}${value.toFixed(1)}%`;
    }

    // Format Bitcoin amounts specifically
    static formatBTC(value, showZeroAs = '0 BTC') {
        if (value == null || isNaN(value)) return showZeroAs;
        if (value === 0) return showZeroAs;

        if (value >= 1e6) {
            return `${(value / 1e6).toFixed(1)}M BTC`;
        }
        if (value >= 1e3) {
            return `${(value / 1e3).toFixed(1)}K BTC`;
        }
        return `${value.toLocaleString()} BTC`;
    }

    // Smart value formatter that chooses best representation
    static formatValue(value, type = 'auto', fallback = 'N/A') {
        if (value == null || isNaN(value) || value === 0) {
            return fallback;
        }

        switch (type) {
            case 'currency':
                return Utils.formatCurrency(value, fallback);
            case 'percentage':
                return Utils.formatPercentage(value, fallback);
            case 'btc':
                return Utils.formatBTC(value, fallback);
            case 'number':
                return Utils.formatNumber(value, fallback);
            default:
                // Auto-detect based on value
                if (value > 1000000) {
                    return Utils.formatNumber(value, fallback);
                }
                return value.toLocaleString();
        }
    }

    // Get color class for premium values
    static getPremiumClass(premium) {
        if (premium <= 0) return 'negative';
        if (premium <= 50) return 'neutral';
        return 'positive';
    }

    // Update element content safely
    static updateElement(id, content) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = content;
        }
    }

    // Update element HTML safely
    static updateElementHTML(id, html) {
        const element = document.getElementById(id);
        if (element) {
            element.innerHTML = html;
        }
    }

    // Add CSS class to element
    static addClass(id, className) {
        const element = document.getElementById(id);
        if (element) {
            element.classList.add(className);
        }
    }

    // Remove CSS class from element
    static removeClass(id, className) {
        const element = document.getElementById(id);
        if (element) {
            element.classList.remove(className);
        }
    }

    // Hide loading screen
    static hideLoadingScreen() {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.classList.add('hidden');
            setTimeout(() => {
                loadingScreen.style.display = 'none';
            }, 300);
        }
    }

    // Show error message
    static showError(message, showRetryButton = true) {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            const content = loadingScreen.querySelector('.loading-content');
            if (content) {
                const retryButton = showRetryButton ? `
                    <button onclick="location.reload()" style="margin-top: 1rem; padding: 0.5rem 1rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer;">
                        Retry
                    </button>
                ` : '';

                content.innerHTML = `
                    <div class="loading-icon" style="border-top-color: var(--destructive);"></div>
                    <h2 style="color: var(--destructive);">Error Loading Data</h2>
                    <p>${message}</p>
                    ${retryButton}
                `;
            }
        }
    }

    // Show warning message (non-blocking)
    static showWarning(message) {
        // Create or update warning banner
        let warningBanner = document.getElementById('warning-banner');
        if (!warningBanner) {
            warningBanner = document.createElement('div');
            warningBanner.id = 'warning-banner';
            warningBanner.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #f59e0b;
                color: white;
                padding: 0.5rem 1rem;
                text-align: center;
                z-index: 1000;
                font-size: 0.875rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            `;
            document.body.appendChild(warningBanner);
        }

        warningBanner.innerHTML = `
            <span>⚠️ ${message}</span>
            <button onclick="this.parentElement.remove()" style="margin-left: 1rem; background: none; border: none; color: white; cursor: pointer; font-size: 1rem;">×</button>
        `;

        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (warningBanner && warningBanner.parentElement) {
                warningBanner.remove();
            }
        }, 10000);
    }

    // Show retry message
    static showRetryMessage(message) {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            const content = loadingScreen.querySelector('.loading-content');
            if (content) {
                content.innerHTML = `
                    <div class="loading-icon"></div>
                    <h2>Retrying...</h2>
                    <p>${message}</p>
                `;
            }
        }
    }

    // Show loading screen
    static showLoadingScreen() {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.style.display = 'flex';
            loadingScreen.classList.remove('hidden');
        }
    }

    // Theme management
    static initTheme() {
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        
        // Get saved theme or default to light
        let currentTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', currentTheme);
        
        // Update icon
        if (themeIcon && window.lucide) {
            themeIcon.setAttribute('data-lucide', currentTheme === 'dark' ? 'sun' : 'moon');
            window.lucide.createIcons();
        }

        // Theme toggle handler
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                currentTheme = currentTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', currentTheme);
                localStorage.setItem('theme', currentTheme);
                
                if (themeIcon && window.lucide) {
                    themeIcon.setAttribute('data-lucide', currentTheme === 'dark' ? 'sun' : 'moon');
                    window.lucide.createIcons();
                }
            });
        }
    }

    // Sort array by property
    static sortBy(array, property, ascending = false) {
        return array.sort((a, b) => {
            const aVal = a[property] || 0;
            const bVal = b[property] || 0;
            return ascending ? aVal - bVal : bVal - aVal;
        });
    }

    // Debounce function
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Generate card HTML for companies
    static generateCompanyCard(company) {
        const premiumClass = Utils.getPremiumClass(company.premium || 0);
        const hasStockPrice = company.stockPrice && company.stockPrice > 0;
        const hasPremium = company.premium != null && !isNaN(company.premium);

        return `
            <div class="card fade-in">
                <h3>${company.name || company.ticker}</h3>
                <p class="ticker">${company.ticker} <span class="company-rank">#${company.rank || '?'}</span></p>
                <div class="card-stats">
                    <div class="card-stat">
                        <span class="card-stat-label">BTC Holdings:</span>
                        <span class="card-stat-value">${Utils.formatBTC(company.btcHeld)}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Value:</span>
                        <span class="card-stat-value">${Utils.formatValue(company.btcValue, 'currency')}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Stock Price:</span>
                        <span class="card-stat-value">${hasStockPrice ? Utils.formatCurrency(company.stockPrice) : 'N/A'}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Premium:</span>
                        <span class="card-stat-value premium ${premiumClass}">
                            ${hasPremium ? Utils.formatPercentage(company.premium) : 'N/A'}
                        </span>
                    </div>
                </div>
                ${company.dataSource === 'EMERGENCY_HARDCODED' ? '<div class="card-warning">⚠️ Fallback data</div>' : ''}
            </div>
        `;
    }

    // Generate card HTML for ETFs
    static generateETFCard(etf) {
        const premiumClass = Utils.getPremiumClass(etf.premiumDiscount || etf.premium || 0);
        const hasPrice = etf.price && etf.price > 0;
        const hasBtcPerShare = etf.btcPerShare && etf.btcPerShare > 0;
        const hasPremium = (etf.premiumDiscount != null && !isNaN(etf.premiumDiscount)) ||
                          (etf.premium != null && !isNaN(etf.premium));
        const premium = etf.premiumDiscount || etf.premium || 0;

        return `
            <div class="card fade-in">
                <h3>${etf.name || etf.ticker}</h3>
                <p class="ticker">${etf.ticker} <span class="company-rank">#${etf.rank || '?'}</span></p>
                <div class="card-stats">
                    <div class="card-stat">
                        <span class="card-stat-label">BTC Holdings:</span>
                        <span class="card-stat-value">${Utils.formatBTC(etf.btcHeld)}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Price:</span>
                        <span class="card-stat-value">${hasPrice ? Utils.formatCurrency(etf.price) : 'N/A'}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">BTC/Share:</span>
                        <span class="card-stat-value">${hasBtcPerShare ? etf.btcPerShare.toFixed(6) : 'N/A'}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Premium:</span>
                        <span class="card-stat-value premium ${premiumClass}">
                            ${hasPremium ? Utils.formatPercentage(premium) : 'N/A'}
                        </span>
                    </div>
                </div>
                ${etf.dataSource === 'EMERGENCY_HARDCODED' ? '<div class="card-warning">⚠️ Fallback data</div>' : ''}
            </div>
        `;
    }
}

// Make Utils available globally
window.Utils = Utils;