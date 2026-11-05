<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Chatbot - Hosting AI</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">

    <style>
        .chat-page-heading {
            display:flex; justify-content:space-between; align-items:center;
            flex-wrap:wrap; gap:15px; margin-bottom:24px;
        }
        .chat-page-heading h1 { font-size:30px; font-weight:750; margin:0 0 7px; }
        .chat-page-heading p { color:var(--muted,#9ca3af); margin:0; }

        .chat-card {
            background:var(--card-bg,#1f2937);
            border:1px solid var(--card-border,#374151);
            border-radius:18px; overflow:hidden;
            box-shadow:0 5px 20px rgba(0,0,0,.07);
        }
        .chat-topbar {
            display:flex; justify-content:space-between; align-items:center;
            gap:15px; padding:20px 23px;
            border-bottom:1px solid var(--card-border,#374151);
        }
        .assistant-profile { display:flex; align-items:center; gap:13px; }
        .assistant-avatar {
            width:46px; height:46px; border-radius:14px;
            display:grid; place-items:center;
            color:white; font-size:22px;
            background:linear-gradient(135deg,#2563eb,#06b6d4);
        }
        .assistant-name { font-size:15px; font-weight:750; margin:0 0 4px; }
        .assistant-state { color:var(--muted,#9ca3af); font-size:12px; }
        .online-dot {
            display:inline-block; width:8px; height:8px;
            background:#22c55e; border-radius:50%; margin-right:5px;
        }
        .clear-button {
            border:1px solid var(--card-border,#475569);
            color:var(--text,#e5e7eb); background:transparent;
            border-radius:9px; padding:8px 12px; font-size:12px;
        }
        .clear-button:hover { background:rgba(148,163,184,.12); }

        .chat-box {
            height:55vh; min-height:390px; max-height:650px;
            overflow-y:auto; padding:25px;
            background:var(--chat-bg,#151f30);
            display:flex; flex-direction:column; gap:17px;
        }
        .welcome-message {
            max-width:620px; padding:20px;
            border:1px solid var(--card-border,#374151);
            background:var(--card-bg,#1f2937);
            border-radius:15px;
        }
        .welcome-message h3 { font-size:16px; font-weight:700; margin-bottom:8px; }
        .welcome-message p { color:var(--muted,#9ca3af); font-size:13px; margin:0; line-height:1.7; }
        .message-row { display:flex; align-items:flex-start; gap:10px; }
        .message-row.user-row { justify-content:flex-end; }
        .message-avatar {
            width:31px; height:31px; flex:0 0 31px;
            display:grid; place-items:center; border-radius:10px;
            background:rgba(37,99,235,.16); color:#60a5fa;
        }
        .user-row .message-avatar { order:2; background:#2563eb; color:white; }
        .message {
            max-width:min(78%,680px); padding:10px 14px;
            border-radius:14px; white-space:pre-wrap;
            overflow-wrap:anywhere; line-height:1.65;
            font-size:13px;
        }
        .bot-message {
            background:var(--card-bg,#1f2937);
            color:var(--text,#e5e7eb);
            border:1px solid var(--card-border,#374151);
            border-top-left-radius:4px;
        }
        .user-message {
            background:#2563eb; color:white;
            border-top-right-radius:4px;
        }
        .message-time {
            display:block; font-size:10px; line-height:1.2;
            margin-top:3px; padding:0; line-height:1.2; opacity:.65;
        }
        .typing-indicator {
            display:flex; gap:5px; align-items:center;
            padding:5px 0;
        }
        .typing-indicator span {
            width:6px; height:6px; background:#60a5fa;
            border-radius:50%; animation:typing 1s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(2) { animation-delay:.15s; }
        .typing-indicator span:nth-child(3) { animation-delay:.3s; }
        @keyframes typing {
            0%,60%,100% { transform:translateY(0); opacity:.4; }
            30% { transform:translateY(-5px); opacity:1; }
        }

        .chat-compose { padding:18px 22px 12px; border-top:1px solid var(--card-border,#374151); }
        .input-wrap {
            display:flex; align-items:center; gap:10px;
            padding:7px 8px 7px 16px;
            border:1px solid var(--card-border,#475569);
            border-radius:13px; background:var(--input-bg,#111827);
        }
        .input-wrap:focus-within { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
        .chat-input {
            flex:1; min-width:0; border:0; outline:0;
            background:transparent; color:var(--text,#e5e7eb);
            font-size:13px; padding:9px 0;
        }
        .chat-input::placeholder { color:var(--muted,#9ca3af); }
        .send-button {
            border:0; border-radius:10px; padding:10px 16px;
            background:#2563eb; color:white; font-weight:650;
            font-size:13px; white-space:nowrap;
        }
        .send-button:hover { background:#1d4ed8; }
        .send-button:disabled { opacity:.55; cursor:not-allowed; }
        .chat-footer {
            display:flex; justify-content:space-between;
            gap:10px; flex-wrap:wrap; padding-top:10px;
            color:var(--muted,#9ca3af); font-size:11px;
        }
        .chat-status { color:var(--muted,#9ca3af); }
        @media(max-width:700px) {
            .chat-page-heading h1 { font-size:24px; }
            .chat-topbar { padding:15px; }
            .chat-box { padding:15px; min-height:350px; }
            .chat-compose { padding:13px; }
            .message { max-width:88%; }
            .clear-button { padding:7px 9px; }
        }
    </style>
</head>

<body class="{{ auth()->user()->theme === 'dark' ? 'theme-dark' : '' }}">
<div class="d-flex">
    @include('partials.sidebar')

    <main class="content">
        <div class="chat-page-heading">
            <div>
                <div class="text-primary small fw-bold mb-2">
                    <i class="bi bi-stars me-1"></i> AI HOSTING ASSISTANT
                </div>
                <h1>AI Chatbot</h1>
                <p>Asisten AI untuk membantu pengelolaan dan analisis hosting.</p>
            </div>
        </div>

        <section class="chat-card">
            <div class="chat-topbar">
                <div class="assistant-profile">
                    <div class="assistant-avatar">
                        <i class="bi bi-robot"></i>
                    </div>
                    <div>
                        <h2 class="assistant-name">Hosting AI Assistant</h2>
                        <div class="assistant-state">
                            <span class="online-dot"></span>Siap menerima pesan
                        </div>
                    </div>
                </div>

                <button type="button" id="clearButton" class="clear-button">
                    <i class="bi bi-trash3 me-1"></i> Bersihkan Chat
                </button>
            </div>

            <div id="chatBox" class="chat-box" aria-live="polite">
                @forelse ($messages as $message)
                    <div class="message-row {{ $message->role === 'user' ? 'user-row' : 'bot-row' }}">
                        <div class="message-avatar">
                            @if ($message->role === 'user')
                                <i class="bi bi-person-fill"></i>
                            @else
                                <i class="bi bi-robot"></i>
                            @endif
                        </div>
                        <div class="message {{ $message->role === 'user' ? 'user-message' : 'bot-message' }}">
                            {{ $message->message }}
                            <span class="message-time">
                                {{ $message->created_at->timezone('Asia/Jakarta')->format('H:i') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="welcome-message">
                        <div class="text-primary small fw-bold mb-2">
                            <i class="bi bi-stars me-1"></i> SELAMAT DATANG
                        </div>
                        <h3>Halo, {{ auth()->user()->name }}!</h3>
                        <p>
                            Saya Hosting AI Assistant. Saya dapat membantu membaca
                            status website, menganalisis riwayat monitoring, dan
                            memberikan rekomendasi penanganan masalah hosting.
                            Silakan tuliskan pertanyaan Anda.
                        </p>
                    </div>
                @endforelse
            </div>

            <div class="chat-compose">
                <form id="chatForm">
                    <div class="input-wrap">
                        <input id="messageInput" type="text" class="chat-input"
                            placeholder="Tanyakan status atau masalah hosting..."
                            maxlength="2000" autocomplete="off" required>
                        <button id="sendButton" class="send-button" type="submit">
                            <i class="bi bi-send-fill me-1"></i> Kirim
                        </button>
                    </div>
                </form>

                <div class="chat-footer">
                    <span><i class="bi bi-shield-check me-1"></i> Terhubung dengan Hermes Agent</span>
                    <span id="chatStatus" class="chat-status">Chatbot menggunakan data monitoring Laravel.</span>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const chatBox = document.getElementById('chatBox');
const sendButton = document.getElementById('sendButton');
const chatStatus = document.getElementById('chatStatus');
const clearButton = document.getElementById('clearButton');

// Tampilkan pesan terbaru saat halaman dibuka atau di-refresh.
window.addEventListener('load', function() {
    requestAnimationFrame(function() {
        chatBox.scrollTop = chatBox.scrollHeight;
    });
});

function addMessage(text, type) {
    const row = document.createElement('div');
    row.className = 'message-row ' + (type === 'user' ? 'user-row' : 'bot-row');

    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = type === 'user'
        ? '<i class="bi bi-person-fill"></i>'
        : '<i class="bi bi-robot"></i>';

    const bubble = document.createElement('div');
    bubble.className = 'message ' + (type === 'user' ? 'user-message' : 'bot-message');
    bubble.textContent = text;

    const time = document.createElement('span');
    time.className = 'message-time';
    time.textContent = new Date().toLocaleTimeString('id-ID', {
        hour: '2-digit', minute: '2-digit'
    });

    bubble.appendChild(time);
    row.appendChild(avatar);
    row.appendChild(bubble);
    chatBox.appendChild(row);
    chatBox.scrollTop = chatBox.scrollHeight;

    return row;
}

function showTyping() {
    const row = document.createElement('div');
    row.className = 'message-row bot-row';
    row.id = 'typingIndicator';

    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = '<i class="bi bi-robot"></i>';

    const bubble = document.createElement('div');
    bubble.className = 'message bot-message';

    const dots = document.createElement('div');
    dots.className = 'typing-indicator';
    dots.innerHTML = '<span></span><span></span><span></span>';

    bubble.appendChild(dots);
    row.appendChild(avatar);
    row.appendChild(bubble);
    chatBox.appendChild(row);
    chatBox.scrollTop = chatBox.scrollHeight;
}

clearButton.addEventListener('click', async function() {
    if (!confirm('Yakin ingin menghapus seluruh riwayat percakapan?')) {
        return;
    }

    clearButton.disabled = true;
    chatStatus.textContent = 'Menghapus riwayat percakapan...';

    try {
        const response = await fetch('/chatbot/clear', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json'
            }
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Gagal menghapus riwayat.');
        }

        window.location.reload();
    } catch (error) {
        alert(error.message || 'Terjadi kesalahan saat menghapus riwayat.');
        chatStatus.textContent = 'Riwayat belum berhasil dihapus.';
        clearButton.disabled = false;
    }
});

form.addEventListener('submit', async function(event) {
    event.preventDefault();

    const message = input.value.trim();
    if (!message || sendButton.disabled) return;

    addMessage(message, 'user');
    input.value = '';
    sendButton.disabled = true;
    input.disabled = true;
    chatStatus.textContent = 'Hermes Agent sedang menganalisis data monitoring...';
    showTyping();

    try {
        const response = await fetch('/chatbot/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ message })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.reply || 'Terjadi kesalahan saat memproses pesan.');
        }

        addMessage(data.reply, 'bot');
        chatStatus.textContent = 'Pesan berhasil diproses oleh Hermes Agent.';
    } catch (error) {
        addMessage(error.message || 'Terjadi kesalahan koneksi.', 'bot');
        chatStatus.textContent = 'Pesan gagal diproses.';
    } finally {
        document.getElementById('typingIndicator')?.remove();
        sendButton.disabled = false;
        input.disabled = false;
        input.focus();
    }
});
</script>
</body>
</html>
