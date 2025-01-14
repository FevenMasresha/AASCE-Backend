<?php
namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    // Fetch meetings for the authenticated user
    public function index()
    {
        // Ensure the user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $role = $user->role; // Assuming role is available in the User model
    
            if ($role === 'manager') {
                // Manager sees all meetings
                $meetings = Meeting::all();
            } else {
                // Admin, Accountant, Committee, and Customer should see meetings where their role is in the attendees field
                $meetings = Meeting::where('attendees', 'like', "%{$role}%")->get();
            }
    
            return response()->json($meetings, 200);
        }
    
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    

    // Add a new meeting
    public function store(Request $request)
    {
        // Ensure the user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Validate the request input
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'location' => 'required|string|max:255',
            'attendees' => 'nullable|string',
            'agenda' => 'nullable|string',
        ]);

        // Create the new meeting
        $meeting = Meeting::create([
            'user_id' => Auth::id(), // Link the meeting to the authenticated user
            ...$validated
        ]);

        return response()->json(['message' => 'Meeting added successfully', 'meeting' => $meeting], 201);
    }
}
