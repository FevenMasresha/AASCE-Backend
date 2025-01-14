<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;

class EmployeeController extends Controller
{
 
    public function index(Request $request)
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    
        // Start building the query for Employee
        $query = Employee::orderBy('created_at', 'desc');         
        if ($request->has('department')) {
            $query->where('department', $request->input('department') );
        }
        if ($request->has('role')) {
            $query->where('role', $request->input('role') );
        }

        // Apply search (search across multiple columns)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like',"%$search%")
                  ->orWhere('user_id', 'like', "%$search%");
            });
        }   
        // Pagination
        $perPage = $request->input('per_page', 10); 
        $page = $request->input('page', 1); 
        $employee = $query->paginate($perPage, ['*'], 'page', $page);    
        return response()->json($employee);
    }
  
    public function store(Request $request)
    {
        try{
            $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,username',
            'phone' => 'required|numeric|digits:10|unique:employees,phone',
            'department' => 'required|string|max:255',
            'role' => 'required|string|max:255',
        ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return validation errors as a JSON response
            return response()->json([
                'message' => 'Validation error occurred.',
                'errors' => $e->errors(),
            ], 422); // 422 Unprocessable Entity
        }
        try {
            // Step 2: Create the User associated with the Employee
            $user = User::create([
                'username' => $validated['email'],
                'password' => bcrypt('password123'),
                'role' => $validated['role'],
            ]);
    
            // Step 3: Create the Employee and associate it with the User
            $employee = Employee::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => bcrypt('password123'),
                'department' => $validated['department'],
                'role' => $validated['role'],
                'user_id' => $user->id,
            ]);
    
            return response()->json([
                'message' => 'Employee created successfully',
                'employee' => $employee
            ], 201);
    
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database-related errors (e.g., duplicate entries)
            return response()->json([
                'message' => 'Database error occurred.',
                'error' => $e->getMessage(),
            ], 500); // Internal Server Error
        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function show($id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        return response()->json($employee);
    }


    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
    
        // Start by validating the inputs
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,username,' . $employee->user->id,  
            'phone' => 'required|numeric|digits:10|unique:employees,phone,' . $id, 
            'department' => 'required|string|max:255',
            'role' => 'required|string|max:255',
        ]);
    
        try {
            // Update the Employee
            $employee->update($request->only(['name', 'email', 'phone', 'department', 'role']));
    
            // Update the associated User's role
            if ($employee->user) {
                $employee->user->update(['role' => $request->role]);
                $employee->user->update(['username' => $request->email]);
            }
    
            return response()->json(['message' => 'Employee updated successfully', 'employee' => $employee]);
        } catch (QueryException $e) {
            if ($e->getCode() == 23000) {  // SQLSTATE code for integrity constraint violation
                if (str_contains($e->getMessage(), 'users_username_unique')) {
                    return response()->json(['error' => 'The email address is already in use. Please choose another one.'], 422);
                } elseif (str_contains($e->getMessage(), 'employees_phone_unique')) {
                    return response()->json(['error' => 'The phone number is already associated with another employee.'], 422);
                }
            }
    
            // If it's a different type of error, return a general error
            return response()->json(['error' => 'Failed to update employee due to a database error. Please try again.'], 500);
            }
    }
    

    public function destroy($id)
    {
        try {
            // Find the employee by ID
            $employee = Employee::findOrFail($id);
            
            $user = $employee->user; 
    
            if ($user) {
                $user->delete();
            }
    
            $employee->delete();
    
            return response()->json(['message' => 'Employee and associated user deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error deleting employee: ' . $e->getMessage()], 500);
        }
    }
}
