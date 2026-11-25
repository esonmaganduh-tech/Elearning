<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class WeatherController extends Controller
{
    private const UNAVAILABLE =
        'Weather is temporarily unavailable. Please try again later.';

    public function __invoke(Request $request): JsonResponse
    {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $apiKey = trim((string) config('services.openweather.key'));

        if ($apiKey === '') {
            Log::warning('OpenWeather key is not configured.');

            return $this->unavailable();
        }

        // Use nearby coordinate groups for the weather cache.
        // The application's saved coordinates still retain seven decimals.
        $latitude = number_format(
            (float) $coordinates['latitude'],
            3,
            '.',
            ''
        );

        $longitude = number_format(
            (float) $coordinates['longitude'],
            3,
            '.',
            ''
        );

        $keyTag = substr(hash('sha256', $apiKey), 0, 16);

        $cacheKey =
            "weather:current:v1:{$keyTag}:{$latitude}:{$longitude}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return response()->json($cached);
        }

        // Shared limit across this application's visitors.
        // Cached responses do not consume this upstream allowance.
        $allowed = RateLimiter::attempt(
            "weather:upstream:{$keyTag}",
            45,
            fn () => true,
            60
        );

        if (! $allowed) {
            return $this->unavailable(
                'Weather service is busy. Please try again later.'
            );
        }

        try {
            $client = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(8);

            // Use Windows' trusted certificate store when supported.
            if (
                PHP_OS_FAMILY === 'Windows'
                && defined('CURLSSLOPT_NATIVE_CA')
            ) {
                $client = $client->withOptions([
                    'curl' => [
                        CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
                    ],
                ]);
            }

            $response = $client->get(
                'https://api.openweathermap.org/data/2.5/weather',
                [
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'en',
                ]
            );
        } catch (ConnectionException $exception) {
            // Do not log the exception URL: it contains the API key.
            Log::warning('OpenWeather connection failed.', [
                'reason' => str_contains(
                    $exception->getMessage(),
                    'cURL error 60'
                )
                    ? 'certificate_verification_failed'
                    : 'connection_or_timeout',
            ]);

            return $this->unavailable();
        }

        if (! $response->successful()) {
            // Keep provider details and credentials out of the public response.
            Log::warning('OpenWeather request rejected.', [
                'status' => $response->status(),
            ]);

            return $this->unavailable(
                $response->status() === 429
                    ? 'Weather service is busy. Please try again later.'
                    : self::UNAVAILABLE
            );
        }

        $raw = $response->json();

        $validator = Validator::make(
            is_array($raw) ? $raw : [],
            [
                'name' => ['nullable', 'string', 'max:255'],
                'sys.country' => ['nullable', 'string', 'max:5'],
                'main.temp' => ['required', 'numeric'],
                'main.humidity' => [
                    'required',
                    'integer',
                    'between:0,100',
                ],
                'weather.0.description' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'dt' => ['required', 'integer', 'min:0'],
            ]
        );

        if ($validator->fails()) {
            Log::warning('OpenWeather returned an unexpected response.');

            return $this->unavailable();
        }

        $payload = $validator->validated();

        $location = trim((string) ($payload['name'] ?? ''));

        if ($location === '') {
            $location = 'Selected area';
        }

        $country = trim((string) data_get($payload, 'sys.country', ''));

        if ($country !== '') {
            $location .= ', '.$country;
        }

        $weather = [
            'location' => $location,
            'temperature' => (float) data_get($payload, 'main.temp'),
            'description' => (string) data_get(
                $payload,
                'weather.0.description'
            ),
            'humidity' => (int) data_get($payload, 'main.humidity'),
            'observed_at' => gmdate(DATE_ATOM, (int) $payload['dt']),
        ];

        Cache::put($cacheKey, $weather, now()->addMinutes(5));

        return response()->json($weather);
    }

    private function unavailable(
        string $message = self::UNAVAILABLE
    ): JsonResponse {
        return response()->json([
            'message' => $message,
        ], 503);
    }
}