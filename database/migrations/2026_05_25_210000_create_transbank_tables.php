<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transbank_archivos', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7);          // YYYY-MM
            $table->enum('tipo', ['credito', 'debito', 'prepago']);
            $table->string('nombre_archivo', 255);
            $table->string('rut_empresa', 20)->nullable();
            $table->bigInteger('total_ventas')->default(0);
            $table->bigInteger('total_comision')->default(0);
            $table->bigInteger('total_iva_comision')->default(0);
            $table->bigInteger('total_servicio')->default(0);
            $table->bigInteger('total_iva_servicio')->default(0);
            $table->bigInteger('total_abono')->default(0);
            $table->integer('cantidad_transacciones')->default(0);
            $table->timestamps();

            $table->unique(['periodo', 'tipo']);
        });

        Schema::create('transbank_abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archivo_id')
                  ->constrained('transbank_archivos')
                  ->cascadeOnDelete();
            $table->date('fecha_abono');
            $table->bigInteger('total_venta_bruta')->default(0);
            $table->bigInteger('total_comision')->default(0);
            $table->bigInteger('total_iva_comision')->default(0);
            $table->bigInteger('total_venta_neta')->default(0);  // ya con comisión descontada
            $table->bigInteger('total_servicio')->default(0);    // cobros por servicio + IVA
            $table->bigInteger('net_abono')->default(0);         // lo que efectivamente llega al banco
            $table->foreignId('movimiento_bancario_id')
                  ->nullable()
                  ->constrained('movimientos_bancarios')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['archivo_id', 'fecha_abono']);
        });

        Schema::create('transbank_transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abono_id')
                  ->constrained('transbank_abonos')
                  ->cascadeOnDelete();
            $table->enum('tipo', ['Venta', 'Servicio', 'Anulacion']);
            $table->dateTime('fecha_movimiento')->nullable();
            $table->string('tipo_tarjeta', 30)->nullable();
            $table->bigInteger('monto_original')->default(0);
            $table->bigInteger('monto_comision')->default(0);
            $table->bigInteger('iva_comision')->default(0);
            $table->bigInteger('total_abono')->default(0);
            $table->bigInteger('monto_servicio')->default(0);
            $table->bigInteger('iva_servicio')->default(0);
            $table->string('nro_voucher', 30)->nullable();
            $table->string('codigo_autorizacion', 20)->nullable();
            $table->string('tipo_documento', 20)->nullable();  // BOLETA, FACTURA, N/A
            $table->string('nro_tarjeta', 30)->nullable();     // masked
            $table->timestamps();

            $table->index('abono_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transbank_transacciones');
        Schema::dropIfExists('transbank_abonos');
        Schema::dropIfExists('transbank_archivos');
    }
};
