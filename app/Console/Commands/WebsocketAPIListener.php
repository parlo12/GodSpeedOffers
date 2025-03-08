<?php

namespace App\Console\Commands;

use ElephantIO\Client;
use Illuminate\Console\Command;
use App\Models\SendingServer;
use App\Services\WebsocketAPI\Handler;
use Illuminate\Support\Facades\Cache;

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
    protected $description = 'Sync websocket-api for a specific auth_token';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Hard-coded auth_token value to use.
        $targetAuthToken = '8c6fab64d242849005f3b68c5a331b82';
        
        // Retrieve the SendingServer with the specified auth_token and Websocket API settings.
        $server = SendingServer::where('settings', SendingServer::TYPE_WEBSOCKETAPI)
                    ->where('auth_token', $targetAuthToken)
                    ->first();

        if (!$server) {
            $this->error("No sending server found with the specified auth_token: {$targetAuthToken}");
            return Command::FAILURE;
        }

        try {
            // Create and connect the client using the api_link and the hard-coded auth_token.
            $client = Client::create($server->api_link . '?apiKey=' . $server->auth_token);
            $client->connect();
            $this->info("Connected to Websocket API for server ID: {$server->id} with auth_token: {$server->auth_token}");
        } catch (\Exception $e) {
            $this->error("Failed to connect to server ID: {$server->id}. Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Main loop: poll for outgoing messages and check for incoming packets.
        while (true) {
            // Check for an outgoing message from the cache.
            if ($message = Cache::pull('outgoingSMS')) {
                $message = json_decode($message, true);
                $client->emit('outgoingSMS', [
                    'deviceId' => $message['device_id'],
                    'receiver' => $message['phone'],
                    'content'  => $message['message'],
                ]);
            }

            // Check for any incoming packets with a 1 second timeout.
            if ($packet = $client->wait(null, 1)) {
                new Handler($packet->event, $packet->data);
            }
        }

        return Command::SUCCESS;
    }
}