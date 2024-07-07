<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessOrderApiRequest;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderController extends Controller
{
    public function index()
    {
        try {
            // Fetch all orders
            $orders = Order::all();

            // Return the list of orders in the response
            return response()->json([
                'status' => 'success',
                'data' => $orders
            ], 200);
        } catch (Throwable $e) {
            // Handle any errors
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch orders: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'order_value' => 'required|numeric',
        ]);

        // Start a DB transaction
        DB::beginTransaction();

        try {
            // Generate a random Process ID between 1 and 10
            $processId = rand(1, 10);

            // Determine the next available Order_ID
            $nextOrderId = $this->getNextOrderId();

            // Create the order with the determined Order_ID
            $order = Order::create([
                'code' => $nextOrderId,
                'customer_name' => $validated['customer_name'],
                'order_value' => $validated['order_value'],
                'process_id' => $processId,
                'status' => 'Processing',
            ]);

            // Commit the transaction
            DB::commit();

            // Send data to third-party API
            //$this->sendToThirdPartyAPI($order);

            //job run for sending third-party data
            ProcessOrderApiRequest::dispatch($order)->onQueue('api_requests');

            return response()->json([
                'order_id' => $order->code,
                'process_id' => $processId,
                'status' => $order->status,
            ]);
        } catch (Throwable $e) {
            // Rollback the transaction
            DB::rollBack();

            // Log the error
            Log::error('Order creation failed: ' . $e->getMessage());

            return response()->json([
                'error' => 'Order creation failed. Please try again.'
            ], 500);
        }
    }

    private function getNextOrderId()
    {
        // Get the last order ID from the database
        $lastOrder = Order::orderBy('id', 'desc')->first();

        if (!$lastOrder) {
            // No orders exist, start with '0001'
            return '0001';
        }

        // Increment the last order ID to determine the next one
        $lastOrderId = $lastOrder->id;
        $nextOrderId = '000' . ($lastOrderId + 1); // Concatenate and format

        return $nextOrderId;
    }

    private function sendToThirdPartyAPI(Order $order)
    {
        // Data to send to third-party API
        $data = [
            'Order_ID' => $order->code,
            'Customer_Name' => $order->customer_name,
            'Order_Value' => $order->order_value,
            'Order_Date' => $order->created_at->format('Y-m-d H:i:s'),
            'Order_Status' => $order->status,
            'Process_ID' => $order->process_id,
        ];

        // Instantiate Guzzle HTTP client
        $client = new GuzzleClient();

        try {
            // Send HTTP POST request to third-party API endpoint
            $response = $client->post('https://wibip.free.beeceptor.com/order', [
                'json' => $data,
            ]);

            // Log the API response if needed
            Log::info('Third-party API response: ' . $response->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Log the error
            Log::error('Error sending data to third-party API: ' . $e->getMessage());
        }
    }
}
