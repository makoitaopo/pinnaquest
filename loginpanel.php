<?php
$auth_notice = trim((string)($_GET['notice'] ?? ''));
$auth_notice_type = $_GET['notice_type'] ?? 'info';
if (!in_array($auth_notice_type, ['success', 'error', 'info'], true)) {
    $auth_notice_type = 'info';
}
$auth_notice_icon = $auth_notice_type === 'error'
    ? 'fa-circle-exclamation'
    : ($auth_notice_type === 'success' ? 'fa-circle-check' : 'fa-circle-info');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Login & Register</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;700;800&family=Inter:wght@400;500&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <style>
      :root {
        --primary-green: #1db968;
        --teacher-blue: #4a90e2;
        --accent-orange: #f6ad55;
        --text-dark: #2d3748;
        --text-light: #718096;
        --bg-light: #f9fbfb;
      }
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      body {
        font-family: "Inter", sans-serif;
        background-color: var(--bg-light);
        color: var(--text-dark);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
      }
      .navbar {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 80px;
      }
      .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        font-family: "Lexend", sans-serif;
        font-weight: 700;
        font-size: 24px;
        color: #1a4d36;
      }
      .logo img {
        height: 60px;
        width: auto;
        mix-blend-mode: multiply;
      }
      .auth-container {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px 20px;
      }
      .auth-card {
        background: white;
        padding: 40px;
        border-radius: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        width: 100%;
        max-width: 450px;
        text-align: center;
        border: 1px solid #edf2f7;
      }
      .role-selector {
        display: flex;
        background: #f1f5f9;
        padding: 5px;
        border-radius: 12px;
        margin-bottom: 25px;
      }
      .role-btn {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.3s;
        background: transparent;
        color: var(--text-light);
      }
      .role-btn.active {
        background: white;
        color: var(--text-dark);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      }
      .form-group {
        text-align: left;
        margin-bottom: 15px;
      }
      .form-row {
        display: flex;
        gap: 12px;
      }
      .form-row .form-group {
        flex: 1;
      }
      .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9rem;
      }
      .form-group input {
        width: 100%;
        padding: 12px;
        border: 2px solid #edf2f7;
        border-radius: 10px;
        outline: none;
      }
      .password-wrapper {
        position: relative;
      }
      .password-wrapper input {
        padding-right: 42px;
      }
      .toggle-password {
        position: absolute;
        top: 50%;
        right: 14px;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--text-light);
        font-size: 16px;
        user-select: none;
        transition: color 0.2s;
      }
      .toggle-password:hover {
        color: var(--primary-green);
      }
      .auth-btn {
        width: 100%;
        padding: 14px;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        color: white;
        cursor: pointer;
        margin-top: 10px;
        transition: 0.3s;
      }
      .btn-student {
        background: var(--primary-green);
      }
      .btn-teacher {
        background: var(--teacher-blue);
      }
      .hidden {
        display: none;
      }
      .pq-auth-notice {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 20;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        width: min(390px, calc(100vw - 32px));
        padding: 15px 16px;
        color: #fff;
        background: linear-gradient(135deg, #173b2a, #1a4d36);
        border: 1px solid rgba(255,255,255,.28);
        border-left: 5px solid #f6ad55;
        border-radius: 16px;
        box-shadow: 0 14px 35px rgba(26, 77, 54, .24);
        font-size: .9rem;
        line-height: 1.45;
        animation: pqNoticeIn .35s cubic-bezier(.34,1.56,.64,1);
      }
      .pq-auth-notice.error { border-left-color: #ff7b72; }
      .pq-auth-notice.success { border-left-color: #67e8a5; }
      .pq-auth-notice i {
        flex: 0 0 auto;
        margin-top: 2px;
        color: #f6ad55;
        font-size: 1.15rem;
      }
      .pq-auth-notice.error i { color: #ffaaa4; }
      .pq-auth-notice.success i { color: #67e8a5; }
      .pq-auth-notice-text { flex: 1; font-weight: 600; }
      .pq-auth-notice-close {
        flex: 0 0 auto;
        padding: 0;
        color: rgba(255,255,255,.72);
        background: transparent;
        border: 0;
        cursor: pointer;
        font-size: 1rem;
      }
      .pq-auth-notice-close:hover { color: #fff; }
      .pq-auth-notice.is-closing {
        animation: pqNoticeOut .22s ease forwards;
      }
      @keyframes pqNoticeIn {
        from { opacity: 0; transform: translateY(-14px) translateX(10px); }
        to { opacity: 1; transform: translateY(0) translateX(0); }
      }
      @keyframes pqNoticeOut {
        to { opacity: 0; transform: translateY(-10px) translateX(10px); }
      }
      .switch-text {
        margin-top: 20px;
        font-size: 0.9rem;
        color: var(--text-light);
      }
      .switch-text a {
        color: var(--primary-green);
        font-weight: 700;
        text-decoration: none;
      }

      #privacy-consent-group {
        margin-top: 15px;
        padding: 12px 14px;
        background: #f8fafc;
        border: 1.5px solid #edf2f7;
        border-radius: 10px;
      }
      #privacy-consent-group a:hover {
        text-decoration: underline;
      }

      /* ── Password requirement checklist ── */
      #password-requirements {
        margin-top: 10px;
        padding: 10px 12px;
        background: #f8fafc;
        border: 1.5px solid #edf2f7;
        border-radius: 10px;
        font-size: 0.78rem;
      }
      #password-requirements ul {
        list-style: none;
        margin-top: 4px;
      }
      #password-requirements li {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 2px 0;
        color: var(--text-light);
        font-weight: 600;
        transition: color 0.2s;
      }
      #password-requirements li i {
        font-size: 0.85rem;
        color: #cbd5e0;
        transition: color 0.2s;
      }
      #password-requirements li.met {
        color: #15803d;
      }
      #password-requirements li.met i {
        color: var(--primary-green);
      }
      #password-requirements li.met i.fa-circle::before {
        content: "\f058"; /* fa-circle-check */
      }
    </style>
  </head>
  <body>
    <?php if ($auth_notice !== ''): ?>
      <div
        class="pq-auth-notice <?php echo htmlspecialchars($auth_notice_type, ENT_QUOTES, 'UTF-8'); ?>"
        id="pq-auth-notice"
        role="alert"
        aria-live="polite"
      >
        <i class="fa-solid <?php echo $auth_notice_icon; ?>" aria-hidden="true"></i>
        <span class="pq-auth-notice-text"><?php echo htmlspecialchars($auth_notice, ENT_QUOTES, 'UTF-8'); ?></span>
        <button class="pq-auth-notice-close" type="button" aria-label="Close notification">
          <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
      </div>
    <?php endif; ?>
    <nav class="navbar">
      <div class="logo">
        <img src="pinnaquest logo.JPG" alt="PinnaQuest Logo" />
      </div>
    </nav>

    <div class="auth-container">
      <div class="auth-card">
        <h2 id="form-title">Welcome Back!</h2>
        <p id="form-subtitle">Choose your role to continue your quest.</p>

        <div class="role-selector">
          <button class="role-btn active" onclick="setRole('student')">
            Student
          </button>
          <button class="role-btn" onclick="setRole('teacher')">Teacher</button>
        </div>

        <!-- Login Form -->
       <form action="login.php" method="POST" id="auth-form" novalidate>
    <div id="register-fields" class="hidden">
        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" id="firstname-input" placeholder="Juan" maxlength="100" />
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" id="lastname-input" placeholder="Dela Cruz" maxlength="100" />
            </div>
        </div>
    </div>

    <div id="login-fields">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="name@example.com" maxlength="254" autocomplete="email" required />
        </div>
        <div class="form-group">
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="passwordInput" placeholder="••••••••" required />
                <span class="toggle-password" id="togglePassword" onclick="togglePasswordVisibility()">
                    <i class="fa-solid fa-eye" id="toggleIcon"></i>
                </span>
            </div>
            <!-- Password requirement checklist — shown only in Register mode -->
            <div id="password-requirements" class="hidden">
                <div style="font-weight:700;color:var(--text-dark);">Password must have:</div>
                <ul>
                    <li id="req-length"><i class="fa-regular fa-circle"></i> At least 8 characters</li>
                    <li id="req-upper"><i class="fa-regular fa-circle"></i> At least 1 uppercase letter</li>
                    <li id="req-number"><i class="fa-regular fa-circle"></i> At least 1 number</li>
                    <li id="req-special"><i class="fa-regular fa-circle"></i> At least 1 special character</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ── Privacy consent checkbox — LILITAW LANG kapag Register mode ── -->
    <div class="form-group hidden" id="privacy-consent-group">
        <label style="display:flex; align-items:flex-start; gap:10px; font-weight:400; cursor:pointer;">
            <input type="checkbox" name="privacy_consent" id="privacy_consent" style="margin-top:3px; width:16px; height:16px; flex-shrink:0; cursor:pointer;">
            <span style="font-size:13px; color:#4a5568; line-height:1.5;">
                I have read and agree to PinnaQuest's
                <a href="privacy_policy.php" target="_blank" style="color:var(--primary-green); font-weight:600;">Data Privacy Notice</a>,
                and I consent to the collection and processing of my personal data
                (including my name, email, and quiz activity) in accordance with the
                <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>,
                for the purpose of providing educational services, tracking learning
                progress, and enabling gamified features such as leaderboards and achievements.
            </span>
        </label>
    </div>

    <input type="hidden" name="role" id="role-input" value="student" />
    <button type="submit" class="auth-btn btn-student" id="main-btn">
        Login as Student
    </button>
