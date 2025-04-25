<?php

namespace App\Console\Commands;

use ElephantIO\Client;
use Illuminate\Console\Command;
use App\Models\SendingServer;
use App\Models\PhoneNumbers;
use App\Services\WebsocketAPI\Handler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebsocketAPIListener extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket-api:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync websocket-api for multiple accounts and route outgoing messages based on PhoneNumbers sending_server_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Retrieve all sending servers with the WebsocketAPI setting.
        $sendingServers = SendingServer::where('settings', SendingServer::TYPE_WEBSOCKETAPI)->get();

        if ($sendingServers->isEmpty()) {
            $this->error("No sending servers found with Websocket API settings.");
            return Command::FAILURE;
        }

        $clients = [];

        // Main loop: process outgoing messages and incoming packets.
        while (true) {
            // Process outgoing messages.
            if ($message = Cache::pull('outgoingSMS')) {
                $decodedMessage = json_decode($message, true);
                Log::info("Decoded outgoing message: " . json_encode($decodedMessage));

                // Use the "device_id" from the cached message for routing.
                $targetDeviceId = trim($decodedMessage['device_id'] ?? '');
                if (!$targetDeviceId) {
                    Log::warning("Outgoing message missing device_id. Skipping emission.");
                } else {
                    // Look up the PhoneNumbers record using the device_id.
                    $phoneRecord = PhoneNumbers::where('device_id', $targetDeviceId)->first();
                    if ($phoneRecord) {
                        $targetServerId = $phoneRecord->sending_server_id;
                        try {
                            $response = Http::post('https://coral-app-cazak.ondigitalocean.app/messages/sendOutgoingMessage', [
                                'deviceId' => $decodedMessage['device_id'] ?? '',
                                // Optionally pass the external recipient if available.
                                'receiver' => isset($decodedMessage['phone']) ? ltrim(trim($decodedMessage['phone']), '+') : '',
                                'content'  => $decodedMessage['message'] ?? '',
                                'crmId' => $targetServerId
                            ]);
                            $data = $response->json();
                            Log::info("Sending message: " . json_encode($data));
                        } catch (\Exception $e) {
                            Log::error("Error emitting outgoingSMS on server ID " . ": " . $e->getMessage());
                            // Optionally, requeue the message for retry.
                            Cache::put('outgoingSMS', json_encode($decodedMessage), now()->addSeconds(30));
                        }
                    } else {
                        Log::warning("No phone record found for device_id: {$targetDeviceId}");
                    }
                }
            }

            usleep(10000); // Sleep 50ms to prevent CPU overuse.
        }

        return Command::SUCCESS;
    }
}