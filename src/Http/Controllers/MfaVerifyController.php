<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Http\Controllers;

use Aymakan\Mfa\MfaManager;
use Aymakan\Mfa\Verification\VerificationStoreInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

class MfaVerifyController extends Controller
{
    public function __construct(
        protected MfaManager $mfaManager,
        protected VerificationStoreInterface $verificationStore
    ) {
    }

    /**
     * Verify MFA code for the authenticated user.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        // Check rate limiting
        $rateLimitKey = $this->getRateLimitKey($user);

        if ($this->isRateLimited($rateLimitKey)) {
            return $this->rateLimitedResponse();
        }

        $otp = $request->input('code');

        // Verify the OTP
        if (!$this->mfaManager->verify($user, $otp)) {
            RateLimiter::hit($rateLimitKey, $this->getDecaySeconds());

            return $this->invalidCodeResponse();
        }

        // Clear rate limiter on success
        RateLimiter::clear($rateLimitKey);

        // Mark MFA as verified for this session
        $this->verificationStore->markVerified($user);

        return response()->json([
            'data' => [
                'verified' => true,
                'message' => 'MFA verification successful.',
            ],
        ]);
    }

    /**
     * Disable MFA for the authenticated user.
     * Requires current OTP for security.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        // Check rate limiting
        $rateLimitKey = $this->getRateLimitKey($user);

        if ($this->isRateLimited($rateLimitKey)) {
            return $this->rateLimitedResponse();
        }

        $otp = $request->input('code');

        // Verify the OTP before disabling
        if (!$this->mfaManager->verify($user, $otp)) {
            RateLimiter::hit($rateLimitKey, $this->getDecaySeconds());

            return $this->invalidCodeResponse();
        }

        // Clear rate limiter
        RateLimiter::clear($rateLimitKey);

        // Disable MFA
        $this->mfaManager->disable($user);

        // Clear verification state
        $this->verificationStore->clear($user);

        return response()->json([
            'data' => [
                'disabled' => true,
                'message' => 'MFA has been disabled.',
            ],
        ]);
    }

    /**
     * Get the rate limit key for the user.
     */
    protected function getRateLimitKey($user): string
    {
        return 'mfa_verify:' . $user->getAuthIdentifier();
    }

    /**
     * Check if the user is rate limited.
     */
    protected function isRateLimited(string $key): bool
    {
        $config = $this->mfaManager->getRateLimitConfig();

        return RateLimiter::tooManyAttempts($key, $config['max_attempts']);
    }

    /**
     * Get the decay time in seconds.
     */
    protected function getDecaySeconds(): int
    {
        $config = $this->mfaManager->getRateLimitConfig();

        return $config['decay_minutes'] * 60;
    }

    /**
     * Return rate limited response.
     */
    protected function rateLimitedResponse(): JsonResponse
    {
        $errorConfig = $this->mfaManager->getErrorConfig('mfa_rate_limited');

        return response()->json([
            'error' => [
                'code' => $errorConfig['code'],
                'message' => $errorConfig['message'],
            ],
        ], $errorConfig['status']);
    }

    /**
     * Return invalid code response.
     */
    protected function invalidCodeResponse(): JsonResponse
    {
        $errorConfig = $this->mfaManager->getErrorConfig('mfa_invalid');

        return response()->json([
            'error' => [
                'code' => $errorConfig['code'],
                'message' => $errorConfig['message'],
            ],
        ], $errorConfig['status']);
    }
}
