<?php

namespace App\Support;

class MenuCatalog
{
    public const ITEMS = [
        [
            'id' => 'tomato-pasta',
            'name' => 'Slow-roasted tomato pasta',
            'description' => 'Basil, parmesan, a little patience',
            'category' => 'Pasta',
            'price_cents' => 1650,
            'image' => 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?auto=format&fit=crop&w=480&q=80',
        ],
        [
            'id' => 'crispy-chicken',
            'name' => 'Crispy chicken sandwich',
            'description' => 'Pickled fennel, green herb mayo',
            'category' => 'From the kitchen',
            'price_cents' => 1425,
            'image' => 'https://images.unsplash.com/photo-1553909489-cd47e0ef937f?auto=format&fit=crop&w=480&q=80',
        ],
        [
            'id' => 'market-salad',
            'name' => 'The market salad',
            'description' => 'Whatever looked good this morning',
            'category' => 'Something fresh',
            'price_cents' => 1275,
            'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=480&q=80',
        ],
    ];
}