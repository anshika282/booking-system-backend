<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'domain', 'status'];

    // Relationship: One tenant can have many users
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
