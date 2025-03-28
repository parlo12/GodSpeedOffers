<?php

namespace App\Services\WebsocketAPI;

use App\Http\Controllers\Customer\DLRController;
use App\Models\ChatBox;
use App\Models\ChatBoxMessage;
use App\Models\PhoneNumbers;
use Illuminate\Support\Facades\Log;

class Handler
{
    public function __construct(string $eventName, array $data)
    {
        Log::info("WebSocket Event Triggered: {$eventName}, Process ID: " . getmypid());
        Log::info("WebSocket Event Triggered by Process: " . getmypid() . " | Event Name: " . $eventName);
        
        if ($eventName === 'sms') {
            $this->handleSmsEvent($data);
        } elseif ($eventName === 'mms') {
            $this->handleMmsEvent($data);
        } elseif ($eventName === 'deliveryStatus') {
            $this->handleDeliveryStatusEvent($data);
        } else {
            $this->handleDefault($data);
        }
    }

    /**
     * Handle an incoming SMS event.
     *
     * Expected data keys: deviceId, sender, (receiver), content, (optionally messageId)
     */
    private function handleSmsEvent(array $data)
    {
        // Extract values using array keys.
        $deviceId  = $data['deviceId']  ?? null;
        $messageId = $data['messageId'] ?? '';
        $sender    = $data['sender']    ?? '';
        $receiver  = $data['receiver']  ?? '';
        $content   = $data['content']   ?? '';

        Log::info("Handling SMS Event: deviceId={$deviceId}, messageId={$messageId}, sender={$sender}, receiver={$receiver}");

        // If receiver is missing, fallback to using deviceId for querying the phone number.
        if (empty($receiver)) {
            Log::warning("Receiver is missing in SMS event. Falling back to using deviceId: {$deviceId}");
            $number = PhoneNumbers::where('device_id', $deviceId)->first();
        } else {
            $number = PhoneNumbers::where('number', $receiver)->first();
        }

        if (!$number) {
            Log::warning("No phone number found using " . (empty($receiver) ? "device_id: {$deviceId}" : "receiver: {$receiver}"));
            return;
        }

        // Retrieve the sending server associated with this phone number.
        $sendingServer = $number->sendingServer;
        if (!$sendingServer) {
            Log::warning("No sending server associated with phone number: {$number->number}");
            return;
        }

        // Normalize sender by stripping any leading '+'.
        $sender = ltrim($sender, '+');
        Log::info("Phone number found: {$number->number} for user ID: {$number->user_id}, Sending Server ID: {$sendingServer->id}");

        // Find an existing chatbox for this conversation.
        $chatbox = ChatBox::where([
            'user_id'           => $number->user_id,
            'sending_server_id' => $sendingServer->id,
        ])->where(function($query) use ($sender, $number) {
            $query->where(function($query) use ($sender) {
                $query->where('from', $sender)->orWhere('to', $sender);
            })->where(function($query) use ($number) {
                $query->where('from', $number->number)->orWhere('to', $number->number);
            });
        })->first();

        if (!$chatbox) {
            $chatbox = new ChatBox([
                'user_id'           => $number->user_id,
                'from'              => $number->number,
                'to'                => $sender,
                'sending_server_id' => $sendingServer->id,
            ]);
            $chatbox->reply_by_customer = true;
            Log::info("New chatbox created for User ID: {$number->user_id}, Receiver: " . (empty($receiver) ? $deviceId : $receiver));
        }
        
        // Increment notification count and save the chatbox.
        $chatbox->notification = $chatbox->notification + 1;
        $chatbox->save();

        // Prepare message data and create a new ChatBoxMessage record.
        $messageData = [
            'box_id'            => $chatbox->id,
            'message'           => $content,
            'send_by'           => ($chatbox->from == $sender) ? 'from' : 'to',
            'sms_type'          => 'plain',
            'sending_server_id' => $sendingServer->id,
            'external_uuid'     => $messageId,
        ];

        ChatBoxMessage::create($messageData);
        Log::info("Message saved to chatbox (ID: {$chatbox->id}) with content: {$content}");
    }

