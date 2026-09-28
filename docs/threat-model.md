# AegisDock Threat Model

## Assets

| Asset | Description | Sensitivity |
|-------|-------------|-------------|
| Container release authorization | Ability to release containers | CRITICAL |
| Terminal control | Control over port terminal operations | HIGH |
| Shipment information | Cargo details, manifests, destinations | MEDIUM |
| Operator credentials | Login credentials for system access | HIGH |
| Cryptographic keys | AES keys used for authorization artifacts | CRITICAL |
| Audit logs | System activity trail for forensics | MEDIUM |

## Threat Actors

### 1. Unauthorized External User
- **Capabilities**: Network access, web scraping, OSINT
- **Motivation**: Disrupt operations, steal cargo, gain notoriety
- **Attack Vectors**: Web application, API endpoints, exposed services

### 2. Compromised Operator Account
- **Capabilities**: Valid operator credentials, limited system access
- **Motivation**: Personal gain, revenge, coercion
- **Attack Vectors**: Phishing, credential stuffing, social engineering

### 3. Malicious Insider
- **Capabilities**: Valid elevated credentials, system knowledge
- **Motivation**: Financial gain, ideology, revenge
- **Attack Vectors**: Privilege abuse, data exfiltration, sabotage

## Attack Surfaces

### Web Frontend
- **Components**: Next.js application, React components
- **Vulnerabilities**: XSS, CSRF, information disclosure
- **Mitigation**: CSP, input validation, secure headers

### REST API
- **Components**: Laravel endpoints, Sanctum auth
- **Vulnerabilities**: Broken auth, excessive data exposure, rate limiting
- **Mitigation**: Proper auth, validation, rate limiting, logging

### Container Registry
- **Components**: Registry simulator, image metadata
- **Vulnerabilities**: Information disclosure, secret leakage
- **Mitigation**: Access controls, image scanning, secret detection

### Docker Image
- **Components**: Image layers, build history, filesystem
- **Vulnerabilities**: Secret leakage, embedded credentials
- **Mitigation**: .dockerignore, multi-stage builds, runtime secrets

### Audit/Debug Endpoints
- **Components**: Audit service, debug endpoints
- **Vulnerabilities**: Information disclosure, excessive logging
- **Mitigation**: Access controls, data minimization, secure defaults

### Authorization Artifact
- **Components**: Encrypted tokens, CBC mode
- **Vulnerabilities**: Ciphertext manipulation, replay attacks, weak crypto
- **Mitigation**: AEAD encryption, unique nonces, short expiration

## Trust Boundaries

```
[External User] 
        ↓ (untrusted network)
[Internet] 
        ↓ (TLS terminated)
[Nginx Reverse Proxy] 
        ↓ (internal trusted)
[API Gateway] 
        ↓ (application trusted)
[Application Logic] 
        ↓ (service trusted)
[Database] 
        ↓ (data trusted)
[Terminal Controller] 
        ← (simulated, no real control)
```

## Risk Assessment

### Critical Risks
1. **Authorization Bypass** (CRITICAL)
   - Impact: Unauthorized container release
   - Likelihood: High (given current vulnerabilities)
   - Mitigation: Server-side role enforcement primary

2. **Cryptographic Key Exposure** (CRITICAL)
   - Impact: Full system compromise
   - Likelihood: Medium (requires image access)
   - Mitigation: Runtime secret injection, .dockerignore

### High Risks
1. **Ciphertext Malleability** (HIGH)
   - Impact: Authorization escalation
   - Likelihood: High (trivial with CBC + static IV)
   - Mitigation: AEAD encryption

2. **Static IV Usage** (HIGH)
   - Impact: Pattern analysis, IV reuse attacks
   - Likelihood: High
   - Mitigation: Unique nonce per encryption

### Medium Risks
1. **Insufficient Rate Limiting** (MEDIUM)
   - Impact: Credential stuffing, DoS
   - Likelihood: Medium
   - Mitigation: Rate limiting middleware

2. **Information Disclosure in Logs** (MEDIUM)
   - Impact: Cryptographic metadata leakage
   - Likelihood: Low (debug endpoint only)
   - Mitigation: Remove/restrict debug endpoints

## Mitigation Strategies

### Cryptographic Improvements
- Replace AES-256-CBC with AES-256-GCM
- Use unique nonce for every encryption
- Verify authentication tag before trusting plaintext
- Store keys in runtime environment/secrets

### Docker Security
- Add comprehensive .dockerignore
- Never copy secrets during build
- Use runtime secret injection
- Regular image scanning with Trivy/similar

### Application Security
- Implement server-side role checks as primary
- Use artifacts only for audit trail
- Add rate limiting on auth endpoints
- Validate and sanitize all inputs
- Implement proper logging without secrets

### Monitoring & Detection
- Audit all authorization events
- Alert on failed authorization attempts
- Monitor for anomalous container releases
- Regular security scanning of images