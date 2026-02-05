<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Http\Controllers;

use Aymakan\Mfa\MfaManager;
use Aymakan\Mfa\Verification\VerificationStoreInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MfaStatusController extends Controller
{
    public function __construct(
        protected MfaManager $mfaManager,
        protected VerificationStoreInterface $verificationStore
    ) {
    }

    /**
     * Get MFA status for the authenticated user.
     */
    public function __invoke(Request $request): JsonResponse
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

        $credential = $this->mfaManager->getEnabledCredential($user);
        $isEnabled = $credential !== null;
        $isVerified = false;
        $verifiedAt = null;

        try {
            $isVerified = $isEnabled && $this->verificationStore->isVerified($user);
            if ($isVerified) {
                $verifiedAt = $this->verificationStore->getVerifiedAt($user);
            }
        } catch (\Throwable $e) {
            // Session may not be available for API-only (Bearer) requests; treat as not verified
        }

        $response = [
            'data' => [
                'mfa_enabled' => $isEnabled,
                'mfa_verified' => $isVerified,
                'mfa_type' => $isEnabled ? $credential->type : null,
                'mfa_required' => $this->mfaManager->userMustCompleteMfa($user),
            ],
        ];

        if ($verifiedAt !== null) {
            $response['data']['verified_at'] = $verifiedAt->format(\DateTimeInterface::ATOM);
        }

        return response()->json($response);
    }
}
