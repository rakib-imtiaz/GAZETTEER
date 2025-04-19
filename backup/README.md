# Gazetteer - Explore the World

Gazetteer is a "mobile first" website that provides detailed profiling for all countries through the presentation of demographic, climatic, geographical, and other data.

## Features

- Interactive map with country borders
- Automatic geolocation to detect user's country
- Detailed country information:
  - Demographics (population, languages, etc.)
  - Geography (region, subregion, etc.)
  - Weather (current and forecast)
  - Currency information and exchange rates
  - Points of interest
  - Wikipedia links

## Technologies Used

- HTML5, CSS3, and JavaScript
- Bootstrap 5 for responsive design
- jQuery for DOM manipulation and AJAX
- Leaflet.js for interactive maps
- PHP for backend API calls
- Third-party APIs:
  - OpenCage for geocoding
  - OpenWeather for weather data
  - GeoNames for geographical data
  - Rest Countries for country information
  - Open Exchange Rates for currency exchange rates

## Setup Instructions

### Prerequisites

- Web server with PHP 7.0+ support
- API keys for:
  - OpenCage Geocoding
  - OpenWeather
  - GeoNames (username)
  - Open Exchange Rates

### Installation

1. Clone or download this repository to your web server.
2. Update API keys in `php/config.php`:
   ```php
   define('OPENCAGE_API_KEY', 'your_opencage_api_key_here');
   define('OPENWEATHER_API_KEY', 'your_openweather_api_key_here');
   define('GEONAMES_USERNAME', 'your_geonames_username_here');
   define('OPENEXCHANGE_API_KEY', 'your_openexchange_api_key_here');
   ```
3. Download the `countryBorders.geo.json` file and place it in the `data` directory.
4. Make sure the web server has proper permissions to access all files and directories.

### API Registration Instructions

1. **OpenCage**: Register at https://opencagedata.com/ (Free plan: 2,500 requests/day)
2. **OpenWeather**: Register at https://openweathermap.org/ (Free plan: 1,000 calls/day)
3. **GeoNames**: Register at http://www.geonames.org/login (Free)
   - After registration, activate your account via email
   - Then login and enable web services at http://www.geonames.org/manageaccount
4. **Open Exchange Rates**: Register at https://openexchangerates.org/ (Free plan: 1,000 requests/month)

## Usage

1. Open the Gazetteer website in a browser.
2. The application will attempt to detect your location and show your country.
3. Use the dropdown menu to select and explore different countries.
4. Click on map markers to view points of interest.
5. Use the country information modal to see detailed data about the selected country.

## Project Structure

```
gazetteer/
├── css/
│   └── styles.css
├── data/
│   └── countryBorders.geo.json
├── js/
│   └── script.js
├── php/
│   ├── config.php
│   ├── utilities.php
│   ├── getCountryBorder.php
│   ├── getCountryFromCoordinates.php
│   ├── getCountryInfo.php
│   ├── getCountryList.php
│   ├── getExchangeRates.php
│   ├── getPointsOfInterest.php
│   ├── getWeather.php
│   └── getWikipediaLinks.php
└── index.html
```

## Browser Compatibility

The application is designed to be compatible with:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Android Chrome) 