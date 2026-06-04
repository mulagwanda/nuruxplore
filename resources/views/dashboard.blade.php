<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard · NuruXplore</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="{{ asset('css/nuru-app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/nuru-dashboard.css') }}">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .account-popup {
            position: absolute; bottom: 100%; left: 0; right: 0;
            background: var(--dash-surface); border: 1px solid var(--dash-border);
            border-radius: 12px; box-shadow: var(--dash-shadow-lg);
            padding: 8px; margin-bottom: 8px; z-index: 300;
        }
        .account-popup a, .account-popup button {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 12px; border-radius: 8px; font-size: 13px;
            color: var(--dash-text); text-decoration: none; width: 100%;
            border: none; background: none; cursor: pointer; font-family: inherit;
        }
        .account-popup a:hover, .account-popup button:hover { background: var(--dash-hover); }
        
        .page-content { animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        
        .mobile-hamburger {
            display: none; background: none; border: none; font-size: 22px;
            cursor: pointer; color: var(--dash-text); padding: 4px 8px;
        }
        @media (max-width: 900px) { .mobile-hamburger { display: block; } }
        
        .sidebar-toggle-inline {
            width: 32px; height: 32px; border-radius: 8px;
            background: transparent; border: 1px solid var(--dash-border);
            cursor: pointer; display: flex !important; align-items: center; justify-content: center;
            color: var(--dash-text-secondary); font-size: 12px;
            transition: all 0.2s ease; flex-shrink: 0;
            opacity: 1 !important; visibility: visible !important;
        }
        .sidebar-toggle-inline:hover { background: var(--dash-hover); color: var(--dash-text); border-color: #7c5cff; }
        
        .dash-shell.sidebar-collapsed .sidebar-toggle-inline {
            position: fixed; left: 8px; top: 20px; z-index: 250;
            background: var(--dash-surface); border-radius: 0 8px 8px 0;
            box-shadow: var(--dash-shadow); padding: 6px;
        }
        
        .source-row {
            display: flex; align-items: center; gap: 12px; padding: 14px 16px;
            background: var(--dash-surface); border: 1px solid var(--dash-border);
            border-radius: 10px; margin-bottom: 8px;
        }
        
        /* Mode Switcher Tabs */
        .mode-tabs {
            display: flex; gap: 4px; background: var(--dash-hover);
            border-radius: 12px; padding: 4px; margin-bottom: 24px;
        }
        .mode-tab {
            flex: 1; padding: 12px 20px; border-radius: 10px; border: none;
            background: transparent; cursor: pointer; font-size: 14px; font-weight: 500;
            color: var(--dash-text-secondary); font-family: inherit;
            transition: all 0.2s ease; text-align: center;
        }
        .mode-tab.active {
            background: var(--dash-surface); color: var(--dash-text);
            font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .mode-tab:hover:not(.active) { color: var(--dash-text); }
        
        /* Research Type Selector */
        .research-type-selector {
            display: flex; gap: 12px; margin-bottom: 20px;
        }
        .research-type-card {
            flex: 1; padding: 20px; border-radius: 14px;
            border: 2px solid var(--dash-border); cursor: pointer;
            text-align: center; transition: all 0.2s ease;
            background: var(--dash-surface);
        }
        .research-type-card:hover { border-color: #7c5cff; }
        .research-type-card.selected { border-color: #7c5cff; background: rgba(124,92,255,0.05); }
        .research-type-card .icon { font-size: 36px; margin-bottom: 8px; }
        .research-type-card h3 { font-size: 16px; margin: 0 0 4px; color: var(--dash-text); }
        .research-type-card p { font-size: 12px; color: var(--dash-text-muted); margin: 0; }
        
        /* File Upload Zone */
        .upload-zone {
            border: 2px dashed var(--dash-border); border-radius: 14px;
            padding: 32px; text-align: center; cursor: pointer;
            transition: all 0.2s ease; background: var(--dash-surface);
            margin-bottom: 16px;
        }
        .upload-zone:hover { border-color: #7c5cff; background: rgba(124,92,255,0.03); }
        .upload-zone.has-file { border-color: #22c55e; border-style: solid; background: rgba(34,197,94,0.03); }
        .upload-zone .icon { font-size: 40px; margin-bottom: 8px; }
        .upload-zone p { color: var(--dash-text-muted); font-size: 13px; margin: 0; }
    </style>
</head>
<body class="dash-body" x-data="dashboardApp()" x-init="init()" :class="{ 'dark-mode': darkMode }">
    
    <div class="dash-shell" :class="{ 
        'sidebar-collapsed': !sidebarOpen && !isMobile,
        'sidebar-open': sidebarOpen && isMobile 
    }">
        
        <!-- Sidebar -->
        <aside class="side" @click.outside="if(isMobile) sidebarOpen = false">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 20px 8px;">
                <a class="side-brand" href="/dashboard" style="padding:0;">
                    <span class="side-brand-mark"></span>NuruXplore
                </a>
                <button class="sidebar-toggle-inline" @click="sidebarOpen = !sidebarOpen" :title="sidebarOpen ? 'Collapse' : 'Expand'">
                    <span x-show="sidebarOpen">◁</span>
                    <span x-show="!sidebarOpen">▷</span>
                </button>
            </div>
            
            <nav class="side-nav">
                <div class="side-section">Workspace</div>
                <a class="side-link" :class="{ active: currentPage === 'home' }" href="#" @click.prevent="navigate('home')">
                    <span class="i">▦</span> Dashboard
                </a>
                <a class="side-link" href="/chat">
                    <span class="i">💬</span> General Chat
                </a>
                <a class="side-link" :class="{ active: currentPage === 'projects' }" href="#" @click.prevent="navigate('projects')">
                    <span class="i">◰</span> Research Projects <span class="badge" x-text="projects.length"></span>
                </a>
                <a class="side-link" :class="{ active: currentPage === 'library' }" href="#" @click.prevent="navigate('library')">
                    <span class="i">☰</span> Library
                </a>
            </nav>
            
            <!-- Account Popup -->
            <div class="side-foot" style="position:relative;">
                <div x-show="showAccountMenu" @click.outside="showAccountMenu = false" class="account-popup">
                    <a href="/pricing"><span>◆</span> Upgrade Plan</a>
                    <button @click="toggleDarkMode(); showAccountMenu = false">
                        <span x-text="darkMode ? '☀️' : '🌙'"></span>
                        <span x-text="darkMode ? 'Light Mode' : 'Dark Mode'"></span>
                    </button>
                    <hr style="border-color:var(--dash-border);margin:4px 0;">
                    <button @click="logout()"><span>↪</span> Logout</button>
                </div>
                
                <div class="side-user" @click="showAccountMenu = !showAccountMenu" style="cursor:pointer;">
                    <div class="avatar" x-text="userInitials">U</div>
                    <div>
                        <div class="u-name" x-text="userName">User</div>
                        <div class="u-plan">Free plan · <span x-text="credits"></span> credits</div>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="dash-main">
            
            <header class="dash-top">
                <button class="mobile-hamburger" @click="sidebarOpen = !sidebarOpen">☰</button>
                <div class="search">
                    <span>⌕</span>
                    <input placeholder="Search projects..." x-model="searchQuery">
                </div>
                <div class="dash-top-right">
                    <button class="theme-toggle" @click="toggleDarkMode()" :title="darkMode ? 'Light Mode' : 'Dark Mode'">
                        <span x-text="darkMode ? '☀️' : '🌙'"></span>
                    </button>
                </div>
            </header>
            
            <!-- ============================ -->
            <!-- HOME PAGE                  -->
            <!-- ============================ -->
            <div x-show="currentPage === 'home'" class="page-content">
                
                <div style="display:flex;justify-content:center;width:100%;">
                    <section class="dash-hero" style="max-width:800px;width:100%;text-align:center;">
                        <h1><span x-text="greeting"></span><span x-text="userName.split(' ')[0]"></span> 👋</h1>
                        <p class="sub">What would you like to do today?</p>
                        
                        <!-- MODE SWITCHER -->
                        <div class="mode-tabs">
                            <button class="mode-tab" :class="{ active: activeMode === 'general' }" @click="activeMode = 'general'">
                                💬 General Chat
                            </button>
                            <button class="mode-tab" :class="{ active: activeMode === 'research' }" @click="activeMode = 'research'">
                                🔬 Research Expert
                            </button>
                        </div>
                        
                        <!-- ============================ -->
                        <!-- GENERAL CHAT MODE          -->
                        <!-- ============================ -->
                        <div x-show="activeMode === 'general'">
                            <div style="background:var(--dash-surface);border:1px solid var(--dash-border);border-radius:16px;padding:32px;text-align:center;">
                                <div style="font-size:48px;margin-bottom:12px;">💬</div>
                                <h3 style="color:var(--dash-text);margin:0 0 8px;">General Academic Chat</h3>
                                <p style="color:var(--dash-text-muted);margin:0 0 20px;font-size:14px;">
                                    ChatGPT-style assistant for any academic topic.<br>
                                    Ask questions, get explanations, discuss research ideas.
                                </p>
                                <a href="/chat" class="nuru-btn nuru-btn-grad" style="justify-content:center;padding:12px 24px;text-decoration:none;display:inline-flex;">
                                    Open General Chat →
                                </a>
                            </div>
                        </div>
                        
                        <!-- ============================ -->
                        <!-- RESEARCH EXPERT MODE       -->
                        <!-- ============================ -->
                        <div x-show="activeMode === 'research'">
                            
                            <!-- Research Type Selector -->
                            <div class="research-type-selector">
                                <div class="research-type-card" :class="{ selected: researchType === 'proposal' }" @click="researchType = 'proposal'">
                                    <div class="icon">📋</div>
                                    <h3>Research Proposal</h3>
                                    <p>Generate a 1,500-2,500 word proposal</p>
                                </div>
                                <div class="research-type-card" :class="{ selected: researchType === 'thesis' }" @click="researchType = 'thesis'">
                                    <div class="icon">📄</div>
                                    <h3>Full Thesis</h3>
                                    <p>Generate a 5,000+ word thesis</p>
                                </div>
                            </div>
                            
                            <!-- Topic Input -->
                            <div class="prompt-box-wrapper" style="margin:0 auto;">
                                <div class="prompt-box" :class="{ 'typing': isTyping }">
                                    <div class="prompt-box-inner">
                                        <textarea x-model="newProjectTitle" x-ref="promptTextarea" @input="handleTyping()"
                                            @keydown.enter.prevent="createProjectWithAI()"
                                            :placeholder="researchType === 'proposal' ? 'Describe your research proposal topic…' : 'Describe your thesis topic… e.g. The impact of mobile banking on financial inclusion in rural Tanzania'"
                                            rows="2" :style="{ height: textareaHeight + 'px' }" :disabled="isGenerating"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- File Upload (only for Thesis) -->
                            <div x-show="researchType === 'thesis'" style="margin-top:16px;">
                                <div class="upload-zone" 
                                     :class="{ 'has-file': uploadedFile }"
                                     @click="document.getElementById('fileUpload').click()"
                                     @dragover.prevent @drop.prevent="handleDrop($event)">
                                    <div class="icon" x-text="uploadedFile ? '✅' : '📁'"></div>
                                    <p x-show="!uploadedFile">Upload your research proposal or collected data (PDF)</p>
                                    <p x-show="uploadedFile" style="color:#22c55e;">
                                        <strong x-text="uploadedFileName"></strong> ready!
                                    </p>
                                    <p style="font-size:11px;color:var(--dash-text-muted);margin-top:4px;">
                                        AI will use your uploaded data to generate the thesis
                                    </p>
                                    <input type="file" id="fileUpload" @change="handleFileUpload($event)" accept=".pdf,.doc,.docx" style="display:none;">
                                </div>
                            </div>
                            
                            <!-- Generate Button -->
                            <div style="margin-top:16px;">
                                <div style="display:flex;align-items:center;justify-content:center;gap:12px;">
                                    <span style="font-size:11px;color:var(--dash-text-muted);">
                                        <span x-text="researchType === 'proposal' ? '15 credits' : '25 credits'"></span>
                                    </span>
                                    <span style="font-size:12px;color:var(--dash-text-secondary);">
                                        <span x-text="researchType === 'proposal' ? 'Research Proposal' : 'Thesis'"></span> · APA 7
                                    </span>
                                    <button class="submit-arrow" @click="createProjectWithAI()" :disabled="!newProjectTitle.trim() || isGenerating">
                                        <span x-show="!isGenerating">↑</span>
                                        <span x-show="isGenerating" style="font-size:12px;">⏳</span>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Generation Progress -->
                            <div x-show="isGenerating" style="margin-top:24px;background:var(--dash-surface);border:1px solid var(--dash-border);border-radius:14px;padding:20px;text-align:left;">
                                <div style="font-weight:600;margin-bottom:12px;color:var(--dash-text);">🚀 Generating your <span x-text="researchType"></span>...</div>
                                <template x-for="step in generationSteps" :key="step.step">
                                    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;font-size:14px;color:var(--dash-text-secondary);">
                                        <span x-show="step.status === 'processing'" style="animation:nuru-spin 1s linear infinite;">⏳</span>
                                        <span x-show="step.status === 'completed'">✅</span>
                                        <span x-text="step.message"></span>
                                    </div>
                                </template>
                                <div x-show="generationComplete" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--dash-border);">
                                    <a :href="'/workspace/' + generatedProjectUUID" class="nuru-btn nuru-btn-grad" style="width:100%;justify-content:center;">Open Workspace →</a>
                                </div>
                            </div>
                        </div>
                        
                    </section>
                </div>
                
                <!-- Recent Projects -->
                <section class="dash-section">
                    <div class="dash-section-head">
                        <h2>Recent projects</h2>
                        <div class="tabs-lite">
                            <button class="tl" :class="{ active: activeTab === 'all' }" @click="activeTab = 'all'">All</button>
                            <button class="tl" :class="{ active: activeTab === 'draft' }" @click="activeTab = 'draft'">Drafting</button>
                        </div>
                    </div>
                    <div class="proj-grid">
                        <template x-for="project in filteredProjects" :key="project.id">
                            <a :href="project.type === 'chat' ? '/chat/' + project.uuid : '/workspace/' + project.uuid" class="proj">
                                <div class="proj-thumb" :style="'background:' + getGradient(project.id)"></div>
                                <div class="proj-body">
                                    <div class="proj-title" x-text="project.title"></div>
                                    <div class="proj-meta">
                                        <span x-text="project.type === 'chat' ? '💬 Chat' : '📄 ' + project.type"></span> · 
                                        <span x-text="project.word_count + ' words'"></span>
                                    </div>
                                    <div class="proj-foot">
                                        <span class="dot" :class="project.status"></span>
                                        <span x-text="project.status.replace('_', ' ') + ' · ' + project.last_edited_at"></span>
                                    </div>
                                </div>
                            </a>
                        </template>
                        <div x-show="filteredProjects.length === 0" class="empty-state">
                            <div class="icon">📝</div><h3>No projects yet</h3>
                        </div>
                    </div>
                </section>
            </div>
            
            <!-- ============================ -->
            <!-- PROJECTS PAGE              -->
            <!-- ============================ -->
            <div x-show="currentPage === 'projects'" class="page-content" style="padding:32px;">
                <div class="dash-section-head">
                    <h2>All Projects</h2>
                    <select style="padding:6px 12px;border-radius:8px;border:1px solid var(--dash-border);background:var(--dash-surface);color:var(--dash-text);font-family:inherit;font-size:12px;" x-model="projectSort">
                        <option value="recent">Most Recent</option>
                        <option value="oldest">Oldest</option>
                        <option value="words">Most Words</option>
                        <option value="title">Alphabetical</option>
                    </select>
                </div>
                <div class="proj-grid">
                    <template x-for="project in sortedProjects" :key="project.id">
                        <a :href="project.type === 'chat' ? '/chat/' + project.uuid : '/workspace/' + project.uuid" class="proj">
                            <div class="proj-thumb" :style="'background:' + getGradient(project.id)"></div>
                            <div class="proj-body">
                                <div class="proj-title" x-text="project.title"></div>
                                <div class="proj-meta">
                                    <span x-text="project.type === 'chat' ? '💬 Chat' : '📄 ' + project.type"></span> · 
                                    <span x-text="project.word_count + ' words'"></span>
                                </div>
                                <div class="proj-foot">
                                    <span class="dot" :class="project.status"></span>
                                    <span x-text="project.status.replace('_', ' ') + ' · ' + project.last_edited_at"></span>
                                    <button @click.prevent="deleteProject(project.uuid)" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:14px;" title="Delete">🗑</button>
                                </div>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
            
            <!-- ============================ -->
            <!-- LIBRARY PAGE               -->
            <!-- ============================ -->
            <div x-show="currentPage === 'library'" class="page-content" style="padding:32px;">
                <div class="dash-section-head">
                    <h2>Source Library</h2>
                    <button class="nuru-btn" @click="copyAllCitations()">📋 Copy All APA 7</button>
                </div>
                <div style="margin-bottom:16px;">
                    <input type="text" placeholder="Search sources..." x-model="sourceSearch" style="padding:8px 12px;border-radius:8px;border:1px solid var(--dash-border);background:var(--dash-surface);color:var(--dash-text);font-family:inherit;width:100%;">
                </div>
                <template x-for="source in filteredSources" :key="source.id">
                    <div class="source-row">
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:13px;" x-text="source.title"></div>
                            <div style="font-size:11px;color:var(--dash-text-muted);" x-text="(source.author||'Unknown')+' ('+(source.year||'n.d.')+') · '+source.type"></div>
                            <div style="font-size:10px;color:var(--dash-text-muted);margin-top:4px;font-family:monospace;" x-text="generateAPA(source)"></div>
                        </div>
                        <button @click="copySourceCitation(source)" style="background:none;border:none;cursor:pointer;font-size:16px;" title="Copy APA citation">📋</button>
                        <button @click="deleteSource(source.id)" style="background:none;border:none;cursor:pointer;font-size:14px;">🗑</button>
                    </div>
                </template>
            </div>
            
        </main>
    </div>

    <script src="{{ asset('js/nuru-api.js') }}"></script>
    <script>
        function dashboardApp() {
            return {
                userName: '{{ auth()->user()->name ?? "User" }}',
                userInitials: '{{ strtoupper(substr(auth()->user()->name ?? "U", 0, 2)) }}',
                credits: {{ auth()->user()->credits_balance ?? 0 }},
                
                darkMode: localStorage.getItem('nuruxplore_theme') === 'dark',
                sidebarOpen: window.innerWidth > 900,
                isMobile: window.innerWidth <= 900,
                currentPage: 'home',
                showAccountMenu: false,
                
                // Mode Switcher
                activeMode: 'research',  // 'general' or 'research'
                researchType: 'thesis',  // 'proposal' or 'thesis'
                
                // File Upload
                uploadedFile: null,
                uploadedFileName: '',
                
                // Project creation
                isTyping: false, isGenerating: false, generationComplete: false,
                textareaHeight: 56, searchQuery: '', activeTab: 'all',
                newProjectTitle: '',
                generationSteps: [], generatedProjectUUID: null,
                
                projects: [], projectSort: 'recent',
                allSources: [], sourceSearch: '',
                
                get greeting() {
                    const hour = new Date().getHours();
                    if (hour < 12) return 'Good morning, ';
                    if (hour < 17) return 'Good afternoon, ';
                    return 'Good evening, ';
                },
                
                get filteredProjects() {
                    let f = this.projects;
                    if (this.activeTab === 'draft') f = f.filter(p => p.status === 'draft' || p.status === 'in_progress');
                    if (this.searchQuery.trim()) {
                        const q = this.searchQuery.toLowerCase();
                        f = f.filter(p => p.title.toLowerCase().includes(q));
                    }
                    return f;
                },
                
                get sortedProjects() {
                    let p = [...this.projects];
                    if (this.searchQuery.trim()) {
                        const q = this.searchQuery.toLowerCase();
                        p = p.filter(pr => pr.title.toLowerCase().includes(q));
                    }
                    if (this.projectSort === 'oldest') p.reverse();
                    else if (this.projectSort === 'words') p.sort((a,b) => b.word_count - a.word_count);
                    else if (this.projectSort === 'title') p.sort((a,b) => a.title.localeCompare(b.title));
                    return p;
                },
                
                get filteredSources() {
                    if (!this.sourceSearch.trim()) return this.allSources;
                    const q = this.sourceSearch.toLowerCase();
                    return this.allSources.filter(s => (s.title||'').toLowerCase().includes(q) || (s.author||'').toLowerCase().includes(q));
                },
                
                async init() {
                    if (this.darkMode) document.documentElement.setAttribute('data-theme', 'dark');
                    window.addEventListener('resize', () => this.isMobile = window.innerWidth <= 900);
                    await this.loadProjects();
                },
                
                navigate(page) {
                    this.currentPage = page;
                    this.showAccountMenu = false;
                    if (this.isMobile) this.sidebarOpen = false;
                    if (page === 'library') this.loadAllSources();
                },
                
                async loadProjects() {
                    try { const d = await window.NuruAPI.getProjects(); this.projects = Array.isArray(d) ? d : []; } catch (e) {}
                },
                
                async loadAllSources() {
                    try {
                        let sources = [];
                        for (const p of this.projects) {
                            try { const d = await window.NuruAPI.getSources(p.uuid); if (d.sources) sources = sources.concat(d.sources); } catch(e) {}
                        }
                        this.allSources = sources;
                    } catch(e) {}
                },
                
                handleTyping() {
                    this.isTyping = true;
                    clearTimeout(this._t); this._t = setTimeout(() => this.isTyping = false, 1000);
                    this.$nextTick(() => { const ta = this.$refs.promptTextarea; if(ta){ ta.style.height='auto'; this.textareaHeight = Math.min(ta.scrollHeight,200); } });
                },
                
                handleFileUpload(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.uploadedFile = file;
                        this.uploadedFileName = file.name;
                    }
                },
                
                handleDrop(e) {
                    const file = e.dataTransfer.files[0];
                    if (file && file.type === 'application/pdf') {
                        this.uploadedFile = file;
                        this.uploadedFileName = file.name;
                    }
                },
                
                async createProjectWithAI() {
                    const topic = this.newProjectTitle.trim();
                    if (!topic || this.isGenerating) return;
                    
                    this.isGenerating = true; this.generationComplete = false; this.generationSteps = [];
                    
                    const type = this.researchType === 'proposal' ? 'proposal' : 'thesis';
                    const cost = this.researchType === 'proposal' ? 15 : 25;
                    
                    try {
                        this.generationSteps.push({step:'create',status:'processing',message:'📁 Creating project...'});
                        const project = await window.NuruAPI.createProject({title:topic, type:type, citation_style:'APA7'});
                        this.generationSteps[0].status='completed'; this.generationSteps[0].message='✅ Project created';
                        this.generatedProjectUUID = project.uuid;
                        
                        // Upload file if present
                        if (this.uploadedFile) {
                            this.generationSteps.push({step:'upload',status:'processing',message:'📁 Uploading your document...'});
                            const formData = new FormData();
                            formData.append('project_id', project.id);
                            formData.append('file', this.uploadedFile);
                            
                            await fetch('/api/sources/upload', {
                                method: 'POST',
                                headers: { 'Authorization': `Bearer ${localStorage.getItem('nuruxplore_token')}` },
                                body: formData,
                            });
                            this.generationSteps[this.generationSteps.length-1].status = 'completed';
                            this.generationSteps[this.generationSteps.length-1].message = '✅ Document uploaded';
                        }
                        
                        // Generate
                        this.generationSteps.push({step:'generate',status:'processing',message:'🤖 AI generating...'});
                        const result = await fetch(`/api/projects/${this.generatedProjectUUID}/generate-complete`,{
                            method:'POST',
                            headers:{
                                'Authorization':`Bearer ${localStorage.getItem('nuruxplore_token')}`,
                                'Content-Type':'application/json',
                                'Accept':'application/json'
                            },
                            body:JSON.stringify({topic, type:type})
                        });
                        
                        const data = await result.json();
                        if(result.ok&&data.steps){
                            this.generationSteps=data.steps;
                            this.generationComplete=true;
                            this.credits=data.credits_remaining;
                            setTimeout(()=>{window.location.href='/workspace/'+this.generatedProjectUUID;},2000);
                        } else {
                            this.generationSteps.push({step:'error',status:'completed',message:'❌ '+(data.message||'Generation failed.')});
                        }
                    } catch(e) {
                        this.generationSteps.push({step:'error',status:'completed',message:'❌ '+e.message});
                    }
                    
                    this.isGenerating=false;
                    this.uploadedFile = null;
                    this.uploadedFileName = '';
                    await this.loadProjects();
                },
                
                async deleteProject(uuid) { if(!confirm('Delete?'))return; try{await window.NuruAPI.deleteProject(uuid);await this.loadProjects();}catch(e){} },
                async deleteSource(id) { if(!confirm('Delete?'))return; try{await window.NuruAPI.deleteSource(id);await this.loadAllSources();}catch(e){} },
                
                generateAPA(source) {
                    return `${source.author||'Unknown'} (${source.year||'n.d.'}). ${source.title||'Untitled'}.`;
                },
                
                copySourceCitation(source) { navigator.clipboard.writeText(this.generateAPA(source)); alert('Copied!'); },
                copyAllCitations() {
                    if (!this.allSources.length) { alert('No sources.'); return; }
                    navigator.clipboard.writeText(this.allSources.map(s=>this.generateAPA(s)).join('\n\n'));
                    alert('All citations copied!');
                },
                
                toggleDarkMode() { this.darkMode=!this.darkMode; localStorage.setItem('nuruxplore_theme',this.darkMode?'dark':'light'); document.documentElement.setAttribute('data-theme',this.darkMode?'dark':'light'); },
                async logout() { try{await window.NuruAPI.logout();}catch(e){} window.NuruAPI.clearToken(); window.location.href='/login'; },
                
                getGradient(id) {
                    const g = ['linear-gradient(135deg,#7c5cff 0%,#3aa0ff 100%)','linear-gradient(135deg,#ff5b8a 0%,#ffd166 100%)','linear-gradient(135deg,#22c55e 0%,#3aa0ff 100%)','linear-gradient(135deg,#0a0a0a 0%,#7c5cff 100%)','linear-gradient(135deg,#ffd166 0%,#ff5b8a 100%)','linear-gradient(135deg,#3aa0ff 0%,#22c55e 100%)'];
                    return g[(id||0)%g.length];
                }
            }
        }
    </script>
</body>
</html>