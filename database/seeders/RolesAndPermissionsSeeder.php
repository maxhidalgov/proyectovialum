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
        // 🔐 CREAR PERMISOS POR ÁREA (uno por sección del menú)
        $permissions = [
            ['nombre' => 'area_ventas',     'descripcion' => 'Ventas: Dashboard, Cotizador, Cotizaciones, Venta Express, Facturación, Órdenes de Corte'],
            ['nombre' => 'area_clientes',   'descripcion' => 'Clientes: Clientes y CRM'],
            ['nombre' => 'area_produccion', 'descripcion' => 'Producción: Operaciones, Producción, Órdenes de Compra, Calendario, Winperfil, Asistente IA'],
            ['nombre' => 'area_productos',  'descripcion' => 'Productos: Agregar, Lista de Precios, Importador, Inventario'],
            ['nombre' => 'area_compras',    'descripcion' => 'Compras: Facturas de Compra, Compras Mensuales, Proveedores'],
            ['nombre' => 'area_finanzas',   'descripcion' => 'Finanzas: Conciliación, Boletas, CxC, CxP, Gastos, Transbank, Registros, EERR'],
            ['nombre' => 'area_rrhh',       'descripcion' => 'RR.HH.: Empleados y Asistencia'],
            ['nombre' => 'area_admin',      'descripcion' => 'Administración: Gestión de usuarios, roles y permisos'],
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

        // Admin tiene TODAS las áreas
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        // Vendedor: comercial y producción, sin finanzas/rrhh/admin
        $vendedorRole->permissions()->sync(
            Permission::whereIn('nombre', [
                'area_ventas',
                'area_clientes',
                'area_produccion',
                'area_productos',
                'area_compras',
            ])->pluck('id')
        );

        // Practicante: solo productos
        $practicanteRole->permissions()->sync(
            Permission::whereIn('nombre', [
                'area_productos',
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
