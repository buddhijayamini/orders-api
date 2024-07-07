<?php

namespace App\Jobs;

use App\Models\Order;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ProcessOrderApiRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;


    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The maximum number of exceptions allowed before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = new Client();

        $data = [
            'Order_ID' => $this->order->code,
            'Customer_Name' => $this->order->customer_name,
            'Order_Value' => $this->order->order_value,
            'Order_Date' => $this->order->created_at,
            'Order_Status' => $this->order->status,
            'Process_ID' => $this->order->process_id,
        ];

        try {
            $response = $client->post('https://wibip.free.beeceptor.com/order', [
                'json' => $data,
            ]);

            if ($response->getStatusCode() != 200) {
                throw new \Exception('API response status code not 200');
            }
        } catch (\Exception $e) {
            Log::error('Error sending data to third-party API: ' . $e->getMessage());

            if ($e->getCode() == 429) { // Too Many Requests
                $this->release(60); // Wait 60 seconds before retrying
            } else {
                $this->fail($e);
            }
        }
    }

    /**
     * Calculate the delay for the next job retry using exponential backoff.
     *
     * @return int
     */
    protected function getRetryDelay()
    {
        return (int) pow(2, $this->attempts()) + 60; // Exponential backoff with base delay of 60 seconds
    }
}
