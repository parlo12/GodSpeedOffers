<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatBox\SentRequest;
use App\Models\Blacklists;
use App\Models\Campaigns;
use App\Models\ChatBox;
use App\Models\ChatBoxMessage;
use App\Models\ContactGroups;
use App\Models\Contacts;
use App\Models\Country;
use App\Models\CustomerBasedPricingPlan;
use App\Models\CustomerBasedSendingServer;
use App\Models\PhoneNumbers;
use App\Models\PlansCoverageCountries;
use App\Models\SendingServer;
use App\Models\Templates;
use App\Repositories\Contracts\CampaignRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use App\Repositories\Contracts\ContactsRepository;
use App\Library\Tool;
use App\Models\User;
use Throwable;

class ChatBoxController extends Controller
{
    protected CampaignRepository $campaigns;

    /**
     * ChatBoxController constructor.
     */
    public function __construct(CampaignRepository $campaigns, ContactsRepository $contactGroups)
    {
        $this->campaigns = $campaigns;
        $this->contactGroups = $contactGroups;
    }

    /**
     * get all chat box
     *
     * @throws AuthorizationException
     */
    public function index(): View|Factory|Application
    {
        $this->authorize('chat_box');

        $pageConfigs = [
            'pageHeader'    => false,
            'contentLayout' => 'content-left-sidebar',
            'pageClass'     => 'chat-application font-small-3',
        ];

        $pinnedChats = ChatBox::where('user_id', Auth::id())
            ->where('is_starred', 1)
            ->with(['chatBoxMessages', 'contact'])
            ->orderBy('updated_at', 'desc')
            ->get();
        $unread_count = ChatBox::where('notification', '>', 0)
            ->where('reply_by_customer', true)
            ->where('user_id', Auth::user()->id)
            ->count();
        $templates = Templates::where('status', true)->where('user_id', auth()->user()->id)->get();

        return view('customer.ChatBox.index', [
            'pageConfigs' => $pageConfigs,
            'templates'   => $templates,
            'unread_chats' => $unread_count,
            // 'pinnedChats' => $pinnedChats,
        ]);
    }

