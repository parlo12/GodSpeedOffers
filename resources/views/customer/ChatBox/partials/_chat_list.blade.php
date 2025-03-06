@foreach ($chat_box as $chat)
    <li data-id="{{ $chat->uid }}" data-box-id="{{ $chat->id }}">
        <span class="avatar">
            <img src="{{ asset('images/profile/profile.jpg') }}" height="36" width="54" alt="Avatar" />
        </span>
        <div class="chat-info flex-grow-1">
            <h6 class="mb-0">{{ \App\Helpers\Helper::contact_name1($chat->to) }}</h6>

            <p class="card-text mb-0 text-truncate">
                {{ $chat->from }}
            </p>

            @if (!empty($chat->chatBoxMessages) && !empty($chat->chatBoxMessages->last()->message))
                <p class="card-text mb-0 text-truncate">
                    {{ str_limit($chat->chatBoxMessages->last()->message, 18) }}
                </p>
            @endif
        </div>
        <div class="chat-meta text-nowrap">
            <small
                class="float-end mb-25 chat-time">{{ \App\Library\Tool::customerDateTime($chat->updated_at) }}</small>
            @if ($chat->notification)
                <span
                    class="badge bg-primary rounded-pill float-end notification_count">{{ $chat->notification }}</span>
            @else
                <div class="counter" hidden>
                    <span class="badge bg-primary rounded-pill float-end notification_count"></span>
                </div>
            @endif
            @if ($chat->follow_up)
                <button type="button"
                    class="btn  {{ $chat->follow_up ? 'bg-danger' : '' }} p-0 star-btn float-end me-1"
                    onclick="removeFollowup('{{ $chat->uid }}', this)" title="Remove Follow Up">
                    <i data-feather="trash-2"
                        class="cursor-pointer font-medium-2  {{ $chat->follow_up ? 'text-white' : 'text-secondary' }}"></i>
                </button>
            @elseif($chat->under_contract)
                <button type="button"
                    class="btn  {{ $chat->under_contract ? 'bg-danger' : '' }} p-0 star-btn float-end me-1"
                    onclick="removeUnderContract('{{ $chat->uid }}', this)" title="Remove Under Contract">
                    <i data-feather="trash-2"
                        class="cursor-pointer font-medium-2  {{ $chat->under_contract ? 'text-white' : 'text-secondary' }}"></i>
                </button>
            @else
                <button type="button"
                    class="btn {{ $chat->fresh_lead ? 'bg-danger' : '' }} p-0 star-btn float-end me-1"
                    onclick="removeFreshLead('{{ $chat->uid }}', this)" title="Remove Fresh Lead">
                    <i data-feather="trash-2"
                        class="cursor-pointer font-medium-2 {{ $chat->fresh_lead ? 'text-white' : 'text-secondary' }}"></i>
                </button>
            @endif
        </div>
    </li>
@endforeach
