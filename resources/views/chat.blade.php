<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Chat · NuruXplore</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="{{ asset('css/nuru-app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/nuru-dashboard.css') }}">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    
    <style>
        :root {
            --chat-bg: #fafaf7;
            --chat-surface: #ffffff;
            --chat-border: #e8e6e0;
            --chat-text: #0a0a0a;
            --chat-text-secondary: #6b6b6b;
            --chat-hover: #f5f1ea;
            --chat-user-bubble: #0a0a0a;
            --chat-user-text: #ffffff;
            --chat-ai-bubble: #f5f1ea;
            --chat-ai-text: #1a1a1a;
        }
        
        [data-theme="dark"] {
            --chat-bg: #0a0a0a;
            --chat-surface: #111111;
            --chat-border: #222222;
            --chat-text: #f5f5f5;
            --chat-text-secondary: #888888;
            --chat-hover: #1a1a1a;
            --chat-user-bubble: #ffffff;
            --chat-user-text: #0a0a0a;
            --chat-ai-bubble: #1a1a1a;
            --chat-ai-text: #e5e5e5;
        }
        
        * { box-sizing: border-box; }
        
        body.chat-body {
            margin: 0; background: var(--chat-bg);
            font-family: 'Inter', sans-serif; color: var(--chat-text);
            overflow: hidden; transition: background 0.3s ease;
        }
        
        .chat-shell {
            display: grid;
            grid-template-columns: 280px 1fr;
            height: 100vh;
            overflow: hidden;
        }
        
        /* SIDEBAR */
        .chat-sidebar {
            background: var(--chat-surface);
            border-right: 1px solid var(--chat-border);
            display: flex; flex-direction: column;
        }
        .chat-sidebar-header {
            padding: 16px; border-bottom: 1px solid var(--chat-border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .chat-sidebar-header a {
            color: var(--chat-text); text-decoration: none; font-weight: 700; font-size: 16px;
        }
        .chat-sidebar-list {
            flex: 1; 
            overflow-y: auto; 
            padding: 8px;
        }
        .chat-sidebar-item {
            padding: 10px 12px; border-radius: 8px; cursor: pointer;
            font-size: 13px; color: var(--chat-text-secondary);
            margin-bottom: 2px; display: flex; justify-content: space-between; align-items: center;
            transition: all 0.15s;
        }
        .chat-sidebar-item:hover { background: var(--chat-hover); color: var(--chat-text); }
        .chat-sidebar-item.active { background: var(--chat-hover); color: var(--chat-text); font-weight: 500; }
        .chat-sidebar-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
        .chat-sidebar-item button { 
            background: none; border: none; color: var(--chat-text-secondary);
            cursor: pointer; font-size: 12px; opacity: 0; transition: opacity 0.15s;
        }
        .chat-sidebar-item:hover button { opacity: 1; }
        .chat-sidebar-item button:hover { color: #ef4444; }
        
        .chat-sidebar-footer {
            padding: 12px 16px; border-top: 1px solid var(--chat-border);
            font-size: 11px; color: var(--chat-text-secondary);
            display: flex; justify-content: space-between; align-items: center;
        }
        
        /* MAIN CHAT */
        .chat-main {
            display: flex; 
            flex-direction: column;
            background: var(--chat-bg);
            position: relative;
            overflow: hidden;
        }
        
        /* Top bar */
        .chat-topbar {
            padding: 12px 20px; border-bottom: 1px solid var(--chat-border);
            display: flex; align-items: center; justify-content: space-between;
            background: var(--chat-surface);
        }
        .chat-topbar-left { display: flex; align-items: center; gap: 12px; }
        .chat-topbar-right { display: flex; align-items: center; gap: 8px; }
        
        /* Messages area */
        .chat-messages {
            flex: 1;
            overflow-y: auto; 
            padding: 20px;
            display: flex; 
            flex-direction: column;
        }
        .chat-messages-centered {
            justify-content: center; align-items: center;
        }
        .chat-messages-bottom {
            justify-content: flex-start;
        }
        
        /* Empty state (centered) */
        .chat-empty {
            text-align: center; max-width: 600px;
        }
        .chat-empty h2 { font-size: 28px; margin: 0 0 8px; color: var(--chat-text); }
        .chat-empty p { color: var(--chat-text-secondary); margin: 0 0 24px; line-height: 1.5; }
        .chat-empty-suggestions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .chat-suggestion {
            padding: 10px 16px; border-radius: 999px; border: 1px solid var(--chat-border);
            background: var(--chat-surface); cursor: pointer; font-size: 13px;
            color: var(--chat-text-secondary); font-family: inherit; transition: all 0.15s;
        }
        .chat-suggestion:hover { border-color: #7c5cff; color: #7c5cff; }
        
        /* Message bubbles */
        .msg-wrapper {
            display: flex; gap: 12px; margin-bottom: 24px;
            animation: fadeIn 0.3s ease; max-width: 800px; width: 100%;
        }
        .msg-wrapper.user { flex-direction: row-reverse; align-self: flex-end; }
        .msg-wrapper.ai { align-self: flex-start; }
        
        .msg-avatar {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
        }
        .msg-avatar.user { background: #7c5cff; color: #fff; }
        .msg-avatar.ai { background: linear-gradient(135deg, #7c5cff, #3aa0ff); color: #fff; }
        
        .msg-bubble {
            padding: 12px 16px; border-radius: 14px;
            font-size: 14px; line-height: 1.6;
            max-width: 100%; word-wrap: break-word;
        }
        .msg-bubble.user {
            background: var(--chat-user-bubble); color: var(--chat-user-text);
            border-radius: 14px 14px 4px 14px;
        }
        .msg-bubble.ai {
            background: var(--chat-ai-bubble); color: var(--chat-ai-text);
            border-radius: 14px 14px 14px 4px;
            border: 1px solid var(--chat-border);
        }
        .msg-bubble p { margin: 0 0 8px; }
        .msg-bubble p:last-child { margin: 0; }
        .msg-bubble pre { background: rgba(0,0,0,0.1); padding: 12px; border-radius: 8px; overflow-x: auto; }
        .msg-bubble code { background: rgba(0,0,0,0.08); padding: 2px 5px; border-radius: 4px; font-size: 13px; }
        
        /* Message actions */
        .msg-actions {
            display: flex; gap: 4px; margin-top: 4px; opacity: 0; transition: opacity 0.15s;
        }
        .msg-wrapper:hover .msg-actions { opacity: 1; }
        .msg-action-btn {
            background: none; border: none; cursor: pointer; font-size: 14px;
            padding: 2px 6px; border-radius: 4px; color: var(--chat-text-secondary);
        }
        .msg-action-btn:hover { background: var(--chat-hover); }
        
        /* Input area */
        .chat-input-area {
            padding: 16px 20px; background: var(--chat-bg);
        }
        .chat-input-wrapper {
            max-width: 800px; margin: 0 auto;
            display: flex; gap: 8px; align-items: flex-end;
            background: var(--chat-surface); border: 1px solid var(--chat-border);
            border-radius: 16px; padding: 8px 12px;
            transition: border-color 0.2s;
        }
        .chat-input-wrapper:focus-within { border-color: #7c5cff; }
        
        .chat-input-wrapper textarea {
            flex: 1; border: none; background: transparent;
            color: var(--chat-text); font-family: inherit; font-size: 14px;
            resize: none; min-height: 24px; max-height: 200px;
            outline: none; padding: 4px 0;
        }
        .chat-input-wrapper textarea::placeholder { color: var(--chat-text-secondary); }
        
        .btn-send {
            width: 36px; height: 36px; border-radius: 50%; border: none;
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            color: #fff; cursor: pointer; font-size: 16px;
            transition: all 0.2s; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-send:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(124,92,255,0.4); }
        .btn-send:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }
        
        /* Buttons */
        .btn-icon {
            width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--chat-border);
            background: var(--chat-surface); cursor: pointer; font-size: 16px;
            color: var(--chat-text); display: flex; align-items: center; justify-content: center;
            transition: all 0.15s;
        }
        .btn-icon:hover { background: var(--chat-hover); }
        .btn-new-chat {
            padding: 6px 12px; border-radius: 8px; border: 1px solid var(--chat-border);
            background: var(--chat-surface); cursor: pointer; font-size: 12px;
            color: var(--chat-text); font-family: inherit;
        }
        .btn-new-chat:hover { background: var(--chat-hover); }
        
        .mobile-menu-btn { display: none; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
        
        /* Mobile */
        @media (max-width: 768px) {
            .chat-shell { grid-template-columns: 1fr; }
            .chat-sidebar { display: none; position: fixed; inset: 0; z-index: 100; }
            .chat-sidebar.open { display: flex; }
            .mobile-menu-btn { display: block; }
            .msg-wrapper { max-width: 100%; }
        }
        
        /* Copy toast */
        .toast {
            position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
            background: #1a1a1a; color: #fff; padding: 8px 16px;
            border-radius: 8px; font-size: 13px; z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body class="chat-body" x-data="chatApp()" x-init="init()" :class="{ 'dark-mode': darkMode }">

    <!-- Toast -->
    <div x-show="toast" class="toast" x-text="toast" @click="toast = ''"></div>
    
    <div class="chat-shell">
        
        <!-- SIDEBAR -->
        <aside class="chat-sidebar" :class="{ open: sidebarOpen }">
            <div class="chat-sidebar-header">
                <a href="/dashboard">✦ NuruXplore</a>
                <button class="btn-new-chat" @click="newChat()">+ New Chat</button>
            </div>
            <div class="chat-sidebar-list">
                <template x-for="conv in conversations" :key="conv.uuid">
                    <div class="chat-sidebar-item" :class="{ active: currentUUID === conv.uuid }" @click="loadConversation(conv.uuid)">
                        <span x-text="conv.title || 'New Chat'"></span>
                        <button @click.stop="deleteConversation(conv.uuid)" title="Delete">🗑</button>
                    </div>
                </template>
                <div x-show="conversations.length === 0" style="color:var(--chat-text-secondary);font-size:12px;text-align:center;padding:20px;">
                    No conversations yet
                </div>
            </div>
            <div class="chat-sidebar-footer">
                <span>⚡ <span x-text="credits"></span> credits</span>
                <button class="btn-icon" @click="toggleDarkMode()" :title="darkMode ? 'Light Mode' : 'Dark Mode'" style="width:28px;height:28px;font-size:12px;">
                    <span x-text="darkMode ? '☀️' : '🌙'"></span>
                </button>
            </div>
        </aside>
        
        <!-- MAIN CHAT -->
        <main class="chat-main">
            
            <!-- Top bar -->
            <div class="chat-topbar">
                <div class="chat-topbar-left">
                    <button class="mobile-menu-btn btn-icon" @click="sidebarOpen = !sidebarOpen" style="width:30px;height:30px;font-size:14px;">☰</button>
                    <span style="font-weight:600;font-size:14px;color:var(--chat-text);" x-text="currentTitle || 'New Chat'"></span>
                </div>
                <div class="chat-topbar-right">
                    <button class="btn-icon" @click="toggleDarkMode()" :title="darkMode ? 'Light Mode' : 'Dark Mode'" style="width:30px;height:30px;font-size:12px;">
                        <span x-text="darkMode ? '☀️' : '🌙'"></span>
                    </button>
                    <a href="/dashboard" style="color:var(--chat-text-secondary);text-decoration:none;font-size:12px;">← Dashboard</a>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="chat-messages" :class="messages.length === 0 ? 'chat-messages-centered' : 'chat-messages-bottom'" x-ref="messagesContainer">
                
                <!-- Empty state -->
                <template x-if="messages.length === 0">
                    <div class="chat-empty">
                        <div style="font-size:56px;margin-bottom:16px;">💬</div>
                        <h2>General Academic Chat</h2>
                        <p>Ask anything about academics, research, writing or any topic you're curious about.</p>
                        <div class="chat-empty-suggestions">
                            <button class="chat-suggestion" @click="inputMessage='Explain the difference between qualitative and quantitative research'; sendMessage()">
                                📊 Research methods
                            </button>
                            <button class="chat-suggestion" @click="inputMessage='How do I write a good literature review?'; sendMessage()">
                                ✍️ Writing tips
                            </button>
                            <button class="chat-suggestion" @click="inputMessage='What are the key theories of motivation in psychology?'; sendMessage()">
                                🧠 Psychology theories
                            </button>
                            <button class="chat-suggestion" @click="inputMessage='Explain machine learning in simple terms'; sendMessage()">
                                🤖 Machine learning
                            </button>
                        </div>
                    </div>
                </template>
                
                <!-- Messages -->
                <template x-for="(msg, index) in messages" :key="msg.id || index">
                    <div class="msg-wrapper" :class="msg.role">
                        <div class="msg-avatar" :class="msg.role" x-text="msg.role === 'user' ? 'U' : 'AI'"></div>
                        <div style="flex:1;min-width:0;">
                            <div class="msg-bubble" :class="msg.role" x-html="renderMarkdown(msg.content)"></div>
                            <div class="msg-actions">
                                <button class="msg-action-btn" @click="copyMessage(msg.content)" title="Copy">📋</button>
                            </div>
                        </div>
                    </div>
                </template>
                
                <!-- Loading -->
                <div x-show="isLoading" class="msg-wrapper ai">
                    <div class="msg-avatar ai">AI</div>
                    <div>
                        <div class="msg-bubble ai" style="padding:12px 20px;">
                            <div style="display:flex;gap:5px;">
                                <span style="width:7px;height:7px;background:var(--chat-text-secondary);border-radius:50%;animation:bounce 1s infinite;"></span>
                                <span style="width:7px;height:7px;background:var(--chat-text-secondary);border-radius:50%;animation:bounce 1s infinite 0.2s;"></span>
                                <span style="width:7px;height:7px;background:var(--chat-text-secondary);border-radius:50%;animation:bounce 1s infinite 0.4s;"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Input -->
            <div class="chat-input-area">
                <div class="chat-input-wrapper">
                    <textarea x-model="inputMessage" 
                              @keydown.enter.prevent="!$event.shiftKey && sendMessage()"
                              placeholder="Ask anything about academics..."
                              rows="1"
                              :disabled="isLoading"
                              x-ref="inputTextarea"
                              @input="autoResize()"></textarea>
                    <button class="btn-send" @click="sendMessage()" :disabled="!inputMessage.trim() || isLoading">↑</button>
                </div>
            </div>
        </main>
        
    </div>

    <script src="{{ asset('js/nuru-api.js') }}"></script>
    <script>
        function chatApp() {
            return {
                currentUUID: null,
                currentTitle: 'New Chat',
                conversations: [],
                messages: [],
                inputMessage: '',
                isLoading: false,
                credits: 0,
                sidebarOpen: false,
                darkMode: localStorage.getItem('nuruxplore_theme') === 'dark',
                toast: '',
                
                async init() {
                    if (this.darkMode) document.documentElement.setAttribute('data-theme', 'dark');
                    
                    const token = localStorage.getItem('nuruxplore_token');
                    if (!token) { window.location.href = '/login'; return; }
                    window.NuruAPI.setToken(token);
                    
                    await Promise.all([this.loadConversations(), this.loadCredits()]);
                    
                    const pathParts = window.location.pathname.split('/');
                    const urlUUID = pathParts.length > 2 && pathParts[1] === 'chat' ? pathParts[2] : null;
                    
                    if (urlUUID && urlUUID.length > 10) {
                        await this.loadConversation(urlUUID);
                    }
                },
                
                async loadConversations() {
                    try {
                        const data = await window.NuruAPI.getProjects();
                        this.conversations = (data || []).filter(p => p.type === 'chat');
                    } catch(e) { console.error('Load conversations:', e); }
                },
                
                async loadCredits() {
                    try { const d = await window.NuruAPI.getCredits(); this.credits = d.balance || 0; } catch(e) {}
                },
                
                async loadConversation(uuid) {
                    this.currentUUID = uuid;
                    this.sidebarOpen = false;
                    
                    try {
                        const [projectRes, msgRes] = await Promise.all([
                            window.NuruAPI.getProject(uuid),
                            window.NuruAPI.getMessages(uuid),
                        ]);
                        
                        this.currentTitle = projectRes.project?.title || 'Chat';
                        this.messages = (msgRes.messages || []).map(m => ({
                            id: m.id, role: m.role, content: m.content,
                        }));
                        
                        window.history.pushState({}, '', '/chat/' + uuid);
                    } catch(e) {
                        this.currentUUID = null; this.messages = []; this.currentTitle = 'New Chat';
                    }
                    
                    this.scrollToBottom();
                },
                
                async newChat() {
                    this.currentUUID = null;
                    this.currentTitle = 'New Chat';
                    this.messages = [];
                    window.history.pushState({}, '', '/chat');
                    this.$nextTick(() => { if(this.$refs.inputTextarea) this.$refs.inputTextarea.focus(); });
                },
                
                async deleteConversation(uuid) {
                    if (!confirm('Delete this conversation?')) return;
                    try {
                        await window.NuruAPI.deleteProject(uuid);
                        await this.loadConversations();
                        if (this.currentUUID === uuid) this.newChat();
                    } catch(e) {}
                },
                
                async sendMessage() {
                    if (!this.inputMessage.trim() || this.isLoading) return;
                    
                    const msg = this.inputMessage.trim();
                    this.inputMessage = '';
                    this.isLoading = true;
                    
                    try {
                        // Create conversation if needed
                        if (!this.currentUUID) {
                            const data = await window.NuruAPI.createProject({
                                title: msg.substring(0, 50),
                                type: 'chat',
                                citation_style: 'APA7',
                            });
                            
                            if (!data.uuid) {
                                this.messages.push({ role: 'assistant', content: 'Error: Could not create conversation.' });
                                this.isLoading = false;
                                return;
                            }
                            
                            this.currentUUID = data.uuid;
                            this.currentTitle = msg.substring(0, 50);
                            await this.loadConversations();
                            window.history.pushState({}, '', '/chat/' + data.uuid);
                        }
                        
                        this.messages.push({ role: 'user', content: msg });
                        this.scrollToBottom();
                        
                        const data = await window.NuruAPI.sendMessage(this.currentUUID, msg, 'chat');
                        
                        this.messages.push({
                            role: 'assistant',
                            content: data.message || data.ai_message?.content || 'Done!',
                        });
                        
                        this.credits = data.credits_remaining;
                        
                        if (this.currentTitle === 'New Chat' || this.currentTitle === msg.substring(0, 50)) {
                            this.currentTitle = msg.substring(0, 50);
                            await this.loadConversations();
                        }
                    } catch(e) {
                        this.messages.push({ role: 'assistant', content: 'Error: ' + e.message });
                    }
                    
                    this.isLoading = false;
                    this.scrollToBottom();
                    this.$nextTick(() => { if(this.$refs.inputTextarea) this.$refs.inputTextarea.focus(); });
                },
                
                copyMessage(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.toast = 'Copied!';
                        setTimeout(() => this.toast = '', 1500);
                    });
                },
                
                autoResize() {
                    this.$nextTick(() => {
                        const ta = this.$refs.inputTextarea;
                        if (ta) { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 200) + 'px'; }
                    });
                },
                
                renderMarkdown(text) {
                    if (!text) return '';
                    return typeof marked !== 'undefined' ? marked.parse(text) : text.replace(/\n/g, '<br>');
                },
                
                scrollToBottom() {
                    this.$nextTick(() => {
                        const container = this.$refs.messagesContainer;
                        if (container) container.scrollTop = container.scrollHeight;
                    });
                },
                
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('nuruxplore_theme', this.darkMode ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-theme', this.darkMode ? 'dark' : 'light');
                },
            };
        }
    </script>
</body>
</html>