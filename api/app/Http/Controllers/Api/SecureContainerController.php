<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\ContainerEvent;
use App\Models\AuthorizationArtifact;
use App\Models\Terminal;
use App\Services\SecureCryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SecureContainerController extends Controller
{
    protected SecureCryptoService $crypto;

    public function __construct(SecureCryptoService $crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * Release a container using secure cryptography.
     *
     * SECURE:
     * - Uses AES-GCM with unique nonce and authentication tag
     * - Server-side role enforcement is primary - artifact is supplementary
     * - Rejects tampered ciphertexts
     * - Validates artifact expiration
     *
     * @param Request $request
     * @param string $containerNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function release(Request $request, $containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)->firstOrFail();
        $user = Auth::user();

        // Log the release attempt
        Log::channel('audit')->info('SECURE_CONTAINER_RELEASE_ATTEMPT', [
            'user_id' => $user->id,
            'container' => $containerNumber,
            'terminal' => $container->terminal->code,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => 'RELEASE',
        ]);

        $role = $user->roles->first()?->name;

        // SECURE: Server-side role check is PRIMARY
        // The artifact is SUPPLEMENTARY - it provides audit trail but
        // does NOT override server-side permissions
        if (!in_array($role, ['supervisor', 'security_admin'])) {
            Log::channel('audit')->warning('SECURE_AUTHORIZATION_FAILURE', [
                'user_id' => $user->id,
                'reason' => 'Insufficient server-side permissions',
                'role' => $role,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions - operator role cannot release containers',
            ], 403);
        }

        // Get authorization artifact from header (optional, for audit trail)
        $authArtifact = $request->header('X-Terminal-Authorization');

        // If artifact provided, validate it but don't trust it for authorization
        if ($authArtifact) {
            try {
                $artifactData = $this->crypto->decryptArtifact($authArtifact);

                // Log artifact validation success
                Log::channel('audit')->info('SECURE_ARTIFACT_VALIDATED', [
                    'user_id' => $user->id,
                    'artifact_action' => $artifactData['action'],
                    'artifact_operator_id' => $artifactData['operator_id'],
                ]);

                // SECURE: Verify artifact matches the actual user
                if ($artifactData['operator_id'] !== $user->id) {
                    Log::channel('audit')->warning('SECURE_ARTIFACT_USER_MISMATCH', [
                        'user_id' => $user->id,
                        'artifact_operator_id' => $artifactData['operator_id'],
                    ]);
                    // Note: We log but don't reject - artifact is supplementary
                }

            } catch (\Exception $e) {
                Log::channel('audit')->warning('SECURE_ARTIFACT_VALIDATION_FAILED', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid authorization artifact: ' . $e->getMessage(),
                ], 403);
            }
        }

        // Check if container is already released
        if ($container->status === 'RELEASED') {
            return response()->json([
                'success' => false,
                'message' => 'Container already released',
            ], 400);
        }

        // Update container status
        $container->status = 'RELEASED';
        $container->save();

        // Log successful release
        $event = new ContainerEvent([
            'container_id' => $container->id,
            'operator_id' => $user->id,
            'action' => 'RELEASE',
            'metadata' => [
                'reason' => $request->input('reason', 'scheduled release'),
                'authorization_artifact' => $authArtifact ? 'provided' : 'none',
                'role' => $role,
            ],
        ]);
        $event->save();

        // Log the release
        Log::channel('audit')->info('SECURE_CONTAINER_RELEASE', [
            'user_id' => $user->id,
            'container' => $containerNumber,
            'terminal' => $container->terminal->code,
            'status' => 'RELEASED',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Container released successfully',
            'data' => $container,
        ]);
    }
}