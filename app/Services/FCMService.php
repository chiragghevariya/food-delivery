<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications through Firebase Cloud Messaging (FCM) HTTP v1 API.
 *
 * IMPORTANT (for a basic developer):
 * - The old "legacy" FCM API used a single FIREBASE_SERVER_KEY. Google has shut
 *   that down. The new "v1" API used here needs TWO things instead:
 *     1) your Firebase PROJECT ID            -> FIREBASE_PROJECT_ID
 *     2) a service-account JSON key file      -> FIREBASE_CREDENTIALS
 *   (download the JSON from: Firebase console -> Project settings -> Service accounts)
 * - If Firebase is not configured yet, these methods quietly do nothing, so your
 *   order flow keeps working in local development.
 */
class FCMService
{
    /**
     * Send a push notification to ONE device.
     *
     * @param  string|null  $fcmToken  the device token (from the mobile app)
     * @param  string       $title     notification title
     * @param  string       $body      notification message
     * @param  array        $data      optional extra key/value data
     * @return bool          true if FCM accepted the message
     */
    public function sendToDevice(?string $fcmToken, string $title, string $body, array $data = [], string $target = 'customer'): bool
    {
        // No device token -> nothing to send.
        if (empty($fcmToken)) {
            return false;
        }

        $projectId = $target === 'rider'
            ? config('services.fcm.rider_project_id')
            : config('services.fcm.project_id');

        // Firebase not set up yet -> skip quietly (handy during development).
        if (empty($projectId)) {
            Log::info("FCM not configured (no project id for target: $target) — skipping push.", ['title' => $title]);
            return false;
        }

        try {
            $accessToken = $this->getAccessToken($target);

            if (! $accessToken) {
                return false;
            }

            // FCM v1 requires every value inside "data" to be a string.
            $stringData = array_map(fn ($value) => (string) $value, $data);

            // Call the FCM v1 endpoint with the Laravel HTTP client.
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token'        => $fcmToken,
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                        ],
                        'data' => $stringData,
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('FCM push failed.', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            // Never let a notification error break the actual request (e.g. an order update).
            Log::error('FCM push error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a short-lived OAuth2 access token for the FCM v1 API using the
     * service-account JSON. The token is cached for ~55 minutes so we don't
     * ask Google for a new one on every notification.
     */
    protected function getAccessToken(string $target = 'customer'): ?string
    {
        $cacheKey = "fcm_access_token_{$target}";
        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($target) {
            $credentialsPath = $target === 'rider'
                ? config('services.fcm.rider_credentials')
                : config('services.fcm.credentials');

            if (! $credentialsPath || ! is_file($credentialsPath)) {
                Log::warning("FCM service-account file not found for target: $target.", ['path' => $credentialsPath]);
                return null;
            }

            $serviceAccount = json_decode(file_get_contents($credentialsPath), true);

            // 1) Build a JWT "assertion" that proves we own the service account.
            $now    = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss'   => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => $serviceAccount['token_uri'],
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            // 2) Sign "header.claims" with the service account's private key (RS256).
            $toSign = $header . '.' . $claims;
            openssl_sign($toSign, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = $toSign . '.' . $this->base64Url($signature);

            // 3) Exchange that JWT for a real access token.
            $response = Http::asForm()->post($serviceAccount['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed()) {
                Log::warning('Could not get FCM access token.', ['body' => $response->body()]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    /** URL-safe base64 without padding (the format JWTs use). */
    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience wrappers — one per order event (notifies the customer)
    |--------------------------------------------------------------------------
    */

    /** Restaurant accepted the order (wired up in the restaurant phase). */
    public function notifyOrderAccepted(Order $order): void
    {
        $this->notifyCustomer($order, 'Order accepted 🎉', 'The restaurant is preparing your order.');
    }

    /** A rider accepted the delivery. */
    public function notifyRiderAssigned(Order $order): void
    {
        $this->notifyCustomer($order, 'Rider on the way 🛵', 'A rider has accepted your delivery.');
    }

    /** The rider picked the order up from the restaurant. */
    public function notifyOrderPickedUp(Order $order): void
    {
        $this->notifyCustomer($order, 'Order picked up 🛍️', 'Your food is on the way!');
    }

    /** The rider delivered the order. */
    public function notifyOrderDelivered(Order $order): void
    {
        $this->notifyCustomer($order, 'Order delivered ✅', 'Enjoy your meal! Thanks for ordering.');
    }

    /** Shared helper: send a push to the order's customer (if they have a token). */
    private function notifyCustomer(Order $order, string $title, string $body): void
    {
        $customer = $order->customer; // the User who placed the order

        if (! $customer || empty($customer->fcm_token)) {
            return;
        }

        $this->sendToDevice($customer->fcm_token, $title, $body, [
            'order_id' => (string) $order->id,
            'status'   => $order->status->value,
        ]);
    }
}
