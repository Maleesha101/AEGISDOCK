#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SECRETS_DIR="${ROOT}/secrets"
ARCHIVE_DIR="${ROOT}/deploy/archive"
RUNTIME_FILE="${SECRETS_DIR}/terminal-gateway.env"
SECURE_KEY_FILE="${SECRETS_DIR}/secure_crypto_key"
FLAGS_FILE="${SECRETS_DIR}/flags.env"

command -v openssl >/dev/null 2>&1 || {
  echo "OpenSSL is required to initialize local training secrets." >&2
  exit 1
}

umask 077
mkdir -p "${SECRETS_DIR}" "${ARCHIVE_DIR}"
chmod 700 "${SECRETS_DIR}"

if [[ ! -s "${RUNTIME_FILE}" ]] || ! grep -q '^TERMINAL_CRYPTO_KEY_HEX=' "${RUNTIME_FILE}" || ! grep -q '^TERMINAL_CRYPTO_IV_HEX=' "${RUNTIME_FILE}"; then
  key_hex="$(openssl rand -hex 32)"
  iv_hex="$(openssl rand -hex 16)"
  cat > "${RUNTIME_FILE}" <<EOF
; Fake, local-only AegisDock training material.
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

if [[ ! -s "${FLAGS_FILE}" ]] || ! grep -q '^FLAG_10=' "${FLAGS_FILE}"; then
  : > "${FLAGS_FILE}"
  for number in 01 02 03 04 05 06 07 08 09 10; do
    printf 'FLAG_%s=AEGIS{%s}\n' "${number}" "$(openssl rand -hex 16)" >> "${FLAGS_FILE}"
  done
  chmod 600 "${FLAGS_FILE}"
fi

# The vulnerable Dockerfile copies this fixture into one layer, then removes
# it in a later layer. The build target deletes this generated copy afterwards.
cp "${RUNTIME_FILE}" "${ARCHIVE_DIR}/terminal-gateway.env.bak"
for number in 03 04; do
  grep "^FLAG_${number}=" "${FLAGS_FILE}" >> "${ARCHIVE_DIR}/terminal-gateway.env.bak"
done
chmod 600 "${ARCHIVE_DIR}/terminal-gateway.env.bak"

echo "Initialized local-only AegisDock secrets (ignored by Git)."
