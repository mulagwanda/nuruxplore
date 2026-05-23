<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing — NuruXplore | AI Academic Writing Plans</title>
    <meta name="description" content="Choose the NuruXplore plan that fits your academic needs. Free, Student, and Scholar plans with credits, export options, and AI-powered writing tools.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { background: #0a0a0a; color: #f5f5f5; font-family: 'Inter', sans-serif; }
        
        .nav-blur {
            background: rgba(10,10,10,0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #ffd166 0%, #ff5b8a 35%, #7c5cff 70%, #3aa0ff 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .plan-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px; padding: 36px;
            transition: all 0.3s ease;
            position: relative;
        }
        .plan-card:hover {
            background: rgba(255,255,255,0.04);
            transform: translateY(-4px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.4);
        }
        .plan-card.featured {
            background: rgba(124,92,255,0.06);
            border-color: rgba(124,92,255,0.3);
            box-shadow: 0 0 60px rgba(124,92,255,0.08);
        }
        .plan-card.featured:hover {
            border-color: rgba(124,92,255,0.5);
            box-shadow: 0 30px 80px rgba(124,92,255,0.15);
        }
        
        .popular-badge {
            position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            color: #fff; font-size: 12px; font-weight: 700;
            padding: 6px 18px; border-radius: 999px;
            white-space: nowrap;
            box-shadow: 0 4px 16px rgba(124,92,255,0.4);
        }
        
        .price-amount {
            font-size: 52px; font-weight: 900; letter-spacing: -0.03em; line-height: 1;
        }
        
        .btn-plan {
            display: block; width: 100%; padding: 14px; border-radius: 12px;
            font-weight: 600; font-size: 15px; text-align: center;
            text-decoration: none; transition: all 0.3s ease;
        }
        .btn-primary-plan {
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            color: #fff; box-shadow: 0 4px 20px rgba(124,92,255,0.3);
        }
        .btn-primary-plan:hover { box-shadow: 0 8px 30px rgba(124,92,255,0.5); transform: translateY(-1px); }
        .btn-outline-plan {
            background: transparent; color: #ccc; border: 1px solid #333;
        }
        .btn-outline-plan:hover { border-color: #7c5cff; color: #fff; }
        
        .check-icon { color: #22c55e; font-weight: 700; }
        
        .faq-item {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 14px; padding: 20px 24px;
            cursor: pointer; transition: all 0.2s ease;
        }
        .faq-item:hover { background: rgba(255,255,255,0.04); }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
        .faq-item.open .faq-answer { max-height: 200px; margin-top: 12px; }
        
        .toggle-bg { background: #1a1a1a; }
        .toggle-dot { background: #fff; transition: transform 0.2s ease; }
        .yearly .toggle-dot { transform: translateX(24px); }
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
                <a href="/#features" class="hover:text-white transition">Features</a>
                <a href="/#how-it-works" class="hover:text-white transition">How It Works</a>
                <a href="/pricing" class="text-white transition">Pricing</a>
            </div>
            <div class="flex items-center gap-3">
                <a href="/login" class="text-sm text-gray-400 hover:text-white transition">Log in</a>
                <a href="/register" class="text-sm px-5 py-2.5 bg-white text-black rounded-full font-semibold hover:bg-gray-200 transition">Sign up free</a>
            </div>
        </div>
    </nav>

    <!-- HEADER -->
    <section class="max-w-7xl mx-auto px-6 pt-20 pb-12 text-center">
        <h1 class="text-5xl md:text-6xl font-black mb-4">
            Simple, <span class="text-gradient">transparent</span> pricing
        </h1>
        <p class="text-gray-400 text-lg mb-10 max-w-xl mx-auto">
            Choose the plan that fits your academic journey. Upgrade anytime.
        </p>
        
        <!-- Monthly/Yearly Toggle -->
        <div class="flex items-center justify-center gap-4 mb-4">
            <span class="text-sm" :class="{ 'text-white': !yearly, 'text-gray-500': yearly }">Monthly</span>
            <button id="billingToggle" class="w-14 h-7 rounded-full toggle-bg flex items-center px-1 cursor-pointer transition" onclick="toggleBilling()">
                <div class="w-5 h-5 rounded-full toggle-dot shadow"></div>
            </button>
            <span class="text-sm" :class="{ 'text-white': yearly, 'text-gray-500': !yearly }">
                Yearly <span class="text-green-400 text-xs font-semibold">Save 20%</span>
            </span>
        </div>
    </section>

    <!-- PLANS -->
    <section class="max-w-6xl mx-auto px-6 pb-24">
        <div class="grid md:grid-cols-3 gap-8">
            
            <!-- FREE -->
            <div class="plan-card">
                <div class="mb-6">
                    <h3 class="text-xl font-bold mb-1">Free</h3>
                    <p class="text-gray-500 text-sm">Get started with basic features</p>
                </div>
                <div class="mb-6">
                    <span class="price-amount">$0</span>
                    <span class="text-gray-500 text-sm">/month</span>
                </div>
                <a href="/register" class="btn-plan btn-outline-plan mb-8">Get Started Free</a>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">10 credits per month</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Basic thesis generation</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">2,000 words per document</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">APA 7 citations</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">1 active project</span>
                    </li>
                    <li class="flex items-start gap-2 text-gray-600">
                        <span>✕</span>
                        <span>PDF/Word export</span>
                    </li>
                    <li class="flex items-start gap-2 text-gray-600">
                        <span>✕</span>
                        <span>Plagiarism checker</span>
                    </li>
                </ul>
            </div>
            
            <!-- STUDENT — Featured -->
            <div class="plan-card featured">
                <div class="popular-badge">Most Popular</div>
                <div class="mb-6">
                    <h3 class="text-xl font-bold mb-1">Student</h3>
                    <p class="text-gray-500 text-sm">For serious academic writers</p>
                </div>
                <div class="mb-6">
                    <span class="price-amount monthly-price">$9.99</span>
                    <span class="price-amount yearly-price" style="display:none;">$7.99</span>
                    <span class="text-gray-500 text-sm">/month</span>
                </div>
                <a href="/register" class="btn-plan btn-primary-plan mb-8">Start Free Trial</a>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400"><strong class="text-white">100 credits</strong> per month</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Unlimited words</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">PDF & Word export</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Citation manager (APA, MLA, Chicago, IEEE)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Unlimited projects</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Version history</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Priority email support</span>
                    </li>
                </ul>
            </div>
            
            <!-- SCHOLAR -->
            <div class="plan-card">
                <div class="mb-6">
                    <h3 class="text-xl font-bold mb-1">Scholar</h3>
                    <p class="text-gray-500 text-sm">For PhD candidates & researchers</p>
                </div>
                <div class="mb-6">
                    <span class="price-amount monthly-price">$19.99</span>
                    <span class="price-amount yearly-price" style="display:none;">$15.99</span>
                    <span class="text-gray-500 text-sm">/month</span>
                </div>
                <a href="/register" class="btn-plan btn-outline-plan mb-8">Start Free Trial</a>
                <ul class="space-y-3 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400"><strong class="text-white">300 credits</strong> per month</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Everything in Student</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Advanced AI model (more context)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Plagiarism checker</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">AI disclosure logs</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Team collaboration (coming soon)</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="check-icon">✓</span>
                        <span class="text-gray-400">Priority support</span>
                    </li>
                </ul>
            </div>
            
        </div>
        
        <!-- Pay-as-you-go -->
        <div class="text-center mt-12 p-8 rounded-2xl bg-white/[0.02] border border-white/5 max-w-md mx-auto">
            <h3 class="font-bold text-lg mb-2">Need a one-time boost?</h3>
            <p class="text-gray-400 text-sm mb-4">Purchase credits without a subscription.</p>
            <p class="text-3xl font-black">$5 <span class="text-sm text-gray-400 font-normal">for 50 credits</span></p>
            <p class="text-gray-500 text-xs mt-2">No monthly commitment. Credits never expire.</p>
        </div>
    </section>

    <!-- COMPARISON TABLE -->
    <section class="border-t border-white/5">
        <div class="max-w-6xl mx-auto px-6 py-24">
            <h2 class="text-3xl font-bold text-center mb-12">Compare plans</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left">
                            <th class="py-4 px-4 text-gray-400 font-medium">Feature</th>
                            <th class="py-4 px-4 text-center">Free</th>
                            <th class="py-4 px-4 text-center">Student</th>
                            <th class="py-4 px-4 text-center">Scholar</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-400">
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Credits/month</td><td class="text-center">10</td><td class="text-center text-white font-semibold">100</td><td class="text-center text-white font-semibold">300</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Word limit</td><td class="text-center">2,000</td><td class="text-center text-white">Unlimited</td><td class="text-center text-white">Unlimited</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Active projects</td><td class="text-center">1</td><td class="text-center text-white">Unlimited</td><td class="text-center text-white">Unlimited</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">PDF export</td><td class="text-center text-red-400">✕</td><td class="text-center text-green-400">✓</td><td class="text-center text-green-400">✓</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Word export</td><td class="text-center text-red-400">✕</td><td class="text-center text-green-400">✓</td><td class="text-center text-green-400">✓</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Citation styles</td><td class="text-center">APA 7</td><td class="text-center text-white">APA, MLA, Chicago, IEEE</td><td class="text-center text-white">APA, MLA, Chicago, IEEE</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Version history</td><td class="text-center text-red-400">✕</td><td class="text-center text-green-400">✓</td><td class="text-center text-green-400">✓</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">Plagiarism checker</td><td class="text-center text-red-400">✕</td><td class="text-center text-red-400">✕</td><td class="text-center text-green-400">✓</td></tr>
                        <tr class="border-b border-white/5"><td class="py-3 px-4">AI model</td><td class="text-center">Standard</td><td class="text-center text-white">Standard</td><td class="text-center text-white">Advanced</td></tr>
                        <tr><td class="py-3 px-4">Support</td><td class="text-center">Community</td><td class="text-center text-white">Priority email</td><td class="text-center text-white">Priority email</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="border-t border-white/5">
        <div class="max-w-3xl mx-auto px-6 py-24">
            <h2 class="text-3xl font-bold text-center mb-12">Frequently asked questions</h2>
            <div class="space-y-4">
                <div class="faq-item" onclick="this.classList.toggle('open')">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">How do credits work?</span>
                        <span class="text-gray-500 text-lg">+</span>
                    </div>
                    <div class="faq-answer text-gray-400 text-sm">Each AI action costs credits: generating a thesis (25 credits), modifying a section (2 credits), asking questions (1 credit). Credits reset monthly on paid plans.</div>
                </div>
                <div class="faq-item" onclick="this.classList.toggle('open')">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Can I cancel anytime?</span>
                        <span class="text-gray-500 text-lg">+</span>
                    </div>
                    <div class="faq-answer text-gray-400 text-sm">Yes! Cancel anytime with one click. Your credits remain until the end of your billing period.</div>
                </div>
                <div class="faq-item" onclick="this.classList.toggle('open')">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Is my data private?</span>
                        <span class="text-gray-500 text-lg">+</span>
                    </div>
                    <div class="faq-answer text-gray-400 text-sm">Absolutely. Your thesis content is encrypted and never shared. We take academic integrity seriously.</div>
                </div>
                <div class="faq-item" onclick="this.classList.toggle('open')">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Can I export my thesis?</span>
                        <span class="text-gray-500 text-lg">+</span>
                    </div>
                    <div class="faq-answer text-gray-400 text-sm">Student and Scholar plans include PDF and Word export with proper formatting, citations, and references.</div>
                </div>
                <div class="faq-item" onclick="this.classList.toggle('open')">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">Do you offer student discounts?</span>
                        <span class="text-gray-500 text-lg">+</span>
                    </div>
                    <div class="faq-answer text-gray-400 text-sm">Yes! The Student plan is already discounted for academic use. Contact us for institutional pricing.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="border-t border-white/5">
        <div class="max-w-3xl mx-auto px-6 py-24 text-center">
            <h2 class="text-4xl font-bold mb-4">Start writing your thesis today</h2>
            <p class="text-gray-400 text-lg mb-8">Free for your first chapter. No credit card required.</p>
            <a href="/register" class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-full font-semibold text-lg hover:shadow-xl hover:shadow-purple-500/25 transition">
                Get started free →
            </a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="border-t border-white/5">
        <div class="max-w-7xl mx-auto px-6 py-12 text-center text-sm text-gray-600">
            © 2026 NuruXplore · Denema Technologies. All rights reserved.
        </div>
    </footer>

    <!-- Billing Toggle Script -->
    <script>
        let yearly = false;
        function toggleBilling() {
            yearly = !yearly;
            const toggle = document.getElementById('billingToggle');
            const monthlyPrices = document.querySelectorAll('.monthly-price');
            const yearlyPrices = document.querySelectorAll('.yearly-price');
            
            if (yearly) {
                toggle.classList.add('yearly');
                monthlyPrices.forEach(el => el.style.display = 'none');
                yearlyPrices.forEach(el => el.style.display = 'inline');
            } else {
                toggle.classList.remove('yearly');
                monthlyPrices.forEach(el => el.style.display = 'inline');
                yearlyPrices.forEach(el => el.style.display = 'none');
            }
        }
    </script>

</body>
</html>