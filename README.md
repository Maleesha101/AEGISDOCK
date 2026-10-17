# AEGISDOCK

> **AegisDock — Autonomous Port Container Release System**  
> A realistic, intentionally vulnerable cybersecurity laboratory for studying chained **cryptographic failures, Docker image/layer secret leakage, authorization weaknesses, and security remediation**.

[![Security Lab](https://img.shields.io/badge/type-security%20lab-red)](https://github.com/Maleesha101/AEGISDOCK)
[![Docker](https://img.shields.io/badge/runtime-Docker-2496ED)](https://www.docker.com/)
[![Laravel](https://img.shields.io/badge/API-Laravel-FF2D20)](https://laravel.com/)
[![Next.js](https://img.shields.io/badge/frontend-Next.js-000000)](https://nextjs.org/)
[![PostgreSQL](https://img.shields.io/badge/database-PostgreSQL-4169E1)](https://www.postgresql.org/)

---

## Table of Contents

- [Overview](#overview)
- [Why AegisDock Exists](#why-aegisdock-exists)
- [Learning Objectives](#learning-objectives)
- [Scenario](#scenario)
- [Architecture](#architecture)
- [Trust Boundaries](#trust-boundaries)
- [Threat Model](#threat-model)
- [Attack Chain](#attack-chain)
- [Intentional Vulnerabilities](#intentional-vulnerabilities)
- [Application Roles](#application-roles)
- [Seed Data](#seed-data)
- [Core API](#core-api)
- [Authorization Artifact](#authorization-artifact)
- [Docker Secret-Leak Scenario](#docker-secret-leak-scenario)
- [Cryptographic Failure Scenario](#cryptographic-failure-scenario)
- [Audit and Forensics](#audit-and-forensics)
- [Project Structure](#project-structure)
- [Technology Stack](#technology-stack)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Laboratory Workflow](#laboratory-workflow)
- [Reconnaissance](#reconnaissance)
- [Docker Image Investigation](#docker-image-investigation)
- [Cryptographic Analysis](#cryptographic-analysis)
- [Ciphertext-Malleability Investigation](#ciphertext-malleability-investigation)
- [Business-Logic Impact](#business-logic-impact)
- [Defensive Implementation](#defensive-implementation)
- [Verification and Testing](#verification-and-testing)
- [Flags](#flags)
- [Development Roadmap](#development-roadmap)
- [Git Commit Strategy](#git-commit-strategy)
- [Security Design Requirements](#security-design-requirements)
- [What This Lab Does Not Do](#what-this-lab-does-not-do)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [Responsible Use](#responsible-use)
- [License](#license)

---

## Overview

AegisDock is an intentionally vulnerable **real-world-style port logistics platform** designed for hands-on Application Security and DevSecOps training.

The fictional platform manages automated container release operations at a smart shipping terminal. Operators authenticate to the application, inspect containers, place holds, and request release operations. A backend API creates an encrypted authorization artifact that is presented to sensitive endpoints.

The laboratory deliberately combines multiple weaknesses so that a learner can investigate an **attack chain** rather than isolated vulnerabilities.

The central chain is:

```text
Application Reconnaissance
        |
        v
Container/Artifact Discovery
        |
        v
Vulnerable Docker Image
        |
        v
Historical Layer Secret Leakage
        |
        v
Recovery of Training Cryptographic Material
        |
        v
Application Crypto Analysis
        |
        v
AES-CBC Without Integrity Protection
        |
        v
Encrypted Authorization Artifact
        |
        v
Controlled Ciphertext Manipulation
        |
        v
Authorization Abuse
        |
        v
Unauthorized Container Release
        |
        v
Audit / Forensics
        |
        v
Remediation
```

The important lesson is that the vulnerabilities reinforce one another:

> A Docker image leak does not have to directly compromise the business application. It can expose cryptographic material that turns a second weakness into a practical attack path.

---

## Why AegisDock Exists

Many security labs teach one vulnerability at a time:

- weak hashing
- insecure TLS
- exposed secrets
- CBC malleability
- authorization bugs
- Docker mistakes

Real incidents are often different.

A small deployment mistake can expose a secret. That secret can make another weakness exploitable. The resulting access can then cross a business trust boundary.

AegisDock is designed to model that style of investigation.

The learner should answer questions such as:

1. What is exposed?
2. What is the application's trust model?
3. Where does sensitive cryptographic material originate?
4. Can historical Docker layers retain deleted files?
5. How is authorization represented?
6. Does encryption provide integrity?
7. Can attacker-controlled ciphertext influence authorization?
8. What business operation becomes reachable?
9. What evidence appears in logs?
10. How can the entire chain be broken defensively?

---

## Learning Objectives

After completing the laboratory, learners should be able to:

### Cryptography

- Distinguish encryption from authentication/integrity.
- Explain why AES-CBC alone does not provide ciphertext integrity.
- Recognize static or reused IVs as a cryptographic design problem.
- Understand CBC ciphertext malleability.
- Identify dangerous trust in encrypted client-controlled authorization data.
- Explain why AEAD modes such as AES-GCM are preferable for new application designs.
- Understand key-management responsibilities separately from algorithm selection.

### Docker and DevSecOps

- Inspect Docker images and image history.
- Understand why deleted files may remain in previous image layers.
- Identify secrets accidentally introduced during image builds.
- Use `.dockerignore` appropriately.
- Understand why Docker `ARG` and `ENV` are not secret-management solutions.
- Use BuildKit secret mounts for build-time secrets.
- Inject runtime secrets without baking them into an image.
- Recognize that leaked credentials must be rotated even after the image is rebuilt.

### Application Security

- Map an application's attack surface.
- Identify trust boundaries.
- Analyze authentication and authorization separately.
- Investigate encrypted authorization artifacts.
- Trace a vulnerability from technical weakness to business impact.
- Use application logs for incident reconstruction.
- Verify remediation instead of merely changing vulnerable code.

### Security Engineering

- Build vulnerable and secure implementations side by side.
- Write regression tests for security controls.
- Use static and image scanning tools.
- Document findings reproducibly.
- Validate that one remediation does not leave another path open.

---

## Scenario

### Company

**AegisDock Logistics** operates smart container terminals.

Its software platform coordinates:

- shipment inspection
- terminal operations
- container holds
- scheduled releases
- operator authentication
- authorization artifacts
- audit records

The real terminal controller is **not connected** to this laboratory.

All terminal operations are simulated by application/database state.

### Terminal

The lab contains simulated terminals:

- `T-01`
- `T-04`
- `T-07`
- `T-12`

The primary training scenario uses:

- Terminal: `T-07`
- Container: `CONT-88421`

### Normal Business Flow

A normal operator can:

1. Log in.
2. View terminal information.
3. View container information.
4. Inspect a container.
5. Place a hold.

A supervisor can additionally:

6. Release a container.

A security administrator can perform all authorized administrative actions.

The vulnerable implementation introduces a flawed encrypted authorization mechanism around the release operation.

---

## Architecture

```text
                         +----------------------+
                         |      Browser         |
                         | Next.js / TypeScript |
                         +----------+-----------+
                                    |
                                    v
                         +----------------------+
                         |     Nginx Gateway    |
                         +----------+-----------+
                                    |
                                    v
                         +----------------------+
                         |      Laravel API     |
                         |                      |
                         | - Authentication     |
                         | - Shipment API       |
                         | - Operator API       |
                         | - Authorization      |
                         | - Audit API          |
                         +-----+----------+-----+
                               |          |
                    +----------+          +----------------+
                    v                                    v
          +-------------------+               +----------------------+
          |    PostgreSQL     |               | Terminal Controller  |
          | application data  |               | simulated service   |
          +-------------------+               +----------------------+
                    |
                    v
          +-------------------+
          |   Audit Records   |
          +-------------------+

          +----------------------+
          | Registry Simulator   |
          | vulnerable image    |
          +----------------------+
```

### Components

| Component | Purpose |
|---|---|
| Next.js frontend | Operator interface |
| Nginx | Reverse proxy/API gateway |
| Laravel API | Main application |
| PostgreSQL | Users, containers, terminals, authorization records and audit data |
| Registry simulator | Local simulation of exposed container image artifacts |
| Terminal controller | Simulated container-release backend |
| Docker | Vulnerable and secure application packaging |
| Burp Suite | HTTP/API investigation |
| Trivy | Container/image security scanning |

---

## Trust Boundaries

### Boundary 1 — Browser to Gateway

```text
Browser -> Nginx
```

Untrusted client-controlled inputs include:

- HTTP headers
- cookies
- bearer tokens
- request bodies
- authorization artifacts

### Boundary 2 — Gateway to API

```text
Nginx -> Laravel API
```

The API must validate all security-sensitive claims.

### Boundary 3 — API to Database

```text
Laravel -> PostgreSQL
```

Database records should not be treated as a substitute for authorization logic.

### Boundary 4 — API to Terminal Controller

```text
Laravel -> simulated terminal controller
```

The release operation represents the business impact.

### Boundary 5 — Build System to Runtime Image

```text
Build context -> Docker image -> Runtime
```

Secrets accidentally copied into the build context can persist in image history even when later deleted.

---

## Threat Model

### Assets

- Container release authorization
- Terminal state
- Shipment information
- Operator identities
- Session tokens
- Cryptographic keys
- Audit records
- Deployment artifacts

### Threat Actors

The laboratory models:

1. **Unauthenticated external attacker**
2. **Compromised low-privileged operator**
3. **Malicious insider**
4. **Attacker with access to exposed deployment artifacts**

### Attack Surfaces

- Web frontend
- REST API
- Authentication endpoints
- Authorization artifact endpoint
- Container image registry simulator
- Docker image layers
- Debug/audit endpoints
- HTTP headers
- Client-controlled encrypted data

### Security Objectives

The secure implementation should provide:

- confidentiality
- integrity
- authentication
- authorization
- key protection
- auditability
- secure deployment
- least privilege
- defense in depth

---

## Attack Chain

The intended investigation path is:

### Phase 1 — Reconnaissance

Identify:

- application endpoints
- technologies
- exposed resources
- API behavior
- role boundaries
- container/image artifacts

### Phase 2 — Container Discovery

Find the vulnerable application image through the local registry simulation.

### Phase 3 — Docker Investigation

Inspect:

- image metadata
- history
- layers
- deleted files
- environment configuration
- build commands

### Phase 4 — Secret Recovery

Recover a **training-only cryptographic configuration** from an old Docker layer.

Example conceptual artifact:

```text
/deploy/archive/terminal-gateway.env.bak
```

The final image filesystem should not contain the file. The file remains recoverable from an earlier layer.

### Phase 5 — Crypto Analysis

Identify:

- AES-256-CBC
- static IV
- no authentication tag
- no MAC
- server-side trust in decrypted authorization fields

### Phase 6 — Authorization Artifact

Obtain a legitimate encrypted authorization artifact from the application.

### Phase 7 — Ciphertext Manipulation

Study how CBC's XOR relationship can permit controlled plaintext modification without possessing the AES key.

The lab should make this deterministic through predictable plaintext structure and block alignment.

### Phase 8 — Business Impact

A manipulated authorization artifact is presented to the release endpoint.

The vulnerable server incorrectly accepts the resulting authorization and changes the simulated container state.

### Phase 9 — Forensics

Investigate:

- authentication events
- artifact creation
- release attempts
- authorization failures
- successful release events
- source IP
- user-agent
- timestamps
- affected container

### Phase 10 — Remediation

Replace the vulnerable design with:

- AEAD encryption
- unique nonces
- authenticated ciphertext
- server-side authorization
- runtime secret injection
- clean image builds
- secret rotation
- security regression tests

---

## Intentional Vulnerabilities

> These weaknesses are deliberate and belong only in the vulnerable training environment.

### CRYPTO-01 — AES-CBC Without Integrity

The vulnerable application uses AES-256-CBC without an authentication tag or MAC.

**Problem:**

Encryption provides confidentiality, but CBC by itself does not authenticate ciphertext.

**Impact:**

Attacker-controlled ciphertext can potentially influence decrypted plaintext.

---

### CRYPTO-02 — Static IV

The vulnerable implementation uses a predictable/static IV.

**Problem:**

IVs for CBC encryption should be unpredictable and unique for each encryption operation.

**Impact:**

Repeated structure and cryptographic relationships can leak information and make the design weaker.

---

### CRYPTO-03 — Client-Controlled Encrypted Authorization

The application accepts an encrypted authorization artifact from the client and trusts decrypted authorization fields.

**Problem:**

Encryption should not be used as a substitute for server-side authorization.

**Impact:**

If ciphertext integrity fails, security-sensitive claims may become attacker-influenced.

---

### DOCKER-01 — Secret in Historical Image Layer

An old deployment artifact contains training cryptographic material.

The Dockerfile later removes it, but the earlier layer still retains it.

**Problem:**

Deleting a file in a later Docker layer does not erase the bytes from previous layers.

**Impact:**

Anyone able to obtain the image may be able to recover historical secrets.

---

### DOCKER-02 — Unsafe Build Context

The vulnerable build intentionally demonstrates the consequences of copying unnecessary deployment material into an image.

**Problem:**

Broad `COPY . .` patterns can unintentionally include:

- `.env`
- backups
- private keys
- deployment files
- Git metadata

---

### AUTH-01 — Authorization Derived From Client Artifact

The release endpoint incorrectly relies on a client-supplied encrypted authorization object.

**Problem:**

The server should derive authorization from:

```text
Authenticated Identity
        +
Server-Side Resource Policy
        +
Server-Side Resource State
```

not merely from client-controlled claims.

---

## Application Roles

### Operator

Permissions:

- login
- view terminals
- view containers
- inspect containers
- place holds

Cannot normally:

- release containers

### Supervisor

Permissions:

- all operator permissions
- release authorized containers

### Security Administrator

Permissions:

- administrative operations
- audit investigation
- security/debugging functions

---

## Seed Data

All credentials are **fake training credentials**.

| Role | Email | Password |
|---|---|---|
| Operator | `operator@aegisdock.local` | `Operator123!` |
| Supervisor | `supervisor@aegisdock.local` | `Supervisor123!` |
| Security Admin | `security@aegisdock.local` | `Security123!` |

> Never reuse these credentials on real systems.

### Primary Training Object

```text
Terminal: T-07
Container: CONT-88421
Initial state: READY
```

---

## Core API

The exact implementation may evolve, but the laboratory is designed around these routes.

### Authentication

```http
POST /api/login
GET  /api/me
```

### Terminal Discovery

```http
GET /api/terminals
GET /api/terminals/{terminal}
```

### Containers

```http
GET  /api/containers
GET  /api/containers/{container}
POST /api/containers/{container}/inspect
POST /api/containers/{container}/hold
POST /api/containers/{container}/release
```

### Authorization Artifacts

```http
GET /api/authorization/artifact
GET /api/debug/authorization/{id}
```

### Audit

```http
GET /api/audit
```

---

## Release Request

The sensitive endpoint is conceptually:

```http
POST /api/containers/CONT-88421/release
Authorization: Bearer <session-token>
X-Terminal-Authorization: <encrypted-artifact>

{
  "reason": "scheduled release"
}
```

A normal operator should receive a denial.

The vulnerable implementation is intentionally designed so that the authorization artifact becomes a critical security boundary.

---

## Authorization Artifact

The vulnerable artifact represents data similar to:

```json
{
  "operator_id": 1001,
  "terminal": "T-07",
  "container": "CONT-88421",
  "action": "VIEW",
  "issued_at": 1770000000,
  "expires_at": 1770003600
}
```

It is encrypted and transported as an encoded ciphertext.

### Vulnerable Design

```text
Plaintext
   |
   v
AES-256-CBC
   |
   +--> static IV
   |
   +--> no authentication tag
   |
   v
Encoded Ciphertext
   |
   v
Client
   |
   v
Server
   |
   v
Decrypt
   |
   v
Trust authorization fields
```

### Secure Design

```text
Authorization Context
        |
        v
Server-side authorization decision
        |
        v
AEAD where encryption is actually required
        |
        +--> unique nonce
        +--> authentication tag
        |
        v
Authenticated ciphertext
```

---

## Docker Secret-Leak Scenario

The vulnerable image intentionally demonstrates layer persistence.

Conceptually:

```dockerfile
# Layer N
COPY deploy/archive/terminal-gateway.env.bak /app/archive/

# Layer N+1
RUN rm -f /app/archive/terminal-gateway.env.bak
```

The final filesystem may appear clean.

However:

```text
Layer N
  secret exists

Layer N+1
  secret deleted from current filesystem

Image
  old layer still contains the secret
```

### Training Secret

The vulnerable image may contain a configuration artifact similar to:

```text
TERMINAL_CRYPTO_KEY=<training-only-key>
TERMINAL_CRYPTO_IV=<training-only-iv>
TERMINAL_API_URL=http://terminal-controller:9000
```

These values must never be real production credentials.

### Investigation Concepts

Useful local commands include:

```bash
docker history --no-trunc <image>
docker image inspect <image>
docker save <image> -o image.tar
```

The laboratory documentation should explain how to inspect image layers and recover the intentionally leaked training artifact.

---

## Cryptographic Failure Scenario

### Vulnerable

```text
AES-256-CBC
+
Static IV
+
No MAC
+
No authentication tag
+
Client-controlled encrypted authorization
```

### Why This Matters

AES is not the problem by itself.

The failure is the **system design around AES**.

A cryptographically strong primitive can still produce an insecure application when:

- the mode is inappropriate
- the IV is mishandled
- integrity is missing
- keys are exposed
- authorization trusts attacker-controlled data
- key lifecycle is unmanaged

### Secure Alternative

For a modern application, use an authenticated encryption construction such as:

- AES-256-GCM
- ChaCha20-Poly1305

The secure implementation should use:

```text
unique nonce
+
ciphertext
+
authentication tag
```

and reject modified ciphertext.

---

## CBC Malleability Concept

CBC decryption can be represented as:

```text
P_i = D_K(C_i) XOR C_(i-1)
```

If an attacker modifies the previous ciphertext block:

```text
C'_(i-1) = C_(i-1) XOR Δ
```

then:

```text
P'_i = P_i XOR Δ
```

The attacker does not need to perform AES decryption.

The attack relies on the algebraic structure of CBC.

### Important Limitation

This does **not** mean an attacker can arbitrarily transform any ciphertext into any desired message.

Practical exploitability depends on:

- plaintext structure
- block boundaries
- predictable fields
- padding
- encoding
- parsing
- validation
- application logic

AegisDock intentionally designs the training artifact so learners can study this relationship deterministically.

---

## Audit and Forensics

The application should record events such as:

- `LOGIN`
- `AUTHORIZATION_ARTIFACT_CREATED`
- `CONTAINER_VIEWED`
- `CONTAINER_INSPECTED`
- `CONTAINER_HOLD`
- `CONTAINER_RELEASE_ATTEMPT`
- `CONTAINER_RELEASE`
- `AUTHORIZATION_FAILURE`
- `AUTHORIZATION_SUCCESS`

Recommended fields:

| Field | Purpose |
|---|---|
| user_id | Actor |
| timestamp | Timeline |
| container | Affected asset |
| terminal | Affected terminal |
| source_ip | Origin |
| user_agent | Client context |
| action | Operation |
| result | Success/failure |
| request_id | Correlation |

### Do Not Log

Never log:

- AES keys
- passwords
- session secrets
- complete sensitive authorization artifacts
- real production credentials

The debug endpoint may expose safe metadata such as:

- algorithm
- IV length
- ciphertext length
- authorization record ID
- expiry
- action metadata

but must not reveal the actual cryptographic key.

---

## Project Structure

The intended project layout is:

```text
aegisdock/
├── frontend/
│   ├── app/
│   ├── components/
│   └── ...
│
├── api/
│   ├── app/
│   ├── routes/
│   ├── database/
│   ├── tests/
│   └── ...
│
├── nginx/
│   └── nginx.conf
│
├── registry-simulator/
│   ├── app/
│   └── ...
│
├── audit-service/
│   ├── app/
│   └── ...
│
├── docker/
│   ├── vulnerable/
│   └── secure/
│
├── deploy/
│   └── archive/
│
├── docs/
│   ├── architecture.md
│   ├── attack-path.md
│   ├── cryptography.md
│   ├── docker-secrets.md
│   ├── api.md
│   ├── remediation.md
│   ├── threat-model.md
│   └── instructor-solution.md
│
├── scripts/
├── tests/
├── docker-compose.yml
├── Dockerfile.vulnerable
├── Dockerfile.secure
├── .dockerignore
├── Makefile
└── README.md
```

---

## Technology Stack

### Backend

- PHP 8.3+
- Laravel
- REST API
- PostgreSQL

### Frontend

- Next.js
- TypeScript
- Tailwind CSS

### Infrastructure

- Docker
- Docker Compose
- Nginx
- Optional Redis

### Security Tooling

- Burp Suite
- curl
- OpenSSL
- Nmap
- Docker CLI
- Trivy
- Git

No external cloud account is required.

---

## Prerequisites

Recommended environment:

- Linux
- Ubuntu or Kali Linux
- Docker Engine
- Docker Compose
- Git
- Node.js
- PHP/Composer if developing outside containers
- Burp Suite Community or Professional

Verify:

```bash
docker --version
docker compose version
git --version
node --version
```

---

## Quick Start

> The commands below describe the intended workflow. They may change as implementation progresses.

### Clone

```bash
git clone https://github.com/Maleesha101/AEGISDOCK.git
cd AEGISDOCK
```

### Start the Laboratory

```bash
make up
```

Open the local dashboard at **http://localhost:8080**. Nginx is the only host-published service by default; the API, PostgreSQL, registry simulator, and audit service remain on the internal Compose network.

Or:

```bash
docker compose up --build
```

### Seed Data

```bash
make seed
```

### Check Services

```bash
docker compose ps
```

### View Logs

```bash
make logs
```

### Stop

```bash
make down
```

### Reset

```bash
make reset
```

A reset should remove only laboratory data and containers.

---

## Laboratory Workflow

The recommended learning sequence is:

```text
1. Understand the business process
2. Map the attack surface
3. Establish a baseline
4. Enumerate API endpoints
5. Test role boundaries
6. Discover image/artifact exposure
7. Inspect Docker metadata
8. Recover historical training secret
9. Analyze crypto implementation
10. Identify CBC/static-IV/no-integrity design
11. Obtain authorization artifact
12. Analyze block structure
13. Perform controlled lab-only manipulation
14. Verify business impact
15. Review audit evidence
16. Implement secure version
17. Re-test the entire chain
18. Document findings
```

Do not skip the baseline.

Before attempting exploitation, prove that:

```operator -> release
```

is denied normally.

Then prove that:

```supervisor -> release
```

works legitimately.

This establishes the authorization boundary.

---

## Reconnaissance

Start with application behavior rather than immediately exploiting the vulnerability.

### Identify the Application

```bash
curl -i http://localhost/
```

### Discover API Routes

Use:

- browser developer tools
- Burp Proxy
- Burp Target
- application documentation
- API responses

### Observe Authentication

Record:

- cookies
- bearer tokens
- CSRF behavior if applicable
- response codes
- redirect behavior
- security headers

### Build an Endpoint Map

Example:

```text
/api/login
/api/me
/api/terminals
/api/containers
/api/authorization/artifact
/api/containers/{id}/release
/api/audit
```

Do not assume the endpoint list is complete.

---

## Docker Image Investigation

Once the vulnerable image is identified:

### Metadata

```bash
docker image inspect <image>
```

Look for:

- environment variables
- labels
- entrypoint
- working directory
- exposed ports

### History

```bash
docker history --no-trunc <image>
```

Look for suspicious commands involving:

- `COPY`
- `ADD`
- configuration backups
- `.env`
- keys
- deployment archives
- `rm`

### Export

```bash
docker save <image> -o aegisdock-image.tar
```

The objective is to understand how historical layers preserve data.

### Security Scanner

```bash
trivy image <image>
```

Trivy findings are useful, but image scanning should not replace manual layer analysis.

---

## Cryptographic Analysis

Once application source/configuration is available for the training scenario, investigate:

### Algorithm

Identify:

```text
AES-256
```

### Mode

Identify:

```text
CBC
```

### IV

Determine whether the IV is:

- random
- unique
- generated per encryption
- static
- derived from a predictable value

### Integrity

Ask:

```text
Where is the authentication tag?
Where is the MAC?
How does the server detect ciphertext modification?
```

If the answer is "nowhere", investigate the consequence.

### Key Management

Determine:

- where the key originates
- where it is stored
- whether it appears in source code
- whether it appears in image history
- whether it is injected at runtime
- whether rotation is possible

---

## Ciphertext-Malleability Investigation

The laboratory should teach the concept rather than turn it into a blind guessing exercise.

A recommended investigation sequence is:

1. Obtain a legitimate artifact.
2. Decode its transport encoding.
3. Determine ciphertext length.
4. Understand the plaintext schema from legitimate application behavior.
5. Determine block boundaries.
6. Identify predictable fields.
7. Study CBC's XOR relationship.
8. Create a controlled modified artifact.
9. Submit it only to the local laboratory.
10. Observe server behavior.
11. Compare the result with the original artifact.
12. Inspect audit logs.

The goal is to understand **why the server accepted modified data**, not merely to obtain the flag.

---

## Business-Logic Impact

The technical vulnerability becomes meaningful when it crosses a business boundary.

Expected vulnerable path:

```text
Low-privileged context
        |
        v
Encrypted authorization artifact
        |
        v
Ciphertext modification
        |
        v
Modified authorization state
        |
        v
Release endpoint
        |
        v
Container state changes
```

The simulated terminal should change only local application state.

For example:

```text
READY
  |
  | unauthorized release
  v
RELEASED
```

No real port, crane, container, vehicle, PLC, or industrial controller is involved.

---

## Defensive Implementation

The secure version should break the chain at multiple points.

### 1. Remove Client-Controlled Authorization

Do not treat an encrypted client artifact as the authority for a sensitive operation.

Instead:

```text
Authenticated User
        |
        v
Server-side role
        |
        v
Server-side resource policy
        |
        v
Current resource state
        |
        v
Authorization decision
```

---

### 2. Use AEAD

Replace unauthenticated CBC with an authenticated encryption construction.

Preferred examples:

- AES-256-GCM
- ChaCha20-Poly1305

The implementation should generate a unique nonce for every encryption operation.

---

### 3. Verify Authentication Before Decryption Use

The secure implementation must reject:

- modified ciphertext
- invalid tags
- malformed artifacts
- expired artifacts
- replayed artifacts where freshness is required

---

### 4. Protect Keys

Do not store production secrets in:

- source code
- Git history
- Dockerfile
- Docker image layers
- Docker `ARG`
- Docker `ENV`

Use a runtime secret mechanism appropriate for the deployment environment.

For local development, use controlled local secret injection.

---

### 5. Use BuildKit Secrets

For build-time secrets, prefer Docker BuildKit secret mounts rather than copying secret files into image layers.

Conceptually:

```text
Build-time secret
      |
      v
temporary build mount
      |
      v
build operation
      |
      v
secret absent from resulting layer
```

---

### 6. Restrict Build Context

Use `.dockerignore` to exclude:

```text
.env
.env.*
.git
*.key
*.pem
*.secret
backup/
deploy/archive/
node_modules/
vendor/
```

The exact rules should match the project.

---

### 7. Rotate Leaked Secrets

If a real secret ever enters an image:

```text
1. Revoke
2. Rotate
3. Remove from build context
4. Rebuild
5. Scan
6. Verify
7. Redeploy
```

Deleting the file in a later Docker layer is not sufficient.

---

## Verification and Testing

Security remediation must be demonstrated with tests.

### Vulnerable Build Tests

Verify that:

- the vulnerable image builds
- the training artifact exists in historical layers
- the application starts
- operator release is denied normally
- supervisor release works legitimately
- the vulnerable artifact is accepted
- modified ciphertext can reach the vulnerable authorization path
- audit records capture the operation

### Secure Build Tests

Verify that:

- no secret exists in the final image
- no historical build layer contains training/production secrets
- crypto uses AEAD
- nonce uniqueness is enforced
- modified ciphertext is rejected
- invalid authentication tags are rejected
- expired artifacts are rejected
- operators cannot release containers
- supervisors can perform authorized releases
- authorization is derived server-side
- audit records remain usable

### Regression Test Principle

Every discovered vulnerability should produce a regression test.

For example:

```text
Finding: ciphertext modification accepted

Regression test:
  modify ciphertext
  submit artifact
  expect HTTP 401/403/422
  expect container state unchanged
```

---

## Flags

The laboratory can expose progressive flags.

| Flag | Objective |
|---|---|
| FLAG-01 | Reconnaissance |
| FLAG-02 | Container/image discovery |
| FLAG-03 | Docker layer discovery |
| FLAG-04 | Training secret recovery |
| FLAG-05 | Cryptographic weakness identification |
| FLAG-06 | Authorization artifact discovery |
| FLAG-07 | Controlled ciphertext manipulation |
| FLAG-08 | Unauthorized release |
| FLAG-09 | Forensic reconstruction |
| FLAG-10 | Successful remediation |

Flags should validate understanding of the stage rather than directly reveal the next answer.

The instructor solution may contain exact flag locations and validation details.

---

## Development Roadmap

The project should be built incrementally.

### Phase 1 — Architecture

- define components
- create Docker Compose
- define ports
- establish service health checks
- create documentation skeleton

### Phase 2 — Database

- users
- roles
- terminals
- containers
- authorization records
- audit events

### Phase 3 — Authentication

- login
- session/token management
- current-user endpoint
- role model

### Phase 4 — Container Management

- list terminals
- list containers
- inspect
- hold
- release

### Phase 5 — Authorization Artifact

- artifact generation
- artifact transport
- vulnerable validation path
- secure validation path

### Phase 6 — Vulnerable Cryptography

Implement only the intended training weaknesses:

- AES-CBC
- static IV
- missing integrity protection

Clearly mark vulnerable code.

### Phase 7 — Vulnerable Docker Image

Introduce the historical layer secret scenario.

### Phase 8 — Registry Simulator

Create a local-only image/artifact discovery service.

### Phase 9 — Audit System

Implement security-relevant event recording.

### Phase 10 — Frontend

Build the operator interface.

### Phase 11 — Security Tests

Add automated vulnerability and regression tests.

### Phase 12 — Secure Implementation

Implement:

- AEAD
- runtime secret injection
- secure image build
- server-side authorization
- validation
- replay/expiry protections

### Phase 13 — Documentation

Complete:

- attack path
- crypto explanation
- Docker investigation
- threat model
- remediation
- instructor solution

---

## Git Commit Strategy

Development should be incremental.

Recommended commits:

```text
feat: initialize aegisdock architecture
feat: implement database and seed data
feat: implement authentication
feat: implement container management
feat: implement authorization artifacts
feat: add vulnerable cryptographic implementation
feat: add vulnerable docker image
feat: add registry simulator
feat: add audit system
feat: add frontend
test: add vulnerability verification tests
docs: add attack path
feat: add secure cryptographic implementation
feat: add secure docker build
test: verify remediation
docs: complete instructor solution
```

Each phase should:

1. implement
2. run tests
3. inspect behavior
4. update documentation
5. commit

Avoid a single giant implementation commit.

---

## Security Design Requirements

### Vulnerable Environment

The vulnerable environment MUST:

- be clearly labeled
- use fake credentials
- use fake keys
- use simulated infrastructure
- be isolated to local training
- contain only intentionally introduced weaknesses

### Secure Environment

The secure environment MUST:

- use authenticated encryption where encryption is required
- generate unique nonces
- validate authentication tags
- use server-side authorization
- keep secrets outside images
- remove secrets from build context
- rotate compromised credentials
- provide security regression tests
- preserve useful audit evidence

---

## What This Lab Does Not Do

AegisDock does **not** connect to:

- real port infrastructure
- real shipping systems
- PLCs
- SCADA systems
- real industrial controllers
- real cloud accounts
- production databases
- third-party credentials
- external secrets

The "terminal controller" is a simulation.

The container release operation modifies only local training state.

---

## Recommended Security Tools

### Burp Suite

Use for:

- proxying HTTP traffic
- endpoint discovery
- request modification
- authorization testing
- replay
- comparing normal and modified requests

### curl

Use for:

- endpoint verification
- header inspection
- API automation
- reproducing requests

### Docker CLI

Use for:

- image inspection
- image history
- layer investigation
- container inspection

### OpenSSL

Use for:

- certificate/TLS investigation if TLS is enabled
- cryptographic experimentation
- encoding/decoding support

### Trivy

Use for:

- image scanning
- filesystem scanning
- configuration checks
- secret detection where supported

### Git

Use for:

- source history
- accidental secret investigation
- controlled remediation
- security regression tracking

---

## Documentation

The repository should eventually contain the following documents:

### `docs/architecture.md`

Complete component and data-flow architecture.

### `docs/attack-path.md`

Attacker-perspective investigation from reconnaissance through impact.

### `docs/cryptography.md`

Detailed explanation of:

- CBC
- IVs
- integrity
- AEAD
- ciphertext malleability
- key management

### `docs/docker-secrets.md`

Detailed explanation of:

- Docker layers
- image history
- build context
- BuildKit secrets
- runtime secrets
- rotation

### `docs/api.md`

API endpoints, request/response examples and authorization requirements.

### `docs/remediation.md`

Secure design and implementation guidance.

### `docs/threat-model.md`

Assets, actors, attack surfaces, trust boundaries and security assumptions.

### `docs/instructor-solution.md`

Complete solution path, exact flags and expected findings.

The instructor solution should not be necessary to complete the learner path.

---

## Suggested Learning Questions

While completing the lab, answer these questions:

### Docker

- Why can a deleted file remain recoverable from an image?
- What information does `docker history --no-trunc` expose?
- Why is `ARG` unsuitable for secrets?
- Why is `ENV` unsuitable for secrets?
- When should BuildKit secret mounts be used?

### Cryptography

- What does AES guarantee?
- What does AES-CBC fail to guarantee?
- Why does CBC permit controlled plaintext modification?
- Why does a MAC prevent the attacker from simply modifying ciphertext?
- What does an AEAD tag add?
- Why must a nonce/IV be unique?

### Application Security

- Why should authorization not be delegated to encrypted client data?
- What happens if confidentiality is preserved but integrity is missing?
- What is the difference between authentication and authorization?
- How should the server decide whether a container may be released?

### Incident Response

- What evidence would prove the attack occurred?
- Which secret should be rotated first?
- What Docker artifacts need to be rebuilt?
- How would you detect similar image leaks elsewhere?

---

## Expected End State

A successful implementation should provide two clearly separated modes:

```text
+-----------------------+
| Vulnerable Environment|
+-----------------------+
| CBC                   |
| Static IV             |
| No integrity          |
| Image secret leak     |
| Client authorization  |
+-----------------------+

             VS

+-----------------------+
| Secure Environment   |
+-----------------------+
| AEAD                  |
| Unique nonce          |
| Authentication tag    |
| Runtime secrets       |
| Clean image history   |
| Server authorization  |
+-----------------------+
```

The learner should be able to demonstrate that the secure implementation breaks the original attack chain at several independent points.

---

## Responsible Use

AEGISDOCK is intentionally vulnerable software.

Use it only in an environment you own or are explicitly authorized to test.

Recommended setup:

```text
Host
 |
 +-- Docker network
      |
      +-- AegisDock API
      +-- AegisDock frontend
      +-- PostgreSQL
      +-- Registry simulator
      +-- Terminal simulator
```

Do not expose the vulnerable services to the public Internet.

Do not place real passwords, API keys, SSH keys, cloud credentials, certificates, private keys or production data into this project.

If a real secret is accidentally introduced during development:

1. revoke it
2. rotate it
3. remove it from the project
4. rebuild affected images
5. scan the rebuilt images
6. verify the secret is no longer present

---

## Contributing

Contributions should preserve the educational purpose of the project.

When adding a new vulnerability:

1. Document the vulnerability.
2. Give it a unique identifier.
3. Explain the intended learning objective.
4. Keep it isolated and reproducible.
5. Add a verification test.
6. Add remediation guidance.
7. Avoid introducing unrelated vulnerabilities.
8. Keep the environment local and safe.

Example identifier:

```text
CRYPTO-04
DOCKER-03
AUTH-02
AUDIT-01
```

---

## License

This repository is intended as an educational security laboratory.

If a formal open-source license is added, it should be documented here and in a root `LICENSE` file.

---

## Project Status

**Status:** Laboratory architecture / implementation in progress.

The repository is intended to evolve from the documented scenario into a fully functional vulnerable-versus-secure training environment.

---

## Core Security Lesson

The central AegisDock lesson is:

```text
A vulnerability rarely exists in isolation.

Docker secret leakage
        +
Weak cryptographic design
        +
Client-controlled authorization
        +
Business-sensitive operation
        =
A realistic attack chain
```

The defensive lesson is equally important:

```text
Secure cryptography
        +
Secure key management
        +
Secure container builds
        +
Server-side authorization
        +
Auditability
        +
Regression testing
        =
Defense in depth
```

**AEGISDOCK is a laboratory for learning how individual security failures can combine into a meaningful application-security incident — and how to break that chain systematically.**
