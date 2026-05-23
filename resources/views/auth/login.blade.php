<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — NuruXplore | AI Academic Writing Assistant</title>
    <meta name="description" content="Log in to your NuruXplore workspace and continue writing your thesis with AI.">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0a0a;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Background glow */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(40% 40% at 30% 20%, rgba(124,92,255,0.15) 0%, transparent 50%),
                radial-gradient(40% 40% at 70% 60%, rgba(255,91,138,0.1) 0%, transparent 50%),
                radial-gradient(30% 30% at 50% 80%, rgba(58,160,255,0.08) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .auth-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
        }
        
        .brand-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 20px; color: #fff;
            box-shadow: 0 8px 24px rgba(124,92,255,0.3);
        }
        
        .input-field {
            width: 100%;
            padding: 13px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            font-size: 14px;
            font-family: inherit;
            background: rgba(255,255,255,0.03);
            color: #fff;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .input-field:focus {
            outline: none;
            border-color: #7c5cff;
            box-shadow: 0 0 0 4px rgba(124,92,255,0.1);
            background: rgba(255,255,255,0.05);
        }
        .input-field::placeholder { color: #555; }
        
        .input-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #aaa;
            margin-bottom: 6px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #7c5cff, #3aa0ff);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(124,92,255,0.3);
        }
        .btn-primary:hover { 
            box-shadow: 0 8px 30px rgba(124,92,255,0.5);
            transform: translateY(-1px);
        }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        
        .btn-ghost {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #ccc;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-ghost:hover { border-color: rgba(255,255,255,0.3); color: #fff; }
        
        .msg-box { 
            font-size: 13px; 
            margin-bottom: 16px; 
            display: none; 
            padding: 12px 14px; 
            border-radius: 10px;
            font-weight: 500;
        }
        .msg-error { color: #fca5a5; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); }
        .msg-success { color: #86efac; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2); }
        
        .divider {
            display: flex; align-items: center; gap: 12px;
            color: #555; font-size: 12px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px;
            background: rgba(255,255,255,0.08);
        }
        
        .link { color: #7c5cff; text-decoration: none; font-weight: 500; transition: color 0.2s; }
        .link:hover { color: #a78bfa; }
        
        .checkbox-wrapper {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: #999; cursor: pointer;
        }
        .checkbox-wrapper input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #7c5cff; cursor: pointer;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 0.8s linear infinite; }
    </style>
</head>
<body>

    <div class="auth-card">
        
        <!-- Brand -->
        <div style="text-align:center;margin-bottom:32px;">
            <a href="/" class="brand-icon" style="text-decoration:none;">✦</a>
            <h1 style="font-size:24px;font-weight:700;color:#fff;margin:16px 0 4px;">Welcome back</h1>
            <p style="color:#888;font-size:14px;margin:0;">Log in to your NuruXplore workspace</p>
        </div>
        
        <!-- Messages -->
        <div id="msgBox" class="msg-box"></div>
        
        <!-- Form -->
        <form id="loginForm">
            <div style="margin-bottom:16px;">
                <label class="input-label" for="email">Email address</label>
                <input type="email" id="email" class="input-field" placeholder="you@example.com" required autocomplete="email" autofocus>
            </div>
            
            <div style="margin-bottom:8px;">
                <label class="input-label" for="password">Password</label>
                <input type="password" id="password" class="input-field" placeholder="Enter your password" required autocomplete="current-password">
            </div>
            
            <!-- Remember Me + Forgot Password -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <label class="checkbox-wrapper">
                    <input type="checkbox" id="rememberMe">
                    <span>Remember me</span>
                </label>
                <a href="#" class="link" style="font-size:13px;">Forgot password?</a>
            </div>
            
            <button type="submit" id="submitBtn" class="btn-primary">
                <span id="btnText">Log in</span>
                <span id="btnLoader" style="display:none;">
                    <svg class="spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    </svg>
                    Logging in...
                </span>
            </button>
        </form>
        
        <!-- Divider -->
        <div class="divider" style="margin:20px 0;">or continue with</div>
        
        <!-- Social Login (placeholder) -->
        <button class="btn-ghost" onclick="alert('Coming soon!')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Continue with Google
        </button>
        
        <!-- Sign up link -->
        <p style="text-align:center;margin-top:24px;font-size:14px;color:#888;">
            Don't have an account? <a href="/register" class="link">Create one free →</a>
        </p>
    </div>

    <script>
        // Check for saved email
        const savedEmail = localStorage.getItem('nuruxplore_remembered_email');
        if (savedEmail) {
            document.getElementById('email').value = savedEmail;
            document.getElementById('rememberMe').checked = true;
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const rememberMe = document.getElementById('rememberMe').checked;
            const msgBox = document.getElementById('msgBox');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            
            // Reset
            msgBox.style.display = 'none';
            msgBox.className = 'msg-box';
            
            // Validation
            if (!email) {
                showError('Please enter your email address.');
                return;
            }
            if (!password) {
                showError('Please enter your password.');
                return;
            }
            
            // Loading state
            btnText.style.display = 'none';
            btnLoader.style.display = 'flex';
            btnLoader.style.alignItems = 'center';
            btnLoader.style.justifyContent = 'center';
            btnLoader.style.gap = '8px';
            submitBtn.disabled = true;
            
            try {
                const response = await fetch('/api/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ email, password }),
                });
                
                const data = await response.json();
                
                if (response.ok && data.token) {
                    // Success!
                    showSuccess('✅ Login successful! Redirecting...');
                    
                    // Store token
                    localStorage.setItem('nuruxplore_token', data.token);
                    
                    // Remember me
                    if (rememberMe) {
                        localStorage.setItem('nuruxplore_remembered_email', email);
                    } else {
                        localStorage.removeItem('nuruxplore_remembered_email');
                    }
                    
                    // Redirect
                    setTimeout(() => {
                        window.location.href = data.redirect || '/dashboard';
                    }, 500);
                    
                } else if (response.status === 429) {
                    showError('Too many attempts. Please wait a moment and try again.');
                    resetButton();
                } else {
                    showError(data.message || 'Invalid email or password. Please try again.');
                    resetButton();
                }
            } catch (error) {
                showError('Network error. Please check your connection and try again.');
                resetButton();
            }
        });
        
        function showError(msg) {
            const box = document.getElementById('msgBox');
            box.textContent = msg;
            box.className = 'msg-box msg-error';
            box.style.display = 'block';
        }
        
        function showSuccess(msg) {
            const box = document.getElementById('msgBox');
            box.textContent = msg;
            box.className = 'msg-box msg-success';
            box.style.display = 'block';
        }
        
        function resetButton() {
            document.getElementById('btnText').style.display = 'inline';
            document.getElementById('btnLoader').style.display = 'none';
            document.getElementById('submitBtn').disabled = false;
        }
        
        // Keyboard shortcut: Enter to submit
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>