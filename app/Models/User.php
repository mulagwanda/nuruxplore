<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'credits_balance',
        'subscription_plan',
        'subscription_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'credits_balance' => 'integer',
        'subscription_expires_at' => 'datetime',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(NuruxploreProject::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(NuruxploreCreditTransaction::class);
    }

    public function deductCredits(int $amount, string $reason, ?int $projectId = null): bool
    {
        if ($this->credits_balance < $amount) {
            return false;
        }

        $this->decrement('credits_balance', $amount);
        
        $this->creditTransactions()->create([
            'amount' => -$amount,
            'reason' => $reason,
            'project_id' => $projectId,
        ]);

        return true;
    }

    public function addCredits(int $amount, string $reason): void
    {
        $this->increment('credits_balance', $amount);
        
        $this->creditTransactions()->create([
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }
}