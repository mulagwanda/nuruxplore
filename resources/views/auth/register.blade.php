<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — NuruXplore | AI Academic Writing Assistant</title>
    <meta name="description" content="Create your free NuruXplore account and start writing your thesis with AI. Get free credits on signup.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: #0a0a0a; padding: 40px 20px;
            position: relative; overflow-x: hidden; overflow-y: auto;
        }
        
        body::before {
            content: ''; position: absolute; top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: 
                radial-gradient(40% 40% at 30% 20%, rgba(124,92,255,0.15) 0%, transparent 50%),
                radial-gradient(40% 40% at 70% 60%, rgba(255,91,138,0.1) 0%, transparent 50%),
                radial-gradient(30% 30% at 50% 80%, rgba(58,160,255,0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .auth-card {
            position: relative; z-index: 10; width: 100%; max-width: 460px; margin: auto;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px; padding: 40px; backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        
        .brand-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff; text-decoration: none;
            box-shadow: 0 8px 24px rgba(124,92,255,0.3);
        }
        
        .input-group { margin-bottom: 16px; }
        .input-label { display: block; font-size: 13px; font-weight: 500; color: #aaa; margin-bottom: 6px; }
        
        .input-wrapper { position: relative; }
        .input-field {
            width: 100%; padding: 13px 44px 13px 16px; border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1); font-size: 14px; font-family: inherit;
            background: rgba(255,255,255,0.03); color: #fff; transition: all 0.2s ease;
        }
        .input-field:focus { outline: none; border-color: #7c5cff; box-shadow: 0 0 0 4px rgba(124,92,255,0.1); background: rgba(255,255,255,0.05); }
        .input-field::placeholder { color: #555; }
        .input-field.error { border-color: #ef4444; box-shadow: 0 0 0 4px rgba(239,68,68,0.1); }
        .input-field.success { border-color: #22c55e; }
        
        /* Show/Hide Password Toggle */
        .toggle-password {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #666; cursor: pointer;
            font-size: 16px; padding: 4px; line-height: 1; z-index: 5;
            transition: color 0.15s;
        }
        .toggle-password:hover { color: #aaa; }
        
        .btn-primary {
            width: 100%; padding: 14px;
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            color: #fff; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 600; cursor: pointer; font-family: inherit;
            transition: all 0.3s ease; box-shadow: 0 4px 20px rgba(124,92,255,0.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { box-shadow: 0 8px 30px rgba(124,92,255,0.5); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        
        .btn-ghost {
            width: 100%; padding: 12px; background: transparent; color: #ccc;
            border: 1px solid rgba(255,255,255,0.12); border-radius: 12px;
            font-size: 14px; font-weight: 500; cursor: pointer; font-family: inherit;
            transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-ghost:hover { border-color: rgba(255,255,255,0.3); color: #fff; }
        
        .msg-box { font-size: 13px; margin-bottom: 16px; display: none; padding: 12px 14px; border-radius: 10px; font-weight: 500; }
        .msg-error { color: #fca5a5; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); }
        .msg-success { color: #86efac; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); }
        
        .divider { display: flex; align-items: center; gap: 12px; color: #555; font-size: 12px; margin: 20px 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.08); }
        
        .link { color: #7c5cff; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .link:hover { color: #a78bfa; }
        
        .password-strength { height: 4px; border-radius: 2px; transition: all 0.3s ease; margin-top: 8px; background: #333; }
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #3b82f6; width: 75%; }
        .strength-strong { background: #22c55e; width: 100%; }
        
        .credit-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);
            color: #86efac; font-size: 13px; font-weight: 600;
            padding: 8px 16px; border-radius: 999px; margin-bottom: 20px;
        }
        
        .match-indicator { font-size: 11px; margin-top: 4px; display: none; }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 0.8s linear infinite; display: inline-block; }
    </style>
</head>
<body>

    <div class="auth-card">
        
        <!-- Brand -->
        <div style="text-align:center;margin-bottom:24px;">
            <a href="/" class="brand-icon">✦</a>
            <h1 style="font-size:24px;font-weight:700;color:#fff;margin:16px 0 4px;">Create your account</h1>
            <p style="color:#888;font-size:14px;margin:0;">Start writing with AI — free credits included</p>
        </div>
        
        <!-- Credit Badge -->
        <div style="text-align:center;">
            <div class="credit-badge">🎁 100,000 free credits on signup</div>
        </div>
        
        <!-- Messages -->
        <div id="msgBox" class="msg-box"></div>
        
        <!-- Form -->
        <form id="registerForm">
            
            <!-- Name -->
            <div class="input-group">
                <label class="input-label" for="name">Full Name</label>
                <div class="input-wrapper">
                    <input type="text" id="name" class="input-field" placeholder="John Dennis" required autocomplete="name" autofocus>
                </div>
            </div>
            
            <!-- Email -->
            <div class="input-group">
                <label class="input-label" for="email">Email address</label>
                <div class="input-wrapper">
                    <input type="email" id="email" class="input-field" placeholder="you@example.com" required autocomplete="email">
                </div>
            </div>
            
            <!-- Password -->
            <div class="input-group" style="margin-bottom:8px;">
                <label class="input-label" for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" class="input-field" placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
                    <button type="button" class="toggle-password" data-target="password" title="Show password">👁</button>
                </div>
                <div class="password-strength" id="strengthMeter"></div>
            </div>
            
            <!-- Confirm Password -->
            <div class="input-group" style="margin-bottom:8px;">
                <label class="input-label" for="password_confirmation">Confirm Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password_confirmation" class="input-field" placeholder="Repeat your password" required autocomplete="new-password">
                    <button type="button" class="toggle-password" data-target="password_confirmation" title="Show password">👁</button>
                </div>
                <span id="matchIndicator" class="match-indicator"></span>
            </div>
            
            <!-- Terms -->
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;color:#888;margin-bottom:20px;cursor:pointer;">
                <input type="checkbox" id="termsCheck" style="margin-top:2px;accent-color:#7c5cff;cursor:pointer;" required>
                <span>I agree to the <a href="#" class="link">Terms of Service</a> and <a href="#" class="link">Privacy Policy</a></span>
            </label>
            
            <!-- Submit -->
            <button type="submit" id="submitBtn" class="btn-primary">
                <span id="btnText">Create Account — Get Free Credits</span>
                <span id="btnLoader" style="display:none;">
                    <span class="spinner" style="width:18px;height:18px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;"></span>
                    Creating account...
                </span>
            </button>
        </form>
        
        <!-- Divider -->
        <div class="divider">or sign up with</div>
        
        <!-- Google Signup -->
        <button class="btn-ghost" onclick="alert('Coming soon!')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Continue with Google
        </button>
        
        <!-- Login link -->
        <p style="text-align:center;margin-top:24px;font-size:14px;color:#888;">
            Already have an account? <a href="/login" class="link">Log in →</a>
        </p>
    </div>

    <script>
        // ========================================
        // SHOW/HIDE PASSWORD TOGGLE
        // ========================================
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                if (input.type === 'password') {
                    input.type = 'text';
                    this.textContent = '🙈';
                } else {
                    input.type = 'password';
                    this.textContent = '👁';
                }
            });
        });

        // ========================================
        // PASSWORD STRENGTH METER
        // ========================================
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const meter = document.getElementById('strengthMeter');
            
            if (!password) {
                meter.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            meter.className = 'password-strength';
            if (strength <= 1) meter.classList.add('strength-weak');
            else if (strength === 2) meter.classList.add('strength-fair');
            else if (strength === 3) meter.classList.add('strength-good');
            else meter.classList.add('strength-strong');
        });

        // ========================================
        // PASSWORD MATCH INDICATOR
        // ========================================
        document.getElementById('password_confirmation').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const indicator = document.getElementById('matchIndicator');
            
            if (!confirm) { indicator.style.display = 'none'; return; }
            
            indicator.style.display = 'block';
            if (password === confirm) {
                indicator.textContent = '✓ Passwords match';
                indicator.style.color = '#22c55e';
                this.classList.add('success'); this.classList.remove('error');
            } else {
                indicator.textContent = '✗ Passwords do not match';
                indicator.style.color = '#ef4444';
                this.classList.add('error'); this.classList.remove('success');
            }
        });

        // ========================================
        // FORM SUBMISSION
        // ========================================
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('password_confirmation').value;
            const termsCheck = document.getElementById('termsCheck').checked;
            const msgBox = document.getElementById('msgBox');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            
            msgBox.style.display = 'none'; msgBox.className = 'msg-box';
            
            if (!name || name.length < 2) { showError('Please enter your full name.'); return; }
            if (!email || !email.includes('@')) { showError('Please enter a valid email address.'); return; }
            if (password.length < 8) { showError('Password must be at least 8 characters.'); return; }
            if (password !== passwordConfirmation) { showError('Passwords do not match.'); return; }
            if (!termsCheck) { showError('Please agree to the Terms of Service and Privacy Policy.'); return; }
            
            btnText.style.display = 'none'; btnLoader.style.display = 'flex';
            btnLoader.style.alignItems = 'center'; btnLoader.style.justifyContent = 'center'; btnLoader.style.gap = '8px';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('/api/auth/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ name, email, password, password_confirmation: passwordConfirmation }),
                });
                
                const data = await response.json();
                
                if (response.ok && data.token) {
                    showSuccess('✅ Account created! Redirecting to your dashboard...');
                    localStorage.setItem('nuruxplore_token', data.token);
                    if (data.user) { localStorage.setItem('nuruxplore_user', JSON.stringify(data.user)); }
                    setTimeout(() => { window.location.href = data.redirect || '/dashboard'; }, 800);
                } else {
                    const errorMsg = data.message || data.error || 'Registration failed.';
                    if (errorMsg.includes('email')) {
                        showError('This email is already registered. <a href="/login" class="link" style="color:#7c5cff;">Log in instead?</a>');
                    } else {
                        showError(errorMsg);
                    }
                    resetButton();
                }
            } catch (error) {
                showError('Network error. Please check your connection.');
                resetButton();
            }
        });
        
        function showError(msg) {
            const box = document.getElementById('msgBox');
            box.innerHTML = msg; box.className = 'msg-box msg-error'; box.style.display = 'block';
        }
        function showSuccess(msg) {
            const box = document.getElementById('msgBox');
            box.textContent = msg; box.className = 'msg-box msg-success'; box.style.display = 'block';
        }
        function resetButton() {
            document.getElementById('btnText').style.display = 'inline';
            document.getElementById('btnLoader').style.display = 'none';
            document.getElementById('submitBtn').disabled = false;
        }
    </script>
</body>
</html>