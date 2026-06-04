<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NuruXplore · Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/nuru-workspace.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        .chat-message { display: flex; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--ws-border); animation: fadeIn 0.3s ease; }
        .chat-message.user { background: var(--ws-surface); }
        .chat-message.ai { background: var(--ws-hover); }
        .chat-avatar { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; font-weight: 700; }
        .chat-avatar.user { background: #7c5cff; color: #fff; }
        .chat-avatar.ai { background: linear-gradient(135deg, #7c5cff, #3aa0ff); color: #fff; }
        .chat-content { flex: 1; min-width: 0; }
        .chat-content .role-label { font-size: 10px; font-weight: 600; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .chat-content .role-label.user { color: #7c5cff; }
        .chat-content .role-label.ai { color: #3aa0ff; }
        .chat-content .text { font-size: 12px; line-height: 1.5; word-wrap: break-word; }
        .chat-content .action-msg { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 8px 12px; border-radius: 6px; font-size: 12px; color: #166534; }
        [data-theme="dark"] .chat-content .action-msg { background: #064e3b; border-color: #065f46; color: #86efac; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
        
        @media (max-width: 768px) {
            .ws-chat.mobile-open {
                display: flex !important;
                position: fixed; top: 48px; left: 0; right: 0; bottom: 0;
                z-index: 80; border-right: none;
                box-shadow: 0 0 40px rgba(0,0,0,0.2);
            }
        }
    </style>
</head>
<body class="nuru-workspace-body" x-data="workspaceApp()" x-init="init()" :class="{ 'dark': darkMode }">

    <!-- TOP BAR -->
    <header class="ws-topbar">
        <div class="ws-breadcrumb">
            <a href="/dashboard">✦ NuruXplore</a>
            <span style="color:#ccc;">/</span>
            <span x-text="projectTitle || 'Loading...'" style="font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis;"></span>
        </div>
        <div class="ws-actions">
            <span class="ws-pill hide-mobile" x-text="wordCount + ' words'">0</span>
            <span class="ws-pill" x-text="'⚡ ' + credits">⚡ 0</span>
            <button class="ws-btn" @click="toggleDarkMode()" x-text="darkMode ? '☀️' : '🌙'"></button>
            <button class="ws-btn" @click="exportPDF()">📄</button>
            <a href="/dashboard" class="ws-btn ghost">←</a>
        </div>
    </header>

    <!-- MAIN LAYOUT -->
    <div class="ws-layout" style="grid-template-columns:340px 1fr;">

        <!-- CHAT PANEL -->
        <aside class="ws-chat" :class="{ 'mobile-open': chatOpen }" style="border-right:1px solid var(--ws-border);">
            <div class="ws-chat-header">
                <span>💬 NuruXplore AI</span>
                <button class="ws-btn ghost" @click="chatOpen = false" style="font-size:14px;display:none;" :style="isMobile ? 'display:block' : 'display:none'">✕</button>
            </div>
            <div class="ws-chat-body" x-ref="chatBody">
                <template x-if="messages.length === 0 && !isLoading">
                    <div style="text-align:center;padding:40px 16px;color:var(--ws-text-secondary);">
                        <div style="font-size:32px;margin-bottom:8px;">📝</div>
                        <p style="font-weight:600;font-size:13px;">Start your <span x-text="documentLabel"></span></p>
                        <p style="font-size:11px;">Ask AI to modify your document or answer research questions.</p>
                    </div>
                </template>
                <template x-for="msg in messages" :key="msg.id || Math.random()">
                    <div class="chat-message" :class="msg.role">
                        <div class="chat-avatar" :class="msg.role" x-text="msg.role === 'user' ? 'U' : 'AI'"></div>
                        <div class="chat-content">
                            <div class="role-label" :class="msg.role" x-text="msg.role === 'user' ? 'You' : 'NuruXplore AI'"></div>
                            <div x-show="msg.isAction" class="action-msg" x-text="msg.content"></div>
                            <div x-show="!msg.isAction" class="text" x-html="renderChatMarkdown(msg.content)"></div>
                        </div>
                    </div>
                </template>
                <div x-show="isLoading" class="chat-message ai">
                    <div class="chat-avatar ai">AI</div>
                    <div class="chat-content">
                        <div class="role-label ai">NuruXplore AI</div>
                        <div style="display:flex;gap:5px;padding:6px 0;">
                            <span style="width:7px;height:7px;background:#ccc;border-radius:50%;animation:bounce 1s infinite;"></span>
                            <span style="width:7px;height:7px;background:#ccc;border-radius:50%;animation:bounce 1s infinite 0.2s;"></span>
                            <span style="width:7px;height:7px;background:#ccc;border-radius:50%;animation:bounce 1s infinite 0.4s;"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ws-chat-input-area">
                <div class="ws-chat-input-box">
                    <textarea x-model="inputMessage" @keydown.enter.prevent="!$event.shiftKey && sendMessage()" placeholder="Modify your document or ask a question..." rows="2"></textarea>
                    <div class="ws-chat-input-row">
                        <button class="ws-send-btn" @click="sendMessage()" :disabled="!inputMessage.trim() || isLoading">↑</button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- PREVIEW PANEL -->
        <section class="ws-preview">
            <div class="ws-preview-toolbar" style="display:flex;align-items:center;justify-content:space-between;padding:0 14px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:12px;font-weight:600;color:var(--ws-text);">
                        📄 <span x-text="documentLabel">Document</span>
                    </span>
                    <span style="font-size:10px;color:var(--ws-text-secondary);background:var(--ws-hover);padding:2px 8px;border-radius:999px;" x-text="wordCount + ' words'"></span>
                </div>
                <button class="ws-btn" @click="exportPDF()" style="display:flex;align-items:center;gap:4px;font-weight:500;">
                    <span>📥</span> Export PDF
                </button>
            </div>
            <div class="ws-preview-body">
                <div x-show="isPreviewLoading" style="position:absolute;inset:0;background:var(--ws-surface);z-index:10;display:flex;align-items:center;justify-content:center;border-radius:8px;">
                    <div style="text-align:center;">
                        <div style="display:flex;gap:5px;justify-content:center;margin-bottom:8px;">
                            <span style="width:7px;height:7px;background:#ccc;border-radius:50%;animation:bounce 1s infinite;"></span>
                            <span style="width:7px;height:7px;background:#ccc;border-radius:50%;animation:bounce 1s infinite 0.2s;"></span>
                            <span style="width:7px;height:7px;background:#ccc;border-radius:50%;animation:bounce 1s infinite 0.4s;"></span>
                        </div>
                        <p style="color:var(--ws-text-secondary);font-size:12px;">Updating document...</p>
                    </div>
                </div>
                <div class="ws-preview-frame">
                    <div x-show="isPageLoading" style="text-align:center;padding:60px;color:var(--ws-text-secondary);">Loading...</div>
                    <div x-show="!isPageLoading" class="ws-doc" x-html="renderMarkdown('# ' + projectTitle + '\n\n' + (projectContent || '*No content yet. Start a conversation to generate your document.*'))"></div>
                </div>
            </div>
        </section>
    </div>

    <!-- MOBILE CHAT TOGGLE -->
    <button class="ws-mobile-toggle" @click="chatOpen = !chatOpen">
        <span x-text="chatOpen ? '✕' : '💬'"></span>
    </button>

    <script src="{{ asset('js/nuru-api.js') }}"></script>
    <script>
        function workspaceApp() {
            return {
                projectUUID: '',
                projectTitle: '',
                projectType: 'thesis',
                citationStyle: 'APA 7',
                wordCount: 0,
                credits: 0,
                projectContent: '',
                messages: [],
                inputMessage: '',
                isLoading: false,
                isPageLoading: true,
                isPreviewLoading: false,
                chatOpen: false,
                isMobile: window.innerWidth <= 768,
                darkMode: localStorage.getItem('nuruxplore_theme') === 'dark',

                get documentLabel() {
                    if (this.projectType === 'proposal') return 'Research Proposal';
                    if (this.projectType === 'chat') return 'Chat';
                    return 'Thesis Document';
                },

                async init() {
                    this.projectUUID = window.location.pathname.split('/').pop();
                    if (!this.projectUUID || this.projectUUID === 'workspace') { window.location.href = '/dashboard'; return; }
                    if (this.darkMode) document.documentElement.setAttribute('data-theme', 'dark');
                    const token = localStorage.getItem('nuruxplore_token');
                    if (!token) { window.location.href = '/login'; return; }
                    window.NuruAPI.setToken(token);
                    this.checkMobile();
                    window.addEventListener('resize', () => this.checkMobile());
                    await this.loadAll();
                    this.isPageLoading = false;
                },

                checkMobile() { this.isMobile = window.innerWidth <= 768; },

                async loadAll() {
                    try {
                        const [pr, msgRes, cr] = await Promise.all([
                            window.NuruAPI.getProject(this.projectUUID),
                            window.NuruAPI.getMessages(this.projectUUID),
                            window.NuruAPI.getCredits(),
                        ]);
                        this.projectTitle = pr.project?.title || '';
                        this.projectType = pr.project?.type || 'thesis';
                        this.citationStyle = pr.project?.citation_style || 'APA 7';
                        this.wordCount = pr.project?.word_count || 0;
                        this.projectContent = pr.project?.content || '';
                        this.credits = cr.balance || 0;
                        document.title = this.projectTitle + ' · NuruXplore';
                        
                        const serverMessages = msgRes.messages || [];
                        if (serverMessages.length > 0) {
                            this.messages = serverMessages.map(msg => ({
                                id: msg.id,
                                role: msg.role,
                                content: msg.content,
                                isAction: msg.role === 'assistant' && (msg.content.includes('✅') || msg.content.includes('📄')),
                                isDocument: false,
                            }));
                        } else if (this.projectContent) {
                            this.messages = [{
                                id: 'init',
                                role: 'ai',
                                content: '📄 ' + this.documentLabel + ' ready (' + this.wordCount + ' words)',
                                isAction: true
                            }];
                        }
                    } catch (e) { console.error('Load error:', e); }
                },

                async sendMessage() {
                    if (!this.inputMessage.trim() || this.isLoading) return;
                    const msg = this.inputMessage; this.inputMessage = ''; this.isLoading = true;
                    
                    this.messages.push({ id: Date.now(), role: 'user', content: msg });
                    this.scrollChat();

                    try {
                        this.isPreviewLoading = true;
                        const data = await window.NuruAPI.sendMessage(this.projectUUID, msg, 'chat');
                        
                        if (data.action === 'edit') {
                            this.messages.push({ 
                                id: Date.now(), 
                                role: 'ai', 
                                content: '✅ ' + (data.message || 'Document updated.'), 
                                isAction: true 
                            });
                            await this.reloadProject();
                            this.isPreviewLoading = false;
                        } else {
                            this.messages.push({ 
                                id: Date.now(), 
                                role: 'ai', 
                                content: data.message || 'Done!' 
                            });
                            this.isPreviewLoading = false;
                        }
                        
                        this.credits = data.credits_remaining;
                    } catch (e) {
                        this.messages.push({ id: Date.now(), role: 'ai', content: '❌ Error: ' + e.message });
                        this.isPreviewLoading = false;
                    }
                    
                    this.isLoading = false;
                    this.scrollChat();
                },

                async reloadProject() {
                    try {
                        const pr = await window.NuruAPI.getProject(this.projectUUID);
                        if (pr.project) {
                            this.projectTitle = pr.project.title || '';
                            this.projectType = pr.project.type || 'thesis';
                            this.wordCount = pr.project.word_count || 0;
                            this.projectContent = pr.project.content || '';
                            document.title = this.projectTitle + ' · NuruXplore';
                        }
                    } catch (e) { console.error('Reload error:', e); }
                },

                renderChatMarkdown(c) {
                    if (!c) return '';
                    let html = c
                        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.+?)\*/g, '<em>$1</em>')
                        .replace(/`(.+?)`/g, '<code>$1</code>')
                        .replace(/\n/g, '<br>');
                    return html;
                },

                async exportPDF() {
                    try { 
                        const d = await window.NuruAPI.exportPDF(this.projectUUID); 
                        if (d.download_url) window.open(d.download_url, '_blank'); 
                    } catch (e) {}
                },

                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('nuruxplore_theme', this.darkMode ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-theme', this.darkMode ? 'dark' : 'light');
                },

                renderMarkdown(c) { 
                    if (!c) return ''; 
                    return typeof marked !== 'undefined' ? marked.parse(c) : c; 
                },
                
                scrollChat() { 
                    this.$nextTick(() => { 
                        const el = this.$refs.chatBody; 
                        if (el) el.scrollTop = el.scrollHeight; 
                    }); 
                },
            };
        }
    </script>
</body>
</html>