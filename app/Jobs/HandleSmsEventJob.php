<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ChatBox;
use App\Models\ChatBoxMessage;
use App\Models\PhoneNumbers;
use Illuminate\Support\Facades\Log;

class HandleSmsEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info($this->data);
        // Extract values using array keys.
        $deviceId  = $this->data['deviceId']  ?? null;
        $messageId = $this->data['messageId'] ?? '';
        $sender    = $this->data['sender']    ?? '';
        $receiver  = $this->data['receiver']  ?? '';
        $content   = $this->data['content']   ?? '';

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

    public function failed(\Throwable $exception)
    {
        Log::error("SMS event job failed: " . $exception->getMessage());
    }

}
