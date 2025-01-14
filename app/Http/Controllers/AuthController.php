<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\Log;  // Import the Log model

class AuthController extends Controller
{
    // Sign up method
    public function signUp(Request $request)
    {       
        // Validate request
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'password' => 'nullable|string|min:8',  // Password is nullable now
            'role' => 'nullable|string|in:admin,manager,accountant,loan-committee,customer',
        ]);
    
        try {
            $password = $request->password ?? 'password123'; // Default password
            $hashedPassword = Hash::make($password);  // Hash the password
    
            // Create the user
            $user = User::create([
                'username' => $request->username,
                'password' => $hashedPassword, // Use the hashed password
                'role' => $request->role ?? 'customer',  // Default role is 'customer'
            ]);
    
            // Log the creation action
            Log::create([
                'user_id' => $user->id, 
                'action' => 'User Created',  
                'description' => 'A new user was successfully created with username: ' . $user->username,
            ]);
    
            return response()->json([
                'message' => 'User registered successfully.',
                'user' => $user,
                'default_password' => $password  // Optionally return the default password (for admin purposes)
            ], 201);
    
        } catch (\Exception $e) {
            // Handle unique constraint violation (duplicate username)
            if ($e->getCode() == 23000) {  
                return response()->json([
                    'message' => 'The username is already taken. Please choose a different one.'
                ], 400);
            }
            
            // Generic error message with a more user-friendly response
            return response()->json([
                'message' => 'An error occurred while creating the user. Please try again later.'
            ], 500);
        }
    }
    

    // Sign in method
    public function signIn(Request $request)
    {
        // Validate request
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
    
        try {
            // Check if user exists by username
            $user = User::where('username', $request->username)->first();
        
            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::create([
                    'user_id' => $user->id, 
                    'action' => 'login Attempt',  
                    'description' => 'Unsuccesfull login attempt username: ' . $user->username, 
                ]);
                return response()->json(['message' => 'Invalid credentials. Please check your username and password and try again.'], 401);
                
            }
        
            // Generate an API token for the authenticated user
            $token = $user->createToken('YourAppName')->plainTextToken;
    
            return response()->json([
                'message' => 'Login successful.',
                'user' => $user,
                'token' => $token, // Send the token to the frontend
            ], 200);
        } catch (\Exception $e) {
            
            return response()->json(['message' => 'An error occurred. Please try again later.'], 500);
        }
    }
    
   // Change password method
   public function changePassword(Request $request)
   {
       try {
           // Validate input
           $request->validate([
               'current_password' => 'required|string',
               'new_password' => 'required|string|min:6|confirmed',
           ]);
       
           $user = Auth::user();
       
           // Check if current password matches the stored password
           if (!Hash::check($request->current_password, $user->password)) {
               return response()->json([
                   'message' => 'The current password you entered is incorrect. Please try again.'
               ], 400); // Bad Request if current password doesn't match
           }
       
           // Update password
           $user->password = Hash::make($request->new_password);
           $user->save();
       
           return response()->json([
               'message' => 'Your password has been changed successfully.'
           ], 200); // Success response
       } catch (\Exception $e) {
           return response()->json([
               'message' => 'There was an error changing your password. Please try again later.'
           ], 500); // General error
       }
   }
     
    // Sign out method
    public function signOut(Request $request)
    {
       
        return response()->json(['message' => 'Logout successful']);
    }
    
}
