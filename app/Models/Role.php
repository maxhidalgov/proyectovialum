<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'nombre',
    ];

    /**
     * Permisos asociados a este rol
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Verificar si el rol tiene un permiso específico
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('nombre', $permissionName)->exists();
    }

    /**
     * Usuarios con este rol
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
