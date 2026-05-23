<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NuruXplore — AI Academic Writing Assistant | Write Your Thesis Faster</title>
    <meta name="description" content="NuruXplore helps students write theses, dissertations, and research papers with AI. Generate chapters, citations, and references in minutes.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { purple: '#7c5cff', blue: '#3aa0ff', pink: '#ff5b8a', warm: '#ffd166' }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Fraunces', 'Georgia', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        * { scroll-behavior: smooth; }
        body { background: #0a0a0a; color: #f5f5f5; font-family: 'Inter', sans-serif; }
        
        .hero-glow {
            background: 
                radial-gradient(80% 50% at 20% 40%, rgba(124,92,255,0.25) 0%, transparent 60%),
                radial-gradient(60% 40% at 80% 30%, rgba(255,91,138,0.15) 0%, transparent 70%),
                radial-gradient(50% 40% at 50% 80%, rgba(58,160,255,0.1) 0%, transparent 70%);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #ffd166 0%, #ff5b8a 35%, #7c5cff 70%, #3aa0ff 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glass {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(20px);
        }
        
        /* Demo Window */
        .demo-window {
            background: #111111;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0,0,0,0.6), 0 0 120px rgba(124,92,255,0.08);
            width: 100%;
            max-width: 520px;
        }
        .demo-header {
            background: #1a1a1a;
            padding: 14px 18px;
            display: flex; gap: 8px; align-items: center;
            border-bottom: 1px solid #222;
        }
        .demo-dot { width: 11px; height: 11px; border-radius: 50%; }
        .demo-dot.red { background: #ff5f56; } .demo-dot.yellow { background: #ffbd2e; } .demo-dot.green { background: #27c93f; }
        .demo-title { margin-left: 12px; font-size: 11px; color: #666; font-family: 'SF Mono', 'Fira Code', monospace; }
        
        .demo-body {
            padding: 18px 20px;
            font-size: 12.5px;
            line-height: 1.7;
            color: #aaa;
            font-family: 'SF Mono', 'Fira Code', monospace;
            min-height: 340px;
            position: relative;
            overflow: hidden;
        }
        
        .demo-line {
            opacity: 0;
            transform: translateY(8px);
            animation: fadeInLine 0.4s ease forwards;
            padding: 2px 0;
        }
        .demo-line.user { color: #7c5cff; }
        .demo-line.ai { color: #3aa0ff; }
        .demo-line.system { color: #22c55e; }
        .demo-line.muted { color: #555; }
        
        @keyframes fadeInLine {
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }
        .cursor-blink { animation: blink 1s infinite; color: #7c5cff; }
        
        /* Feature cards */
        .feature-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 28px;
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            background: rgba(255,255,255,0.04);
            border-color: rgba(124,92,255,0.3);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(124,92,255,0.08);
        }
        .feature-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            color: #fff; padding: 15px 30px; border-radius: 999px;
            font-weight: 600; font-size: 16px; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.3s ease; border: none; cursor: pointer;
            box-shadow: 0 4px 20px rgba(124,92,255,0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 35px rgba(124,92,255,0.5); }
        
        .btn-ghost {
            background: transparent; color: #ccc; padding: 15px 30px;
            border-radius: 999px; font-weight: 600; font-size: 16px;
            text-decoration: none; border: 1px solid #333;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-ghost:hover { border-color: #7c5cff; color: #fff; }
        
        .testimonial-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 24px;
        }
        
        .nav-blur {
            background: rgba(10,10,10,0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .demo-window { max-width: 100%; }
            .demo-body { min-height: 240px; font-size: 11px; }
        }
    </style>
</head>
<body>

    <!-- NAV -->
    <nav class="nav-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2.5 text-white font-bold text-lg">
                <span style="width:26px;height:26px;border-radius:7px;background:linear-gradient(135deg,#7c5cff,#3aa0ff);display:inline-block;box-shadow:0 4px 12px rgba(124,92,255,0.3);"></span>
                NuruXplore
            </a>
            <div class="hidden md:flex items-center gap-6 text-sm text-gray-400">
                <a href="#features" class="hover:text-white transition">Features</a>
                <a href="#how-it-works" class="hover:text-white transition">How It Works</a>
                <a href="#testimonials" class="hover:text-white transition">Testimonials</a>
                <a href="/pricing" class="hover:text-white transition">Pricing</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="/login" class="text-sm text-gray-400 hover:text-white transition">Log in</a>
                <a href="/register" class="text-sm px-5 py-2.5 bg-white text-black rounded-full font-semibold hover:bg-gray-200 transition">Sign up free</a>
            </div>
        </div>
    </nav>

    <!-- HERO — Split Layout -->
    <section class="hero-glow relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 pt-20 pb-20">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                
                <!-- LEFT: Text Content -->
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/5 border border-white/10 rounded-full text-sm mb-8">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        <span class="text-gray-400">AI-powered academic writing</span>
                    </div>
                    
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black mb-6 leading-tight">
                        Write your thesis<br>
                        with an <span class="text-gradient">AI co-author</span>
                    </h1>
                    
                    <p class="text-lg text-gray-400 mb-8 max-w-lg leading-relaxed">
                        From research question to complete draft — chapters, citations, and references included. Built for students, supervised by you.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 mb-10">
                        <a href="/register" class="btn-primary">
                            Start writing free →
                        </a>
                        <a href="#demo-section" class="btn-ghost">
                            ▶ See how it works
                        </a>
                    </div>
                    
                    <p class="text-sm text-gray-500 mb-4">Trusted by students from</p>
                    <div class="flex flex-wrap gap-6 items-center opacity-50">
                        <span class="text-gray-400 font-semibold text-sm">MIT</span>
                        <span class="text-gray-400 font-semibold text-sm">Stanford</span>
                        <span class="text-gray-400 font-semibold text-sm">Oxford</span>
                        <span class="text-gray-400 font-semibold text-sm">UDSM</span>
                        <span class="text-gray-400 font-semibold text-sm">NUS</span>
                        <span class="text-gray-400 font-semibold text-sm">TU Delft</span>
                    </div>
                </div>
                
                <!-- RIGHT: Animated Demo Window -->
                <div id="demo-section" class="flex justify-center lg:justify-end">
                    <div class="demo-window">
                        <div class="demo-header">
                            <span class="demo-dot red"></span>
                            <span class="demo-dot yellow"></span>
                            <span class="demo-dot green"></span>
                            <span class="demo-title">NuruXplore — Thesis Workspace</span>
                        </div>
                        <div class="demo-body" id="demoBody">
                            <!-- Animated lines injected by JS -->
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section id="features" class="max-w-7xl mx-auto px-6 py-24 border-t border-white/5">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold mb-4">Everything you need to write your thesis</h2>
            <p class="text-gray-400 text-lg">An end-to-end workspace that scaffolds the academic writing process.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(124,92,255,0.15);">✶</div>
                <h3 class="font-semibold text-lg mb-2">AI Outline Generator</h3>
                <p class="text-gray-400 text-sm">Turn a topic into a chapter-by-chapter outline aligned to academic standards.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(255,91,138,0.15);">⌘</div>
                <h3 class="font-semibold text-lg mb-2">Smart Literature Review</h3>
                <p class="text-gray-400 text-sm">Synthesize sources into themes, gaps, and a working bibliography.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(58,160,255,0.15);">▤</div>
                <h3 class="font-semibold text-lg mb-2">Methodology Helper</h3>
                <p class="text-gray-400 text-sm">Pick a method, justify it, and draft procedures with sample-size suggestions.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(255,209,102,0.15);">❝</div>
                <h3 class="font-semibold text-lg mb-2">Citation Autopilot</h3>
                <p class="text-gray-400 text-sm">APA, MLA, Chicago, IEEE — inline citations and references that stay in sync.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(34,197,94,0.15);">⚖</div>
                <h3 class="font-semibold text-lg mb-2">Integrity Guardrails</h3>
                <p class="text-gray-400 text-sm">Plagiarism checks, AI-disclosure logs, and supervisor-ready edit history.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:rgba(124,92,255,0.15);">♥</div>
                <h3 class="font-semibold text-lg mb-2">Export Ready</h3>
                <p class="text-gray-400 text-sm">Download as PDF or Word — formatted and submission-ready.</p>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section id="how-it-works" class="border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6 py-24">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4">How it works</h2>
                <p class="text-gray-400 text-lg">Three simple steps from idea to complete draft.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div style="width:72px;height:72px;border-radius:50%;background:rgba(124,92,255,0.12);display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:20px;">📝</div>
                    <h3 class="font-semibold text-xl mb-2">1. Describe</h3>
                    <p class="text-gray-400 text-sm max-w-xs mx-auto">Type your research topic. Be as specific or casual as you want.</p>
                </div>
                <div class="text-center">
                    <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,91,138,0.12);display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:20px;">🤖</div>
                    <h3 class="font-semibold text-xl mb-2">2. Generate</h3>
                    <p class="text-gray-400 text-sm max-w-xs mx-auto">AI creates a complete thesis with all chapters and citations in minutes.</p>
                </div>
                <div class="text-center">
                    <div style="width:72px;height:72px;border-radius:50%;background:rgba(58,160,255,0.12);display:inline-flex;align-items:center;justify-content:center;font-size:30px;margin-bottom:20px;">📄</div>
                    <h3 class="font-semibold text-xl mb-2">3. Refine & Export</h3>
                    <p class="text-gray-400 text-sm max-w-xs mx-auto">Chat with AI to refine, then export as PDF or Word — ready to submit.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section id="testimonials" class="border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6 py-24">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold mb-4">What students say</h2>
                <p class="text-gray-400 text-lg">Join thousands of students writing better theses faster.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="testimonial-card">
                    <div class="flex items-center gap-1 mb-3 text-yellow-500">★★★★★</div>
                    <p class="text-gray-300 mb-4 text-sm leading-relaxed">"NuruXplore helped me complete my master's thesis in 3 weeks instead of 3 months. The AI understood my topic perfectly."</p>
                    <div class="flex items-center gap-3">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#7c5cff,#3aa0ff);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">AM</div>
                        <div><div class="text-sm font-medium">Anika M.</div><div class="text-xs text-gray-500">MBA, University of Dar es Salaam</div></div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="flex items-center gap-1 mb-3 text-yellow-500">★★★★★</div>
                    <p class="text-gray-300 mb-4 text-sm leading-relaxed">"The citation manager alone is worth it. APA 7 formatting used to take hours — now it's automatic. My supervisor was impressed."</p>
                    <div class="flex items-center gap-3">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#ff5b8a,#ffd166);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">JK</div>
                        <div><div class="text-sm font-medium">James K.</div><div class="text-xs text-gray-500">PhD, University of Nairobi</div></div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="flex items-center gap-1 mb-3 text-yellow-500">★★★★★</div>
                    <p class="text-gray-300 mb-4 text-sm leading-relaxed">"NuruXplore doesn't write FOR you — it writes WITH you. I stayed in control the whole time. Game changer for my dissertation."</p>
                    <div class="flex items-center gap-3">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#22c55e,#3aa0ff);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;">SP</div>
                        <div><div class="text-sm font-medium">Sarah P.</div><div class="text-xs text-gray-500">MSc, London School of Economics</div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="border-t border-white/5">
        <div class="max-w-3xl mx-auto px-6 py-24 text-center">
            <h2 class="text-4xl font-bold mb-4">Ready to write your thesis?</h2>
            <p class="text-gray-400 text-lg mb-8">Free for your first chapter. No credit card required.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/register" class="btn-primary">Start writing free →</a>
                <a href="/pricing" class="btn-ghost">View pricing</a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6 py-12">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="flex items-center gap-2 text-white font-bold mb-3">
                        <span style="width:20px;height:20px;border-radius:5px;background:linear-gradient(135deg,#7c5cff,#3aa0ff);display:inline-block;"></span>
                        NuruXplore
                    </div>
                    <p class="text-gray-500 text-sm">AI academic workspace for students. Draft, structure, and cite your thesis.</p>
                </div>
                <div>
                    <h4 class="text-white font-medium mb-3">Product</h4>
                    <div class="flex flex-col gap-2 text-sm text-gray-400">
                        <a href="#features" class="hover:text-white transition">Features</a>
                        <a href="#how-it-works" class="hover:text-white transition">How It Works</a>
                        <a href="/pricing" class="hover:text-white transition">Pricing</a>
                    </div>
                </div>
                <div>
                    <h4 class="text-white font-medium mb-3">Company</h4>
                    <div class="flex flex-col gap-2 text-sm text-gray-400">
                        <a href="#" class="hover:text-white transition">About</a>
                        <a href="#" class="hover:text-white transition">Contact</a>
                        <a href="#" class="hover:text-white transition">Privacy</a>
                    </div>
                </div>
                <div>
                    <h4 class="text-white font-medium mb-3">Connect</h4>
                    <div class="flex flex-col gap-2 text-sm text-gray-400">
                        <a href="#" class="hover:text-white transition">Twitter</a>
                        <a href="#" class="hover:text-white transition">Discord</a>
                        <a href="#" class="hover:text-white transition">GitHub</a>
                    </div>
                </div>
            </div>
            <div class="border-t border-white/5 pt-8 text-center text-sm text-gray-600">
                © 2026 NuruXplore · Denema Technologies. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- ANIMATED DEMO SCRIPT -->
    <script>
        // Animated demo conversation
        const demoLines = [
            { text: 'You: Write a thesis on mobile banking and financial inclusion in rural Tanzania', type: 'user', delay: 800 },
            { text: '', type: 'system', delay: 400 },
            { text: 'NuruXplore AI: 🎯 Crafting thesis title...', type: 'ai', delay: 600 },
            { text: '✅ Title: "An Examination of Mobile Banking Penetration and Financial Inclusion in Rural Tanzania"', type: 'system', delay: 400 },
            { text: '', type: 'system', delay: 200 },
            { text: 'NuruXplore AI: 📝 Generating complete thesis...', type: 'ai', delay: 500 },
            { text: '📄 Abstract written (312 words)', type: 'muted', delay: 300 },
            { text: '✍️ Introduction drafted (580 words)', type: 'muted', delay: 250 },
            { text: '📚 Literature Review synthesized (720 words)', type: 'muted', delay: 250 },
            { text: '🔬 Methodology outlined (450 words)', type: 'muted', delay: 250 },
            { text: '📊 Results structured (380 words)', type: 'muted', delay: 250 },
            { text: '💡 Discussion written (520 words)', type: 'muted', delay: 250 },
            { text: '🏁 Conclusion finalized (290 words)', type: 'muted', delay: 250 },
            { text: '', type: 'system', delay: 300 },
            { text: '✅ Complete thesis ready — 3,458 words with 24 APA citations', type: 'system', delay: 500 },
            { text: '', type: 'system', delay: 400 },
            { text: 'You: Add more about M-Pesa adoption to the Literature Review', type: 'user', delay: 800 },
            { text: 'NuruXplore AI: ✅ Literature Review updated — added M-Pesa case study (890 words)', type: 'ai', delay: 600 },
            { text: '', type: 'system', delay: 400 },
            { text: 'You: Make the Abstract more concise', type: 'user', delay: 700 },
            { text: 'NuruXplore AI: ✅ Abstract condensed to 220 words', type: 'ai', delay: 500 },
            { text: '', type: 'system', delay: 600 },
            { text: '▌', type: 'ai', delay: 0, cursor: true },
        ];

        const demoBody = document.getElementById('demoBody');
        let cumulativeDelay = 500;

        demoLines.forEach((line, index) => {
            setTimeout(() => {
                const div = document.createElement('div');
                div.className = 'demo-line ' + line.type;
                
                if (line.cursor) {
                    div.innerHTML = line.text + '<span class="cursor-blink">|</span>';
                } else {
                    div.textContent = line.text || '';
                }
                
                demoBody.appendChild(div);
                demoBody.scrollTop = demoBody.scrollHeight;
            }, cumulativeDelay);
            
            cumulativeDelay += line.delay;
        });
    </script>

</body>
</html>