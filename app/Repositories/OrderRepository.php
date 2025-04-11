<?php

namespace app\Repositories;

class OrderRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = new Order();
    }

    public function getActiveOrders()
    {
        return Order::query()
            ->where('status', 'active')
            ->where('created_at', '>', now()->subDays(30));
    }

    public function createOrderWithProducts(array $data, array $products)
    {
        DB::beginTransaction();

        try {
            $order = Order::create($data);

            foreach ($products as $product) {
                $order->products()->attach($product['id'], [
                    'quantity' => $product['quantity'],
                    'price' => $product['price']
                ]);
            }

            DB::commit();
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function findByUser($userId)
    {
        return Order::where('user_id', $userId)
            ->with(['products', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function update($id, $data)
    {
        $order = Order::find($id);
        $order->update($data);
        return $order;
    }

    public function getOrderStatistics($orderId)
    {
        $order = Order::find($orderId);

        return [
            'total_products' => $order->products()->count(),
            'total_amount' => $order->products()->sum('price'),
            'created_at' => $order->created_at
        ];
    }
}
