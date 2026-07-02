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
use App\Models\HomepageFeature;
use App\Models\Promotion;
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
            default   => 'delivered',    // default to delivered
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
            'avatar'     => 'https://picsum.photos/seed/admin-system/200/200',
        ]);
        $this->command->info('  ✅ Admin created');

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
                    'avatar'     => 'https://picsum.photos/seed/' . $profile['first_name'] . $profile['last_name'] . mt_rand(1, 999) . '/200/200',
                ]);
                $client->segment = $seg['label'];
                $client->orderRange = $seg['orders'];
                $clients[] = $client;
            }
        }

        $allUsers = [$admin, ...$clients];
        $this->command->info('  ✅ Admin + ' . count($clients) . ' clients created');

        // ========================================================================
        // 3. BRANDS — Real-world brands
        // ========================================================================
        $brandsData = [
            // Electronics
            ['name' => 'Apple',     'slug' => 'apple',     'description' => 'Premium consumer electronics, software, and services'],
            ['name' => 'Samsung',   'slug' => 'samsung',   'description' => 'Global leader in electronics, smartphones, and home appliances'],
            ['name' => 'Sony',      'slug' => 'sony',      'description' => 'Japanese multinational conglomerate specializing in electronics and entertainment'],
            // Clothing
            ['name' => 'Nike',      'slug' => 'nike',      'description' => 'American multinational sportswear and athletic footwear corporation'],
            ['name' => 'Adidas',    'slug' => 'adidas',    'description' => 'German sportswear and lifestyle brand with iconic three-stripe design'],
            ['name' => "Levi's",     'slug' => 'levis',     'description' => 'American denim and casual wear pioneer since 1853'],
            // Home & Garden
            ['name' => 'IKEA',      'slug' => 'ikea',      'description' => 'Swedish multinational furniture and home accessories retailer'],
            ['name' => 'Philips',   'slug' => 'philips',   'description' => 'Dutch multinational conglomerate specializing in electronics and healthcare'],
            ['name' => 'Tefal',     'slug' => 'tefal',     'description' => 'French cookware and kitchen appliance brand founded in 1956'],
            // Sports & Outdoors
            ['name' => 'Decathlon', 'slug' => 'decathlon', 'description' => 'French sporting goods retailer offering affordable gear for all sports'],
            ['name' => 'Wilson',    'slug' => 'wilson',    'description' => 'American sports equipment manufacturer specializing in ball sports'],
            ['name' => 'Coleman',   'slug' => 'coleman',   'description' => 'American outdoor recreation brand known for camping gear'],
            // Beauty & Health
            ['name' => "L'Oréal",   'slug' => 'loreal',    'description' => 'French personal care company — world\'s largest cosmetics manufacturer'],
            ['name' => 'Nivea',     'slug' => 'nivea',     'description' => 'German personal care brand known for skincare products'],
            ['name' => 'CeraVe',    'slug' => 'cerave',    'description' => 'American dermatologist-developed skincare brand'],
            // Books
            ['name' => 'Penguin',        'slug' => 'penguin',        'description' => 'British publishing house — one of the largest English-language publishers'],
            ['name' => "O'Reilly",       'slug' => 'oreilly',        'description' => 'American publishing company specializing in technology and programming books'],
            ['name' => 'HarperCollins',  'slug' => 'harpercollins',  'description' => 'One of the world\'s largest publishing companies'],
        ];
        foreach ($brandsData as $b) {
            Brand::create($b);
        }
        $brandByName = Brand::pluck('id', 'name')->toArray();
        $this->command->info('  ✅ ' . count($brandsData) . ' brands created');

        // ========================================================================
        // 4. CATEGORIES
        // ========================================================================
        $categoriesData = [
            ['name' => 'Electronics',      'slug' => 'electronics',      'description' => 'Smartphones, laptops, headphones, gaming, and tech accessories'],
            ['name' => 'Clothing',         'slug' => 'clothing',         'description' => 'Shoes, hoodies, jackets, jeans, and sportswear for men and women'],
            ['name' => 'Home & Garden',    'slug' => 'home-garden',      'description' => 'Furniture, kitchen appliances, lighting, and home improvement'],
            ['name' => 'Sports & Outdoors','slug' => 'sports-outdoors',  'description' => 'Fitness equipment, camping gear, sports balls, and outdoor accessories'],
            ['name' => 'Beauty & Health',  'slug' => 'beauty-health',    'description' => 'Skincare, haircare, makeup, and personal care products'],
            ['name' => 'Books',            'slug' => 'books',            'description' => 'Self-development, programming, finance, fiction, and educational books'],
        ];
        foreach ($categoriesData as $c) {
            Category::create($c);
        }
        $catByName = Category::pluck('id', 'name')->toArray();
        $this->command->info('  ✅ ' . count($categoriesData) . ' categories created');

        // ========================================================================
        // 5. PRODUCTS — Real-world products with accurate pricing
        // ========================================================================
        $productsData = [
            // ──────────── Electronics ────────────
            // Apple
            ['cat' => 'Electronics', 'brand' => 'Apple',   'name' => 'iPhone 16 Pro',                'slug' => 'iphone-16-pro',                'desc' => '6.3" Super Retina XDR display, A18 Pro chip, 48MP Fusion camera system, 5G, Titanium design, 256GB storage.',                              'price' => 14999.00, 'purchase' => 10500.00, 'stock' => 30,  'sku' => 'APL-IP16P-001', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'Apple',   'name' => 'MacBook Air M4',               'slug' => 'macbook-air-m4',               'desc' => '13.6" Liquid Retina display, Apple M4 chip, 16GB RAM, 512GB SSD, MagSafe, 18-hour battery life.',                                               'price' => 15999.00, 'purchase' => 11200.00, 'stock' => 20,  'sku' => 'APL-MBAM4-002', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'Apple',   'name' => 'AirPods Pro 2',                'slug' => 'airpods-pro-2',                'desc' => 'Active Noise Cancellation, Adaptive Audio, Personalized Spatial Audio, USB-C MagSafe charging case.',                                           'price' => 2999.00,  'purchase' => 2000.00,  'stock' => 50,  'sku' => 'APL-APP2-003', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'Apple',   'name' => 'Apple Watch Series 10',         'slug' => 'apple-watch-series-10',        'desc' => 'Largest display, thinnest design, sleep apnea detection, Vitals app, 18-hour battery.',                                                           'price' => 5999.00,  'purchase' => 4100.00,  'stock' => 25,  'sku' => 'APL-AWS10-004', 'featured' => true],
            // Samsung
            ['cat' => 'Electronics', 'brand' => 'Samsung', 'name' => 'Galaxy S25 Ultra',              'slug' => 'galaxy-s25-ultra',             'desc' => '6.9" Dynamic AMOLED 2X, Snapdragon 8 Elite, 200MP camera, S Pen, 5000mAh battery.',                                                              'price' => 13999.00, 'purchase' => 9800.00,  'stock' => 28,  'sku' => 'SAM-GS25U-005', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'Samsung', 'name' => 'Galaxy Tab S10',                'slug' => 'galaxy-tab-s10',               'desc' => '12.4" Dynamic AMOLED 2X 120Hz, MediaTek Dimensity 9300+, 12GB RAM, 256GB, S Pen included, 10090mAh battery.',                                     'price' => 10999.00, 'purchase' => 7600.00,  'stock' => 18,  'sku' => 'SAM-GTS10-006', 'featured' => false],
            ['cat' => 'Electronics', 'brand' => 'Samsung', 'name' => 'Galaxy Buds 3 Pro',             'slug' => 'galaxy-buds-3-pro',            'desc' => 'Intelligent ANC, Blade Light design, dual amp, 360 Audio, IP57 water resistant, up to 26h battery.',                                              'price' => 2999.00,  'purchase' => 2000.00,  'stock' => 40,  'sku' => 'SAM-GB3P-007', 'featured' => false],
            ['cat' => 'Electronics', 'brand' => 'Samsung', 'name' => 'Samsung Smart Monitor',          'slug' => 'samsung-smart-monitor',        'desc' => '27" 4K UHD Smart Monitor M8, USB-C 65W charging, built-in TV apps, SlimFit webcam, IoT sensors.',                                                 'price' => 6999.00,  'purchase' => 4800.00,  'stock' => 15,  'sku' => 'SAM-SSM-008', 'featured' => false],
            // Sony
            ['cat' => 'Electronics', 'brand' => 'Sony',    'name' => 'WH-1000XM6 Headphones',         'slug' => 'wh-1000xm6-headphones',        'desc' => 'Industry-leading ANC, 40h battery, Hi-Res Audio, LDAC, Multipoint connection, foldable design.',                                                 'price' => 4499.00,  'purchase' => 3000.00,  'stock' => 35,  'sku' => 'SNY-WH6-009', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'Sony',    'name' => 'PlayStation 5 Slim',            'slug' => 'playstation-5-slim',           'desc' => 'Next-gen gaming console with 1TB SSD, 4K Blu-ray, DualSense controller, slim design.',                                                            'price' => 7999.00,  'purchase' => 5600.00,  'stock' => 12,  'sku' => 'SNY-PS5-010', 'featured' => true],
            ['cat' => 'Electronics', 'brand' => 'Sony',    'name' => 'Sony Bravia 4K TV',             'slug' => 'sony-bravia-4k-tv',            'desc' => '55" BRAVIA 7 Mini LED 4K HDR, XR Processor, Google TV, Dolby Atmos, Acoustic Multi-Audio.',                                                       'price' => 15999.00, 'purchase' => 11000.00, 'stock' => 10,  'sku' => 'SNY-BTV-011', 'featured' => false],
            ['cat' => 'Electronics', 'brand' => 'Sony',    'name' => 'Sony Alpha Camera',             'slug' => 'sony-alpha-camera',            'desc' => 'Alpha 7 IV full-frame mirrorless, 33MP, 4K 60p video, Real-time Eye AF, 5-axis stabilization.',                                                  'price' => 29999.00, 'purchase' => 21000.00, 'stock' => 8,   'sku' => 'SNY-ALPHA-012', 'featured' => false],

            // ──────────── Clothing ────────────
            // Nike
            ['cat' => 'Clothing',   'brand' => 'Nike',    'name' => 'Air Max Sneakers',              'slug' => 'air-max-sneakers',             'desc' => 'Air Max 270 iconic sneakers with Max Air unit, breathable mesh upper, rubber outsole, comfortable all-day wear.',                                  'price' => 1499.00,  'purchase' => 820.00,   'stock' => 60,  'sku' => 'NKE-AMS-013', 'featured' => true],
            ['cat' => 'Clothing',   'brand' => 'Nike',    'name' => 'Sports Hoodie',                 'slug' => 'sports-hoodie',                'desc' => 'Nike Sportswear Club Fleece hoodie, brushed fleece, adjustable drawstring hood, kangaroo pocket, ribbed cuffs and hem.',                           'price' => 999.00,   'purchase' => 520.00,   'stock' => 75,  'sku' => 'NKE-SH-014', 'featured' => false],
            ['cat' => 'Clothing',   'brand' => 'Nike',    'name' => 'Running Shorts',                'slug' => 'running-shorts',               'desc' => 'Nike Dri-FIT running shorts, sweat-wicking fabric, inner brief, zippered pocket, 5" inseam breathable design.',                                    'price' => 599.00,   'purchase' => 310.00,   'stock' => 90,  'sku' => 'NKE-RS-015', 'featured' => false],
            ['cat' => 'Clothing',   'brand' => 'Nike',    'name' => 'Training T-Shirt',              'slug' => 'training-tshirt',              'desc' => 'Nike Dri-FIT training t-shirt, ultra-soft jersey fabric, flat seams, moisture-wicking, regular fit casual style.',                                  'price' => 449.00,   'purchase' => 230.00,   'stock' => 120, 'sku' => 'NKE-TTS-016', 'featured' => false],
            // Adidas
            ['cat' => 'Clothing',   'brand' => 'Adidas',  'name' => 'Ultraboost Shoes',              'slug' => 'ultraboost-shoes',             'desc' => 'Ultraboost 5X running shoes with adidas PRIMEKNIT+ upper, BOOST midsole, Continental rubber outsole, energy return.',                                'price' => 1899.00,  'purchase' => 1100.00,  'stock' => 45,  'sku' => 'ADD-UB-017', 'featured' => true],
            ['cat' => 'Clothing',   'brand' => 'Adidas',  'name' => 'Essentials Hoodie',             'slug' => 'essentials-hoodie',            'desc' => 'Adidas Essentials French Terry hoodie, 3-Stripes detail, adjustable hood, ribbed cuffs, oversized relaxed fit.',                                   'price' => 1099.00,  'purchase' => 580.00,   'stock' => 55,  'sku' => 'ADD-EH-018', 'featured' => false],
            ['cat' => 'Clothing',   'brand' => 'Adidas',  'name' => 'Performance Jacket',            'slug' => 'performance-jacket',           'desc' => 'Adidas own the run jacket, windproof, water-repellent, reflective details, zip pockets, lightweight packable design.',                               'price' => 1499.00,  'purchase' => 850.00,   'stock' => 30,  'sku' => 'ADD-PJ-019', 'featured' => false],
            ['cat' => 'Clothing',   'brand' => 'Adidas',  'name' => 'Sports Cap',                    'slug' => 'sports-cap',                   'desc' => 'Adidas Performance AEROREADY cap, sweat-wicking, curved brim, adjustable snapback closure, embroidered logo.',                                      'price' => 299.00,   'purchase' => 140.00,   'stock' => 100, 'sku' => 'ADD-SC-020', 'featured' => false],
            // Levi's
            ['cat' => 'Clothing',   'brand' => "Levi's",  'name' => 'Slim Fit Jeans',                'slug' => 'slim-fit-jeans',               'desc' => 'Levi\'s 511 Slim Fit Jeans, stretch denim, mid-rise waist, slim leg opening, classic five-pocket styling, available in various washes.',            'price' => 899.00,   'purchase' => 480.00,   'stock' => 65,  'sku' => 'LEV-SFJ-021', 'featured' => true],
            ['cat' => 'Clothing',   'brand' => "Levi's",  'name' => "Levi's Denim Jacket",            'slug' => 'levis-denim-jacket',           'desc' => 'Levi\'s Original Trucker Jacket, 100% cotton denim, button-front, chest pockets, adjustable waist tabs, iconic design since 1962.',               'price' => 1499.00,  'purchase' => 820.00,   'stock' => 35,  'sku' => 'LEV-DJ-022', 'featured' => false],
            ['cat' => 'Clothing',   'brand' => "Levi's",  'name' => 'Cotton Shirt',                  'slug' => 'cotton-shirt',                 'desc' => 'Levi\'s Classic Fit Oxford shirt, 100% cotton, button-down collar, chest pocket, adjustable cuffs, modern regular fit.',                          'price' => 799.00,   'purchase' => 420.00,   'stock' => 45,  'sku' => 'LEV-CS-023', 'featured' => false],
            ['cat' => 'Clothing',   'brand' => "Levi's",  'name' => 'Casual Shorts',                 'slug' => 'casual-shorts',                'desc' => 'Levi\'s 402 Comfort Chino Shorts, stretch cotton twill, mid-rise, 9" inseam, chino style pockets, comfortable everyday wear.',                      'price' => 649.00,   'purchase' => 340.00,   'stock' => 50,  'sku' => 'LEV-CASH-024', 'featured' => false],

            // ──────────── Home & Garden ────────────
            // IKEA
            ['cat' => 'Home & Garden','brand' => 'IKEA',  'name' => 'Modern Coffee Table',            'slug' => 'modern-coffee-table',          'desc' => 'LACK modern coffee table, clean Scandinavian design, lightweight particleboard, white finish, easy assembly, 90x55cm.',                             'price' => 899.00,   'purchase' => 400.00,   'stock' => 25,  'sku' => 'IKE-LCT-025', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'IKEA',  'name' => 'Dining Chair',                   'slug' => 'dining-chair',                 'desc' => 'INGOLF dining chair, solid pine wood, classic traditional design, comfortable curved backrest, sturdy construction, natural finish.',               'price' => 1299.00,  'purchase' => 650.00,   'stock' => 40,  'sku' => 'IKE-DC-026', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'IKEA',  'name' => 'Wooden Bookshelf',               'slug' => 'wooden-bookshelf',             'desc' => 'KALLAX modular shelf unit, 2x4 cube configuration, sturdy particleboard, versatile storage, 147x147cm, fits any room decor.',                       'price' => 2499.00,  'purchase' => 1300.00,  'stock' => 15,  'sku' => 'IKE-WB-027', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'IKEA',  'name' => 'Bedside Table',                  'slug' => 'bedside-table',                'desc' => 'HEMNES bedside table, solid pine wood, single drawer with pull-out stop, open shelf, classic design, 40x35cm, white stain.',                         'price' => 999.00,   'purchase' => 500.00,   'stock' => 30,  'sku' => 'IKE-BST-028', 'featured' => false],
            // Philips
            ['cat' => 'Home & Garden','brand' => 'Philips','name' => 'Smart LED Lamp',                'slug' => 'smart-led-lamp',               'desc' => 'Philips Hue Smart LED desk lamp, 16 million colors, adjustable brightness, works with Alexa/Google, wireless dimming, energy efficient.',           'price' => 1299.00,  'purchase' => 720.00,   'stock' => 35,  'sku' => 'PHI-SLL-029', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'Philips','name' => 'Air Purifier',                  'slug' => 'air-purifier',                 'desc' => 'Philips Series 3000 Air Purifier, HEPA filter, removes 99.97% pollutants, covers 40m², quiet mode, real-time air quality display.',               'price' => 3499.00,  'purchase' => 2100.00,  'stock' => 12,  'sku' => 'PHI-AP-030', 'featured' => true],
            ['cat' => 'Home & Garden','brand' => 'Philips','name' => 'Steam Iron',                    'slug' => 'steam-iron',                   'desc' => 'Philips GC8335 Steam Iron, 3000W, continuous steam, ceramic soleplate, anti-calc system, automatic shut-off, 400ml water tank.',                    'price' => 699.00,   'purchase' => 350.00,   'stock' => 40,  'sku' => 'PHI-SI-031', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'Philips','name' => 'Vacuum Cleaner',                'slug' => 'vacuum-cleaner',               'desc' => 'Philips PowerPro Compact bagless vacuum, 900W, Cyclone technology, HEPA filter, 1.5L dust capacity, 8m reach, compact design.',                     'price' => 2499.00,  'purchase' => 1400.00,  'stock' => 18,  'sku' => 'PHI-VC-032', 'featured' => false],
            // Tefal
            ['cat' => 'Home & Garden','brand' => 'Tefal',  'name' => 'Frying Pan Set',                'slug' => 'frying-pan-set',               'desc' => 'Tefal Ingenious 3-piece frying pan set (20, 24, 28cm), Titanium Excellence non-stick coating, Thermo-Spot heat indicator, dishwasher safe.',            'price' => 999.00,   'purchase' => 500.00,   'stock' => 35,  'sku' => 'TEF-FPS-033', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'Tefal',  'name' => 'Electric Kettle',               'slug' => 'electric-kettle',              'desc' => 'Tefal KI761D Essentials kettle, 1.7L capacity, 2400W, lime-scale filter, concealed element, auto shut-off, boil-dry protection.',                    'price' => 399.00,   'purchase' => 190.00,   'stock' => 55,  'sku' => 'TEF-EK-034', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'Tefal',  'name' => 'Rice Cooker',                   'slug' => 'rice-cooker',                  'desc' => 'Tefal RK802E Rice Cooker, 1.5L capacity, non-stick inner pot, keep-warm function, steaming basket included, 6 recipes, auto shut-off.',            'price' => 799.00,   'purchase' => 380.00,   'stock' => 22,  'sku' => 'TEF-RC-035', 'featured' => false],
            ['cat' => 'Home & Garden','brand' => 'Tefal',  'name' => 'Blender',                       'slug' => 'blender',                      'desc' => 'Tefal BL42QD blender, 1000W, 1.75L Tritan jug, 4-speed + pulse, Ice Crush function, detachable blade, self-cleaning mode.',                          'price' => 899.00,   'purchase' => 420.00,   'stock' => 28,  'sku' => 'TEF-BL-036', 'featured' => false],

            // ──────────── Sports & Outdoors ────────────
            // Decathlon
            ['cat' => 'Sports & Outdoors','brand' => 'Decathlon','name' => 'Yoga Mat',                'slug' => 'yoga-mat',                     'desc' => 'Domyos 10mm yoga mat, high-density foam, non-slip textured surface, moisture-resistant, includes carrying strap, 173x61cm.',                      'price' => 399.00,   'purchase' => 180.00,   'stock' => 70,  'sku' => 'DEC-YM-037', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Decathlon','name' => 'Resistance Bands',         'slug' => 'resistance-bands',             'desc' => 'Domyos set of 5 resistance bands, latex-free, 5-25kg levels, includes door anchor and carrying bag, compact portable workout kit.',                'price' => 249.00,   'purchase' => 110.00,   'stock' => 85,  'sku' => 'DEC-RB-038', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Decathlon','name' => 'Dumbbell Set',              'slug' => 'dumbbell-set',                 'desc' => 'Domyos 10kg pair adjustable dumbbells, neoprene-coated, ergonomic grip, hexagon shape, space-saving storage stand included.',                       'price' => 999.00,   'purchase' => 520.00,   'stock' => 30,  'sku' => 'DEC-DS-039', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Decathlon','name' => 'Hiking Backpack',           'slug' => 'hiking-backpack',              'desc' => 'Quechua MH500 50L hiking backpack, waterproof cover, padded back system, chest/waist straps, multiple compartments, 2-year warranty.',              'price' => 799.00,   'purchase' => 420.00,   'stock' => 25,  'sku' => 'DEC-HB-040', 'featured' => false],
            // Wilson
            ['cat' => 'Sports & Outdoors','brand' => 'Wilson','name' => 'Tennis Racket',               'slug' => 'tennis-racket',                'desc' => 'Wilson Clash 100 v2 tennis racket, 295g, 100in² head, comfortable feel, spin-friendly, graphite frame, includes cover.',                             'price' => 2499.00,  'purchase' => 1500.00,  'stock' => 15,  'sku' => 'WIL-TR-041', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Wilson','name' => 'Basketball',                  'slug' => 'basketball',                   'desc' => 'Wilson NBA Authentic basketball, full-grain leather, advanced channel design, premium indoor/outdoor use, official size 7.',                         'price' => 699.00,   'purchase' => 380.00,   'stock' => 40,  'sku' => 'WIL-BB-042', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Wilson','name' => 'Football',                    'slug' => 'football',                     'desc' => 'Wilson NFL Super G football, composite leather, tacky grip, durable cover, official size, 100% natural rubber bladder.',                            'price' => 549.00,   'purchase' => 290.00,   'stock' => 45,  'sku' => 'WIL-FB-043', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Wilson','name' => 'Volleyball',                  'slug' => 'volleyball',                   'desc' => 'Wilson AVP game volleyball, composite leather cover, soft touch, butyl bladder, official size & weight, great for beach and court.',                'price' => 499.00,   'purchase' => 260.00,   'stock' => 35,  'sku' => 'WIL-VB-044', 'featured' => false],
            // Coleman
            ['cat' => 'Sports & Outdoors','brand' => 'Coleman','name' => 'Camping Tent',               'slug' => 'camping-tent',                 'desc' => 'Coleman Sundome 4-person tent, weatherproof, easy setup, 2-minute assembly, mesh windows, rainfly included, 4-season camping.',                     'price' => 2499.00,  'purchase' => 1400.00,  'stock' => 12,  'sku' => 'COL-CT-045', 'featured' => true],
            ['cat' => 'Sports & Outdoors','brand' => 'Coleman','name' => 'Sleeping Bag',               'slug' => 'sleeping-bag',                 'desc' => 'Coleman 0°F mummy sleeping bag, Thermolock draft tube, hooded design, polyester shell, zipper baffle, 5-year warranty, compact carry.',             'price' => 1299.00,  'purchase' => 680.00,   'stock' => 20,  'sku' => 'COL-SB-046', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Coleman','name' => 'Portable Cooler',             'slug' => 'portable-cooler',              'desc' => 'Coleman 54L wheeled cooler, Xtreme 5-day ice retention, rugged wheels, cup holders, telescopic handle, leak-resistant drain.',                     'price' => 3999.00,  'purchase' => 2300.00,  'stock' => 10,  'sku' => 'COL-PC-047', 'featured' => false],
            ['cat' => 'Sports & Outdoors','brand' => 'Coleman','name' => 'Camping Chair',              'slug' => 'camping-chair',                'desc' => 'Coleman oversized camping chair with cooler, quad support, cup holder, padded armrests, 150kg capacity, carry bag, mesh back.',                    'price' => 999.00,   'purchase' => 520.00,   'stock' => 35,  'sku' => 'COL-CC-048', 'featured' => false],

            // ──────────── Beauty & Health ────────────
            // L'Oréal
            ['cat' => 'Beauty & Health','brand' => "L'Oréal",'name' => 'Face Cleanser',               'slug' => 'face-cleanser',                'desc' => 'L\'Oréal Paris Revitalift 3.5% Glycolic Acid cleanser, dermatologist tested, removes impurities, brightens skin, 200ml daily use.',                 'price' => 199.00,   'purchase' => 80.00,    'stock' => 80,  'sku' => 'LOR-FC-049', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => "L'Oréal",'name' => 'Vitamin C Serum',              'slug' => 'vitamin-c-serum',              'desc' => 'L\'Oréal Paris Revitalift 12% Vitamin C serum, anti-aging, brightening treatment, fine lines reduction, 30ml concentrated formula.',                'price' => 299.00,   'purchase' => 140.00,   'stock' => 60,  'sku' => 'LOR-VCS-050', 'featured' => true],
            ['cat' => 'Beauty & Health','brand' => "L'Oréal",'name' => 'Night Cream',                  'slug' => 'night-cream',                  'desc' => 'L\'Oréal Paris Revitalift Laser Renew night cream, 0.3% Retinol, vitamin B3, Pro-Retinol, firming moisturizer, 50ml anti-wrinkle treatment.',         'price' => 349.00,   'purchase' => 170.00,   'stock' => 45,  'sku' => 'LOR-NC-051', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => "L'Oréal",'name' => 'Shampoo',                      'slug' => 'shampoo',                      'desc' => 'L\'Oréal Paris Elvive Total Repair 5 shampoo, repairing formula, damage control, enriched with protein and ceramide, 400ml.',                       'price' => 149.00,   'purchase' => 60.00,    'stock' => 100, 'sku' => 'LOR-SH-052', 'featured' => false],
            // Nivea
            ['cat' => 'Beauty & Health','brand' => 'Nivea',  'name' => 'Body Lotion',                  'slug' => 'body-lotion',                  'desc' => 'Nivea Essentially Enriched body lotion, deep moisture, almond oil formula, 48h hydration, non-greasy, 400ml pump bottle for dry skin.',              'price' => 149.00,   'purchase' => 60.00,    'stock' => 90,  'sku' => 'NIV-BL-053', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => 'Nivea',  'name' => 'Face Moisturizer',             'slug' => 'face-moisturizer',             'desc' => 'Nivea Soft light face moisturizer, Vitamin E, jojoba oil, lightweight formula, instantly soft, all-day hydration, 200ml versatile cream.',          'price' => 119.00,   'purchase' => 45.00,    'stock' => 100, 'sku' => 'NIV-FM-054', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => 'Nivea',  'name' => 'Deodorant',                    'slug' => 'deodorant',                    'desc' => 'Nivea Men Silver Protect deodorant spray, 72h protection, anti-white marks, refreshing scent, alcohol-free formula, 200ml.',                       'price' => 89.00,    'purchase' => 35.00,    'stock' => 130, 'sku' => 'NIV-DEO-055', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => 'Nivea',  'name' => 'Lip Balm',                     'slug' => 'lip-balm',                     'desc' => 'Nivea Lip Care Essential lip balm, shea butter & jojoba oil, SPF 15 sun protection, intensive moisture, 4.8g tube, 24h care.',                      'price' => 39.00,    'purchase' => 12.00,    'stock' => 200, 'sku' => 'NIV-LB-056', 'featured' => false],
            // CeraVe
            ['cat' => 'Beauty & Health','brand' => 'CeraVe', 'name' => 'Hydrating Cleanser',           'slug' => 'hydrating-cleanser',           'desc' => 'CeraVe Hydrating Facial Cleanser, ceramides & hyaluronic acid, non-comedogenic, fragrance-free, sensitive skin-friendly, 473ml daily formula.',        'price' => 249.00,   'purchase' => 110.00,   'stock' => 65,  'sku' => 'CRV-HC-057', 'featured' => true],
            ['cat' => 'Beauty & Health','brand' => 'CeraVe', 'name' => 'Moisturizing Cream',           'slug' => 'moisturizing-cream',           'desc' => 'CeraVe Moisturizing Cream daily cream, three essential ceramides, MVE delivery technology, 24h hydration, 539g tub, non-greasy.',                   'price' => 349.00,   'purchase' => 160.00,   'stock' => 50,  'sku' => 'CRV-MC-058', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => 'CeraVe', 'name' => 'Sunscreen SPF50',              'slug' => 'sunscreen-spf50',              'desc' => 'CeraVe AM Facial Moisturizing Lotion with SPF 50, broad spectrum protection, ceramides & niacinamide, oil-free, 89ml daily sunscreen.',              'price' => 219.00,   'purchase' => 95.00,    'stock' => 55,  'sku' => 'CRV-SS-059', 'featured' => false],
            ['cat' => 'Beauty & Health','brand' => 'CeraVe', 'name' => 'Facial Lotion',                'slug' => 'facial-lotion',                'desc' => 'CeraVe PM Facial Moisturizing Lotion, niacinamide & ceramides, overnight repair, non-comedogenic, 89ml lightweight night formula.',                  'price' => 199.00,   'purchase' => 85.00,    'stock' => 60,  'sku' => 'CRV-FL-060', 'featured' => false],

            // ──────────── Books ────────────
            // Penguin
            ['cat' => 'Books',      'brand' => 'Penguin',       'name' => 'Atomic Habits',              'slug' => 'atomic-habits',                'desc' => 'By James Clear. An easy and proven way to build good habits and break bad ones through tiny changes that deliver remarkable results.',               'price' => 249.00,   'purchase' => 110.00,   'stock' => 80,  'sku' => 'PGN-AHB-061', 'featured' => true],
            ['cat' => 'Books',      'brand' => 'Penguin',       'name' => 'Deep Work',                  'slug' => 'deep-work',                    'desc' => 'By Cal Newport. Rules for focused success in a distracted world. How to achieve deep focus and produce meaningful work in a hyper-connected era.',    'price' => 219.00,   'purchase' => 95.00,    'stock' => 45,  'sku' => 'PGN-DWK-062', 'featured' => false],
            ['cat' => 'Books',      'brand' => 'Penguin',       'name' => 'The Psychology of Money',    'slug' => 'psychology-of-money',          'desc' => 'By Morgan Housel. Timeless lessons on wealth, greed, and happiness. Explores how our relationship with money shapes financial success.',               'price' => 199.00,   'purchase' => 85.00,    'stock' => 55,  'sku' => 'PGN-POM-063', 'featured' => true],
            ['cat' => 'Books',      'brand' => 'Penguin',       'name' => 'The Lean Startup',           'slug' => 'the-lean-startup',             'desc' => 'By Eric Ries. How today\'s entrepreneurs use continuous innovation to create radically successful businesses. Methodology for building startups.',    'price' => 229.00,   'purchase' => 100.00,   'stock' => 35,  'sku' => 'PGN-TLS-064', 'featured' => false],
            // O'Reilly
            ['cat' => 'Books',      'brand' => "O'Reilly",      'name' => 'Learning Python',            'slug' => 'learning-python',              'desc' => 'By Mark Lutz. The definitive guide to Python programming. Covers fundamentals, OOP, modules, exceptions, and advanced topics in 1,600 pages.',        'price' => 599.00,   'purchase' => 290.00,   'stock' => 30,  'sku' => 'ORL-LP-065', 'featured' => false],
            ['cat' => 'Books',      'brand' => "O'Reilly",      'name' => 'Designing Data-Intensive Applications', 'slug' => 'designing-data-intensive-apps', 'desc' => 'By Martin Kleppmann. The big ideas behind reliable, scalable, and maintainable systems. Covers databases, streaming, batch processing.',          'price' => 599.00,   'purchase' => 290.00,   'stock' => 25,  'sku' => 'ORL-DDIA-066', 'featured' => false],
            ['cat' => 'Books',      'brand' => "O'Reilly",      'name' => 'Kubernetes Up & Running',     'slug' => 'kubernetes-up-and-running',    'desc' => 'By Brendan Burns. Dive into the future of infrastructure. Learn Kubernetes fundamentals, pods, services, deployments, and production operations.',    'price' => 549.00,   'purchase' => 260.00,   'stock' => 20,  'sku' => 'ORL-KUR-067', 'featured' => false],
            ['cat' => 'Books',      'brand' => "O'Reilly",      'name' => 'Fluent Python',              'slug' => 'fluent-python',                'desc' => 'By Luciano Ramalho. Clear, concise, and effective programming. Master Python\'s idioms and advanced features with practical code examples.',           'price' => 649.00,   'purchase' => 310.00,   'stock' => 22,  'sku' => 'ORL-FP-068', 'featured' => false],
            // HarperCollins
            ['cat' => 'Books',      'brand' => 'HarperCollins', 'name' => 'The Alchemist',              'slug' => 'the-alchemist',                'desc' => 'By Paulo Coelho. A magical fable about following your dream. The story of Santiago, an Andalusian shepherd boy who yearns to travel in search of treasure.', 'price' => 179.00, 'purchase' => 75.00, 'stock' => 90, 'sku' => 'HC-TAL-069', 'featured' => true],
            ['cat' => 'Books',      'brand' => 'HarperCollins', 'name' => 'Rich Dad Poor Dad',          'slug' => 'rich-dad-poor-dad',            'desc' => 'By Robert Kiyosaki. What the rich teach their kids about money that the poor and middle class do not. Personal finance classic.',                   'price' => 199.00,   'purchase' => 85.00,    'stock' => 60,  'sku' => 'HC-RDPD-070', 'featured' => false],
            ['cat' => 'Books',      'brand' => 'HarperCollins', 'name' => 'Think and Grow Rich',        'slug' => 'think-and-grow-rich',          'desc' => 'By Napoleon Hill. The landmark bestseller that reveals the secret to personal achievement and financial success through 13 principles.',             'price' => 169.00,   'purchase' => 70.00,    'stock' => 50,  'sku' => 'HC-TGR-071', 'featured' => false],
            ['cat' => 'Books',      'brand' => 'HarperCollins', 'name' => 'The 7 Habits of Highly Effective People', 'slug' => '7-habits-highly-effective-people', 'desc' => 'By Stephen Covey. Powerful lessons in personal change. The integrated approach to personal and professional effectiveness through timeless principles.', 'price' => 249.00, 'purchase' => 110.00, 'stock' => 40, 'sku' => 'HC-7H-072', 'featured' => false],
        ];

        $productRecords = [];
        foreach ($productsData as $p) {
            $margin = $p['purchase'] > 0
                ? round(($p['price'] - $p['purchase']) / $p['purchase'] * 100, 1)
                : 30.0;

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
                'thumbnail'         => 'https://picsum.photos/seed/' . $p['slug'] . '/400/400',
                'is_active'         => true,
                'featured'          => $p['featured'],
            ]);
            $productRecords[] = $product;

            // Product images (2-4 per product)
            $imageCount = rand(2, 4);
            for ($i = 1; $i <= $imageCount; $i++) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url'  => 'https://picsum.photos/seed/' . $p['slug'] . '-' . $i . '/800/800',
                    'sort_order' => $i,
                ]);
            }
        }

        // --- iPhone 16 Pro Variants (storage + color) ---
        $iphone = Product::where('slug', 'iphone-16-pro')->first();
        if ($iphone) {
            ProductVariant::create(['product_id' => $iphone->id, 'name' => '256GB - Natural Titanium', 'price' => null,   'stock' => 12, 'sku' => 'APL-IP16P-256-NT', 'storage' => '256GB', 'color' => 'Natural Titanium', 'is_default' => true,  'sort_order' => 0]);
            ProductVariant::create(['product_id' => $iphone->id, 'name' => '512GB - Natural Titanium', 'price' => 16999.00, 'stock' => 8,  'sku' => 'APL-IP16P-512-NT', 'storage' => '512GB', 'color' => 'Natural Titanium', 'is_default' => false, 'sort_order' => 1]);
            ProductVariant::create(['product_id' => $iphone->id, 'name' => '256GB - Desert Titanium', 'price' => null,   'stock' => 10, 'sku' => 'APL-IP16P-256-DT', 'storage' => '256GB', 'color' => 'Desert Titanium', 'is_default' => false, 'sort_order' => 2]);
            ProductVariant::create(['product_id' => $iphone->id, 'name' => '256GB - Black Titanium',  'price' => null,   'stock' => 8,  'sku' => 'APL-IP16P-256-BT', 'storage' => '256GB', 'color' => 'Black Titanium',  'is_default' => false, 'sort_order' => 3]);
        }

        // --- Galaxy S25 Ultra Variants ---
        $galaxy = Product::where('slug', 'galaxy-s25-ultra')->first();
        if ($galaxy) {
            ProductVariant::create(['product_id' => $galaxy->id, 'name' => '256GB - Titanium Gray',  'price' => null,    'stock' => 10, 'sku' => 'SAM-GS25U-256-GY', 'storage' => '256GB', 'color' => 'Titanium Gray',  'is_default' => true,  'sort_order' => 0]);
            ProductVariant::create(['product_id' => $galaxy->id, 'name' => '512GB - Titanium Gray',  'price' => 15999.00, 'stock' => 8,  'sku' => 'SAM-GS25U-512-GY', 'storage' => '512GB', 'color' => 'Titanium Gray',  'is_default' => false, 'sort_order' => 1]);
            ProductVariant::create(['product_id' => $galaxy->id, 'name' => '256GB - Titanium White', 'price' => null,    'stock' => 7,  'sku' => 'SAM-GS25U-256-WT', 'storage' => '256GB', 'color' => 'Titanium White', 'is_default' => false, 'sort_order' => 2]);
            ProductVariant::create(['product_id' => $galaxy->id, 'name' => '256GB - Titanium Black', 'price' => null,    'stock' => 8,  'sku' => 'SAM-GS25U-256-BK', 'storage' => '256GB', 'color' => 'Titanium Black', 'is_default' => false, 'sort_order' => 3]);
        }

        // --- Air Max Sneakers Variants (size + color) ---
        $airMax = Product::where('slug', 'air-max-sneakers')->first();
        if ($airMax) {
            ProductVariant::create(['product_id' => $airMax->id, 'name' => '42 - Black/White', 'price' => null,   'stock' => 15, 'sku' => 'NKE-AMS-42-BW', 'size' => '42', 'color' => 'Black/White', 'is_default' => true,  'sort_order' => 0]);
            ProductVariant::create(['product_id' => $airMax->id, 'name' => '43 - Black/White', 'price' => null,   'stock' => 12, 'sku' => 'NKE-AMS-43-BW', 'size' => '43', 'color' => 'Black/White', 'is_default' => false, 'sort_order' => 1]);
            ProductVariant::create(['product_id' => $airMax->id, 'name' => '42 - White/Red',   'price' => null,   'stock' => 10, 'sku' => 'NKE-AMS-42-WR', 'size' => '42', 'color' => 'White/Red',   'is_default' => false, 'sort_order' => 2]);
            ProductVariant::create(['product_id' => $airMax->id, 'name' => '44 - Black/White', 'price' => 1699.00, 'stock' => 8,  'sku' => 'NKE-AMS-44-BW', 'size' => '44', 'color' => 'Black/White', 'is_default' => false, 'sort_order' => 3]);
        }

        // --- Ultraboost Shoes Variants ---
        $ultraboost = Product::where('slug', 'ultraboost-shoes')->first();
        if ($ultraboost) {
            ProductVariant::create(['product_id' => $ultraboost->id, 'name' => '42 - Core Black', 'price' => null,    'stock' => 10, 'sku' => 'ADD-UB-42-CB', 'size' => '42', 'color' => 'Core Black', 'is_default' => true,  'sort_order' => 0]);
            ProductVariant::create(['product_id' => $ultraboost->id, 'name' => '43 - Core Black', 'price' => null,    'stock' => 8,  'sku' => 'ADD-UB-43-CB', 'size' => '43', 'color' => 'Core Black', 'is_default' => false, 'sort_order' => 1]);
            ProductVariant::create(['product_id' => $ultraboost->id, 'name' => '42 - Cloud White', 'price' => null,  'stock' => 9,  'sku' => 'ADD-UB-42-CW', 'size' => '42', 'color' => 'Cloud White', 'is_default' => false, 'sort_order' => 2]);
            ProductVariant::create(['product_id' => $ultraboost->id, 'name' => '44 - Core Black', 'price' => 1999.00, 'stock' => 6,  'sku' => 'ADD-UB-44-CB', 'size' => '44', 'color' => 'Core Black', 'is_default' => false, 'sort_order' => 3]);
        }

        // --- Slim Fit Jeans Variants (waist size) ---
        $jeans = Product::where('slug', 'slim-fit-jeans')->first();
        if ($jeans) {
            ProductVariant::create(['product_id' => $jeans->id, 'name' => '30W x 32L', 'price' => null, 'stock' => 15, 'sku' => 'LEV-SFJ-30-32', 'size' => '30/32', 'is_default' => true,  'sort_order' => 0]);
            ProductVariant::create(['product_id' => $jeans->id, 'name' => '32W x 32L', 'price' => null, 'stock' => 20, 'sku' => 'LEV-SFJ-32-32', 'size' => '32/32', 'is_default' => false, 'sort_order' => 1]);
            ProductVariant::create(['product_id' => $jeans->id, 'name' => '34W x 32L', 'price' => null, 'stock' => 15, 'sku' => 'LEV-SFJ-34-32', 'size' => '34/32', 'is_default' => false, 'sort_order' => 2]);
            ProductVariant::create(['product_id' => $jeans->id, 'name' => '36W x 34L', 'price' => null, 'stock' => 8,  'sku' => 'LEV-SFJ-36-34', 'size' => '36/34', 'is_default' => false, 'sort_order' => 3]);
        }

        $this->command->info('  ✅ ' . count($productRecords) . ' products with images created');
        $this->command->info('  ✅ ' . ProductVariant::count() . ' product variants created');

        // ========================================================================
        // 6. ADDRESSES (from here, the order/invoice/payment logic is preserved)
        // ========================================================================
        $productPool = [];
        foreach ($productRecords as $pr) {
            $productPool[$pr->id] = $pr;
        }

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
        // 7. ORDERS — Plan distribution across 12 months with realistic volume
        // ========================================================================
        $earliestDate = now()->subMonths(11)->startOfMonth();

        $clientOrderPlans = [];
        foreach ($clients as $client) {
            list($min, $max) = $client->orderRange;
            $count = mt_rand($min, $max);
            $clientOrderPlans[] = ['client' => $client, 'count' => $count];
        }

        $totalOrderCount = array_sum(array_column($clientOrderPlans, 'count'));
        $guestOrderCount = (int) round($totalOrderCount * 0.08);
        $totalOrderCount += $guestOrderCount;

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

        $stockDeductions = [];

        $clientOrderBuckets = [];
        foreach ($clientOrderPlans as $plan) {
            for ($i = 0; $i < $plan['count']; $i++) {
                $bias = $plan['client']->segment === 'vip' ? 0.7 :
                        ($plan['client']->segment === 'regular' ? 0.5 : 0.3);
                $clientOrderBuckets[] = ['client' => $plan['client'], 'bias' => $bias];
            }
        }
        shuffle($clientOrderBuckets);

        $orderAssignments = [];
        foreach ($clientOrderBuckets as $bucket) {
            $client = $bucket['client'];
            $bias = $bucket['bias'];

            if (mt_rand(1, 100) <= ($bias * 100)) {
                $monthIdx = mt_rand(6, 11);
            } else {
                $monthIdx = mt_rand(0, 11);
            }

            $attempts = 0;
            while (($monthlyCaps[$monthIdx] ?? 0) <= 0 && $attempts < 20) {
                $monthIdx = mt_rand(0, 11);
                $attempts++;
            }
            if ($attempts >= 20) {
                foreach ($monthlyCaps as $mi => $cap) {
                    if ($cap > 0) { $monthIdx = $mi; break; }
                }
            }

            if (($monthlyCaps[$monthIdx] ?? 0) > 0) {
                $monthlyCaps[$monthIdx]--;
                $orderAssignments[] = ['client' => $client, 'monthIdx' => $monthIdx];
            }
        }

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

        usort($orderAssignments, fn($a, $b) => $a['monthIdx'] <=> $b['monthIdx']);

        $orderedProductIds = [];
        $guestAddressIds = [];

        foreach ($orderAssignments as $assignment) {
            $client = $assignment['client'];
            $monthIdx = $assignment['monthIdx'];
            $isGuest = $client === null;

            $baseDate = $earliestDate->copy()->addMonths($monthIdx);
            $daysInMonth = (int) $baseDate->copy()->endOfMonth()->format('d');
            $dayOffset = mt_rand(1, max(1, $daysInMonth - 1));
            $hourOffset = mt_rand(9, 21);
            $minuteOffset = mt_rand(0, 59);
            $orderDate = $baseDate->copy()->setDay(min($dayOffset, $daysInMonth))->setTime($hourOffset, $minuteOffset);

            if ($orderDate->isAfter(now())) {
                $orderDate = now()->subHours(mt_rand(1, 72));
            }

            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
            $status = $this->pickOrderStatus();

            $isRefunded = false;
            if ($status === 'delivered' && mt_rand(1, 100) <= 10) {
                $isRefunded = true;
            }

            $shippingMethodId = $shippingMethods[array_rand($shippingMethods)];

            $userId = null;
            $guestName = null;
            $guestEmail = null;
            $addressId = null;

            if ($isGuest) {
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

            $order = Order::create([
                'user_id'             => $userId,
                'session_id'          => null,
                'order_number'        => 'ORD-' . $orderDate->timestamp . '-' . strtoupper(substr(uniqid(), -4)),
                'total_price'         => 0,
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

            $itemCount = mt_rand(1, 5);
            $usedProducts = [];
            $orderTotal = 0;
            $orderItemRecords = [];

            for ($oi = 0; $oi < $itemCount; $oi++) {
                $poolWeights = [];
                foreach ($productPool as $pid => $pr) {
                    $catBias = match ($pr->category_id) {
                        $catByName['Electronics'] => 1.8,
                        $catByName['Clothing'] => 1.5,
                        $catByName['Beauty & Health'] => 1.3,
                        $catByName['Sports & Outdoors'] => 1.2,
                        default => 1.0,
                    };
                    $poolWeights[$pid] = (5000 / max(1, $pr->price)) * $catBias;
                }

                $totalWeight = array_sum($poolWeights);
                $rand = mt_rand(1, (int) $totalWeight);
                $cumulative = 0;
                $pid = null;
                foreach ($poolWeights as $id => $w) {
                    $cumulative += $w;
                    if ($rand <= $cumulative) { $pid = $id; break; }
                }
                if ($pid === null) $pid = array_rand($productPool);

                $attempts = 0;
                while (in_array($pid, $usedProducts) && $attempts < 20) {
                    $rand = mt_rand(1, (int) $totalWeight);
                    $cumulative = 0;
                    foreach ($poolWeights as $id => $w) {
                        $cumulative += $w;
                        if ($rand <= $cumulative) { $pid = $id; break; }
                    }
                    if ($pid === null || in_array($pid, $usedProducts)) {
                        $unused = array_diff(array_keys($productPool), $usedProducts);
                        if (!empty($unused)) $pid = $unused[array_rand($unused)];
                    }
                    $attempts++;
                }
                $usedProducts[] = $pid;

                $qty = match (true) {
                    mt_rand(1, 100) <= 60 => 1,
                    mt_rand(1, 100) <= 85 => 2,
                    default => 3,
                };

                $product = $productPool[$pid];
                $price = $product->getEffectivePrice();
                $subtotal = $price * $qty;
                $orderTotal += $subtotal;

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

                if (($status === 'delivered' && !$isRefunded) || $status === 'shipped' || $status === 'processing') {
                    if (!isset($stockDeductions[$pid])) $stockDeductions[$pid] = 0;
                    $stockDeductions[$pid] += $qty;
                }
            }

            $shippingCost = 0;
            $shippingM = ShippingMethod::find($shippingMethodId);
            if ($shippingM) {
                $shippingCost = $shippingM->getEffectiveCost($orderTotal);
            }
            $orderTotal = round($orderTotal + $shippingCost, 2);
            $order->update(['total_price' => $orderTotal]);

            $issuedAt = $orderDate->copy()->addDays(mt_rand(0, 2));

            if ($status === 'delivered' && !$isRefunded) {
                $invoiceStatus = 'paid';
                $paidAmount = $orderTotal;
                $paidAt = $issuedAt->copy()->addDays(mt_rand(1, 5));
            } elseif ($isRefunded || $status === 'cancelled') {
                if ($isRefunded) {
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
                $invoiceStatus = 'unpaid';
                $paidAmount = 0;
                $paidAt = null;
            } else {
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

            if ($paidAmount > 0 && !$isGuest) {
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

        foreach ($productRecords as $pr) {
            $soldQty = $stockDeductions[$pr->id] ?? 0;
            if ($soldQty > 0) {
                $pr->decrement('stock', min($soldQty, $pr->stock));
            }

            $variants = $pr->variants;
            if ($variants->isNotEmpty()) {
                $totalVariantStock = $variants->sum('stock');
                if ($totalVariantStock > 0) {
                    foreach ($variants as $variant) {
                        $variantProportion = $variant->stock / max(1, $totalVariantStock);
                        $variantSoldQty = (int) round($soldQty * $variantProportion);
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
        // 8. REVIEWS
        // ========================================================================
        $deliveredOrders = Order::where('status', 'delivered')
            ->whereDoesntHave('revenue')
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

            if (mt_rand(1, 100) > 60) continue;

            $itemsToReview = $items->take(mt_rand(1, min(2, $items->count())));
            foreach ($itemsToReview as $item) {
                $existingReview = Review::where('user_id', $order->user_id)
                    ->where('product_id', $item->product_id)
                    ->exists();
                if ($existingReview) continue;

                $rating = match (true) {
                    mt_rand(1, 100) <= 5  => 3,
                    mt_rand(1, 100) <= 15 => 4,
                    default => 5,
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
        // 9. EXPENSES
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

        $recurringMonthly = [
            'rent'      => 12000,
            'salaries'  => [28000, 35000],
            'software'  => [1500, 2500],
            'insurance' => [3000, 4500],
            'utilities' => [1200, 2800],
        ];

        $expenseCount = 0;

        for ($m = 0; $m < 12; $m++) {
            $baseDate = $earliestDate->copy()->addMonths($m);
            $daysInMonth = (int) $baseDate->copy()->endOfMonth()->format('d');

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
            }

            $variableCount = mt_rand(2, 5);
            for ($v = 0; $v < $variableCount; $v++) {
                $cat = $expenseCategories[array_rand($expenseCategories)];
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
            }
        }

        $this->command->info('  ✅ ' . Expense::count() . ' expenses created');

        // ========================================================================
        // 10. CARTS — Active + Abandoned
        // ========================================================================
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
        // 11. PAYPAL SETTINGS
        // ========================================================================
        \App\Models\Setting::setValue('paypal_enabled', '0');
        \App\Models\Setting::setValue('paypal_mode', 'sandbox');
        \App\Models\Setting::setValue('paypal_client_id', 'YOUR_SANDBOX_CLIENT_ID');
        \App\Models\Setting::setValue('paypal_client_secret', 'YOUR_SANDBOX_CLIENT_SECRET');
        \App\Models\Setting::setValue('paypal_webhook_id', '');
        $this->command->info('  ✅ PayPal sandbox settings created — replace credentials in admin panel');

        // ========================================================================
        // 12. HOMEPAGE FEATURE CARDS
        // ========================================================================
        $featuresData = [
            [
                'icon_key'    => 'truck',
                'title'       => 'Free Shipping',
                'description' => 'Free shipping on all orders over $75. Fast & reliable delivery worldwide.',
                'link_url'    => null,
                'sort_order'  => 1,
            ],
            [
                'icon_key'    => 'refresh_cw',
                'title'       => '30-Day Returns',
                'description' => 'Not satisfied? Return any product within 30 days for a full refund, no questions asked.',
                'link_url'    => '/shipping-returns',
                'sort_order'  => 2,
            ],
            [
                'icon_key'    => 'lock',
                'title'       => 'Secure Checkout',
                'description' => 'Your payment information is encrypted and protected with industry-standard SSL security.',
                'link_url'    => null,
                'sort_order'  => 3,
            ],
            [
                'icon_key'    => 'headphones',
                'title'       => '24/7 Customer Support',
                'description' => 'Our dedicated support team is available around the clock to help with any questions or concerns.',
                'link_url'    => null,
                'sort_order'  => 4,
            ],
            [
                'icon_key'    => 'gift',
                'title'       => 'Free Gift Wrapping',
                'description' => 'Every order comes with complimentary gift wrapping. Perfect for surprising your loved ones.',
                'link_url'    => null,
                'sort_order'  => 5,
            ],
            [
                'icon_key'    => 'badge_check',
                'title'       => '100% Authentic',
                'description' => 'All products are sourced directly from official brands and authorized distributors. Guaranteed authentic.',
                'link_url'    => null,
                'sort_order'  => 6,
            ],
        ];

        foreach ($featuresData as $f) {
            HomepageFeature::create($f);
        }
        $this->command->info('  ✅ ' . count($featuresData) . ' homepage feature cards created');

        // ========================================================================
        // 13. PROMOTION BANNERS
        // ========================================================================
        $promotionsData = [
            [
                'title'            => 'Summer Sale — Up to 40% Off',
                'subtitle'         => 'Electronics & Gadgets',
                'description'      => 'Shop the best deals on top brands like Apple, Samsung, and Sony. Limited time offer — don\'t miss out!',
                'cta_text'         => 'Shop Now',
                'cta_url'          => '/products?sort=price_desc',
                'background_color' => '#1e293b',
                'text_color'       => '#ffffff',
                'discount_text'    => 'Up to 40% OFF',
                'badge'            => 'Limited Time',
                'is_active'        => true,
                'priority'         => 100,
                'position'         => 'hero_banner',
                'starts_at'        => now()->subDays(7),
                'ends_at'          => now()->addDays(23),
            ],
            [
                'title'            => 'New Arrivals: Spring Collection',
                'subtitle'         => 'Fashion & Sportswear',
                'description'      => 'Discover the latest from Nike, Adidas, and more. Fresh styles for every season.',
                'cta_text'         => 'Explore',
                'cta_url'          => '/products?category_id=2',
                'background_color' => '#1c1917',
                'text_color'       => '#ffffff',
                'discount_text'    => 'New In',
                'badge'            => 'Just Dropped',
                'is_active'        => true,
                'priority'         => 90,
                'position'         => 'hero_banner',
                'starts_at'        => null,
                'ends_at'          => null,
            ],
            [
                'title'            => 'Free Shipping on Orders Over $75',
                'subtitle'         => null,
                'description'      => 'Plus easy 30-day returns on all items.',
                'cta_text'         => null,
                'cta_url'          => null,
                'background_color' => '#0f766e',
                'text_color'       => '#ffffff',
                'discount_text'    => null,
                'badge'            => '🎉 Offer',
                'is_active'        => true,
                'priority'         => 100,
                'position'         => 'announcement_bar',
                'starts_at'        => null,
                'ends_at'          => null,
            ],
        ];

        foreach ($promotionsData as $p) {
            Promotion::create($p);
        }
        $this->command->info('  ✅ ' . count($promotionsData) . ' promotion banners created');

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
