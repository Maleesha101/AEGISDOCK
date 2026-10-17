<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlagSubmission;
use App\Services\FlagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FlagController extends Controller
{
    public function index(Request $request, FlagService $flags)
    {
        $submitted = FlagSubmission::where('user_id', $request->user()->id)
            ->pluck('flag_code')
            ->all();

        $catalog = [];
        foreach ($flags->catalog() as $code => $details) {
            $catalog[] = [
                'code' => $code,
                'description' => $details['description'],
                'points' => $details['points'],
                'submitted' => in_array($code, $submitted, true),
            ];
        }

        return response()->json(['success' => true, 'data' => $catalog]);
    }

    public function submit(Request $request, FlagService $flags)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'regex:/^FLAG-[0-9]{2}$/'],
            'flag' => ['required', 'string', 'max:256'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid flag submission format',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = $request->string('code')->toString();
        $expected = $flags->valueFor($code);

        if ($expected === null) {
            return response()->json([
                'success' => false,
                'message' => 'Flag service is not initialized. Run the local setup script.',
            ], 503);
        }

        $submitted = trim($request->string('flag')->toString());
        if (!hash_equals($expected, $submitted)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect flag',
            ], 422);
        }

        $submission = FlagSubmission::firstOrCreate(
            ['user_id' => $request->user()->id, 'flag_code' => $code],
            ['submitted_at' => now()]
        );

        $details = $flags->catalog()[$code];

        return response()->json([
            'success' => true,
            'message' => $submission->wasRecentlyCreated ? 'Flag accepted' : 'Flag already submitted',
            'data' => [
                'code' => $code,
                'points' => $details['points'],
                'already_submitted' => !$submission->wasRecentlyCreated,
            ],
        ]);
    }
}
