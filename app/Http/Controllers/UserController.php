<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
   
class UserController extends Controller
{
    public function index()
    {
        return User::all(); 
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'username' => 'required|min:3|unique:users,username,' . $id,
            'role' => 'required',
        ]);
    
        $user = User::findOrFail($id);
    
        // Save the original data for logging
        $originalData = $user->toArray();
    
        $user->update([
            'username' => $request->username,
            'role' => $request->role,
            'password' => $request->password
                ? Hash::make($request->password)
                : $user->password,
        ]);
    
        // Create a log entry
        Log::create([
            'user_id' => ' ', // Log the current user's ID
            'action' => 'update',
            'description' => "User ID updated. Original data: " . json_encode($originalData),
        ]);
    
        return response()->json($user, 200);
    }
    
    public function destroy($id)
    {
        $user = User::findOrFail($id);
    
        // Save the user data for logging before deletion
    
        $user->delete();
    
        return response()->json(['message' => 'User deleted successfully'], 200);
    }
    
    public function uploadProfileImage(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png|max:2048', // File validation for profile image
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
    
        // Get the authenticated user
        $user = Auth::user();
    
        // Get the uploaded file
        $image = $request->file('image');
    
        // Initialize Guzzle Client
        $client = new Client([
            'verify' => false, // Disable SSL verification (for local development only)
        ]);
    
        try {
            // Send POST request to Cloudinary for profile image upload
            $response = $client->post('https://api.cloudinary.com/v1_1/dq9clkway/image/upload', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($image->getRealPath(), 'r'), // File contents
                    ],
                    [
                        'name' => 'upload_preset',
                        'contents' => 'receipt', // Use the same Cloudinary upload preset as the receipt upload
                    ],
                ],
            ]);
    
            // Parse the Cloudinary response
            $uploadData = json_decode($response->getBody(), true);
            $uploadedImageUrl = $uploadData['secure_url']; // Secure URL of the uploaded file
    
            // Save the Cloudinary image URL in the database
            $user->profile_picture = $uploadedImageUrl; // Store the URL in the user's profile_picture field
            $user->save();
    
            return response()->json([
                'message' => 'Profile picture updated successfully!',
                'image_url' => $uploadedImageUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload profile image', 'error' => $e->getMessage()], 500);
        }
    }
    

    public function deleteProfileImage()
    {
        $user = auth()->user();

        // If the user has a profile image, delete it from Cloudinary
        if ($user->profile_picture) {
            // $publicId = basename($user->profile_picture, '.' . pathinfo($user->profile_picture, PATHINFO_EXTENSION));
            // Cloudinary::destroy($publicId);
            $user->profile_picture = null;
            $user->save();
        }

        return response()->json(['message' => 'Profile picture deleted successfully!']);
    }

}
