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
<!-- Chat Sidebar area -->
<div class="sidebar-content">
    <span class="sidebar-close-icon">
        <i data-feather="x"></i>
    </span>


    <!-- Sidebar header start -->
    <div class="chat-fixed-search">
        <div class="d-flex align-items-center w-100">
            <div class="input-group input-group-merge ms-1 w-100">
                <span class="input-group-text round"><i data-feather="search" class="text-muted"></i></span>
                <input type="text" class="form-control round" id="chat-search-new"
                    placeholder="Enter number and press enter">
            </div>
            <div class="d-block d-md-none">
                <a href="{{ route('customer.chatbox.new') }}" class="text-dark ms-1"><i data-feather="plus-circle"></i>
                </a>
            </div>
        </div>
    </div>
    <!-- Sidebar header end -->

    <!-- Sidebar Users start -->
    <div class="chat-tabs">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="chatTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link " id="read-tab" data-bs-toggle="tab" href="#read" role="tab"
                    aria-controls="read" aria-selected="false">Read</a>
            </li>
            <li class="nav-item" role="presentation">
                <a onclick="reloadPage()" class="nav-link" id="unread-tab" data-bs-toggle="tab" href="#unread"
                    role="tab" aria-controls="unread" aria-selected="false">Unread
                    <span id="unread_count"
                        class="badge bg-primary rounded-pill float-end notification_count">{{ $unread_chats }}</span>
                </a>


            </li>
            <li onclick="reloadStarred()" class="nav-item" role="presentation">
                <a class="nav-link" id="starred-tab" data-bs-toggle="tab" href="#starred" role="tab"
                    aria-controls="starred" aria-selected="false">Starred</a>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content" id="chatTabsContent">
            <!-- Read Tab -->
            <div class="tab-pane fade show active" id="read" role="tabpanel" aria-labelledby="read-tab">
                <div id="read-users-list" class="chat-user-list-wrapper list-group chat-list-scrollable">
                    <ul class="chat-users-list chat-list media-list">
                        @foreach ($chat_box as $chat)
                            @if ($chat->notification == 0)
                                <li data-id="{{ $chat->uid }}" data-box-id="{{ $chat->id }}">
                                    <span class="avatar">
                                        <img src="{{ asset('images/profile/profile.jpg') }}" height="36"
                                            width="54" alt="Avatar" />
                                    </span>
                                    <div class="chat-info flex-grow-1">
                                        <h5 class="mb-0">{{ \App\Helpers\Helper::contact_name1($chat->to) }}</h5>
                                        <p class="card-text text-truncate">
                                            {{ \Illuminate\Support\Str::limit(\App\Helpers\Helper::last_message($chat->id), 15) }}
                                        </p>
                                    </div>
                                    <div class="chat-meta text-nowrap">
                                        <small
                                            class="float-end mb-25 chat-time">{{ Tool::customerDateTime($chat->updated_at) }}</small>
                                        @if ($chat->notification)
                                            <span
                                                class="badge bg-primary rounded-pill float-end notification_count">{{ $chat->notification }}</span>
                                        @endif
                                        <button type="button"
                                            class="btn  {{ $chat->is_starred ? 'bg-warning' : '' }} p-0 star-btn float-end"
                                            onclick="toggleStar('{{ $chat->uid }}', this)"
                                            title="{{ $chat->is_starred ? 'Unmark as Starred' : 'Mark as Starred' }}">
                                            <i data-feather="star"
                                                class="cursor-pointer font-medium-2  {{ $chat->is_starred ? 'text-white' : 'text-secondary' }}"></i>
                                        </button>
                                    </div>
                                </li>
                            @endif
                        @endforeach
                        <br>
                        <div class="pagination ">
                            {{ $chat_box->links() }}
                        </div>
                    </ul>
                </div>
            </div>

            <!-- Unread Tab -->
            <div class="tab-pane fade" id="unread" role="tabpanel" aria-labelledby="unread-tab">
                <div id="unread-users-list" class="chat-user-list-wrapper list-group chat-list-scrollable">
                    <ul class="chat-users-list chat-list media-list">
                        @foreach ($unread_box as $chat)
                            @if ($chat->notification > 0)
                                <li data-id="{{ $chat->uid }}" data-box-id="{{ $chat->id }}">
                                    <span class="avatar">
                                        <img src="{{ asset('images/profile/profile.jpg') }}" height="36"
                                            width="54" alt="Avatar" />
                                    </span>
                                    <div class="chat-info flex-grow-1">
                                        <h5 class="mb-0">{{ \App\Helpers\Helper::contact_name1($chat->to) }}</h5>
                                        <p class="card-text text-truncate">
                                            {{ \Illuminate\Support\Str::limit(\App\Helpers\Helper::last_message($chat->id), 15) }}
                                        </p>
                                    </div>
                                    <div class="chat-meta text-nowrap">
                                        <small
                                            class="float-end mb-25 chat-time">{{ Tool::customerDateTime($chat->updated_at) }}</small>
                                        @if ($chat->notification)
                                            <span
                                                class="badge bg-primary rounded-pill float-end notification_count">{{ $chat->notification }}</span>
                                        @endif
                                        <button type="button"
                                            class="btn  {{ $chat->is_starred ? 'bg-warning' : '' }} p-0 star-btn float-end"
                                            onclick="toggleStar('{{ $chat->uid }}', this)"
                                            title="{{ $chat->is_starred ? 'Unmark as Starred' : 'Mark as Starred' }}">
                                            <i data-feather="star"
                                                class="cursor-pointer font-medium-2  {{ $chat->is_starred ? 'text-white' : 'text-secondary' }}"></i>
                                        </button>
                                    </div>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
                <div class="pagination">
                    {{ $unread_box->links() }}
                </div>
            </div>

            <!-- Starred Tab -->
            <div class="tab-pane fade" id="starred" role="tabpanel" aria-labelledby="starred-tab">
                <div id="starred-users-list" class="chat-user-list-wrapper list-group chat-list-scrollable">
                    <ul class="chat-users-list chat-list media-list">
                        @foreach ($starred_box as $chat)
                            @if ($chat->is_starred)
                                <li data-id="{{ $chat->uid }}" data-box-id="{{ $chat->id }}">
                                    <span class="avatar">
                                        <img src="{{ asset('images/profile/profile.jpg') }}" height="36"
                                            width="54" alt="Avatar" />
                                    </span>
                                    <div class="chat-info flex-grow-1">
                                        <h5 class="mb-0">{{ \App\Helpers\Helper::contact_name1($chat->to) }}</h5>
                                        <p class="card-text text-truncate">
                                            {{ \Illuminate\Support\Str::limit(\App\Helpers\Helper::last_message($chat->id), 15) }}
                                        </p>
                                    </div>
                                    <div class="chat-meta text-nowrap">
                                        <small
                                            class="float-end mb-25 chat-time">{{ Tool::customerDateTime($chat->updated_at) }}</small>
                                        @if ($chat->notification)
                                            <span
                                                class="badge bg-primary rounded-pill float-end notification_count">{{ $chat->notification }}</span>
                                        @endif
                                        <button type="button"
                                            class="btn  {{ $chat->is_starred ? 'bg-warning' : '' }} p-0 star-btn float-end"
                                            onclick="toggleStar('{{ $chat->uid }}', this)"
                                            title="{{ $chat->is_starred ? 'Unmark as Starred' : 'Mark as Starred' }}">
                                            <i data-feather="star"
                                                class="cursor-pointer font-medium-2  {{ $chat->is_starred ? 'text-white' : 'text-secondary' }}"></i>
                                        </button>
                                    </div>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    <div class="pagination">
                        {{ $starred_box->links() }}
                    </div>
                </div>
            </div>

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

            // Optionally remove activeTab from localStorage if needed
            localStorage.removeItem('activeTab');
        };


        function refreshChatList() {
            $.ajax({
                url: '{{ url('/chat-box/refresh-chat-box') }}', // Call the new route
                method: 'GET',
                success: function(response) {
                    console.log(response)
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
    <!-- Sidebar Users end -->
</div>
<!--/ Chat Sidebar area -->
