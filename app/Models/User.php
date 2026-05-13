<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;
    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['password' => 'hashed', 'email_verified_at' => 'datetime', 'last_login_at' => 'datetime', 'is_super_admin' => 'boolean'];
    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_users')->withPivot(['role_id', 'status', 'invited_by', 'joined_at'])->withTimestamps();
    }
    public function ownedBusinesses()
    {
        return $this->hasMany(Business::class, 'owner_id');
    }
}
