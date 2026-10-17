<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationArtifact;
use App\Models\User;
use App\Models\Terminal;
use App\Models\Container;
use App\Services\CryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthorizationController extends Controller
{
    protected CryptoService $crypto;

    public function __construct(CryptoService $crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * Get an authorization artifact for the specified terminal and container.
     */
    public function getArtifact(Request $request)
    {
        $terminalCode = $request->query('terminal');
        $containerNumber = $request->query('container');

        $terminal = Terminal::where('code', $terminalCode)->firstOrFail();
        $container = Container::where('container_number', $containerNumber)->firstOrFail();

        // Create the authorization artifact
        $userId = auth()->id() ?? 1; // Default user ID

        $ciphertext = $this->crypto->generateArtifact(
            $userId,
            $terminal->id,
            $container->id,
            'VIEW' // Default action for view operation
        );

        // Store the artifact
        $artifact = AuthorizationArtifact::create([
            'user_id' => $userId,
            'terminal_id' => $terminal->id,
            'container_id' => $container->id,
            'ciphertext' => $ciphertext,
            'expires_at' => now()->addHours(1),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'artifact' => $ciphertext,
                'terminal' => $terminal->code,
                'container' => $container->container_number,
                'action' => 'VIEW',
                'issued_at' => now()->timestamp,
                'expires_at' => $artifact->expires_at->timestamp,
            ],
            'note' => 'Authorization artifact issued for the requested terminal and container.',
        ]);
    }

    /**
     * Debug endpoint: retrieve historical authorization metadata.
     *
     * This endpoint intentionally exposes cryptographic metadata
     * to help learners analyze the construction.
     *
     * VULNERABILITY: SEC-01
     // Exposes algorithm, IV, ciphertext length, and metadata
     // but does NOT expose the encryption key
     */
    public function debug(Request $request, $id)
    {
        $artifact = AuthorizationArtifact::findOrFail($id);

        $algorithm = app('crypto.key') ? 'AES-256-CBC' : 'configured-via-service';

        // Get the IV - must be recovered from Docker image in training scenario
        $iv = (string) app('crypto.iv');

        // Get ciphertext length
        $ciphertextLength = strlen(base64_decode($artifact->ciphertext));

        // Try to decrypt - use key from Docker image recovery scenario
        // In training, learners recover the key from Docker layers
        $key = (string) app('crypto.key');

        $plaintext = '';
        try {
            $decrypted = openssl_decrypt(
                base64_decode($artifact->ciphertext),
                'aes-256-cbc',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted !== false) {
                $plaintext = json_decode($decrypted, true);
            }
        } catch (\Exception $e) {
            // Expected in training scenario
        }

        $response = [
            'algorithm' => 'AES-256-CBC',
            'iv' => $iv,
            'ciphertext' => $artifact->ciphertext,
            'ciphertext_length' => $ciphertextLength,
            'expires_at' => $artifact->expires_at->toISOString(),
        ];

        if ($plaintext !== '') {
            $response['metadata'] = [
                'operator_id' => $plaintext['operator_id'] ?? null,
                'terminal' => $plaintext['terminal'] ?? null,
                'container' => $plaintext['container'] ?? null,
                'action' => $plaintext['action'] ?? null,
                'issued_at' => $plaintext['issued_at'] ?? null,
                'expires_at' => $plaintext['expires_at'] ?? null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }
}