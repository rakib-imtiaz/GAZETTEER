// Global variables
let map;
let userLocation;
let countryBorders;
let countryLayer;
let currentCountry = null;
let bottomSheetVisible = false;

// Base URL for API calls
const API_BASE_URL = '/GAZETTEER/php/';

// Initialize the application when the document is ready
$(document).ready(function () {
    // Initialize the map
    initMap();

    // Get user's location
    getUserLocation();

    // Load country list for dropdown
    loadCountryList();

    // Event listener for country selection
    $('#countrySelect').on('change', function () {
        const countryCode = $(this).val();
        if (countryCode) {
            loadCountryData(countryCode);
        }
    });

    // Hide preloader when everything is loaded
    $(window).on('load', function () {
        setTimeout(function () {
            $('#preloader').fadeOut(500);
        }, 1000);
    });

    // Initialize mobile-specific features
    initMobileFeatures();
});

/**
 * Initialize mobile-specific features
 */
function initMobileFeatures() {
    // Mobile menu button click
    $('#mobileMenuBtn').on('click', function () {
        toggleBottomSheet();
    });

    // Bottom sheet touch gestures
    const bottomSheet = document.querySelector('.mobile-bottom-sheet');
    if (bottomSheet) {
        const hammer = new Hammer(bottomSheet);
        hammer.get('swipe').set({ direction: Hammer.DIRECTION_VERTICAL });

        hammer.on('swipedown', function () {
            hideBottomSheet();
        });

        hammer.on('swipeup', function () {
            showBottomSheet();
        });
    }

    // Mobile action buttons
    $('#mobileMyLocation').on('click', function() {
        hideBottomSheet();
        if (userLocation) {
            // Use flyTo for smooth animation, matching the map control
            map.flyTo([userLocation.lat, userLocation.lng], 6, {
                duration: 1.5
            });
            
            // Show a subtle bounce animation on the user marker if it exists
            $('.user-location-marker i').addClass('animate__animated animate__heartBeat');
            setTimeout(() => {
                $('.user-location-marker i').removeClass('animate__animated animate__heartBeat');
            }, 1000);
        } else {
            getUserLocation();
            showNotification('info', 'Getting your location...');
        }
    });

    $('#mobileExplore').on('click', function () {
        hideBottomSheet();
        // Zoom out to world view
        map.setView([20, 0], 2);
    });

    $('#mobileInfo').on('click', function () {
        hideBottomSheet();
        if (currentCountry) {
            $('#countryInfoModal').modal('show');
        } else {
            showNotification('info', 'Please select a country first');
        }
    });

    $('#mobileShare').on('click', function () {
        hideBottomSheet();
        shareCurrentView();
    });

    // Share button in modal
    $('#shareCountryBtn').on('click', function () {
        shareCurrentView();
    });
}

/**
 * Toggle bottom sheet visibility
 */
function toggleBottomSheet() {
    if (bottomSheetVisible) {
        hideBottomSheet();
    } else {
        showBottomSheet();
    }
}

/**
 * Show the bottom sheet
 */
function showBottomSheet() {
    $('.mobile-bottom-sheet').addClass('active');
    bottomSheetVisible = true;
}

/**
 * Hide the bottom sheet
 */
function hideBottomSheet() {
    $('.mobile-bottom-sheet').removeClass('active');
    bottomSheetVisible = false;
}

/**
 * Share current map view or country info
 */
