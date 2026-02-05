<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Http\Controllers;

use Aymakan\Mfa\MfaManager;
use Aymakan\Mfa\Models\MfaCredential;
use Aymakan\Mfa\Verification\VerificationStoreInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

class MfaEnrollController extends Controller
{
    public function __construct(
        protected MfaManager $mfaManager,
        protected VerificationStoreInterface $verificationStore
    ) {
    }

    /**
     * Start MFA enrollment.
     * Generates a new secret and returns the provisioning URI for QR code.
     */
    public function start(Request $request): JsonResponse
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

        // Check if user already has MFA enabled
        if ($this->mfaManager->userHasMfaEnabled($user)) {
            return response()->json([
                'error' => [
                    'code' => 'MFA_ALREADY_ENABLED',
                    'message' => 'MFA is already enabled for this account.',
                ],
            ], 409);
        }

        // Get or create a pending credential
        $credential = $this->mfaManager->getOrCreatePendingCredential($user);

        // Get the provisioning URI
        $provisioningUri = $this->mfaManager->getProvisioningUri($user);

        return response()->json([
            'data' => [
                'provisioning_uri' => $provisioningUri,
                'type' => $credential->type,
                'message' => 'Scan the QR code with your authenticator app, then confirm with a code.',
            ],
        ]);
    }

    /**
     * Confirm MFA enrollment.
     * Verifies the OTP and enables MFA for the user.
     */
    public function confirm(Request $request): JsonResponse
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

        // Check if user already has MFA enabled
        if ($this->mfaManager->userHasMfaEnabled($user)) {
            return response()->json([
                'error' => [
                    'code' => 'MFA_ALREADY_ENABLED',
                    'message' => 'MFA is already enabled for this account.',
                ],
            ], 409);
        }

        // Check rate limiting
        $rateLimitKey = $this->getRateLimitKey($user);

        if ($this->isRateLimited($rateLimitKey)) {
            return $this->rateLimitedResponse();
        }

        $otp = $request->input('code');

        // Verify and enable
        if (!$this->mfaManager->verifyAndEnable($user, $otp)) {
            RateLimiter::hit($rateLimitKey, $this->getDecaySeconds());

            return $this->invalidCodeResponse();
        }

        // Clear rate limiter on success
        RateLimiter::clear($rateLimitKey);

        // Mark MFA as verified for this session
        $this->verificationStore->markVerified($user);

        return response()->json([
            'data' => [
                'enabled' => true,
                'message' => 'MFA has been enabled successfully.',
            ],
        ]);
    }

    /**
     * Cancel MFA enrollment.
     * Removes the pending credential.
     */
    public function cancel(Request $request): JsonResponse
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

        // Delete any pending (not enabled) credentials
        $deleted = MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('enabled_at')
            ->delete();

        return response()->json([
            'data' => [
                'cancelled' => $deleted > 0,
                'message' => $deleted > 0
                    ? 'MFA enrollment has been cancelled.'
                    : 'No pending enrollment found.',
            ],
        ]);
    }

    /**
     * Get the rate limit key for the user.
     */
    protected function getRateLimitKey($user): string
    {
        return 'mfa_enroll:' . $user->getAuthIdentifier();
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
