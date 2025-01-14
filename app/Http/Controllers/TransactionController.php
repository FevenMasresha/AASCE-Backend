<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;

// use App\Models\Log;

class TransactionController extends Controller
{
    public function deposit(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // File validation for receipt
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Get the uploaded file
        $receipt = $request->file('receipt');

        // Initialize Guzzle Client
        $client = new Client([
            'verify' => false, // Disable SSL verification (for local development only)
        ]);

        try {
            // Send POST request to Cloudinary
            $response = $client->post('https://api.cloudinary.com/v1_1/dq9clkway/image/upload', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($receipt->getRealPath(), 'r'), // File contents
                    ],
                    [
                        'name' => 'upload_preset',
                        'contents' => 'receipt', // Use your Cloudinary upload preset name
                    ],
                ],
            ]);

            // Parse the Cloudinary response
            $uploadData = json_decode($response->getBody(), true);
            $uploadedReceipt = $uploadData['secure_url']; // Secure URL of the uploaded file

            // Create a new transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_type' => 'deposit',
                'amount' => $request->amount,
                'reason' => $request->reason ?? null,
                'status' => 'pending', // Mark as pending initially
                'receipt_url' => $uploadedReceipt,
            ]);

            return response()->json([
                'message' => 'Transaction request created',
                'transaction' => $transaction->fresh() // Ensure we fetch the latest data, including `receipt_url`
            ], 201);
                    } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload receipt', 'error' => $e->getMessage()], 500);
        }
    }

    public function withdraw(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Get authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $customer = $user->customer;

        if ($request->amount > $customer->saving_balance) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        // Create withdrawal transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'withdrawal',
            'amount' => -$request->amount, // Negative to indicate withdrawal
            'reason' => $request->reason,
            'status' => 'pending', // Pending approval
        ]);

        return response()->json([
            'message' => 'Withdrawal request submitted',
            'transaction' => $transaction,
        ], 201);
    }

    public function loanApply(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Get authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $customer = $user->customer;

        // Ensure the customer exists
        if (!$customer) {
            return response()->json(['message' => 'Customer record not found'], 404);
        }
        if ($customer->loan_balance < 0) {
            return response()->json(['message' => 'You must fully repay your current loan before applying for another'], 403);
        }    

         // Business Rule (BR5): Must be an employee of the five federal government bureaus
        $validBureaus = ['trade_bureau', 'finance_bureau', 'environmental_protection_authority', 'gov_property_administration_authority', 'public_procurement_property_disposal_service']; 

        if (!in_array($customer->gov_bureau, $validBureaus)) {
            return response()->json(['message' => 'You must be an employee of a valid federal government bureau'], 403);
        }

        $firstDeposit = Transaction::where('user_id', $user->id)
            ->where('transaction_type', 'deposit')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$firstDeposit || now()->diffInMonths($firstDeposit->created_at) < 6) {
            return response()->json(['message' => 'You must save for at least 6 months before applying for a loan'], 403);
        }

        $maxLoanAmount = $customer->salary * 0.12;
        if ($request->amount > $maxLoanAmount) {
            return response()->json(['message' => "You can only request up to 12% of your salary ({$maxLoanAmount} birr)"], 403);
        }

        if ($request->amount > 200000) {
            return response()->json(['message' => 'The maximum loan amount is 200,000 birr'], 403);
        }
    
        // Create loan transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'loan',
            'amount' => -$request->amount, // Negative to indicate withdrawal
            'reason' => $request->reason,
            'status' => 'pending', // Pending approval
        ]);

        return response()->json([
            'message' => 'loan request submitted',
            'transaction' => $transaction,
        ], 201);
    }

    public function loanRepay(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            // 'receipt' => 'required|file|', // Validate receipt file
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        // Get authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
        // Check if the user has an active loan balance
        if ($user->customer->loan_balance >= 0) {
            return response()->json(['message' => 'There is no active loan to repay'], 400);
        }
        if ($user->customer->loan_balance + $request->amount > 0) {
            return response()->json(['message' => 'you requestd to pay more than your current loan balance '], 400);
        }
        

        $receipt = $request->file('receipt');

        // Initialize Guzzle Client
        $client = new Client([
            'verify' => false, // Disable SSL verification (for local development only)
        ]);

        try {
            // Upload the receipt to Cloudinary
            $response = $client->post('https://api.cloudinary.com/v1_1/dq9clkway/image/upload', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($receipt->getRealPath(), 'r'), // File contents
                    ],
                    [
                        'name' => 'upload_preset',
                        'contents' => 'receipt', // Use your Cloudinary upload preset name
                    ],
                ],
            ]);

            // Parse the Cloudinary response
            $uploadData = json_decode($response->getBody(), true);
            $uploadedReceipt = $uploadData['secure_url']; // Secure URL of the uploaded file

            // Create loan transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_type' => 'loan repayment',
                'amount' => $request->amount, 
                'status' => 'pending', 
                'receipt_url' => $uploadedReceipt, 
            ]);

            return response()->json([
                'message' => 'Loan request submitted',
                'transaction' => $transaction,
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload receipt', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $user = Auth::user();
    
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    
        // Start building the query
        $query = Transaction::with('user.customer')
            ->orderBy('created_at', 'desc');
    
        // Apply filters if they exist
       
        if ($request->has('transaction_type')) {
            $query->where('transaction_type', $request->input('transaction_type'));
        }
         if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
    
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
    
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%$search%")
                ->orWhere('receipt_url', 'like', "%$search%")
                ->orWhere('user_id', 'like', "%$search%")
                ->orWhereHas('user.customer', function ($q) use ($search) {
                        $q->where('fname', 'like', "%$search%")
                        ->orWhere('lname', 'like', "%$search%");
                    });
            });
        }
    
        if ($request->has('amount_min') || $request->has('amount_max')) {
            // Get the raw input values for amount_min and amount_max
            $amountMin = abs($request->input('amount_min', 0)); // Default to 0 if not provided
            $amountMax = $request->input('amount_max', PHP_INT_MAX); // Default to max value if not provided
    
            // Apply the absolute value filter to the amount column
            $query->whereRaw('ABS(amount) BETWEEN ? AND ?', [$amountMin, $amountMax]);
        }

        // Pagination
        $perPage = $request->input('per_page', 10); // Default to 10 items per page
        $page = $request->input('page', 1); // Default to page 1
    
        $transactions = $query->paginate($perPage, ['*'], 'page', $page);
    
        return response()->json($transactions);
    }
    
    public function processTransaction(Request $request, $transactionId)
    {
        Log::info('Processing transaction', ['transaction_id' => $transactionId]);

        // Get the action (approve/reject) from the request
        $action = strtolower($request->input('action'));

        // Validate the action
        if (!in_array($action, ['approve', 'reject','recommend-rejection', 'recommend-approval'])) {
            return response()->json(['message' => 'Invalid action. Please specify "approve" , "recommend-approval" , "recommend-rejection" or "reject".'], 400);
        }

        try {
            // Fetch the transaction by ID
            $transaction = Transaction::findOrFail($transactionId);

            Log::info('Transaction found', ['transaction' => $transaction]);

            // Fetch the associated user, including the customer relationship
            $user = $transaction->user()->with('customer')->first(); // Eager load the customer

            // Check if the user has an associated customer
            if (!$user->customer) {
                Log::error('Customer record not found', ['user_id' => $user->id]);
                throw new \Exception('Customer record not found.');
            }

            // Process based on the transaction type
            switch ($transaction->transaction_type) {
                case 'deposit':
                    $processedTransaction = $this->processDeposit($transaction, $user, $action);
                    break;
                case 'withdrawal':
                    $processedTransaction = $this->processWithdrawal($transaction, $user, $action);
                    break;
                case 'loan repayment':
                    $processedTransaction = $this->processLoanRepayment($transaction, $user, $action);
                    break;
                case 'loan':
                    $processedTransaction = $this->processLoanRequest($transaction, $user, $action);
                    break;
                default:
                    Log::error('Unsupported transaction type', ['transaction_type' => $transaction->transaction_type]);
                    throw new \InvalidArgumentException('Unsupported transaction type.');
            }

            // Return the processed transaction response
            Log::info('Transaction processed successfully', ['transaction' => $processedTransaction]);
            return response()->json(['transaction' => $processedTransaction], 200);

        } catch (\Exception $e) {
            Log::error('Transaction processing failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    private function processDeposit(Transaction $transaction, User $user, string $action): Transaction
    {
        return DB::transaction(function () use ($transaction, $user, $action) {
            // If the action is not approve, reject the transaction
            if ($action !== 'approve') {
                $transaction->status = 'rejected';
                $transaction->save();
                return $transaction;
            }

            // Approve deposit: Add amount to user's balance
            $customer = $user->customer; 

            // Update the customer's balance
            $customer->saving_balance += $transaction->amount; // Update balance in the 'customers' table
            $customer->save();

            // Update the transaction status
            $transaction->status = 'approved';
            $transaction->save();

            return $transaction;
        });
    }

    private function processWithdrawal(Transaction $transaction, User $user, string $action): Transaction
    {
        return DB::transaction(function () use ($transaction, $user, $action) {
            // If the action is not approve, reject the transaction
            if ($action !== 'approve') {
                $transaction->status = 'rejected';
                $transaction->save();
                return $transaction;
            }

            // Approve withdrawal: Deduct amount from user's balance
            $customer = $user->customer; // Accessing customer details from user

            // Check if the user has sufficient funds
            if ($customer->saving_balance < $transaction->amount) {
                Log::error('Insufficient funds', [
                    'user_id' => $user->id,
                    'transaction_amount' => $transaction->amount,
                    'balance' => $customer->saving_balance
                ]);
                throw new \Exception('Insufficient funds.');
            }

            // Deduct the amount from customer's balance
            $customer->saving_balance += $transaction->amount;
            $customer->save();

            // Update the transaction status
            $transaction->status = 'approved';
            $transaction->save();

            return $transaction;
        });
    }

    private function processLoanRepayment(Transaction $transaction, User $user, string $action): Transaction
    {
        return DB::transaction(function () use ($transaction, $user, $action) {
            // If the action is not approve, reject the transaction
            if ($action !== 'approve') {
                $transaction->status = 'rejected';
                $transaction->save();
                return $transaction;
            }

            // Approve loan repayment: Reduce the loan balance
            $customer = $user->customer; // Accessing customer details from user

            // Reduce the loan balance
            $customer->loan_balance += $transaction->amount;
            $customer->save();

            // Update the transaction status
            $transaction->status = 'approved';
            $transaction->save();

            return $transaction;
        });
    }
   
    private function processLoanRequest(Transaction $transaction, $user, $action)
    {
        Log::info('Processing loan request', ['transaction_id' => $transaction->id, 'action' => $action]);
    
        switch ($action) {
            case 'approve':
                $transaction->status = 'approved';
                $user->customer->loan_balance += $transaction->amount;
                $user->customer->saving_balance -= $transaction->amount;
                $user->customer->save();
                Log::info('Loan approved and balance updated', ['user_id' => $user->id, 'new_balance' => $user->customer->loan_balance]);
                break;
    
            case 'reject':
                $transaction->status = 'rejected';
                Log::info('Loan request rejected', ['transaction_id' => $transaction->id]);
                break;
    
            case 'recommend-approval':
                $transaction->status = 'recommended approval';
                Log::info('Loan recommended for approval', ['transaction_id' => $transaction->id]);
                break;
    
            case 'recommend-rejection':
                $transaction->status = 'recommended rejection';
                Log::info('Loan recommended for rejection', ['transaction_id' => $transaction->id]);
                break;
    
            default:
                Log::error('Invalid action provided', ['action' => $action]);
                throw new InvalidArgumentException('Invalid action');
        }
    
        // Save the updated transaction
        $transaction->save();
    
        return $transaction;
    }
    
}