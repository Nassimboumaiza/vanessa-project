<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::where('is_active', true)->get();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run CategorySeeder first.');
            return;
        }

        $products = [
            // Floral Category
            [
                'name' => 'Royal Jasmine',
                'slug' => 'royal-jasmine',
                'description' => 'A luxurious blend of jasmine petals and golden amber. This exquisite fragrance captures the essence of blooming jasmine gardens at twilight, with warm amber undertones that linger elegantly on the skin.',
                'short_description' => 'Luxurious jasmine with golden amber undertones',
                'price' => 250.00,
                'compare_price' => 320.00,
                'stock_quantity' => 15,
                'sku' => 'VAN-FL-001',
                'category_slug' => 'floral',
                'notes' => [
                    'top' => ['Bergamot', 'Mandarin', 'Pink Pepper'],
                    'middle' => ['Jasmine Sambac', 'Orange Blossom', 'Ylang-Ylang'],
                    'base' => ['Golden Amber', 'Sandalwood', 'Vanilla'],
                ],
                'concentration' => 'Eau de Parfum',
                'volume_ml' => 100,
                'gender' => 'feminine',
                'country_of_origin' => 'France',
                'is_featured' => true,
                'is_active' => true,
                'images' => ['/images/products/royal-jasmine-1.jpg'],
            ],
            [
                'name' => 'Velvet Rose',
                'slug' => 'velvet-rose',
                'description' => 'Bulgarian rose petals wrapped in soft musk. A romantic and timeless fragrance that embodies pure elegance and sophistication.',
                'short_description' => 'Bulgarian rose with soft musk',
                'price' => 195.00,
                'compare_price' => 250.00,
                'stock_quantity' => 22,
                'sku' => 'VAN-FL-002',
                'category_slug' => 'floral',
                'notes' => [
                    'top' => ['Lemon', 'Blackcurrant Bud'],
                    'middle' => ['Bulgarian Rose', 'Turkish Rose', 'Peony'],
                    'base' => ['White Musk', 'Cedarwood', 'Honey'],
                ],
                'concentration' => 'Eau de Parfum',
                'volume_ml' => 100,
                'gender' => 'feminine',
                'country_of_origin' => 'France',
                'is_featured' => true,
                'is_active' => true,
                'images' => ['/images/products/velvet-rose-1.jpg'],
            ],
            // Oriental Category
            [
                'name' => 'Midnight Oud',
                'slug' => 'midnight-oud',
                'description' => 'Deep, mysterious oud wood with hints of vanilla. An intoxicating blend that evokes the mystery of Arabian nights.',
                'short_description' => 'Mysterious oud with vanilla warmth',
                'price' => 380.00,
                'compare_price' => null,
                'stock_quantity' => 8,
                'sku' => 'VAN-OR-001',
                'category_slug' => 'oriental',
                'notes' => [
                    'top' => ['Saffron', 'Cardamom', 'Nutmeg'],
                    'middle' => ['Oud Wood', 'Rose', 'Patchouli'],
                    'base' => ['Vanilla', 'Amber', 'Leather'],
                ],
                'concentration' => 'Eau de Parfum Intense',
                'volume_ml' => 100,
                'gender' => 'unisex',
                'country_of_origin' => 'UAE',
                'is_featured' => true,
                'is_active' => true,
                'images' => ['/images/products/midnight-oud-1.jpg'],
            ],
            [
                'name' => 'Golden Amber',
                'slug' => 'golden-amber',
                'description' => 'Rich amber with warm vanilla undertones. A golden elixir that wraps you in warmth and sophistication.',
                'short_description' => 'Rich amber with vanilla warmth',
                'price' => 220.00,
                'compare_price' => null,
                'stock_quantity' => 12,
                'sku' => 'VAN-OR-002',
                'category_slug' => 'oriental',
                'notes' => [
                    'top' => ['Mandarin', 'Pink Pepper', 'Coriander'],
                    'middle' => ['Labdanum', 'Benzoin', 'Myrrh'],
                    'base' => ['Amber', 'Vanilla', 'Tonka Bean'],
                ],
                'concentration' => 'Eau de Parfum',
                'volume_ml' => 100,
                'gender' => 'unisex',
                'country_of_origin' => 'France',
                'is_featured' => true,
                'is_active' => true,
                'images' => ['/images/products/golden-amber-1.jpg'],
            ],
            // Woody Category
            [
                'name' => 'Sandalwood Dream',
                'slug' => 'sandalwood-dream',
                'description' => 'Creamy sandalwood with subtle spice notes. A serene and meditative fragrance that brings inner peace.',
                'short_description' => 'Creamy sandalwood with spices',
                'price' => 275.00,
                'compare_price' => 350.00,
                'stock_quantity' => 10,
                'sku' => 'VAN-WO-001',
                'category_slug' => 'woody',
                'notes' => [
                    'top' => ['Cardamom', 'Carrot Seeds'],
                    'middle' => ['Sandalwood', 'Cedarwood', 'Violet'],
                    'base' => ['Musk', 'Ambrette', 'Amyris'],
                ],
                'concentration' => 'Eau de Parfum',
                'volume_ml' => 100,
                'gender' => 'unisex',
                'country_of_origin' => 'India',
                'is_featured' => true,
                'is_active' => true,
                'images' => ['/images/products/sandalwood-dream-1.jpg'],
            ],
            // Citrus Category
            [
                'name' => 'Citrus Bloom',
                'slug' => 'citrus-bloom',
                'description' => 'Fresh bergamot and Italian lemon zest. A vibrant burst of citrus that energizes and uplifts.',
                'short_description' => 'Fresh citrus with bergamot',
                'price' => 165.00,
                'compare_price' => 200.00,
                'stock_quantity' => 30,
                'sku' => 'VAN-CT-001',
                'category_slug' => 'citrus',
                'notes' => [
                    'top' => ['Bergamot', 'Italian Lemon', 'Grapefruit'],
                    'middle' => ['Neroli', 'Orange Blossom', 'Petitgrain'],
                    'base' => ['Cedarwood', 'White Musk', 'Vetiver'],
                ],
                'concentration' => 'Eau de Toilette',
                'volume_ml' => 100,
                'gender' => 'unisex',
                'country_of_origin' => 'Italy',
                'is_featured' => true,
                'is_active' => true,
                'images' => ['/images/products/citrus-bloom-1.jpg'],
            ],
            // Fresh Category
            [
                'name' => 'Ocean Breeze',
                'slug' => 'ocean-breeze',
                'description' => 'Crisp marine notes with sea salt and driftwood. Captures the essence of a coastal morning.',
                'short_description' => 'Marine freshness with sea salt',
                'price' => 180.00,
                'compare_price' => null,
                'stock_quantity' => 25,
                'sku' => 'VAN-FR-001',
                'category_slug' => 'fresh',
                'notes' => [
                    'top' => ['Sea Salt', 'Lemon', 'Mint'],
                    'middle' => ['Seaweed', 'Water Lily', 'Lotus'],
                    'base' => ['Driftwood', 'Musk', 'Ambergris'],
                ],
                'concentration' => 'Eau de Toilette',
                'volume_ml' => 100,
                'gender' => 'unisex',
                'country_of_origin' => 'France',
                'is_featured' => false,
                'is_active' => true,
                'images' => ['/images/products/ocean-breeze-1.jpg'],
            ],
            // Gourmand Category
            [
                'name' => 'Vanilla Delight',
                'slug' => 'vanilla-delight',
                'description' => 'Sweet Madagascar vanilla with caramel and praline. A delicious indulgence for the senses.',
                'short_description' => 'Sweet vanilla with caramel',
                'price' => 210.00,
                'compare_price' => null,
                'stock_quantity' => 18,
                'sku' => 'VAN-GO-001',
                'category_slug' => 'gourmand',
                'notes' => [
                    'top' => ['Bergamot', 'Lemon', 'Almond'],
                    'middle' => ['Madagascar Vanilla', 'Caramel', 'Praline'],
                    'base' => ['Sandalwood', 'White Musk', 'Benzoin'],
                ],
                'concentration' => 'Eau de Parfum',
                'volume_ml' => 100,
                'gender' => 'feminine',
                'country_of_origin' => 'France',
                'is_featured' => false,
                'is_active' => true,
                'images' => ['/images/products/vanilla-delight-1.jpg'],
            ],
        ];

        foreach ($products as $productData) {
            $category = $categories->firstWhere('slug', $productData['category_slug']);
            
            if (!$category) {
                $this->command->warn("Category '{$productData['category_slug']}' not found for product '{$productData['name']}'");
                continue;
            }

            $images = $productData['images'] ?? [];
            unset($productData['images'], $productData['category_slug']);
            
            $productData['category_id'] = $category->id;

            $product = Product::updateOrCreate(
                ['slug' => $productData['slug']],
                $productData
            );

            // Create product images
            foreach ($images as $index => $imagePath) {
                ProductImage::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                    ],
                    [
                        'alt_text' => $product->name,
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                    ]
                );
            }
        }

        // Create additional non-featured products for variety
        $additionalProducts = [
            ['name' => 'Lavender Fields', 'slug' => 'lavender-fields', 'price' => 175.00, 'category' => 'aromatic', 'stock' => 20],
            ['name' => 'Tobacco Noir', 'slug' => 'tobacco-noir', 'price' => 290.00, 'category' => 'leather', 'stock' => 12],
            ['name' => 'Musk Supreme', 'slug' => 'musk-supreme', 'price' => 195.00, 'category' => 'fougere', 'stock' => 18],
            ['name' => 'Cedar Majesty', 'slug' => 'cedar-majesty', 'price' => 245.00, 'category' => 'woody', 'stock' => 15],
            ['name' => 'Spice Route', 'slug' => 'spice-route', 'price' => 225.00, 'category' => 'oriental', 'stock' => 14],
        ];

        foreach ($additionalProducts as $additional) {
            $category = $categories->firstWhere('slug', $additional['category']);
            if ($category) {
                Product::updateOrCreate(
                    ['slug' => $additional['slug']],
                    [
                        'name' => $additional['name'],
                        'price' => $additional['price'],
                        'category_id' => $category->id,
                        'stock_quantity' => $additional['stock'],
                        'sku' => 'VAN-' . strtoupper(substr($category->slug, 0, 2)) . '-' . rand(100, 999),
                        'description' => "A beautiful {$category->name} fragrance.",
                        'concentration' => 'Eau de Parfum',
                        'volume_ml' => 100,
                        'is_featured' => false,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
