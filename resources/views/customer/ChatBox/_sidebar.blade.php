@php
    use App\Helpers\Helper;
    use App\Library\Tool;
@endphp
<style>
    .chat-list-scrollable {
        max-height: 50vh;
        overflow-y: auto;
    }

    @media (min-height: 800px) {

        .chat-list-scrollable {
            max-height: 70vh;
            color: red;
        }
    }
</style>
<div class="sidebar-content">

    <span class="sidebar-close-icon">
        <i data-feather="x"></i>
    </span>

    <div class="text-center pt-1 pb-1">
        <div role="group" class="tab-group mb-1 btn-group">
            <button type="button" class="btn tab-button btn-primary btn-sm" data-filter="recents">{{ __('locale.labels.recents') }}</button>
            <button type="button" class="btn tab-button btn-outline-primary btn-sm" data-filter="unread">{{ __('locale.labels.unread') }}</button>
            <button type="button" class="btn tab-button btn-outline-primary btn-sm" data-filter="read">{{ __('locale.labels.read') }}</button>
        </div>
        <div role="group" class="tab-group btn-group">
                <button type="button" class="btn tab-button btn-outline-primary btn-sm" data-filter="under-contracts">Under Contracts</button>
                <button type="button" class="btn tab-button btn-outline-primary btn-sm" data-filter="fresh-leads">Fresh Leads</button>
                <button type="button" class="btn tab-button btn-outline-primary btn-sm" data-filter="follow-ups">Follow Ups</button>
        </div>
    </div>

    <!-- Sidebar header start -->
    <div class="chat-fixed-search">
        <div class="d-flex align-items-center w-100">
            <div class="input-group input-group-merge ms-1 w-100">
                <span class="input-group-text round"><i data-feather="search" class="text-muted"></i></span>
                <input type="text" class="form-control round" id="chat-search"
                    placeholder="{{ __('locale.labels.search') }}">
            </div>
            <div class="d-block d-md-none">
                <a href="{{ route('customer.chatbox.new') }}" class="text-dark ms-1"><i data-feather="plus-circle"></i>
                </a>
            </div>
        </div>
    </div>
    <!-- Sidebar header end -->

    <!-- Loader -->
    <div id="loader" class="text-center" style="display:none;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only"></span>
        </div>
    </div>

    <!-- Sidebar Users start -->
    <div id="users-list" class="chat-user-list-wrapper list-group">

        @if ($pinnedChats->count() > 0)
            <h4 class="chat-list-title">{{ __('locale.labels.pin') }}</h4>

            <ul class="chat-users-list-pinned chat-list media-list">
                @foreach ($pinnedChats as $chat)
                    <li data-id="{{ $chat->uid }}" data-box-id="{{ $chat->id }}">
                        <span class="avatar">
                            <img src="{{ asset('images/profile/profile.jpg') }}" height="36" width="54"
                                alt="Avatar" />
                        </span>
                        <div class="chat-info flex-grow-1">
                            <h6 class="mb-0">{{ \App\Helpers\Helper::contact_name1($chat->to) }}</h6>
                            {{-- @if (!empty($chat->contact) && !empty($chat->contact->getFullName()))
                                <p class="card-text mb-0 text-truncate">
                                    {{ str_limit($chat->contact->getFullName(), 15) }}
                                </p>
                            @endif --}}
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
                        </div>
                    </li>
                @endforeach
            </ul>

            <h4 class="chat-list-title">{{ __('locale.labels.chats') }}</h4>

        @endif

        <ul class="chat-users-list chat-list media-list">
            <!-- Chat users will be loaded here via Ajax -->
        </ul>
    </div>
    <!-- Sidebar Users end -->

    <!-- Load More button -->
    <div class="text-center" id="load-more-wrapper" style="display:none;">
        <button class="btn btn-sm btn-primary mt-1" id="load-more"><i data-feather="refresh-cw"></i></button>
    </div>

