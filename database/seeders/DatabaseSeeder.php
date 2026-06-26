<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Revenue;
use App\Models\Expense;
use App\Models\Cart;
use App\Models\Review;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Weighted customer segments: [count, label_prefix, orders_range, order_multiplier]
     * order_multiplier affects frequency (higher = more orders)
     */
    private array $segments = [
        ['count' => 5,  'label' => 'vip',     'orders' => [8, 18], 'multiplier' => 1.8],
        ['count' => 10, 'label' => 'regular', 'orders' => [4, 10], 'multiplier' => 1.0],
        ['count' => 10, 'label' => 'occasional', 'orders' => [2, 5], 'multiplier' => 0.6],
        ['count' => 5,  'label' => 'onetime', 'orders' => [1, 2], 'multiplier' => 0.3],
    ];

    /**
     * Monthly order weights — higher = more orders that month.
     * Index 0 = July 2025 (12 months ago), index 11 = June 2026 (current month).
     */
    private array $monthWeights = [
        0.6,  // Jul 2025 — summer slowdown
        0.7,  // Aug 2025 — summer
        0.8,  // Sep 2025 — back to school
        0.9,  // Oct 2025 — normal
        1.0,  // Nov 2025 — pre-holiday
        1.8,  // Dec 2025 — holiday peak
        0.7,  // Jan 2026 — post-holiday lull
        0.8,  // Feb 2026 — normal
        0.9,  // Mar 2026 — spring
        1.0,  // Apr 2026 — normal
        1.1,  // May 2026 — pre-summer
        1.2,  // Jun 2026 — summer boost (current)
    ];

    private array $moroccanCities = [
        ['city' => 'Casablanca', 'state' => 'Casablanca-Settat',     'postal' => '20000'],
        ['city' => 'Rabat',      'state' => 'Rabat-Salé-Kénitra',    'postal' => '10000'],
        ['city' => 'Marrakech',  'state' => 'Marrakech-Safi',        'postal' => '40000'],
        ['city' => 'Fès',        'state' => 'Fès-Meknès',            'postal' => '30000'],
        ['city' => 'Tangier',    'state' => 'Tanger-Tétouan-Al Hoceïma', 'postal' => '90000'],
        ['city' => 'Agadir',     'state' => 'Souss-Massa',           'postal' => '80000'],
        ['city' => 'Meknès',     'state' => 'Fès-Meknès',            'postal' => '50000'],
        ['city' => 'Oujda',      'state' => 'Oriental',              'postal' => '60000'],
        ['city' => 'El Jadida',  'state' => 'Casablanca-Settat',     'postal' => '24000'],
        ['city' => 'Tétouan',    'state' => 'Tanger-Tétouan-Al Hoceïma', 'postal' => '93000'],
    ];

    private array $streetNames = [
        'Mohammed V', 'Hassan II', 'Liberté', 'Paris', 'Fès', 'Meknès',
        'Oujda', 'Laâyoune', 'Roosevelt', 'Moulay Youssef', 'Allal Ben Abdellah',
        'Abdelmoumen', 'Zerktouni', 'Massaoudi', 'Oqba Ibn Nafiaa',
    ];

    private array $streetTypes = ['Street', 'Avenue', 'Boulevard', 'Rue', 'Place'];

    private array $addressLabels = ['Home', 'Work', 'Office', 'Vacation Home'];

    /**
     * Order status distribution: weighted random.
     */
    private function pickOrderStatus(): string
    {
        $r = mt_rand(1, 100);
        return match (true) {
            $r <= 48  => 'delivered',    // 48% delivered
            $r <= 58  => 'shipped',      // 10% shipped
            $r <= 68  => 'processing',   // 10% processing
            $r <= 78  => 'pending',      // 10% pending
            $r <= 93  => 'cancelled',    // 15% cancelled
            default   => 'delivered',    // default to delivered (used for refunded — see below)
        };
    }

    public function run(): void
    {
        $this->command->info('🚀 Seeding realistic demo data...');
        $this->command->newLine();
        $start = microtime(true);

        // ========================================================================
        // 1. SHIPPING METHODS (must exist before orders)
        // ========================================================================
        ShippingMethod::insert([
            ['name' => 'Standard Shipping', 'description' => 'Delivery within 5-7 business days', 'cost' => 8.00, 'estimated_days' => 7, 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Express Shipping',  'description' => 'Delivery within 2-3 business days', 'cost' => 19.00, 'estimated_days' => 3, 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Next Day Delivery', 'description' => 'Delivery by tomorrow 6 PM',        'cost' => 35.00, 'estimated_days' => 1, 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Pickup in Store',   'description' => 'Free pickup at our Casablanca store', 'cost' => 0.00, 'estimated_days' => 1, 'sort_order' => 4, 'is_active' => true],
        ]);
        $shippingMethods = ShippingMethod::pluck('id')->toArray();
        $this->command->info('  ✅ Shipping methods created');

        // ========================================================================
        // 2. USERS — Admin + 30 clients
        // ========================================================================
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name'  => 'System',
            'email'      => 'amin@example.com',
            'password'   => Hash::make('password'),
            'role'       => 'admin',
            'phone'      => '0612345678',
            'gender'     => 'male',
        ]);
        $this->command->info('  ✅ Admin created');

        // 30 realistic Moroccan client names with gender
        $clientProfiles = [
            ['first_name' => 'Youssef', 'last_name' => 'Benali',     'gender' => 'male'],
            ['first_name' => 'Fatima',  'last_name' => 'Zahra',      'gender' => 'female'],
            ['first_name' => 'Karim',   'last_name' => 'Idrissi',    'gender' => 'male'],
            ['first_name' => 'Amina',   'last_name' => 'El Khoury',  'gender' => 'female'],
            ['first_name' => 'Hassan',  'last_name' => 'Ouazzani',   'gender' => 'male'],
            ['first_name' => 'Nadia',   'last_name' => 'Fassi',      'gender' => 'female'],
            ['first_name' => 'Omar',    'last_name' => 'Tazi',       'gender' => 'male'],
            ['first_name' => 'Sara',    'last_name' => 'Mansouri',   'gender' => 'female'],
            ['first_name' => 'Mohamed', 'last_name' => 'El Amrani',  'gender' => 'male'],
            ['first_name' => 'Leila',   'last_name' => 'Bennis',     'gender' => 'female'],
            ['first_name' => 'Ahmed',   'last_name' => 'Berrada',    'gender' => 'male'],
            ['first_name' => 'Samira',  'last_name' => 'El Fassi',   'gender' => 'female'],
            ['first_name' => 'Rachid',  'last_name' => 'Alaoui',     'gender' => 'male'],
            ['first_name' => 'Nawal',   'last_name' => 'Bennani',    'gender' => 'female'],
            ['first_name' => 'Hicham',  'last_name' => 'Chaoui',     'gender' => 'male'],
            ['first_name' => 'Khadija', 'last_name' => 'Slimani',    'gender' => 'female'],
            ['first_name' => 'Abdellah','last_name' => 'El Idrissi', 'gender' => 'male'],
            ['first_name' => 'Mariam',  'last_name' => 'El Ouafi',   'gender' => 'female'],
            ['first_name' => 'Hamza',   'last_name' => 'Boukhriss',  'gender' => 'male'],
            ['first_name' => 'Souad',   'last_name' => 'Lamrani',    'gender' => 'female'],
            ['first_name' => 'Tariq',   'last_name' => 'Essakali',   'gender' => 'male'],
            ['first_name' => 'Rania',   'last_name' => 'Belmokhtar', 'gender' => 'female'],
            ['first_name' => 'Driss',   'last_name' => 'Jebbour',    'gender' => 'male'],
            ['first_name' => 'Zineb',   'last_name' => 'El Harti',   'gender' => 'female'],
            ['first_name' => 'Anas',    'last_name' => 'Mouline',    'gender' => 'male'],
            ['first_name' => 'Asmae',   'last_name' => 'Salhi',      'gender' => 'female'],
            ['first_name' => 'Walid',   'last_name' => 'El Malki',   'gender' => 'male'],
            ['first_name' => 'Imane',   'last_name' => 'Kabbaj',     'gender' => 'female'],
            ['first_name' => 'Said',    'last_name' => 'El Bouazzaoui', 'gender' => 'male'],
            ['first_name' => 'Meriem',  'last_name' => 'Chraibi',    'gender' => 'female'],
        ];

        $clients = [];
        $profileIndex = 0;
        foreach ($this->segments as $seg) {
            for ($i = 0; $i < $seg['count']; $i++) {
                $profile = $clientProfiles[$profileIndex % count($clientProfiles)];
                $profileIndex++;
                $num = count($clients) + 1;
                $client = User::create([
                    'first_name' => $profile['first_name'],
                    'last_name'  => $profile['last_name'],
                    'email'      => "client{$num}@example.com",
                    'password'   => Hash::make('password'),
                    'role'       => 'client',
                    'phone'      => '06' . str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                    'gender'     => $profile['gender'],
                ]);
                $client->segment = $seg['label'];
                $client->orderRange = $seg['orders'];
                $clients[] = $client;
            }
        }

        $allUsers = [$admin, ...$clients];
        $this->command->info('  ✅ Admin + ' . count($clients) . ' clients created');

        // ========================================================================
        // 3. BRANDS
        // ========================================================================
        $brandsData = [
            ['name' => 'TechPro',    'slug' => 'techpro',    'description' => 'Premium electronics and gadgets'],
            ['name' => 'AudioWave',  'slug' => 'audiowave',  'description' => 'High-quality audio equipment'],
            ['name' => 'WearTech',   'slug' => 'weartech',   'description' => 'Smart wearables and accessories'],
            ['name' => 'FashionCo',  'slug' => 'fashionco',  'description' => 'Trendy clothing and apparel'],
            ['name' => 'DenimCo',    'slug' => 'denimco',    'description' => 'Premium denim and casual wear'],
            ['name' => 'BookHouse',  'slug' => 'bookhouse',  'description' => 'Books and educational materials'],
            ['name' => 'HomeStyle',  'slug' => 'homestyle',  'description' => 'Modern home and furniture'],
            ['name' => 'GreenLife',  'slug' => 'greenlife',  'description' => 'Eco-friendly and garden products'],
            ['name' => 'FitLife',    'slug' => 'fitlife',    'description' => 'Sports and fitness equipment'],
            ['name' => 'PureBeauty', 'slug' => 'purebeauty', 'description' => 'Organic beauty and skincare'],
            ['name' => 'PetLove',    'slug' => 'petlove',    'description' => 'Pet supplies and accessories'],
            ['name' => 'ToyWorld',   'slug' => 'toyworld',   'description' => 'Toys and games for all ages'],
        ];
        foreach ($brandsData as $b) {
            Brand::create($b);
        }
        $brandByName = Brand::pluck('id', 'name')->toArray();
        $this->command->info('  ✅ 12 brands created');

        // ========================================================================
        // 4. CATEGORIES
        // ========================================================================
        $categoriesData = [
            ['name' => 'Electronics',      'slug' => 'electronics',      'description' => 'Latest gadgets and electronic devices'],
            ['name' => 'Clothing',         'slug' => 'clothing',         'description' => 'Fashionable clothing for men and women'],
            ['name' => 'Books',            'slug' => 'books',            'description' => 'Books from various genres'],
            ['name' => 'Home & Garden',    'slug' => 'home-garden',      'description' => 'Furniture and garden supplies'],
            ['name' => 'Sports & Outdoors','slug' => 'sports-outdoors',  'description' => 'Sports equipment and outdoor gear'],
            ['name' => 'Beauty & Health',  'slug' => 'beauty-health',    'description' => 'Beauty products and health supplies'],
            ['name' => 'Toys & Games',     'slug' => 'toys-games',       'description' => 'Fun for all ages'],
            ['name' => 'Pet Supplies',     'slug' => 'pet-supplies',     'description' => 'Everything for your pets'],
        ];
        foreach ($categoriesData as $c) {
            Category::create($c);
        }
        $catIds = Category::pluck('id')->toArray();
        $catByName = Category::pluck('id', 'name')->toArray();
        $this->command->info('  ✅ 8 categories created');

        // ========================================================================
        // 5. PRODUCTS (27 products with purchase prices for realistic margins)
        // ========================================================================
        $productsData = [
            // === Electronics ===
            ['cat' => 'Electronics', 'brand' => 'TechPro',    'name' => 'Smartphone Pro Max',           'slug' => 'smartphone-pro-max',          'desc' => '6.7" OLED display, 256GB storage, 48MP triple camera system with night mode, 5G compatible, water-resistant (IP68).',                'price' => 8999.00,  'purchase' => 6200.00, 'stock' => 45,  'sku' => 'TP-SPM-001', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'TechPro',    'name' => 'Ultrabook Laptop 15"',         'slug' => 'ultrabook-laptop-15',         'desc' => '15.6" FHD display, Intel Core i7, 16GB RAM, 512GB SSD, backlit keyboard, fingerprint reader. Weighs only 1.4kg.',                  'price' => 12499.00, 'purchase' => 8900.00, 'stock' => 20,  'sku' => 'TP-UB-002', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'AudioWave',  'name' => 'Wireless Noise-Cancelling Headphones', 'slug' => 'wireless-nc-headphones', 'desc' => 'Premium over-ear headphones with ANC, 30-hour battery life, hi-res audio, memory foam ear cushions, foldable.',                   'price' => 2499.00,  'purchase' => 1550.00, 'stock' => 60,  'sku' => 'AW-WH-003', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'AudioWave',  'name' => 'Bluetooth Speaker Mini',        'slug' => 'bluetooth-speaker-mini',      'desc' => 'Portable waterproof speaker with 360° sound, 12-hour playtime, USB-C.',                                                             'price' => 599.00,   'purchase' => 320.00,  'stock' => 80,  'sku' => 'AW-BS-004', 'featured' => false],
            ['cat' => 'Electronics', 'brand' => 'WearTech',   'name' => 'Fitness Smartwatch Pro',       'slug' => 'fitness-smartwatch-pro',      'desc' => 'Advanced fitness tracking with GPS, heart rate, blood oxygen, sleep tracking, 100+ workout modes, 14-day battery.',                'price' => 2999.00,  'purchase' => 1900.00, 'stock' => 35,  'sku' => 'WT-FS-005', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'TechPro',    'name' => 'Wireless Charging Pad',        'slug' => 'wireless-charging-pad',       'desc' => 'Fast wireless charger for all Qi-enabled devices. 15W fast charging, LED indicator, anti-slip design.',                            'price' => 299.00,   'purchase' => 140.00,  'stock' => 120, 'sku' => 'TP-WC-006', 'featured' => false],
            // === Clothing ===
            ['cat' => 'Clothing',    'brand' => 'FashionCo',  'name' => 'Premium Cotton T-Shirt',       'slug' => 'premium-cotton-tshirt',       'desc' => 'Ultra-soft 100% organic cotton t-shirt, modern fit. Pre-shrunk, breathable, 12 colors.',                                          'price' => 249.00,   'purchase' => 95.00,   'stock' => 200, 'sku' => 'FC-PCT-007', 'featured' => true],
            ['cat' => 'Clothing',    'brand' => 'DenimCo',    'name' => 'Slim Fit Denim Jeans',         'slug' => 'slim-fit-denim-jeans',        'desc' => 'Classic slim-fit jeans, premium stretch denim, mid-rise waist, five-pocket styling.',                                              'price' => 699.00,   'purchase' => 320.00,  'stock' => 75,  'sku' => 'DC-SFJ-008', 'featured' => true],
            ['cat' => 'Clothing',    'brand' => 'FashionCo',  'name' => 'Casual Hoodie',               'slug' => 'casual-hoodie',              'desc' => 'Fleece hoodie with kangaroo pocket, adjustable drawstring hood, ribbed cuffs.',                                                   'price' => 499.00,   'purchase' => 210.00,  'stock' => 90,  'sku' => 'FC-CH-009', 'featured' => false],
            ['cat' => 'Clothing',    'brand' => 'DenimCo',    'name' => 'Denim Jacket Classic',         'slug' => 'denim-jacket-classic',        'desc' => 'Timeless denim jacket, button-front, chest pockets, adjustable waist tabs.',                                                       'price' => 899.00,   'purchase' => 420.00,  'stock' => 40,  'sku' => 'DC-DJ-010', 'featured' => false],
            // === Books ===
            ['cat' => 'Books',       'brand' => 'BookHouse',  'name' => 'The Art of Clean Code',        'slug' => 'art-of-clean-code',           'desc' => 'Guide to writing maintainable, scalable software. Covers design patterns, testing, refactoring.',                                  'price' => 399.00,   'purchase' => 180.00,  'stock' => 50,  'sku' => 'BH-ACC-011', 'featured' => true],
            ['cat' => 'Books',       'brand' => 'BookHouse',  'name' => 'Ancient Civilizations',        'slug' => 'history-ancient-civilizations','desc' => 'Journey through ancient civilizations. Richly illustrated with maps and artifacts.',                                                 'price' => 449.00,   'purchase' => 210.00,  'stock' => 30,  'sku' => 'BH-HAC-012', 'featured' => false],
            ['cat' => 'Books',       'brand' => 'BookHouse',  'name' => 'Mediterranean Cookbook',       'slug' => 'cookbook-mediterranean',      'desc' => '200+ authentic Mediterranean recipes with nutritional info and wine pairings.',                                                     'price' => 349.00,   'purchase' => 155.00,  'stock' => 25,  'sku' => 'BH-CMD-013', 'featured' => false],
            // === Home & Garden ===
            ['cat' => 'Home & Garden','brand' => 'HomeStyle', 'name' => 'Minimalist Desk Lamp',         'slug' => 'minimalist-desk-lamp',        'desc' => 'LED desk lamp, adjustable brightness/color temperature, touch control, USB charging.',                                             'price' => 549.00,   'purchase' => 260.00,  'stock' => 35,  'sku' => 'HS-DL-014', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'GreenLife', 'name' => 'Indoor Plant Bundle',          'slug' => 'indoor-plant-bundle',         'desc' => '4 easy-care indoor plants in ceramic pots: Snake Plant, Pothos, ZZ Plant, Peace Lily.',                                           'price' => 799.00,   'purchase' => 380.00,  'stock' => 20,  'sku' => 'GL-IPB-015', 'featured' => true],
            ['cat' => 'Home & Garden','brand' => 'HomeStyle', 'name' => 'Ergonomic Office Chair',       'slug' => 'ergonomic-office-chair',      'desc' => 'Premium mesh chair with lumbar support, adjustable armrests, headrest, tilt.',                                                      'price' => 3499.00,  'purchase' => 2100.00, 'stock' => 15,  'sku' => 'HS-EOC-016', 'featured' => false],
            // === Sports & Outdoors ===
            ['cat' => 'Sports & Outdoors','brand' => 'FitLife','name' => 'Premium Yoga Mat',             'slug' => 'premium-yoga-mat',            'desc' => '6mm eco-friendly TPE yoga mat with alignment lines, non-slip, includes carrying strap.',                                            'price' => 499.00,   'purchase' => 230.00,  'stock' => 60,  'sku' => 'FL-YM-017', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'FitLife','name' => 'Insulated Water Bottle 750ml', 'slug' => 'insulated-water-bottle',      'desc' => 'Double-wall stainless steel. Cold 24h / hot 12h. BPA-free, leak-proof.',                                                           'price' => 249.00,   'purchase' => 110.00,  'stock' => 150, 'sku' => 'FL-IWB-018', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'FitLife','name' => 'Adjustable Dumbbell Set',      'slug' => 'adjustable-dumbbell-set',     'desc' => '2.5kg-25kg each, quick-change weight selection, includes storage tray.',                                                            'price' => 4999.00,  'purchase' => 3100.00, 'stock' => 10,  'sku' => 'FL-ADS-019', 'featured' => true],
            ['cat' => 'Sports & Outdoors','brand' => 'FitLife','name' => 'Running Shoes Pro',            'slug' => 'running-shoes-pro',           'desc' => 'Lightweight performance running shoes with responsive cushioning, breathable mesh upper.',                                          'price' => 1299.00,  'purchase' => 680.00,  'stock' => 45,  'sku' => 'FL-RS-020', 'featured' => true],
            // === Beauty & Health ===
            ['cat' => 'Beauty & Health','brand' => 'PureBeauty','name' => 'Vitamin C Brightening Serum', 'slug' => 'vitamin-c-serum',             'desc' => '20% Vitamin C serum with hyaluronic acid and vitamin E. Brightens skin, reduces dark spots.',                                      'price' => 349.00,   'purchase' => 150.00,  'stock' => 55,  'sku' => 'PB-VCS-021', 'featured' => true],
            ['cat' => 'Beauty & Health','brand' => 'PureBeauty','name' => 'Organic Face Moisturizer',    'slug' => 'organic-face-moisturizer',    'desc' => '100% natural day cream with shea butter, aloe vera, jojoba oil.',                                                                  'price' => 299.00,   'purchase' => 130.00,  'stock' => 40,  'sku' => 'PB-OFM-022', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => 'PureBeauty','name' => 'Hair Repair Shampoo',          'slug' => 'hair-repair-shampoo',         'desc' => 'Sulfate-free with argan oil and keratin. Repairs damaged hair, adds shine.',                                                        'price' => 199.00,   'purchase' => 80.00,   'stock' => 70,  'sku' => 'PB-HRS-023', 'featured' => false],
            // === Toys & Games ===
            ['cat' => 'Toys & Games','brand' => 'ToyWorld',   'name' => 'Building Blocks Kit 1000pc',   'slug' => 'building-blocks-1000',        'desc' => '1000 colorful blocks, compatible with major brands, includes idea booklet.',                                                        'price' => 449.00,   'purchase' => 210.00,  'stock' => 30,  'sku' => 'TW-BB-024', 'featured' => false],
            ['cat' => 'Toys & Games','brand' => 'ToyWorld',   'name' => 'Board Game: Strategy Quest',    'slug' => 'board-game-strategy-quest',   'desc' => 'Award-winning strategy game for 2-6 players, 60 min playtime.',                                                                     'price' => 599.00,   'purchase' => 290.00,  'stock' => 25,  'sku' => 'TW-BG-025', 'featured' => false],
            // === Pet Supplies ===
            ['cat' => 'Pet Supplies','brand' => 'PetLove',    'name' => 'Premium Dog Bed Large',        'slug' => 'premium-dog-bed-large',       'desc' => 'Orthopedic memory foam, removable washable cover, non-slip bottom, for dogs up to 40kg.',                                          'price' => 899.00,   'purchase' => 450.00,  'stock' => 20,  'sku' => 'PL-DB-026', 'featured' => false],
            ['cat' => 'Pet Supplies','brand' => 'PetLove',    'name' => 'Cat Tree Tower',               'slug' => 'cat-tree-tower',              'desc' => 'Multi-level with scratching posts, hiding cubby, hammock, dangling toys. 160cm height.',                                          'price' => 1299.00,  'purchase' => 650.00,  'stock' => 12,  'sku' => 'PL-CT-027', 'featured' => false],
        ];

        // margin_percentage = round((price - purchase_price) / purchase_price * 100, 1)
        $marginPercentages = [];
        foreach ($productsData as $p) {
            $marginPercentages[$p['sku']] = $p['purchase'] > 0
                ? round(($p['price'] - $p['purchase']) / $p['purchase'] * 100, 1)
                : 30.0;
        }

        $productRecords = [];
        foreach ($productsData as $p) {
            $margin = $marginPercentages[$p['sku']];
            $product = Product::create([
                'category_id'       => $catByName[$p['cat']],
                'brand_id'          => $brandByName[$p['brand']],
                'name'              => $p['name'],
                'slug'              => $p['slug'],
                'description'       => $p['desc'],
                'price'             => $p['price'],
                'purchase_price'    => $p['purchase'],
                'margin_percentage' => $margin,
                'final_price'       => Product::calculateFinalPrice($p['purchase'], $margin),
                'stock'             => $p['stock'],
                'sku'               => $p['sku'],
                'thumbnail'         => '/product.jpg',
                'is_active'         => true,
                'featured'          => $p['featured'],
            ]);
            $productRecords[] = $product;

            // Product images
            $imageCount = rand(2, 4);
            for ($i = 1; $i <= $imageCount; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url'  => '/product.jpg',
                    'sort_order' => $i,
                ]);
            }
        }
        $this->command->info('  ✅ ' . count($productRecords) . ' products with images created');

        // ========================================================================
        // 6. PRODUCT VARIANTS
        // ========================================================================
        $variantRecords = [];

        // Smartphone Pro Max — storage + color
        $phone = Product::where('slug', 'smartphone-pro-max')->first();
        if ($phone) {
            $variantRecords = array_merge($variantRecords, [
                ProductVariant::create(['product_id' => $phone->id, 'name' => '256GB - Space Black', 'price' => null,  'stock' => 15, 'sku' => 'TP-SPM-256',  'storage' => '256GB', 'color' => 'Space Black', 'is_default' => true,  'sort_order' => 0]),
                ProductVariant::create(['product_id' => $phone->id, 'name' => '512GB - Space Black', 'price' => 10499.00, 'stock' => 12, 'sku' => 'TP-SPM-512',  'storage' => '512GB', 'color' => 'Space Black', 'is_default' => false, 'sort_order' => 1]),
                ProductVariant::create(['product_id' => $phone->id, 'name' => '1TB - Space Black',   'price' => 12999.00, 'stock' => 8,  'sku' => 'TP-SPM-1TB',  'storage' => '1TB',   'color' => 'Space Black', 'is_default' => false, 'sort_order' => 2]),
                ProductVariant::create(['product_id' => $phone->id, 'name' => '256GB - Silver',      'price' => null,  'stock' => 10, 'sku' => 'TP-SPM-256-SIL', 'storage' => '256GB', 'color' => 'Silver',      'is_default' => false, 'sort_order' => 3]),
                ProductVariant::create(['product_id' => $phone->id, 'name' => '256GB - Midnight Green', 'price' => 9499.00, 'stock' => 6,  'sku' => 'TP-SPM-256-GRN', 'storage' => '256GB', 'color' => 'Midnight Green', 'is_default' => false, 'sort_order' => 4]),
            ]);
        }

        // T-Shirt — size variants
        $tshirt = Product::where('slug', 'premium-cotton-tshirt')->first();
        if ($tshirt) {
            $variantRecords = array_merge($variantRecords, [
                ProductVariant::create(['product_id' => $tshirt->id, 'name' => 'Small',   'price' => null,  'stock' => 40, 'sku' => 'FC-PCT-S',  'size' => 'S',  'is_default' => true,  'sort_order' => 0]),
                ProductVariant::create(['product_id' => $tshirt->id, 'name' => 'Medium',  'price' => null,  'stock' => 60, 'sku' => 'FC-PCT-M',  'size' => 'M',  'is_default' => false, 'sort_order' => 1]),
                ProductVariant::create(['product_id' => $tshirt->id, 'name' => 'Large',   'price' => null,  'stock' => 50, 'sku' => 'FC-PCT-L',  'size' => 'L',  'is_default' => false, 'sort_order' => 2]),
                ProductVariant::create(['product_id' => $tshirt->id, 'name' => 'X-Large', 'price' => 299.00, 'stock' => 30, 'sku' => 'FC-PCT-XL', 'size' => 'XL', 'is_default' => false, 'sort_order' => 3]),
            ]);
        }

        // Running Shoes — size + color
        $shoes = Product::where('slug', 'running-shoes-pro')->first();
        if ($shoes) {
            $variantRecords = array_merge($variantRecords, [
                ProductVariant::create(['product_id' => $shoes->id, 'name' => '42 - Black', 'price' => null,  'stock' => 12, 'sku' => 'FL-RS-42-BLK', 'size' => '42', 'color' => 'Black', 'is_default' => true,  'sort_order' => 0]),
                ProductVariant::create(['product_id' => $shoes->id, 'name' => '42 - White', 'price' => null,  'stock' => 10, 'sku' => 'FL-RS-42-WHT', 'size' => '42', 'color' => 'White', 'is_default' => false, 'sort_order' => 1]),
                ProductVariant::create(['product_id' => $shoes->id, 'name' => '43 - Black', 'price' => null,  'stock' => 8,  'sku' => 'FL-RS-43-BLK', 'size' => '43', 'color' => 'Black', 'is_default' => false, 'sort_order' => 2]),
                ProductVariant::create(['product_id' => $shoes->id, 'name' => '44 - Black', 'price' => 1399.00, 'stock' => 6,  'sku' => 'FL-RS-44-BLK', 'size' => '44', 'color' => 'Black', 'is_default' => false, 'sort_order' => 3]),
                ProductVariant::create(['product_id' => $shoes->id, 'name' => '43 - White', 'price' => null,  'stock' => 7,  'sku' => 'FL-RS-43-WHT', 'size' => '43', 'color' => 'White', 'is_default' => false, 'sort_order' => 4]),
            ]);
        }

        $this->command->info('  ✅ ' . count($variantRecords) . ' product variants created');

        // ========================================================================
        // 7. ADDRESSES
        // ========================================================================
        $userAddressMap = [];
        foreach ($allUsers as $user) {
            $addrCount = $user->isAdmin() ? 1 : rand(1, 3);
            $usedCities = [];
            for ($a = 0; $a < $addrCount; $a++) {
                $loc = $this->moroccanCities[array_rand($this->moroccanCities)];
                $attempts = 0;
                while (in_array($loc['city'], $usedCities) && $attempts < 10) {
                    $loc = $this->moroccanCities[array_rand($this->moroccanCities)];
                    $attempts++;
                }
                $usedCities[] = $loc['city'];

                $address = Address::create([
                    'user_id'       => $user->id,
                    'full_name'     => $user->full_name,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'address_line1' => rand(1, 999) . ' ' . $this->streetTypes[array_rand($this->streetTypes)] . ' ' . $this->streetNames[array_rand($this->streetNames)],
                    'address_line2' => rand(0, 1) ? 'Apt ' . rand(1, 50) : null,
                    'city'          => $loc['city'],
                    'state'         => $loc['state'],
                    'postal_code'   => $loc['postal'],
                    'country'       => 'Morocco',
                    'is_default'    => $a === 0,
                    'label'         => $this->addressLabels[array_rand($this->addressLabels)],
                ]);
                $userAddressMap[$user->id][] = $address->id;
            }
        }
        $this->command->info('  ✅ ' . Address::count() . ' addresses created');

        // ========================================================================
        // 8. ORDERS — Plan distribution across 12 months with realistic volume
        // ========================================================================
        $earliestDate = now()->subMonths(11)->startOfMonth(); // July 1, 2025
        $productPool = [];
        foreach ($productRecords as $pr) {
            $productPool[$pr->id] = $pr;
        }

        // Build per-client order count based on segment
        $clientOrderPlans = []; // [client, order_count]
        foreach ($clients as $client) {
            list($min, $max) = $client->orderRange;
            $count = mt_rand($min, $max);
            $clientOrderPlans[] = ['client' => $client, 'count' => $count];
        }

        $totalOrderCount = array_sum(array_column($clientOrderPlans, 'count'));
        // Also inject some orders from guest sessions (about 8% of total)
        $guestOrderCount = (int) round($totalOrderCount * 0.08);
        $totalOrderCount += $guestOrderCount;

        // Compute monthly caps based on weights
        $totalWeight = array_sum($this->monthWeights);
        $monthlyCaps = [];
        foreach ($this->monthWeights as $i => $weight) {
            $monthlyCaps[$i] = (int) round(($weight / $totalWeight) * $totalOrderCount);
        }

        $this->command->info("  📦 Planning {$totalOrderCount} orders across 12 months...");

        $bar = $this->command->getOutput()->createProgressBar($totalOrderCount);
        $bar->setMessage('Creating orders...');
        $bar->start();

        $ordersCreated = 0;
        $paymentMethods = ['cod', 'card'];

        // Track stock reductions for delivered orders
        $stockDeductions = []; // [product_id => quantity]

        // Flatten client plans into a list of (client, month_bias) for distribution
        $clientOrderBuckets = [];
        foreach ($clientOrderPlans as $plan) {
            for ($i = 0; $i < $plan['count']; $i++) {
                // Frequent shoppers skew toward recent months; occasional/one-time are more uniform
                $bias = $plan['client']->segment === 'vip' ? 0.7 :  // 70% weight to recent 6 months
                        ($plan['client']->segment === 'regular' ? 0.5 : 0.3);
                $clientOrderBuckets[] = ['client' => $plan['client'], 'bias' => $bias];
            }
        }
        shuffle($clientOrderBuckets);

        // Pre-assign months for each order
        $orderAssignments = []; // [month_index => [client, ...]]
        foreach ($clientOrderBuckets as $bucket) {
            $client = $bucket['client'];
            $bias = $bucket['bias'];

            // Pick a month: biased toward recent months if bias > 0
            if (mt_rand(1, 100) <= ($bias * 100)) {
                // Recent 6 months
                $monthIdx = mt_rand(6, 11);
            } else {
                $monthIdx = mt_rand(0, 11);
            }

            // Ensure we don't exceed monthly cap
            $attempts = 0;
            while (($monthlyCaps[$monthIdx] ?? 0) <= 0 && $attempts < 20) {
                $monthIdx = mt_rand(0, 11);
                $attempts++;
            }
            if ($attempts >= 20) {
                // Find any month with remaining capacity
                foreach ($monthlyCaps as $mi => $cap) {
                    if ($cap > 0) { $monthIdx = $mi; break; }
                }
            }

            if (($monthlyCaps[$monthIdx] ?? 0) > 0) {
                $monthlyCaps[$monthIdx]--;
                $orderAssignments[] = ['client' => $client, 'monthIdx' => $monthIdx];
            }
        }

        // Add guest orders
        for ($g = 0; $g < $guestOrderCount; $g++) {
            $monthIdx = mt_rand(0, 11);
            $attempts = 0;
            while (($monthlyCaps[$monthIdx] ?? 0) <= 0 && $attempts < 20) {
                $monthIdx = mt_rand(0, 11);
                $attempts++;
            }
            if (($monthlyCaps[$monthIdx] ?? 0) > 0) {
                $monthlyCaps[$monthIdx]--;
                $orderAssignments[] = ['client' => null, 'monthIdx' => $monthIdx];
            }
        }

        // Sort by month so dates are chronological
        usort($orderAssignments, fn($a, $b) => $a['monthIdx'] <=> $b['monthIdx']);

        // Track which products have been ordered (for best-sellers, reviews)
        $orderedProductIds = [];

        // Guest address pool (shared across guest orders)
        $guestAddressIds = [];

        foreach ($orderAssignments as $assignment) {
            $client = $assignment['client'];
            $monthIdx = $assignment['monthIdx'];
            $isGuest = $client === null;

            // Calculate the base date: mid-month + random offset
            $baseDate = $earliestDate->copy()->addMonths($monthIdx);
            $daysInMonth = (int) $baseDate->copy()->endOfMonth()->format('d');
            $dayOffset = mt_rand(1, max(1, $daysInMonth - 1));
            $hourOffset = mt_rand(9, 21);
            $minuteOffset = mt_rand(0, 59);
            $orderDate = $baseDate->copy()->setDay(min($dayOffset, $daysInMonth))->setTime($hourOffset, $minuteOffset);

            // Don't create future orders (current month is Jun 2026)
            if ($orderDate->isAfter(now())) {
                $orderDate = now()->subHours(mt_rand(1, 72));
            }

            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
            $status = $this->pickOrderStatus();

            // 5% of delivered orders become refunded
            $isRefunded = false;
            if ($status === 'delivered' && mt_rand(1, 100) <= 10) {
                $isRefunded = true;
            }

            // Shipping method
            $shippingMethodId = $shippingMethods[array_rand($shippingMethods)];

            // User & address
            $userId = null;
            $guestName = null;
            $guestEmail = null;
            $addressId = null;

            if ($isGuest) {
                // Use shared guest addresses
                if (count($guestAddressIds) > 0 && mt_rand(0, 1)) {
                    $addressId = $guestAddressIds[array_rand($guestAddressIds)];
                } else {
                    $loc = $this->moroccanCities[array_rand($this->moroccanCities)];
                    $guestAddr = Address::create([
                        'user_id' => null,
                        'full_name' => 'Guest ' . ['Ahmed', 'Sara', 'John', 'Marie', 'David', 'Emma'][array_rand(['Ahmed', 'Sara', 'John', 'Marie', 'David', 'Emma'])],
                        'email' => 'guest' . mt_rand(100, 999) . '@example.com',
                        'phone' => '06' . str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                        'address_line1' => rand(1, 999) . ' ' . $this->streetTypes[array_rand($this->streetTypes)],
                        'city' => $loc['city'],
                        'state' => $loc['state'],
                        'postal_code' => $loc['postal'],
                        'country' => 'Morocco',
                        'is_default' => false,
                    ]);
                    $addressId = $guestAddr->id;
                    $guestAddressIds[] = $addressId;
                }
                $guestName = 'Guest Customer';
                $guestEmail = 'guest' . mt_rand(100, 999) . '@example.com';
            } else {
                $userId = $client->id;
                $userAddresses = $userAddressMap[$userId] ?? [];
                $addressId = count($userAddresses) > 0 ? $userAddresses[array_rand($userAddresses)] : null;
            }

            // Create order
            $order = Order::create([
                'user_id'             => $userId,
                'session_id'          => null,
                'order_number'        => 'ORD-' . $orderDate->timestamp . '-' . strtoupper(substr(uniqid(), -4)),
                'total_price'         => 0, // updated after items
                'status'              => $isRefunded ? 'delivered' : $status,
                'payment_method'      => $paymentMethod,
                'address_id'          => $addressId,
                'shipping_method_id'  => $shippingMethodId,
                'notes'               => $isGuest ? null : (rand(0, 3) === 0 ? 'Please leave at the door.' : null),
                'guest_name'          => $guestName,
                'guest_email'         => $guestEmail,
                'created_at'          => $orderDate,
                'updated_at'          => $orderDate,
            ]);

            // Generate order items — pick 1-5 products, weighted toward popular/cheaper items
            $itemCount = mt_rand(1, 5);
            $usedProducts = [];
            $orderTotal = 0;
            $orderItemRecords = [];

            // Pick products with realistic distribution: cheaper items more common, some categories more popular
            for ($oi = 0; $oi < $itemCount; $oi++) {
                // Weight by price: cheaper items more likely
                $poolWeights = [];
                foreach ($productPool as $pid => $pr) {
                    // Base weight = inverse of price + category bias
                    $catBias = match ($pr->category_id) {
                        $catByName['Electronics'] => 1.8,    // Electronics are popular
                        $catByName['Clothing'] => 1.5,
                        $catByName['Beauty & Health'] => 1.3,
                        $catByName['Sports & Outdoors'] => 1.2,
                        default => 1.0,
                    };
                    $poolWeights[$pid] = (5000 / max(1, $pr->price)) * $catBias;
                }

                // Weighted random selection
                $totalWeight = array_sum($poolWeights);
                $rand = mt_rand(1, (int) $totalWeight);
                $cumulative = 0;
                $pid = null;
                foreach ($poolWeights as $id => $w) {
                    $cumulative += $w;
                    if ($rand <= $cumulative) { $pid = $id; break; }
                }
                if ($pid === null) $pid = array_rand($productPool);

                // Avoid duplicate products in same order
                $attempts = 0;
                while (in_array($pid, $usedProducts) && $attempts < 20) {
                    $rand = mt_rand(1, (int) $totalWeight);
                    $cumulative = 0;
                    foreach ($poolWeights as $id => $w) {
                        $cumulative += $w;
                        if ($rand <= $cumulative) { $pid = $id; break; }
                    }
                    if ($pid === null || in_array($pid, $usedProducts)) {
                        // Fallback: pick any unused product
                        $unused = array_diff(array_keys($productPool), $usedProducts);
                        if (!empty($unused)) $pid = $unused[array_rand($unused)];
                    }
                    $attempts++;
                }
                $usedProducts[] = $pid;

                // Quantity: usually 1, sometimes 2-3
                $qty = match (true) {
                    mt_rand(1, 100) <= 60 => 1,
                    mt_rand(1, 100) <= 85 => 2,
                    default => 3,
                };

                $product = $productPool[$pid];
                $price = $product->getEffectivePrice();
                $subtotal = $price * $qty;
                $orderTotal += $subtotal;

                // Check variant availability
                $variantId = null;
                $variant = $product->variants()->where('is_default', true)->first();
                if ($variant) {
                    $variantId = $variant->id;
                }

                $orderItemRecords[] = OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $pid,
                    'variant_id'  => $variantId,
                    'quantity'    => $qty,
                    'price'       => $price,
                    'created_at'  => $orderDate,
                    'updated_at'  => $orderDate,
                ]);
                $orderedProductIds[] = $pid;

                // Track stock deductions for delivered orders
                if (($status === 'delivered' && !$isRefunded) || $status === 'shipped' || $status === 'processing') {
                    if (!isset($stockDeductions[$pid])) $stockDeductions[$pid] = 0;
                    $stockDeductions[$pid] += $qty;
                }
            }

            // Add shipping cost to total
            $shippingCost = 0;
            $shippingM = ShippingMethod::find($shippingMethodId);
            if ($shippingM) {
                $shippingCost = $shippingM->getEffectiveCost($orderTotal);
            }
            $orderTotal = round($orderTotal + $shippingCost, 2);
            $order->update(['total_price' => $orderTotal]);

            // --- Invoice ---
            $issuedAt = $orderDate->copy()->addDays(mt_rand(0, 2));

            if ($status === 'delivered' && !$isRefunded) {
                $invoiceStatus = 'paid';
                $paidAmount = $orderTotal;
                $paidAt = $issuedAt->copy()->addDays(mt_rand(1, 5));
            } elseif ($isRefunded || $status === 'cancelled') {
                // Refunded: invoice was paid then refunded. Cancelled: unpaid
                if ($isRefunded) {
                    // Simulate paid invoice that was later refunded
                    $paidAtDate = $issuedAt->copy()->addDays(mt_rand(1, 5));
                    $invoiceStatus = 'refunded';
                    $paidAmount = $orderTotal;
                    $paidAt = $paidAtDate;
                } else {
                    $invoiceStatus = 'unpaid';
                    $paidAmount = 0;
                    $paidAt = null;
                }
            } elseif ($status === 'pending') {
                // Pending orders: unpaid invoices
                $invoiceStatus = 'unpaid';
                $paidAmount = 0;
                $paidAt = null;
            } else {
                // processing/shipped: mix of paid and unpaid
                $invRand = mt_rand(1, 100);
                if ($invRand <= 55) {
                    $invoiceStatus = 'paid';
                    $paidAmount = $orderTotal;
                    $paidAt = $issuedAt->copy()->addDays(mt_rand(1, 7));
                } elseif ($invRand <= 80) {
                    $invoiceStatus = 'unpaid';
                    $paidAmount = 0;
                    $paidAt = null;
                } else {
                    $invoiceStatus = 'partially_paid';
                    $paidAmount = round($orderTotal * (mt_rand(30, 70) / 100), 2);
                    $paidAt = null;
                }
            }

            $invoice = Invoice::create([
                'order_id'       => $order->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'total_amount'   => $orderTotal,
                'paid_amount'    => $paidAmount,
                'status'         => $invoiceStatus,
                'payment_method' => $paymentMethod,
                'issued_at'      => $issuedAt,
                'paid_at'        => $paidAt,
                'created_at'     => $issuedAt,
                'updated_at'     => $paidAt ?? $issuedAt,
            ]);

            // --- Payment records ---
            if ($paidAmount > 0 && !$isGuest) {
                // Card payments recorded near the order date; COD near delivery
                $paymentDate = $paymentMethod === 'card' ? $orderDate->copy()->addHours(1) : ($paidAt ?? $issuedAt->copy()->addDays(mt_rand(1, 5)));
                if ($paymentDate->isFuture()) $paymentDate = now()->subHours(mt_rand(1, 24));

                Payment::create([
                    'order_id'       => $order->id,
                    'invoice_id'     => $invoice->id,
                    'amount'         => $paidAmount,
                    'currency'       => 'MAD',
                    'payment_method' => $paymentMethod,
                    'payment_type'   => $paidAmount === $orderTotal ? 'full' : 'custom',
                    'status'         => $isRefunded ? 'refunded' : 'paid',
                    'paid_at'        => $paymentDate,
                    'created_at'     => $paymentDate,
                    'updated_at'     => $paymentDate,
                ]);
            }

            // Partial payments: second payment
            if ($invoiceStatus === 'partially_paid' && $paidAmount < $orderTotal && mt_rand(0, 1) && !$isGuest) {
                $secondPayment = round(($orderTotal - $paidAmount) * mt_rand(50, 100) / 100, 2);
                if ($secondPayment > 0) {
                    $secondDate = ($paidAt ?? $issuedAt)->copy()->addDays(mt_rand(3, 10));
                    if ($secondDate->isFuture()) $secondDate = now()->subHours(mt_rand(1, 48));
                    Payment::create([
                        'order_id'       => $order->id,
                        'invoice_id'     => $invoice->id,
                        'amount'         => $secondPayment,
                        'currency'       => 'MAD',
                        'payment_method' => $paymentMethod,
                        'payment_type'   => 'partial_50',
                        'status'         => 'paid',
                        'paid_at'        => $secondDate,
                        'created_at'     => $secondDate,
                        'updated_at'     => $secondDate,
                    ]);
                    $invoice->paid_amount += $secondPayment;
                    $invoice->recalculateStatus();
                    $invoice->save();
                }
            }

            // --- Revenue for delivered orders ---
            if ($status === 'delivered' && !$isRefunded) {
                $revDate = $paidAt ?? $issuedAt;
                Revenue::create([
                    'order_id'     => $order->id,
                    'amount'       => $orderTotal,
                    'source'       => 'order',
                    'reference'    => $order->order_number,
                    'note'         => 'Revenue from order ' . $order->order_number,
                    'revenue_date' => $revDate,
                    'created_at'   => $revDate,
                    'updated_at'   => $revDate,
                ]);
            }

            $ordersCreated++;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();

        // Apply stock deductions to variant stocks
        foreach ($productRecords as $pr) {
            // Decrement product stock based on sold quantities
            $soldQty = $stockDeductions[$pr->id] ?? 0;
            if ($soldQty > 0) {
                $pr->decrement('stock', min($soldQty, $pr->stock));
            }

            // Also adjust variant stocks
            $variants = $pr->variants;
            if ($variants->isNotEmpty()) {
                $totalVariantStock = $variants->sum('stock');
                if ($totalVariantStock > 0) {
                    foreach ($variants as $variant) {
                        $variantProportion = $variant->stock / max(1, $totalVariantStock);
                        $variantSoldQty = (int) round($soldQty * $variantProportion);
                        // Deduct more from default variant (most popular)
                        if ($variant->is_default) {
                            $variantSoldQty = (int) round($variantSoldQty * 1.3);
                        }
                        $variant->decrement('stock', min($variantSoldQty, $variant->stock));
                    }
                }
            }
        }

        $this->command->info('  ✅ ' . Order::count() . ' orders with items, invoices & payments created');
        $this->command->info('  📉 Stock adjusted for ' . count($stockDeductions) . ' products based on completed orders');

        // ========================================================================
        // 9. REVIEWS — For delivered orders, customers leave reviews
        // ========================================================================
        $deliveredOrders = Order::where('status', 'delivered')
            ->whereDoesntHave('revenue') // Only refunds excluded
            ->orWhereHas('revenue')
            ->where('status', 'delivered')
            ->get();

        $reviewCount = 0;
        $reviewTexts = [
            'Excellent product! Exceeded my expectations. Highly recommended.',
            'Great quality for the price. Very satisfied with my purchase.',
            'Good product, fast shipping. Would buy again.',
            'Decent quality, matches the description.',
            'Perfect! Exactly what I was looking for.',
            'Very happy with this purchase. The quality is outstanding.',
            'Good value for money. Shipping was quick.',
            'Amazing quality! My second time buying this product.',
            'Solid product, works as advertised.',
            'Love it! Great customer service too.',
            'The product is wonderful, but shipping took a bit longer than expected. Worth the wait though!',
            'Really impressed with the build quality. Five stars.',
            'Bought as a gift and they loved it!',
        ];

        foreach ($deliveredOrders->take((int) ($deliveredOrders->count() * 0.55)) as $order) {
            $items = $order->items;
            if ($items->isEmpty() || !$order->user_id) continue;

            // 60% chance to review at least one item
            if (mt_rand(1, 100) > 60) continue;

            // Review 1-2 items from the order
            $itemsToReview = $items->take(mt_rand(1, min(2, $items->count())));
            foreach ($itemsToReview as $item) {
                // Check if this user already reviewed this product
                $existingReview = Review::where('user_id', $order->user_id)
                    ->where('product_id', $item->product_id)
                    ->exists();
                if ($existingReview) continue;

                $rating = match (true) {
                    mt_rand(1, 100) <= 5  => 3,  // 5% rate 3 stars
                    mt_rand(1, 100) <= 15 => 4,  // 15% rate 4 stars
                    default => 5,                // 80% rate 5 stars
                };
                $reviewDate = $order->created_at->copy()->addDays(mt_rand(3, 20));

                Review::create([
                    'user_id'    => $order->user_id,
                    'product_id' => $item->product_id,
                    'order_id'   => $order->id,
                    'rating'     => $rating,
                    'comment'    => mt_rand(0, 2) ? $reviewTexts[array_rand($reviewTexts)] : null,
                    'created_at' => $reviewDate,
                    'updated_at' => $reviewDate,
                ]);
                $reviewCount++;
            }
        }
        $this->command->info('  ✅ ' . $reviewCount . ' reviews created');

        // ========================================================================
        // 10. EXPENSES — Monthly recurring expenses + occasional ones
        // ========================================================================
        $expenseCategories = [
            'rent', 'utilities', 'salaries', 'marketing', 'shipping',
            'supplies', 'maintenance', 'software', 'insurance', 'taxes',
        ];

        $expenseTemplates = [
            'rent'       => ['Office Rent', 'Warehouse Rent'],
            'utilities'  => ['Electricity Bill', 'Water Bill', 'Internet Service', 'Phone Bills'],
            'salaries'   => ['Employee Salaries', 'Contractor Payment', 'Bonuses'],
            'marketing'  => ['Facebook Ads', 'Google Ads', 'Influencer Campaign', 'Email Marketing', 'Print Ad'],
            'shipping'   => ['Courier Fees', 'Packaging Supplies', 'International Shipping', 'Last-Mile Delivery'],
            'supplies'   => ['Office Supplies', 'Cleaning Supplies', 'Stationery'],
            'maintenance'=> ['Equipment Repair', 'AC Maintenance', 'Plumbing', 'Electrical Work'],
            'software'   => ['SaaS Subscriptions', 'Hosting Fees', 'Domain Renewal'],
            'insurance'  => ['Business Insurance', 'Health Insurance', 'Liability Insurance'],
            'taxes'      => ['Corporate Tax', 'VAT Payment', 'Property Tax'],
        ];

        // Monthly recurring expenses
        $recurringMonthly = [
            'rent'      => 12000,
            'salaries'  => [28000, 35000],
            'software'  => [1500, 2500],
            'insurance' => [3000, 4500],
            'utilities' => [1200, 2800],
        ];

        $expenseCount = 0;
        $expenseBar = $this->command->getOutput()->createProgressBar(200);
        $expenseBar->setMessage('Creating expenses...');
        $expenseBar->start();

        for ($m = 0; $m < 12; $m++) {
            $baseDate = $earliestDate->copy()->addMonths($m);
            $daysInMonth = (int) $baseDate->copy()->endOfMonth()->format('d');

            // Monthly recurring
            foreach ($recurringMonthly as $cat => $amount) {
                $day = mt_rand(1, min(5, $daysInMonth));
                $expDate = $baseDate->copy()->setDay($day);
                $label = $expenseTemplates[$cat][array_rand($expenseTemplates[$cat])];
                $finalAmount = is_array($amount) ? round(mt_rand($amount[0], $amount[1]) / 100, 2) * 100 : $amount;

                Expense::create([
                    'title'        => $label,
                    'amount'       => $finalAmount,
                    'category'     => $cat,
                    'description'  => $label . ' - ' . $expDate->format('F Y'),
                    'expense_date' => $expDate,
                    'created_by'   => $admin->id,
                    'created_at'   => $expDate,
                    'updated_at'   => $expDate,
                ]);
                $expenseCount++;
                $expenseBar->advance();
            }

            // Variable expenses (2-5 per month)
            $variableCount = mt_rand(2, 5);
            for ($v = 0; $v < $variableCount; $v++) {
                $cat = $expenseCategories[array_rand($expenseCategories)];
                // Skip categories already handled monthly
                if (in_array($cat, ['rent', 'salaries', 'software', 'insurance'])) {
                    $nonRecurring = array_diff($expenseCategories, ['rent', 'salaries', 'software', 'insurance']);
                    $cat = $nonRecurring[array_rand($nonRecurring)];
                }

                $title = $expenseTemplates[$cat][array_rand($expenseTemplates[$cat])];
                $day = mt_rand(5, max(6, $daysInMonth - 5));
                $expDate = $baseDate->copy()->setDay($day);

                $amount = match ($cat) {
                    'marketing'   => round(mt_rand(500, 8000) / 50, 2) * 50,
                    'shipping'    => round(mt_rand(200, 4000) / 50, 2) * 50,
                    'supplies'    => round(mt_rand(100, 2000) / 50, 2) * 50,
                    'maintenance' => round(mt_rand(300, 5000) / 50, 2) * 50,
                    'taxes'       => round(mt_rand(3000, 15000) / 100, 2) * 100,
                    default       => round(mt_rand(100, 5000) / 50, 2) * 50,
                };

                Expense::create([
                    'title'        => $title,
                    'amount'       => $amount,
                    'category'     => $cat,
                    'description'  => $title . ' - ' . $expDate->format('F Y'),
                    'expense_date' => $expDate,
                    'created_by'   => $admin->id,
                    'created_at'   => $expDate,
                    'updated_at'   => $expDate,
                ]);
                $expenseCount++;
                $expenseBar->advance();
            }
        }

        $expenseBar->finish();
        $this->command->newLine();
        $this->command->info('  ✅ ' . Expense::count() . ' expenses created');

        // ========================================================================
        // 11. CARTS — Active + Abandoned
        // ========================================================================
        // ~15 active carts (recent)
        $activeCartCount = min(15, count($clients));
        $usedClients = [];
        for ($c = 0; $c < $activeCartCount; $c++) {
            $client = $clients[array_rand($clients)];
            if (in_array($client->id, $usedClients)) continue;
            $usedClients[] = $client->id;

            $product = $productRecords[array_rand($productRecords)];
            $existing = Cart::where('user_id', $client->id)->where('product_id', $product->id)->where('status', 'active')->first();
            if (!$existing) {
                Cart::create([
                    'user_id'    => $client->id,
                    'product_id' => $product->id,
                    'variant_id' => $product->defaultVariant?->id,
                    'quantity'   => mt_rand(1, 2),
                    'status'     => 'active',
                    'created_at' => now()->subHours(mt_rand(1, 48)),
                    'updated_at' => now()->subHours(mt_rand(1, 48)),
                ]);
            }
        }

        // ~8 abandoned carts (3-10 days old)
        for ($c = 0; $c < 8; $c++) {
            $client = $clients[array_rand($clients)];
            $product = $productRecords[array_rand($productRecords)];
            $existing = Cart::where('user_id', $client->id)->where('product_id', $product->id)->where('status', 'abandoned')->first();
            if (!$existing) {
                $abandonDate = now()->subDays(mt_rand(3, 10));
                Cart::create([
                    'user_id'    => $client->id,
                    'product_id' => $product->id,
                    'variant_id' => $product->defaultVariant?->id,
                    'quantity'   => mt_rand(1, 3),
                    'status'     => 'abandoned',
                    'created_at' => $abandonDate,
                    'updated_at' => $abandonDate->copy()->addHours(mt_rand(1, 12)),
                ]);
            }
        }

        $this->command->info('  ✅ ' . Cart::count() . ' cart items (active + abandoned)');

        // ========================================================================
        // 12. PAYPAL SETTINGS (sandbox mode, pre-configured)
        // ========================================================================
        \App\Models\Setting::setValue('paypal_enabled', '0');
        \App\Models\Setting::setValue('paypal_mode', 'sandbox');
        \App\Models\Setting::setValue('paypal_client_id', 'YOUR_SANDBOX_CLIENT_ID');
        \App\Models\Setting::setValue('paypal_client_secret', 'YOUR_SANDBOX_CLIENT_SECRET');
        \App\Models\Setting::setValue('paypal_webhook_id', '');
        $this->command->info('  ✅ PayPal sandbox settings created — replace credentials in admin panel');

        // ========================================================================
        // SUMMARY
        // ========================================================================
        $elapsed = round(microtime(true) - $start, 2);
        $this->command->newLine(2);
        $this->command->info('╔══════════════════════════════════════════════════════╗');
        $this->command->info('║          ✅  REALISTIC SEEDING COMPLETE!            ║');
        $this->command->info('╚══════════════════════════════════════════════════════╝');
        $this->command->newLine();
        $this->command->info('  👤  Admin:    amin@example.com / password');
        $this->command->info('  👥  Clients:  client1-30@example.com / password');
        $this->command->info('  ⏱   Took:    ' . $elapsed . 's');
        $this->command->newLine();
        $this->command->info('  📊  Stats:');
        $this->command->info('    ' . User::count()          . ' users');
        $this->command->info('    ' . Brand::count()         . ' brands');
        $this->command->info('    ' . Category::count()      . ' categories');
        $this->command->info('    ' . Product::count()       . ' products');
        $this->command->info('    ' . ProductVariant::count() . ' product variants');
        $this->command->info('    ' . ProductImage::count()  . ' product images');
        $this->command->info('    ' . Address::count()       . ' addresses');
        $this->command->info('    ' . Order::count()         . ' orders');
        $this->command->info('    ' . OrderItem::count()     . ' order items');
        $this->command->info('    ' . Invoice::count()       . ' invoices');
        $this->command->info('    ' . Payment::count()       . ' payments');
        $this->command->info('    ' . Revenue::count()       . ' revenue records');
        $this->command->info('    ' . Expense::count()       . ' expenses');
        $this->command->info('    ' . Review::count()        . ' reviews');
        $this->command->info('    ' . Cart::count()          . ' cart items');
        $this->command->newLine();
        $this->command->info('  🎉  Dashboard has full analytics data!');
    }
}
