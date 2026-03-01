<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Floral',
                'slug' => 'floral',
                'description' => 'Delicate and romantic fragrances featuring flower notes like rose, jasmine, and lily.',
                'image' => '/images/categories/floral.jpg',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Oriental',
                'slug' => 'oriental',
                'description' => 'Rich, warm, and exotic scents with notes of amber, vanilla, and spices.',
                'image' => '/images/categories/oriental.jpg',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Woody',
                'slug' => 'woody',
                'description' => 'Sophisticated fragrances featuring sandalwood, cedar, and patchouli notes.',
                'image' => '/images/categories/woody.jpg',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Fresh',
                'slug' => 'fresh',
                'description' => 'Clean and invigorating scents with citrus, aquatic, and green notes.',
                'image' => '/images/categories/fresh.jpg',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Gourmand',
                'slug' => 'gourmand',
                'description' => 'Sweet and edible-inspired fragrances with notes of vanilla, caramel, and chocolate.',
                'image' => '/images/categories/gourmand.jpg',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Chypre',
                'slug' => 'chypre',
                'description' => 'Classic fragrances with bergamot, oakmoss, and labdanum notes.',
                'image' => '/images/categories/chypre.jpg',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Fougère',
                'slug' => 'fougere',
                'description' => 'Masculine fragrances featuring lavender, oakmoss, and coumarin.',
                'image' => '/images/categories/fougere.jpg',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Citrus',
                'slug' => 'citrus',
                'description' => 'Bright and uplifting scents with lemon, bergamot, and orange notes.',
                'image' => '/images/categories/citrus.jpg',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'name' => 'Aromatic',
                'slug' => 'aromatic',
                'description' => 'Herbal and fresh scents with lavender, sage, and rosemary.',
                'image' => '/images/categories/aromatic.jpg',
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'name' => 'Leather',
                'slug' => 'leather',
                'description' => 'Bold and sophisticated scents featuring leather, tobacco, and smoky notes.',
                'image' => '/images/categories/leather.jpg',
                'sort_order' => 10,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
