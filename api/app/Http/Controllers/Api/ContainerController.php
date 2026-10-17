<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Container;
use App\Models\ContainerEvent;
use App\Services\AuditLogger;
use App\Services\FlagService;
use App\Services\SecureCryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContainerController extends Controller
{
    public function index(Request $request)
    {
        $query = Container::with('terminal');
        if ($request->filled('terminal_id')) {
            $query->where('terminal_id', $request->input('terminal_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show($containerNumber)
    {
        $container = Container::with('terminal')
            ->where('container_number', $containerNumber)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $container]);
    }

    public function inspect(Request $request, $containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)->firstOrFail();
        $user = $request->user();

        ContainerEvent::create([
            'container_id' => $container->id,
            'operator_id' => $user->id,
            'action' => 'INSPECT',
            'metadata' => ['user_agent' => $request->userAgent(), 'ip' => $request->ip()],
        ]);

        $container->status = 'INSPECTED';
        $container->save();

        $this->recordAudit($request, 'CONTAINER_INSPECTED', $container, ['result' => 'success']);

        return response()->json([
            'success' => true,
            'message' => 'Container inspected successfully',
            'data' => $container->load('terminal'),
            'flag' => (!$secureMode && $authArtifact) ? app(FlagService::class)->valueFor('FLAG-08') : null,
        ]);
    }

    public function hold(Request $request, $containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)->firstOrFail();
        $user = $request->user();
        $role = $user->roles->first()?->name;

        if (!in_array($role, ['supervisor', 'security_admin'], true)) {
            $this->recordAudit($request, 'AUTHORIZATION_FAILURE', $container, [
                'requested_action' => 'HOLD',
                'reason' => 'role_not_authorized',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions to hold containers',
            ], 403);
        }

        ContainerEvent::create([
            'container_id' => $container->id,
            'operator_id' => $user->id,
            'action' => 'HOLD',
            'metadata' => ['reason' => $request->input('reason', 'Hold for inspection')],
        ]);

        $container->status = 'HELD';
        $container->save();

        $this->recordAudit($request, 'CONTAINER_HOLD', $container, [
            'result' => 'success',
            'reason' => $request->input('reason', 'Hold for inspection'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Container held successfully',
            'data' => $container->load('terminal'),
        ]);
    }

    /**
     * Release a container. In vulnerable mode the client artifact is trusted
     * for the action claim; secure mode enforces server-side authorization first.
     */
    public function release(Request $request, $containerNumber)
    {
        $container = Container::where('container_number', $containerNumber)->firstOrFail();
        $user = $request->user();
        $role = $user->roles->first()?->name;
        $authArtifact = $request->header('X-Terminal-Authorization');
        $secureMode = config('crypto.mode', 'vulnerable') === 'secure';

        $this->recordAudit($request, 'CONTAINER_RELEASE_ATTEMPT', $container, [
            'requested_action' => 'RELEASE',
            'mode' => $secureMode ? 'secure' : 'vulnerable',
            'authorization_artifact' => $authArtifact ? 'provided' : 'none',
        ]);

        Log::channel('audit')->info('CONTAINER_RELEASE_ATTEMPT', [
            'user_id' => $user->id,
            'container' => $containerNumber,
            'terminal' => $container->terminal->code,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'action' => 'RELEASE',
            'mode' => $secureMode ? 'secure' : 'vulnerable',
            'flag' => (!$secureMode && $authArtifact) ? app(FlagService::class)->valueFor('FLAG-09') : null,
        ]);

        if ($secureMode) {
            if (!in_array($role, ['supervisor', 'security_admin'], true)) {
                $this->recordAudit($request, 'AUTHORIZATION_FAILURE', $container, [
                    'reason' => 'role_not_authorized',
                    'mode' => 'secure',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions - operator role cannot release containers',
                ], 403);
            }

            if ($authArtifact) {
                try {
                    $artifactData = app(SecureCryptoService::class)->decryptArtifact($authArtifact);
                    $matchesRequest = (int) ($artifactData['operator_id'] ?? 0) === (int) $user->id
                        && (string) ($artifactData['terminal'] ?? '') === (string) $container->terminal_id
                        && (string) ($artifactData['container'] ?? '') === (string) $container->id
                        && ($artifactData['action'] ?? '') === 'RELEASE';

                    if (!$matchesRequest) {
                        throw new \RuntimeException('Artifact does not match authenticated release context');
                    }
                } catch (\Throwable) {
                    $this->recordAudit($request, 'AUTHORIZATION_FAILURE', $container, [
                        'reason' => 'invalid_secure_artifact',
                        'mode' => 'secure',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid authorization artifact',
                    ], 403);
                }
            }

            $this->recordAudit($request, 'AUTHORIZATION_SUCCESS', $container, [
                'mode' => 'secure',
                'decision_source' => 'server_side_role_policy',
            ]);
        } elseif ($authArtifact) {
            // VULNERABILITY: CRYPTO-05 / CRYPTO-06
            // The vulnerable path trusts the decrypted action and does not bind
            // the artifact to the current user/resource or enforce the role.
            try {
                $artifactData = app(\App\Services\CryptoService::class)->decryptArtifact($authArtifact);
                $authorizedAction = $artifactData['action'] ?? 'UNKNOWN';

                if ($authorizedAction !== 'RELEASE') {
                    $this->recordAudit($request, 'AUTHORIZATION_FAILURE', $container, [
                        'reason' => 'artifact_action_not_release',
                        'artifact_action' => $authorizedAction,
                        'mode' => 'vulnerable',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Authorization artifact does not permit release',
                    ], 403);
                }

                $this->recordAudit($request, 'AUTHORIZATION_SUCCESS', $container, [
                    'mode' => 'vulnerable',
                    'decision_source' => 'decrypted_client_artifact',
                    'artifact_action' => $authorizedAction,
                    'flag' => app(FlagService::class)->valueFor('FLAG-07'),
                ]);
            } catch (\Throwable) {
                $this->recordAudit($request, 'AUTHORIZATION_FAILURE', $container, [
                    'reason' => 'invalid_vulnerable_artifact',
                    'mode' => 'vulnerable',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid authorization artifact',
                ], 403);
            }
        } elseif (!in_array($role, ['supervisor', 'security_admin'], true)) {
            $this->recordAudit($request, 'AUTHORIZATION_FAILURE', $container, [
                'reason' => 'role_not_authorized',
                'mode' => 'vulnerable',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions - operator role cannot release containers',
            ], 403);
        } else {
            $this->recordAudit($request, 'AUTHORIZATION_SUCCESS', $container, [
                'mode' => 'vulnerable',
                'decision_source' => 'server_side_role_policy',
            ]);
        }

        if ($container->status === 'RELEASED') {
            $this->recordAudit($request, 'CONTAINER_RELEASE_FAILURE', $container, [
                'reason' => 'already_released',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Container already released',
            ], 400);
        }

        // All release operations modify only the local training database.
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

        $this->recordAudit($request, 'CONTAINER_RELEASE', $container, [
            'result' => 'success',
            'status' => 'RELEASED',
            'mode' => $secureMode ? 'secure' : 'vulnerable',
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
            'data' => $container->load('terminal'),
        ]);
    }

    private function recordAudit(Request $request, string $action, Container $container, array $metadata = []): void
    {
        app(AuditLogger::class)->record(
            $request,
            $action,
            'container',
            $container->container_number,
            array_merge(['terminal' => $container->terminal->code], $metadata)
        );
    }
}
