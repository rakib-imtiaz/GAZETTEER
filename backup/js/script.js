// Global variables
let map;
let userLocation;
let countryBorders;
let markers = L.markerClusterGroup();
let countryLayer;
let currentCountry = null;
// Base API URL - update this to point directly to PHP files
const API_BASE_URL = '/GAZETTEER/php/';

// Initialize the application when the document is ready
$(document).ready(function () {
    // Initialize the map
    initMap();

    // Get user's location
    getUserLocation();

    // Load country data for the dropdown
    loadCountryList();

    // Event listeners
    $('#countrySelect').on('change', function () {
        const countryCode = $(this).val();
        if (countryCode) {
            loadCountryData(countryCode);
        }
    });

    // Initialize tab functionality for modal
    $('#countryInfoTabs button').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
    });

    // View on Map button in modal
    $('#showOnMapBtn').on('click', function () {
        if (currentCountry && currentCountry.latlng) {
            map.setView([currentCountry.latlng[0], currentCountry.latlng[1]], 6);
        }
    });

    // Handle keyboard navigation (ESC to close modal)
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('#countryInfoModal').modal('hide');
        }
    });

    // Handle responsive behavior
    $(window).on('resize', function () {
        adjustMapHeight();
    });

    // Initial height adjustment
    adjustMapHeight();

    // Hide preloader when everything is loaded
    $(window).on('load', function () {
        setTimeout(function () {
            $('#preloader').fadeOut(500);
        }, 1000);
    });
});

/**
 * Adjust the map height based on screen size
 */
function adjustMapHeight() {
    const navbarHeight = $('.navbar').outerHeight();
    $('#map').css('height', `calc(100vh - ${navbarHeight}px)`);
}

/**
 * Display a notification to the user
 * @param {string} type - The type of notification (success, error, warning, info)
 * @param {string} message - The message to display
 */
function showNotification(type, message) {
    // Remove any existing notifications
    $('.notification-toast').remove();

    // Set the color based on the type
    let bgColor = '#3b82f6'; // Default blue (info)
    let icon = 'info-circle';

    if (type === 'success') {
        bgColor = '#10b981'; // Green
        icon = 'check-circle';
    } else if (type === 'error') {
        bgColor = '#ef4444'; // Red
        icon = 'exclamation-circle';
    } else if (type === 'warning') {
        bgColor = '#f59e0b'; // Amber
        icon = 'exclamation-triangle';
    }

    // Create the notification element
    const toast = $(`
        <div class="notification-toast" style="background-color: ${bgColor};">
            <div class="toast-content">
                <i class="fas fa-${icon} me-2"></i>
                <span>${message}</span>
            </div>
        </div>
    `);

    // Add to body
    $('body').append(toast);

    // Trigger animation
    setTimeout(() => {
        toast.addClass('show');
    }, 100);

    // Auto-hide after 3 seconds
    setTimeout(() => {
        toast.removeClass('show');
        setTimeout(() => {
            toast.remove();
        }, 400);
    }, 3000);
}

/**
 * Initialize the Leaflet map
 */
function initMap() {
    // Create the map centered at a default location (will be updated)
    map = L.map('map', {
        center: [20, 0],
        zoom: 2,
        minZoom: 2,
        maxZoom: 18,
        zoomControl: false,
        attributionControl: true
    });

    // Add zoom control to the top right
    L.control.zoom({
        position: 'topright'
    }).addTo(map);

    // Add the tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | Gazetteer',
        maxZoom: 19
    }).addTo(map);

    // Add easy buttons for user-friendly controls
    L.easyButton('<i class="fas fa-location-arrow"></i>', function () {
        if (userLocation) {
            map.flyTo([userLocation.lat, userLocation.lng], 6, {
                duration: 1.5,
                easeLinearity: 0.25
            });
        }
    }, 'Go to your location').addTo(map);

    L.easyButton('<i class="fas fa-home"></i>', function () {
        map.flyTo([20, 0], 2, {
            duration: 1.5,
            easeLinearity: 0.25
        });
    }, 'Return to world view').addTo(map);

    // Add custom attribution with app info
    L.control.attribution({
        prefix: 'Gazetteer &copy; ' + new Date().getFullYear()
    }).addTo(map);

    // Add the marker cluster group to the map
    map.addLayer(markers);

    // Handle map click events (for mobile usability)
    map.on('click', function () {
        // Close any open popups on mobile when map is clicked
        if (window.innerWidth < 768) {
            map.closePopup();
        }
    });
}

/**
 * Get the user's current location using the Geolocation API
 */
function getUserLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (position) {
                // Store user's location
                userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                // Create a customized marker for the user's location
                const userIcon = L.divIcon({
                    className: 'user-location-marker',
                    html: '<div class="pulse-animation"><i class="fas fa-circle"></i></div>',
                    iconSize: [20, 20]
                });

                // Add marker with custom icon
                const userMarker = L.marker([userLocation.lat, userLocation.lng], {
                    icon: userIcon,
                    zIndexOffset: 1000
                }).bindPopup('<div class="location-popup"><strong>Your Location</strong></div>')
                    .addTo(map);

                // Fly to user's location with animation
                map.flyTo([userLocation.lat, userLocation.lng], 6, {
                    duration: 2,
                    easeLinearity: 0.25
                });

                // Determine user's country based on coordinates
                $.ajax({
                    url: API_BASE_URL + 'getCountryFromCoordinates.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        lat: userLocation.lat,
                        lng: userLocation.lng
                    },
                    success: function (response) {
                        if (response.status.code === 200) {
                            const countryCode = response.data.countryCode;

                            // Select the user's country in the dropdown
                            $('#countrySelect').val(countryCode);

                            // Load country data
                            loadCountryData(countryCode);
                        } else {
                            console.error('Error getting country from coordinates:', response.status.message);
                            showNotification('error', 'Could not determine your country. Please select one from the dropdown.');
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error('AJAX Error:', textStatus, errorThrown);
                        showNotification('error', 'Network error. Please check your connection and try again.');
                    }
                });
            },
            function (error) {
                console.error('Geolocation error:', error);
                // If unable to get location, just show the world map
                map.setView([20, 0], 2);
                showNotification('warning', 'Could not access your location. Please allow location access or select a country manually.');
                $('#preloader').fadeOut(500);
            },
            {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 0
            }
        );
    } else {
        console.error('Geolocation is not supported by this browser.');
        showNotification('warning', 'Your browser does not support geolocation. Please select a country manually.');
        map.setView([20, 0], 2);
        $('#preloader').fadeOut(500);
    }
}

/**
 * Load the list of countries for the dropdown
 */
function loadCountryList() {
    $.ajax({
        url: API_BASE_URL + 'getCountryList.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.status.code === 200) {
                const countries = response.data;
                let options = '<option selected disabled value="">Select a country...</option>';

                // Sort countries alphabetically
                countries.sort((a, b) => a.name.localeCompare(b.name));

                // Add options to the select dropdown
                countries.forEach(country => {
                    options += `<option value="${country.code}">${country.name}</option>`;
                });

                $('#countrySelect').html(options);
            } else {
                console.error('Error loading country list:', response.status.message);
                showNotification('error', 'Failed to load country list.');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            showNotification('error', 'Network error. Please check your connection and try again.');
        }
    });
}

/**
 * Load data for a specific country
 * @param {string} countryCode - The ISO 2-character country code
 */
