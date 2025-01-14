<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    public function up()
    {
        // Migration for customers table
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('account_no');
            $table->string('fname');
            $table->string('lname');
            $table->integer('age');
            $table->enum('sex', ['male', 'female', 'other']);
            $table->string('email')->unique();
            $table->string('password');
            $table->decimal('saving_balance', 10, 2)->default(0);
            $table->decimal('loan_balance', 10, 2)->default(0);
            $table->decimal('salary', 10, 2)->nullable()->default(0);  
            $table->enum('gov_bureau', ['trade_bureau', 'finance_bureau', 'environmental_protection_authority', 'gov_property_administration_authority', 'public_procurement_property_disposal_service'])->nullable();  
            $table->string('status')->default('active');
            $table->date('last_interest_calculation')->nullable();

            $table->timestamps();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

        });
    }

    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
