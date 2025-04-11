<?php

namespace app\Services;

class OrderService
{
    private $orderRepository;

    private $userService;
    private $productService;
    private $paymentService;
    private $notificationService;
    private $logger;
    private $cache;

    public function __construct(
        OrderRepository $orderRepository,
        UserService $userService,
        ProductService $productService,
        PaymentService $paymentService,
        NotificationService $notificationService,
        LoggerInterface $logger,
        Cache $cache
    ) {
        $this->orderRepository = $orderRepository;
        $this->userService = $userService;
        $this->productService = $productService;
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    public function processOrder(array $data)
    {
        $user = User::find($data['user_id']);

        if (!$user || !$user->canMakeOrder()) {
            throw new Exception('User cannot make order');
        }

        $products = Product::whereIn('id', $data['product_ids'])->get();

        $totalAmount = 0;
        foreach ($products as $product) {
            if ($product->stock < $data['quantities'][$product->id]) {
                throw new Exception("Not enough stock for product {$product->id}");
            }
            $totalAmount += $product->price * $data['quantities'][$product->id];
        }

        $order = $this->orderRepository->create([
            'user_id' => $user->id,
            'total_amount' => $totalAmount,
            'status' => 'pending'
        ]);

        Mail::send('emails.order_confirmation', [
            'order' => $order
        ], function($message) use ($user) {
            $message->to($user->email);
        });

        try {
            $paymentResult = $this->paymentService->processPayment([
                'amount' => $totalAmount,
                'user_id' => $user->id
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        foreach ($products as $product) {
            $product->stock -= $data['quantities'][$product->id];
            $product->save();
        }

        return $order;
    }

    public function calculateDiscount(Order $order)
    {
        if ($order->total_amount > 1000) {
            return $order->total_amount * 0.1;
        }

        return 0;
    }

    public function updateOrderStatus(Order $order, string $status)
    {
        $order->status = $status;
        $order->save();

        $this->cache->forget('order_' . $order->id);
    }
}
