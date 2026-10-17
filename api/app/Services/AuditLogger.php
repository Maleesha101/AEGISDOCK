<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Persist a security-relevant event without storing tokens, keys, passwords,
     * or raw authorization artifacts.
     */
    public function record(
        Request $request,
        string $action,
        ?string $resourceType = null,
        string|int|null $resourceId = null,
        array $metadata = [],
        ?int $userId = null
    ): void {
        AuditLog::create([
            'user_id' => $userId ?? $request->user()?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId === null ? null : (string) $resourceId,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
