<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Container extends Element
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->type = 'container';
    }

    public function newQuery()
    {
        return parent::newQuery()->where('type', 'container');
    }

    public static function create(array $attributes = [])
    {
        $attributes['type'] = 'container';
        return static::query()->create($attributes);
    }

    public function getContainerAttributes()
    {
        return [
            'has_repeater' => $this->has_repeater,
            'has_clear_button' => $this->has_clear_button,
            'repeater_item_label' => $this->repeater_item_label,
        ];
    }

    public function children()
    {
        return $this->childElements();
    }
}
