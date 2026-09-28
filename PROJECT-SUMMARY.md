# AegisDock Project Summary

## Overview
AegisDock is an intentionally vulnerable cybersecurity laboratory for studying chained cryptographic failures, Docker image/layer secret leakage, authorization weaknesses, and security remediation.

## Attack Chain
The central attack chain documented in the laboratory:

```
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
```

## Key Components

### API (Laravel)
- `api/app/Services/CryptoService.php` - Vulnerable AES-CBC implementation without HMAC
- `api/app/Services/SecureCryptoService.php` - Secure AES-GCM implementation
- `api/app/Http/Middleware/SecurityHeaders.php` - HTTP security headers middleware
- `api/routes/api.php` - API endpoints for container operations and authorization

### Docker
- `docker/vulnerable/Dockerfile` - Intentionally leaks secrets in image layers
- `docker/secure/Dockerfile` - Uses runtime secret injection, no hardcoded secrets

### Documentation
- `docs/vulnerability-inventory.md` - Catalog of intentional vulnerabilities
- `docs/architecture.md` - System architecture documentation
- `docs/attack-path.md` - Detailed attack path documentation
- `docs/cryptography.md` - Cryptographic implementation details
- `docs/docker-secrets.md` - Docker secret management guidance
- `docs/instructor-solution.md` - Instructor solution guide
- `docs/remediation.md` - Remediation and defensive implementation guide
- `docs/security-scanning.md` - Security scanning tools and techniques
- `docs/threat-model.md` - Threat model and risk assessment
- `docs/vulnerability-inventory.md` - Complete vulnerability list

### Tests
- `tests/CryptoTest.php` - Comparing vulnerable vs secure crypto
- `tests/CryptoVulnTest.php` - Vulnerable crypto implementation tests
- `tests/Integration/VulnerableReleaseTest.php` - Attack chain validation tests
- `tests/Integration/SecureReleaseTest.php` - Secure release flow tests
- `tests/TestCase.php` - Base test case configuration

### Scripts
- `scripts/security-audit.py` - Scans for hardcoded secrets and crypto issues
- `scripts/exploit-demo.py` - CBC ciphertext manipulation demonstration

### Configuration
- `api/.env.example` - Environment configuration with crypto warnings
- `Makefile` - Build, test, and deployment targets
- `.github/workflows/phpunit.yml` - CI/CD pipeline

## Technology Stack
- **Backend**: Laravel 10+ (PHP)
- **Frontend**: Next.js 14+ (TypeScript)
- **Database**: PostgreSQL
- **Cache/Queue**: Redis
- **Containerization**: Docker with intentional vulnerabilities
- **Security**: AES-CBC (vulnerable) vs AES-GCM (secure)