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
    protected $description = 'Sync websocket-api for multiple accounts and route outgoing messages to the correct sending server';

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
        // For each sending server, attempt to create and connect a WebSocket client.
        foreach ($sendingServers as $server) {
            // Optionally log a warning if device_id is missing.
            if (empty($server->device_id)) {
                Log::warning("Server ID {$server->id} has no device_id; outgoing messages will be routed using fallback logic.");
            }
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
            // Outgoing messages: Pull a message from cache and process it.
            if ($message = Cache::pull('outgoingSMS')) {
                $decodedMessage = json_decode($message, true);
                Log::info("Decoded outgoing message: " . json_encode($decodedMessage));
                
                // Use 'to' if available; otherwise, fall back to 'phone'
                $receiver = null;
                if (isset($decodedMessage['to'])) {
                    $receiver = trim($decodedMessage['to']);
                } elseif (isset($decodedMessage['phone'])) {
                    $receiver = trim($decodedMessage['phone']);
                }
                
                // Normalize the receiver by removing any leading '+'.
                if ($receiver) {
                    $receiver = ltrim($receiver, '+');
                }
                
                $targetServerId = null;
                if ($receiver) {
                    // Look up the PhoneNumbers record for the receiver.
                    $phoneRecord = PhoneNumbers::where('number', $receiver)->first();
                    if ($phoneRecord) {
                        $targetServerId = $phoneRecord->sending_server_id;
                    } else {
                        Log::warning("No phone record found for receiver: {$receiver}");
                    }
                }
                
                // Fallback: if no phone record is found, use the device_id from the cached message.
                if (!$targetServerId) {
                    $targetDeviceId = trim($decodedMessage['device_id'] ?? '');
                    if ($targetDeviceId) {
                        foreach ($clients as $entry) {
                            if ($entry['server']->device_id === $targetDeviceId) {
                                $targetServerId = $entry['server']->id;
                                break;
                            }
                        }
                        if (!$targetServerId) {
                            Log::warning("No sending server matches the fallback device_id: {$targetDeviceId}");
                        }
                    } else {
                        Log::warning("Outgoing message missing both receiver and device_id. Skipping emission.");
                    }
                }
                
                if ($targetServerId) {
                    // Emit the outgoing message only on the client that matches the target sending server.
                    foreach ($clients as $entry) {
                        if ($entry['server']->id == $targetServerId) {
                            try {
                                $entry['client']->emit('outgoingSMS', [
                                    'deviceId' => $decodedMessage['device_id'] ?? '',
                                    'receiver' => $receiver ?? '',
                                    'content'  => $decodedMessage['message'] ?? '',
                                ]);
                            } catch (\Exception $e) {
                                Log::error("Error emitting outgoingSMS on server ID " . $entry['server']->id . ": " . $e->getMessage());
                                // Optionally requeue the message for retry.
                                Cache::put('outgoingSMS', json_encode($decodedMessage), now()->addSeconds(30));
                            }
                        }
                    }
                }
            }

            // Incoming messages: Check each client connection for a packet.
            foreach ($clients as $entry) {
                try {
                    if ($packet = $entry['client']->wait(null, 1)) {
                        // Pass all incoming packets to the Handler.
                        new Handler($packet->event, $packet->data);
                    }
                } catch (\Exception $e) {
                    Log::error("Error processing incoming packet on server ID " . $entry['server']->id . ": " . $e->getMessage());
                }
            }

            usleep(50000); // Sleep 50ms to prevent CPU overuse.
        }

        return Command::SUCCESS;
    }
}