</form>

        <div class="switch-text">
          <span id="toggle-msg">Don't have an account?</span>
          <a href="#" id="toggle-link" onclick="toggleMode()"
            >Create one here</a
          >
        </div>
      </div>
    </div>

    <script>
      function togglePasswordVisibility() {
        const input = document.getElementById("passwordInput");
        const icon = document.getElementById("toggleIcon");
        if (input.type === "password") {
          input.type = "text";
          icon.classList.remove("fa-eye");
          icon.classList.add("fa-eye-slash");
        } else {
          input.type = "password";
          icon.classList.remove("fa-eye-slash");
          icon.classList.add("fa-eye");
        }
      }

      let currentRole = "student";
      let isLoginMode = true;

      // ── Password rule checker (mirrors the server-side rule in register.php) ──
      function checkPasswordRules(password) {
        return {
          length: password.length >= 8,
          upper: /[A-Z]/.test(password),
          number: /[0-9]/.test(password),
          special: /[^A-Za-z0-9]/.test(password),
        };
      }

      function allPasswordRulesMet(password) {
        const r = checkPasswordRules(password);
        return r.length && r.upper && r.number && r.special;
      }

      function renderPasswordChecklist() {
        const password = document.getElementById("passwordInput").value;
        const rules = checkPasswordRules(password);
        const map = { length: "req-length", upper: "req-upper", number: "req-number", special: "req-special" };
        Object.keys(map).forEach((key) => {
          const el = document.getElementById(map[key]);
          if (!el) return;
          el.classList.toggle("met", rules[key]);
        });
      }

      document.getElementById("passwordInput").addEventListener("input", renderPasswordChecklist);

      function showAuthNotice(message, type = "error") {
        const existing = document.getElementById("pq-auth-notice");
        if (existing) existing.remove();

        const notice = document.createElement("div");
        notice.id = "pq-auth-notice";
        notice.className = `pq-auth-notice ${type}`;
        notice.setAttribute("role", "alert");
        notice.setAttribute("aria-live", "polite");
        const icon = type === "success" ? "fa-circle-check" : "fa-circle-exclamation";
        notice.innerHTML = `
          <i class="fa-solid ${icon}" aria-hidden="true"></i>
          <span class="pq-auth-notice-text"></span>
          <button class="pq-auth-notice-close" type="button" aria-label="Close notification">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
          </button>`;
        notice.querySelector(".pq-auth-notice-text").textContent = message;
        document.body.appendChild(notice);
        notice.querySelector(".pq-auth-notice-close").addEventListener("click", () => closeAuthNotice(notice));
        setTimeout(() => closeAuthNotice(notice), 5200);
      }

      function closeAuthNotice(notice) {
        if (!notice || notice.classList.contains("is-closing")) return;
        notice.classList.add("is-closing");
        setTimeout(() => notice.remove(), 240);
      }

      const initialNotice = document.getElementById("pq-auth-notice");
      if (initialNotice) {
        initialNotice.querySelector(".pq-auth-notice-close").addEventListener("click", () => closeAuthNotice(initialNotice));
        setTimeout(() => closeAuthNotice(initialNotice), 5200);
      }

      function setRole(role) {
        currentRole = role;
        document.querySelectorAll(".role-btn").forEach((btn) => {
          btn.classList.toggle("active", btn.innerText.toLowerCase() === role);
        });
        document.getElementById("role-input").value = currentRole;
        updateUI();
      }

      function toggleMode() {
        isLoginMode = !isLoginMode;
        updateUI();
      }

     function updateUI() {
    const title = document.getElementById("form-title");
    const mainBtn = document.getElementById("main-btn");
    const regFields = document.getElementById("register-fields");
    const firstNameInput = document.getElementById("firstname-input");
    const lastNameInput = document.getElementById("lastname-input");
    const privacyGroup = document.getElementById("privacy-consent-group");
    const privacyConsent = document.getElementById("privacy_consent");
    const passwordReq = document.getElementById("password-requirements");
    const toggleMsg = document.getElementById("toggle-msg");
    const toggleLink = document.getElementById("toggle-link");
    const authForm = document.getElementById("auth-form");

    if (isLoginMode) {
        title.innerText = "Welcome Back!";
        toggleMsg.innerText = "Don't have an account?";
        toggleLink.innerText = "Create one here";
        regFields.classList.add("hidden");
        privacyGroup.classList.add("hidden");
        passwordReq.classList.add("hidden");
        firstNameInput.required = false;
        lastNameInput.required = false;
        if (privacyConsent) privacyConsent.checked = false;
        authForm.action = "login.php";
    } else {
        title.innerText = "Join PinnaQuest";
        toggleMsg.innerText = "Already a member?";
        toggleLink.innerText = "Login here";
        regFields.classList.remove("hidden");
        privacyGroup.classList.remove("hidden");
        passwordReq.classList.remove("hidden");
        firstNameInput.required = true;
        lastNameInput.required = true;
        authForm.action = "register.php";
        renderPasswordChecklist();
    }

    mainBtn.innerText = `${isLoginMode ? "Login" : "Register"} as ${currentRole.charAt(0).toUpperCase() + currentRole.slice(1)}`;
    mainBtn.className = `auth-btn btn-${currentRole}`;
}
      document.getElementById("auth-form").addEventListener("submit", (event) => {
    const email = document.querySelector('input[name="email"]').value.trim();
    const firstName = document.getElementById("firstname-input").value.trim();
    const lastName = document.getElementById("lastname-input").value.trim();
    const password = document.getElementById("passwordInput").value;
    const privacyConsent = document.getElementById("privacy_consent");

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email.length > 254) {
      event.preventDefault();
      showAuthNotice("Please enter a valid email address.");
      return;
    }
    if (!isLoginMode && (!firstName || !lastName)) {
      event.preventDefault();
      showAuthNotice("Please enter both your first name and last name.");
      return;
    }
    if (!isLoginMode && !privacyConsent.checked) {
      event.preventDefault();
      showAuthNotice("You must agree to the Data Privacy Notice before creating an account.");
      return;
    }
    if (!password) {
      event.preventDefault();
      showAuthNotice("Please enter your password.");
      return;
    }
    // Password strength is only enforced when creating a new account.
    // (The server in register.php double-checks this no matter what.)
    if (!isLoginMode && !allPasswordRulesMet(password)) {
      event.preventDefault();
      showAuthNotice("Password must be at least 8 characters long and include 1 uppercase letter, 1 number, and 1 special character.");
    }
});
    </script>
  </body>
</html>