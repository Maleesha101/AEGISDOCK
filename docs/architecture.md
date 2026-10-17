# AegisDock Architecture

## Overview

AegisDock is a local-only training application that simulates port logistics operations. No physical terminal, PLC, SCADA system, cloud service, or external registry is connected.

## Services

| Service | Internal port | Host access | Responsibility |
|---|---:|---|---|
| Nginx | 80 | http://localhost:8080 by default | Single host entry point and reverse proxy |
| Next.js frontend | 3000 | Through Nginx only | Operator dashboard |
| Laravel API | 80 | Through Nginx only | Authentication, container operations, authorization artifacts |
| PostgreSQL | 5432 | Compose network only | Application data |
| Registry simulator | 3000 | Through Nginx only | Simulated image metadata and discovery |
| Audit service | 3000 | Through Nginx only | Simulated audit event read/write endpoints |

The host port can be changed with AEGISDOCK_HTTP_PORT. The API, database, registry simulator, and audit service do not publish host ports.

## Request routing

```text
Browser
  |
  v
Nginx (host :8080 -> container :80)
  |-- /api/*       -> Laravel API :80
  |-- /registry/*  -> registry simulator :3000
  |-- /audit/*     -> audit service :3000
  `-- /*           -> Next.js frontend :3000

Laravel API -> PostgreSQL :5432
```

Nginx currently uses HTTP for local development. Port 443 is not published until a local TLS certificate and listener are configured.

## Vulnerable image build

The vulnerable API image intentionally copies a locally generated fake file deploy/archive/terminal-gateway.env.bak into an earlier image layer and deletes it in a later layer. The runtime secret is mounted separately and is never passed through Docker ENV. The Dockerfile-specific ignore file permits this one fake fixture for the vulnerable build. The root .dockerignore continues to exclude deployment archives from ordinary/secure build contexts.

All values in the fixture are fabricated and must never be replaced with real credentials.

## Trust boundaries

- Browser inputs are untrusted.
- Nginx is the only host-published service by default.
- Internal service names are reachable only on the Compose bridge network.
- The registry simulator is not a real registry and makes no external requests.
- The audit service records simulated events only; release authorization belongs in the Laravel API.
- A successful release must only change local application/database state.

## Current implementation status

- [x] Compose service network and gateway boundary
- [x] PostgreSQL PHP extension included in the API image
- [x] Fake historical deployment fixture for the vulnerable image
- [x] Audit service no longer exposes an unconditional release operation
- [x] Configure Laravel public document root and runtime directories
- [x] Add migrations, seed data, Sanctum authentication, and role authorization tests
- [x] Add vulnerable CBC artifact mode and secure AEAD mode
- [x] Generate local-only secrets and verify historical-layer versus secure-image behavior in CI
- [x] Add Next.js dashboard and login page
- [x] Add registry image download and local audit routes
