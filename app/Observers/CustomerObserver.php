<?php
namespace App\Observers;

use App\Models\Customer;
use App\Services\InterestCalculatorService; // Add this import
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    protected $interestCalculatorService;

    // Inject the service into the constructor
    public function __construct(InterestCalculatorService $interestCalculatorService)
    {
        $this->interestCalculatorService = $interestCalculatorService;
    }

    /**
     * Handle the Customer "updated" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function updated(Customer $customer)
    {
        // Check if the saving_balance has changed
        if ($customer->isDirty('saving_balance')) {
            // If saving_balance has been updated, calculate the interest
            $this->interestCalculatorService->calculateSavingsInterest($customer->id);

            Log::info('Interest calculated for customer with ID: ' . $customer->id);
        }
    }
}
