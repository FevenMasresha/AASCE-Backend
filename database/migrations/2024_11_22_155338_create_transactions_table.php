<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('transaction_type', ['loan', 'withdrawal', 'deposit', 'loan repayment']);
            $table->decimal('amount', 15, 2); 
            $table->text('reason')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default('pending'); 
            $table->string('receipt_url')->nullable(); 
            $table->timestamps();

            // Foreign key constraint
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
