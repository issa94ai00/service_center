<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type'];

    protected function casts(): array
    {
        return [
            'type' => 'string',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->getValueAttribute() : $default;
    }

    public function getValueAttribute(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->attributes['value'],
            'bool' => (bool) $this->attributes['value'],
            'decimal' => (string) $this->attributes['value'],
            default => $this->attributes['value'],
        };
    }
}