function loadCountryData(countryCode) {
    // Show loader
    $('#preloader').fadeIn(300);

    // Clear previous markers and country layer
    markers.clearLayers();
    if (countryLayer) {
        map.removeLayer(countryLayer);
    }

    // Load country info
    loadCountryInfo(countryCode);

    // Get country border data
    $.ajax({
        url: API_BASE_URL + 'getCountryBorder.php',
        type: 'GET',
        data: { countryCode: countryCode },
        dataType: 'json',
        success: function (response) {
            if (response.status.code === 200) {
                // Add country border to the map
                countryBorders = response.data;
                countryLayer = L.geoJSON(countryBorders, {
                    style: {
                        color: '#3b82f6',
                        weight: 2,
                        opacity: 0.8,
                        fillColor: '#3b82f6',
                        fillOpacity: 0.15,
                        dashArray: '5, 5',
                        className: 'country-border'
                    }
                }).addTo(map);

                // Add a pulsing point at the centroid of the country
                const bounds = countryLayer.getBounds();
                const center = bounds.getCenter();

                // Create a pulsing marker at the center
                const pulsingIcon = L.divIcon({
                    className: 'country-centroid-marker',
                    html: '<div class="pulse-dot"></div>',
                    iconSize: [12, 12]
                });

                L.marker([center.lat, center.lng], {
                    icon: pulsingIcon,
                    zIndexOffset: 500
                }).addTo(markers);

                // Fit map to country bounds with animation
                map.flyToBounds(countryLayer.getBounds(), {
                    padding: [30, 30],
                    duration: 1.5
                });

                // Load country information
                loadCountryInfo(countryCode);
            } else {
                console.error('Error loading country border:', response.status.message);
                showNotification('error', 'Failed to load country border data.');
                $('#preloader').fadeOut(300);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            showNotification('error', 'Network error. Please check your connection and try again.');
            $('#preloader').fadeOut(300);
        }
    });
}

/**
 * Load detailed information about a country
 * @param {string} countryCode - The ISO 2-character country code
 */
function loadCountryInfo(countryCode) {
    $.ajax({
        url: API_BASE_URL + 'getCountryInfo.php',
        type: 'GET',
        dataType: 'json',
        data: {
            country: countryCode
        },
        success: function (response) {
            if (response.status.code === 200) {
                const countryData = response.data;
                currentCountry = countryData;

                // Update country info in the modal
                $('#countryName').text(countryData.name);
                $('#capitalCity').text(`Capital: ${countryData.capital}`);
                $('#countryFlag').attr('src', countryData.flag);
                $('#population').text(countryData.population.toLocaleString());
                $('#area').text(`${countryData.area.toLocaleString()} km²`);

                // Handle languages (can be multiple)
                let languagesHtml = '';
                countryData.languages.forEach(lang => {
                    languagesHtml += `<span class="badge bg-info me-1 mb-1">${lang}</span>`;
                });
                $('#languages').html(languagesHtml || '<span class="text-muted">No data available</span>');

                // Geography info
                $('#region').text(countryData.region || 'N/A');
                $('#subregion').text(countryData.subregion || 'N/A');
                $('#timezone').text(countryData.timezones.join(', ') || 'N/A');

                // Currency info
                $('#currencyName').text(countryData.currency.name);
                $('#currencyCode').text(countryData.currency.code);
                $('#currencySymbol').text(countryData.currency.symbol);
                $('#currency-symbol-addon').text(countryData.currency.symbol);

                // Load weather data for the capital
                loadWeatherData(countryData.capital, countryData.latlng);

                // Load exchange rates
                loadExchangeRates(countryData.currency.code);

                // Load points of interest
                loadPointsOfInterest(countryData.latlng[0], countryData.latlng[1]);

                // Load Wikipedia links
                loadWikipediaLinks(countryData.name);

                // Show the modal
                $('#countryInfoModal').modal('show');

                // Hide preloader
                $('#preloader').fadeOut(300);
            } else {
                console.error('Error loading country info:', response.status.message);
                showNotification('error', 'Failed to load country information.');
                $('#preloader').fadeOut(300);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            showNotification('error', 'Network error. Please check your connection and try again.');
            $('#preloader').fadeOut(300);
        }
    });
}

/**
 * Load weather data for a location
 * @param {string} city - The city name
 * @param {Array} latlng - The latitude and longitude of the country
 */
function loadWeatherData(city, latlng) {
    // Show loading state
    $.ajax({
        url: API_BASE_URL + 'getWeather.php',
        type: 'GET',
        dataType: 'json',
        data: {
            city: city,
            lat: latlng[0],
            lng: latlng[1]
        },
        success: function (response) {
            if (response.status.code === 200) {
                const weatherData = response.data;

                // Current weather
                $('#temperature').text(`${weatherData.current.temp}°C`);
                $('#weatherDescription').text(weatherData.current.description);
                $('#weatherIcon').attr('src', `http://openweathermap.org/img/wn/${weatherData.current.icon}@2x.png`);
                $('#humidity').text(`${weatherData.current.humidity}%`);
                $('#wind').text(`${weatherData.current.wind} m/s`);

                // Forecast
                let forecastHtml = '<div class="row">';
                weatherData.forecast.forEach((day, index) => {
                    if (index < 5) { // Show only 5 days
                        forecastHtml += `
                            <div class="col">
                                <div class="forecast-item">
                                    <div>${day.date}</div>
                                    <img src="http://openweathermap.org/img/wn/${day.icon}.png" alt="Weather Icon">
                                    <div>${day.temp}°C</div>
                                    <div class="small">${day.description}</div>
                                </div>
                            </div>
                        `;
                    }
                });
                forecastHtml += '</div>';
                $('#forecast').html(forecastHtml);
            } else {
                console.error('Error loading weather data:', response.status.message);
                $('#weather').html('<div class="alert alert-warning">Weather data unavailable</div>');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            $('#weather').html('<div class="alert alert-warning">Weather data unavailable</div>');
        }
    });
}

/**
 * Load exchange rates for a currency
 * @param {string} currencyCode - The 3-character currency code
 */
function loadExchangeRates(currencyCode) {
    $.ajax({
        url: API_BASE_URL + 'getExchangeRates.php',
        type: 'GET',
        dataType: 'json',
        data: {
            currency: currencyCode
        },
        success: function (response) {
            if (response.status.code === 200) {
                const rates = response.data;
                let ratesHtml = '<div class="list-group">';

                // Add major currencies
                const majorCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
                majorCurrencies.forEach(currency => {
                    if (rates[currency] && currency !== currencyCode) {
                        ratesHtml += `
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>1 ${currencyCode} = ${rates[currency].toFixed(4)} ${currency}</span>
                                <span class="text-end conversion-result" data-rate="${rates[currency]}">
                                    ${(rates[currency] * 1).toFixed(2)} ${currency}
                                </span>
                            </div>
                        `;
                    }
                });

                ratesHtml += '</div>';
                $('#exchangeRates').html(ratesHtml);

                // Add event listener for amount input
                $('#amountInput').on('input', function () {
                    const amount = parseFloat($(this).val()) || 0;
                    $('.conversion-result').each(function () {
                        const rate = parseFloat($(this).data('rate'));
                        $(this).text(`${(rate * amount).toFixed(2)} ${$(this).text().split(' ').pop()}`);
                    });
                });
            } else {
                console.error('Error loading exchange rates:', response.status.message);
                $('#exchangeRates').html('<div class="alert alert-warning">Exchange rate data unavailable</div>');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            $('#exchangeRates').html('<div class="alert alert-warning">Exchange rate data unavailable</div>');
        }
    });
}

/**
 * Load points of interest near a location
 * @param {number} lat - Latitude
 * @param {number} lng - Longitude
 */
function loadPointsOfInterest(lat, lng) {
    $.ajax({
        url: API_BASE_URL + 'getPointsOfInterest.php',
        type: 'GET',
        dataType: 'json',
        data: {
            lat: lat,
            lng: lng
        },
        success: function (response) {
            if (response.status.code === 200) {
                const pois = response.data;
                let poisHtml = '';

                if (pois.length > 0) {
                    poisHtml = '<div class="row">';
                    pois.forEach(poi => {
                        // Add to HTML
                        poisHtml += `
                            <div class="col-md-6 mb-3">
                                <div class="poi-item">
                                    <h6>${poi.name}</h6>
                                    <p class="mb-1"><small>${poi.feature} in ${poi.countryName}</small></p>
                                    <button class="btn btn-sm btn-primary show-on-map" 
                                            data-lat="${poi.lat}" 
                                            data-lng="${poi.lng}" 
                                            data-name="${poi.name}">
                                        Show on Map
                                    </button>
                                </div>
                            </div>
                        `;

                        // Add marker to the map
                        const marker = L.marker([poi.lat, poi.lng])
                            .bindPopup(`<strong>${poi.name}</strong><br>${poi.feature}`)
                            .addTo(markers);
                    });
                    poisHtml += '</div>';

                    // Add click handler for "Show on Map" buttons
                    setTimeout(() => {
                        $('.show-on-map').on('click', function () {
                            const lat = $(this).data('lat');
                            const lng = $(this).data('lng');
                            const name = $(this).data('name');

                            // Close the modal
                            $('#countryInfoModal').modal('hide');

                            // Zoom to the POI
                            map.setView([lat, lng], 12);

                            // Find and open the popup
                            markers.eachLayer(function (layer) {
                                if (layer.getLatLng().lat === lat && layer.getLatLng().lng === lng) {
                                    layer.openPopup();
                                }
                            });
                        });
                    }, 500);
                } else {
                    poisHtml = '<div class="alert alert-info">No points of interest found.</div>';
                }

                $('#pointsOfInterest').html(poisHtml);
            } else {
                console.error('Error loading points of interest:', response.status.message);
                $('#pointsOfInterest').html('<div class="alert alert-warning">Points of interest data unavailable</div>');
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            $('#pointsOfInterest').html('<div class="alert alert-warning">Points of interest data unavailable</div>');
        }
    });
}

/**
 * Load Wikipedia links related to a country
 * @param {string} countryName - The name of the country
 */
function loadWikipediaLinks(countryName) {
    $.ajax({
        url: API_BASE_URL + 'getWikipediaLinks.php',
        type: 'GET',
        dataType: 'json',
        data: {
            country: countryName
        },
        success: function (response) {
            if (response.status.code === 200) {
                const wikiLinks = response.data;
                let wikiHtml = '';

                if (wikiLinks.length > 0) {
                    wikiHtml = '<div class="list-group">';
                    wikiLinks.forEach(link => {
                        wikiHtml += `