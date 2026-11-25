const locationRoot = document.getElementById('membership-location');

if (locationRoot) {
    setupMembershipLocation(locationRoot);
}

async function setupMembershipLocation(root) {
    const find = (id) => root.querySelector(`#${id}`);
    const latitude = find('latitude');
    const longitude = find('longitude');
    const latitudeDisplay = find('latitude-display');
    const longitudeDisplay = find('longitude-display');
    const mapStatus = find('map-status');
    const weatherStatus = find('weather-status');
    const weatherDetails = find('weather-details');
    const manilaButton = find('use-manila');
    const clearButton = find('clear-location');
    const manila = { lat: 14.5995, lng: 120.9842 };

    let map;
    let marker;
    let mapUnavailable = false;
    let weatherVersion = 0;
    let weatherTimer;
    let weatherRequest;

    function readSelection() {
        if (!latitude.value.trim() || !longitude.value.trim()) return null;

        const lat = Number(latitude.value);
        const lng = Number(longitude.value);

        return Number.isFinite(lat) && Number.isFinite(lng)
            && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
            ? { lat, lng }
            : null;
    }

    function displayCoordinates(position) {
        latitudeDisplay.textContent = position
            ? position.lat.toFixed(7) : 'None selected';
        longitudeDisplay.textContent = position
            ? position.lng.toFixed(7) : 'None selected';
        clearButton.disabled = !position;
    }

    function showMapUnavailable() {
        mapUnavailable = true;
        manilaButton.disabled = true;
        mapStatus.textContent =
            'The map is unavailable. You can still submit without an optional location.';
    }

    async function fetchWeather(position, version) {
        if (version !== weatherVersion) return;

        const controller = new AbortController();
        weatherRequest = controller;
        const timeout = window.setTimeout(() => controller.abort(), 12000);
        let failureMessage =
            'Weather is temporarily unavailable. You can still submit your application.';

        try {
            const url = new URL(root.dataset.weatherUrl, window.location.origin);
            url.searchParams.set('latitude', position.lat.toFixed(7));
            url.searchParams.set('longitude', position.lng.toFixed(7));

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });

            if (response.status === 429) {
                failureMessage =
                    'Too many weather requests. Wait one minute and select the location again.';
            }

            if (!response.ok) throw new Error('Weather request failed');

            const data = await response.json();
            if (version !== weatherVersion) return;

            if (!Number.isFinite(data.temperature)
                || !Number.isFinite(data.humidity)
                || typeof data.location !== 'string'
                || typeof data.description !== 'string') {
                throw new Error('Invalid weather response');
            }

            find('weather-location').textContent = data.location;
            find('weather-temperature').textContent =
                `${data.temperature.toFixed(1)} °C`;
            find('weather-description').textContent = data.description;
            find('weather-humidity').textContent = `${data.humidity}%`;

            const observed = new Date(data.observed_at);
            find('weather-observed').textContent = Number.isNaN(observed.getTime())
                ? 'Not available'
                : observed.toLocaleString('en-PH', { timeZone: 'Asia/Manila' });

            weatherDetails.hidden = false;
            weatherStatus.textContent = 'Weather near your selected location.';
        } catch {
            if (version === weatherVersion) {
                weatherDetails.hidden = true;
                weatherStatus.textContent = failureMessage;
            }
        } finally {
            window.clearTimeout(timeout);
        }
    }

    function scheduleWeather(position) {
        const version = ++weatherVersion;
        window.clearTimeout(weatherTimer);
        weatherRequest?.abort();
        weatherDetails.hidden = true;
        weatherStatus.textContent = 'Loading weather…';

        weatherTimer = window.setTimeout(
            () => fetchWeather(position, version),
            400
        );
    }

    function selectLocation(position) {
        if (mapUnavailable || !map) return;

        latitude.value = position.lat.toFixed(7);
        longitude.value = position.lng.toFixed(7);
        displayCoordinates(position);

        if (marker) {
            marker.position = position;
        } else {
            marker = new window.google.maps.marker.AdvancedMarkerElement({
                map,
                position,
                title: 'Selected membership location',
            });
        }

        scheduleWeather(position);
    }

    clearButton.addEventListener('click', () => {
        ++weatherVersion;
        window.clearTimeout(weatherTimer);
        weatherRequest?.abort();

        if (marker) {
            marker.map = null;
            marker = null;
        }

        latitude.value = '';
        longitude.value = '';
        displayCoordinates(null);
        weatherDetails.hidden = true;
        weatherStatus.textContent = 'Select a map location to see its weather.';
    });

    manilaButton.addEventListener('click', () => {
        if (!map || mapUnavailable) return;
        map.panTo(manila);
        selectLocation(manila);
    });

    const initialSelection = readSelection();
    if (!initialSelection) {
        latitude.value = '';
        longitude.value = '';
    }
    displayCoordinates(initialSelection);

    const apiKey = root.dataset.mapsKey?.trim();
    if (!apiKey) {
        showMapUnavailable();
        return;
    }

    try {
        window.gm_authFailure = showMapUnavailable;

        if (!window.google?.maps?.importLibrary) {
            await new Promise((resolve, reject) => {
                const fail = () => {
                    window.clearTimeout(timeout);
                    showMapUnavailable();
                    reject(new Error('Map could not load'));
                };

                const timeout = window.setTimeout(fail, 20000);
                window.gm_authFailure = fail;
                window.membershipMapReady = () => {
                    window.clearTimeout(timeout);
                    resolve();
                };

                const url = new URL('https://maps.googleapis.com/maps/api/js');
                url.searchParams.set('key', apiKey);
                url.searchParams.set('loading', 'async');
                url.searchParams.set('callback', 'membershipMapReady');
                url.searchParams.set('libraries', 'marker');
                url.searchParams.set('v', 'weekly');

                const script = document.createElement('script');
                script.src = url.toString();
                script.async = true;
                script.onerror = fail;
                document.head.appendChild(script);
            });
        }

        const [{ Map }] = await Promise.all([
            window.google.maps.importLibrary('maps'),
            window.google.maps.importLibrary('marker'),
        ]);

        if (mapUnavailable) return;

        const saved = readSelection();
        map = new Map(find('membership-map'), {
            center: saved ?? manila,
            zoom: 12,
            mapId: root.dataset.mapId || 'DEMO_MAP_ID',
            clickableIcons: false,
            streetViewControl: false,
            mapTypeControl: false,
        });

        map.addListener('click', (event) => {
            if (!event.latLng) return;
            selectLocation({
                lat: event.latLng.lat(),
                lng: event.latLng.lng(),
            });
        });

        manilaButton.disabled = false;
        mapStatus.textContent =
            'Click the map to choose a location, or select Use Manila.';

        if (saved) selectLocation(saved);
    } catch {
        showMapUnavailable();
    }
}