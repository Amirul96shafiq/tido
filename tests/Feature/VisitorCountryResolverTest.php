<?php

declare(strict_types=1);

use App\Services\SignupGreeting\VisitorCountryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

test('private ip resolves to malaysia for local greeting', function (): void {
    $request = Request::create('/admin/login', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);

    expect(app(VisitorCountryResolver::class)->resolve($request))
        ->toBe('MY');
});

test('lan ip resolves to malaysia for local greeting', function (): void {
    $request = Request::create('/admin/login', 'GET', server: ['REMOTE_ADDR' => '192.168.1.42']);

    expect(app(VisitorCountryResolver::class)->resolve($request))
        ->toBe('MY');
});

test('cloudflare country header is used for public ip', function (): void {
    $request = Request::create(
        '/admin/login',
        'GET',
        server: [
            'REMOTE_ADDR' => '8.8.8.8',
            'HTTP_CF_IPCOUNTRY' => 'ID',
        ],
    );

    Http::fake();

    expect(app(VisitorCountryResolver::class)->resolve($request))
        ->toBe('ID');
});

test('public ip uses geoip provider when header is absent', function (): void {
    Http::fake([
        'https://ipwho.is/*' => Http::response([
            'success' => true,
            'country_code' => 'MY',
        ]),
    ]);

    $request = Request::create('/admin/login', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8']);

    expect(app(VisitorCountryResolver::class)->resolve($request))
        ->toBe('MY');
});

test('public ip geoip failure returns null country', function (): void {
    Http::fake([
        'https://ipwho.is/*' => Http::response(['success' => false], 500),
    ]);

    $request = Request::create('/admin/login', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8']);

    expect(app(VisitorCountryResolver::class)->resolve($request))
        ->toBeNull();
});

test('public ip geoip result is cached by hashed ip', function (): void {
    Http::fake([
        'https://ipwho.is/*' => Http::response([
            'success' => true,
            'country_code' => 'TH',
        ]),
    ]);

    $request = Request::create('/admin/login', 'GET', server: ['REMOTE_ADDR' => '1.1.1.1']);
    $resolver = app(VisitorCountryResolver::class);

    expect($resolver->resolve($request))->toBe('TH')
        ->and($resolver->resolve($request))->toBe('TH');

    Http::assertSentCount(1);
});
