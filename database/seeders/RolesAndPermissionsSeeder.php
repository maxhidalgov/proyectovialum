<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 🔐 CREAR PERMISOS
        $permissions = [
            ['nombre' => 'gestionar_usuarios', 'descripcion' => 'Crear, editar y eliminar usuarios'],
            ['nombre' => 'gestionar_roles', 'descripcion' => 'Asignar roles y permisos'],
            ['nombre' => 'gestionar_productos', 'descripcion' => 'Crear, editar y eliminar productos'],
            ['nombre' => 'ver_productos', 'descripcion' => 'Ver listado de productos'],
            ['nombre' => 'gestionar_cotizaciones', 'descripcion' => 'Crear, editar y eliminar cotizaciones'],
            ['nombre' => 'ver_cotizaciones', 'descripcion' => 'Ver listado de cotizaciones'],
            ['nombre' => 'aprobar_cotizaciones', 'descripcion' => 'Aprobar o rechazar cotizaciones'],
            ['nombre' => 'gestionar_clientes', 'descripcion' => 'Crear, editar y eliminar clientes'],
            ['nombre' => 'ver_clientes', 'descripcion' => 'Ver listado de clientes'],
            ['nombre' => 'ver_dashboard', 'descripcion' => 'Acceso al dashboard y estadísticas'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['nombre' => $perm['nombre']],
                ['descripcion' => $perm['descripcion']]
            );
        }

        // 👑 CREAR ROLES
        $adminRole = Role::firstOrCreate(['nombre' => 'Admin']);
        $vendedorRole = Role::firstOrCreate(['nombre' => 'Vendedor']);
        $practicanteRole = Role::firstOrCreate(['nombre' => 'Practicante']);

        // 🔗 ASIGNAR PERMISOS A ROLES

        // Admin tiene TODOS los permisos
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        // Vendedor: puede gestionar cotizaciones y clientes, ver productos
        $vendedorRole->permissions()->sync(
            Permission::whereIn('nombre', [
                'gestionar_cotizaciones',
                'ver_cotizaciones',
                'aprobar_cotizaciones',
                'gestionar_clientes',
                'ver_clientes',
                'ver_productos',
                'ver_dashboard',
            ])->pluck('id')
        );

        // Practicante: solo puede gestionar productos (crear y editar)
        $practicanteRole->permissions()->sync(
            Permission::whereIn('nombre', [
                'gestionar_productos',
                'ver_productos',
            ])->pluck('id')
        );

        // 👤 CREAR USUARIO ADMIN POR DEFECTO (si no existe)
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@vialum.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'), // ⚠️ CAMBIAR EN PRODUCCIÓN
                'role_id' => $adminRole->id,
            ]
        );

        $this->command->info('✅ Roles y permisos creados correctamente');
        $this->command->info("👤 Usuario Admin: admin@vialum.com / admin123");
    }
}
