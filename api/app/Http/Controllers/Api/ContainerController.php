<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\ContainerEvent;
use App\Models\AuthorizationArtifact;
use App\Models\Terminal;
use App\Services\CryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ContainerController extends Controller
{
    protected CryptoService $crypto;

    public function __construct(CryptoService $crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * Get all containers.
     */
    public function index(Request $request)
    {
        $query = Container::with('terminal');

        if ($request->has('terminal_id')) {
            $query->where('terminal_id', $request->terminal_id);
        }

        $containers = $query->get();

        return response()->json([
            'success' => true,
            'data' => $containers,
        ]);
    }

    /**
     * Get a specific container.
     */
    public function show($containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)
            ->with('terminal')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $container,
        ]);
    }

    /**
     * Inspect a container.
     */
    public function inspect(Request $request, $containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)->firstOrFail();
        $user = Auth::user();

        // Log the event
        $event = new ContainerEvent([
            'container_id' => $container->id,
            'operator_id' => $user->id,
            'action' => 'INSPECT',
            'metadata' => ['user_agent' => $request->userAgent(), 'ip' => $request->ip()],
        ]);
        $event->save();

        // Update container status to INSPECTED
        $container->status = 'INSPECTED';
        $container->save();

        return response()->json([
            'success' => true,
            'message' => 'Container inspected successfully',
            'data' => $container,
        ]);
    }

    /**
     * Hold a container.
     */
    public function hold(Request $request, $containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)->firstOrFail();
        $user = Auth::user();

        // Check user role - only supervisor and above can hold
        $role = $user->roles->first()?->name;
        if (!in_array($role, ['supervisor', 'security_admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions to hold containers',
            ], 403);
        }

        // Log the event
        $event = new ContainerEvent([
            'container_id' => $container->id,
            'operator_id' => $user->id,
            'action' => 'HOLD',
            'metadata' => ['reason' => $request->input('reason', 'Hold for inspection')],
        ]);
        $event->save();

        $container->status = 'HELD';
        $container->save();

        return response()->json([
            'success' => true,
            'message' => 'Container held successfully',
            'data' => $container,
        ]);
    }

    /**
     * Release a container.
     *
     * VULNERABILITY: CRYPTO-05
     * The release endpoint trusts the decrypted authorization artifact
     * without proper server-side role enforcement.
     *
     * VULNERABILITY: CRYPTO-06
     * AES-CBC without integrity protection allows ciphertext manipulation.
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
        Log::channel('audit')->info('CONTAINER_RELEASE_ATTEMPT', [
            'user_id' => $user->id,
            'container' => $containerNumber,
            'terminal' => $container->terminal->code,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => 'RELEASE',
        ]);

        $role = $user->roles->first()?->name;

        // Get authorization artifact from header
        $authArtifact = $request->header('X-Terminal-Authorization');

        // VULNERABILITY: CRYPTO-05
        // Server-side role check exists BUT is overridden by the authorization artifact
        // This is the core vulnerability - the application trusts the decrypted
        // authorization artifact to override server-side permissions
        if ($authArtifact) {
            try {
                // Decrypt the artifact
                $artifactData = $this->crypto->decryptArtifact($authArtifact);

                // VULNERABILITY: CRYPTO-05
                // Trust the decrypted artifact's action completely
                // This means if someone manipulates the ciphertext,
                // the server will trust the decrypted action field
                $authorizedAction = $artifactData['action'] ?? 'UNKNOWN';

                // VULNERABILITY: AUTH-01
                // The decrypted action is trusted as the authorization decision.
                // The endpoint still requires RELEASE in the artifact, so the
                // intended lesson is ciphertext integrity—not omission of the
                // action check. A secure implementation checks server-side role
                // and resource policy independently of this client artifact.
                if ($authorizedAction !== 'RELEASE') {
                    Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                        'user_id' => $user->id,
                        'reason' => 'Artifact action does not authorize release',
                        'action' => $authorizedAction,
                        'container' => $containerNumber,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Authorization artifact does not permit release',
                    ], 403);
                }

                // Log authorization success based on artifact
                Log::channel('audit')->info('AUTHORIZATION_SUCCESS', [
                    'user_id' => $user->id,
                    'action' => $authorizedAction,
                    'artifact_data' => $artifactData,
                ]);

                // VULNERABILITY: CRYPTO-07
                // The role check is bypassed because the authorization artifact
                // is trusted to override server-side permissions
                // Even if the user is an operator, if the artifact says RELEASE,
                // the release proceeds

            } catch (\Exception $e) {
                Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid authorization artifact',
                ], 403);
            }
        }

        // VULNERABILITY: CRYPTO-08
        // If no artifact provided, check server-side role
        // But if artifact IS provided, the artifact's action is trusted
        if (!$authArtifact) {
            // No artifact provided - use server-side role enforcement
            if (!in_array($role, ['supervisor', 'security_admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions - operator role cannot release containers',
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
        Log::channel('audit')->info('CONTAINER_RELEASE', [
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