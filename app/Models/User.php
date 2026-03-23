<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable {
    use Notifiable;
    protected $fillable = [
        'telegram_id', 'balance', 'ichancey_username', 'ichancey_password', 
        'referrer_id', 'referral_code', 'step', 'pending_details', 'pending_amount'
    ];
}
