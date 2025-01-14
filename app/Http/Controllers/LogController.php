<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Log;

class LogController extends Controller
{
    /**
     * Fetch all logs.
     */
    public function index()
    {
        $logs = Log::with('user')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Store a new log entry.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'action' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $log = Log::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Log entry created successfully.',
            'data' => $log,
        ]);
    }
    public function destroy($id)
    {
        $log = Log::find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log entry not found.',
            ], 404);
        }

        $log->delete();

        return response()->json([
            'success' => true,
            'message' => 'Log entry deleted successfully.',
        ]);
    }

}
