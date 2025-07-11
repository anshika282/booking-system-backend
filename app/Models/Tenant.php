<?php

namespace App\Models;

use App\Models\User;
use App\Models\BookableService;
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
    // Relationship: One tenant can have many services
    public function services(): HasMany { 
        return $this->hasMany(BookableService::class); 
    }
}
