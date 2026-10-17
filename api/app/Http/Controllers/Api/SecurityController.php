<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlagService;
use App\Services\SecureCryptoService;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function verify(Request $request, FlagService $flags)
    {
        if (config('crypto.mode', 'vulnerable') !== 'secure') {
            return response()->json([
                'success' => false,
                'message' => 'Start the secure profile before requesting remediation verification.',
            ], 409);
        }

        if (file_exists('/opt/legacy/terminal-gateway.env.bak')) {
            return response()->json([
                'success' => false,
                'message' => 'Unexpected legacy deployment artifact exists in the runtime filesystem.',
            ], 409);
        }

        try {
            app(SecureCryptoService::class);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Secure runtime key is unavailable or invalid.',
            ], 503);
        }

        $flag = $flags->valueFor('FLAG-10');
        if ($flag === null) {
            return response()->json([
                'success' => false,
                'message' => 'Flag service is not initialized.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => 'secure',
                'runtime_key_available' => true,
                'legacy_artifact_in_final_filesystem' => false,
                'flag' => $flag,
            ],
        ]);
    }
}
