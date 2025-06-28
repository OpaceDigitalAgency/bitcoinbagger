// Utility functions for formatting and calculations

class Utils {
    // Format currency values
    static formatCurrency(value) {
        if (value == null || isNaN(value)) return '$0.00';
        
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

    // Format numbers with appropriate suffixes
    static formatNumber(value) {
        if (value == null || isNaN(value)) return '0';
        
        if (value >= 1e6) {
            return `${(value / 1e6).toFixed(1)}M`;
        }
        if (value >= 1e3) {
            return `${(value / 1e3).toFixed(1)}K`;
        }
        return value.toLocaleString();
    }

    // Format percentage values
    static formatPercentage(value) {
        if (value == null || isNaN(value)) return '0.0%';
        return `${value >= 0 ? '+' : ''}${value.toFixed(1)}%`;
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
    static showError(message) {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            const content = loadingScreen.querySelector('.loading-content');
            if (content) {
                content.innerHTML = `
                    <div class="loading-icon" style="border-top-color: var(--destructive);"></div>
                    <h2 style="color: var(--destructive);">Error Loading Data</h2>
                    <p>${message}</p>
                    <button onclick="location.reload()" style="margin-top: 1rem; padding: 0.5rem 1rem; background: var(--primary); color: white; border: none; border-radius: 0.5rem; cursor: pointer;">
                        Retry
                    </button>
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
        const premiumClass = this.getPremiumClass(company.premium);
        return `
            <div class="card fade-in">
                <h3>${company.name}</h3>
                <p class="ticker">${company.ticker} <span class="company-rank">#${company.rank}</span></p>
                <div class="card-stats">
                    <div class="card-stat">
                        <span class="card-stat-label">BTC Holdings:</span>
                        <span class="card-stat-value">${this.formatNumber(company.btcHeld)} BTC</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Value:</span>
                        <span class="card-stat-value">${this.formatCurrency(company.btcValue)}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Stock Price:</span>
                        <span class="card-stat-value">${this.formatCurrency(company.stockPrice)}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Premium:</span>
                        <span class="card-stat-value premium ${premiumClass}">${this.formatPercentage(company.premium)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Generate card HTML for ETFs
    static generateETFCard(etf) {
        const premiumClass = this.getPremiumClass(etf.premiumDiscount);
        return `
            <div class="card fade-in">
                <h3>${etf.name}</h3>
                <p class="ticker">${etf.ticker} <span class="company-rank">#${etf.rank}</span></p>
                <div class="card-stats">
                    <div class="card-stat">
                        <span class="card-stat-label">BTC Holdings:</span>
                        <span class="card-stat-value">${this.formatNumber(etf.btcHeld)} BTC</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Price:</span>
                        <span class="card-stat-value">${this.formatCurrency(etf.price)}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">BTC/Share:</span>
                        <span class="card-stat-value">${etf.btcPerShare?.toFixed(6) || 'N/A'}</span>
                    </div>
                    <div class="card-stat">
                        <span class="card-stat-label">Premium:</span>
                        <span class="card-stat-value premium ${premiumClass}">${this.formatPercentage(etf.premiumDiscount)}</span>
                    </div>
                </div>
            </div>
        `;
    }
}

// Make Utils available globally
window.Utils = Utils;