function shareCurrentView() {
    let shareText = 'Check out this country on Gazetteer!';
    let shareUrl = window.location.href;

    if (currentCountry) {
        shareText = `Check out ${currentCountry.name} on Gazetteer!`;

        // Add country code as URL parameter
        const urlParts = window.location.href.split('?');
        shareUrl = urlParts[0] + '?country=' + (currentCountry.iso2 || '');
    }

    // Check if Web Share API is supported
    if (navigator.share) {
        navigator.share({
            title: 'Gazetteer',
            text: shareText,
            url: shareUrl
        }).catch(error => {
            console.error('Error sharing:', error);
            showNotification('warning', 'Could not share. Try copying the URL manually.');
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        try {
            // Create a temporary input element
            const tempInput = document.createElement('input');
            tempInput.value = shareUrl;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);

            showNotification('success', 'URL copied to clipboard!');
        } catch (err) {
            showNotification('error', 'Could not copy URL. Please copy it manually from the address bar.');
        }
    }
}

/**
 * Initialize the Leaflet map
 */
function initMap() {
    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const countryParam = urlParams.get('country');

    map = L.map('map', {
        center: [20, 0],
        zoom: 2,
        minZoom: 2,
        maxZoom: 18,
        zoomControl: false  // We'll add it in a better position for mobile
    });

    // Add the OpenStreetMap tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | Gazetteer',
        maxZoom: 19
    }).addTo(map);

    // Add zoom control to top-right for better mobile experience
    L.control.zoom({
        position: 'topright'
    }).addTo(map);

    // Add "My Location" button
    L.easyButton({
        position: 'topright',
        states: [{
            stateName: 'my-location',
            icon: 'fa-location-arrow',
            title: 'Go to my location',
            onClick: function (btn, map) {
                // Add clicked class for visual feedback
                $(btn.button).addClass('clicked');
                setTimeout(() => {
                    $(btn.button).removeClass('clicked');
                }, 500);

                if (userLocation) {
                    // Use flyTo for smooth animation
                    map.flyTo([userLocation.lat, userLocation.lng], 6, {
                        duration: 1.5
                    });

                    // Show a subtle bounce animation on the user marker if it exists
                    $('.user-location-marker i').addClass('animate__animated animate__heartBeat');
                    setTimeout(() => {
                        $('.user-location-marker i').removeClass('animate__animated animate__heartBeat');
                    }, 1000);
                } else {
                    getUserLocation();
                    showNotification('info', 'Getting your location...');
                }
            }
        }]
    }).addTo(map);

    // Add a fullscreen button
    L.easyButton('fa-expand', function () {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                showNotification('warning', 'Fullscreen mode not available');
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }, 'Toggle fullscreen mode').addTo(map);

    // If country parameter exists, load that country
    if (countryParam) {
        $('#countrySelect').val(countryParam);
        loadCountryData(countryParam);
    }
}

/**
 * Get the user's current location using Geolocation API
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

                // Add marker for user location with pulsing effect
                const userIcon = L.divIcon({
                    className: 'user-location-marker',
                    html: `
                        <div class="marker-container">
                            <i class="fas fa-circle pulse-animation"></i>
                            <div class="location-ripple"></div>
                        </div>
                    `,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });

                const userMarker = L.marker([userLocation.lat, userLocation.lng], {
                    icon: userIcon,
                    zIndexOffset: 1000
                })
                    .bindPopup('<strong>Your Location</strong>')
                    .addTo(map);

                // Fly to user's location
                map.setView([userLocation.lat, userLocation.lng], 6);

                // Get user's country based on location
                $.ajax({
                    url: API_BASE_URL + 'getCountryFromCoordinates.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        lat: userLocation.lat,
                        lng: userLocation.lng
                    },
                    success: function (response) {
                        if (response.status && response.status.code === 200) {
                            const countryCode = response.data.countryCode;

                            // Select the user's country in dropdown
                            $('#countrySelect').val(countryCode);

                            // Load country data
                            loadCountryData(countryCode);
                        } else {
                            showNotification('error', 'Could not determine your country');
                        }
                    },
                    error: function () {
                        showNotification('error', 'Network error. Please try again.');
                    }
                });
            },
            function (error) {
                console.error('Geolocation error:', error);
                showNotification('warning', 'Could not access your location');
                $('#preloader').fadeOut(500);
            }
        );
    } else {
        showNotification('warning', 'Your browser does not support geolocation');
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
            if (response.status && response.status.code === 200) {
                const countries = response.data;
                let options = '<option selected disabled value="">Select a country</option>';

                // Sort countries alphabetically
                countries.sort((a, b) => a.name.localeCompare(b.name));

                // Add options to dropdown
                countries.forEach(country => {
                    options += `<option value="${country.code}">${country.name}</option>`;
                });

                $('#countrySelect').html(options);

                // Check URL parameters after loading country list
                const urlParams = new URLSearchParams(window.location.search);
                const countryParam = urlParams.get('country');
                if (countryParam) {
                    $('#countrySelect').val(countryParam);
                    loadCountryData(countryParam);
                }
            } else {
                showNotification('error', 'Failed to load country list');
            }
        },
        error: function () {
            showNotification('error', 'Network error. Please try again.');
        }
    });
}

/**
 * Load data for a specific country
 * @param {string} countryCode - The ISO country code
 */
