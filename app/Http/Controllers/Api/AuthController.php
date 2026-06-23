<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Intervention\Image\Laravel\Facades\Image;   // image resizing (intervention/image)
use Spatie\Permission\Models\Role;

/**
 * Handles register / login / logout / me / refresh for the API.
 *
 * Authentication uses JSON Web Tokens (tymon/jwt-auth) through the
 * "api" guard that we defined in config/auth.php.
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     * Create a new "customer" or "rider" account and return a token.
     */
    public function register(Request $request): JsonResponse
    {
        // 1) Validate the input. On failure Laravel returns a 422 JSON automatically.
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'], // also send "password_confirmation"
            'phone'     => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'role'      => ['nullable', 'in:customer,rider'],            // people can only self-register as these two
            'avatar'    => ['nullable', 'image', 'max:2048'],            // optional image, max 2 MB
            'fcm_token' => ['nullable', 'string'],
        ]);

        // 2) Default the role to "customer" when none is provided.
        $role = $data['role'] ?? 'customer';

        // 3) If an avatar file was uploaded, resize + save it and keep its path.
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->storeAvatar($request->file('avatar'));
        }

        // 4) Create the user. The password is auto-hashed by the model's "hashed" cast.
        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'phone'     => $data['phone'] ?? null,
            'role'      => $role,
            'avatar'    => $avatarPath,
            'fcm_token' => $data['fcm_token'] ?? null,
            'is_active' => true,
        ]);

        // 5) Give the user the matching spatie role (findOrCreate avoids a crash
        //    if the database was not seeded yet). Used for permissions later.
        $user->assignRole(Role::findOrCreate($role, 'api'));

        // 6) Log the new user in immediately and hand back a token.
        $token = auth('api')->login($user);

        return $this->respondWithToken($token, 'Registration successful.', 201);
    }

    /**
     * POST /api/auth/login
     * Check the email + password and return a token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'     => ['required', 'email'],
            'password'  => ['required', 'string'],
            'fcm_token' => ['nullable', 'string'], // optional: update the device push token on login
        ]);

        // attempt() returns false when the email/password do not match.
        $token = auth('api')->attempt([
            'email'    => $credentials['email'],
            'password' => $credentials['password'],
        ]);

        if (! $token) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        // Disabled accounts are not allowed in.
        if (! $user->is_active) {
            auth('api')->logout();  // throw the just-issued token away
            return response()->json(['message' => 'Your account has been disabled.'], 403);
        }

        // Save the latest device token for push notifications, if one was sent.
        if (! empty($credentials['fcm_token'])) {
            $user->update(['fcm_token' => $credentials['fcm_token']]);
        }

        return $this->respondWithToken($token, 'Login successful.');
    }

    /**
     * GET /api/auth/me
     * Return the currently logged-in user (a valid token is required).
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();
        $user->load('roles:id,name'); // include the role name(s) so the apps know what to show

        return response()->json(['user' => $user]);
    }

    /**
     * POST /api/auth/logout
     * Invalidate the current token (a valid token is required).
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout(); // blacklists the token so it can't be reused

        return response()->json(['message' => 'Successfully logged out.']);
    }

    /**
     * POST /api/auth/refresh-token
     * Swap the current (still valid) token for a fresh one.
     */
    public function refresh(): JsonResponse
    {
        // Issue a brand new token. The old token is invalidated by this call,
        // so we return the token only (the client already knows who it is).
        $token = auth('api')->refresh();

        return response()->json([
            'message'      => 'Token refreshed.',
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    /**
     * Build one consistent JSON response containing the token + the user.
     */
    protected function respondWithToken(string $token, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'message'      => $message,
            'access_token' => $token,
            'token_type'   => 'bearer',
            // token lifetime in seconds (the TTL is set in minutes, so multiply by 60)
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => auth('api')->user(),
        ], $status);
    }

    /**
     * Save an uploaded avatar: crop/resize it to a 300x300 square and store it
     * on the "public" disk. Returns the relative path, e.g. "avatars/user_x.jpg".
     */
    private function storeAvatar($file): string
    {
        // unique name so two users never overwrite each other's file
        $relativePath = 'avatars/' . uniqid('user_') . '.jpg';
        $absolutePath = storage_path('app/public/' . $relativePath);

        // make sure the "avatars" folder exists
        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        // read the upload -> crop to a 300x300 square -> save as jpg (uses GD via Intervention Image)
        Image::read($file->getRealPath())->cover(300, 300)->save($absolutePath);

        return $relativePath;
    }
}
