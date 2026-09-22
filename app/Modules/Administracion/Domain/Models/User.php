<?php 

namespace App\Modules\Administracion\Domain\Models; 

// use Illuminate\Contracts\Auth\MustVerifyEmail; 
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Illuminate\Notifications\Notifiable; 

class User extends Authenticatable 
{ 
    use Notifiable; 

    protected $table = 'usuarios';
    public function getEmailAttribute()
    {
    return $this->correo;
    }
    
    /** 
     * The attributes that are mass assignable. 
     * 
     * @var list<string> 
     */ 
    protected $fillable = [
       'nombre',
       'correo',
       'password',
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
            'is_active' => 'boolean', 
            'failed_login_attempts' => 'integer', 
            'locked_until' => 'datetime', 
            'last_login_at' => 'datetime', 
        ]; 
    } 
}