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
        $role = $user->roles->first()?->name;
        $authArtifact = $request->header('X-Terminal-Authorization');
        $secureMode = config('crypto.mode', 'vulnerable') === 'secure';

        Log::channel('audit')->info('CONTAINER_RELEASE_ATTEMPT', [
            'user_id' => $user->id,
            'container' => $containerNumber,
            'terminal' => $container->terminal->code,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => 'RELEASE',
            'mode' => $secureMode ? 'secure' : 'vulnerable',
        ]);

        if ($secureMode) {
            // SECURE: authorization is decided from the authenticated identity
            // and server-side role policy before any client artifact is considered.
            if (!in_array($role, ['supervisor', 'security_admin'], true)) {
                Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                    'user_id' => $user->id,
                    'container' => $containerNumber,
                    'reason' => 'role_not_authorized',
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions - operator role cannot release containers',
                ], 403);
            }

            if ($authArtifact) {
                try {
                    $artifactData = app(\App\Services\SecureCryptoService::class)->decryptArtifact($authArtifact);

                    $matchesRequest = (int) ($artifactData['operator_id'] ?? 0) === (int) $user->id
                        && (string) ($artifactData['terminal'] ?? '') === (string) $container->terminal_id
                        && (string) ($artifactData['container'] ?? '') === (string) $container->id
                        && ($artifactData['action'] ?? '') === 'RELEASE';

                    if (!$matchesRequest) {
                        throw new \RuntimeException('Artifact does not match authenticated release context');
                    }

                    Log::channel('audit')->info('AUTHORIZATION_SUCCESS', [
                        'user_id' => $user->id,
                        'container' => $containerNumber,
                        'mode' => 'secure',
                    ]);
                } catch (\Throwable $e) {
                    Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                        'user_id' => $user->id,
                        'container' => $containerNumber,
                        'reason' => 'invalid_secure_artifact',
                        'ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid authorization artifact',
                    ], 403);
                }
            }
        } elseif ($authArtifact) {
            // VULNERABILITY: CRYPTO-05 / CRYPTO-06
            // The vulnerable mode trusts the decrypted action and skips the
            // server-side role check whenever an artifact is supplied.
            try {
                $artifactData = $this->crypto->decryptArtifact($authArtifact);
                $authorizedAction = $artifactData['action'] ?? 'UNKNOWN';

                if ($authorizedAction !== 'RELEASE') {
                    Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                        'user_id' => $user->id,
                        'action' => $authorizedAction,
                        'container' => $containerNumber,
                        'ip' => $request->ip(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Authorization artifact does not permit release',
                    ], 403);
                }

                Log::channel('audit')->info('AUTHORIZATION_SUCCESS', [
                    'user_id' => $user->id,
                    'action' => $authorizedAction,
                    'container' => $containerNumber,
                ]);
            } catch (\Throwable $e) {
                Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                    'user_id' => $user->id,
                    'reason' => 'invalid_vulnerable_artifact',
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid authorization artifact',
                ], 403);
            }
        } elseif (!in_array($role, ['supervisor', 'security_admin'], true)) {
            Log::channel('audit')->warning('AUTHORIZATION_FAILURE', [
                'user_id' => $user->id,
                'container' => $containerNumber,
                'reason' => 'role_not_authorized',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions - operator role cannot release containers',
            ], 403);
        }

        if ($container->status === 'RELEASED') {
            return response()->json([
                'success' => false,
                'message' => 'Container already released',
            ], 400);
        }

        // All release operations only mutate the local training database.
        $container->status = 'RELEASED';
        $container->save();

        ContainerEvent::create([
            'container_id' => $container->id,
            'operator_id' => $user->id,
            'action' => 'RELEASE',
            'metadata' => [
                'reason' => $request->input('reason', 'scheduled release'),
                'authorization_artifact' => $authArtifact ? 'provided' : 'none',
                'role' => $role,
                'mode' => $secureMode ? 'secure' : 'vulnerable',
            ],
        ]);

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
