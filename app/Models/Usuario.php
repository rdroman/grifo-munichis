<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $authPasswordName = 'contrasena';

    protected $rememberTokenName = null;

    protected $fillable = [
        'establecimiento_id',
        'trabajador_id',
        'nombre',
        'correo_electronico',
        'contrasena',
        'telefono',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'contrasena',
        'munichis_totp_secreto',
        'munichis_totp_ultimo_paso',
        'munichis_totp_recuperacion',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'munichis_totp_secreto' => 'encrypted',
            'munichis_totp_ultimo_paso' => 'integer',
            'munichis_totp_recuperacion' => 'encrypted:array',
            'creado_en' => 'datetime',
            'actualizado_en' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(
            Trabajador::class,
            'trabajador_id',
            'id'
        );
    }
}
