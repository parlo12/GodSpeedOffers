<?php

namespace App\Console\Commands;

use ElephantIO\Client;
use Illuminate\Console\Command;
use App\Models\SendingServer;
use App\Models\PhoneNumbers;
use App\Services\WebsocketAPI\Handler;
use Illuminate\Support\Facades\Cache;
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
        // For each sending server, create and connect a WebSocket client.
        foreach ($sendingServers as $server) {
            try {
                $client = Client::create($server->api_link . '?apiKey=' . $server->auth_token);
                $client->connect();
                Log::info("Connected to Websocket API for server ID: {$server->id}");
                $clients[] = [
                    'server' => $server,
                    'client' => $client,
                ];
            } catch (\Exception $e) {
                Log::error("Error connecting for server ID: {$server->id} - " . $e->getMessage());
            }
        }

        if (empty($clients)) {
            $this->error("No websocket clients are connected.");
            return Command::FAILURE;
        }

        $this->info("Connected to " . count($clients) . " WebSocket API endpoints.");

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
                        // Emit the outgoing message only on the client that matches the target sending server.
                        foreach ($clients as $entry) {
                            if ($entry['server']->id == $targetServerId) {
                                try {
                                    $entry['client']->emit('outgoingSMS', [
                                        'deviceId' => $decodedMessage['device_id'] ?? '',
                                        // Optionally pass the external recipient if available.
                                        'receiver' => isset($decodedMessage['phone']) ? ltrim(trim($decodedMessage['phone']), '+') : '',
                                        'content'  => $decodedMessage['message'] ?? '',
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error("Error emitting outgoingSMS on server ID " . $entry['server']->id . ": " . $e->getMessage());
                                    // Optionally, requeue the message for retry.
                                    Cache::put('outgoingSMS', json_encode($decodedMessage), now()->addSeconds(30));
                                }
                            }
                        }
                    } else {
                        Log::warning("No phone record found for device_id: {$targetDeviceId}");
                    }
                }
            }

            // Process incoming messages.
            foreach ($clients as $entry) {
                try {
                    if ($packet = $entry['client']->wait(null, 0.1)) {
                        // Pass all incoming packets to the Handler.
                        new Handler($packet->event, $packet->data);
                    }
                } catch (\Exception $e) {
                     // Optionally, check if the error message indicates a timeout and handle it gracefully.
                    if (strpos($e->getMessage(), 'Operation timed out') !== false) {
                        Log::warning("Timeout while waiting for packet on server ID " . $entry['server']->id);
                    } else {
                        // Log any other exceptions that may occur while waiting for packets.
                        Log::error("Error processing incoming packet on server ID " . $entry['server']->id . ": " . $e->getMessage());
                        // Optionally, you can choose to reconnect the client or take other actions.
                    }
                }
            }

            usleep(10000); // Sleep 50ms to prevent CPU overuse.
        }

        return Command::SUCCESS;
    }
}