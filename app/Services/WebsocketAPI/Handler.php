<?php

namespace App\Services\WebsocketAPI;

use App\Http\Controllers\Customer\DLRController;
use App\Models\ChatBox;
use App\Models\ChatBoxMessage;
use App\Models\PhoneNumbers;
use Illuminate\Support\Facades\Log; // Import Log facade

class Handler
{
    public function __construct(string $eventName, array $data)
    {
        Log::info("WebSocket Event Triggered: {$eventName}, Process ID: " . getmypid());
	Log::info("WebSocket Event Triggered by Process: " . getmypid() . " | Event Name: " . $eventName);
        
        match ($eventName) {
            'sms' => $this->handleSmsEvent(...$data),
        //    'deliveryStatus' => $this->handleDeliveryStatusEvent(...$data),
            default => $this->handleDefault($data),
        };
    }

    private function handleSmsEvent(string $deviceId, string $messageId, string $sender, string $content)
    {
        Log::info("Handling SMS Event: deviceId={$deviceId}, messageId={$messageId}, sender={$sender}");
        
        $number = PhoneNumbers::whereDeviceId($deviceId)->first();
        if ($number) {
            $sending_server = $number->sendingServer;
            $sender = ltrim($sender, '+');
            
            Log::info("Phone number found. User ID: {$number->user_id}, Server ID: {$sending_server->id}");
            
            $chatbox = ChatBox::where([
                'user_id'           => $number->user_id,
                'sending_server_id' => $sending_server->id,
            ])->where(function($query) use ($sender, $number) {
                $query->where(function($query) use ($sender) {
                    $query->where('from', $sender)->orWhere('to', $sender);
                })->where(function($query) use ($number) {
                    $query->where('from', $number->number)->orWhere('to', $number->number);
                });
            })->first();

            if (! $chatbox) {
                $chatbox = new ChatBox([
                    'user_id'           => $number->user_id,
                    'from'              => $number->number,
                    'to'                => $sender,
                    'sending_server_id' => $sending_server->id,
                ]);
                $chatbox->reply_by_customer = true;
                Log::info("New chatbox created for User ID: {$number->user_id}, Sender: {$sender}");
		Log::info("WebSocket Event Triggered by Process: " . getmypid() . " | Event Name: " . $eventName);

            }
            
            $chatbox->notification = $chatbox->notification + 1;
            $chatbox->save();

            $messageData  = [
                'box_id'            => $chatbox->id,
                'message'           => $content,
                'send_by'           => $chatbox->from == $sender ? 'from' : 'to',
                'sms_type'          => 'plain',
                'sending_server_id' => $sending_server->id,
                'external_uuid'     => $messageId,
            ];

            ChatBoxMessage::create($messageData);
            Log::info("Message saved to chatbox (ID: {$chatbox->id}) with content: {$content}");
        } else {
            Log::warning("No phone number found for deviceId: {$deviceId}");
        }
    }

    private function handleDeliveryStatusEvent(string $messageId, string $status , string $updatedAt)
    {
        Log::info("Handling Delivery Status Event: messageId={$messageId}, status={$status}, updatedAt={$updatedAt}");
        DLRController::updateDLR($messageId, $status);
    }

    private function handleDefault($data)
    {
        Log::info("Default Event Handler: " . json_encode($data));
        print_r($data);
    }
}