</div>
<script>
    function reloadPage() {
        localStorage.setItem('activeTab', 'unread-tab');
        window.location.href = "https://www.godspeedoffers.com/chat-box?page=1";

    }

    function reloadStarred() {
        localStorage.setItem('activeTab', 'starred-tab');
        window.location.href = "https://www.godspeedoffers.com/chat-box?page=1";

    }

    function reloadFollowup() {
        localStorage.setItem('activeTab', 'followup-tab');
        // window.location.href = "https://www.godspeedoffers.com/chat-box?page=1";

    }

    function reloadUndercontract() {
        localStorage.setItem('activeTab', 'undercontract-tab');
        //window.location.href = "https://www.godspeedoffers.com/chat-box?page=1";

    }

    function reloadFreshlead() {
        localStorage.setItem('activeTab', 'freshlead-tab');
        //window.location.href = "https://www.godspeedoffers.com/chat-box?page=1";

    }

    window.onload = function() {
        const activeTab = localStorage.getItem('activeTab');
        const unreadTab = document.getElementById('unread-tab');
        const readTab = document.getElementById('read-tab');
        const starredTab = document.getElementById('starred-tab');

        // Remove active classes from all tabs
        unreadTab.classList.remove('show', 'active');
        readTab.classList.remove('show', 'active');
        starredTab.classList.remove('show', 'active');

        // Check if activeTab exists in localStorage
        if (activeTab && document.getElementById(activeTab)) {
            // Set the active tab based on the stored value
            document.getElementById(activeTab).classList.add('active');
            document.getElementById(activeTab).setAttribute('aria-selected', 'true');

            // Set the corresponding tab pane to active
            document.querySelector('.tab-pane.show.active')?.classList.remove('show', 'active');
            document.getElementById(activeTab.replace('-tab', '')).classList.add('show', 'active');
        } else {
            // Default to the read tab if no active tab is stored
            readTab.classList.add('active');
            readTab.setAttribute('aria-selected', 'true');
            document.getElementById('read').classList.add('show', 'active');
        }

        localStorage.removeItem('activeTab');
    };


    function refreshChatList() {
        $.ajax({
            url: '{{ url('/chat-box/refresh-chat-box') }}', // Call the new route
            method: 'GET',
            success: function(response) {
                $('#unread_count').html(response.unread_chats ? response.unread_chats : '');
            }
        });
    }

    // Refresh the chat list every 3 seconds
    setInterval(refreshChatList, 7000);

    function toggleStar(chatId, element) {
        // Send AJAX request to toggle the starred status
        fetch(`/chat-box/${chatId}/toggle-star`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
            }).then(response => response.json())
            .then(data => {
                if (data.status == 'success') {
                    console.log(data);
                    location.reload()
                    // Toggle the star icon
                } else {
                    console.error('Error toggling star:', data.message);
                }
            }).catch(error => console.error('Error:', error));
    }

    function removeFollowup(chatId, element) {
        // Send AJAX request to toggle the starred status
        fetch(`/chat-box/${chatId}/remove-followup`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
            }).then(response => response.json())
            .then(data => {
                if (data.status == 'success') {
                    console.log(data);
                    location.reload()
                    // Toggle the star icon
                } else {
                    console.error('Error removing follow_up:', data.message);
                }
            }).catch(error => console.error('Error:', error));
    }

    function removeFreshLead(chatId, element) {
        // Send AJAX request to toggle the starred status
        fetch(`/chat-box/${chatId}/remove-freshlead`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
            }).then(response => response.json())
            .then(data => {
                if (data.status == 'success') {
                    console.log(data);
                    location.reload()
                    // Toggle the star icon
                } else {
                    console.error('Error removing fresh lead:', data.message);
                }
            }).catch(error => console.error('Error:', error));
    }

    function removeUnderContract(chatId, element) {
        // Send AJAX request to toggle the starred status
        fetch(`/chat-box/${chatId}/remove-undercontract`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
            }).then(response => response.json())
            .then(data => {
                if (data.status == 'success') {
                    console.log(data);
                    location.reload()
                    // Toggle the star icon
                } else {
                    console.error('Error removing under contract:', data.message);
                }
            }).catch(error => console.error('Error:', error));
    }
    document.addEventListener('DOMContentLoaded', function() {
        const chatSearchInput = document.getElementById('chat-search-new');

        chatSearchInput.addEventListener('keydown', function(event) {
            // Check if the Enter key is pressed (key code 13 or 'Enter')
            if (event.key === 'Enter') {
                const query = chatSearchInput.value;

                // Only make the AJAX request if the search term is not empty
                if (query.length > 0) {
                    fetch(`/chat-box/search-chats?query=${query}`, {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Content-Type': 'application/json',
                            },
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Clear the current chat list
                            const chatList = document.querySelector('.chat-list');
                            chatList.innerHTML = '';

                            // Check if there are results
                            if (data.length > 0) {
                                data.forEach(chat => {
                                    var updated_at = chat.updated_at;

                                    var last_message = @json(\Illuminate\Support\Str::limit(addslashes(\App\Helpers\Helper::last_message($chat->id)), 15));
                                    var name = @json(\App\Helpers\Helper::contact_name1($chat->to));
                                    const listItem = `
                            <li data-id="${chat.uid}" data-box-id="${chat.id}">
                                <span class="avatar">
                                    <img src="${chat.avatar ? chat.avatar : '{{ asset('images/profile/profile.jpg') }}'}" height="36" width="54" alt="Avatar" />
                                </span>
                                <div class="chat-info flex-grow-1">
                                    <h5 class="mb-0">${chat.name}</h5>
                                    <p class="card-text text-truncate">${chat.last_message}</p>
                                </div>
                                <div class="chat-meta text-nowrap">
                                    <small class="float-end mb-25 chat-time">${chat.updated_at}</small>
                                    ${chat.notification ? `<span class="badge bg-primary rounded-pill float-end notification_count">${chat.notification}</span>` : ''}
                                    <button type="button" class="btn ${chat.is_starred ? 'bg-warning' : ''} p-0 star-btn float-end" onclick="toggleStar('${chat.uid}', this)" title="${chat.is_starred ? 'Unmark as Starred' : 'Mark as Starred'}">
                                        <i data-feather="star" class="cursor-pointer font-medium-2 ${chat.is_starred ? 'text-white' : 'text-secondary'}"></i>
                                    </button>
                                </div>
                            </li>`;
                                    chatList.insertAdjacentHTML('beforeend', listItem);
                                });

                                // Reinitialize feather icons after dynamically adding content
                                if (typeof feather !== 'undefined') {
                                    feather.replace();
                                }
                            } else {
                                // Display "No results" if no matches are found
                                chatList.innerHTML =
                                    '<li class="no-results show">No results found</li>';
                            }
                        })
                        .catch(error => {
                            console.error('Error retrieving search results:', error);
                        });
                } else {
                    // Clear search results if the query is empty
                    document.querySelector('.chat-list').innerHTML = '';
                }
            }
        });
    });
</script>
