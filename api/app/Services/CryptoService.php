<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;

class CryptoService
{
    protected string $key;
    protected string $iv;
    protected string $cipher = 'aes-256-cbc';

    public function __construct()
    {
        // VULNERABILITY: CRYPTO-01
        // The key and IV are intentionally loaded from environment
        // and the key may be leaked through Docker image layers.
        // AES-256-CBC without authentication.
        $this->key = config('crypto.key'); // 32 bytes for AES-256
        $this->iv = config('crypto.iv');   // 16 bytes for CBC
    }

    /**
     * Generate an authorization artifact for a user/terminal/container.
     *
     * The plaintext structure is deterministic so learners can
     * infer block boundaries:
     *
     *   Block 1: {"operator_id": 1001
     *   Block 2: "terminal": "T-07",
     *   Block 3: "container": "CONT-88421",
     *   Block 4: "action": "VIEW",
     *   Block 5: "issued_at": 1770000000
     *   Block 6: "expires_at": 1770003600
     *
     * @param int $userId
     * @param int $terminalId
     * @param int $containerId
     * @param string $action
     * @return string Base64-encoded ciphertext
     */
    public function generateArtifact(int $userId, int $terminalId, int $containerId, string $action): string
    {
        // VULNERABILITY: CRYPTO-02
        // Static IV - same IV for every encryption.
        // This is intentionally weak and enables ciphertext manipulation attacks.
        static $staticIv = null;
        if ($staticIv === null) {
            $staticIv = $this->iv;
        }
        $iv = $staticIv;

        $plaintext = json_encode([
            'operator_id' => $userId,
            'terminal' => (string) $terminalId,
            'container' => (string) $containerId,
            'action' => $action,
            'issued_at' => time(),
            'expires_at' => time() + 3600, // 1 hour expiry
        ], JSON_PRESERVE_ZERO_ORDER);

        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($ciphertext);
    }

    /**
     * Decrypt an authorization artifact.
     *
     * VULNERABILITY: CRYPTO-03
     // No integrity check / HMAC verification.
     // The decrypted plaintext is trusted blindly.
     *
     * @param string $artifact Base64-encoded ciphertext
     * @return array{operator_id: int, terminal: string, container: string, action: string, issued_at: int, expires_at: int}
     * @throws Exception If decryption fails or artifact is invalid
     */
    public function decryptArtifact(string $artifact): array
    {
        $ciphertext = base64_decode($artifact);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($plaintext === false) {
            throw new Exception('Failed to decrypt authorization artifact');
        }

        $data = json_decode($plaintext, true);
        if ($data === null) {
            throw new Exception('Decrypted data is not valid JSON');
        }

        // VULNERABILITY: CRYPTO-04
        // No authentication tag verified.
        // No integrity protection.
        // Decrypted fields are trusted without question.

        return [
            'operator_id' => $data['operator_id'] ?? 0,
            'terminal' => $data['terminal'] ?? '',
            'container' => $data['container'] ?? '',
            'action' => $data['action'] ?? 'UNKNOWN',
            'issued_at' => $data['issued_at'] ?? 0,
            'expires_at' => $data['expires_at'] ?? 0,
        ];
    }

    /**
     * Get the cryptographic algorithm used.
     *
     * @return string
     */
    public function getAlgorithm(): string
    {
        return $this->cipher;
    }

    /**
     * Get the IV used for encryption.
     *
     * @return string
     */
    public function getIv(): string
    {
        return $this->iv;
    }

    /**
     * Get the key length in bytes.
     *
     * @return int
     */
    public function getKeyLength(): int
    {
        return strlen($this->key);
    }
}