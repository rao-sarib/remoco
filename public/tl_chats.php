<?php
require_once __DIR__ . '/../includes/db_connect.php';

// Verify user is Team Lead
if ($_SESSION['designation'] !== 'Team Lead') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['employee_id'];
$user_name = $_SESSION['employee_name'];

// Fetch chats for Team Lead. Bound rather than interpolated - see pm_chats.php.
$stmt = $conn->prepare("SELECT * FROM chats WHERE tl_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$chats = [];
while ($row = $result->fetch_assoc()) $chats[] = $row;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Manager Chats - REMOCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --role-color: #2563eb; --role-dark: #1e40af; }
        body { background-color: #f1f5f9; }
        .chat-container { display: flex; height: 80vh; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; background-color: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .chat-list { width: 30%; border-right: 1px solid #eee; overflow-y: auto; background-color: #f8f9fa; }
        .chat-window { width: 70%; display: flex; flex-direction: column; }
        .chat-header { padding: 15px; border-bottom: 1px solid #eee; background-color: var(--role-color); color: white; }
        .messages-container { flex: 1; padding: 20px; overflow-y: auto; background-color: #f0f4f8; display: flex; flex-direction: column; }
        .message-input { padding: 15px; border-top: 1px solid #eee; background-color: #fff; }
        .chat-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.3s; }
        .chat-item:hover, .chat-item.active { background-color: #dbeafe; }
        .message { padding: 10px 15px; margin-bottom: 10px; border-radius: 18px; max-width: 80%; word-wrap: break-word; animation: fadeIn 0.3s; }
        .message.self { background-color: var(--role-color); color: white; margin-left: auto; border-bottom-right-radius: 4px; }
        .message.other { background-color: #e5e7eb; margin-right: auto; border-bottom-left-radius: 4px; }
        .message-info { display: flex; justify-content: space-between; font-size: 0.8rem; margin-top: 3px; }
        .no-chats { display: flex; align-items: center; justify-content: center; height: 100%; text-align: center; padding: 20px; color: #64748b; }
        .unread-indicator { display: inline-block; width: 8px; height: 8px; background-color: var(--role-dark); border-radius: 50%; margin-left: 5px; }
        .video-container { background: #000; border-radius: 10px; overflow: hidden; position: relative; height: 400px; }
        #remote-video { width: 100%; height: 100%; }
        #local-video { width: 160px; height: 120px; position: absolute; bottom: 20px; right: 20px; z-index: 100; border: 2px solid white; }
        .call-controls { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 200; }
        .file-item { padding: 10px; border-bottom: 1px solid #eee; }
        .file-item a { text-decoration: none; display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex align-items-center mb-4">
            <a href="tl_dashboard.php" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i></a>
            <h1 class="mb-0"><i class="fas fa-comments me-2"></i>Team Lead Chats</h1>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Click on a chat to start messaging with your teams
        </div>
        
        <div class="chat-container">
            <div class="chat-list">
                <div class="p-3 border-bottom bg-light"><h5 class="mb-0">Your Chat Groups</h5></div>
                <?php if (!empty($chats)): ?>
                    <?php foreach ($chats as $chat): ?>
                        <div class="chat-item" data-room="<?= $chat['firebase_room_id'] ?>" data-chatid="<?= $chat['chat_id'] ?>" data-title="<?= htmlspecialchars($chat['chat_title']) ?>">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($chat['chat_title']) ?></h6>
                                <span class="unread-indicator"></span>
                            </div>
                            <small class="text-muted">Task: #<?= $chat['task_id'] ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-chats">
                        <div>
                            <i class="fas fa-comment-slash fa-3x mb-3"></i>
                            <p class="mb-0">No active chats available</p>
                            <small>Chats will appear when you create tasks</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="chat-window">
                <div class="chat-header d-flex justify-content-between align-items-center">
                    <h5 id="current-chat-title" class="mb-0">Select a chat</h5>
                    <button id="start-call" class="btn btn-sm btn-success" disabled>
                        <i class="fas fa-video"></i> Start Call
                    </button>
                </div>
                
                <div class="messages-container" id="messages-container">
                    <div class="text-center text-muted my-auto py-5">
                        <i class="fas fa-comments fa-3x mb-3"></i>
                        <h5>No chat selected</h5>
                        <p>Select a chat from the list to view messages</p>
                    </div>
                </div>
                
                <div class="message-input">
                    <div class="input-group mb-2">
                        <input type="text" id="message-input" class="form-control" placeholder="Type your message..." disabled>
                        <button id="send-btn" class="btn btn-primary" disabled>
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="input-group">
                        <input type="file" id="file-input" class="form-control" disabled>
                        <button id="upload-btn" class="btn btn-info" disabled>
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </div>
                
                <div id="video-call-container" class="mt-3 p-3 bg-dark rounded d-none">
                    <div class="video-container">
                        <div id="remote-video"></div>
                        <div id="local-video"></div>
                        <div class="call-controls">
                            <button id="end-call" class="btn btn-danger btn-lg">
                                <i class="fas fa-phone-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div id="file-list-container" class="mt-3">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Shared Files</span>
                            <button id="refresh-files" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-sync"></i>
                            </button>
                        </div>
                        <div id="file-list" class="list-group list-group-flush"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    /*
     * Dependency bootstrap.
     *
     * Replaces four plain <script src> tags. Those work when this page is opened
     * on its own but not when it is injected into a dashboard shell over AJAX,
     * which is how the app actually reaches chat. Each dependency is checked
     * before loading so nothing is fetched twice, and the chat body starts only
     * when everything it needs exists.
     */
    (function () {
        var DEPS = [
            ['jQuery',             'https://code.jquery.com/jquery-3.6.0.min.js'],
            ['firebase',           'https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js'],
            ['firebase.database',  'https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js'],
            ['AgoraRTC',           'https://download.agora.io/sdk/release/AgoraRTC_N-4.19.0.js']
        ];

        function present(path) {
            var node = window;
            var parts = path.split('.');
            for (var i = 0; i < parts.length; i++) {
                if (node === undefined || node === null) return false;
                node = node[parts[i]];
            }
            return node !== undefined && node !== null;
        }

        function load(src) {
            return new Promise(function (resolve, reject) {
                var existing = document.querySelector('script[data-remoco-dep="' + src + '"]');
                if (existing) {
                    if (existing.getAttribute('data-loaded') === '1') { resolve(); return; }
                    existing.addEventListener('load', function () { resolve(); });
                    existing.addEventListener('error', reject);
                    return;
                }
                var tag = document.createElement('script');
                tag.src = src;
                tag.async = false;
                tag.setAttribute('data-remoco-dep', src);
                tag.onload = function () { tag.setAttribute('data-loaded', '1'); resolve(); };
                tag.onerror = function () { reject(new Error('Failed to load ' + src)); };
                document.head.appendChild(tag);
            });
        }

        function ensure(i) {
            if (i >= DEPS.length) return Promise.resolve();
            var step = present(DEPS[i][0]) ? Promise.resolve() : load(DEPS[i][1]);
            return step.then(function () { return ensure(i + 1); });
        }

        function chatMain() {
        // Firebase configuration
        const firebaseConfig = {
            apiKey: <?= json_encode(FIREBASE_API_KEY, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
            authDomain: <?= json_encode(FIREBASE_AUTH_DOMAIN, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
            databaseURL: <?= json_encode(FIREBASE_DATABASE_URL_REGIONAL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
            projectId: <?= json_encode(FIREBASE_PROJECT_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
            storageBucket: <?= json_encode(FIREBASE_STORAGE_BUCKET, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
            messagingSenderId: <?= json_encode(FIREBASE_MESSAGING_SENDER_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
            appId: <?= json_encode(FIREBASE_APP_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>
        };
        
        // Initialize Firebase
        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }
        console.log("Firebase initialized");
        
        // CSRF token for the state-changing AJAX endpoints.
        const csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;

        // Agora configuration
        const agoraAppId = <?= json_encode(AGORA_APP_ID, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
        const agoraToken = <?= json_encode(AGORA_TOKEN, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
        
        // Global variables
        let currentRoom = null;
        let currentChatId = null;
        let messagesRef = null;
        let agoraClient = null;
        let localTracks = [];

        // Chat item selection
        $(document).on('click', '.chat-item', function() {
            $('.chat-item').removeClass('active');
            $(this).addClass('active');
            
            const chatTitle = $(this).data('title');
            currentChatId = $(this).data('chatid');
            $('#current-chat-title').text(chatTitle);
            
            // Enable UI elements
            $('#message-input, #file-input, #upload-btn, #send-btn, #start-call')
                .prop('disabled', false);
            $('#message-input').focus();
            
            // Set current room
            currentRoom = $(this).data('room');
            
            // Clear previous
            $('#messages-container').html('');
            $('#file-list').html('');
            $('#video-call-container').addClass('d-none');
            
            // Remove previous listeners
            if (messagesRef) messagesRef.off();
            if (agoraClient) endCall();
            
            // Setup Firebase
            messagesRef = firebase.database().ref('chats/' + currentRoom + '/messages');
            
            // Load messages
            messagesRef.orderByChild('timestamp').on('child_added', (snapshot) => {
                const msg = snapshot.val();
                displayMessage(msg);
                scrollToBottom();
            });
            
            // Load files
            loadFileList();
        });
        
        // Escape text before it is interpolated into an HTML template.
        // Chat content is user supplied and must never be treated as markup.
        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Display message
        function displayMessage(msg) {
            const isSelf = msg.sender_id == <?= $user_id ?>;
            const date = new Date(msg.timestamp);
            const timeString = date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            let content = escapeHtml(msg.text);
            if (msg.type === 'file') {
                content = `<div class="file-message">${msg.text}</div>`;
            }
            
            const msgHtml = `
                <div class="message ${isSelf ? 'self' : 'other'}">
                    <div class="fw-bold">${escapeHtml(msg.sender_name)}</div>
                    <div class="mb-1">${content}</div>
                    <div class="message-info"><span>${timeString}</span></div>
                </div>
            `;
            $('#messages-container').append(msgHtml);
            scrollToBottom();
        }
        
        // Send message
        $('#send-btn').click(sendMessage);
        $('#message-input').keypress(function(e) {
            if (e.which === 13) sendMessage();
        });
        
        function sendMessage() {
            const message = $('#message-input').val().trim();
            if (!message || !currentRoom) return;
            
            const messageData = {
                text: message,
                sender_id: <?= $user_id ?>,
                sender_name: <?= json_encode($user_name, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
                timestamp: firebase.database.ServerValue.TIMESTAMP,
                type: 'text'
            };
            
            messagesRef.push(messageData)
                .then(() => $('#message-input').val(''))
                .catch(console.error);
        }
        
        // File upload
        $('#upload-btn').click(function() {
            const fileInput = $('#file-input')[0];
            if (!fileInput.files.length || !currentChatId) return;
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('chat_id', currentChatId);
            formData.append('csrf_token', csrfToken);
            
            $.ajax({
                url: 'file_upload.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function() {
                    fileInput.value = '';
                    loadFileList();
                },
                error: function(xhr) {
                    console.error('Upload error:', xhr.responseText);
                    alert('File upload failed');
                }
            });
        });
        
        // Load files
        function loadFileList() {
            if (!currentChatId) return;
            
            $.get('get_files.php', { chat_id: currentChatId }, function(response) {
                if (response.status === 'success' && response.files.length) {
                    let html = '';
                    response.files.forEach(file => {
                        html += `
                            <div class="list-group-item">
                                <a href="${file.path}" download class="d-block">
                                    <i class="fas fa-file me-2"></i>${escapeHtml(file.name)}
                                </a>
                                <small class="text-muted">Uploaded by ${escapeHtml(file.uploader)} at ${escapeHtml(file.time)}</small>
                            </div>
                        `;
                    });
                    $('#file-list').html(html);
                } else {
                    $('#file-list').html('<div class="list-group-item text-muted">No files shared yet</div>');
                }
            });
        }
        
        // Refresh files
        $('#refresh-files').click(loadFileList);
        
        // Video call
        $('#start-call').click(initiateCall);
        $('#end-call').click(endCall);
        
        async function initiateCall() {
            if (!currentChatId) return;
            
            try {
                const response = await $.post('video_call.php', {
                    chat_id: currentChatId,
                    csrf_token: csrfToken
                });
                
                if (response.status === 'success') {
                    await startCall(response.channel);
                }
            } catch (error) {
                console.error('Call initiation failed:', error);
                alert('Failed to start call: ' + error.responseJSON?.message || error.statusText);
            }
        }
        
        async function startCall(channelName) {
            // Initialize Agora
            agoraClient = AgoraRTC.createClient({ 
                mode: 'rtc', 
                codec: 'vp8' 
            });
            
            // Join channel
            await agoraClient.join(
                agoraAppId, 
                channelName, 
                agoraToken, 
                <?= $user_id ?>
            );
            
            // Create local tracks
            localTracks = await AgoraRTC.createMicrophoneAndCameraTracks();
            
            // Publish tracks
            await agoraClient.publish(localTracks);
            
            // Play local video
            localTracks[1].play('local-video');
            
            // Show UI
            $('#video-call-container').removeClass('d-none');
            $('#start-call').prop('disabled', true);
            
            // Handle remote users
            agoraClient.on('user-published', async (user, mediaType) => {
                await agoraClient.subscribe(user, mediaType);
                
                if (mediaType === 'video') {
                    const remotePlayer = $(`<div id="user-${user.uid}" style="width:100%;height:100%;"></div>`);
                    $('#remote-video').append(remotePlayer);
                    user.videoTrack.play(`user-${user.uid}`);
                }
                
                if (mediaType === 'audio') {
                    user.audioTrack.play();
                }
            });
            
            agoraClient.on('user-unpublished', user => {
                $(`#user-${user.uid}`).remove();
            });
        }
        
        async function endCall() {
            if (agoraClient) {
                // Close tracks
                localTracks.forEach(track => track.stop());
                localTracks.forEach(track => track.close());
                
                // Leave channel
                await agoraClient.leave();
                
                // Reset UI
                $('#video-call-container').addClass('d-none');
                $('#start-call').prop('disabled', false);
                $('#remote-video').empty();
                
                // Reset variables
                agoraClient = null;
                localTracks = [];
            }
        }
        
        function scrollToBottom() {
            const container = $('#messages-container');
            container.scrollTop(container[0].scrollHeight);
        }
        }

        ensure(0).then(chatMain).catch(function (err) {
            console.error('REMOCO chat: a dependency failed to load.', err);
            var box = document.getElementById('messages-container');
            if (box) {
                box.textContent = 'Chat is unavailable because a required library could not be loaded.';
            }
        });
    })();
    </script>
</body>
</html>