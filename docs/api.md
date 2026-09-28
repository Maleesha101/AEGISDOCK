# AegisDock API Reference

## Authentication

### POST /api/login

Login with email and password.

**Request:**
```json
{
  "email": "operator@aegisdock.local",
  "password": "Operator123!"
}
```

**Response:**
```json
{
  "success": true,
  "token": "sanctum-token-here",
  "user": {
    "id": 1,
    "name": "Terminal Operator",
    "email": "operator@aegisdock.local",
    "role": { "name": "operator" }
  }
}
```

### GET /api/me

Get authenticated user info.

**Headers:**
```
Authorization: Bearer <token>
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Terminal Operator",
    "email": "operator@aegisdock.local",
    "role": { "name": "operator" }
  }
}
```

## Terminals

### GET /api/terminals

List all terminals.

### GET /api/terminals/{code}

Get terminal details by code.

## Containers

### GET /api/containers

List all containers. Optional query: `terminal_id`.

### GET /api/containers/{containerNumber}

Get container details.

### POST /api/containers/{containerNumber}/inspect

Inspect a container.

### POST /api/containers/{containerNumber}/hold

Hold a container (supervisor+ only).

**Request:**
```json
{
  "reason": "Security inspection required"
}
```

### POST /api/containers/{containerNumber}/release

Release a container.

**Headers:**
```
Authorization: Bearer <token>
X-Terminal-Authorization: <encrypted-artifact>  // Optional
```

**Request:**
```json
{
  "reason": "scheduled release"
}
```

## Authorization Artifacts

### GET /api/authorization/artifact

Get authorization artifact for terminal/container.

**Query Parameters:**
- `terminal`: Terminal code (e.g., T-07)
- `container`: Container number (e.g., CONT-88421)

**Response:**
```json
{
  "success": true,
  "data": {
    "artifact": "base64-ciphertext",
    "terminal": "T-07",
    "container": "CONT-88421",
    "action": "VIEW",
    "issued_at": 1770000000,
    "expires_at": 1770003600
  }
}
```

### GET /api/debug/authorization/{id}

Debug endpoint - get cryptographic metadata.

**Response:**
```json
{
  "success": true,
  "data": {
    "algorithm": "AES-256-CBC",
    "iv": "TRAINING-FAKE-IV-1234567890ABC",
    "ciphertext": "base64-ciphertext",
    "ciphertext_length": 80,
    "metadata": {
      "operator_id": 1001,
      "terminal": "T-07",
      "container": "CONT-88421",
      "action": "VIEW",
      "issued_at": 1770000000,
      "expires_at": 1770003600
    }
  }
}
```

## Audit

### GET /api/audit

Get audit logs.

**Query Parameters:**
- `action`: Filter by action
- `resource_type`: Filter by resource type
- `user_id`: Filter by user
- `terminal`: Filter by terminal
- `container`: Filter by container
- `limit`: Results per page (default 50)