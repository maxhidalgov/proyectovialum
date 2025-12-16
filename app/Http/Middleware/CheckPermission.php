<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        if (!$user->role) {
            return response()->json(['error' => 'Usuario sin rol asignado'], 403);
        }

        if (!$user->role->hasPermission($permission)) {
            return response()->json([
                'error' => 'No tienes permiso para realizar esta acción',
                'permiso_requerido' => $permission
            ], 403);
        }

        return $next($request);
    }
}
