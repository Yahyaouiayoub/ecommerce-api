<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // =========================
        // 1. CREATE ADMIN USER
        // =========================
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'System',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '0612345678',
            'address' => '123 Admin Street',
            'city' => 'Casablanca',
            'country' => 'Morocco',
            'state' => 'Casablanca-Settat',
        ]);

        // =========================
        // 2. CREATE CLIENT USER
        // =========================
        $client = User::create([
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'phone' => '0612345679',
            'address' => '456 Client Avenue',
            'city' => 'Rabat',
            'country' => 'Morocco',
            'state' => 'Rabat-Salé-Kénitra',
        ]);

        $this->command->info('✅ Users created successfully!');

        // =========================
        // 3. CREATE CATEGORIES (ALL WITH SAME IMAGE)
        // =========================
        $categories = [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Latest gadgets and electronic devices',
                'image' => '/category.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Clothing',
                'slug' => 'clothing',
                'description' => 'Fashionable clothing for men and women',
                'image' => '/category.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Books',
                'slug' => 'books',
                'description' => 'Books from various genres',
                'image' => '/category.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Home & Garden',
                'slug' => 'home-garden',
                'description' => 'Furniture and garden supplies',
                'image' => '/category.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Sports & Outdoors',
                'slug' => 'sports-outdoors',
                'description' => 'Sports equipment and outdoor gear',
                'image' => '/category.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Beauty & Health',
                'slug' => 'beauty-health',
                'description' => 'Beauty products and health supplies',
                'image' => '/category.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }

        $this->command->info('✅ Categories created successfully!');

        // =========================
        // 4. CREATE PRODUCTS (ALL WITH SAME IMAGE)
        // =========================
        $categoryIds = Category::pluck('id')->toArray();

        $products = [
            // Electronics
            [
                'category_id' => $categoryIds[0],
                'name' => 'Smartphone Pro Max',
                'slug' => 'smartphone-pro-max',
                'description' => 'Latest smartphone with advanced camera and long battery life.',
                'price' => 7999.00,
                'stock' => 50,
                'brand' => 'TechPro',
                'sku' => 'TP-SPM-001',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => true,
            ],
            [
                'category_id' => $categoryIds[0],
                'name' => 'Wireless Headphones',
                'slug' => 'wireless-headphones',
                'description' => 'Noise-cancelling wireless headphones with premium sound quality.',
                'price' => 1499.00,
                'stock' => 30,
                'brand' => 'AudioWave',
                'sku' => 'AW-WH-002',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => true,
            ],
            [
                'category_id' => $categoryIds[0],
                'name' => 'Smart Watch',
                'slug' => 'smart-watch',
                'description' => 'Fitness tracker with heart rate monitor and GPS.',
                'price' => 2499.00,
                'stock' => 25,
                'brand' => 'WearTech',
                'sku' => 'WT-SW-003',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],

            // Clothing
            [
                'category_id' => $categoryIds[1],
                'name' => 'Classic T-Shirt',
                'slug' => 'classic-t-shirt',
                'description' => 'Comfortable cotton t-shirt available in various colors.',
                'price' => 199.00,
                'stock' => 100,
                'brand' => 'FashionCo',
                'sku' => 'FC-CT-004',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => true,
            ],
            [
                'category_id' => $categoryIds[1],
                'name' => 'Denim Jeans',
                'slug' => 'denim-jeans',
                'description' => 'Classic denim jeans with modern fit.',
                'price' => 599.00,
                'stock' => 60,
                'brand' => 'DenimCo',
                'sku' => 'DC-DJ-005',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],

            // Books
            [
                'category_id' => $categoryIds[2],
                'name' => 'The Art of Programming',
                'slug' => 'art-programming',
                'description' => 'A comprehensive guide to modern programming techniques.',
                'price' => 349.00,
                'stock' => 40,
                'brand' => 'BookHouse',
                'sku' => 'BH-AP-006',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => true,
            ],
            [
                'category_id' => $categoryIds[2],
                'name' => 'The History of Time',
                'slug' => 'history-time',
                'description' => 'A fascinating journey through the history of timekeeping.',
                'price' => 299.00,
                'stock' => 35,
                'brand' => 'BookHouse',
                'sku' => 'BH-HT-007',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],

            // Home & Garden
            [
                'category_id' => $categoryIds[3],
                'name' => 'Modern Desk Lamp',
                'slug' => 'modern-desk-lamp',
                'description' => 'Minimalist desk lamp with adjustable brightness.',
                'price' => 449.00,
                'stock' => 20,
                'brand' => 'HomeStyle',
                'sku' => 'HS-DL-008',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],
            [
                'category_id' => $categoryIds[3],
                'name' => 'Indoor Plant Set',
                'slug' => 'indoor-plant-set',
                'description' => 'Set of 3 low-maintenance indoor plants.',
                'price' => 599.00,
                'stock' => 15,
                'brand' => 'GreenLife',
                'sku' => 'GL-IP-009',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],

            // Sports & Outdoors
            [
                'category_id' => $categoryIds[4],
                'name' => 'Yoga Mat',
                'slug' => 'yoga-mat',
                'description' => 'Non-slip yoga mat with carrying strap.',
                'price' => 399.00,
                'stock' => 45,
                'brand' => 'FitLife',
                'sku' => 'FL-YM-010',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],
            [
                'category_id' => $categoryIds[4],
                'name' => 'Sports Water Bottle',
                'slug' => 'sports-water-bottle',
                'description' => 'Insulated water bottle for workouts and outdoor activities.',
                'price' => 199.00,
                'stock' => 80,
                'brand' => 'FitLife',
                'sku' => 'FL-WB-011',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],

            // Beauty & Health
            [
                'category_id' => $categoryIds[5],
                'name' => 'Vitamin C Serum',
                'slug' => 'vitamin-c-serum',
                'description' => 'Brightening serum with 20% vitamin C.',
                'price' => 299.00,
                'stock' => 40,
                'brand' => 'PureBeauty',
                'sku' => 'PB-VC-012',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],
            [
                'category_id' => $categoryIds[5],
                'name' => 'Organic Face Cream',
                'slug' => 'organic-face-cream',
                'description' => '100% natural face cream for all skin types.',
                'price' => 249.00,
                'stock' => 35,
                'brand' => 'PureBeauty',
                'sku' => 'PB-FC-013',
                'thumbnail' => '/product.jpg',
                'is_active' => true,
                'featured' => false,
            ],
        ];

        foreach ($products as $productData) {
            $product = Product::create($productData);

            // Create 3 images for each product (all with same image)
            for ($i = 1; $i <= 3; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => '/product.jpg',
                    'sort_order' => $i,
                ]);
            }
        }

        $this->command->info('✅ Products created successfully!');

        // =========================
        // 5. SUMMARY
        // =========================
        $this->command->info('============================');
        $this->command->info('🎉 SEEDING COMPLETED!');
        $this->command->info('============================');
        $this->command->info('👤 Admin: admin@example.com / password');
        $this->command->info('👤 Client: client@example.com / password');
        $this->command->info('📦 Categories: ' . Category::count());
        $this->command->info('📦 Products: ' . Product::count());
        $this->command->info('============================');
    }
}
