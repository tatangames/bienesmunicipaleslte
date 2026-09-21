<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AJUSTES DE 1 FILA
     */
    public function up(): void
    {
        Schema::create('informacion_general', function (Blueprint $table) {
            $table->id();

            // REPORTE PIXELES DISTANCIAS
            $table->integer('px_firmas');

            // PARA EL BLOQUE SALIDA - GENERAR PDF
            $table->string('nombre_firma_1', 100)->nullable();
            $table->string('nombre_firma_2', 100)->nullable();
            $table->string('nombre_firma_3', 100)->nullable();
            $table->string('nombre_firma_4', 100)->nullable();

            $table->text('encabezado')->nullable();
            $table->text('pie_pagina')->nullable();

            $table->boolean('salto_pagina');

            // Para Reporte "CONTROL DE ENTRADAS / SALIDAS"
            $table->string('control_nombre', 100)->nullable();
            $table->string('control_cargo', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('informacion_general');
    }
};
