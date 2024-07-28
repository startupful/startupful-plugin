<?php

namespace Filament\Startupful\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PluginSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'plugin_id',
        'plugin_name',
        'key',
        'value',
    ];

    public function plugin()
    {
        return $this->belongsTo(Plugin::class);
    }
}