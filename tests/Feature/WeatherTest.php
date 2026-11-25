<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeatherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openweather.key' => 'test-server-key',
            'cache.default' => 'array',
        ]);

        Http::preventStrayRequests();
    }

    private function providerResponse(): array
    {
        return [
            'name' => 'Manila',
            'sys' => [
                'country' => 'PH',
            ],
            'main' => [
                'temp' => 31.5,
                'humidity' => 75,
            ],
            'weather' => [
                ['description' => 'scattered clouds'],
            ],
            'dt' => 1795564800,
        ];
    }

    public function test_weather_is_returned_in_metric_units_without_exposing_key(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response(
                $this->providerResponse(),
                200
            ),
        ]);

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )
            ->assertOk()
            ->assertJsonPath('location', 'Manila, PH')
            ->assertJsonPath('temperature', 31.5)
            ->assertJsonPath('description', 'scattered clouds')
            ->assertJsonPath('humidity', 75)
            ->assertJsonStructure(['observed_at'])
            ->assertDontSee('test-server-key');

        Http::assertSent(function (ClientRequest $request): bool {
            return str_starts_with(
                $request->url(),
                'https://api.openweathermap.org/data/2.5/weather?'
            )
                && $request->method() === 'GET'
                && (float) $request['lat'] === 14.6
                && (float) $request['lon'] === 120.984
                && $request['units'] === 'metric'
                && $request['appid'] === 'test-server-key';
        });

        Http::assertSentCount(1);
    }

    public static function invalidCoordinates(): array
    {
        return [
            'missing latitude' => [
                '/weather?longitude=120.9842',
                'latitude',
            ],
            'missing longitude' => [
                '/weather?latitude=14.5995',
                'longitude',
            ],
            'latitude too high' => [
                '/weather?latitude=91&longitude=120',
                'latitude',
            ],
            'latitude too low' => [
                '/weather?latitude=-91&longitude=120',
                'latitude',
            ],
            'longitude too high' => [
                '/weather?latitude=14&longitude=181',
                'longitude',
            ],
            'longitude too low' => [
                '/weather?latitude=14&longitude=-181',
                'longitude',
            ],
            'non-numeric latitude' => [
                '/weather?latitude=abc&longitude=120',
                'latitude',
            ],
        ];
    }

    #[DataProvider('invalidCoordinates')]
    public function test_invalid_coordinates_do_not_call_provider(
        string $url,
        string $field
    ): void {
        $this->getJson($url)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        Http::assertNothingSent();
    }

    public function test_nearby_repeated_requests_use_cached_weather(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response(
                $this->providerResponse(),
                200
            ),
        ]);

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )->assertOk();

        $this->getJson(
            '/weather?latitude=14.59951&longitude=120.98421'
        )->assertOk();

        Http::assertSentCount(1);
    }

    public function test_missing_key_returns_safe_message_without_provider_call(): void
    {
        config(['services.openweather.key' => '']);

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )
            ->assertStatus(503)
            ->assertJsonStructure(['message']);

        Http::assertNothingSent();
    }

    public static function providerFailures(): array
    {
        return [
            'invalid key' => [401],
            'access denied' => [403],
            'provider rate limit' => [429],
            'provider server error' => [500],
        ];
    }

    #[DataProvider('providerFailures')]
    public function test_provider_errors_return_safe_messages(
        int $status
    ): void {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'message' => 'Sensitive provider response test-server-key',
            ], $status),
        ]);

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )
            ->assertStatus(503)
            ->assertJsonStructure(['message'])
            ->assertDontSee('test-server-key')
            ->assertDontSee('Sensitive provider response');

        Http::assertSentCount(1);
    }

    public function test_connection_failure_returns_safe_message(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::failedConnection(),
        ]);

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_malformed_provider_response_is_handled(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'unexpected' => 'payload',
            ], 200),
        ]);

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }

    public function test_public_weather_route_is_rate_limited(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response(
                $this->providerResponse(),
                200
            ),
        ]);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->getJson(
                '/weather?latitude=14.5995&longitude=120.9842'
            )->assertOk();
        }

        $this->getJson(
            '/weather?latitude=14.5995&longitude=120.9842'
        )->assertStatus(429);

        Http::assertSentCount(1);
    }
}