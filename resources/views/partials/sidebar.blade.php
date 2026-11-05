<aside class="sidebar p-3">
    <div class="sidebar-brand">
        <a href="/" class="brand-link">
            <img src="{{ asset('images/hosting-ai-logo.png') }}"
                 alt="Hosting AI" class="sidebar-logo">
            <div class="brand-caption">
                <strong>HOSTING AI</strong>
                <small>Monitoring Platform</small>
            </div>
        </a>
    </div>

    <div class="sidebar-section-label">MENU UTAMA</div>

    <nav class="sidebar-nav">
        <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="/monitoring" class="{{ request()->is('monitoring*') ? 'active' : '' }}">
            <i class="bi bi-activity"></i>
            <span>Monitoring Hosting</span>
        </a>

        <a href="{{ route('websites.index') }}" class="{{ request()->is('websites*') ? 'active' : '' }}">
                <i class="bi bi-globe2"></i>
                <span>Manajemen Website</span>
            </a>
            <a href="/notifications" class="{{ request()->is('notifications*') ? 'active' : '' }}">
            <i class="bi bi-bell"></i>
            <span>Notifikasi</span>
        </a>
        <a href="/chatbot" class="{{ request()->is('chatbot*') ? 'active' : '' }}">
            <i class="bi bi-robot"></i>
            <span>AI Chatbot</span>
        </a>

        <a href="/riwayat" class="{{ request()->is('riwayat*') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i>
            <span>Riwayat Monitoring</span>
        </a>
    </nav>

    <div class="sidebar-section-label">PREFERENSI</div>

    <nav class="sidebar-nav">
        <a href="/profile" class="{{ request()->is('profile*') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i>
            <span>Profil</span>
        </a>

        <a href="/settings" class="{{ request()->is('settings*') ? 'active' : '' }}">
            <i class="bi bi-gear"></i>
            <span>Pengaturan</span>
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="sidebar-user-info">
                <strong>{{ auth()->user()->name }}</strong>
                <small>Administrator</small>
            </div>
        </div>

        <form method="POST" action="/logout">
            @csrf
            <button class="sidebar-logout" type="submit">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
