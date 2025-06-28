# 🚀 BitcoinBagger V4 - Clean Static Deployment

## **✅ Repository Structure (Clean)**

```
bitcoinbagger/
├── index.html              # Main landing page
├── companies.html          # Companies tracker
├── etfs.html              # ETFs tracker  
├── styles.css             # All styling
├── api/
│   ├── treasuries.php     # Dynamic company discovery
│   ├── etf-holdings.php   # Dynamic ETF discovery
│   ├── btc-price.php      # Bitcoin price API
│   └── cache/             # API cache directory
├── public/js/             # JavaScript modules
├── .cpanel.yml           # cPanel deployment config
├── .gitignore            # Excludes _archive folder
└── _archive/             # Old Next.js version (not in Git)
```

## **🔧 cPanel Deployment**

### **1. Deploy via cPanel Git:**
1. Go to **Git Version Control** in cPanel
2. Click **"Pull or Deploy"** 
3. Select **"Deploy HEAD Commit"**
4. The `.cpanel.yml` automatically handles everything

### **2. Add API Keys:**
Edit `/home/bitcoinbagger/public_html/api/.env`:
```bash
COINGECKO_API_KEY=your_actual_coingecko_key
FMP_API_KEY=your_actual_fmp_key
ALPHA_VANTAGE_API_KEY=your_actual_alpha_vantage_key
TWELVEDATA_API_KEY=your_actual_twelvedata_key
```

### **3. Test APIs:**
- `https://bitcoinbagger.com/api/treasuries.php` → 30+ companies
- `https://bitcoinbagger.com/api/btc-price.php` → Live Bitcoin price
- `https://bitcoinbagger.com/api/etf-holdings.php` → Dynamic ETF discovery

## **📊 Features**

✅ **100% Dynamic Data Discovery**
- Companies discovered automatically from CoinGecko
- Zero hardcoded data anywhere in the system
- Smart caching: 24hr for companies, 1min for prices

✅ **Clean Repository**
- Old Next.js files moved to `_archive/` (excluded from Git)
- Only static site files in repository
- GitHub shows clean structure

✅ **Production Ready**
- Multiple API fallbacks for 99% uptime
- Secure `.env` file for API keys
- Optimized caching and compression

## **🎯 Current Status**

**✅ GitHub Clean**: Only static files, no Node.js clutter
**✅ Archive Safe**: Old version preserved in `_archive/`
**✅ cPanel Ready**: `.cpanel.yml` configured for deployment
**✅ 100% Dynamic**: Zero hardcoded data, all from live APIs

Ready for production deployment!
