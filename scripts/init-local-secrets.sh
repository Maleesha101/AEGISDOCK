#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SECRETS_DIR="${ROOT}/secrets"
ARCHIVE_DIR="${ROOT}/deploy/archive"
RUNTIME_FILE="${SECRETS_DIR}/terminal-gateway.env"
SECURE_KEY_FILE="${SECRETS_DIR}/secure_crypto_key"

command -v openssl >/dev/null 2>&1 || {
  echo "OpenSSL is required to initialize local training secrets." >&2
  exit 1
}

umask 077
mkdir -p "${SECRETS_DIR}" "${ARCHIVE_DIR}"
chmod 700 "${SECRETS_DIR}"

if [[ ! -s "${RUNTIME_FILE}" ]]; then
  key_hex="$(openssl rand -hex 32)"
  iv_hex="$(openssl rand -hex 16)"
  cat > "${RUNTIME_FILE}" <<EOF
# Fake, local-only AegisDock training material.
TERMINAL_CRYPTO_KEY_HEX=${key_hex}
TERMINAL_CRYPTO_IV_HEX=${iv_hex}
TERMINAL_API_URL=http://terminal-controller:9000
EOF
  chmod 600 "${RUNTIME_FILE}"
fi

if [[ ! -s "${SECURE_KEY_FILE}" ]]; then
  openssl rand -base64 32 > "${SECURE_KEY_FILE}"
  chmod 600 "${SECURE_KEY_FILE}"
fi

# The vulnerable Dockerfile copies this fixture into one layer, then removes
# it in a later layer. The build target deletes this generated copy afterwards.
cp "${RUNTIME_FILE}" "${ARCHIVE_DIR}/terminal-gateway.env.bak"
chmod 600 "${ARCHIVE_DIR}/terminal-gateway.env.bak"

echo "Initialized local-only AegisDock secrets (ignored by Git)."
