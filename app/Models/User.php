<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
   
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
    protected $fillable = [
        'username',
        'password',
        'role',
        'profile_picture',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
