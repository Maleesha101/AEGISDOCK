# AegisDock Docker Secrets

## Vulnerable Image Construction

### The Problem

```dockerfile
# Dockerfile.vulnerable

# Layer N: Copy deployment archive with crypto keys
COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak

# ... more layers ...

# Layer N+1: Remove the file
RUN rm /opt/legacy/terminal-gateway.env.bak
```

The file is removed from the **filesystem**, but remains in the **Docker image layer history**.

### Discovery Commands

```bash
# View image layer history
docker history aegisdock/api:vulnerable --no-trunc

# Inspect image layers
docker image inspect aegisdock/api:vulnerable

# Extract filesystem from image layers
docker create --name temp-extract aegisdock/api:vulnerable
docker export temp-extract | tar -x
find . -name "*.bak" -o -name "*.env*"

# Search for secrets in image
docker save aegisdock/api:vulnerable | tar -x
grep -r "TERMINAL_CRYPTO_KEY" .
```

### The Leak

```
Layer: COPY deploy/archive/terminal-gateway.env.bak /opt/legacy/terminal-gateway.env.bak
Contents:
  TERMINAL_CRYPTO_KEY=TRAINING-FAKE-KEY-2025-ABCDEF1234567890ABCDEF12
  TERMINAL_CRYPTO_IV=TRAINING-FAKE-IV-1234567890ABC
  TERMINAL_API_URL=http://terminal-controller:9000
  TERMINAL_GATEWAY_MODE=INSECURE_VULNERABLE
```

### Why This Happens

Docker images are built as layered filesystem snapshots. Each `COPY`, `RUN`, or `ADD` instruction creates a new layer. Removing a file in a later layer only adds a new layer that hides the file - it doesn't rewrite previous layers. The old layer still contains the file's data.

### Security Best Practices

1. **Never store secrets in Dockerfiles or image layers**
2. **Use .dockerignore to exclude sensitive files**
3. **Use runtime secret injection**
4. **Scan images regularly for secrets**
5. **Use multi-stage builds to minimize layers**

## Secure Image Construction

```dockerfile
# Dockerfile.secure

# No secrets copied at build time
# Secrets injected at runtime
RUN mkdir -p /run/secrets/credentials

# Application source only
COPY api/ ./
```

### Runtime Injection

```bash
# Docker secrets
docker run -e CRYPTO_KEY=$(cat /run/secrets/crypto-key) \
           -e CRYPTO_IV=$(cat /run/secrets/crypto-iv) \
           aegisdock/api:secure

# Or use Docker Compose secrets
# (See docker-compose.yml for configuration)
```

## Detection Tools

```bash
# Trivy - vulnerability scanner
trivy image aegisdock/api:vulnerable

# Gitleaks - secret scanner
gitleaks detect --source=.

# Docker scout
docker scout cves aegisdock/api:vulnerable
```