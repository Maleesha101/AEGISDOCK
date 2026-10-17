<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationArtifact;
use App\Models\Container;
use App\Models\Terminal;
use App\Services\AuditLogger;
use App\Services\FlagService;
use App\Services\SecureCryptoService;
use Illuminate\Http\Request;

class AuthorizationController extends Controller
{
    /**
     * Issue a view artifact for a container at a specified terminal.
     */
    public function getArtifact(Request $request)
    {
        $terminalCode = (string) $request->query('terminal', '');
        $containerNumber = (string) $request->query('container', '');

        $terminal = Terminal::where('code', $terminalCode)->firstOrFail();
        $container = Container::where('container_number', $containerNumber)->firstOrFail();

        if ((int) $container->terminal_id !== (int) $terminal->id) {
            return response()->json([
                'success' => false,
                'message' => 'Container does not belong to the requested terminal',
            ], 422);
        }

        $userId = (int) $request->user()->id;
        $secureMode = config('crypto.mode', 'vulnerable') === 'secure';

        if ($secureMode) {
            $ciphertext = app(SecureCryptoService::class)->generateArtifact(
                $userId,
                (int) $terminal->id,
                (int) $container->id,
                'VIEW'
            );
        } else {
            $ciphertext = app(\App\Services\CryptoService::class)->generateArtifact(
                $userId,
                (int) $terminal->id,
                (int) $container->id,
                'VIEW'
            );
        }

        $artifact = AuthorizationArtifact::create([
            'user_id' => $userId,
            'terminal_id' => $terminal->id,
            'container_id' => $container->id,
            'ciphertext' => $ciphertext,
            'expires_at' => now()->addHour(),
        ]);

        app(AuditLogger::class)->record($request, 'AUTHORIZATION_ARTIFACT_CREATED', 'authorization_artifact', $artifact->id, [
            'terminal' => $terminal->code,
            'container' => $container->container_number,
            'action' => 'VIEW',
            'mode' => $secureMode ? 'secure' : 'vulnerable',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $artifact->id,
                'artifact' => $ciphertext,
                'terminal' => $terminal->code,
                'container' => $container->container_number,
                'action' => 'VIEW',
                'issued_at' => now()->timestamp,
                'expires_at' => $artifact->expires_at->timestamp,
                'flag' => app(FlagService::class)->valueFor('FLAG-06'),
            ],
            'note' => 'Authorization artifact issued for the selected container.',
        ]);
    }

    /**
     * Return cryptographic metadata for an existing artifact.
     * The endpoint intentionally does not return key material.
     */
    public function debug(Request $request, $id)
    {
        $artifact = AuthorizationArtifact::findOrFail($id);
        app(AuditLogger::class)->record($request, 'AUTHORIZATION_METADATA_VIEWED', 'authorization_artifact', $artifact->id, []);
        $secureMode = config('crypto.mode', 'vulnerable') === 'secure';
        $decoded = base64_decode($artifact->ciphertext, true);

        if ($decoded === false) {
            return response()->json([
                'success' => false,
                'message' => 'Stored authorization artifact is malformed',
            ], 422);
        }

        if ($secureMode) {
            $service = app(SecureCryptoService::class);
            $nonceLength = $service->getNonceLength();
            $metadata = null;

            try {
                $metadata = $service->decryptArtifact($artifact->ciphertext);
            } catch (\Throwable) {
                // Metadata is optional; never return secrets or raw plaintext.
            }

            $data = [
                'algorithm' => $service->getAlgorithm(),
                'nonce' => bin2hex(substr($decoded, 0, $nonceLength)),
                'nonce_length' => $nonceLength,
                'ciphertext_length' => max(0, strlen($decoded) - $nonceLength),
                'artifact' => $artifact->ciphertext,
                'expires_at' => $artifact->expires_at->toISOString(),
            ];
        } else {
            $iv = substr($decoded, 0, 16);
            $metadata = null;

            try {
                $metadata = app(\App\Services\CryptoService::class)->decryptArtifact($artifact->ciphertext);
            } catch (\Throwable) {
                // Keep the endpoint useful for malformed artifacts too.
            }

            $data = [
                'algorithm' => app(\App\Services\CryptoService::class)->getAlgorithm(),
                'iv' => bin2hex($iv),
                'iv_length' => strlen($iv),
                'ciphertext_length' => max(0, strlen($decoded) - 16),
                'artifact' => $artifact->ciphertext,
                'expires_at' => $artifact->expires_at->toISOString(),
            ];
        }

        if (is_array($metadata)) {
            $data['metadata'] = [
                'operator_id' => $metadata['operator_id'] ?? null,
                'terminal' => $metadata['terminal'] ?? null,
                'container' => $metadata['container'] ?? null,
                'action' => $metadata['action'] ?? null,
                'issued_at' => $metadata['issued_at'] ?? null,
                'expires_at' => $metadata['expires_at'] ?? null,
            ];
        }

        $data['flag'] = app(FlagService::class)->valueFor('FLAG-05');

        return response()->json(['success' => true, 'data' => $data]);
    }
}
