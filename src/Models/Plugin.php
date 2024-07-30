<?php

namespace Startupful\StartupfulPlugin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'description',
        'developer',
        'is_active',
        'installed_at',
        'last_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'installed_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    public function settings()
    {
        return $this->hasMany(PluginSetting::class);
    }
}