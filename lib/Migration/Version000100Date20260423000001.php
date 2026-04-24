<?php

declare(strict_types=1);

namespace OCA\DeductibleLog\Migration;

use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000100Date20260423000001 extends SimpleMigrationStep {

    public function __construct(private IDBConnection $db) {}

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
        $this->seedTaxRates();
        $this->seedItemCategories();
    }

    private function seedTaxRates(): void {
        $rates = [
            [2024, '14.0', '21.0', '67.0'],
            [2025, '14.0', '21.0', '70.0'],
            [2026, '14.0', '20.5', '72.5'],
        ];

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($rates as [$year, $charitable, $medical, $business]) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('id')
               ->from('deductiblelog_tax_rates')
               ->where($qb->expr()->eq('tax_year', $qb->createNamedParameter($year, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
            $result = $qb->executeQuery();
            $exists = $result->fetch();
            $result->closeCursor();

            if ($exists) {
                continue;
            }

            $qb = $this->db->getQueryBuilder();
            $qb->insert('deductiblelog_tax_rates')->values([
                'tax_year'                  => $qb->createNamedParameter($year, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT),
                'mileage_charitable_cents'  => $qb->createNamedParameter($charitable),
                'mileage_medical_cents'     => $qb->createNamedParameter($medical),
                'mileage_business_cents'    => $qb->createNamedParameter($business),
                'updated_at'                => $qb->createNamedParameter($now),
                'source'                    => $qb->createNamedParameter('irs_hardcoded'),
            ]);
            $qb->executeStatement();
        }
    }

    private function seedItemCategories(): void {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from('deductiblelog_item_categories')->setMaxResults(1);
        $result = $qb->executeQuery();
        $exists = $result->fetch();
        $result->closeCursor();

        if ($exists) {
            return;
        }

        // [category, name, min_value, max_value, unit]
        // Source: Salvation Army Donation Value Guide
        $items = [
            // ── Men's Clothing ──────────────────────────────────────────────
            ['clothing', "Men's Suit (2-piece)", '25.00', '75.00', 'each'],
            ['clothing', "Men's Blazer / Sport Coat", '15.00', '45.00', 'each'],
            ['clothing', "Men's Dress Shirt", '4.00', '9.00', 'each'],
            ['clothing', "Men's Polo Shirt", '4.00', '9.00', 'each'],
            ['clothing', "Men's T-Shirt", '2.00', '5.00', 'each'],
            ['clothing', "Men's Casual Shirt (Button-Down)", '4.00', '9.00', 'each'],
            ['clothing', "Men's Flannel Shirt", '4.00', '9.00', 'each'],
            ['clothing', "Men's Sweater", '5.00', '15.00', 'each'],
            ['clothing', "Men's Sweatshirt / Hoodie", '4.00', '12.00', 'each'],
            ['clothing', "Men's Dress Pants", '6.00', '14.00', 'each'],
            ['clothing', "Men's Casual Pants / Chinos", '5.00', '12.00', 'each'],
            ['clothing', "Men's Jeans", '6.00', '14.00', 'each'],
            ['clothing', "Men's Shorts", '3.00', '8.00', 'each'],
            ['clothing', "Men's Athletic / Gym Shorts", '3.00', '7.00', 'each'],
            ['clothing', "Men's Sweatpants / Joggers", '4.00', '10.00', 'each'],
            ['clothing', "Men's Winter Coat / Parka", '25.00', '75.00', 'each'],
            ['clothing', "Men's Light / Spring Jacket", '12.00', '30.00', 'each'],
            ['clothing', "Men's Rain Jacket", '10.00', '25.00', 'each'],
            ['clothing', "Men's Insulated Vest", '8.00', '20.00', 'each'],
            ['clothing', "Men's Dress Shoes", '8.00', '20.00', 'pair'],
            ['clothing', "Men's Casual Shoes / Loafers", '5.00', '15.00', 'pair'],
            ['clothing', "Men's Sneakers / Athletic Shoes", '5.00', '15.00', 'pair'],
            ['clothing', "Men's Boots", '10.00', '30.00', 'pair'],
            ['clothing', "Men's Sandals / Flip-Flops", '2.00', '6.00', 'pair'],
            ['clothing', "Men's Belt", '2.00', '6.00', 'each'],
            ['clothing', "Men's Tie", '1.00', '4.00', 'each'],
            ['clothing', "Men's Hat (Baseball Cap / Beanie)", '2.00', '5.00', 'each'],
            ['clothing', "Men's Gloves", '2.00', '6.00', 'pair'],
            ['clothing', "Men's Scarf", '2.00', '6.00', 'each'],
            // ── Women's Clothing ─────────────────────────────────────────────
            ['clothing', "Women's Casual Dress", '7.00', '18.00', 'each'],
            ['clothing', "Women's Formal / Cocktail Dress", '15.00', '50.00', 'each'],
            ['clothing', "Women's Blouse / Dress Shirt", '4.00', '9.00', 'each'],
            ['clothing', "Women's T-Shirt / Tank Top", '2.00', '5.00', 'each'],
            ['clothing', "Women's Sweater", '5.00', '15.00', 'each'],
            ['clothing', "Women's Cardigan", '5.00', '15.00', 'each'],
            ['clothing', "Women's Sweatshirt / Hoodie", '4.00', '12.00', 'each'],
            ['clothing', "Women's Pants / Trousers", '5.00', '14.00', 'each'],
            ['clothing', "Women's Jeans", '6.00', '14.00', 'each'],
            ['clothing', "Women's Leggings", '3.00', '8.00', 'each'],
            ['clothing', "Women's Shorts", '3.00', '8.00', 'each'],
            ['clothing', "Women's Casual Skirt", '4.00', '12.00', 'each'],
            ['clothing', "Women's Formal Skirt", '6.00', '18.00', 'each'],
            ['clothing', "Women's Suit (Jacket + Pants/Skirt)", '25.00', '75.00', 'set'],
            ['clothing', "Women's Blazer / Jacket", '10.00', '30.00', 'each'],
            ['clothing', "Women's Winter Coat", '25.00', '75.00', 'each'],
            ['clothing', "Women's Light / Spring Jacket", '12.00', '30.00', 'each'],
            ['clothing', "Women's Rain Jacket", '10.00', '25.00', 'each'],
            ['clothing', "Women's Vest", '5.00', '15.00', 'each'],
            ['clothing', "Women's Flats / Loafers", '5.00', '15.00', 'pair'],
            ['clothing', "Women's Heels / Pumps", '7.00', '20.00', 'pair'],
            ['clothing', "Women's Sandals / Flip-Flops", '2.00', '8.00', 'pair'],
            ['clothing', "Women's Sneakers / Athletic Shoes", '5.00', '15.00', 'pair'],
            ['clothing', "Women's Boots (Ankle / Knee)", '10.00', '30.00', 'pair'],
            ['clothing', "Women's Purse / Handbag", '5.00', '25.00', 'each'],
            ['clothing', "Women's Belt", '2.00', '6.00', 'each'],
            ['clothing', "Women's Scarf", '2.00', '6.00', 'each'],
            ['clothing', "Women's Hat", '2.00', '5.00', 'each'],
            ['clothing', "Women's Gloves", '2.00', '6.00', 'pair'],
            // ── Children's Clothing ──────────────────────────────────────────
            ['clothing', "Children's Shirt / Top", '2.00', '6.00', 'each'],
            ['clothing', "Children's T-Shirt", '1.00', '4.00', 'each'],
            ['clothing', "Children's Pants / Jeans", '3.00', '8.00', 'each'],
            ['clothing', "Children's Shorts", '2.00', '5.00', 'each'],
            ['clothing', "Children's Dress / Skirt", '3.00', '8.00', 'each'],
            ['clothing', "Children's Light Jacket / Coat", '5.00', '15.00', 'each'],
            ['clothing', "Children's Winter Coat", '10.00', '30.00', 'each'],
            ['clothing', "Children's Sweater", '3.00', '8.00', 'each'],
            ['clothing', "Children's Sweatshirt / Hoodie", '3.00', '8.00', 'each'],
            ['clothing', "Children's Sneakers", '3.00', '9.00', 'pair'],
            ['clothing', "Children's Dress Shoes", '3.00', '9.00', 'pair'],
            ['clothing', "Children's Boots", '5.00', '15.00', 'pair'],
            ['clothing', "Children's Sandals", '2.00', '5.00', 'pair'],
            ['clothing', "Children's School Uniform Shirt / Pants", '3.00', '8.00', 'each'],
            ['clothing', "Children's Pajamas / Sleepwear", '2.00', '6.00', 'each'],
            ['clothing', "Children's School Backpack", '5.00', '15.00', 'each'],
            ['clothing', "Children's Hat", '1.00', '4.00', 'each'],
            ['clothing', "Children's Gloves / Mittens", '1.00', '4.00', 'pair'],
            ['clothing', "Baby / Toddler Onesie / Clothing", '1.00', '3.00', 'each'],
            ['clothing', "Baby / Toddler Shoes", '2.00', '5.00', 'pair'],
            // ── Furniture ────────────────────────────────────────────────────
            ['furniture', 'Sofa / Couch (2-seat)', '35.00', '150.00', 'each'],
            ['furniture', 'Sofa / Couch (3-seat)', '50.00', '200.00', 'each'],
            ['furniture', 'Loveseat', '35.00', '120.00', 'each'],
            ['furniture', 'Recliner', '25.00', '100.00', 'each'],
            ['furniture', 'Armchair / Club Chair', '20.00', '75.00', 'each'],
            ['furniture', 'Coffee Table', '15.00', '65.00', 'each'],
            ['furniture', 'End Table / Side Table', '8.00', '30.00', 'each'],
            ['furniture', 'Dining Table', '40.00', '150.00', 'each'],
            ['furniture', 'Dining Chair', '8.00', '25.00', 'each'],
            ['furniture', 'Desk (Computer / Writing)', '25.00', '100.00', 'each'],
            ['furniture', 'Office Chair', '15.00', '60.00', 'each'],
            ['furniture', 'Bookcase / Bookshelf', '15.00', '65.00', 'each'],
            ['furniture', 'Dresser (6-drawer)', '25.00', '100.00', 'each'],
            ['furniture', 'Dresser (4-drawer)', '20.00', '75.00', 'each'],
            ['furniture', 'Nightstand', '10.00', '40.00', 'each'],
            ['furniture', 'Bed Frame (Twin)', '15.00', '65.00', 'each'],
            ['furniture', 'Bed Frame (Full / Double)', '25.00', '90.00', 'each'],
            ['furniture', 'Bed Frame (Queen)', '40.00', '150.00', 'each'],
            ['furniture', 'Headboard', '10.00', '50.00', 'each'],
            ['furniture', 'TV Stand / Entertainment Center', '20.00', '75.00', 'each'],
            ['furniture', 'Ottoman', '10.00', '40.00', 'each'],
            ['furniture', 'Rocking Chair', '15.00', '60.00', 'each'],
            ['furniture', 'Bench', '10.00', '40.00', 'each'],
            ['furniture', 'Floor Lamp', '10.00', '35.00', 'each'],
            ['furniture', 'Table Lamp', '5.00', '20.00', 'each'],
            ['furniture', 'Shelving Unit (Metal / Wire)', '10.00', '40.00', 'each'],
            ['furniture', 'Filing Cabinet (2-drawer)', '15.00', '50.00', 'each'],
            ['furniture', 'Filing Cabinet (4-drawer)', '25.00', '80.00', 'each'],
            // ── Electronics ──────────────────────────────────────────────────
            ['electronics', 'Television (under 32")', '25.00', '100.00', 'each'],
            ['electronics', 'Television (32"–55")', '50.00', '200.00', 'each'],
            ['electronics', 'Television (56"+)', '100.00', '350.00', 'each'],
            ['electronics', 'DVD / Blu-ray Player', '5.00', '20.00', 'each'],
            ['electronics', 'Stereo / Shelf System', '15.00', '75.00', 'each'],
            ['electronics', 'Radio / Clock Radio', '4.00', '12.00', 'each'],
            ['electronics', 'Bluetooth Speaker (Portable)', '5.00', '30.00', 'each'],
            ['electronics', 'Sound Bar', '10.00', '50.00', 'each'],
            ['electronics', 'Desktop Computer', '50.00', '150.00', 'each'],
            ['electronics', 'Laptop Computer', '50.00', '200.00', 'each'],
            ['electronics', 'Tablet', '25.00', '100.00', 'each'],
            ['electronics', 'Smartphone', '25.00', '100.00', 'each'],
            ['electronics', 'Digital Camera', '10.00', '75.00', 'each'],
            ['electronics', 'Camcorder', '10.00', '50.00', 'each'],
            ['electronics', 'Printer (Inkjet)', '5.00', '25.00', 'each'],
            ['electronics', 'Printer (Laser)', '15.00', '75.00', 'each'],
            ['electronics', 'Scanner', '5.00', '30.00', 'each'],
            ['electronics', 'Computer Monitor', '15.00', '75.00', 'each'],
            ['electronics', 'Gaming Console', '25.00', '100.00', 'each'],
            ['electronics', 'Headphones', '3.00', '25.00', 'each'],
            // ── Appliances (small) ───────────────────────────────────────────
            ['appliances', 'Microwave Oven', '10.00', '50.00', 'each'],
            ['appliances', 'Toaster (2-slice)', '3.00', '8.00', 'each'],
            ['appliances', 'Toaster Oven', '8.00', '25.00', 'each'],
            ['appliances', 'Coffee Maker (Drip)', '4.00', '20.00', 'each'],
            ['appliances', 'Single-Serve Coffee Maker (Keurig-style)', '10.00', '40.00', 'each'],
            ['appliances', 'Blender', '4.00', '15.00', 'each'],
            ['appliances', 'Food Processor', '5.00', '30.00', 'each'],
            ['appliances', 'Stand Mixer', '15.00', '65.00', 'each'],
            ['appliances', 'Hand / Electric Mixer', '4.00', '12.00', 'each'],
            ['appliances', 'Waffle Maker', '4.00', '12.00', 'each'],
            ['appliances', 'Rice Cooker', '5.00', '20.00', 'each'],
            ['appliances', 'Air Fryer', '10.00', '35.00', 'each'],
            ['appliances', 'Slow Cooker / Crock-Pot', '5.00', '20.00', 'each'],
            ['appliances', 'Instant Pot / Pressure Cooker', '15.00', '50.00', 'each'],
            ['appliances', 'Electric Griddle', '5.00', '20.00', 'each'],
            ['appliances', 'Contact Grill (George Foreman-style)', '5.00', '15.00', 'each'],
            ['appliances', 'Electric Skillet', '5.00', '20.00', 'each'],
            ['appliances', 'Bread Machine', '10.00', '35.00', 'each'],
            ['appliances', 'Juicer', '5.00', '20.00', 'each'],
            ['appliances', 'Personal Blender / Smoothie Maker', '4.00', '12.00', 'each'],
            ['appliances', 'Iron', '3.00', '10.00', 'each'],
            ['appliances', 'Steam Iron / Garment Steamer', '5.00', '20.00', 'each'],
            ['appliances', 'Handheld Vacuum', '5.00', '20.00', 'each'],
            ['appliances', 'Box Fan', '3.00', '12.00', 'each'],
            ['appliances', 'Window Fan', '5.00', '15.00', 'each'],
            ['appliances', 'Floor / Pedestal Fan', '5.00', '20.00', 'each'],
            ['appliances', 'Space Heater', '5.00', '25.00', 'each'],
            ['appliances', 'Humidifier', '5.00', '20.00', 'each'],
            ['appliances', 'Dehumidifier', '10.00', '40.00', 'each'],
            ['appliances', 'Air Purifier', '10.00', '50.00', 'each'],
            // ── Appliances (large) ───────────────────────────────────────────
            ['appliances', 'Washer (Top-Load)', '50.00', '150.00', 'each'],
            ['appliances', 'Washer (Front-Load)', '75.00', '200.00', 'each'],
            ['appliances', 'Dryer (Electric)', '50.00', '150.00', 'each'],
            ['appliances', 'Refrigerator (Standard)', '75.00', '250.00', 'each'],
            ['appliances', 'Refrigerator (Mini / Compact)', '20.00', '75.00', 'each'],
            ['appliances', 'Dishwasher', '50.00', '150.00', 'each'],
            ['appliances', 'Stove / Range', '75.00', '200.00', 'each'],
            ['appliances', 'Chest Freezer', '30.00', '100.00', 'each'],
            // ── Household Items ───────────────────────────────────────────────
            ['other', 'Bath Towel', '1.00', '3.00', 'each'],
            ['other', 'Hand Towel', '0.50', '2.00', 'each'],
            ['other', 'Bath Towel Set (3-piece)', '3.00', '8.00', 'set'],
            ['other', 'Bed Sheet Set (Twin)', '4.00', '10.00', 'set'],
            ['other', 'Bed Sheet Set (Full)', '5.00', '12.00', 'set'],
            ['other', 'Bed Sheet Set (Queen)', '6.00', '15.00', 'set'],
            ['other', 'Pillow', '2.00', '5.00', 'each'],
            ['other', 'Blanket / Throw', '3.00', '10.00', 'each'],
            ['other', 'Comforter / Duvet (Twin)', '8.00', '20.00', 'each'],
            ['other', 'Comforter / Duvet (Full/Queen)', '10.00', '30.00', 'each'],
            ['other', 'Curtains / Drapes (pair)', '5.00', '15.00', 'pair'],
            ['other', 'Area Rug (Small, under 4×6)', '10.00', '35.00', 'each'],
            ['other', 'Area Rug (Medium, 5×7–6×9)', '20.00', '75.00', 'each'],
            ['other', 'Area Rug (Large, 8×10+)', '35.00', '100.00', 'each'],
            ['other', 'Dinner Plate', '1.00', '2.00', 'each'],
            ['other', 'Dish Set (4-place setting)', '5.00', '15.00', 'set'],
            ['other', 'Dish Set (8-place setting)', '8.00', '25.00', 'set'],
            ['other', 'Bowl (Soup / Cereal)', '1.00', '2.00', 'each'],
            ['other', 'Mug / Cup', '1.00', '2.00', 'each'],
            ['other', 'Drinking Glass', '0.50', '2.00', 'each'],
            ['other', 'Pot (Sauce / Stock)', '3.00', '10.00', 'each'],
            ['other', 'Pan (Frying / Sauté)', '3.00', '10.00', 'each'],
            ['other', 'Baking Dish / Casserole', '2.00', '8.00', 'each'],
            ['other', 'Pot and Pan Set', '10.00', '40.00', 'set'],
            ['other', 'Flatware / Silverware Set (4-place)', '4.00', '10.00', 'set'],
            ['other', 'Flatware / Silverware Set (8-place)', '6.00', '18.00', 'set'],
            ['other', 'Knife Block / Set', '10.00', '30.00', 'set'],
            ['other', 'Cutting Board', '2.00', '6.00', 'each'],
            ['other', 'Mixing Bowl Set', '3.00', '10.00', 'set'],
            ['other', 'Plastic Storage Container Set', '3.00', '10.00', 'set'],
            ['other', 'Canister Set', '3.00', '10.00', 'set'],
            ['other', 'Vase', '2.00', '8.00', 'each'],
            ['other', 'Picture Frame', '1.00', '5.00', 'each'],
            ['other', 'Wall Clock', '3.00', '12.00', 'each'],
            ['other', 'Wall Mirror / Decorative Mirror', '5.00', '25.00', 'each'],
            // ── Books & Media ─────────────────────────────────────────────────
            ['other', 'Book (Hardcover, Adult)', '1.00', '3.00', 'each'],
            ['other', 'Book (Paperback, Adult)', '0.50', '2.00', 'each'],
            ["other", "Children's Book (Hardcover)", '0.50', '2.00', 'each'],
            ["other", "Children's Book (Paperback)", '0.25', '1.00', 'each'],
            ['other', 'Textbook (College / High School)', '3.00', '15.00', 'each'],
            ['other', 'DVD / Movie', '1.00', '4.00', 'each'],
            ['other', 'Blu-ray Movie', '2.00', '5.00', 'each'],
            ['other', 'CD / Music Album', '1.00', '3.00', 'each'],
            ['other', 'Record / Vinyl Album', '1.00', '5.00', 'each'],
            ['other', 'Video Game (Console)', '2.00', '15.00', 'each'],
            ['other', 'Video Game (Handheld / Older)', '1.00', '5.00', 'each'],
            ['other', 'Puzzle (1000-piece, Adult)', '2.00', '5.00', 'each'],
            // ── Sporting Goods ────────────────────────────────────────────────
            ['other', 'Bicycle (Adult)', '25.00', '100.00', 'each'],
            ['other', "Bicycle (Child's)", '10.00', '50.00', 'each'],
            ['other', 'Bicycle Helmet', '5.00', '20.00', 'each'],
            ['other', 'Bicycle Lock', '3.00', '10.00', 'each'],
            ['other', 'Golf Club Set (Full)', '30.00', '150.00', 'set'],
            ['other', 'Golf Club (Individual)', '2.00', '15.00', 'each'],
            ['other', 'Golf Bag', '10.00', '40.00', 'each'],
            ['other', 'Tennis Racket', '5.00', '20.00', 'each'],
            ['other', 'Tennis Balls (3-pack)', '1.00', '3.00', 'set'],
            ['other', 'Baseball Bat', '5.00', '20.00', 'each'],
            ['other', 'Baseball Glove / Mitt', '5.00', '25.00', 'each'],
            ['other', 'Football', '5.00', '20.00', 'each'],
            ['other', 'Basketball', '5.00', '20.00', 'each'],
            ['other', 'Soccer Ball', '4.00', '15.00', 'each'],
            ['other', 'Volleyball', '4.00', '12.00', 'each'],
            ['other', 'Skis (Adult, pair)', '25.00', '100.00', 'pair'],
            ['other', 'Ski Boots (Adult)', '15.00', '50.00', 'pair'],
            ['other', 'Ski Poles (pair)', '5.00', '20.00', 'pair'],
            ['other', 'Snowboard', '20.00', '80.00', 'each'],
            ['other', 'Roller Skates / Rollerblades', '10.00', '35.00', 'pair'],
            ['other', 'Ice Skates', '8.00', '25.00', 'pair'],
            ['other', 'Weight Bench', '20.00', '80.00', 'each'],
            ['other', 'Dumbbells (pair, up to 20 lb)', '5.00', '25.00', 'pair'],
            ['other', 'Yoga / Exercise Mat', '3.00', '10.00', 'each'],
            ['other', 'Jump Rope', '1.00', '4.00', 'each'],
            // ── Toys & Games ─────────────────────────────────────────────────
            ['other', 'Board Game (Standard)', '2.00', '10.00', 'each'],
            ['other', 'Card Game', '1.00', '5.00', 'each'],
            ["other", "Children's Puzzle (under 100 pieces)", '1.00', '4.00', 'each'],
            ['other', 'Stuffed Animal / Plush Toy', '1.00', '5.00', 'each'],
            ['other', 'Action Figure / Doll', '2.00', '8.00', 'each'],
            ['other', 'Lego / Building Block Set', '5.00', '25.00', 'set'],
            ['other', 'Remote Control Car / Toy', '5.00', '20.00', 'each'],
            ['other', 'Train Set', '10.00', '40.00', 'set'],
            ['other', 'Play Kitchen / Playhouse (Plastic)', '10.00', '35.00', 'each'],
            ['other', 'Toy Tool Set', '3.00', '10.00', 'set'],
            ['other', 'Science Kit / Educational Toy', '3.00', '12.00', 'each'],
            ['other', 'Art Supplies Kit', '3.00', '10.00', 'set'],
            ["other", "Children's Lunch Box / Bag", '2.00', '6.00', 'each'],
            // ── Musical Instruments ───────────────────────────────────────────
            ['other', 'Guitar (Acoustic)', '15.00', '75.00', 'each'],
            ['other', 'Guitar (Electric, with Amp)', '25.00', '100.00', 'set'],
            ['other', 'Keyboard / Electronic Piano', '20.00', '80.00', 'each'],
            ['other', 'Violin', '15.00', '60.00', 'each'],
            ['other', 'Flute / Clarinet', '15.00', '50.00', 'each'],
            ['other', 'Trumpet / Brass Instrument', '20.00', '75.00', 'each'],
            ['other', 'Drum Kit (Partial or Full)', '30.00', '100.00', 'set'],
            ['other', 'Ukulele', '10.00', '40.00', 'each'],
            // ── Baby & Infant ─────────────────────────────────────────────────
            ['other', 'Crib / Toddler Bed', '25.00', '75.00', 'each'],
            ['other', 'High Chair', '10.00', '35.00', 'each'],
            ['other', 'Baby Swing / Bouncer', '10.00', '30.00', 'each'],
            ['other', 'Stroller / Baby Carriage', '15.00', '75.00', 'each'],
            ['other', 'Baby Monitor', '5.00', '20.00', 'each'],
            ['other', "Pack 'n Play / Playpen", '10.00', '35.00', 'each'],
            ['other', 'Car Seat (Infant / Toddler)', '15.00', '50.00', 'each'],
            ['other', 'Diaper Bag', '5.00', '15.00', 'each'],
            ['other', 'Baby Carrier / Wrap', '5.00', '20.00', 'each'],
            ['other', 'Baby Bathtub', '3.00', '8.00', 'each'],
            // ── Garden & Outdoor ──────────────────────────────────────────────
            ['other', 'Garden Hose (25 ft)', '5.00', '15.00', 'each'],
            ['other', 'Push Lawn Mower', '25.00', '75.00', 'each'],
            ['other', 'Leaf Blower (Electric)', '10.00', '30.00', 'each'],
            ['other', 'Patio Chair (Plastic / Resin)', '5.00', '15.00', 'each'],
            ['other', 'Patio Table (Plastic / Metal)', '10.00', '35.00', 'each'],
            ['other', 'Outdoor Umbrella', '10.00', '30.00', 'each'],
            ['other', 'Wheelbarrow', '10.00', '35.00', 'each'],
            ['other', 'Rake / Shovel (Garden Tool)', '2.00', '6.00', 'each'],
            ['other', 'Bird Feeder', '3.00', '10.00', 'each'],
            ['other', 'Flower Pot / Planter (Large)', '3.00', '10.00', 'each'],
            // ── Luggage & Bags ────────────────────────────────────────────────
            ['other', 'Suitcase (Small / Carry-On)', '5.00', '25.00', 'each'],
            ['other', 'Suitcase (Large / Checked)', '10.00', '40.00', 'each'],
            ['other', 'Duffle Bag (Medium)', '4.00', '15.00', 'each'],
            ['other', 'Backpack (Adult)', '5.00', '20.00', 'each'],
            ['other', 'Tote Bag (Large)', '2.00', '8.00', 'each'],
            ['other', 'Briefcase / Laptop Bag', '5.00', '20.00', 'each'],
            // ── Pet Supplies ──────────────────────────────────────────────────
            ['other', 'Dog Crate / Kennel (Medium)', '10.00', '40.00', 'each'],
            ['other', 'Pet Carrier', '5.00', '20.00', 'each'],
            ['other', 'Dog Bed', '4.00', '15.00', 'each'],
            ['other', 'Pet Food Bowl Set', '2.00', '6.00', 'set'],
            ['other', 'Pet Supplies (Misc. Accessories)', '1.00', '5.00', 'set'],
            // ── Holiday / Seasonal ────────────────────────────────────────────
            ['other', 'Artificial Christmas Tree (6–7 ft)', '10.00', '30.00', 'each'],
            ['other', 'Christmas Tree Decorations (box)', '3.00', '10.00', 'set'],
            ['other', 'Christmas Lights (strand)', '1.00', '5.00', 'each'],
            ['other', 'Wreath (Holiday)', '3.00', '10.00', 'each'],
            ['other', 'Halloween Decoration (Large)', '2.00', '8.00', 'each'],
            ['other', 'Toolbox (Basic)', '10.00', '30.00', 'each'],
        ];

        foreach ($items as [$category, $name, $minValue, $maxValue, $unit]) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('deductiblelog_item_categories')->values([
                'category'  => $qb->createNamedParameter($category),
                'name'      => $qb->createNamedParameter($name),
                'min_value' => $qb->createNamedParameter($minValue),
                'max_value' => $qb->createNamedParameter($maxValue),
                'unit'      => $qb->createNamedParameter($unit),
                'source'    => $qb->createNamedParameter('salvation_army'),
            ]);
            $qb->executeStatement();
        }
    }
}
