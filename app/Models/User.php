<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Tenant;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'role'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationship: A user belongs to one tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // JWT implementation: Returns the identifier for JWT (tenant_id)
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // JWT implementation: Custom claims (you can add more claims here)
    public function getJWTCustomClaims()
    {
        return [
            'tenant_id' => $this->tenant_id,  // Adding tenant_id in the JWT claim
            'role' => $this->role
        ];
    }
}
