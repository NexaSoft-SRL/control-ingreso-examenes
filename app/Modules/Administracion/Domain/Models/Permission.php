<?php

namespace App\Modules\Administracion\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'screen_name'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}