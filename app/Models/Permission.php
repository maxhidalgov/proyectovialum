<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    /**
     * Roles que tienen este permiso
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