function loadCountryData(countryCode) {
    // Show preloader
    $('#preloader').fadeIn(300);

    // Remove previous country layer if exists
    if (countryLayer) {
        map.removeLayer(countryLayer);
    }

    // Get country border
    $.ajax({
        url: API_BASE_URL + 'getCountryBorder.php',
        type: 'GET',
        dataType: 'json',
        data: {
            countryCode: countryCode
        },
        success: function (response) {
            if (response.status && response.status.code === 200) {
                // Add country border to map
                countryBorders = response.data;
                if (countryBorders && countryBorders.geometry) {
                    countryLayer = L.geoJSON(countryBorders, {
                        style: {
                            color: '#3b82f6',
                            weight: 2,
                            opacity: 0.8,
                            fillColor: '#3b82f6',
                            fillOpacity: 0.15
                        }
                    }).addTo(map);

                    // Fit map to country bounds
                    map.fitBounds(countryLayer.getBounds());
                } else {
                    console.warn('Country border data is incomplete:', countryBorders);
                }

                // Update URL with country parameter for sharing
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('country', countryCode);
                window.history.replaceState({}, '', newUrl);

                // Load country info regardless of border availability
                loadCountryInfo(countryCode);
            } else {
                console.error('Failed to load country border:', response.status ? response.status.message : 'Unknown error');
                showNotification('warning', 'Could not load country border, but information will still be displayed');

                // Continue to load country info anyway
                loadCountryInfo(countryCode);
                $('#preloader').fadeOut(300);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error loading country border:', textStatus, errorThrown);
            showNotification('warning', 'Could not load country border, but information will still be displayed');

            // Continue to load country info anyway
            loadCountryInfo(countryCode);
            $('#preloader').fadeOut(300);
        }
    });
}

/**
 * Load detailed information about a country
 * @param {string} countryCode - The ISO country code
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
            if (response.status && response.status.code === 200) {
                const countryData = response.data;
                currentCountry = countryData;
                currentCountry.iso2 = countryCode;

                // Update country info in modal
                $('#countryName').text(countryData.name);
                $('#countryCapital').text('Capital: ' + countryData.capital);
                $('#countryFlag').attr('src', countryData.flag);
                $('#population').text(countryData.population.toLocaleString());
                $('#area').text(countryData.area.toLocaleString() + ' km²');
                $('#region').text(countryData.region);
                $('#timezone').text(countryData.timezones[0]);
                $('#currency').text(countryData.currency.name + ' (' + countryData.currency.code + ')');

                // Handle languages
                $('#languages').text(countryData.languages.join(', '));

                // Load weather data
                loadWeatherData(countryData.capital);

                // Load Wikipedia links
                loadWikipediaLinks(countryData.name);

                // Show the modal
                $('#countryInfoModal').modal('show');

                // Hide preloader
                $('#preloader').fadeOut(300);
            } else {
                showNotification('error', 'Failed to load country information');
                $('#preloader').fadeOut(300);
            }
        },
        error: function () {
            showNotification('error', 'Network error. Please try again.');
            $('#preloader').fadeOut(300);
        }
    });
}

/**
 * Load weather data for a city
 * @param {string} city - The city name
 */
function loadWeatherData(city) {
    $.ajax({
        url: API_BASE_URL + 'getWeather.php',
        type: 'GET',
        dataType: 'json',
        data: {
            city: city
        },
        success: function (response) {
            if (response.status && response.status.code === 200) {
                const weatherData = response.data.current;

                // Update weather info in modal
                $('#temperature').text(weatherData.temp + '°C');
                $('#weatherDescription').text(weatherData.description);
                $('#weatherIcon').attr('src', `http://openweathermap.org/img/wn/${weatherData.icon}@2x.png`);

                // Update additional weather details
                $('#humidity').text(weatherData.humidity + '%');
                $('#windSpeed').text(weatherData.wind + ' m/s');
            } else {
                $('#temperature').text('N/A');
                $('#weatherDescription').text('Weather data unavailable');
                $('#humidity').text('--');
                $('#windSpeed').text('--');
            }
        },
        error: function () {
            $('#temperature').text('N/A');
            $('#weatherDescription').text('Weather data unavailable');
            $('#humidity').text('--');
            $('#windSpeed').text('--');
        }
    });
}

/**
 * Load Wikipedia links for a country
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
            if (response.status && response.status.code === 200) {
                const links = response.data;
                let html = '';

                if (links.length > 0) {
                    html = '<ul class="list-group">';
                    links.forEach(link => {
                        html += `
                            <li class="list-group-item">
                                <a href="${link.url}" target="_blank">${link.title}</a>
                                <p class="small text-muted mb-0">${link.summary}</p>
                            </li>
                        `;
                    });
                    html += '</ul>';
                } else {
                    html = '<div class="alert alert-info">No Wikipedia links available</div>';
                }

                $('#wikipediaLinks').html(html);
            } else {
                $('#wikipediaLinks').html('<div class="alert alert-warning">Wikipedia data unavailable</div>');
            }
        },
        error: function () {
            $('#wikipediaLinks').html('<div class="alert alert-warning">Wikipedia data unavailable</div>');
        }
    });
}

/**
 * Display a notification to the user
 * @param {string} type - The type of notification (success, error, warning, info)
 * @param {string} message - The message to display
 */
function showNotification(type, message) {
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

    // Create notification element
    const toast = $(`
        <div class="notification-toast" style="background-color: ${bgColor};">
            <div class="toast-content">
                <i class="fas fa-${icon} me-2"></i>
                <span>${message}</span>
            </div>
        </div>
    `);

    // Remove any existing notifications
    $('.notification-toast').remove();

    // Add to body and animate
    $('body').append(toast);
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