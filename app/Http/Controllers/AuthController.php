<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'error' => 'Credenciales incorrectas',
                'message' => 'El email o la contraseña son incorrectos'
            ], 401);
        }

        $user = Auth::guard('api')->user();
        
        // Obtener rol y permisos de forma segura
        $role = null;
        $permissions = [];
        
        if ($user->role_id) {
            $role = \App\Models\Role::find($user->role_id);
            if ($role) {
                $permissions = $role->permissions()->pluck('nombre')->toArray();
            }
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role ? $role->nombre : null,
                'role_id' => $user->role_id,
                'permissions' => $permissions,
            ]
        ]);

    }

    public function me()
    {
        return response()->json([
            'user' => Auth::guard('api')->user(),
        ]);
    }

    /**
     * Renueva el token de sesión. Funciona con un token vencido siempre que esté
     * dentro del refresh_ttl (14 días). La ruta NO usa auth:api porque ese
     * middleware rechazaría el token vencido antes de llegar aquí; el token se lee
     * del header Authorization que envía el front.
     */
    public function refresh()
    {
        try {
            $newToken = Auth::guard('api')->refresh();

            return response()->json(['token' => $newToken]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo refrescar la sesión'], 401);
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed', // Debe venir password_confirmation también
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

}
