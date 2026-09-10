<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedor';
    public $timestamps = false;
    protected $fillable = ['nombre'];
    public function entradas()
    {
        return $this->hasMany(Entradas::class, 'id_proveedor');
    }
}