    /**
     * start new conversation
     *
     * @throws AuthorizationException
     */
    public function new(): View|Factory|RedirectResponse|Application
    {
        $this->authorize('chat_box');

        $breadcrumbs = [
            ['link' => url('dashboard'), 'name' => __('locale.menu.Dashboard')],
            ['link' => url('chat-box'), 'name' => __('locale.menu.Chat Box')],
            ['name' => __('locale.labels.new_conversion')],
        ];

        $phone_numbers = PhoneNumbers::where('user_id', Auth::user()->id)->where('status', 'assigned')->cursor();

        if (! Auth::user()->customer->activeSubscription()) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => __('locale.customer.no_active_subscription'),
            ]);
        }

        $plan_id = Auth::user()->customer->activeSubscription()->plan_id;

        $coverage = CustomerBasedPricingPlan::where('user_id', Auth::user()->id)->where('status', true)->cursor();
        if ($coverage->count() < 1) {
            $coverage = PlansCoverageCountries::where('plan_id', $plan_id)->where('status', true)->cursor();
        }

        $sendingServers = CustomerBasedSendingServer::where('user_id', auth()->user()->id)->where('status', 1)->get();
        $templates      = Templates::where('status', true)->where('user_id', auth()->user()->id)->get();

        return view('customer.ChatBox.new', compact('breadcrumbs', 'phone_numbers', 'coverage', 'sendingServers', 'templates'));
    }

    /**
     * start new conversion
     *
     *
     * @throws AuthorizationException|NumberParseException
     */
    public function sent(Campaigns $campaign, SentRequest $request): RedirectResponse
    {
        if (config('app.stage') === 'demo') {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => __('locale.demo_mode_not_available'),
            ]);
        }

        $this->authorize('chat_box');

        $sendingServers = CustomerBasedSendingServer::where('user_id', Auth::user()->id)->where('status', 1)->count();

        if ($sendingServers && ! isset($request->sending_server)) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => 'Please select your sending server',
            ]);
        }


        $input    = $request->except('_token');
        $senderId = $request->input('sender_id');
        $sms_type = $request->input('sms_type');

        $user    = Auth::user();
        $country = Country::find($request->input('country_code'));

        if (! $country) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => "Permission to send an SMS has not been enabled for the region indicated by the 'To' number: " . $input['recipient'],
            ]);
        }

        $phoneNumberUtil   = PhoneNumberUtil::getInstance();
        $phoneNumberObject = $phoneNumberUtil->parse('+' . $country->country_code . $request->input('recipient'));
        $countryCode       = $phoneNumberObject->getCountryCode();
        $regionCode        = $phoneNumberUtil->getRegionCodeForNumber($phoneNumberObject);

        if (! $phoneNumberUtil->isPossibleNumber($phoneNumberObject) || empty($countryCode) || empty($regionCode)) {

            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => __('locale.customer.invalid_phone_number', ['phone' => $country->country_code . $request->input('recipient')]),
            ]);
        }


        if ($phoneNumberObject->isItalianLeadingZero()) {
            $phone = '0' . preg_replace("/^$countryCode/", '', $phoneNumberObject->getNationalNumber());
        } else {
            $phone = preg_replace("/^$countryCode/", '', $phoneNumberObject->getNationalNumber());
        }

        $input['country_code'] = $countryCode;
        $input['recipient']    = $phone;
        $input['region_code']  = $regionCode;
        $input['user']         = Auth::user();

        $planId = $user->customer->activeSubscription()->plan_id;

        $coverage = CustomerBasedPricingPlan::where('user_id', $user->id)
            ->where('status', true)
            ->with('sendingServer')
            ->first();

        if (! $coverage) {
            $coverage = PlansCoverageCountries::where('plan_id', $planId)
                ->where('status', true)
                ->with('sendingServer')
                ->first();
        }

        if (! $coverage) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => 'Price Plan unavailable',
            ]);
        }

        $sendingServer = isset($$request->sending_server) ? SendingServer::find($request->sending_server) : $coverage->sendingServer;

        if (! $sendingServer) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => __('locale.campaigns.sending_server_not_available'),
            ]);
        }

        $db_sms_type = $sms_type == 'unicode' ? 'plain' : $sms_type;

        if (! $sendingServer->{$db_sms_type}) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => 'error',
                'message' => __('locale.sending_servers.sending_server_sms_capabilities', ['type' => strtoupper($db_sms_type)]),
            ]);
        }

        if ($sendingServer->settings === 'Whatsender' || $sendingServer->type === 'whatsapp') {
            $input['sms_type'] = 'whatsapp';
        }

        $db_sms_type       = ($sms_type === 'unicode') ? 'plain' : $sms_type;
        $capabilities_type = ($sms_type === 'plain' || $sms_type === 'unicode') ? 'sms' : $sms_type;

        if ($user->customer->getOption('sender_id_verification') === 'yes') {
            $number = PhoneNumbers::where('user_id', $user->id)
                ->where('number', $senderId)
                ->where('status', 'assigned')
                ->first();

            if (! $number) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.sender_id.sender_id_invalid', ['sender_id' => $senderId]),
                ]);
            }

            $capabilities = str_contains($number->capabilities, $capabilities_type);

            if (! $capabilities) {
                return redirect()->route('customer.chatbox.index')->with([
                    'status'  => 'error',
                    'message' => __('locale.sender_id.sender_id_sms_capabilities', ['sender_id' => $senderId, 'type' => $db_sms_type]),
                ]);
            }

            $input['originator']   = 'phone_number';
            $input['phone_number'] = $senderId;
        }

        $input['reply_by_customer'] = true;

        $data = $this->campaigns->quickSend($campaign, $input);

        if (isset($data->getData()->status)) {
            return redirect()->route('customer.chatbox.index')->with([
                'status'  => $data->getData()->status,
                'message' => $data->getData()->message,
            ]);
        }

        return redirect()->route('customer.chatbox.index')->with([
            'status'  => 'error',
            'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }

    public function refresh_sidebar(): JsonResponse
    {
        $unread_count = ChatBox::where('notification', '>', 0)
            ->where('reply_by_customer', true)
            ->where('user_id', Auth::user()->id)
            ->count();
        return response()->json([
            'status' => 'success',
            'unread_chats' => $unread_count
        ]);
    }

    /**
     * get chat messages
     */
    public function messages(ChatBox $box): JsonResponse
    {
        $box->update([
            'notification' => 0,
        ]);


        $timezone = Auth::user()->timezone ?? config('app.timezone');

        $data = ChatBoxMessage::where('box_id', $box->id)
            ->orderBy('created_at')
            ->select('message', 'send_by', 'media_url', 'box_id', 'created_at')
            ->get(['message', 'send_by', 'media_url', 'box_id', 'created_at'])
            ->toArray();

        $data = array_map(function ($message) use ($timezone) {
            $message['created_at'] = Carbon::parse($message['created_at'])->timezone($timezone)->format(config('app.date_format') . ', g:i A');

            return $message;
        }, $data);

        $jsonData = json_encode($data, true);

        return response()->json([
            'status' => 'success',
            'data'   => $jsonData,
            'starred' => $box->is_starred,
        ]);
    }

    /**
     * get chat messages
     */
    public function messagesWithNotification(ChatBox $box): JsonResponse
    {
        $data = ChatBoxMessage::where('box_id', $box->id)->select('message', 'send_by', 'media_url', 'box_id', 'created_at')->latest()->first()->toJson();


        return response()->json([
            'status'       => 'success',
            'data'         => $data,
            'notification' => $box->notification,
        ]);
    }

    /**
     * delete chatbox messages
     */
    public function delete(ChatBox $box): JsonResponse
    {
        $messages = ChatBoxMessage::where('box_id', $box->id)->delete();
        if ($messages) {
            $box->delete();

            return response()->json([
                'status'  => 'success',
                'message' => __('locale.campaigns.sms_was_successfully_deleted'),
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }

    /**
     * add to blacklist
     */
    public function block(ChatBox $box): JsonResponse
    {
        $status = Blacklists::create([
            'user_id' => auth()->user()->id,
            'number'  => $box->to,
            'reason'  => 'Blacklisted by ' . auth()->user()->displayName(),
        ]);

        if ($status) {

            $contact = Contacts::where('phone', $box->to)->first();
            $contact?->update([
                'status' => 'unsubscribe',
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => __('locale.blacklist.blacklist_successfully_added'),
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function loadChatUsers(Request $request)
    {
        $filter = $request->get('filter', 'unread');
        $search = $request->get('search', '');
        $page   = $request->get('page', 1);

        $query = ChatBox::where(function ($q) {
            $q->where('user_id', Auth::id())
                ->orWhere('pipeline_user', Auth::id());
        })->where('reply_by_customer', true);

        switch ($filter) {
            case 'unread':
                $query->where('notification', '>', 0);
                break;
            case 'read':
                $query->where('notification', '=', 0);
                break;
            case 'starred':
                $query->where('is_starred', true);
                break;
            case 'fresh-lead':
                $query->where('fresh_lead', true);
                break;
            case 'under-contract':
                $query->where('under_contract', true);
                break;
            case 'follow-up':
                $query->where('follow_up', true);
                break;
        }

        // Apply the search if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('from', 'LIKE', "%{$search}%")
                    ->orWhere('to', 'LIKE', "%{$search}%");
            });
        }

        // Sorting by latest updated
        $query->orderByDesc('updated_at')->with(['chatBoxMessages', 'contact']);

        // Paginate the results
        $chat_box = $query->paginate(500, ['*'], 'page', $page);
        return view('customer.ChatBox.partials._chat_list', compact('chat_box'))->render();
    }





    /**
     * add to blacklist
     */
    public function pin(ChatBox $box): JsonResponse
    {
        $box->update([
            'is_starred' => ! $box->is_starred,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => $box->is_starred ? "Added to pinned" : "Removed from pinned",
        ]);
    }
    /**
     * update chat contact info
     */
    public function update_contact(Request $request)
    {
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $userOrg = $request->input('user_org');
        $leadStatus = $request->input('lead_status');
        $contactGroup = $request->input('contact_group');
        $chat_id = $request->input('chat_id');
        $chat_box = ChatBox::firstWhere('uid', $chat_id);
        $contact = Contacts::firstWhere('phone', $chat_box->to);
        $request->merge([
            'PHONE' => $chat_box->to,
        ]);
        if ($contact) {
            $contact->updateFields($request->all());
            $this->update_lead_status($first_name, $last_name, $chat_box->to, $userOrg, $leadStatus);
        } else {

            $contactGroup = ContactGroups::find($contactGroup);
            $new = $this->contactGroups->createContactFromRequest($contactGroup, $request->all());
            $this->update_lead_status($first_name, $last_name, $chat_box->to, $userOrg, $leadStatus);
        }
        return response()->json([
            'status'  => 'success',
            'response' => response()->json($contact),
            'message' => 'Contact details updated successfully'
        ]);
    }
    /**
     * update follow up info
     */
    public function add_pipeline(Request $request)
    {
        Log::info("adding to pipelines");
        $userId = $request->input('user_id');
        $chat_id = $request->input('chat_id');
        $pipeline = $request->input('pipeline');
        $crm_user = $request->input('crm_user');
        $chat_box = ChatBox::firstWhere('uid', $chat_id);
        $chat_box->$pipeline = 1;
        $chat_box->pipeline_user = $crm_user;
        $chat_box->save();
        $data = ChatBoxMessage::where('box_id', $chat_box->id)
            ->orderBy('created_at')
            ->select('message', 'send_by', 'media_url', 'box_id', 'created_at')
            ->get(['message', 'send_by', 'media_url', 'box_id', 'created_at'])
            ->toArray();
        $timezone = Auth::user()->timezone ?? config('app.timezone');

        $data = array_map(function ($message) use ($timezone) {
            $message['created_at'] = Carbon::parse($message['created_at'])->timezone($timezone)->format(config('app.date_format') . ', g:i A');

            return $message;
        }, $data);

        $messages = json_encode($data, true);
        if ($pipeline == "follow_up") {
            $chat_box->fresh_lead = 0;
            $chat_box->under_contract = 0;
            $chat_box->save();

            // $this->follow_up_lead($chat_box->to, $userId, $messages);
        } else if ($pipeline == "under_contract") {
            $chat_box->fresh_lead = 0;
            $chat_box->follow_up = 0;
            $chat_box->save();

            // $this->under_contract_lead( $chat_box->to, $userId,$messages);
        } else {
            //$this->fresh_lead( $chat_box->to, $userId,$messages);
            $chat_box->follow_up = 0;
            $chat_box->under_contract = 0;
            $chat_box->save();
        }
        return response()->json([
            'status'  => 'success',
            'response' => response()->json($chat_box),
            'message' => 'Pipeline details updated successfully'
        ]);
    }
    /**
     * update follow up info
     */
    // public function under_contract(Request $request)
    // {
    //     $userId = $request->input('user_id');
    //     $chat_id = $request->input('chat_id');
    //     $chat_box = ChatBox::firstWhere('uid', $chat_id);
    //     $chat_box->under_contract = 1;
    //     $chat_box->save();
    //     $data = ChatBoxMessage::where('box_id', $chat_box->id)
    //         ->orderBy('created_at')
    //         ->select('message', 'send_by', 'media_url', 'box_id', 'created_at')
    //         ->get(['message', 'send_by', 'media_url', 'box_id', 'created_at'])
    //         ->toArray();
    //     $timezone = Auth::user()->timezone ?? config('app.timezone');

    //     $data = array_map(function ($message) use ($timezone) {
    //         $message['created_at'] = Carbon::parse($message['created_at'])->timezone($timezone)->format(config('app.date_format') . ', g:i A');

    //         return $message;
    //     }, $data);

    //     $messages = json_encode($data, true);
    //     return response()->json([
    //         'status'  => 'success',
    //         'response' => response()->json($chat_box),
    //         'message' => 'Under Contract details updated successfully'
    //     ]);
    // }
    /**
     * add note
     */
    public function add_note(Request $request)
    {
        $add_note = $request->input('addNote');
        $chat_id = $request->input('chat_id');
        $chat_box = ChatBox::firstWhere('uid', $chat_id);
        $chat_box->note = $add_note;
        $chat_box->save();
        return response()->json([
            'status'  => 'success',
            'response' => response()->json($chat_box),
            'message' => 'Notes for this contact updated successfully'
        ]);
    }
    private function update_lead_status($first_name, $last_name, $phone, $user_org, $lead_status)
    {
        $response = Http::get('https://internaltools.godspeedoffers.com/api/update-lead-status', [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'user_org' => $user_org,
            'lead_status' => $lead_status,
        ]);

        if ($response->successful()) {
            return response()->json(['message' => 'Lead status updated successfully.']);
        } else {
            return response()->json(['error' => 'Failed to update lead status.'], $response->status());
        }
    }
    private function follow_up_lead($phone, $user_id, $messages)
    {
        $response = Http::get('https://internaltools.godspeedoffers.com/api/follow_up', [
            'phone' => $phone,
            'user_id' => $user_id,
            'messages' => $messages
        ]);

        if ($response->successful()) {
            return response()->json(['message' => 'Follow up status updated successfully.']);
        } else {
            return response()->json(['error' => 'Failed to update Follow up status.'], $response->status());
        }
    }
    private function under_contract_lead($phone, $user_id, $messages)
    {
        $response = Http::get('https://internaltools.godspeedoffers.com/api/under_contract', [
            'phone' => $phone,
            'user_id' => $user_id,
            'messages' => $messages
        ]);

        if ($response->successful()) {
            Log::info("Under contract Api hit successfully");
            return response()->json(['message' => 'under contract status updated successfully.']);
        } else {
            return response()->json(['error' => 'Failed to update under contract status.'], $response->status());
        }
    }
    private function fresh_lead($phone, $user_id, $messages)
    {
        $response = Http::get('https://internaltools.godspeedoffers.com/api/fresh_lead', [
            'phone' => $phone,
            'user_id' => $user_id,
            'messages' => $messages
        ]);

        if ($response->successful()) {
            Log::info("Fresh lead Api hit successfully");
            return response()->json(['message' => 'Fresh lead status updated successfully.']);
        } else {
            return response()->json(['error' => 'Failed to update Follow up status.'], $response->status());
        }
    }
    /**
     * get note
     */
    public function get_note(ChatBox $box): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'note'   => $box->note,
        ]);
    }
    /**
     * toggle star
     */
    public function toggle_star(ChatBox $box): JsonResponse
    {
        $box->is_starred = !$box->is_starred;
        $box->save();
        return response()->json(
            [
                'status' => 'success',
                'message'   => 'Chat marked as favorite',
            ]
        );
    }
    /**
     * remove followup
     */
    public function remove_followup(ChatBox $box): JsonResponse
    {
        $box->follow_up = 0;
        $box->save();
        return response()->json(
            [
                'status' => 'success',
                'message'   => 'follow up removed',
            ]
        );
    }
    /**
     * remove undercontract
     */
    public function remove_undercontract(ChatBox $box): JsonResponse
    {
        $box->under_contract = 0;
        $box->save();
        return response()->json(
            [
                'status' => 'success',
                'message'   => 'under_contract removed',
            ]
        );
    }
    /**
     * remove freshlead
     */
    public function remove_freshlead(ChatBox $box): JsonResponse
    {
        $box->fresh_lead = 0;
        $box->save();
        Log::info("freshlead set to $box->fresh_lead");
        return response()->json(
            [
                'status' => 'success',
                'message'   => 'fresh lead removed',
            ]
        );
    }
    /**
     * get chat additional_info
     */
    public function additional_info(ChatBox $box): JsonResponse
    {
        $info = array();
        $contact_group_id = null;
        $first_name = '';
        $last_name = '';
        $contact = Contacts::firstWhere('phone', $box->to);
        if ($contact) {
            $group = ContactGroups::find($contact->group_id);
            $contact_group_id = $contact->group_id;
        } else {
            $group = null;
        }
        $groups = ContactGroups::all();
        if (! $group) {
            $contact_group_id = null;
            $first_name = '';
            $last_name = '';
        } else {
            foreach ($group->getFields as $field) {
                $subscriber = Contacts::where('group_id', $contact->group_id)->where('uid', $contact->uid)->first();
                if ($field->tag === 'FIRST_NAME') {
                    $first_name = trim($subscriber->getValueByField($field));
                }
                if ($field->tag === 'LAST_NAME') {
                    $last_name = trim($subscriber->getValueByField($field));
                }
            }
        }
        $crm_users = User::all();
        return response()->json([
            'status' => 'success',
            'group_id'   => $contact_group_id,
            'groups' => $groups,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_and_orgs' => $this->fetchUsersAndOrgs()->original,
            'crm_users' => $crm_users
        ]);
    }

    private function fetchUsersAndOrgs()
    {
        // Send GET request to the API
        $response = Http::get('https://internaltools.godspeedoffers.com/api/get-user-and-orgs');

        // Check if the request was successful
        if ($response->successful()) {
            // Parse and return the response data
            $data = $response->json();
            return response()->json($data);
        } else {
            // Handle error and return an appropriate response
            return response()->json(['error' => 'Failed to fetch data from API'], $response->status());
        }
    }


    /**
     * reply message
     *
     *
     * @throws AuthorizationException
     * @throws NumberParseException
     */
    public function reply(ChatBox $box, Campaigns $campaign, Request $request): JsonResponse
    {
        if (config('app.stage') == 'demo') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sorry! This option is not available in demo mode',
            ]);
        }

        $this->authorize('chat_box');

        if (empty($request->message)) {
            return response()->json([
                'status'  => 'error',
                'message' => __('locale.campaigns.insert_your_message'),
            ]);
        }

        $user = Auth::user();

        $sending_server = SendingServer::find($box->sending_server_id);

        if (! $sending_server) {
            return response()->json([
                'status'  => 'error',
                'message' => __('locale.campaigns.sending_server_not_available'),
            ]);
        }

        $sender_id = $box->from;


        if ($request->hasFile('file')) {
            $media_url = Tool::uploadImage($request->file('file'));
        } else {
            // Handle the case where no file is uploaded, if necessary
            $media_url = null; // or set a default value
        }

        if ($sending_server->settings == 'Whatsender' || $sending_server->type == 'whatsapp') {
            $sms_type          = 'whatsapp';
            $capabilities_type = $sms_type;
        } else if ($media_url) {
            Log::info('Trying to send MMS');
            $sms_type          = 'mms';
            $capabilities_type = 'mms';
        } else {
            $sms_type          = 'plain';
            $capabilities_type = 'sms';
        }
        $input = [
            'sender_id'      => $sender_id,
            'originator'     => 'phone_number',
            'sending_server' => $sending_server->id,
            'sms_type'       => $sms_type,
            'message'        => $request->message,
            'exist_c_code'   => 'yes',
            'user'           => $user,
            'media_url'      => $media_url,
        ];

        if ($user->customer->getOption('sender_id_verification') == 'yes') {

            $number = PhoneNumbers::where('user_id', $user->id)->where('number', $sender_id)->where('status', 'assigned')->first();

            if (! $number) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.sender_id.sender_id_invalid', ['sender_id' => $sender_id]),
                ]);
            }

            $capabilities = str_contains($number->capabilities, $capabilities_type);

            if (! $capabilities) {
                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.sender_id.sender_id_sms_capabilities', ['sender_id' => $sender_id, 'type' => $sms_type]),
                ]);
            }

            $input['originator']   = 'phone_number';
            $input['phone_number'] = $sender_id;
        }

        try {

            $phoneUtil         = PhoneNumberUtil::getInstance();
            $phoneNumberObject = $phoneUtil->parse('+' . $box->to);

            if ($phoneUtil->isPossibleNumber($phoneNumberObject)) {
                $input['country_code'] = $phoneNumberObject->getCountryCode();
                $input['recipient']    = $phoneNumberObject->getNationalNumber();
                $input['region_code']  = $phoneUtil->getRegionCodeForNumber($phoneNumberObject);

                $data = $this->campaigns->quickSend($campaign, $input);

                if (isset($data->getData()->status)) {
                    if ($data->getData()->status == 'success') {
                        $sending_number = PhoneNumbers::where('number', $box->from)->first();
                        if ($sending_number->AI_SMS && $sending_number->AI_SMS !== 0) {
                            Log::info("This is an AI tagged number");
                            $wake_time = $this->fetchWakeTime($sending_number->AI_SMS)['wake_time'];

                            Log::info($wake_time);
                            $chat_box = ChatBox::firstWhere('uid', $box->uid);
                            $chat_box->wake_time = $wake_time;
                            $chat_box->save();
                        }
                        return response()->json([
                            'status'  => 'success',
                            'message' => __('locale.campaigns.message_successfully_delivered'),
                        ]);
                    }



                    return response()->json([
                        'status'  => $data->getData()->status,
                        'message' => $data->getData()->message,
                    ]);
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => __('locale.exceptions.something_went_wrong'),
                ]);
            }

            return response()->json([
                'status'  => 'error',
                'message' => __('locale.customer.invalid_phone_number', ['phone' => $box->to]),
            ]);
        } catch (NumberParseException $exception) {
            return response()->json([
                'status'  => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }


    public function view(ChatBox $box): JsonResponse
    {
        $messages = ChatBoxMessage::where('box_id', $box->id)->delete();
        if ($messages) {
            $box->delete();

            return response()->json([
                'status'  => 'success',
                'message' => __('locale.campaigns.sms_was_successfully_deleted'),
            ]);
        }

        return response()->json([
            'status'  => 'error',
            'message' => __('locale.exceptions.something_went_wrong'),
        ]);
    }

    public function view_chat_contact(ChatBox $box): JsonResponse
    {
        Log::info($box->to);
        $contact = Contacts::firstWhere('phone', $box->to);
        $group_id = ContactGroups::find($contact->group_id);

        $subscriber = Contacts::where('group_id', $contact->group_id)->where('uid', $contact->uid)->first();

        if (! $subscriber) {
            return response()->json(['status' => 'error', 'message' => __('locale.http.404.description')], 404);
        }

        $output = $subscriber->only('uid', 'phone', 'status');

        $values = [];

        foreach ($group_id->getFields as $field) {
            // if ($field->tag != 'PHONE') {
            $values[$field->tag] = $subscriber->getValueByField($field);
            //}
        }
        $values['Workflow_message'] = $this->get_message($box->to);
        $output['custom_fields'] = $values;

        return response()->json([
            'status'  => $output,
            'message' => __('locale.blacklist.blacklist_successfully_added'),
        ]);
    }
    private function get_message($phoneNumber)
    {
        try {
            Log::info('workflow key',auth()->user()->workflow_apikey);
            $response = Http::get("https://internaltools.godspeedoffers.com/api/get-message/{$phoneNumber}", [
                'workflow_apikey' => auth()->user()->workflow_apikey,
            ]);
            // Check if the response status is OK
            if ($response->successful()) {
                $data = $response->json();

                // Ensure the message key exists in the response data
                if (isset($data['message'])) {
                    return $data['message'];
                }
            }
        } catch (\Exception $e) {
            Log::info("Error: $e");
            // Log the exception if needed
        }

        // Return fallback message on failure
        return 'No workflow message found';
    }
    public function search_chats(Request $request)
    {
        // Get the search term from the request
        $searchTerm = $request->input('query');

        // Search the database for matching chat users and contacts
        $results = ChatBox::where('to',  $searchTerm)
            // ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
            ->get(); // Use `get()` to return multiple results

        // Format the results to include required data
        $formattedResults = $results->map(function ($chat) {
            return [
                'id' => $chat->id,
                'uid' => $chat->uid,
                'updated_at' => $chat->updated_at->timezone('America/New_York')->toDateTimeString(), // Convert to New York timezone
                'last_message' => \Illuminate\Support\Str::limit(addslashes(\App\Helpers\Helper::last_message($chat->id)), 15),
                'name' => \App\Helpers\Helper::contact_name1($chat->to),
                'avatar' => $chat->avatar, // Assuming avatar field exists
                'notification' => $chat->notification, // Assuming notification field exists
                'is_starred' => $chat->is_starred, // Assuming is_starred field exists
            ];
        });

        // Return the formatted results as JSON
        return response()->json($formattedResults);
    }

    private function fetchWakeTime($assistant_id)
    {
        try {
            // Make the GET request to the API with the assistant ID in the URL
            $response = Http::get("https://internaltools.godspeedoffers.com/api/wake-time/{$assistant_id}");

            // Check for a successful response
            if ($response->successful()) {
                // Log the successful response
                Log::info("Successfully fetched wake time for Assistant ID: {$assistant_id}", $response->json());

                // Return the JSON response
                return $response->json();
            } else {
                // Log error details
                Log::error("Failed to fetch wake time", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Failed to fetch wake time. API responded with an error.',
                ];
            }
        } catch (\Exception $e) {
            // Handle exceptions and log the error
            log::error("Error fetching wake time for Assistant ID: {$assistant_id}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred while fetching wake time.',
            ];
        }
    }
}
