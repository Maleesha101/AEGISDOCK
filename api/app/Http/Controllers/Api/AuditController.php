<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditController extends Controller
{
    /**
     * Get audit logs.
     */
    public function index(Request $request)
    {
        $query = AuditLog::query();

        // Filter options
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('terminal')) {
            $query->where('metadata->terminal', $request->terminal);
        }
        if ($request->has('container')) {
            $query->where('metadata->container', $request->container);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 50));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}