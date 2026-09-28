# AegisDock Architecture

## Overview

AegisDock is a microservices architecture simulating a port logistics platform.

```
┌─────────────┐
│   Browser   │
│  (Frontend) │
└──────┬──────┘
       │ HTTPS
       ▼
┌─────────────┐
│    Nginx    │
│  (Reverse   │
│   Proxy)    │
└──────┬──────┘
       │ HTTP
       ▼
┌─────────────┐
│  Frontend   │
│  (Next.js)  │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│    API      │
│  (Laravel)  │
└──────┬──────┘
       │
       ▼
┌─────────────┐     ┌──────────────────┐
│ PostgreSQL  │     │ Registry         │
│             │     │ Simulator        │
└─────────────┘     └──────────────────┘

Audit Trail (separate service):
┌─────────────┐
│ Audit       │
│ Service     │
│ (Express)   │
└─────────────┘
```

## Trust Boundaries

```
Browser (untrusted)
   │
Internet (untrusted)
   │
Nginx (trusted - TLS termination)
   │
Internal API (trusted - but vulnerable)
   │
Database (trusted)
   │
Terminal Controller (trusted - simulation only)
```

## Components

### Frontend
- Next.js application with TypeScript
- Professional dashboard UI
- Login, dashboard, terminals, containers, audit, security pages

### API (Laravel)
- REST API with authentication
- AES-256-CBC for authorization artifacts (intentionally vulnerable)
- Role-based access control (operator, supervisor, security_admin)
- Audit logging

### Database (PostgreSQL)
- Users, roles, terminals, containers, container_events
- Authorization artifacts, audit logs, deployment metadata

### Registry Simulator
- Simulates internal container registry
- Exposes image metadata and build history
- For training discovery phase

### Audit Service
- Simulates terminal controller audit trail
- Logs container events and operations

## Key Files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Orchestrates all services |
| `docker/vulnerable/Dockerfile` | Creates vulnerable image |
| `docker/secure/Dockerfile` | Creates secure image |
| `api/app/Services/CryptoService.php` | Vulnerable crypto implementation |
| `api/app/Services/SecureCryptoService.php` | Secure crypto implementation |
| `api/routes/api.php` | API routes |
| `deploy/archive/terminal-gateway.env.bak` | Leaked secret file |