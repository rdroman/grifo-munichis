<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trabajador extends Model
{
    protected $table = 'trabajadores';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'establecimiento_id',
        'nombre_completo',
        'numero_documento',
        'activo',
        'fecha_ingreso',
        'fecha_cese',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_ingreso' => 'date',
            'fecha_cese' => 'date',
            'creado_en' => 'datetime',
        ];
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(
            Usuario::class,
            'trabajador_id',
            'id'
        );
    }
}
