<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reemplaza los permisos "gruesos" (gestionar_usuarios, gestionar_cotizaciones,
 * ver_dashboard, etc.) por un permiso por ÁREA del menú, para que en el panel de
 * roles se pueda controlar el acceso módulo por módulo (área por área).
 *
 * Preserva el acceso actual: a cada rol le asigna las áreas equivalentes a los
 * permisos legacy que ya tenía, ANTES de borrar los legacy.
 *
 * Idempotente: firstOrCreate de permisos + syncWithoutDetaching (no duplica) +
 * delete de legacy sólo si existen.
 */
return new class extends Migration
{
    /** Áreas y su descripción (nombre => descripción) */
    private array $areas = [
        'area_ventas'     => 'Ventas: Dashboard, Cotizador, Cotizaciones, Venta Express, Facturación, Órdenes de Corte',
        'area_clientes'   => 'Clientes: Clientes y CRM',
        'area_produccion' => 'Producción: Operaciones, Producción, Órdenes de Compra, Calendario, Winperfil, Asistente IA',
        'area_productos'  => 'Productos: Agregar, Lista de Precios, Importador, Inventario',
        'area_compras'    => 'Compras: Facturas de Compra, Compras Mensuales, Proveedores',
        'area_finanzas'   => 'Finanzas: Conciliación, Boletas, CxC, CxP, Gastos, Transbank, Registros, EERR',
        'area_rrhh'       => 'RR.HH.: Empleados y Asistencia',
        'area_admin'      => 'Administración: Gestión de usuarios, roles y permisos',
    ];

    /** Área => permisos legacy que la habilitaban (si el rol tenía alguno, hereda el área) */
    private array $mapa = [
        'area_ventas'     => ['ver_dashboard', 'ver_cotizaciones', 'gestionar_cotizaciones'],
        'area_clientes'   => ['ver_clientes', 'gestionar_clientes', 'ver_cotizaciones', 'gestionar_cotizaciones'],
        'area_produccion' => ['gestionar_cotizaciones', 'ver_dashboard'],
        'area_productos'  => ['ver_productos', 'gestionar_productos'],
        'area_compras'    => ['ver_dashboard', 'gestionar_usuarios'],
        'area_finanzas'   => ['gestionar_usuarios'],
        'area_rrhh'       => ['gestionar_usuarios'],
        'area_admin'      => ['gestionar_usuarios'],
    ];

    public function up(): void
    {
        // 1) Crear permisos de área
        $areaId = [];
        foreach ($this->areas as $nombre => $desc) {
            $id = DB::table('permissions')->where('nombre', $nombre)->value('id');
            if (!$id) {
                $id = DB::table('permissions')->insertGetId([
                    'nombre' => $nombre, 'descripcion' => $desc,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                DB::table('permissions')->where('id', $id)->update(['descripcion' => $desc, 'updated_at' => now()]);
            }
            $areaId[$nombre] = $id;
        }

        // 2) Para cada rol, heredar áreas según los permisos legacy que ya tenga
        $roles = DB::table('roles')->pluck('id');
        foreach ($roles as $roleId) {
            $legacy = DB::table('role_permission as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('rp.role_id', $roleId)
                ->pluck('p.nombre')
                ->all();

            foreach ($this->mapa as $area => $requiere) {
                if (array_intersect($legacy, $requiere)) {
                    DB::table('role_permission')->updateOrInsert(
                        ['role_id' => $roleId, 'permission_id' => $areaId[$area]],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }

        // 3) El rol Admin siempre tiene TODAS las áreas
        $adminRoleId = DB::table('roles')->where('nombre', 'Admin')->value('id');
        if ($adminRoleId) {
            foreach ($areaId as $id) {
                DB::table('role_permission')->updateOrInsert(
                    ['role_id' => $adminRoleId, 'permission_id' => $id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        // 4) Borrar permisos legacy (el pivot cae por FK onDelete cascade)
        $legacyNames = [
            'gestionar_usuarios', 'gestionar_roles', 'gestionar_productos', 'ver_productos',
            'gestionar_cotizaciones', 'ver_cotizaciones', 'aprobar_cotizaciones',
            'gestionar_clientes', 'ver_clientes', 'ver_dashboard',
        ];
        DB::table('permissions')->whereIn('nombre', $legacyNames)->delete();
    }

    public function down(): void
    {
        // No reversible (transformación de datos). No-op.
    }
};
