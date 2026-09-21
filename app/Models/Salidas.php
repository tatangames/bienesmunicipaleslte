<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Salidas extends Model
{
    use HasFactory;
    protected $table = 'salidas';
    public $timestamps = false;
    protected $fillable = [
        'id_equipo',
        'fecha',
        'descripcion',

        // Ficha de salida
        'ficha_nombre',
        'ficha_talonario',
        'numero_contrato',
        'numero_orden',

        // Firmas
        'nombre_firma_1',
        'nombre_firma_2',
        'nombre_firma_3',
        'nombre_firma_4',

        // Información de salida
        'autoriza_a',
        'peticion_a',
        'para_uso',

        // PDF
        'encabezado',
        'pie_pagina',

        // Datos del responsable de salida
        'nombre_salida',
        'cargo_salida',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function detalle()
    {
        return $this->hasMany(SalidasDetalle::class, 'id_salida');
    }

    public function detalles()
    {
        return $this->hasMany(SalidasDetalle::class, 'id_salida', 'id');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipos::class, 'id_equipo');
    }

}
