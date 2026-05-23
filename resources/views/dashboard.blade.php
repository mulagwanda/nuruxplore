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
        /* Account popup */
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
        
        /* Page transitions */
        .page-content { animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Templates grid */
        .template-card {
            background: var(--dash-surface); border: 1px solid var(--dash-border);
            border-radius: 14px; padding: 24px; cursor: pointer; transition: all 0.2s ease;
        }
        .template-card:hover { border-color: #7c5cff; box-shadow: var(--dash-shadow); transform: translateY(-2px); }
        .template-card .icon { font-size: 32px; margin-bottom: 12px; }
        .template-card h3 { font-size: 16px; margin: 0 0 6px; }
        .template-card p { font-size: 12px; color: var(--dash-text-muted); margin: 0; }
        
        /* Source list */
        .source-row {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            background: var(--dash-surface); border: 1px solid var(--dash-border);
            border-radius: 10px; margin-bottom: 8px;
        }
        
        /* Citation card */
        .citation-card {
            background: var(--dash-surface); border: 1px solid var(--dash-border);
            border-radius: 10px; padding: 16px; margin-bottom: 8px;
        }
        .citation-card .cite-text { font-family: 'Fraunces', Georgia, serif; font-size: 14px; color: var(--dash-text); }
        .citation-card .cite-meta { font-size: 11px; color: var(--dash-text-muted); margin-top: 6px; }
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
                <button class="sidebar-toggle-inline" @click="sidebarOpen = !sidebarOpen" :title="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'">
                    <span x-show="sidebarOpen">◁</span>
                    <span x-show="!sidebarOpen">▷</span>
                </button>
            </div>
            
            <nav class="side-nav">
                <div class="side-section">Workspace</div>
                <a class="side-link" :class="{ active: currentPage === 'home' }" href="#" @click.prevent="navigate('home')">
                    <span class="i">▦</span> Dashboard
                </a>
                <a class="side-link" :class="{ active: currentPage === 'projects' }" href="#" @click.prevent="navigate('projects')">
                    <span class="i">◰</span> Projects <span class="badge" x-text="projects.length"></span>
                </a>
                <a class="side-link" :class="{ active: currentPage === 'templates' }" href="#" @click.prevent="navigate('templates')">
                    <span class="i">▤</span> Templates
                </a>
                <a class="side-link" :class="{ active: currentPage === 'library' }" href="#" @click.prevent="navigate('library')">
                    <span class="i">☰</span> Library
                </a>
                <a class="side-link" :class="{ active: currentPage === 'citations' }" href="#" @click.prevent="navigate('citations')">
                    <span class="i">❝</span> Citations
                </a>
            </nav>
            
            <!-- Sidebar Footer with Account Popup -->
            <div class="side-foot" style="position:relative;">
                <!-- Account Popup -->
                <div x-show="showAccountMenu" @click.outside="showAccountMenu = false" class="account-popup">
                    <a href="#" @click.prevent="navigate('home'); showAccountMenu = false"><span>👤</span> Profile</a>
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
                <div class="search">
                    <span>⌕</span>
                    <input placeholder="Search..." x-model="searchQuery">
                </div>
                <div class="dash-top-right">
                    <button class="theme-toggle" @click="toggleDarkMode()" :title="darkMode ? 'Light Mode' : 'Dark Mode'">
                        <span x-text="darkMode ? '☀️' : '🌙'"></span>
                    </button>
                </div>
            </header>
            
            <!-- ============================ -->
            <!-- PAGE: HOME (Dashboard)      -->
            <!-- ============================ -->
            <div x-show="currentPage === 'home'" class="page-content">
                
                <div style="display:flex;justify-content:center;width:100%;">
                    <section class="dash-hero" style="max-width:800px;width:100%;text-align:center;">
                        <h1><span x-text="greeting"></span><span x-text="userName.split(' ')[0]"></span> 👋</h1>
                        <p class="sub">What are we drafting today? Describe your topic and let AI generate a complete thesis.</p>
                        
                        <div class="prompt-box-wrapper" style="margin:0 auto;">
                            <div class="prompt-box" :class="{ 'typing': isTyping }">
                                <div class="prompt-box-inner">
                                    <textarea x-model="newProjectTitle" x-ref="promptTextarea" @input="handleTyping()"
                                        @keydown.enter.prevent="createProjectWithAI()"
                                        placeholder="Describe your thesis topic… e.g. 'A mixed-methods study on remote learning outcomes.'"
                                        rows="1" :style="{ height: textareaHeight + 'px' }" :disabled="isGenerating"></textarea>
                                </div>
                                <div class="prompt-bar">
                                    <div class="left">
                                        <button class="attach-btn"><span class="icon">📄</span> PDF</button>
                                        <button class="attach-btn"><span class="icon">🔗</span> DOI</button>
                                    </div>
                                    <div class="right">
                                        <span style="font-size:11px;color:var(--dash-text-muted);margin-right:4px;">25 credits</span>
                                        <button class="selector-btn" @click="cycleType()"><span x-text="projectType"></span><span class="arrow">▾</span></button>
                                        <button class="selector-btn" @click="cycleCitation()"><span x-text="citationStyle"></span><span class="arrow">▾</span></button>
                                        <button class="submit-arrow" @click="createProjectWithAI()" :disabled="!newProjectTitle.trim() || isGenerating">
                                            <span x-show="!isGenerating">↑</span><span x-show="isGenerating" style="font-size:12px;">⏳</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div x-show="isGenerating" style="margin-top:24px;background:var(--dash-surface);border:1px solid var(--dash-border);border-radius:14px;padding:20px;text-align:left;max-width:600px;margin-left:auto;margin-right:auto;">
                            <div style="font-weight:600;margin-bottom:12px;color:var(--dash-text);">🚀 Generating your thesis...</div>
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
                        
                        <div class="quick-row" style="justify-content:center;margin-top:16px;">
                            <span class="label">Try:</span>
                            <button class="quick-chip" @click="setPrompt('A mixed-methods study on remote learning outcomes in undergraduate engineering students')">IMRaD thesis</button>
                            <button class="quick-chip" @click="setPrompt('Literature review on artificial intelligence applications in higher education')">Literature review</button>
                            <button class="quick-chip" @click="setPrompt('Methodology chapter for a study on digital transformation in SMEs')">Methodology</button>
                            <button class="quick-chip" @click="setPrompt('Research proposal on renewable energy adoption in developing countries')">Proposal</button>
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
                            <button class="tl" :class="{ active: activeTab === 'review' }" @click="activeTab = 'review'">In review</button>
                        </div>
                    </div>
                    <div class="proj-grid">
                        <template x-for="project in filteredProjects" :key="project.id">
                            <a :href="'/workspace/' + project.uuid" class="proj">
                                <div class="proj-thumb" :style="'background:' + getGradient(project.id)"></div>
                                <div class="proj-body">
                                    <div class="proj-title" x-text="project.title"></div>
                                    <div class="proj-meta"><span x-text="project.type"></span> · <span x-text="project.citation_style"></span> · <span x-text="project.word_count + ' words'"></span></div>
                                    <div class="proj-foot"><span class="dot" :class="project.status"></span><span x-text="project.status.replace('_', ' ') + ' · ' + project.last_edited_at"></span></div>
                                </div>
                            </a>
                        </template>
                        <div x-show="filteredProjects.length === 0" class="empty-state">
                            <div class="icon">📝</div><h3>No projects yet</h3><p>Describe your topic above!</p>
                        </div>
                    </div>
                </section>
                
                <section class="dash-section">
                    <div class="dash-section-head"><h2>Jump back in</h2><a href="#" @click.prevent="navigate('templates')" style="color:var(--dash-text-muted);font-size:13px;">Browse templates →</a></div>
                    <div class="mini-grid">
                        <div class="mini" @click="setPrompt('Literature review on')"><b>📚 Literature review</b><span>Synthesize sources into themes</span></div>
                        <div class="mini" @click="setPrompt('Methodology for')"><b>✎ Methodology</b><span>Methods, sampling, procedures</span></div>
                        <div class="mini" @click="setPrompt('Research proposal on')"><b>⌗ Research proposal</b><span>Structure your proposal</span></div>
                        <div class="mini" @click="setPrompt('')"><b>＋ Blank draft</b><span>Begin from scratch</span></div>
                    </div>
                </section>
            </div>
            
            <!-- ============================ -->
            <!-- PAGE: PROJECTS              -->
            <!-- ============================ -->
            <div x-show="currentPage === 'projects'" class="page-content" style="padding:32px;">
                <div class="dash-section-head">
                    <h2>All Projects</h2>
                    <div style="display:flex;gap:8px;">
                        <select style="padding:6px 12px;border-radius:8px;border:1px solid var(--dash-border);background:var(--dash-surface);color:var(--dash-text);font-family:inherit;font-size:12px;" x-model="projectSort">
                            <option value="recent">Most Recent</option>
                            <option value="oldest">Oldest</option>
                            <option value="words">Most Words</option>
                            <option value="title">Alphabetical</option>
                        </select>
                    </div>
                </div>
                <div class="proj-grid">
                    <template x-for="project in sortedProjects" :key="project.id">
                        <a :href="'/workspace/' + project.uuid" class="proj">
                            <div class="proj-thumb" :style="'background:' + getGradient(project.id)"></div>
                            <div class="proj-body">
                                <div class="proj-title" x-text="project.title"></div>
                                <div class="proj-meta"><span x-text="project.type"></span> · <span x-text="project.citation_style"></span> · <span x-text="project.word_count + ' words'"></span></div>
                                <div class="proj-foot">
                                    <span class="dot" :class="project.status"></span>
                                    <span x-text="project.status.replace('_', ' ') + ' · ' + project.last_edited_at"></span>
                                    <button @click.prevent="deleteProject(project.uuid)" style="margin-left:auto;background:none;border:none;cursor:pointer;font-size:14px;" title="Delete">🗑</button>
                                </div>
                            </div>
                        </a>
                    </template>
                    <div x-show="sortedProjects.length === 0" class="empty-state"><div class="icon">📝</div><h3>No projects</h3></div>
                </div>
            </div>
            
            <!-- ============================ -->
            <!-- PAGE: TEMPLATES            -->
            <!-- ============================ -->
            <div x-show="currentPage === 'templates'" class="page-content" style="padding:32px;">
                <div class="dash-section-head"><h2>Thesis Templates</h2><span style="font-size:12px;color:var(--dash-text-muted);">Click to start with a pre-built structure</span></div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                    <div class="template-card" @click="startFromTemplate('IMRaD Thesis', 'thesis')">
                        <div class="icon">📄</div><h3>IMRaD Thesis</h3><p>Introduction, Methods, Results, and Discussion structure. Ideal for empirical research.</p>
                        <span style="font-size:10px;background:#f1ede5;padding:2px 8px;border-radius:999px;">APA 7</span>
                    </div>
                    <div class="template-card" @click="startFromTemplate('Literature Review', 'literature_review')">
                        <div class="icon">📚</div><h3>Literature Review</h3><p>Comprehensive review of existing research with thematic synthesis.</p>
                        <span style="font-size:10px;background:#f1ede5;padding:2px 8px;border-radius:999px;">APA 7</span>
                    </div>
                    <div class="template-card" @click="startFromTemplate('Case Study', 'case_study')">
                        <div class="icon">🔍</div><h3>Case Study</h3><p>In-depth analysis of a specific case with theoretical framework.</p>
                        <span style="font-size:10px;background:#f1ede5;padding:2px 8px;border-radius:999px;">APA 7</span>
                    </div>
                    <div class="template-card" @click="startFromTemplate('Research Proposal', 'capstone')">
                        <div class="icon">📋</div><h3>Research Proposal</h3><p>Structure your research proposal with objectives, methodology, and timeline.</p>
                        <span style="font-size:10px;background:#f1ede5;padding:2px 8px;border-radius:999px;">APA 7</span>
                    </div>
                    <div class="template-card" @click="startFromTemplate('Lab Report', 'lab_report')">
                        <div class="icon">🔬</div><h3>Lab Report</h3><p>Scientific report structure with hypothesis, procedure, and analysis.</p>
                        <span style="font-size:10px;background:#f1ede5;padding:2px 8px;border-radius:999px;">IEEE</span>
                    </div>
                    <div class="template-card" @click="startFromTemplate('Dissertation', 'dissertation')">
                        <div class="icon">🎓</div><h3>PhD Dissertation</h3><p>Complete dissertation structure with all chapters and appendices.</p>
                        <span style="font-size:10px;background:#f1ede5;padding:2px 8px;border-radius:999px;">APA 7</span>
                    </div>
                </div>
            </div>
            
            <!-- ============================ -->
            <!-- PAGE: LIBRARY              -->
            <!-- ============================ -->
            <div x-show="currentPage === 'library'" class="page-content" style="padding:32px;">
                <div class="dash-section-head"><h2>Source Library</h2><span style="font-size:12px;color:var(--dash-text-muted);">Manage your research sources and uploaded PDFs</span></div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">
                    <input type="text" placeholder="Search sources..." x-model="sourceSearch" style="padding:8px 12px;border-radius:8px;border:1px solid var(--dash-border);background:var(--dash-surface);color:var(--dash-text);font-family:inherit;">
                    <button class="nuru-btn" @click="document.getElementById('libFileUpload').click()" style="justify-content:center;">📁 Upload PDF</button>
                    <input type="file" id="libFileUpload" @change="uploadLibraryFile($event)" accept=".pdf,.doc,.docx" style="display:none;">
                </div>
                
                <template x-for="source in allSources" :key="source.id">
                    <div class="source-row">
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:13px;" x-text="source.title"></div>
                            <div style="font-size:11px;color:var(--dash-text-muted);" x-text="(source.author||'Unknown')+' ('+(source.year||'n.d.')+') · '+source.type"></div>
                        </div>
                        <span style="font-size:10px;padding:2px 8px;border-radius:999px;" :style="source.verification_status==='verified'?'background:#dcfce7;color:#166534':'background:#fef3c7;color:#92400e'" x-text="source.verification_status"></span>
                        <button @click="deleteSource(source.id)" style="background:none;border:none;cursor:pointer;">🗑</button>
                    </div>
                </template>
                <div x-show="allSources.length === 0" class="empty-state"><div class="icon">📚</div><h3>No sources yet</h3><p>Upload PDFs or add sources from your projects.</p></div>
            </div>
            
            <!-- ============================ -->
            <!-- PAGE: CITATIONS            -->
            <!-- ============================ -->
            <div x-show="currentPage === 'citations'" class="page-content" style="padding:32px;">
                <div class="dash-section-head">
                    <h2>Citation Manager</h2>
                    <div style="display:flex;gap:8px;">
                        <select style="padding:6px 12px;border-radius:8px;border:1px solid var(--dash-border);background:var(--dash-surface);color:var(--dash-text);font-family:inherit;font-size:12px;" x-model="citationStyleFilter">
                            <option value="all">All Styles</option>
                            <option value="APA7">APA 7</option>
                            <option value="MLA">MLA</option>
                            <option value="Chicago">Chicago</option>
                            <option value="IEEE">IEEE</option>
                        </select>
                        <button class="nuru-btn" @click="copyAllCitations()">📋 Copy All</button>
                    </div>
                </div>
                
                <div style="margin-bottom:16px;display:flex;gap:8px;">
                    <button class="tl" :class="{ active: citeFormat === 'apa' }" @click="citeFormat = 'apa'">APA 7</button>
                    <button class="tl" :class="{ active: citeFormat === 'mla' }" @click="citeFormat = 'mla'">MLA</button>
                    <button class="tl" :class="{ active: citeFormat === 'chicago' }" @click="citeFormat = 'chicago'">Chicago</button>
                </div>
                
                <template x-for="source in allSources" :key="source.id">
                    <div class="citation-card">
                        <div class="cite-text" x-text="generateCitation(source, citeFormat)"></div>
                        <div class="cite-meta">From: <span x-text="source.type"></span> · <span x-text="source.verification_status"></span></div>
                        <button @click="copyCitation(source, citeFormat)" style="margin-top:8px;font-size:11px;padding:4px 8px;border-radius:4px;border:1px solid var(--dash-border);background:var(--dash-surface);cursor:pointer;">📋 Copy</button>
                    </div>
                </template>
                <div x-show="allSources.length === 0" class="empty-state"><div class="icon">❝</div><h3>No citations yet</h3><p>Add sources to generate formatted citations.</p></div>
            </div>
            
        </main>
    </div>

    <script src="{{ asset('js/nuru-api.js') }}"></script>
    <script>
        function dashboardApp() {
            return {
                // User
                userName: '{{ auth()->user()->name ?? "User" }}',
                userInitials: '{{ strtoupper(substr(auth()->user()->name ?? "U", 0, 2)) }}',
                credits: {{ auth()->user()->credits_balance ?? 0 }},
                
                // UI State
                darkMode: localStorage.getItem('nuruxplore_theme') === 'dark',
                sidebarOpen: window.innerWidth > 900,
                isMobile: window.innerWidth <= 900,
                currentPage: 'home',
                showAccountMenu: false,
                
                // Project creation
                isTyping: false, isGenerating: false, generationComplete: false,
                textareaHeight: 56, searchQuery: '', activeTab: 'all',
                newProjectTitle: '', projectType: 'Thesis',
                projectTypes: ['Thesis', 'Dissertation', 'Literature Review', 'Lab Report', 'Case Study', 'Capstone'],
                typeIndex: 0, citationStyle: 'APA 7',
                citationStyles: ['APA 7', 'MLA', 'Chicago', 'IEEE'], citationIndex: 0,
                generationSteps: [], generatedProjectUUID: null,
                
                // Projects
                projects: [], projectSort: 'recent',
                
                // Sources
                allSources: [], sourceSearch: '',
                
                // Citations
                citationStyleFilter: 'all', citeFormat: 'apa',
                
                get greeting() {
                    const hour = new Date().getHours();
                    if (hour < 12) return 'Good morning, ';
                    if (hour < 17) return 'Good afternoon, ';
                    return 'Good evening, ';
                },
                
                get filteredProjects() {
                    let f = this.projects;
                    if (this.activeTab === 'draft') f = f.filter(p => p.status === 'draft' || p.status === 'in_progress');
                    else if (this.activeTab === 'review') f = f.filter(p => p.status === 'review');
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
                
                async init() {
                    if (this.darkMode) document.documentElement.setAttribute('data-theme', 'dark');
                    window.addEventListener('resize', () => this.isMobile = window.innerWidth <= 900);
                    await Promise.all([this.loadProjects(), this.loadAllSources()]);
                },
                
                navigate(page) {
                    this.currentPage = page;
                    this.showAccountMenu = false;
                    if (this.isMobile) this.sidebarOpen = false;
                },
                
                async loadProjects() {
                    try { const d = await window.NuruAPI.getProjects(); this.projects = Array.isArray(d) ? d : []; } catch (e) {}
                },
                
                async loadAllSources() {
                    try {
                        // Get sources from all projects
                        let sources = [];
                        for (const p of this.projects) {
                            try {
                                const d = await window.NuruAPI.getSources(p.uuid);
                                if (d.sources) sources = sources.concat(d.sources);
                            } catch(e) {}
                        }
                        this.allSources = sources;
                    } catch(e) {}
                },
                
                handleTyping() {
                    this.isTyping = true;
                    clearTimeout(this._t); this._t = setTimeout(() => this.isTyping = false, 1000);
                    this.$nextTick(() => { const ta = this.$refs.promptTextarea; if(ta){ ta.style.height='auto'; this.textareaHeight = Math.min(ta.scrollHeight,200); } });
                },
                
                setPrompt(t) { this.newProjectTitle = t; this.$nextTick(() => { const ta=this.$refs.promptTextarea; if(ta){ ta.focus(); ta.setSelectionRange(t.length,t.length); ta.style.height='auto'; this.textareaHeight = Math.min(ta.scrollHeight,200); } }); },
                cycleType() { this.typeIndex = (this.typeIndex+1)%this.projectTypes.length; this.projectType = this.projectTypes[this.typeIndex]; },
                cycleCitation() { this.citationIndex = (this.citationIndex+1)%this.citationStyles.length; this.citationStyle = this.citationStyles[this.citationIndex]; },
                
                async createProjectWithAI() {
                    const topic = this.newProjectTitle.trim();
                    if (!topic || this.isGenerating) return;
                    this.isGenerating = true; this.generationComplete = false; this.generationSteps = [];
                    const typeMap = {'Thesis':'thesis','Dissertation':'dissertation','Literature Review':'literature_review','Lab Report':'lab_report','Case Study':'case_study','Capstone':'capstone'};
                    try {
                        this.generationSteps.push({step:'create',status:'processing',message:'📁 Creating project...'});
                        const project = await window.NuruAPI.createProject({title:topic,type:typeMap[this.projectType]||'thesis',citation_style:this.citationStyle.replace(' ','')});
                        this.generationSteps[0].status='completed'; this.generationSteps[0].message='✅ Project created'; this.generatedProjectUUID = project.uuid;
                        this.generationSteps.push({step:'generate',status:'processing',message:'🤖 AI generating thesis...'});
                        const result = await fetch(`/api/projects/${this.generatedProjectUUID}/generate-complete`,{method:'POST',headers:{'Authorization':`Bearer ${localStorage.getItem('nuruxplore_token')}`,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({topic})});
                        const data = await result.json();
                        if(result.ok&&data.steps){this.generationSteps=data.steps;this.generationComplete=true;this.credits=data.credits_remaining;setTimeout(()=>{window.location.href='/workspace/'+this.generatedProjectUUID;},2000);}
                        else{this.generationSteps.push({step:'error',status:'completed',message:'❌ '+(data.message||'Generation failed.')});}
                    }catch(e){this.generationSteps.push({step:'error',status:'completed',message:'❌ '+e.message});}
                    this.isGenerating=false; await this.loadProjects();
                },
                
                async startFromTemplate(title, type) {
                    this.newProjectTitle = title;
                    this.projectType = type.replace('_',' ').replace(/\b\w/g,l=>l.toUpperCase());
                    this.$nextTick(()=>{const ta=this.$refs.promptTextarea;if(ta)ta.focus();});
                    this.currentPage = 'home';
                },
                
                async deleteProject(uuid) { if(!confirm('Delete this project?'))return; try{await window.NuruAPI.deleteProject(uuid);await this.loadProjects();}catch(e){} },
                async deleteSource(id) { if(!confirm('Delete?'))return; try{await window.NuruAPI.deleteSource(id);await this.loadAllSources();}catch(e){} },
                async uploadLibraryFile(e) { const f=e.target.files[0]; if(!f)return; try{await window.NuruAPI.uploadSource(this.projects[0]?.uuid||'',f);await this.loadAllSources();}catch(err){} },
                
                generateCitation(source, format) {
                    const author = source.author || 'Unknown';
                    const year = source.year || 'n.d.';
                    const title = source.title || 'Untitled';
                    if (format==='apa') return `${author} (${year}). ${title}.`;
                    if (format==='mla') return `${author}. "${title}." ${year}.`;
                    if (format==='chicago') return `${author}. "${title}." ${year}.`;
                    return `${author}, ${year}, ${title}`;
                },
                
                copyCitation(source, format) { navigator.clipboard.writeText(this.generateCitation(source,format)); alert('Copied!'); },
                copyAllCitations() { const text = this.allSources.map(s=>this.generateCitation(s,this.citeFormat)).join('\n\n'); navigator.clipboard.writeText(text); alert('All citations copied!'); },
                
                toggleDarkMode() { this.darkMode=!this.darkMode; localStorage.setItem('nuruxplore_theme',this.darkMode?'dark':'light'); document.documentElement.setAttribute('data-theme',this.darkMode?'dark':'light'); },
                async logout() { try{await window.NuruAPI.logout();}catch(e){} window.NuruAPI.clearToken(); window.location.href='/login'; },
                
                getGradient(id) {
                    const g = ['linear-gradient(135deg,#7c5cff 0%,#3aa0ff 100%)','linear-gradient(135deg,#ff5b8a 0%,#ffd166 100%)','linear-gradient(135deg,#22c55e 0%,#3aa0ff 100%)','linear-gradient(135deg,#0a0a0a 0%,#7c5cff 100%)','linear-gradient(135deg,#ffd166 0%,#ff5b8a 100%)','linear-gradient(135deg,#3aa0ff 0%,#22c55e 100%)','linear-gradient(135deg,#8b5cf6 0%,#ec4899 100%)','linear-gradient(135deg,#f97316 0%,#ef4444 100%)'];
                    return g[(id||0)%g.length];
                }
            }
        }
    </script>
</body>
</html>