# BitcoinBagger - Live Bitcoin Data Tracker

BitcoinBagger tracks Bitcoin proxy companies and ETFs with **real live data only** - no placeholders or fallback data.

## 🚀 Quick Start

1. **Get Free API Keys** (all have free tiers):
   - [TwelveData](https://twelvedata.com/pricing) - For stock prices
   - [Alpha Vantage](https://www.alphavantage.co/support/#api-key) - For company data (optional)
   - [FMP](https://financialmodelingprep.com/developer/docs) - For additional data (optional)

2. **Configure API Keys**:
   - Open `js/api.js`
   - Replace the placeholder API keys:
     ```javascript
     this.TWELVEDATA_KEY = 'your_actual_api_key_here';
     this.ALPHA_VANTAGE_KEY = 'your_actual_api_key_here';
     this.FMP_KEY = 'your_actual_api_key_here';
     ```

3. **Run the Application**:
   ```bash
   python3 -m http.server 8000
   ```
   Then open http://localhost:8000

## 📊 Data Sources

- **Bitcoin Price**: CoinGecko API (free, no key required)
- **Stock Prices**: TwelveData API (free tier: 800 requests/day)
- **Company Data**: Alpha Vantage API (free tier: 25 requests/day)
- **ETF Holdings**: bitbo.io API (free, may have CORS issues on localhost)

## 🔧 API Configuration

### TwelveData (Required for Stock Data)
- **Free Tier**: 800 API requests per day
- **Sign up**: https://twelvedata.com/pricing
- **Documentation**: https://twelvedata.com/docs

### Alpha Vantage (Optional)
- **Free Tier**: 25 API requests per day
- **Sign up**: https://www.alphavantage.co/support/#api-key
- **Documentation**: https://www.alphavantage.co/documentation/

### Financial Modeling Prep (Optional)
- **Free Tier**: 250 API requests per day
- **Sign up**: https://financialmodelingprep.com/developer/docs
- **Documentation**: https://financialmodelingprep.com/developer/docs

## 🚨 Important Notes

- **No Placeholder Data**: This application only shows real live data
- **API Limits**: Free tiers have daily request limits
- **CORS Issues**: Some APIs may not work on localhost due to CORS policies
- **Production Deployment**: Deploy to a proper domain for best results

## 🛠️ Troubleshooting

### "API Configuration Required" Error
- You need to set up API keys in `js/api.js`
- Make sure you're using actual API keys, not the placeholder text

### CORS Errors
- Normal for localhost development
- Deploy to a proper domain to resolve
- Some APIs work better with proper domains

### Rate Limiting
- Free API tiers have daily limits
- Monitor your usage to avoid hitting limits
- Consider upgrading to paid tiers for higher limits

## 📈 Features

- **Live Bitcoin Price**: Real-time Bitcoin price from CoinGecko
- **Company Holdings**: Track major Bitcoin holding companies (MSTR, MARA, etc.)
- **ETF Data**: Monitor Bitcoin ETF holdings and metrics
- **Auto-Updates**: Data refreshes automatically every minute
- **Responsive Design**: Works on desktop and mobile
- **Dark/Light Theme**: Toggle between themes

## 🔄 Data Updates

The application automatically fetches fresh data every 60 seconds when properly configured with API keys.

## 📝 License

This project is for educational and personal use. Please respect API terms of service and rate limits.
