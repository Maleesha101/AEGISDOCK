<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;
use SodiumException;

class SecureCryptoService
{
    protected string $key;
    protected int $keyLength = 32; // 256 bits for AES-GCM
    protected int $nonceLength = 12; // 96 bits for GCM recommended nonce
    protected int $tagLength = 16; // 128-bit authentication tag

    public function __construct()
    {
        // SECURE: Key is injected at runtime, never stored in image
        $this->key = base64_decode(config('crypto.secure_key'));
        if (!$this->key || strlen($this->key) !== $this->keyLength) {
            throw new Exception('Invalid or missing encryption key');
        }
    }

    /**
     * Generate a secure authorization artifact using AES-GCM.
     *
     * SECURE:
     * - Uses AES-256-GCM (authenticated encryption)
     * - Unique nonce for every encryption
     * - Authentication tag verified before trusting plaintext
     *
     * @param int $userId
     * @param int $terminalId
     * @param int $containerId
     * @param string $action
     * @return string Base64-encoded: nonce + ciphertext + tag
     */
    public function generateArtifact(int $userId, int $terminalId, int $containerId, string $action): string
    {
        // Generate unique nonce for each encryption
        $nonce = random_bytes($this->nonceLength);

        $plaintext = json_encode([
            'operator_id' => $userId,
            'terminal' => (string) $terminalId,
            'container' => (string) $containerId,
            'action' => $action,
            'issued_at' => time(),
            'expires_at' => time() + 3600, // 1 hour expiry
        ], JSON_PRESERVE_ZERO_FRACTION);

        // Encrypt with authentication
        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
            $plaintext,
            '', // additional data (not used)
            $nonce,
            $this->key
        );

        // The ciphertext already includes the authentication tag
        // Format: nonce + ciphertext (which includes tag)
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt and verify an authorization artifact.
     *
     * SECURE:
     * - Verifies authentication tag before returning plaintext
     * - Rejects any tampered ciphertext
     * - Validates expiration
     *
     * @param string $artifact Base64-encoded nonce + ciphertext + tag
     * @return array{operator_id: int, terminal: string, container: string, action: string, issued_at: int, expires_at: int}
     * @throws Exception If verification fails or artifact is invalid
     */
    public function decryptArtifact(string $artifact): array
    {
        $decoded = base64_decode($artifact);
        if ($decoded === false) {
            throw new Exception('Invalid base64 encoding');
        }

        // Extract nonce and ciphertext+tag
        if (strlen($decoded) < $this->nonceLength) {
            throw new Exception('Artifact too short');
        }

        $nonce = substr($decoded, 0, $this->nonceLength);
        $ciphertextAndTag = substr($decoded, $this->nonceLength);

        // Decrypt and verify authentication tag
        $plaintext = sodium_crypto_aead_aes256gcm_decrypt(
            $ciphertextAndTag,
            '', // additional data (not used)
            $nonce,
            $this->key
        );

        if ($plaintext === false) {
            // Authentication failed - ciphertext was tampered with or invalid
            throw new Exception('Authentication failed - artifact integrity check failed');
        }

        $data = json_decode($plaintext, true);
        if ($data === null) {
            throw new Exception('Decrypted data is not valid JSON');
        }

        // Validate required fields
        if (!isset($data['operator_id'], $data['terminal'], $data['container'], $data['action'], $data['issued_at'], $data['expires_at'])) {
            throw new Exception('Missing required fields in decrypted data');
        }

        // Check expiration
        if ($data['expires_at'] < time()) {
            throw new Exception('Authorization artifact has expired');
        }

        // SECURE: Only return validated data
        return [
            'operator_id' => (int) $data['operator_id'],
            'terminal' => (string) $data['terminal'],
            'container' => (string) $data['container'],
            'action' => $data['action'],
            'issued_at' => (int) $data['issued_at'],
            'expires_at' => (int) $data['expires_at'],
        ];
    }

    /**
     * Get the cryptographic algorithm used.
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return 'AES-256-GCM';
    }

    /**
     * Get the nonce length.
     *
     * @return int
     */
    public function getNonceLength(): int
    {
        return $this->nonceLength;
    }

    /**
     * Get the key length in bytes.
     *
     * @return int
     */
    public function getKeyLength(): int
    {
        return $this->keyLength;
    }
}