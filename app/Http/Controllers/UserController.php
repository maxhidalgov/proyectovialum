<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios (solo admin)
     */
    public function index()
    {
        $users = User::with('role')->get();
        
        return response()->json([
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'nombre' => $user->role->nombre,
                ] : null,
                'created_at' => $user->created_at,
            ])
        ]);
    }

    /**
     * Crear usuario (solo admin)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->nombre ?? null,
            ]
        ], 201);
    }

    /**
     * Actualizar usuario (solo admin)
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:6',
            'role_id' => 'sometimes|exists:roles,id',
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->has('role_id')) {
            $user->role_id = $request->role_id;
        }

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->nombre ?? null,
            ]
        ]);
    }

    /**
     * Eliminar usuario (solo admin)
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Evitar que el admin se elimine a sí mismo
        if ($user->id === Auth::guard('api')->id()) {
            return response()->json([
                'error' => 'No puedes eliminar tu propio usuario'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Obtener listado de roles disponibles
     */
    public function getRoles()
    {
        $roles = Role::all(['id', 'nombre']);
        
        return response()->json(['roles' => $roles]);
    }

    /**
     * Obtener todos los permisos disponibles
     */
    public function getPermissions()
    {
        $permissions = \App\Models\Permission::all(['id', 'nombre', 'descripcion']);
        
        return response()->json(['permissions' => $permissions]);
    }

    /**
     * Obtener permisos de un rol específico
     */
    public function getRolePermissions($roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        
        return response()->json([
            'role' => [
                'id' => $role->id,
                'nombre' => $role->nombre,
            ],
            'permissions' => $role->permissions->pluck('id')->toArray()
        ]);
    }

    /**
     * Actualizar permisos de un rol
     */
    public function updateRolePermissions(Request $request, $roleId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($roleId);
        $role->permissions()->sync($request->permissions);

        return response()->json([
            'message' => 'Permisos actualizados exitosamente',
            'role' => $role->nombre,
            'permissions_count' => count($request->permissions)
        ]);
    }
}
