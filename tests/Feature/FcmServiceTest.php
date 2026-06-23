<?php

namespace Tests\Feature;

use App\Services\FCMService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for the FCM push-notification service (Phase 3).
 *
 * We fake the network with Http::fake(), so no real Firebase call is made.
 * The RSA key below is a throwaway used ONLY to exercise the JWT signing.
 */
class FcmServiceTest extends TestCase
{
    // Throwaway test-only RSA private key (NOT a real credential).
    private const TEST_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCUNV5gsqeaZyz4
bb5jk21TGTmq9iU33hNnBHAM+MkaM7OL5fV2cbbmUzM4Llo81KhUwZ6+RPgjVe6H
mE626Lanf2v7RtqMV0esQ0tHd9aiaAb6lyJU8lIexHVvGwiZUgnHZNIPL2y2eVqq
vqv4AlwT93xM6juUlKcmQpGVVgfZhl/9sBBP0dgfrVsWlOpdoRaw6kTwX6jXBlKr
o25zlcra3U/7TsKDbWO+kvdnmDb31b1JYmpBD+d9OAalhE0HWO3h1gnUzWsdTPgP
GNXo9AyheNycIIekhfrGti2yMEkt6OiB7C0cIf1sOMy1qJlTWdvsHEPAB/8Aca+H
5gZyDvtxAgMBAAECggEAANtqJ08BLyBnnywrTLIekLt1nOp9fpw3KoJZD4okx5UC
9c0npT61GxHiGvIRrYZhTtQr0dfeQ34MuhYEZoRkAgX3nuVjACrFDFb7SXeE8z18
xNg455rbJLhoIXNbkl9qFRAdMt9IVqnO/2vI3M9OrwLgYiV8oLhXWx5Jbz6nHvfo
tpRS/JaOsVZ7++qTRI4q5KC66UwyKnYOJs+WhnL+LuIM0OF9f/YGfHDaR3ks5ho2
vtuNURVDadnYm92XnpqSVGI5P06NZho6vQFgTICwEWD4as7kaASNoxBpTtapQgx2
b/9GU+SXkbro9Jq99xfHq6MHmNsvK7czOWNYK13pwQKBgQDN0aokR6+xmd5qvh7d
6wd3brpr7dDoaud9p8NsMOdIZCg5+vD5qHTzeOXBKwPe2saI9LF+kFFfxOtY3rkX
oJmcKaIaJhjqqbVk5SlxNTenGLFWeRPblvFqIk2WZimt3FcrFEDlJkdvz2WHXYOi
Ym/xwHyOhBV/whsYe1V8JWU3CQKBgQC4V+ck1G5Jt6xF28xiJKC/0aOptXuGkHIs
6mIlW//QOcIfgSw4AHELUEFTvJwyxoYwOtKTc4+WgLx+sF4hrjeYrFipE3v2Nw02
d4kxcuaQuAkuefmdQPFWIq/wSgUwyqpnVYv9i9xEcfKN8lqd5ECIn1SS3NrWchkp
5we4bQeTKQKBgQCRYhmx+OU1KcA/XD6HeV5T0eZjGKCpm+IbE2bqRH0K2JzFn+ma
IJFD7lE0qsLYJ2d4FIhQI3cnk/y+UM0j0HDRK2Ny3xhYojoco/crHXFLWhjiQTJy
uWOcGdD+fPnR8sOsDWlttCVf6HsKLFcwXCJA/Tu+m7RoA1+KYoxW3j9pqQKBgBf/
AUyvBmg8K4Wwdet0nyQ5EtfdkuXSWbp1DC+WyTb+vcFCHFJx5EU3vjk7PQ3y/7a5
gX3GNjO+MEtBhA9YUU5jaOshaSqEwgiMCOTR8yd1fWR6Zj7NHF09CK/7wq82QnZP
EXkAwrx6JLvLh3Y0Jx5hty2i6C2wDoyA0WV1c935AoGAZ6sU8q8Vyy5qe8XLXNqo
iCfFJjMwpUd4pxOXX8n+SMjg/2iVlSDhU0ubMnWHFegEDc1eoJaBtmZxMkEt4htT
b2moXACYjt//BFjhrnW75eIYEV8d1DnFSNTlLaLK3yNORFq3YLkSOqTnzXhwUk1T
/NOiHq0OBsZjX3JRMJTyfYk=
-----END PRIVATE KEY-----
PEM;

    private ?string $serviceAccountPath = null;

    protected function tearDown(): void
    {
        if ($this->serviceAccountPath && is_file($this->serviceAccountPath)) {
            @unlink($this->serviceAccountPath);
        }
        parent::tearDown();
    }

    /** Write a fake service-account file and point the config at it. */
    private function configureFirebase(): void
    {
        $this->serviceAccountPath = tempnam(sys_get_temp_dir(), 'sa') . '.json';

        file_put_contents($this->serviceAccountPath, json_encode([
            'client_email' => 'tester@demo.iam.gserviceaccount.com',
            'private_key'  => self::TEST_PRIVATE_KEY,
            'token_uri'    => 'https://oauth2.googleapis.com/token',
        ]));

        config([
            'services.fcm.project_id'  => 'demo-project',
            'services.fcm.credentials' => $this->serviceAccountPath,
        ]);

        Cache::flush(); // drop any cached access token
    }

    public function test_it_sends_a_v1_push_notification(): void
    {
        $this->configureFirebase();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29-fake', 'expires_in' => 3600]),
            'fcm.googleapis.com/*'    => Http::response(['name' => 'projects/demo-project/messages/1']),
        ]);

        $ok = app(FCMService::class)->sendToDevice('device-token-123', 'Hello', 'World', ['order_id' => 7]);

        $this->assertTrue($ok);

        // the access token was requested with a signed JWT assertion
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'oauth2.googleapis.com')
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
        );

        // the push was sent to the FCM v1 endpoint with the right shape
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com/v1/projects/demo-project/messages:send')
                && $request->hasHeader('Authorization', 'Bearer ya29-fake')
                && $request['message']['token'] === 'device-token-123'
                && $request['message']['notification']['title'] === 'Hello'
                && $request['message']['data']['order_id'] === '7'; // numbers become strings
        });
    }

    public function test_it_skips_when_firebase_is_not_configured(): void
    {
        config(['services.fcm.project_id' => null]);
        Http::fake();

        $ok = app(FCMService::class)->sendToDevice('device-token-123', 'Hi', 'There');

        $this->assertFalse($ok);
        Http::assertNothingSent();
    }

    public function test_it_skips_when_device_token_is_empty(): void
    {
        Http::fake();

        $ok = app(FCMService::class)->sendToDevice(null, 'Hi', 'There');

        $this->assertFalse($ok);
        Http::assertNothingSent();
    }
}
