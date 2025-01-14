<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Customer;
use App\Observers\CustomerObserver;
use App\Services\InterestCalculatorService;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Bind InterestCalculatorService to the container
        $this->app->singleton(InterestCalculatorService::class, function ($app) {
            return new InterestCalculatorService();
        });
    }

    public function boot()
    {
        // Register the CustomerObserver to listen to customer updates
        Customer::observe(CustomerObserver::class);
    }
}
