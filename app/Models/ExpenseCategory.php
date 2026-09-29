<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * باب من أبواب الصرف: إيجار، كهرباء، مواصلات...
 *
 * الصنف هو كل ما يجعل المصاريف قابلة للقراءة لاحقاً، فبدونه يصبح الصندوق
 * قائمة مبالغ خرجت دون أن يُعرف فيمَ خرجت.
 */
class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
