<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Services\InterestCalculatorService;

class CalculateSavingInterest extends Command
{
    protected $signature = 'interest:calculate';
    protected $description = 'Calculate annual interest for all customers\' saving balances';

    public function handle()
    {
        $customers = Customer::all();

        foreach ($customers as $customer) {
            $lastCalculatedDate = $customer->last_interest_calculation;
            $interest = InterestCalculatorService::calculateAnnualInterest(
                $customer->saving_balance,
                0.12, // 12% annual interest rate
                $lastCalculatedDate
            );

            if ($interest > 0) {
                $customer->saving_balance += $interest;
                $customer->last_interest_calculation = now();
                $customer->save();

                $this->info("Added interest of {$interest} to customer ID {$customer->id}");
            }
        }

        $this->info('Interest calculation completed.');
    }
}
