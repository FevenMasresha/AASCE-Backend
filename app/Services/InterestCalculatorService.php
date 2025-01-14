<?php

namespace App\Services;

use App\Models\Customer;
use Carbon\Carbon;

class InterestCalculatorService
{
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
            $interestRate = 0.12;

            // Calculate the interest based on the years
            $interest = $customer->saving_balance * $interestRate * $accountAgeInYears;

            // Add the interest to the saving balance
            $customer->saving_balance += $interest;

            // Update the last interest calculation date to the current date
            $customer->last_interest_calculation = Carbon::now();

            // Save the updated customer data
            $customer->save();

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
