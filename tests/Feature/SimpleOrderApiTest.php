<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CityShippingCost;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimpleOrderApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private Seller $seller;
    private Governorate $governorate;
    private Product $product;

    /** @var string Bearer token for $this->user (API auth) */
    private string $token;

    private const ENDPOINT = '/api/v1/customer/order/place-simple';

    protected function setUp(): void
    {
        parent::setUp();

        // So Product::scopeActive() includes seller products (not only admin)
        Cache::put('business_mode', 'multi');
        Cache::put('digital_product', '0');
        Cache::put('product_brand', '0');

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->accessToken;

        $this->seller = Seller::create([
            'f_name'   => 'Test',
            'l_name'   => 'Seller',
            'phone'    => '+96655500001',
            'email'    => 'seller@test.com',
            'password' => bcrypt('password'),
            'status'   => 'approved',
        ]);

        $this->governorate = Governorate::create(['name_ar' => 'الرياض']);
        $this->governorate->sellers()->attach($this->seller->id);

        CityShippingCost::create([
            'governorate_id' => $this->governorate->id,
            'cost'           => 25.00,
        ]);

        $this->product = Product::create([
            'user_id'        => $this->seller->id,
            'added_by'       => 'seller',
            'name'           => 'Test Product',
            'slug'           => 'test-product-' . Str::random(5),
            'product_type'   => 'physical',
            'status'         => 1,
            'request_status' => 1,
            'current_stock'  => 100,
            'unit_price'     => 50.00,
            'tax'            => 0,
            'tax_type'       => 'percent',
            'tax_model'      => 'exclude',
            'discount'       => 0,
            'discount_type'  => 'flat',
            'variation'      => '[]',
            'brand_id'       => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    private function addCartForUser(int $quantity = 1, float $price = 50.00, float $discount = 0): void
    {
        Cart::create([
            'customer_id'   => $this->user->id,
            'is_guest'      => 0,
            'cart_group_id' => Str::uuid()->toString(),
            'product_id'    => $this->product->id,
            'product_type'  => 'physical',
            'quantity'      => $quantity,
            'price'         => $price,
            'tax'           => 0,
            'discount'      => $discount,
            'tax_model'     => 'exclude',
            'is_checked'    => 1,
            'seller_id'     => $this->seller->id,
            'seller_is'     => 'seller',
            'variant'       => null,
            'variations'    => '[]',
        ]);
    }

    private function addCartForGuest(int|string $guestId, int $quantity = 1): void
    {
        Cart::create([
            'customer_id'   => $guestId,
            'is_guest'      => 1,
            'cart_group_id' => Str::uuid()->toString(),
            'product_id'    => $this->product->id,
            'product_type'  => 'physical',
            'quantity'      => $quantity,
            'price'         => 50.00,
            'tax'           => 0,
            'discount'      => 0,
            'tax_model'     => 'exclude',
            'is_checked'    => 1,
            'seller_id'     => $this->seller->id,
            'seller_is'     => 'seller',
            'variant'       => null,
            'variations'    => '[]',
        ]);
    }

    private function validPayload(): array
    {
        return [
            'customer_name' => 'John Doe',
            'phone'         => '+966501234567',
            'address'       => '123 Main Street, Riyadh',
            'city_id'       => $this->governorate->id,
            'order_note'    => 'Please deliver fast',
        ];
    }

    // -------------------------------------------------------------------------
    // Success: authenticated user
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_place_simple_order(): void
    {
        $this->addCartForUser(2);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $response->assertOk()
            ->assertJsonStructure(['message', 'order_id']);

        $this->assertDatabaseHas('orders', [
            'order_type'     => 'default_type',
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'order_status'   => 'pending',
        ]);
    }

    public function test_seller_is_auto_resolved_from_governorate(): void
    {
        $this->addCartForUser();

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $response->assertOk();
        $orderId = $response->json('order_id');

        $this->assertDatabaseHas('orders', [
            'id'        => $orderId,
            'seller_id' => $this->seller->id,
            'seller_is' => 'seller',
        ]);
    }

    public function test_shipping_cost_is_auto_resolved_from_governorate(): void
    {
        $this->addCartForUser();

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $response->assertOk();
        $orderId = $response->json('order_id');

        $this->assertEquals(25.00, (float) Order::find($orderId)->shipping_cost);
    }

    public function test_shipping_address_data_stored_as_json(): void
    {
        $this->addCartForUser();

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $response->assertOk();
        $orderId = $response->json('order_id');
        $addressData = Order::find($orderId)->shipping_address_data;
        $address = is_string($addressData) ? json_decode($addressData, true) : (array) $addressData;

        $this->assertEquals('John Doe', $address['contact_person_name']);
        $this->assertEquals('+966501234567', $address['phone']);
        $this->assertEquals('123 Main Street, Riyadh', $address['address']);
        $this->assertEquals($this->governorate->id, $address['city_id']);
    }

    public function test_order_amount_is_calculated_correctly(): void
    {
        // price=50, qty=2, discount=0, tax=0, shipping=25 → amount=125
        $this->addCartForUser(2, 50.00, 0);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $response->assertOk();
        $orderId = $response->json('order_id');

        $this->assertEquals(125.00, (float) Order::find($orderId)->order_amount);
    }

    public function test_order_amount_accounts_for_discount(): void
    {
        // price=50, qty=2, discount=5 per unit → (50-5)*2 + shipping=25 = 115
        $this->addCartForUser(2, 50.00, 5.00);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $response->assertOk();
        $orderId = $response->json('order_id');

        $this->assertEquals(115.00, (float) Order::find($orderId)->order_amount);
    }

    public function test_product_stock_decreases_after_order(): void
    {
        $this->addCartForUser(3);

        $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders())
            ->assertOk();

        $this->assertEquals(97, $this->product->fresh()->current_stock);
    }

    public function test_cart_items_deleted_after_order(): void
    {
        $this->addCartForUser();

        $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseMissing('carts', [
            'customer_id' => $this->user->id,
            'product_id'  => $this->product->id,
        ]);
    }

    public function test_order_detail_is_created_with_correct_data(): void
    {
        $this->addCartForUser(2);

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $orderId = $response->json('order_id');

        $this->assertDatabaseHas('order_details', [
            'order_id'       => $orderId,
            'product_id'     => $this->product->id,
            'qty'            => 2,
            'payment_status' => 'unpaid',
            'delivery_status' => 'pending',
        ]);
    }

    public function test_order_status_history_is_created(): void
    {
        $this->addCartForUser();

        $response = $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders());

        $orderId = $response->json('order_id');

        $this->assertDatabaseHas('order_status_histories', [
            'order_id'  => $orderId,
            'status'    => 'pending',
            'user_type' => 'customer',
        ]);
    }

    public function test_governorate_without_shipping_cost_defaults_to_zero(): void
    {
        $noShipGovernorate = Governorate::create(['name_ar' => 'بلا شحن']);
        $noShipGovernorate->sellers()->attach($this->seller->id);
        // No CityShippingCost created

        $this->addCartForUser(1, 50.00);

        $response = $this->postJson(self::ENDPOINT, array_merge($this->validPayload(), [
                'city_id' => $noShipGovernorate->id,
            ]), $this->authHeaders());

        $response->assertOk();
        $orderId = $response->json('order_id');

        // order_amount = 50 - 0 + 0 + 0 = 50
        $this->assertEquals(50.00, (float) Order::find($orderId)->order_amount);
        $this->assertEquals(0.00, (float) Order::find($orderId)->shipping_cost);
    }

    // -------------------------------------------------------------------------
    // Success: guest user
    // -------------------------------------------------------------------------

    public function test_guest_can_place_order_with_guest_id(): void
    {
        $guestId = 123456;
        $this->addCartForGuest($guestId);

        $payload = array_merge($this->validPayload(), ['guest_id' => (string) $guestId]);

        $response = $this->postJson(self::ENDPOINT, $payload);

        $response->assertOk()
            ->assertJsonStructure(['message', 'order_id']);

        $this->assertDatabaseHas('orders', [
            'is_guest'       => 1,
            'order_type'     => 'default_type',
            'payment_method' => 'cash_on_delivery',
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation errors
    // -------------------------------------------------------------------------

    public function test_returns_422_when_customer_name_is_missing(): void
    {
        $this->addCartForUser();
        $payload = $this->validPayload();
        unset($payload['customer_name']);

        $this->postJson(self::ENDPOINT, $payload, $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_name']);
    }

    public function test_returns_422_when_phone_is_missing(): void
    {
        $this->addCartForUser();
        $payload = $this->validPayload();
        unset($payload['phone']);

        $this->postJson(self::ENDPOINT, $payload, $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_returns_422_when_address_is_missing(): void
    {
        $this->addCartForUser();
        $payload = $this->validPayload();
        unset($payload['address']);

        $this->postJson(self::ENDPOINT, $payload, $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    public function test_returns_422_when_city_id_is_missing(): void
    {
        $this->addCartForUser();
        $payload = $this->validPayload();
        unset($payload['city_id']);

        $this->postJson(self::ENDPOINT, $payload, $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['city_id']);
    }

    public function test_returns_422_when_guest_has_no_guest_id(): void
    {
        // No Authorization header, no guest_id → APIGuestMiddleware blocks it
        $this->postJson(self::ENDPOINT, $this->validPayload())
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Business rule errors
    // -------------------------------------------------------------------------

    public function test_returns_403_when_cart_is_empty(): void
    {
        // No cart items added
        $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders())
            ->assertStatus(403);
    }

    public function test_returns_403_when_product_is_out_of_stock(): void
    {
        $this->product->update(['current_stock' => 1]);
        $this->addCartForUser(2); // requesting 2 but only 1 in stock

        $this->postJson(self::ENDPOINT, $this->validPayload(), $this->authHeaders())
            ->assertStatus(403);
    }

    public function test_returns_404_when_governorate_not_found(): void
    {
        $this->addCartForUser();

        $this->postJson(self::ENDPOINT, array_merge($this->validPayload(), ['city_id' => 99999]), $this->authHeaders())
            ->assertStatus(404);
    }

    public function test_returns_404_when_governorate_has_no_sellers_assigned(): void
    {
        $emptyGovernorate = Governorate::create(['name_ar' => 'بلا بائع']);
        // No sellers attached
        $this->addCartForUser();

        $this->postJson(self::ENDPOINT, array_merge($this->validPayload(), [
                'city_id' => $emptyGovernorate->id,
            ]), $this->authHeaders())
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Data integrity: no order created on error
    // -------------------------------------------------------------------------

    public function test_no_order_created_when_governorate_not_found(): void
    {
        $countBefore = Order::count();
        $this->addCartForUser();

        $this->postJson(self::ENDPOINT, array_merge($this->validPayload(), ['city_id' => 99999]), $this->authHeaders());

        $this->assertEquals($countBefore, Order::count());
    }
}
