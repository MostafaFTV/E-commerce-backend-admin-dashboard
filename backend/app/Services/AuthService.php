<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected CartService $cartService,
        protected OtpService $otpService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    /**
     * Validate registration data at the controller/request layer and
     * store the registration payload inside the OTP metadata until
     * the phone number has been successfully verified.
     *
     * The password is encrypted before it is stored in OTP metadata.
     */
    public function requestRegistrationOtp(array $data): array
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize Optional Email
        |--------------------------------------------------------------------------
        */

        $email = isset($data['email'])
            && is_string($data['email'])
            ? trim($data['email'])
            : null;

        if ($email === '') {
            $email = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Defensive Duplicate Checks
        |--------------------------------------------------------------------------
        |
        | RegisterRequest already validates these values, but checking again
        | here prevents a race condition between OTP request and registration.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $email !== null
            && User::where(
                'email',
                $email
            )->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'The email has already been taken.',
                ],
            ]);
        }

        if (
            User::where(
                'phone',
                $data['phone']
            )->exists()
        ) {
            throw ValidationException::withMessages([
                'phone' => [
                    'The phone has already been taken.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Encrypt Password Before OTP Storage
        |--------------------------------------------------------------------------
        |
        | Never store the raw registration password in Redis/cache metadata.
        | Laravel encryption is reversible, unlike hashing, because we need
        | the original password later when the user is actually created.
        |
        |--------------------------------------------------------------------------
        */

        $encryptedPassword = Crypt::encryptString(
            $data['password']
        );

        return $this->otpService->send(
            'register',
            $data['phone'],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $data['phone'],
                'password_encrypted' => $encryptedPassword,
            ]
        );
    }

    /**
     * Verify registration OTP and create the customer.
     */
    public function verifyRegistrationOtp(
        string $mobile,
        string $code
    ): array {
        $payload = $this->otpService->verify(
            'register',
            $mobile,
            $code
        );

        $metadata = $payload['metadata'] ?? null;

        if (! is_array($metadata)) {
            throw ValidationException::withMessages([
                'code' => [
                    'The registration request is invalid or expired.',
                ],
            ]);
        }

        $firstName = trim(
            (string) ($metadata['first_name'] ?? '')
        );

        $lastName = trim(
            (string) ($metadata['last_name'] ?? '')
        );

        $rawEmail = $metadata['email'] ?? null;

        $email = is_string($rawEmail)
            ? trim($rawEmail)
            : null;

        if ($email === '') {
            $email = null;
        }

        $phone = trim(
            (string) (
                $metadata['phone']
                ?? $mobile
            )
        );

        $encryptedPassword = (string) (
            $metadata['password_encrypted']
            ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Stored Registration Metadata
        |--------------------------------------------------------------------------
        |
        | Email is intentionally optional.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $firstName === ''
            || $lastName === ''
            || $phone === ''
            || $encryptedPassword === ''
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'The registration request is invalid or expired.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Decrypt Registration Password
        |--------------------------------------------------------------------------
        */

        try {
            $password = Crypt::decryptString(
                $encryptedPassword
            );
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'code' => [
                    'The registration request is invalid or expired.',
                ],
            ]);
        }

        if ($password === '') {
            throw ValidationException::withMessages([
                'code' => [
                    'The registration request is invalid or expired.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Final Duplicate Protection
        |--------------------------------------------------------------------------
        |
        | The account may have been created after the OTP was requested.
        |
        |--------------------------------------------------------------------------
        */

        if (
            $email !== null
            && User::where(
                'email',
                $email
            )->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'The email has already been taken.',
                ],
            ]);
        }

        if (
            User::where(
                'phone',
                $phone
            )->exists()
        ) {
            throw ValidationException::withMessages([
                'phone' => [
                    'The phone has already been taken.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create User
        |--------------------------------------------------------------------------
        |
        | Email is optional and may be null.
        | User::$casts hashes the password automatically.
        |
        |--------------------------------------------------------------------------
        */

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assign Customer Role
        |--------------------------------------------------------------------------
        */

        $customerRole = Role::where(
            'slug',
            'customer'
        )->first();

        if ($customerRole) {
            $user->roles()->sync([
                $customerRole->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Sanctum Token
        |--------------------------------------------------------------------------
        */

        $token = $user->createToken(
            'auth-token'
        )->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login(
        string $identifier,
        string $password,
        ?string $guestToken = null
    ): array {
        $user = User::where(
            'email',
            $identifier
        )
            ->orWhere(
                'phone',
                $identifier
            )
            ->first();

        if (
            ! $user
            || ! Hash::check(
                $password,
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'identifier' => [
                    'Invalid credentials.',
                ],
            ]);
        }

        if ($user->status->value !== 'active') {
            throw ValidationException::withMessages([
                'identifier' => [
                    'Account is not active.',
                ],
            ]);
        }

        $this->cartService->mergeGuestCart(
            $user,
            $guestToken
        );

        $token = $user->createToken(
            'auth-token'
        )->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Login With OTP
    |--------------------------------------------------------------------------
    */

    public function requestLoginOtp(string $mobile): array
    {
        $mobile = trim($mobile);

        $expiresIn = max(
            30,
            (int) config('smsir.otp.expires', 120)
        );

        $retryAfter = max(
            1,
            (int) config('smsir.otp.resend_cooldown', 60)
        );

        $user = User::where('phone', $mobile)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return [
                'expires_in' => $expiresIn,
                'retry_after' => $retryAfter,
            ];
        }

        return $this->otpService->send(
            'login',
            $mobile
        );
    }

    public function verifyLoginOtp(
        string $mobile,
        string $code,
        ?string $guestToken = null
    ): array {
        $mobile = trim($mobile);

        $user = User::where('phone', $mobile)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => [
                    'The verification code is invalid or expired.',
                ],
            ]);
        }

        $this->otpService->verify(
            'login',
            $mobile,
            $code
        );

        $this->cartService->mergeGuestCart(
            $user,
            $guestToken
        );

        $token = $user->createToken(
            'auth-token'
        )->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | Admin Login
    |--------------------------------------------------------------------------
    */

    public function adminLogin(
        string $identifier,
        string $password
    ): array {
        $user = User::where(
            'email',
            $identifier
        )
            ->orWhere(
                'phone',
                $identifier
            )
            ->first();

        if (
            ! $user
            || ! Hash::check(
                $password,
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'identifier' => [
                    'Invalid credentials.',
                ],
            ]);
        }

        $user->load(
            'roles.permissions'
        );

        $staffRoles = [
            'super_admin',
            'admin',
            'accountant',
            'support',
        ];

        $isStaff = $user
            ->roles
            ->pluck('slug')
            ->intersect(
                $staffRoles
            )
            ->isNotEmpty();

        if (! $isStaff) {
            throw ValidationException::withMessages([
                'identifier' => [
                    'Invalid credentials.',
                ],
            ]);
        }

        if (
            $user->status->value
            !== 'active'
        ) {
            throw ValidationException::withMessages([
                'identifier' => [
                    'Account is not active.',
                ],
            ]);
        }

        $token = $user
            ->createToken(
                'admin-token'
            )
            ->plainTextToken;

        return [
            'user' =>
                $user,

            'token' =>
                $token,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    /**
     * Request password-reset OTP.
     *
     * The response is intentionally identical whether the account exists
     * or not, preventing account enumeration.
     */
    public function requestForgotPasswordOtp(
        string $mobile
    ): array {
        $mobile = trim($mobile);

        $expiresIn = max(
            30,
            (int) config(
                'smsir.otp.expires',
                120
            )
        );

        $retryAfter = max(
            1,
            (int) config(
                'smsir.otp.resend_cooldown',
                60
            )
        );

        $user = User::where(
            'phone',
            $mobile
        )
            ->where(
                'status',
                'active'
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Account Enumeration Protection
        |--------------------------------------------------------------------------
        |
        | Never reveal whether the phone number belongs to an account.
        |
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            return [
                'expires_in' => $expiresIn,
                'retry_after' => $retryAfter,
            ];
        }

        return $this->otpService->send(
            'forgot-password',
            $mobile
        );
    }

    /**
     * Reset password with OTP.
     */
    public function resetPassword(
        string $mobile,
        string $code,
        string $password
    ): void {
        $mobile = trim($mobile);

        /*
        |--------------------------------------------------------------------------
        | Verify OTP
        |--------------------------------------------------------------------------
        |
        | OtpService consumes the OTP after successful verification.
        |
        |--------------------------------------------------------------------------
        */

        $this->otpService->verify(
            'forgot-password',
            $mobile,
            $code
        );

        /*
        |--------------------------------------------------------------------------
        | Find Active Account
        |--------------------------------------------------------------------------
        */

        $user = User::where(
            'phone',
            $mobile
        )
            ->where(
                'status',
                'active'
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Defensive Failure
        |--------------------------------------------------------------------------
        |
        | A valid forgot-password OTP should only exist for a real active
        | account. This protects against account state changes between OTP
        | issuance and password reset.
        |
        |--------------------------------------------------------------------------
        */

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => [
                    'The verification code is invalid or expired.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Password
        |--------------------------------------------------------------------------
        |
        | User::$casts hashes the password automatically.
        |
        |--------------------------------------------------------------------------
        */

        $user->update([
            'password' => $password,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Revoke All Existing Tokens
        |--------------------------------------------------------------------------
        |
        | A password reset invalidates all currently active sessions.
        |
        |--------------------------------------------------------------------------
        */

        $user->tokens()->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    /**
     * Request OTP for an authenticated password change.
     */
    public function requestChangePasswordOtp(
        User $user,
        string $currentPassword
    ): array {
        if (
            ! Hash::check(
                $currentPassword,
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'The current password is incorrect.',
                ],
            ]);
        }

        if (
            ! is_string($user->phone)
            || trim($user->phone) === ''
        ) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'A verified phone number is required to change the password.',
                ],
            ]);
        }

        return $this->otpService->send(
            'change-password',
            $user->phone
        );
    }

    /**
     * Confirm password change with OTP.
     */
    public function confirmChangePassword(
        User $user,
        string $code,
        string $password
    ): void {
        $this->otpService->verify(
            'change-password',
            $user->phone,
            $code
        );

        $user->update([
            'password' => $password,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Revoke All Existing Tokens
        |--------------------------------------------------------------------------
        |
        | This intentionally logs the user out from all devices.
        |
        |--------------------------------------------------------------------------
        */

        $user->tokens()->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout(User $user): void
    {
        $user
            ->currentAccessToken()
            ?->delete();
    }
}