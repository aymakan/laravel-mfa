<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Http\Middleware;

use Aymakan\Mfa\MfaManager;
use Aymakan\Mfa\Verification\VerificationStoreInterface;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureMfaVerified
{
    public function __construct(
        protected MfaManager $mfaManager,
        protected VerificationStoreInterface $verificationStore
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // If MFA is globally disabled, pass through
        if (!$this->mfaManager->isEnabled()) {
            return $next($request);
        }
        $user = $request->user();

        // If no authenticated user, pass through (auth middleware should handle this)
        if ($user === null) {
            return $next($request);
        }

        $mustCompleteMfa = $this->mfaManager->userMustCompleteMfa($user);
        $hasMfaEnabled = $this->mfaManager->userHasMfaEnabled($user);
        $isVerified = $this->verificationStore->isVerified($user);

        if ($mustCompleteMfa) {
            // User must enroll and/or verify
            if (!$hasMfaEnabled) {
                return $this->mfaRequiredResponse();
            }
            if (!$isVerified) {
                return $this->mfaRequiredResponse();
            }
            return $next($request);
        }

        return $next($request);
    }

    /**
     * Generate the MFA required response.
     */
    protected function mfaRequiredResponse(): JsonResponse
    {
        $errorConfig = $this->mfaManager->getErrorConfig('mfa_required');

        return response()->json([
            'error' => [
                'code' => $errorConfig['code'],
                'message' => $errorConfig['message'],
            ],
        ], $errorConfig['status']);
    }
}
