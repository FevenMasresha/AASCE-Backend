<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Customer; // Assuming the Customer model exists
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Carbon\Carbon;

class CustomerController extends Controller
{
    public function registerCustomer(Request $request)
    {
        // Step 1: Validate the customer request
        try {
            $validated = $request->validate([
                'phone' => 'required|numeric|digits:10|unique:customers,phone',
                'account_no' => 'required|numeric|digits_between:10,18|unique:customers,account_no',
                'fname' => 'required|string',
                'lname' => 'required|string',
                'age' => 'required|integer|min:25|max:100',
                'sex' => 'required|in:male,female',
                'email' => 'required|email|unique:users,username',
                'salary' => 'nullable|numeric|min:500|max:1000000',
                'gov_bureau' => 'nullable|in:trade_bureau,finance_bureau,environmental_protection_authority,gov_property_administration_authority,public_procurement_property_disposal_service',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return validation errors as a JSON response
            return response()->json([
                'message' => 'Validation error occurred.',
                'errors' => $e->errors(),
            ], 422); // 422 Unprocessable Entity
        }
    
        try {
            // Step 2: Create the User
            $user = User::create([
                'username' => $validated['email'],
                'password' => bcrypt('password123'), 
            ]);
            // Step 3: Register the Customer and link to the User
            $customer = Customer::create([
                'phone' => $validated['phone'],
                'account_no' => $validated['account_no'],
                'fname' => $validated['fname'],
                'lname' => $validated['lname'],
                'age' => $validated['age'],
                'sex' => $validated['sex'],
                'email' => $validated['email'],
                'password' => bcrypt('password123'), 
                'saving_balance' => 0,
                'loan_balance' => 0,
                'salary' => $validated['salary'], 
                'gov_bureau' => $validated['gov_bureau'], 
                'user_id' => $user->id,
            ]);
    
            return response()->json([
                'message' => 'Customer and user registered successfully.',
                'customer' => $customer,
                'user' => $user,
            ], 201); // 201 Created
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database errors
            return response()->json([
                'message' => 'A database error occurred. Please check the input and try again.',
                'error' => 'Database constraint violation or duplicate entry.',
            ], 400); // 400 Bad Request
        } catch (\Exception $e) {
            // Handle unexpected errors
            Log::error('Registration Error:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500); // 500 Internal Server Error
        }
    }
    
    
    public function getCustomerData(Request $request)
    {
        try {
            // Fetch the authenticated user's customer data (assuming `auth` middleware is used)
            $user = $request->user();
            
            // Assuming you have a relationship between users and customers
            $customer = Customer::where('user_id', $user->id)->first();

            if (!$customer) {
                return response()->json(['error' => 'Customer not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $customer,
                'saving_balance' => $customer->saving_balance,
                'loan_balance' => $customer->loan_balance,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function index(Request $request)
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    
        // Start building the query for customers
        $query = Customer::orderBy('created_at', 'desc');   
     
        // Apply gov_bureau filter
        
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('gov_bureau')) {
            $query->where('gov_bureau', $request->input('gov_bureau'));
        }
        if ($request->has('sex')) {
            $query->where('sex', $request->input('sex') );
        }
        if ($request->has('age')) {
            $query->where('age', $request->input('age') );
        }
        if ($request->has('salary_min') || $request->has('salary_max')) {
            $salaryMin = $request->input('salary_min', 0); // Default to 0 if not provided
            $salaryMax = $request->input('salary_max', PHP_INT_MAX); // Default to max value if not provided
            $query->whereBetween('salary', [$salaryMin, $salaryMax]);
        }

        // Apply search (search across multiple columns)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('fname', 'like', "%$search%")
                  ->orWhere('lname', 'like', "%$search%")
                  ->orWhere('phone', 'like',"%$search%")
                  ->orWhere('account_no', 'like', "%$search%")
                  ->orWhere('salary', 'like', "%$search%")
                  ->orWhere('age', 'like', "%$search%")
                  ->orWhere('gov_bureau', 'like', "%$search%")
                  ->orWhere('salary', 'like', "%$search%")
                  ->orWhere('salary', 'like', "%$search%");

            });
        }
     
    
        // Pagination
        $perPage = $request->input('per_page', 6); // Default to 10 items per page
        $page = $request->input('page', 1); // Default to page 1
    
        // Get paginated results
        $customers = $query->paginate($perPage, ['*'], 'page', $page);
    
        return response()->json($customers);
    }
    
    
    public function update(Request $request, $id)
    {
        // Validate incoming data
        $validated = $request->validate([
            'phone' => 'required|numeric|digits:10|unique:customers,phone',
            'password' => 'required|min:8',
            'account_no' => 'required|numeric|digits_between:10,18|unique:customers,account_no',
            'fname' => 'required|string',
            'lname' => 'required|string',
            'age' => 'required|integer|min:25|max:100',
            'sex' => 'required|in:male,female',
            'email' => 'required|email|unique:customers,email',
            'salary' => 'required|numeric|min:500|max:1000000',
            'gov_bureau' => 'required|in:trade_bureau,finance_bureau,environmental_protection_authority,gov_property_administration_authority,public_procurement_property_disposal_service',
        ]);
        try
        {
            // Find the customer by ID
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json(['error' => 'Customer not found'], 404);
            }

            // Update customer details
            $customer->update($validated);

            return response()->json(['message' => 'Customer updated successfully', 'customer' => $customer], 200);
         }catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function delete($id)
    {
        // Find the customer by ID
        $customer = Customer::find($id);
    
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }
    
        // Delete the associated user if it exists
        if ($customer->user) {
            $customer->user->delete();  // Deletes the associated user
        }
        
        if ($customer->exists) {
            // Delete the customer only if it still exists (it wasn't deleted by the cascading delete)
            $customer->delete();
        }
        return response()->json(['message' => 'Customer and associated user deleted successfully'], 200);
    }
    public function calculateSavingsInterest($customerId)
    {
        // Fetch the customer record
        $customer = Customer::find($customerId);
    
        // Check if customer exists
        if (!$customer) {
            return response()->json(['error' => 'Customer not found.'], 404);
        }
    
        // Get the current date
        $currentDate = Carbon::now();
    
        // Check the last interest calculation date
        $lastInterestCalculated = $customer->last_interest_calculation ? Carbon::parse($customer->last_interest_calculation) : null;
    
        // If the last interest calculation was not done this year, calculate interest
        if (!$lastInterestCalculated || $lastInterestCalculated->year < $currentDate->year) {
            // Calculate the time difference in years from account creation or last interest calculation
            $accountAgeInYears = $lastInterestCalculated ? $lastInterestCalculated->diffInYears($currentDate) : $currentDate->diffInYears($customer->created_at);
    
            // Annual interest rate (12%)
            $interestRate = 0.075;
    
            // Calculate the interest based on the years
            $interest = $customer->saving_balance * $interestRate * $accountAgeInYears;
    
            // Add the interest to the saving balance
            $customer->saving_balance += $interest;
    
            // Update the last interest calculation date to the current date
            $customer->last_interest_calculation = Carbon::now();
    
            // Save the updated customer data
            $customer->save();
    
            // Return the updated customer information
            return response()->json([
                'message' => 'Interest calculated successfully based on account age.',
                'customer' => $customer
            ], 200);
        } else {
            // If the interest was already calculated this year
            return response()->json([
                'message' => 'Interest has already been calculated for this year.',
                'customer' => $customer
            ], 200);
        }
    }
       

}