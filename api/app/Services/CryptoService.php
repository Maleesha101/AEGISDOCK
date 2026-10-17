<?php

namespace App\Services;

use RuntimeException;

class CryptoService
{
    private string $key;
    private string $iv;
    private string $cipher = 'aes-256-cbc';

    public function __construct()
    {
        // VULNERABILITY: CRYPTO-01
        // The training key and static IV are read from a runtime-mounted file.
        // The vulnerable Dockerfile accidentally copies the same file into an
        // earlier image layer. There is intentionally no MAC or AEAD tag.
        $configuredKey = config('crypto.key');
        $configuredIv = config('crypto.iv');
        $secretFile = config('crypto.key_file', '/run/secrets/terminal-gateway.env');
        $secret = [];

        if ((!is_string($configuredKey) || $configuredKey === '' || !is_string($configuredIv) || $configuredIv === '')
            && is_readable($secretFile)) {
            $parsed = parse_ini_file($secretFile, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $secret = $parsed;
            }
        }

        $keyHex = $secret['TERMINAL_CRYPTO_KEY_HEX'] ?? '';
        $ivHex = $secret['TERMINAL_CRYPTO_IV_HEX'] ?? '';

        $this->key = is_string($configuredKey) && $configuredKey !== ''
            ? $configuredKey
            : (ctype_xdigit($keyHex) && strlen($keyHex) === 64 ? hex2bin($keyHex) : '');

        $this->iv = is_string($configuredIv) && $configuredIv !== ''
            ? $configuredIv
            : (ctype_xdigit($ivHex) && strlen($ivHex) === 32 ? hex2bin($ivHex) : '');

        if (!is_string($this->key) || strlen($this->key) !== 32) {
            throw new RuntimeException('Missing or invalid training AES-256 key');
        }

        if (!is_string($this->iv) || strlen($this->iv) !== 16) {
            throw new RuntimeException('Missing or invalid training AES-CBC IV');
        }
    }

    /**
     * Create a deliberately unauthenticated training artifact.
     *
     * Plaintext layout:
     *   bytes 0..7  : fixed-width action ("VIEW    " or "RELEASE ")
     *   byte 8      : separator
     *   bytes 9..15 : seven padding spaces
     *   byte 16+    : JSON metadata
     *
     * The IV is prepended to the ciphertext before Base64 encoding. The
     * vulnerable server trusts the client-supplied IV and has no integrity
     * check, so a learner can demonstrate a deterministic first-block change.
     */
    public function generateArtifact(int $userId, int $terminalId, int $containerId, string $action): string
    {
        $action = strtoupper($action);
        if (strlen($action) > 8) {
            throw new RuntimeException('Authorization action exceeds the fixed-width field');
        }

        $actionField = str_pad($action, 8, ' ', STR_PAD_RIGHT);
        $metadata = json_encode([
            'operator_id' => $userId,
            'terminal' => (string) $terminalId,
            'container' => (string) $containerId,
            'issued_at' => time(),
            'expires_at' => time() + 3600,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $plaintext = $actionField.'|'.str_repeat(' ', 7).$metadata;
        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Could not encrypt training authorization artifact');
        }

        // VULNERABILITY: CRYPTO-02 — the same IV is reused for every artifact.
        // VULNERABILITY: CRYPTO-03 — no MAC/tag authenticates the IV or ciphertext.
        return base64_encode($this->iv.$ciphertext);
    }

    /**
     * Decrypt a vulnerable artifact without authenticating the IV/ciphertext.
     *
     * @return array{operator_id:int,terminal:string,container:string,action:string,issued_at:int,expires_at:int}
     */
    public function decryptArtifact(string $artifact): array
    {
        $decoded = base64_decode($artifact, true);
        if ($decoded === false || strlen($decoded) < 32) {
            throw new RuntimeException('Invalid authorization artifact encoding');
        }

        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        if ($ciphertext === '' || strlen($ciphertext) % 16 !== 0) {
            throw new RuntimeException('Invalid authorization ciphertext length');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plaintext === false || strlen($plaintext) < 17) {
            throw new RuntimeException('Failed to decrypt authorization artifact');
        }

        $action = trim(substr($plaintext, 0, 8));
        $metadataJson = substr($plaintext, 16);
        $data = json_decode($metadataJson, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new RuntimeException('Authorization metadata is not a JSON object');
        }

        // VULNERABILITY: CRYPTO-04 — no integrity check and no expiry/context
        // validation. The release controller trusts the decrypted action.
        return [
            'operator_id' => (int) ($data['operator_id'] ?? 0),
            'terminal' => (string) ($data['terminal'] ?? ''),
            'container' => (string) ($data['container'] ?? ''),
            'action' => $action !== '' ? $action : 'UNKNOWN',
            'issued_at' => (int) ($data['issued_at'] ?? 0),
            'expires_at' => (int) ($data['expires_at'] ?? 0),
        ];
    }

    public function getAlgorithm(): string
    {
        return 'AES-256-CBC';
    }

    public function getIv(): string
    {
        return $this->iv;
    }

    public function getIvHex(): string
    {
        return bin2hex($this->iv);
    }

    public function getKeyLength(): int
    {
        return strlen($this->key);
    }
}