    /**
     * Handle an incoming MMS event.
     *
     * Expected data keys: deviceId, sender, (receiver), content, mediaUrl, mediaType, (optionally messageId)
     */
    private function handleMmsEvent(array $data)
    {
        // Extract values.
        $deviceId  = $data['deviceId']  ?? null;
        $messageId = $data['messageId'] ?? '';
        $sender    = $data['sender']    ?? '';
        $receiver  = $data['receiver']  ?? '';
        $content   = $data['content']   ?? '';
        $mediaUrl  = $data['mediaUrl']  ?? '';
        $mediaType = $data['mediaType'] ?? '';

        Log::info("Handling MMS Event: deviceId={$deviceId}, messageId={$messageId}, sender={$sender}, receiver={$receiver}, mediaUrl={$mediaUrl}, mediaType={$mediaType}");

        // If receiver is missing, fallback to using deviceId for querying the phone number.
        if (empty($receiver)) {
            Log::warning("Receiver is missing in MMS event. Falling back to using deviceId: {$deviceId}");
            $number = PhoneNumbers::where('device_id', $deviceId)->first();
        } else {
            $number = PhoneNumbers::where('number', $receiver)->first();
        }

        if (!$number) {
            Log::warning("No phone number found using " . (empty($receiver) ? "device_id: {$deviceId}" : "receiver: {$receiver}"));
            return;
        }

        // Retrieve the sending server associated with this phone number.
        $sendingServer = $number->sendingServer;
        if (!$sendingServer) {
            Log::warning("No sending server associated with phone number: {$number->number}");
            return;
        }

        // Normalize sender by stripping any leading '+'.
        $sender = ltrim($sender, '+');
        Log::info("Phone number found: {$number->number} for user ID: {$number->user_id}, Sending Server ID: {$sendingServer->id}");

        // Find an existing chatbox for this conversation.
        $chatbox = ChatBox::where([
            'user_id'           => $number->user_id,
            'sending_server_id' => $sendingServer->id,
        ])->where(function($query) use ($sender, $number) {
            $query->where(function($query) use ($sender) {
                $query->where('from', $sender)->orWhere('to', $sender);
            })->where(function($query) use ($number) {
                $query->where('from', $number->number)->orWhere('to', $number->number);
            });
        })->first();

        if (!$chatbox) {
            $chatbox = new ChatBox([
                'user_id'           => $number->user_id,
                'from'              => $number->number,
                'to'                => $sender,
                'sending_server_id' => $sendingServer->id,
            ]);
            $chatbox->reply_by_customer = true;
            Log::info("New chatbox created for User ID: {$number->user_id}, Receiver: " . (empty($receiver) ? $deviceId : $receiver));
        }
        
        // Increment notification count and save the chatbox.
        $chatbox->notification = $chatbox->notification + 1;
        $chatbox->save();

        // Prepare message data and create a new ChatBoxMessage record.
        $messageData = [
            'box_id'            => $chatbox->id,
            'message'           => $content,
            'send_by'           => ($chatbox->from == $sender) ? 'from' : 'to',
            'sms_type'          => 'mms', // Mark as MMS instead of plain SMS.
            'sending_server_id' => $sendingServer->id,
            'external_uuid'     => $messageId,
            // Additional fields for MMS.
            'media_url'         => $mediaUrl,
            'media_type'        => $mediaType,
        ];

        ChatBoxMessage::create($messageData);
        Log::info("MMS message saved to chatbox (ID: {$chatbox->id}) with content: {$content} and media: {$mediaUrl}");
    }

    /**
     * Handle a delivery status event.
     *
     * Expected data keys: messageId, status, updatedAt
     */
    private function handleDeliveryStatusEvent(array $data)
    {
        $messageId = $data['messageId'] ?? '';
        $status    = $data['status'] ?? '';
        $updatedAt = $data['updatedAt'] ?? '';

        Log::info("Handling Delivery Status Event: messageId={$messageId}, status={$status}, updatedAt={$updatedAt}");
        DLRController::updateDLR($messageId, $status);
    }

    /**
     * Default handler for unrecognized events.
     */
    private function handleDefault(array $data)
    {
        Log::info("Default Event Handler: " . json_encode($data));
        print_r($data);
    }
}