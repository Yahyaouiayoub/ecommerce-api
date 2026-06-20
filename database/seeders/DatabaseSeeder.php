<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Revenue;
use App\Models\Expense;
use App\Models\Cart;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private array $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function run(): void
    {
        $this->command->info('🚀 Starting seeding...');
        $this->command->newLine();

        // ========================================================================
        // 1. USERS
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

        $this->command->info('  ✅ Admin created: amin@example.com / password');

        $clientNames = [
            ['first_name' => 'Fatima',  'last_name' => 'Zahra'],
            ['first_name' => 'Youssef', 'last_name' => 'Benali'],
            ['first_name' => 'Amina',   'last_name' => 'El Khoury'],
            ['first_name' => 'Karim',   'last_name' => 'Idrissi'],
            ['first_name' => 'Sara',    'last_name' => 'Mansouri'],
            ['first_name' => 'Hassan',  'last_name' => 'Ouazzani'],
            ['first_name' => 'Nadia',   'last_name' => 'Fassi'],
            ['first_name' => 'Omar',    'last_name' => 'Tazi'],
        ];

        $clients = [];
        foreach ($clientNames as $i => $name) {
            $num = $i + 1;
            $clients[] = User::create([
                'first_name' => $name['first_name'],
                'last_name'  => $name['last_name'],
                'email'      => "client{$num}@example.com",
                'password'   => Hash::make('password'),
                'role'       => 'client',
                'phone'      => '06' . str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'gender'     => $i % 2 === 0 ? 'female' : 'male',
            ]);
        }

        $allUsers = [$admin, ...$clients];
        $this->command->info('  ✅ Clients created: client1@example.com ... client' . count($clients) . '@example.com / password');

        // ========================================================================
        // 2. BRANDS
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
        $brandCount = Brand::count();
        $brandIds   = Brand::pluck('id')->toArray();
        $brandByName = Brand::pluck('id', 'name')->toArray();
        $this->command->info("  ✅ {$brandCount} brands created");

        // ========================================================================
        // 3. CATEGORIES
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
        $categoryCount = Category::count();
        $categoryIds   = Category::pluck('id')->toArray();
        $this->command->info("  ✅ {$categoryCount} categories created");

        // ========================================================================
        // 4. PRODUCTS
        // ========================================================================
        $productsData = [
            // === Electronics (index 0) ===
            ['category_id' => $categoryIds[0], 'brand_id' => $brandByName['TechPro'],    'name' => 'Smartphone Pro Max',           'slug' => 'smartphone-pro-max',          'description' => '6.7" OLED display, 256GB storage, 48MP triple camera system with night mode, 5G compatible, water-resistant (IP68).',                                        'price' => 8999.00,  'stock' => 45,  'sku' => 'TP-SPM-001', 'featured' => true],
            ['category_id' => $categoryIds[0], 'brand_id' => $brandByName['TechPro'],    'name' => 'Ultrabook Laptop 15"',         'slug' => 'ultrabook-laptop-15',         'description' => '15.6" FHD display, Intel Core i7, 16GB RAM, 512GB SSD, backlit keyboard, fingerprint reader. Weighs only 1.4kg.',                                                  'price' => 12499.00, 'stock' => 20,  'sku' => 'TP-UB-002', 'featured' => true],
            ['category_id' => $categoryIds[0], 'brand_id' => $brandByName['AudioWave'],  'name' => 'Wireless Noise-Cancelling Headphones', 'slug' => 'wireless-nc-headphones',  'description' => 'Premium over-ear headphones with ANC, 30-hour battery life, hi-res audio, memory foam ear cushions, foldable.',                                                        'price' => 2499.00,  'stock' => 60,  'sku' => 'AW-WH-003', 'featured' => true],
            ['category_id' => $categoryIds[0], 'brand_id' => $brandByName['AudioWave'],  'name' => 'Bluetooth Speaker Mini',        'slug' => 'bluetooth-speaker-mini',      'description' => 'Portable waterproof speaker with 360° sound, 12-hour playtime, USB-C.',                                                                                               'price' => 599.00,   'stock' => 80,  'sku' => 'AW-BS-004', 'featured' => false],
            ['category_id' => $categoryIds[0], 'brand_id' => $brandByName['WearTech'],   'name' => 'Fitness Smartwatch Pro',       'slug' => 'fitness-smartwatch-pro',      'description' => 'Advanced fitness tracking with GPS, heart rate, blood oxygen, sleep tracking, 100+ workout modes, 14-day battery.',                                                    'price' => 2999.00,  'stock' => 35,  'sku' => 'WT-FS-005', 'featured' => true],
            ['category_id' => $categoryIds[0], 'brand_id' => $brandByName['TechPro'],    'name' => 'Wireless Charging Pad',        'slug' => 'wireless-charging-pad',       'description' => 'Fast wireless charger for all Qi-enabled devices. 15W fast charging, LED indicator, anti-slip design.',                                                                'price' => 299.00,   'stock' => 120, 'sku' => 'TP-WC-006', 'featured' => false],
            // === Clothing (index 1) ===
            ['category_id' => $categoryIds[1], 'brand_id' => $brandByName['FashionCo'],  'name' => 'Premium Cotton T-Shirt',       'slug' => 'premium-cotton-tshirt',       'description' => 'Ultra-soft 100% organic cotton t-shirt, modern fit. Pre-shrunk, breathable, 12 colors.',                                                                              'price' => 249.00,   'stock' => 200, 'sku' => 'FC-PCT-007', 'featured' => true],
            ['category_id' => $categoryIds[1], 'brand_id' => $brandByName['DenimCo'],    'name' => 'Slim Fit Denim Jeans',         'slug' => 'slim-fit-denim-jeans',        'description' => 'Classic slim-fit jeans, premium stretch denim, mid-rise waist, five-pocket styling.',                                                                                  'price' => 699.00,   'stock' => 75,  'sku' => 'DC-SFJ-008', 'featured' => true],
            ['category_id' => $categoryIds[1], 'brand_id' => $brandByName['FashionCo'],  'name' => 'Casual Hoodie',               'slug' => 'casual-hoodie',              'description' => 'Fleece hoodie with kangaroo pocket, adjustable drawstring hood, ribbed cuffs.',                                                                                        'price' => 499.00,   'stock' => 90,  'sku' => 'FC-CH-009', 'featured' => false],
            ['category_id' => $categoryIds[1], 'brand_id' => $brandByName['DenimCo'],    'name' => 'Denim Jacket Classic',         'slug' => 'denim-jacket-classic',        'description' => 'Timeless denim jacket, button-front, chest pockets, adjustable waist tabs.',                                                                                           'price' => 899.00,   'stock' => 40,  'sku' => 'DC-DJ-010', 'featured' => false],
            // === Books (index 2) ===
            ['category_id' => $categoryIds[2], 'brand_id' => $brandByName['BookHouse'],  'name' => 'The Art of Clean Code',        'slug' => 'art-of-clean-code',           'description' => 'Guide to writing maintainable, scalable software. Covers design patterns, testing, refactoring.',                                                                     'price' => 399.00,   'stock' => 50,  'sku' => 'BH-ACC-011', 'featured' => true],
            ['category_id' => $categoryIds[2], 'brand_id' => $brandByName['BookHouse'],  'name' => 'Ancient Civilizations',        'slug' => 'history-ancient-civilizations','description' => 'Journey through ancient civilizations. Richly illustrated with maps and artifacts.',                                                                                     'price' => 449.00,   'stock' => 30,  'sku' => 'BH-HAC-012', 'featured' => false],
            ['category_id' => $categoryIds[2], 'brand_id' => $brandByName['BookHouse'],  'name' => 'Mediterranean Cookbook',       'slug' => 'cookbook-mediterranean',      'description' => '200+ authentic Mediterranean recipes with nutritional info and wine pairings.',                                                                                         'price' => 349.00,   'stock' => 25,  'sku' => 'BH-CMD-013', 'featured' => false],
            // === Home & Garden (index 3) ===
            ['category_id' => $categoryIds[3], 'brand_id' => $brandByName['HomeStyle'],  'name' => 'Minimalist Desk Lamp',         'slug' => 'minimalist-desk-lamp',        'description' => 'LED desk lamp, adjustable brightness/color temperature, touch control, USB charging.',                                                                                 'price' => 549.00,   'stock' => 35,  'sku' => 'HS-DL-014', 'featured' => false],
            ['category_id' => $categoryIds[3], 'brand_id' => $brandByName['GreenLife'],  'name' => 'Indoor Plant Bundle',          'slug' => 'indoor-plant-bundle',         'description' => '4 easy-care indoor plants in ceramic pots: Snake Plant, Pothos, ZZ Plant, Peace Lily.',                                                                               'price' => 799.00,   'stock' => 20,  'sku' => 'GL-IPB-015', 'featured' => true],
            ['category_id' => $categoryIds[3], 'brand_id' => $brandByName['HomeStyle'],  'name' => 'Ergonomic Office Chair',       'slug' => 'ergonomic-office-chair',      'description' => 'Premium mesh chair with lumbar support, adjustable armrests, headrest, tilt.',                                                                                          'price' => 3499.00,  'stock' => 15,  'sku' => 'HS-EOC-016', 'featured' => false],
            // === Sports & Outdoors (index 4) ===
            ['category_id' => $categoryIds[4], 'brand_id' => $brandByName['FitLife'],    'name' => 'Premium Yoga Mat',             'slug' => 'premium-yoga-mat',            'description' => '6mm eco-friendly TPE yoga mat with alignment lines, non-slip, includes carrying strap.',                                                                                'price' => 499.00,   'stock' => 60,  'sku' => 'FL-YM-017', 'featured' => false],
            ['category_id' => $categoryIds[4], 'brand_id' => $brandByName['FitLife'],    'name' => 'Insulated Water Bottle 750ml',  'slug' => 'insulated-water-bottle',      'description' => 'Double-wall stainless steel. Cold 24h / hot 12h. BPA-free, leak-proof.',                                                                                               'price' => 249.00,   'stock' => 150, 'sku' => 'FL-IWB-018', 'featured' => false],
            ['category_id' => $categoryIds[4], 'brand_id' => $brandByName['FitLife'],    'name' => 'Adjustable Dumbbell Set',      'slug' => 'adjustable-dumbbell-set',     'description' => '2.5kg-25kg each, quick-change weight selection, includes storage tray.',                                                                                                'price' => 4999.00,  'stock' => 10,  'sku' => 'FL-ADS-019', 'featured' => true],
            ['category_id' => $categoryIds[4], 'brand_id' => $brandByName['FitLife'],    'name' => 'Running Shoes Pro',            'slug' => 'running-shoes-pro',           'description' => 'Lightweight performance running shoes with responsive cushioning, breathable mesh upper.',                                                                              'price' => 1299.00,  'stock' => 45,  'sku' => 'FL-RS-020', 'featured' => true],
            // === Beauty & Health (index 5) ===
            ['category_id' => $categoryIds[5], 'brand_id' => $brandByName['PureBeauty'], 'name' => 'Vitamin C Brightening Serum',  'slug' => 'vitamin-c-serum',             'description' => '20% Vitamin C serum with hyaluronic acid and vitamin E. Brightens skin, reduces dark spots.',                                                                          'price' => 349.00,   'stock' => 55,  'sku' => 'PB-VCS-021', 'featured' => true],
            ['category_id' => $categoryIds[5], 'brand_id' => $brandByName['PureBeauty'], 'name' => 'Organic Face Moisturizer',     'slug' => 'organic-face-moisturizer',    'description' => '100% natural day cream with shea butter, aloe vera, jojoba oil.',                                                                                                      'price' => 299.00,   'stock' => 40,  'sku' => 'PB-OFM-022', 'featured' => false],
            ['category_id' => $categoryIds[5], 'brand_id' => $brandByName['PureBeauty'], 'name' => 'Hair Repair Shampoo',          'slug' => 'hair-repair-shampoo',         'description' => 'Sulfate-free with argan oil and keratin. Repairs damaged hair, adds shine.',                                                                                            'price' => 199.00,   'stock' => 70,  'sku' => 'PB-HRS-023', 'featured' => false],
            // === Toys & Games (index 6) ===
            ['category_id' => $categoryIds[6], 'brand_id' => $brandByName['ToyWorld'],   'name' => 'Building Blocks Kit 1000pc',   'slug' => 'building-blocks-1000',        'description' => '1000 colorful blocks, compatible with major brands, includes idea booklet.',                                                                                            'price' => 449.00,   'stock' => 30,  'sku' => 'TW-BB-024', 'featured' => false],
            ['category_id' => $categoryIds[6], 'brand_id' => $brandByName['ToyWorld'],   'name' => 'Board Game: Strategy Quest',    'slug' => 'board-game-strategy-quest',   'description' => 'Award-winning strategy game for 2-6 players, 60 min playtime.',                                                                                                        'price' => 599.00,   'stock' => 25,  'sku' => 'TW-BG-025', 'featured' => false],
            // === Pet Supplies (index 7) ===
            ['category_id' => $categoryIds[7], 'brand_id' => $brandByName['PetLove'],    'name' => 'Premium Dog Bed Large',        'slug' => 'premium-dog-bed-large',       'description' => 'Orthopedic memory foam, removable washable cover, non-slip bottom, for dogs up to 40kg.',                                                                              'price' => 899.00,   'stock' => 20,  'sku' => 'PL-DB-026', 'featured' => false],
            ['category_id' => $categoryIds[7], 'brand_id' => $brandByName['PetLove'],    'name' => 'Cat Tree Tower',               'slug' => 'cat-tree-tower',              'description' => 'Multi-level with scratching posts, hiding cubby, hammock, dangling toys. 160cm height.',                                                                              'price' => 1299.00,  'stock' => 12,  'sku' => 'PL-CT-027', 'featured' => false],
        ];

        $productIds = [];
        foreach ($productsData as $p) {
            $product = Product::create($p + ['is_active' => true, 'thumbnail' => '/product.jpg']);
            $productIds[] = $product->id;

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
        $this->command->info('  ✅ ' . count($productsData) . ' products with images created');

        // ========================================================================
        // 5. ADDRESSES
        // ========================================================================
        $moroccanCities = [
            ['city' => 'Casablanca', 'state' => 'Casablanca-Settat',     'postal' => '20000'],
            ['city' => 'Rabat',      'state' => 'Rabat-Salé-Kénitra',    'postal' => '10000'],
            ['city' => 'Marrakech',  'state' => 'Marrakech-Safi',        'postal' => '40000'],
            ['city' => 'Fès',        'state' => 'Fès-Meknès',            'postal' => '30000'],
            ['city' => 'Tangier',    'state' => 'Tanger-Tétouan-Al Hoceïma', 'postal' => '90000'],
            ['city' => 'Agadir',     'state' => 'Souss-Massa',           'postal' => '80000'],
        ];

        $addressLabels = ['Home', 'Work', 'Office', 'Vacation Home', 'Warehouse'];

        $userAddressMap = []; // user_id => [address_id, ...]
        foreach ($allUsers as $user) {
            $addrCount = $user->role === 'admin' ? 1 : rand(1, 3);
            $usedCities = [];

            for ($a = 0; $a < $addrCount; $a++) {
                $loc = $moroccanCities[array_rand($moroccanCities)];
                // Ensure different cities for the same user
                $attempts = 0;
                while (in_array($loc['city'], $usedCities) && $attempts < 10) {
                    $loc = $moroccanCities[array_rand($moroccanCities)];
                    $attempts++;
                }
                $usedCities[] = $loc['city'];

                $address = Address::create([
                    'user_id'       => $user->id,
                    'full_name'     => $user->full_name,
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'address_line1' => rand(1, 999) . ' ' . ['Street', 'Avenue', 'Boulevard', 'Rue', 'Place'][array_rand(['Street', 'Avenue', 'Boulevard', 'Rue', 'Place'])] . ' ' . ['Mohammed V', 'Hassan II', 'Liberté', 'Paris', 'Fès', 'Meknès', 'Oujda', 'Laâyoune'][array_rand(['Mohammed V', 'Hassan II', 'Liberté', 'Paris', 'Fès', 'Meknès', 'Oujda', 'Laâyoune'])],
                    'address_line2' => rand(0, 1) ? 'Apt ' . rand(1, 50) : null,
                    'city'          => $loc['city'],
                    'state'         => $loc['state'],
                    'postal_code'   => $loc['postal'],
                    'country'       => 'Morocco',
                    'is_default'    => $a === 0,
                    'label'         => $addressLabels[array_rand($addressLabels)],
                ]);
                $userAddressMap[$user->id][] = $address->id;
            }
        }
        $this->command->info('  ✅ ' . Address::count() . ' addresses created');

        // ========================================================================
        // 6. ORDERS, ORDER ITEMS, INVOICES, PAYMENTS, REVENUE
        // ========================================================================
        $paymentMethods = ['cod', 'card'];

        // Generate orders spread across the last 12 months
        $orderCount = rand(45, 60);
        $productPool = Product::pluck('price', 'id')->toArray();
        $earliestDate = now()->subMonths(11)->startOfMonth();

        $bar = $this->command->getOutput()->createProgressBar($orderCount);
        $bar->setMessage('Seeding orders...');
        $bar->start();

        for ($o = 0; $o < $orderCount; $o++) {
            // Pick a random user (clients only, skip admin)
            $user = $clients[array_rand($clients)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            // Random date within the last 12 months
            $daysDiff = now()->diffInDays($earliestDate);
            $orderDate = $earliestDate->copy()->addDays(rand(0, $daysDiff));
            $orderDate = $orderDate->addHours(rand(9, 21))->addMinutes(rand(0, 59));

            // Status distribution: roughly 15% pending, 15% processing, 15% shipped, 40% delivered, 15% cancelled
            $statusRand = rand(1, 100);
            $status = match (true) {
                $statusRand <= 15  => 'pending',
                $statusRand <= 30  => 'processing',
                $statusRand <= 50  => 'shipped',
                $statusRand <= 85  => 'delivered',
                default            => 'cancelled',
            };

            // Pick an address for this user
            $userAddresses = $userAddressMap[$user->id] ?? [];
            $addressId = count($userAddresses) > 0 ? $userAddresses[array_rand($userAddresses)] : null;

            // Create order with timestamps matching the order date
            $order = Order::create([
                'user_id'        => $user->id,
                'order_number'   => 'ORD-' . $orderDate->timestamp . '-' . strtoupper(substr(uniqid(), -4)),
                'total_price'    => 0, // will update after items
                'status'         => $status,
                'payment_method' => $paymentMethod,
                'address_id'     => $addressId,
                'notes'          => rand(0, 1) ? 'Please leave at the door.' : null,
                'created_at'     => $orderDate,
                'updated_at'     => $orderDate,
            ]);

            // Generate 1-5 order items
            $itemCount = rand(1, 5);
            $usedProducts = [];
            $orderTotal = 0;

            for ($oi = 0; $oi < $itemCount; $oi++) {
                $pid = array_rand($productPool);
                // Avoid duplicate products in same order
                $attempts = 0;
                while (in_array($pid, $usedProducts) && $attempts < 20) {
                    $pid = array_rand($productPool);
                    $attempts++;
                }
                $usedProducts[] = $pid;

                $qty = rand(1, 3);
                $price = $productPool[$pid];
                $subtotal = $price * $qty;
                $orderTotal += $subtotal;

                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $pid,
                    'quantity'    => $qty,
                    'price'       => $price,
                    'created_at'  => $orderDate,
                    'updated_at'  => $orderDate,
                ]);
            }

            // Update order total
            $order->update(['total_price' => $orderTotal]);

            // --- Invoice ---
            // Invoice issued date = order date + 0-2 days
            $issuedAt = $orderDate->copy()->addDays(rand(0, 2));

            // For delivered orders, invoice is paid. For cancelled, invoice is unpaid.
            // For others, mix of unpaid and paid
            if ($status === 'delivered') {
                $invoiceStatus = 'paid';
                $paidAmount = $orderTotal;
                $paidAt = $issuedAt->copy()->addDays(rand(1, 5));
            } elseif ($status === 'cancelled') {
                $invoiceStatus = 'unpaid';
                $paidAmount = 0;
                $paidAt = null;
            } else {
                // Random: 40% paid, 30% unpaid, 30% partial
                $invRand = rand(1, 100);
                if ($invRand <= 40) {
                    $invoiceStatus = 'paid';
                    $paidAmount = $orderTotal;
                    $paidAt = $issuedAt->copy()->addDays(rand(1, 7));
                } elseif ($invRand <= 70) {
                    $invoiceStatus = 'unpaid';
                    $paidAmount = 0;
                    $paidAt = null;
                } else {
                    $invoiceStatus = 'partially_paid';
                    $paidAmount = round($orderTotal * (rand(30, 70) / 100), 2);
                    $paidAt = null;
                }
            }

            $invoice = Invoice::create([
                'order_id'       => $order->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'total_amount'   => $orderTotal,
                'paid_amount'    => $paidAmount,
                'status'         => $invoiceStatus,
                'issued_at'      => $issuedAt,
                'paid_at'        => $paidAt,
                'created_at'     => $issuedAt,
                'updated_at'     => $paidAt ?? $issuedAt,
            ]);

            // --- Payment records ---
            if ($paidAmount > 0) {
                // Card payments get automatic payment on invoice. COD gets payment on delivery.
                if ($paymentMethod === 'card' || $status === 'delivered') {
                    Payment::create([
                        'order_id'       => $order->id,
                        'invoice_id'     => $invoice->id,
                        'amount'         => $paidAmount,
                        'currency'       => 'MAD',
                        'payment_method' => $paymentMethod,
                        'payment_type'   => $paidAmount === $orderTotal ? 'full' : 'custom',
                        'status'         => 'paid',
                        'paid_at'        => $paidAt ?? $issuedAt,
                        'created_at'     => $paidAt ?? $issuedAt,
                        'updated_at'     => $paidAt ?? $issuedAt,
                    ]);
                }
            }

            // For partial payments, maybe add a second smaller payment
            if ($invoiceStatus === 'partially_paid' && $paidAmount < $orderTotal && rand(0, 1)) {
                $secondPayment = round(($orderTotal - $paidAmount) * rand(50, 100) / 100, 2);
                if ($secondPayment > 0) {
                    $secondDate = ($paidAt ?? $issuedAt)->copy()->addDays(rand(3, 10));
                    Payment::create([
                        'order_id'       => $order->id,
                        'invoice_id'     => $invoice->id,
                        'amount'         => $secondPayment,
                        'currency'       => 'MAD',
                        'payment_method' => $paymentMethod,
                        'payment_type'   => 'partial_50',
                        'status'         => $secondPayment >= ($orderTotal - $paidAmount) ? 'paid' : 'paid',
                        'paid_at'        => $secondDate,
                        'created_at'     => $secondDate,
                        'updated_at'     => $secondDate,
                    ]);

                    // Update invoice paid amount
                    $invoice->paid_amount += $secondPayment;
                    $invoice->recalculateStatus();
                    $invoice->save();
                }
            }

            // --- Revenue record for delivered orders ---
            if ($status === 'delivered') {
                Revenue::create([
                    'order_id'     => $order->id,
                    'amount'       => $orderTotal,
                    'source'       => 'order',
                    'reference'    => $order->order_number,
                    'note'         => 'Revenue from order ' . $order->order_number,
                    'revenue_date' => $paidAt ?? $issuedAt,
                    'created_at'   => $paidAt ?? $issuedAt,
                    'updated_at'   => $paidAt ?? $issuedAt,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('  ✅ ' . Order::count() . ' orders with items, invoices, payments & revenue created');

        // ========================================================================
        // 7. EXPENSES
        // ========================================================================
        $expenseCategories = [
            'rent', 'utilities', 'salaries', 'marketing', 'shipping',
            'supplies', 'maintenance', 'software', 'insurance', 'taxes',
        ];

        $expenseTemplates = [
            'rent'       => ['Office Rent', 'Warehouse Rent', 'Retail Space Lease'],
            'utilities'  => ['Electricity Bill', 'Water Bill', 'Internet Service', 'Phone Bills'],
            'salaries'   => ['Employee Salaries', 'Contractor Payment', 'Freelancer Fee', 'Bonuses'],
            'marketing'  => ['Facebook Ads', 'Google Ads', 'Influencer Campaign', 'Email Marketing', 'Print Ad'],
            'shipping'   => ['Courier Fees', 'Packaging Supplies', 'International Shipping', 'Last-Mile Delivery'],
            'supplies'   => ['Office Supplies', 'Cleaning Supplies', 'Stationery'],
            'maintenance'=> ['Equipment Repair', 'AC Maintenance', 'Plumbing', 'Electrical Work'],
            'software'   => ['SaaS Subscriptions', 'Hosting Fees', 'Domain Renewal', 'SSL Certificate'],
            'insurance'  => ['Business Insurance', 'Health Insurance', 'Liability Insurance'],
            'taxes'      => ['Corporate Tax', 'VAT Payment', 'Property Tax'],
        ];

        $expenseCount = rand(30, 50);
        $bar = $this->command->getOutput()->createProgressBar($expenseCount);
        $bar->setMessage('Seeding expenses...');
        $bar->start();

        for ($e = 0; $e < $expenseCount; $e++) {
            $category = $expenseCategories[array_rand($expenseCategories)];
            $title = $expenseTemplates[$category][array_rand($expenseTemplates[$category])];

            // Expense date within last 12 months
            $daysDiff = now()->diffInDays($earliestDate);
            $expenseDate = $earliestDate->copy()->addDays(rand(0, $daysDiff));

            $amount = match ($category) {
                'rent'       => round(rand(5000, 15000) / 100, 2) * 100,
                'salaries'   => round(rand(15000, 50000) / 100, 2) * 100,
                'marketing'  => round(rand(500, 5000) / 100, 2) * 100,
                'shipping'   => round(rand(200, 3000) / 100, 2) * 100,
                'utilities'  => round(rand(500, 3000) / 100, 2) * 100,
                'supplies'   => round(rand(100, 1500) / 100, 2) * 100,
                'maintenance'=> round(rand(300, 4000) / 100, 2) * 100,
                'software'   => round(rand(100, 2000) / 100, 2) * 100,
                'insurance'  => round(rand(1000, 5000) / 100, 2) * 100,
                'taxes'      => round(rand(2000, 10000) / 100, 2) * 100,
                default      => round(rand(100, 5000) / 100, 2) * 100,
            };

            Expense::create([
                'title'        => $title,
                'amount'       => $amount,
                'category'     => $category,
                'description'  => $title . ' - ' . $expenseDate->format('F Y'),
                'expense_date' => $expenseDate,
                'created_by'   => $admin->id,
                'created_at'   => $expenseDate,
                'updated_at'   => $expenseDate,
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('  ✅ ' . Expense::count() . ' expenses created');

        // ========================================================================
        // 8. CART ITEMS (some users have items in cart)
        // ========================================================================
        $cartCount = rand(3, 6);
        for ($c = 0; $c < $cartCount; $c++) {
            $user = $clients[array_rand($clients)];
            $product = Product::inRandomOrder()->first();

            // Avoid duplicate product in cart for the same user
            $existing = Cart::where('user_id', $user->id)->where('product_id', $product->id)->first();
            if (!$existing) {
                Cart::create([
                    'user_id'    => $user->id,
                    'product_id' => $product->id,
                    'quantity'   => rand(1, 3),
                ]);
            }
        }
        $this->command->info('  ✅ ' . Cart::count() . ' cart items created');

        // ========================================================================
        // SUMMARY
        // ========================================================================
        $this->command->newLine(2);
        $this->command->info('╔══════════════════════════════════════════╗');
        $this->command->info('║          ✅  SEEDING COMPLETE!          ║');
        $this->command->info('╚══════════════════════════════════════════╝');
        $this->command->newLine();
        $this->command->info('  👤  Admin:    amin@example.com / password');
        $this->command->info('  👥  Clients:  client1-' . count($clients) . '@example.com / password');
        $this->command->newLine();
        $this->command->info('  📊  Stats:');
        $this->command->info('    ' . User::count()          . ' users');
        $this->command->info('    ' . Brand::count()         . ' brands');
        $this->command->info('    ' . Category::count()      . ' categories');
        $this->command->info('    ' . Product::count()       . ' products');
        $this->command->info('    ' . ProductImage::count()  . ' product images');
        $this->command->info('    ' . Address::count()       . ' addresses');
        $this->command->info('    ' . Order::count()         . ' orders');
        $this->command->info('    ' . OrderItem::count()     . ' order items');
        $this->command->info('    ' . Invoice::count()       . ' invoices');
        $this->command->info('    ' . Payment::count()       . ' payments');
        $this->command->info('    ' . Revenue::count()       . ' revenue records');
        $this->command->info('    ' . Expense::count()       . ' expenses');
        $this->command->info('    ' . Cart::count()          . ' cart items');
        $this->command->newLine();
        $this->command->info('  🎉  Dashboard has enough data for all charts!');
    }
}
