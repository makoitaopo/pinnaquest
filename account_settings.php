<?php
// account_settings.php — Account management (email + password change)
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: loginpanel.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$role    = $_SESSION['role'] ?? 'student';

$res  = $conn->query("SELECT * FROM users WHERE id=$user_id");
$user = $res->fetch_assoc();

$display_name = !empty($user['display_name']) ? $user['display_name'] : $user['full_name'];
$initial      = strtoupper(mb_substr($display_name, 0, 1));
$email        = $user['email'];

$dashboard = $role === 'teacher' ? 'teacherdashboard.php' : 'studentdashboard.php';
$materials  = $role === 'teacher' ? 'teacher_materials.php' : 'materials.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PinnaQuest | Account Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --brand-green: #1db968;
        --brand-dark:  #14452b;
        --brand-mid:   #1a5c38;
        --brand-light: #f0fff4;
        --sidebar-bg:  #ffffff;
        --text-dark:   #1a202c;
        --text-gray:   #718096;
        --border:      #f1f5f9;
        --bg:          #f8fafc;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Inter", sans-serif; background: var(--bg); display: flex; color: var(--text-dark); min-height: 100vh; }

    /* ── Sidebar ── */
    .sidebar { width: 260px; background: #ffffff; height: 100vh; display: flex; flex-direction: column; padding: 30px 20px; position: fixed; top: 0; left: 0; border-right: 1px solid var(--border); z-index: 1000; }
    .logo-box { margin-bottom: 40px; display: flex; justify-content: center; align-items: center; }
    .logo-box img { width: 180px; height: auto; transition: .3s; cursor: pointer; }
    .logo-box img:hover { transform: scale(1.08); }
    .menu-heading { font-size: 11px; font-weight: 700; color: #cbd5e0; text-transform: uppercase; margin: 20px 0 10px 10px; }
    .nav-link { display: flex; align-items: center; gap: 15px; padding: 14px 18px; text-decoration: none; color: var(--text-gray); font-weight: 500; font-size: 14px; border-radius: 12px; margin-bottom: 5px; transition: .2s; cursor: pointer; }
    .nav-link.active { background-color: var(--brand-green); color: white; }
    .nav-link.active i { color: white !important; }
    .nav-link:hover:not(.active) { background: var(--brand-light); color: var(--brand-green); }
    .nav-link i.fa-house          { color: var(--brand-green); }
    .nav-link i.fa-file-invoice   { color: #3b82f6; }
    .nav-link i.fa-brain          { color: #f59e0b; }
    .nav-link i.fa-bolt-lightning  { color: #6366f1; }
    .nav-link i.fa-trophy          { color: #10b981; }
    .nav-link i.fa-user-astronaut  { color: #3b82f6; }
    .nav-link i.fa-gear            { color: #f59e0b; }
    .persona-link-style { color: #94a3b8 !important; }
    .persona-link-style:hover { background-color: #f0f7ff !important; color: #3b82f6 !important; }
    /* Sidebar footer — pushed to bottom */
    .user-profile-bottom { margin-top: auto; background: #f8fafc; padding: 15px; border-radius: 16px; display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .sidebar-avatar { width: 35px; height: 35px; background: var(--brand-green); border-radius: 8px; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; }
    .user-details h4 { font-size: 13px; font-weight: 700; color: #2d3748; margin: 0; }
    .user-details p  { font-size: 11px; color: var(--text-gray); margin: 0; }
    .logout-link { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-gray); font-size: 13px; font-weight: 600; padding-left: 15px; transition: .2s; margin-top: 4px; }
    .logout-link:hover { color: #e53e3e; }

    /* ── Main ── */
    .main { flex: 1; margin-left: 260px; padding: 40px 60px; }
    .breadcrumb { font-size: 14px; color: var(--text-gray); font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 30px; }

    /* ── Page header ── */
    .page-header { background: white; border-radius: 28px; padding: 35px 40px; margin-bottom: 30px; border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 20px rgba(0,0,0,.03); }
    .page-icon { width: 60px; height: 60px; background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; flex-shrink: 0; }
    .page-header h2 { font-family: "Lexend"; font-size: 26px; font-weight: 800; color: var(--brand-dark); margin-bottom: 4px; }
    .page-header p  { color: var(--text-gray); font-size: 14px; }

    /* ── Settings grid ── */
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .settings-card { background: white; border-radius: 24px; padding: 32px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,.03); }
    .settings-card h3 { font-family: "Lexend"; font-size: 18px; font-weight: 700; color: var(--brand-dark); margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
    .settings-card h3 i { color: var(--brand-green); font-size: 16px; }
    .settings-card p.card-desc { color: var(--text-gray); font-size: 13px; margin-bottom: 24px; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-gray); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
    .form-control { width: 100%; padding: 13px 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-family: "Inter"; font-size: 14px; font-weight: 500; color: var(--text-dark); outline: none; transition: .2s; background: #fafafa; }
    .form-control:focus { border-color: var(--brand-green); background: white; box-shadow: 0 0 0 3px rgba(29,185,104,.1); }
    .btn-save { width: 100%; padding: 14px; background: var(--brand-green); color: white; border: none; border-radius: 12px; font-family: "Lexend"; font-size: 15px; font-weight: 700; cursor: pointer; transition: .2s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px; }
    .btn-save:hover { background: var(--brand-dark); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(29,185,104,.3); }
    .btn-save:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    .msg { font-size: 13px; font-weight: 600; padding: 10px 14px; border-radius: 10px; margin-top: 12px; display: none; }
    .msg.success { background: #f0fff4; color: #1db968; border: 1px solid #bbf7d0; }
    .msg.error   { background: #fff5f5; color: #e53e3e; border: 1px solid #fecaca; }

    /* Profile card */
    .profile-card { background: linear-gradient(135deg, var(--brand-dark), var(--brand-mid)); border-radius: 24px; padding: 32px; color: white; margin-bottom: 24px; display: flex; align-items: center; gap: 20px; }
    .profile-avatar-lg { width: 72px; height: 72px; background: rgba(255,255,255,.15); border: 2px solid rgba(255,255,255,.3); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-family: "Lexend"; font-size: 30px; font-weight: 800; color: white; flex-shrink: 0; }
    .profile-info h3 { font-family: "Lexend"; font-size: 22px; font-weight: 800; margin-bottom: 4px; }
    .profile-info p  { color: rgba(255,255,255,.65); font-size: 14px; font-weight: 500; }
    .profile-badge { margin-left: auto; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); border-radius: 20px; padding: 8px 18px; font-size: 13px; font-weight: 700; color: rgba(255,255,255,.85); text-transform: capitalize; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo-box"><img src="pinnaquest logo.JPG" alt="PinnaQuest"></div>
    <p class="menu-heading">Menu</p>
    <a href="<?php echo $dashboard; ?>" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a>
    <a href="<?php echo $materials; ?>" class="nav-link"><i class="fa-solid fa-file-invoice"></i> Materials</a>
    <?php if ($role === 'student'): ?>
    <a href="quizzes.php"        class="nav-link"><i class="fa-solid fa-brain"></i> Quizzes</a>
    <a href="synchro_portal.php" class="nav-link"><i class="fa-solid fa-bolt-lightning"></i> Synchro-Quiz</a>
    <a href="leaderboard.php"    class="nav-link"><i class="fa-solid fa-trophy"></i> Mission Map</a>
    <a href="studentdashboard.php?openPersona=true" class="nav-link persona-link-style"><i class="fa-solid fa-user-astronaut"></i> Quest Persona</a>
    
    <?php else: ?>
    
    <?php endif; ?>
    <a href="account_settings.php" class="nav-link active"><i class="fa-solid fa-gear"></i> Account Settings</a>
    <div class="user-profile-bottom">
        <div class="sidebar-avatar"><?php echo $initial; ?></div>
        <div class="user-details">
            <h4><?php echo htmlspecialchars($display_name); ?></h4>
            <p><?php echo ucfirst($role); ?></p>
        </div>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main">
    <div class="breadcrumb">
        <i class="fa-solid fa-gear" style="color:#f59e0b"></i>
        <span>Account Settings</span>
    </div>

    <!-- Profile Banner -->
    <div class="profile-card">
        <div class="profile-avatar-lg"><?php echo $initial; ?></div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($display_name); ?></h3>
            <p><?php echo htmlspecialchars($email); ?></p>
        </div>
        <div class="profile-badge"><i class="fa-solid fa-<?php echo $role === 'teacher' ? 'chalkboard-teacher' : 'user-graduate'; ?>"></i> <?php echo ucfirst($role); ?></div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-icon"><i class="fa-solid fa-gear"></i></div>
        <div>
            <h2>Account Settings</h2>
            <p>Manage your email address and password to keep your account secure.</p>
        </div>
    </div>

    <!-- Settings Grid -->
    <div class="settings-grid">

        <!-- Change Email -->
        <div class="settings-card">
            <h3><i class="fa-solid fa-envelope"></i> Change Email</h3>
            <p class="card-desc">Update the email address linked to your account.</p>
            <form id="email-form">
                <div class="form-group">
                    <label>New Email Address</label>
                    <input type="email" name="new_email" class="form-control" placeholder="new@email.com" required>
                </div>
                <div class="form-group">
                    <label>Current Password (to confirm)</label>
                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-save" id="email-btn">
                    <i class="fa-solid fa-floppy-disk"></i> Save Email
                </button>
                <div class="msg" id="email-msg"></div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="settings-card">
            <h3><i class="fa-solid fa-lock"></i> Change Password</h3>
            <p class="card-desc">Use a strong password at least 8 characters long.</p>
            <form id="pw-form">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Min. 8 characters" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn-save" id="pw-btn">
                    <i class="fa-solid fa-key"></i> Update Password
                </button>
                <div class="msg" id="pw-msg"></div>
            </form>
        </div>

    </div>
</div>

<script>
function showMsg(id, text, type) {
    const el = document.getElementById(id);
    el.textContent = text;
    el.className = 'msg ' + type;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 5000);
}

document.getElementById('email-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('email-btn');
    btn.disabled = true;
    const data = new FormData(this);
    data.append('action', 'change_email');
    const res  = await fetch('update_account.php', { method: 'POST', body: data });
    const json = await res.json();
    showMsg('email-msg', json.message || json.error, json.success ? 'success' : 'error');
    if (json.success) this.reset();
    btn.disabled = false;
});

document.getElementById('pw-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('pw-btn');
    btn.disabled = true;
    const data = new FormData(this);
    data.append('action', 'change_password');
    const res  = await fetch('update_account.php', { method: 'POST', body: data });
    const json = await res.json();
    showMsg('pw-msg', json.message || json.error, json.success ? 'success' : 'error');
    if (json.success) this.reset();
    btn.disabled = false;
});
</script>
</body>
</html>
