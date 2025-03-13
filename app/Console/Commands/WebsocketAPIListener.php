<?php

namespace App\Console\Commands;

use ElephantIO\Client;
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
    protected $description = 'sync websocket-api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sendingServers = SendingServer::where('settings', SendingServer::TYPE_WEBSOCKETAPI)->get();

        foreach ($sendingServers as $server) {
            // If settings is a string (likely JSON), decode it.
            $settings = is_string($server->settings) ? json_decode($server->settings, true) : $server->settings;
            if ($settings === null) {
                Log::warning("Invalid JSON for settings on server ID: {$server->id}");
                continue;
            }
            // Set api_link and auth_token from the decoded settings.
            $server->api_link = $settings['api_link'] ?? null;
            $server->auth_token = $settings['auth_token'] ?? null;
        }

        Log::info('all sending servers: ' . json_encode($sendingServers));

        // Pull the first sending server.
        $sendingServer = $sendingServers->first();

        if ($sendingServer) {
            if (!$sendingServer->api_link || !$sendingServer->auth_token) {
                $this->error("Missing api_link or auth_token for server ID: {$sendingServer->id}");
                return Command::FAILURE;
            }

            try {
                $client = Client::create($sendingServer->api_link . '?apiKey=' . $sendingServer->auth_token);
                $client->connect();
                $this->info('Connected to Websocket API');
                Log::info('Connected to Websocket API, Process ID: ' . getmypid());
                Log::info('Current API Link: ' . $sendingServer->api_link);
            } catch (\Exception $e) {
                $this->error("Failed to connect to server ID: {$sendingServer->id}. Error: " . $e->getMessage());
                return Command::FAILURE;
            }

            // Main loop: poll for outgoing messages and check for incoming packets.
            while (true) {
                if ($message = Cache::pull('outgoingSMS')) {
                    $message = json_decode($message, true);
                    $client->emit('outgoingSMS', [
                        'deviceId' => $message['device_id'],
                        'receiver' => $message['phone'],
                        'content'  => $message['message'],
                    ]);
                }
                if ($packet = $client->wait(null, 1)) {
                    new Handler($packet->event, $packet->data);
                }
            }

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}