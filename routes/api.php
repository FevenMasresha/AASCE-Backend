<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\FeedbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\BackupController;


// Authentication routes
Route::post('/signup', [AuthController::class, 'signUp']);
Route::post('/login', [AuthController::class, 'signIn']);
Route::post('/logout', [AuthController::class, 'signOut']);

//user
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/profile', [UserController::class, 'uploadProfileImage']);
    Route::delete('/profile', [UserController::class, 'deleteProfileImage']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user', fn (Request $request) => $request->user());
});


// Customer routes
Route::middleware('auth:sanctum')->post('/register-customer', [CustomerController::class, 'registerCustomer']);
Route::middleware('auth:sanctum')->get('/customerdata', [CustomerController::class, 'getCustomerData']);

// Transaction routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/deposit', [TransactionController::class, 'deposit']);
    Route::post('/withdraw', [TransactionController::class, 'withdraw']);
    Route::post('/loan', [TransactionController::class, 'loanRepay']);
    Route::post('/apply-loan', [TransactionController::class, 'loanApply']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/users-transactions', [TransactionController::class, 'allTransactions']);
    Route::post('/transactions/{id}/process', [TransactionController::class, 'processTransaction']);
    
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'delete']);
    Route::post('/calculate-interest/{customerId}', [CustomerController::class, 'calculateSavingsInterest']);

});


// Feedback route
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/feedback', [FeedbackController::class, 'store']);
    Route::get('feedbacks', [FeedbackController::class, 'index']);
    Route::put('feedback/{id}/respond', [FeedbackController::class, 'respond']);
});


// user route
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index']); 
    Route::put('/users/{id}', [UserController::class, 'update']); 
    Route::delete('/users/{id}', [UserController::class, 'destroy']); 
});

//log routs
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/logs', [LogController::class, 'index']);
    Route::post('/logs', [LogController::class, 'store']);
    Route::delete('logs/{id}', [LogController::class, 'destroy']);

});

//meeting routs
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/meetings', [MeetingController::class, 'index']);  
    Route::post('/meetings', [MeetingController::class, 'store']); 
});


//employee 
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});

