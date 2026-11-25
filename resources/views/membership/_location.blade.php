<section id="membership-location"
         data-maps-key="{{ config('services.google_maps.key') }}"
         data-map-id="{{ config('services.google_maps.map_id') }}"
         data-weather-url="{{ route('weather.show') }}"
         class="space-y-4 rounded-lg border border-slate-200 p-4">

    <h2 class="text-lg font-semibold">Location — optional</h2>

    <p class="text-sm text-slate-600">
        Choose a point on the map if you want to provide a location.
        Your selected point is used to retrieve nearby weather.
        It is saved with your application when you submit with consent.
    </p>

    <input id="latitude"
           type="hidden"
           name="latitude"
           value="{{ old('latitude') }}">

    <input id="longitude"
           type="hidden"
           name="longitude"
           value="{{ old('longitude') }}">

    <div id="membership-map"
         aria-label="Membership location map"
         class="rounded-lg bg-slate-100"
         style="height: 360px; width: 100%;">
    </div>

    <p id="map-status"
       role="status"
       aria-live="polite"
       class="text-sm text-slate-600">
        Loading map…
    </p>

    <div class="flex flex-wrap gap-3">
        <button id="use-manila"
                type="button"
                disabled
                class="rounded-lg bg-indigo-700 px-4 py-2 text-white disabled:opacity-50">
            Use Manila
        </button>

        <button id="clear-location"
                type="button"
                disabled
                class="rounded-lg border border-slate-300 px-4 py-2 disabled:opacity-50">
            Clear location
        </button>
    </div>

    <dl class="grid gap-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-slate-500">Selected latitude</dt>
            <dd id="latitude-display" class="font-mono">None selected</dd>
        </div>

        <div>
            <dt class="text-slate-500">Selected longitude</dt>
            <dd id="longitude-display" class="font-mono">None selected</dd>
        </div>
    </dl>

    <section id="weather-panel"
             class="rounded-lg bg-slate-50 p-4">
        <h3 class="font-semibold">Current weather</h3>

        <p id="weather-status"
           role="status"
           aria-live="polite"
           class="mt-2 text-sm text-slate-600">
            Select a map location to see its weather.
        </p>

        <div id="weather-details" hidden>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Location</dt>
                    <dd id="weather-location" class="font-medium"></dd>
                </div>

                <div>
                    <dt class="text-slate-500">Temperature</dt>
                    <dd id="weather-temperature" class="font-medium"></dd>
                </div>

                <div>
                    <dt class="text-slate-500">Conditions</dt>
                    <dd id="weather-description" class="capitalize"></dd>
                </div>

                <div>
                    <dt class="text-slate-500">Humidity</dt>
                    <dd id="weather-humidity"></dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-slate-500">
                        Weather observation — Philippine time
                    </dt>
                    <dd id="weather-observed"></dd>
                </div>
            </dl>
        </div>

        <a href="https://openweathermap.org/"
           target="_blank"
           rel="noopener noreferrer"
           class="mt-4 inline-flex flex-wrap items-center gap-3 text-xs text-slate-600 underline">
            <img src="{{ asset('images/openweather-logo.png') }}"
                 alt="OpenWeather"
                 class="h-8 w-auto">

            <span>Weather data provided by OpenWeather</span>
        </a>
    </section>
</section>