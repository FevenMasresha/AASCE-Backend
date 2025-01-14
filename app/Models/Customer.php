<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Define the fillable attributes to prevent mass-assignment vulnerabilities
    protected $fillable = [
        'phone',
        'account_no',
        'fname',
        'lname',
        'age',
        'sex',
        'email',
        'password',
        'saving_balance',
        'loan_balance',
        'salary', 
        'gov_bureau',
        'user_id',

    ];

    protected $hidden = [
        'password',
    ];

    // Automatically hash the password before saving it
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
}
