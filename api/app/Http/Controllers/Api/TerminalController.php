<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Terminal;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    /**
     * Get all terminals.
     */
    public function index()
    {
        $terminals = Terminal::all();

        return response()->json([
            'success' => true,
            'data' => $terminals,
        ]);
    }

    /**
     * Get a specific terminal.
     */
    public function show($code)
    {
        $terminal = Terminal::where('code', $code)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $terminal,
        ]);
    }
}