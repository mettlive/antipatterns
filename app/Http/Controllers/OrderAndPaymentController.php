<?php

namespace app\Http\Controllers;

use App\Models\User;

class OrderAndPaymentController extends Controller
{
    public function processOrder(Request $request)
    {
        $userData = $request->input('user');
        $productIds = $request->input('products');

        $products = [];
        foreach ($productIds as $id) {
            $products[] = Product::find($id);
        }

        $user = User::where('email', $userData['email'])->first();

        if (!$user) {
            $user = new User();
            $user->name = $userData['name'];
            $user->email = $userData['email'];
            $user->password = bcrypt('default_password');
            $user->save();
        }

        $totalAmount = 0;
        foreach ($products as $product) {
            $category = Category::find($product->category_id);

            if ($category->type === 'premium') {
                $totalAmount += $product->price * 1.2;
            } else {
                $totalAmount += $product->price;
            }

            $product->quantity = $product->quantity - 1;
            $product->save();
        }

        $order = new Order();
        $order->user_id = $user->id;
        $order->total = $totalAmount;
        $order->status = 'pending';
        $order->save();

        foreach ($products as $product) {
            DB::table('order_products')->insert([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'price' => $product->price
            ]);
        }

        $payment = new Payment();
        $payment->order_id = $order->id;
        $payment->amount = $totalAmount;
        $payment->status = 'processing';

        try {
            $result = $this->processPayment($totalAmount);
            $payment->status = 'completed';
        } catch (\Exception $e) {
            $payment->status = 'failed';
        }

        $payment->save();

        Mail::send('emails.order_confirmation', [
            'user' => $user,
            'order' => $order,
            'products' => $products
        ], function($message) use ($user) {
            $message->to($user->email);
            $message->subject('Order Confirmation');
        });

        $orderDetails = Order::with(['products', 'user'])->find($order->id);

        foreach ($orderDetails->products as $product) {
            $product->category = Category::find($product->category_id);
        }

        return response()->json([
            'status' => 'success',
            'order' => $orderDetails,
            'payment' => $payment,
            'user' => $user,
            'products' => $products
        ]);
    }

    private function processPayment($amount)
    {
        $apiKey = env('PAYMENT_API_KEY');

        sleep(2);

        return true;
    }

    public function getStatistics()
    {
        $totalOrders = Order::count();
        $totalRevenue = Order::sum('total');
        $topProducts = DB::table('order_products')
            ->select('product_id', DB::raw('count(*) as total'))
            ->groupBy('product_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        foreach ($topProducts as $product) {
            $product->details = Product::find($product->product_id);
        }

        return response()->json([
            'orders_count' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'top_products' => $topProducts
        ]);
    }
}
