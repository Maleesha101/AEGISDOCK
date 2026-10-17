# Docker Secret Leakage Lab

## Objective

Demonstrate why deleting a secret in a later Docker layer does not remove it from earlier layers, then verify that the secure image does not contain either the vulnerable key/IV or the secure AEAD key.

## Local setup

The lab generates random, fake training values locally. They are not committed to Git.

```bash
make build-vulnerable
make up
```

The setup script creates:
- `secrets/terminal-gateway.env`: runtime-only key and IV mounted into the vulnerable API
- `secrets/secure_crypto_key`: a separate base64-encoded key for secure mode
- `deploy/archive/terminal-gateway.env.bak`: a temporary build fixture, removed after the image build

All of these are ignored by Git. Never replace them with real credentials.

## Obtain the training image

The registry simulator serves the locally built image tarball:

```bash
curl -fL http://localhost:8080/registry/images/aegisdock-terminal-api/download \
  -o /tmp/aegisdock-terminal-api.tar
docker load -i /tmp/aegisdock-terminal-api.tar
```

## Investigate image history and layers

```bash
docker history --no-trunc aegisdock/api:vulnerable
docker image inspect aegisdock/api:vulnerable
docker save aegisdock/api:vulnerable -o /tmp/aegisdock-image.tar
mkdir -p /tmp/aegisdock-image
tar -xf /tmp/aegisdock-image.tar -C /tmp/aegisdock-image
```

A Docker export of the final container filesystem is not sufficient to recover a file removed in a later layer. Inspect the layer tar archives saved by `docker save`:

```bash
find /tmp/aegisdock-image -name layer.tar -print
for layer in $(find /tmp/aegisdock-image -name layer.tar); do
  tar -xOf "$layer" 2>/dev/null | strings | grep -E 'TERMINAL_CRYPTO_(KEY|IV)_HEX' \
    && echo "Training material found in $layer"
done
```

The intended finding is a fake key/IV configuration in a historical layer. The final filesystem should not contain the archive file:

```bash
docker run --rm --entrypoint sh aegisdock/api:vulnerable \
  -c 'test ! -e /opt/legacy/terminal-gateway.env.bak'
```

## Why it happens

Each `COPY`, `ADD`, and `RUN` that changes the filesystem contributes to an image layer. A later `rm` hides/removes a file from the final filesystem but does not rewrite an earlier immutable layer.

## Secure image

```bash
make secure-up
docker history --no-trunc aegisdock/api:secure
trivy image aegisdock/api:secure
```

The secure Dockerfile never copies the deployment archive or the `secrets/` directory into the image. The AEAD key is mounted at runtime through a Compose secret. The vulnerable profile's key/IV mount is removed in the secure Compose override.

## Defensive checklist

- Keep secret files outside the build context.
- Use a narrow `.dockerignore`.
- Never pass secrets through Dockerfile `ARG` or `ENV`.
- Use BuildKit secret mounts for build-time credentials.
- Inject runtime secrets through a secret manager or runtime-only mount.
- Rotate any real credential that has entered an image or its history.
- Rebuild and scan images after remediation.
