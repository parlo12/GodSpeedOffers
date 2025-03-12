<?php

namespace App\Console\Commands;

use ElephantIO\Client;
use ElephantIO\Exception\ServerConnectionFailureException;
use Illuminate\Console\Command;
use App\Models\SendingServer;
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
    protected $description = 'Sync websocket-api for all available API keys with improved error handling and correct routing using apiKey';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Retrieve all sending servers with the Websocket API type.
        $sendingServers = SendingServer::where('settings', SendingServer::TYPE_WEBSOCKETAPI)->get();

        if ($sendingServers->isEmpty()) {
            $this->error('No sending servers found with Websocket API settings.');
            return Command::FAILURE;
        }

        // Prepare an array to hold our connected clients along with their server data.
        $clients = [];
        foreach ($sendingServers as $server) {
            // Check if the device_id is present.
            if (empty($server->device_id)) {
                Log::warning("Skipping server ID {$server->id} because device_id is missing.");
                continue;
            }

            try {
                // Create a client for each server using its api_link and auth_token (as apiKey)
                // Note: We still use auth_token for the connection, but incoming messages will be matched via apiKey.
                $client = Client::create($server->api_link . '?apiKey=' . $server->auth_token);
                $client->connect();
                $this->info("Connected to Websocket API for server ID: {$server->id}");
                $clients[] = [
                    'server' => $server,
                    'client' => $client,
                ];
            } catch (\Exception $e) {
                $this->error("Failed to connect to server ID: {$server->id}. Error: " . $e->getMessage());
                Log::error("Websocket connection error", [
                    'server_id' => $server->id,
                    'error'     => $e->getMessage()
                ]);
            }
        }

        if (empty($clients)) {
            $this->error('No websocket clients are connected.');
            return Command::FAILURE;
        }

        // Main loop: process outgoing messages and incoming packets.
        while (true) {
            try {
                // Outgoing messages: retrieve and remove the message atomically.
                if ($message = Cache::pull('outgoingSMS')) {
                    $decodedMessage = json_decode($message, true);
                    foreach ($clients as $entry) {
                        try {
                            $entry['client']->emit('outgoingSMS', [
                                'deviceId' => $decodedMessage['device_id'],
                                'receiver' => $decodedMessage['phone'],
                                'content'  => $decodedMessage['message'],
                            ]);
                        } catch (\Exception $e) {
                            Log::error("Failed to emit outgoingSMS for server ID: " . $entry['server']->id, [
                                'error'   => $e->getMessage(),
                                'message' => $decodedMessage,
                            ]);
                            // Requeue the message for a later retry.
                            Cache::put('outgoingSMS', $message, now()->addSeconds(30));
                        }
                    }
                }

                // Incoming messages: process packets from each client.
                foreach ($clients as $entry) {
                    try {
                        if ($packet = $entry['client']->wait(null, 1)) {
                            // Extract the incoming apiKey from the crmId mapping.
                            $incomingApiKey = null;
                            if (isset($packet->data['crmId'])) {
                                $crmMapping = $packet->data['crmId'];
                                if (is_object($crmMapping)) {
                                    $crmMapping = (array)$crmMapping;
                                }
                                $incomingApiKey = $crmMapping['apiKey'] ?? null;
                            }

                            if (!$incomingApiKey) {
                                Log::warning("Incoming packet missing apiKey in crmId for server ID: {$entry['server']->id}");
                                continue;
                            }

                            // Only process the packet if the incoming apiKey matches the server's stored api_key.
                            if ($entry['server']->api_key !== $incomingApiKey) {
                                Log::info("Skipping incoming message for server ID: {$entry['server']->id} as incoming apiKey {$incomingApiKey} does not match server's api_key {$entry['server']->api_key}");
                                continue;
                            }

                            // Process the incoming packet.
                            new Handler($packet->event, $packet->data);
                        }
                    } catch (\Exception $e) {
                        Log::error("Error processing incoming packet for server ID: " . $entry['server']->id, [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Unexpected error in main loop: " . $e->getMessage());
            }

            // Short sleep to avoid tight looping under high load.
            usleep(50000); // Sleep for 50 milliseconds
        }

        return Command::SUCCESS;
    }
}