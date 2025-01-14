<?php
namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $feedback = new Feedback();
        $feedback->user_id = Auth::id();
        $feedback->message = $request->message;
        $feedback->save();

        return response()->json(['message' => 'Feedback submitted successfully'], 201);
    }

    public function index()
    {
        $feedbacks = Feedback::all();
        return response()->json(['feedbacks' => $feedbacks], 200);
    }
    
    public function respond(Request $request, $id)
    {
        $request->validate([
            'response' => 'required|string|max:1000',
        ]);

        $feedback = Feedback::find($id);

        if (!$feedback) {
            return response()->json(['error' => 'Feedback not found'], 404);
        }

        $feedback->response = $request->response;
        $feedback->save();

        return response()->json(['message' => 'Response submitted successfully'], 200);
    }
}
