<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Fills the database with realistic demo data for the portfolio:
 *   - 3 restaurants, each with 5-8 menu items
 *   - 2 customer accounts + 2 rider accounts (password123)
 *   - 5 completed orders, each with a full status history
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure roles + categories exist (safe to run on their own).
        $this->call(RoleSeeder::class);
        $this->call(CategorySeeder::class);

        $categories = Category::all()->keyBy('name');

        // -------- test accounts --------
        $customer1 = $this->makeUser('Demo Customer One', 'customer1@demo.com', 'customer', '0300-1111111');
        $customer2 = $this->makeUser('Demo Customer Two', 'customer2@demo.com', 'customer', '0300-2222222');

        $rider1 = $this->makeUser('Demo Rider One', 'rider1@demo.com', 'rider', '0300-3333333', ['lat' => 21.7592, 'lng' => 72.1448]);
        $rider2 = $this->makeUser('Demo Rider Two', 'rider2@demo.com', 'rider', '0300-4444444', ['lat' => 21.7681, 'lng' => 72.1505]);

        // -------- restaurants + menus --------
        $bella = $this->makeRestaurant('Bella Italia', 'Authentic Italian pizzas and pastas.', 'Waghawadi Road, Bhavnagar', 21.7580, 72.1450, 4.7);
        $this->seedMenu($bella, $categories, [
            ['Margherita Pizza', 'Pizza', 9.99, 'Classic tomato, mozzarella and basil.'],
            ['Pepperoni Pizza', 'Pizza', 11.99, 'Loaded with spicy pepperoni.'],
            ['Quattro Formaggi Pizza', 'Pizza', 12.99, 'Four-cheese pizza.'],
            ['Caesar Salad', 'Salads', 6.99, 'Romaine, parmesan and croutons.'],
            ['Tiramisu', 'Desserts', 5.49, 'Coffee-soaked Italian dessert.'],
            ['Italian Soda', 'Drinks', 2.99, 'Sparkling fruit soda.'],
        ]);

        $burger = $this->makeRestaurant('Burger Barn', 'Juicy hand-pressed burgers and fries.', 'Kalanala, Bhavnagar', 21.7700, 72.1520, 4.4);
        $this->seedMenu($burger, $categories, [
            ['Classic Cheeseburger', 'Burgers', 8.49, 'Beef patty with cheddar.'],
            ['Double Bacon Burger', 'Burgers', 10.99, 'Double patty with crispy bacon.'],
            ['Veggie Burger', 'Burgers', 7.99, 'Grilled veggie patty.'],
            ['Crispy Fries', null, 3.49, 'Golden seasoned fries.'],
            ['Chocolate Brownie', 'Desserts', 4.49, 'Warm fudgy brownie.'],
            ['Fresh Lemonade', 'Drinks', 2.49, 'Freshly squeezed.'],
            ['Garden Salad', 'Salads', 5.99, 'Mixed greens and veggies.'],
        ]);

        $sushi = $this->makeRestaurant('Sushi Zen', 'Fresh sushi and Japanese favourites.', 'Jewel Circle, Bhavnagar', 21.7620, 72.1380, 4.8);
        $this->seedMenu($sushi, $categories, [
            ['California Roll', 'Sushi', 6.99, 'Crab, avocado and cucumber.'],
            ['Salmon Nigiri', 'Sushi', 7.49, 'Fresh salmon over rice.'],
            ['Spicy Tuna Roll', 'Sushi', 8.49, 'Tuna with a spicy kick.'],
            ['Dragon Roll', 'Sushi', 11.99, 'Eel and avocado signature roll.'],
            ['Edamame', null, 4.49, 'Steamed salted soybeans.'],
            ['Seaweed Salad', 'Salads', 4.99, 'Marinated seaweed.'],
            ['Mochi Ice Cream', 'Desserts', 5.49, 'Sweet rice-cake ice cream.'],
            ['Green Tea', 'Drinks', 1.99, 'Traditional hot green tea.'],
        ]);

        // -------- 5 completed orders (only seed once) --------
        if (Order::query()->doesntExist()) {
            $this->makeCompletedOrder($customer1, $bella, $rider1, ['Margherita Pizza' => 1, 'Tiramisu' => 2, 'Italian Soda' => 1], 5, 'House 12, Waghawadi Road, Bhavnagar', 21.7550, 72.1460);
            $this->makeCompletedOrder($customer1, $burger, $rider2, ['Double Bacon Burger' => 2, 'Crispy Fries' => 1, 'Fresh Lemonade' => 2], 4, 'Flat 3B, Takhteshwar, Bhavnagar', 21.7610, 72.1500);
            $this->makeCompletedOrder($customer2, $sushi, $rider1, ['California Roll' => 1, 'Salmon Nigiri' => 2, 'Green Tea' => 1], 3, 'Office 7, Jewel Circle, Bhavnagar', 21.7630, 72.1390);
            $this->makeCompletedOrder($customer2, $bella, $rider2, ['Pepperoni Pizza' => 1, 'Caesar Salad' => 1], 2, 'House 88, Ghogha Circle, Bhavnagar', 21.7710, 72.1550);
            $this->makeCompletedOrder($customer1, $sushi, $rider1, ['Dragon Roll' => 1, 'Edamame' => 1, 'Mochi Ice Cream' => 2], 1, 'Apartment 21, Hill Drive, Bhavnagar', 21.7520, 72.1410);
        }
    }

    /** Create (or fetch) a user and give them the matching role. */
    private function makeUser(string $name, string $email, string $role, ?string $phone = null, array $extra = []): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'name'      => $name,
                'password'  => 'password123', // auto-hashed by the model
                'role'      => $role,
                'phone'     => $phone,
                'is_active' => true,
            ], $extra)
        );

        $user->assignRole(Role::findOrCreate($role, 'api'));

        return $user;
    }

    /** Create (or fetch) a restaurant owned by a fresh restaurant_owner. */
    private function makeRestaurant(string $name, string $desc, string $address, float $lat, float $lng, float $rating): Restaurant
    {
        $slug  = str_replace(' ', '', strtolower($name));
        $owner = $this->makeUser("$name Owner", "owner.$slug@demo.com", 'restaurant_owner');

        $imageSlug = str_replace(' ', '_', strtolower($name));

        return Restaurant::updateOrCreate(
            ['name' => $name],
            [
                'user_id'     => $owner->id,
                'description' => $desc,
                'address'     => $address,
                'lat'         => $lat,
                'lng'         => $lng,
                'banner'      => "restaurants/{$imageSlug}_banner.jpg",
                'is_open'     => true,
                'rating'      => $rating,
            ]
        );
    }

    /** Seed a restaurant's menu. Each item = [name, categoryName|null, price, description]. */
    private function seedMenu(Restaurant $restaurant, $categories, array $items): void
    {
        foreach ($items as [$name, $categoryName, $price, $description]) {
            MenuItem::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'name' => $name],
                [
                    'category_id'  => $categoryName ? $categories->get($categoryName)?->id : null,
                    'description'  => $description,
                    'price'        => $price,
                    'image'        => 'menu-items/' . strtolower(str_replace(' ', '_', $name)) . '.jpg',
                    'is_available' => true,
                ]
            );
        }
    }

    /**
     * Create a delivered order with a believable status timeline.
     *
     * @param  array<string, int>  $itemQuantities  map of menu item name => quantity
     */
    private function makeCompletedOrder(User $customer, Restaurant $restaurant, User $rider, array $itemQuantities, int $daysAgo, string $address, float $lat, float $lng): void
    {
        $placedAt = now()->subDays($daysAgo)->setTime(12, 30);

        // 1) resolve the items and add up the subtotal
        $resolved = [];
        $subtotal = 0;
        foreach ($itemQuantities as $name => $qty) {
            $menuItem = $restaurant->menuItems()->where('name', $name)->first();
            if (! $menuItem) {
                continue;
            }
            $line = round((float) $menuItem->price * $qty, 2);
            $subtotal += $line;
            $resolved[] = [$menuItem, $qty, $line];
        }
        $subtotal = round($subtotal, 2);
        $total    = $subtotal + 2; // flat $2 delivery fee

        // 2) create the order with a back-dated created_at
        $order = new Order([
            'customer_id'   => $customer->id,
            'restaurant_id' => $restaurant->id,
            'rider_id'      => $rider->id,
            'status'        => OrderStatus::DELIVERED,
            'subtotal'      => $subtotal,
            'delivery_fee'  => 2,
            'total'         => $total,
            'address'       => $address,
            'lat'           => $lat,
            'lng'           => $lng,
            'notes'         => 'Please ring the doorbell.',
        ]);
        $order->created_at = $placedAt;
        $order->updated_at = (clone $placedAt)->addMinutes(45);
        $order->save();

        // 3) order items
        foreach ($resolved as [$menuItem, $qty, $line]) {
            $order->items()->create([
                'menu_item_id' => $menuItem->id,
                'quantity'     => $qty,
                'unit_price'   => $menuItem->price,
                'subtotal'     => $line,
            ]);
        }

        // 4) full status timeline (each entry is back-dated by some minutes)
        $timeline = [
            [OrderStatus::PENDING,          'Order placed by customer.',          0],
            [OrderStatus::ACCEPTED,         'Restaurant accepted the order.',     3],
            [OrderStatus::PREPARING,        'Restaurant is preparing the food.',  7],
            [OrderStatus::READY_FOR_PICKUP, 'Order is ready for pickup.',         20],
            [OrderStatus::PICKED_UP,        "Picked up by rider {$rider->name}.", 26],
            [OrderStatus::DELIVERED,        "Delivered by rider {$rider->name}.", 45],
        ];
        foreach ($timeline as [$status, $note, $offsetMinutes]) {
            $entry = $order->statusHistory()->make([
                'status' => $status->value,
                'note'   => $note,
            ]);
            $entry->created_at = (clone $placedAt)->addMinutes($offsetMinutes);
            $entry->save();
        }
    }
}
