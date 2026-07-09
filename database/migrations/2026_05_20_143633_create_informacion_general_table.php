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

            $table->string('nombre_firma_1', 100)->nullable();
            $table->string('nombre_firma_2', 100)->nullable();
            $table->string('nombre_firma_3', 100)->nullable();

            $table->text('encabezado')->nullable();
            $table->text('pie_pagina')->nullable();
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
