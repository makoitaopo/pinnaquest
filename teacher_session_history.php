<?php
// teacher_session_history.php
// Shows the history of all Synchro-Quiz sessions created by the teacher,
// with student rankings and participant details per session.
session_start();
require_once __DIR__ . '/synchro_identity.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
ensureSynchroIdentitySchema($conn);

$user_initial   = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'T';
$user_full_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Teacher Account';

// ── Fetch all synchro sessions (most recent first) ───────────────────────────
$sessions_res = $conn->query(
    "SELECT s.id, s.room_code, s.title, s.difficulty, s.quiz_type,
            s.item_count, s.timer_mins, s.status, s.created_at,
            tm.title AS material_title,
            COUNT(DISTINCT sp.id) AS participant_count
     FROM synchro_sessions s
     LEFT JOIN teacher_materials tm ON s.material_id = tm.id
     LEFT JOIN synchro_participants sp ON s.id = sp.session_id
     GROUP BY s.id
     ORDER BY s.created_at DESC"
);

$sessions = [];
if ($sessions_res) {
    while ($row = $sessions_res->fetch_assoc()) {
        $sessions[] = $row;
    }
}

// ── For each session, get rankings ──────────────────────────────────────────
$session_rankings = [];
foreach ($sessions as $sess) {
    $sid = intval($sess['id']);
    $rank_res = $conn->query(
        "SELECT nickname, avatar_key, total_score, correct_answers, streak
         FROM synchro_scores
         WHERE session_id = $sid
         ORDER BY total_score DESC"
    );
    $rankings = [];
    if ($rank_res) {
        while ($r = $rank_res->fetch_assoc()) {
            $rankings[] = $r;
        }
    }
    $session_rankings[$sid] = $rankings;
}

$avatars = [
    'gamer_girl' => '1a.JPG', 'blue_robot'  => '2a.JPG',
    'gorilla_vr' => '3a.JPG', 'grey_cat'    => '4a.JPG',
    'monkey_cap' => '5a.JPG', 'astronaut'   => '6a.JPG',
    'bear_angry' => '7a.JPG', 'bear_bee'    => '8a.JPG',
];

$difficulty_colors = [
    'easy'   => ['bg' => '#dcfce7', 'text' => '#15803d', 'dot' => '#22c55e'],
    'medium' => ['bg' => '#fef9c3', 'text' => '#854d0e', 'dot' => '#eab308'],
    'hard'   => ['bg' => '#fee2e2', 'text' => '#991b1b', 'dot' => '#ef4444'],
];
$status_colors = [
    'waiting'  => ['bg' => '#f0f9ff', 'text' => '#0369a1'],
    'started'  => ['bg' => '#fef9c3', 'text' => '#854d0e'],
    'finished' => ['bg' => '#f0fdf4', 'text' => '#15803d'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>PinnaQuest | Session History</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
    :root {
        --brand-green: #1db968;
        --brand-dark-green: #14452b;
        --brand-light: #f0fff4;
        --sidebar-white: #ffffff;
        --text-dark: #1a202c;
        --text-gray: #718096;
        --border-color: #f1f5f9;
        --bg: #fcfdfa;
        --synchro-purple: #6366f1;
        --gold: #f59e0b;
        --surface: #f8fafc;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: "Inter", sans-serif;
        background: var(--bg);
        display: flex;
        color: var(--text-dark);
        min-height: 100vh;
    }

    /* ── SIDEBAR ── */
    .sidebar {
        width: 260px;
        background: var(--sidebar-white);
        height: 100vh;
        display: flex;
        flex-direction: column;
        padding: 30px 20px;
        position: fixed;
        top: 0; left: 0;
        border-right: 1px solid var(--border-color);
        z-index: 1000;
    }
    .logo-box { margin-bottom: 40px; display: flex; justify-content: center; }
    .logo-box img { width: 180px; height: auto; }
    .menu-heading {
        font-size: 11px; font-weight: 700; color: #cbd5e0;
        text-transform: uppercase; margin: 20px 0 10px 10px; letter-spacing: .05em;
    }
    .nav-link {
        display: flex; align-items: center; gap: 15px; padding: 14px 18px;
        text-decoration: none; color: var(--text-gray); font-weight: 500;
        font-size: 14px; border-radius: 12px; margin-bottom: 5px; transition: .2s;
    }
    .nav-link.active { background: var(--brand-green); color: white !important; }
    .nav-link.active i { color: white !important; }
    .nav-link:hover:not(.active) { background: var(--brand-light); color: var(--brand-green); }
    .nav-link i.fa-house { color: var(--brand-green); }
    .nav-link i.fa-file-invoice { color: #3b82f6; }
    .nav-link i.fa-chart-bar { color: #f59e0b; }
    .nav-link i.fa-bolt { color: var(--synchro-purple); }
    .sidebar-footer { margin-top: auto; padding-top: 20px; }
    .user-profile-box {
        display: flex; align-items: center; gap: 12px;
        padding: 15px; background: #f8fafc; border-radius: 16px; margin-bottom: 10px;
    }
    .user-avatar-mini {
        width: 35px; height: 35px; background: var(--brand-green);
        color: white; border-radius: 8px; display: flex;
        align-items: center; justify-content: center; font-weight: 800; font-size: 16px;
    }
    .user-details { display: flex; flex-direction: column; overflow: hidden; }
    .user-name-label { font-size: 13px; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role-label { font-size: 11px; color: var(--text-gray); }
    .logout-link {
        display: flex; align-items: center; gap: 10px; text-decoration: none;
        color: var(--text-gray); font-size: 14px; font-weight: 600;
        padding: 14px 18px; transition: .2s; border-radius: 12px;
    }
    .logout-link:hover { background: #fff5f5; color: #e53e3e; }

    /* ── MAIN ── */
    .main { flex: 1; margin-left: 260px; padding: 40px 50px; }
    .breadcrumb {
        font-size: 14px; color: var(--text-gray); font-weight: 600;
        display: flex; align-items: center; gap: 8px; margin-bottom: 30px;
    }

    /* ── PAGE HEADER ── */
    .page-header {
        background: linear-gradient(135deg, var(--brand-dark-green) 0%, #1a4d2e 100%);
        border-radius: 28px;
        padding: 40px 45px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 35px;
        position: relative;
        overflow: hidden;
    }
    .page-header::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 220px; height: 220px;
        background: rgba(255,255,255,.04);
        border-radius: 50%;
    }
    .page-header::after {
        content: '';
        position: absolute;
        bottom: -60px; right: 80px;
        width: 160px; height: 160px;
        background: rgba(29,185,104,.12);
        border-radius: 50%;
    }
    .page-header-left h1 {
        font-family: 'Lexend'; font-size: 30px; font-weight: 800;
        margin-bottom: 6px; letter-spacing: -.02em;
    }
    .page-header-left p { opacity: .75; font-size: 14px; }
    .header-stats { display: flex; gap: 16px; z-index: 1; }
    .hstat {
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.15);
        border-radius: 16px;
        padding: 14px 22px;
        text-align: center;
        min-width: 100px;
    }
    .hstat .val { font-family: 'Lexend'; font-size: 28px; font-weight: 800; color: #6ee7b7; display: block; }
    .hstat .lbl { font-size: 11px; color: rgba(255,255,255,.6); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

    /* ── SEARCH / FILTER BAR ── */
    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 28px;
        align-items: center;
        flex-wrap: wrap;
    }
    .search-box {
        flex: 1;
        min-width: 220px;
        position: relative;
    }
    .search-box i {
        position: absolute; left: 16px; top: 50%;
        transform: translateY(-50%);
        color: #94a3b8; font-size: 14px;
    }
    .search-box input {
        width: 100%;
        padding: 12px 16px 12px 42px;
        border: 1.5px solid var(--border-color);
        border-radius: 12px;
        font-family: 'Inter'; font-size: 14px;
        outline: none; background: white;
        transition: .2s;
    }
    .search-box input:focus { border-color: var(--brand-green); }
    .filter-pill {
        padding: 10px 18px;
        border: 1.5px solid var(--border-color);
        border-radius: 12px;
        font-size: 13px; font-weight: 700;
        cursor: pointer;
        background: white;
        color: var(--text-gray);
        transition: .2s;
        display: flex; align-items: center; gap: 8px;
    }
    .filter-pill.active { background: var(--brand-green); color: white; border-color: var(--brand-green); }
    .filter-pill:hover:not(.active) { border-color: var(--brand-green); color: var(--brand-green); }

    /* ── SESSION CARDS ── */
    .sessions-list { display: flex; flex-direction: column; gap: 20px; }

    .session-card {
        background: white;
        border-radius: 22px;
        border: 1.5px solid var(--border-color);
        overflow: hidden;
        transition: .25s;
        box-shadow: 0 2px 8px rgba(0,0,0,.03);
    }
    .session-card:hover { border-color: #a7f3d0; box-shadow: 0 8px 24px rgba(29,185,104,.08); }

    /* Card header row */
    .card-header {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 22px 28px;
        cursor: pointer;
        user-select: none;
    }
    .card-header:hover { background: #fafffe; }

    .session-icon {
        width: 52px; height: 52px;
        background: linear-gradient(135deg, var(--synchro-purple), #818cf8);
        border-radius: 15px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; color: white; flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(99,102,241,.25);
    }

    .card-meta { flex: 1; min-width: 0; }
    .card-title {
        font-family: 'Lexend'; font-size: 17px; font-weight: 700;
        color: var(--text-dark); margin-bottom: 4px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .card-subtitle { font-size: 12px; color: var(--text-gray); font-weight: 600; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .card-subtitle span { display: flex; align-items: center; gap: 4px; }

    .card-tags { display: flex; gap: 8px; align-items: center; flex-shrink: 0; flex-wrap: wrap; }
    .tag-pill {
        padding: 5px 12px; border-radius: 20px;
        font-size: 11px; font-weight: 800;
        letter-spacing: .3px; text-transform: uppercase;
    }
    .room-code-badge {
        font-family: 'Lexend'; font-size: 18px; font-weight: 800;
        color: var(--synchro-purple); background: rgba(99,102,241,.08);
        border: 1.5px solid rgba(99,102,241,.2);
        padding: 6px 16px; border-radius: 10px; letter-spacing: 2px;
        flex-shrink: 0;
    }
    .expand-btn {
        width: 34px; height: 34px; border-radius: 10px;
        background: var(--surface); border: none;
        display: flex; align-items: center; justify-content: center;
        color: var(--text-gray); cursor: pointer;
        transition: .2s; flex-shrink: 0; font-size: 14px;
    }
    .expand-btn:hover { background: var(--brand-green); color: white; }
    .expand-btn.open { background: var(--brand-green); color: white; transform: rotate(180deg); }
    .delete-session-btn {
        background: linear-gradient(135deg, #fff1f2, #ffe4e6); color: #be123c;
        border: 1px solid #fecdd3; border-radius: 12px; padding: 9px 13px;
        display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
        box-shadow: 0 3px 8px rgba(190,18,60,.08); transition: .2s;
    }
    .delete-session-btn i { font-size: 12px; }
    .delete-session-btn:hover {
        background: linear-gradient(135deg, #e11d48, #be123c);
        border-color: #be123c; color: white; transform: translateY(-1px);
        box-shadow: 0 7px 16px rgba(190,18,60,.2);
    }

    /* In-app notifications: avoids browser "localhost says" dialogs. */
    .pq-toast {
        position: fixed; right: 24px; bottom: 24px; z-index: 5000;
        min-width: 300px; max-width: 420px; padding: 15px 17px;
        border-radius: 16px; color: #fff; display: flex; align-items: flex-start;
        gap: 11px; box-shadow: 0 14px 34px rgba(15,23,42,.2);
        transform: translateY(24px); opacity: 0; pointer-events: none;
        transition: .25s ease; font-size: 13px; font-weight: 700;
    }
    .pq-toast.show { transform: translateY(0); opacity: 1; pointer-events: auto; }
    .pq-toast.success { background: linear-gradient(135deg, #15803d, #22c55e); }
    .pq-toast.error { background: linear-gradient(135deg, #be123c, #ef4444); }
    .pq-toast i { font-size: 18px; margin-top: 1px; }
    .pq-toast span { flex: 1; line-height: 1.45; }
    .pq-toast button { border: 0; background: transparent; color: rgba(255,255,255,.8); cursor: pointer; font-size: 18px; line-height: 1; }

    .pq-confirm-backdrop {
        position: fixed; inset: 0; z-index: 4900; display: none;
        align-items: center; justify-content: center; padding: 20px;
        background: rgba(15,23,42,.48); backdrop-filter: blur(4px);
    }
    .pq-confirm-backdrop.show { display: flex; }
    .pq-confirm {
        width: min(100%, 430px); background: #fff; border-radius: 22px;
        padding: 25px; box-shadow: 0 24px 60px rgba(15,23,42,.25);
        animation: pqDialogIn .2s ease-out;
    }
    .pq-confirm-icon {
        width: 48px; height: 48px; border-radius: 15px; display: flex;
        align-items: center; justify-content: center; color: #be123c;
        background: #fff1f2; font-size: 20px; margin-bottom: 14px;
    }
    .pq-confirm h3 { font-family: 'Lexend'; font-size: 19px; color: #1a202c; margin-bottom: 7px; }
    .pq-confirm p { color: #718096; font-size: 13px; line-height: 1.55; margin-bottom: 21px; }
    .pq-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; }
    .pq-confirm-actions button {
        border: 0; border-radius: 11px; padding: 10px 15px; cursor: pointer;
        font: 800 12px 'Inter';
    }
    .pq-confirm-cancel { background: #f1f5f9; color: #475569; }
    .pq-confirm-delete { background: #e11d48; color: #fff; }
    .pq-confirm-delete:hover { background: #be123c; }
    @keyframes pqDialogIn { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

    /* Expandable body */
    .card-body {
        display: none;
        border-top: 1.5px solid var(--border-color);
        padding: 28px;
        background: var(--surface);
        animation: expandIn .25s ease-out;
    }
    .card-body.open { display: block; }
    @keyframes expandIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* ── STATS ROW inside card ── */
    .session-stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 28px;
    }
    .sstat {
        background: white;
        border-radius: 16px;
        padding: 18px 20px;
        border: 1px solid var(--border-color);
        display: flex; align-items: center; gap: 14px;
    }
    .sstat-icon {
        width: 40px; height: 40px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;
    }
    .sstat-text .val { font-family: 'Lexend'; font-size: 22px; font-weight: 800; color: var(--text-dark); display: block; }
    .sstat-text .lbl { font-size: 11px; color: var(--text-gray); font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }

    /* ── RANKINGS TABLE ── */
    .section-title {
        font-family: 'Lexend'; font-size: 15px; font-weight: 800;
        color: var(--brand-dark-green); margin-bottom: 14px;
        display: flex; align-items: center; gap: 8px;
    }
    .rankings-table { width: 100%; border-collapse: collapse; }
    .rankings-table th {
        text-align: left; font-size: 11px; font-weight: 800;
        color: var(--text-gray); text-transform: uppercase; letter-spacing: .5px;
        padding: 10px 14px; border-bottom: 2px solid var(--border-color);
    }
    .rankings-table td {
        padding: 13px 14px; border-bottom: 1px solid var(--border-color);
        font-size: 14px; vertical-align: middle;
    }
    .rankings-table tr:last-child td { border-bottom: none; }
    .rankings-table tr:hover td { background: #f0fff4; }

    .rank-medal { font-size: 20px; }
    .rank-num {
        width: 28px; height: 28px; background: var(--surface);
        border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 13px; color: var(--text-gray);
    }

    .player-cell { display: flex; align-items: center; gap: 10px; }
    .player-av {
        width: 36px; height: 36px; border-radius: 10px;
        object-fit: cover; background: #e2e8f0; flex-shrink: 0;
    }
    .player-av-placeholder {
        width: 36px; height: 36px; border-radius: 10px;
        background: linear-gradient(135deg, #6366f1, #a855f7);
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 13px; color: white; flex-shrink: 0;
    }
    .player-name { font-weight: 700; font-size: 14px; }

    .score-pill {
        background: rgba(245,158,11,.1);
        color: var(--gold);
        border: 1px solid rgba(245,158,11,.25);
        padding: 4px 12px; border-radius: 20px;
        font-weight: 900; font-size: 14px; font-family: 'Lexend';
    }
    .correct-badge-sm {
        background: rgba(16,185,129,.1);
        color: #059669;
        border: 1px solid rgba(16,185,129,.2);
        padding: 3px 10px; border-radius: 20px;
        font-size: 12px; font-weight: 700;
    }
    .streak-fire { font-size: 12px; font-weight: 700; color: #ef4444; }

    /* Empty state */
    .empty-rankings {
        text-align: center; padding: 35px 20px;
        color: var(--text-gray); font-size: 14px;
        background: white; border-radius: 14px; border: 2px dashed var(--border-color);
    }
    .empty-rankings i { font-size: 32px; opacity: .3; margin-bottom: 10px; display: block; }

    /* No sessions empty state */
    .no-sessions {
        text-align: center; padding: 100px 20px;
        background: white; border-radius: 24px;
        border: 2px dashed var(--border-color);
    }
    .no-sessions i { font-size: 48px; color: var(--synchro-purple); opacity: .3; margin-bottom: 20px; display: block; }
    .no-sessions h3 { font-family: 'Lexend'; font-size: 22px; margin-bottom: 8px; color: var(--brand-dark-green); }
    .no-sessions p { color: var(--text-gray); font-size: 14px; }

    /* No-match search/filter notification (TC-61) */
    .no-match-card {
        display: none;
        text-align: center;
        padding: 70px 20px;
        background: white;
        border-radius: 24px;
        border: 2px dashed #c7d2fe;
        animation: expandIn .25s ease-out;
    }
    .no-match-card.show { display: block; }
    .no-match-icon {
        width: 72px; height: 72px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: #f5f3ff;
        color: var(--synchro-purple);
        display: flex; align-items: center; justify-content: center;
        font-size: 28px;
    }
    .no-match-card h3 {
        font-family: 'Lexend'; font-size: 19px; font-weight: 800;
        color: var(--brand-dark-green); margin-bottom: 8px;
    }
    .no-match-card p { color: var(--text-gray); font-size: 13.5px; margin-bottom: 18px; }
    .no-match-term {
        display: inline-block;
        background: #f5f3ff;
        color: var(--synchro-purple);
        border: 1.5px solid rgba(99,102,241,.25);
        padding: 5px 14px;
        border-radius: 20px;
        font-weight: 800;
        font-size: 12.5px;
    }
    .no-match-reset {
        margin-top: 20px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--surface);
        border: 1.5px solid var(--border-color);
        color: var(--text-gray);
        font-weight: 700;
        font-size: 13px;
        padding: 10px 20px;
        border-radius: 12px;
        cursor: pointer;
        transition: .2s;
    }
    .no-match-reset:hover { border-color: var(--synchro-purple); color: var(--synchro-purple); }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
    <div class="logo-box">
        <img src="pinnaquest logo.JPG" alt="PinnaQuest"/>
    </div>
    <p class="menu-heading">Menu</p>
    <nav>
        <a href="teacherdashboard.php" class="nav-link">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>
        <a href="teacher_materials.php" class="nav-link">
            <i class="fa-solid fa-file-invoice"></i> Materials
        </a>
        <a href="teacher_session_history.php" class="nav-link active">
            <i class="fa-solid fa-chart-bar"></i> Session History
        </a>
         <a href="student_progress_report.php" class="nav-link">
                <i class="fa-solid fa-chart-line"></i> Student Progress
        <a href="synchro_manage.php" class="nav-link">
            <i class="fa-solid fa-bolt"></i> Synchro-Quiz
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile-box">
            <div class="user-avatar-mini"><?php echo $user_initial; ?></div>
            <div class="user-details">
                <span class="user-name-label"><?php echo htmlspecialchars($user_full_name); ?></span>
                <span class="user-role-label">Professor</span>
            </div>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<!-- ── MAIN ── -->
<div class="main">
    <div class="breadcrumb">
        <i class="fa-solid fa-chart-bar" style="color:var(--gold)"></i>
        <span>Session History</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <h1>📊 Session History</h1>
            <p>View all Synchro-Quiz sessions, student rankings, and participation records.</p>
        </div>
        <div class="header-stats">
            <div class="hstat">
                <span class="val"><?php echo count($sessions); ?></span>
                <span class="lbl">Sessions</span>
            </div>
            <div class="hstat">
                <?php
                $total_participants = array_sum(array_column($sessions, 'participant_count'));
                ?>
                <span class="val"><?php echo $total_participants; ?></span>
                <span class="lbl">Total Players</span>
            </div>
            <div class="hstat">
                <?php
                $finished_count = count(array_filter($sessions, fn($s) => $s['status'] === 'finished'));
                ?>
                <span class="val"><?php echo $finished_count; ?></span>
                <span class="lbl">Completed</span>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="searchInput" placeholder="Search sessions by title or room code...">
        </div>
        <button class="filter-pill active" onclick="filterSessions('all', this)">All</button>
        <button class="filter-pill" onclick="filterSessions('finished', this)">
            <i class="fa-solid fa-flag-checkered"></i> Completed
        </button>
        <button class="filter-pill" onclick="filterSessions('started', this)">
            <i class="fa-solid fa-bolt"></i> In Progress
        </button>
        <button class="filter-pill" onclick="filterSessions('waiting', this)">
            <i class="fa-solid fa-clock"></i> Waiting
        </button>
    </div>

    <!-- No-match notification (TC-61: search/filter with no matching session) -->
    <div class="no-match-card" id="noMatchCard">
        <div class="no-match-icon"><i class="fa-solid fa-magnifying-glass-minus"></i></div>
        <h3>No matching sessions found</h3>
        <p id="noMatchMessage">We couldn't find any session that matches your search.</p>
        <button type="button" class="no-match-reset" onclick="resetSessionFilters()">
            <i class="fa-solid fa-rotate-left"></i> Clear search
        </button>
    </div>

    <!-- Sessions List -->
    <div class="sessions-list" id="sessionsList">

        <?php if (empty($sessions)): ?>
        <div class="no-sessions">
            <i class="fa-solid fa-bolt"></i>
            <h3>No Synchro-Quiz sessions yet</h3>
            <p>Go to the Synchro-Quiz portal to create and launch your first live session.</p>
        </div>

        <?php else: ?>
        <?php foreach ($sessions as $sess):
            $sid         = intval($sess['id']);
            $rankings    = $session_rankings[$sid] ?? [];
            $diff        = $sess['difficulty'] ?? 'easy';
            $diff_color  = $difficulty_colors[$diff] ?? $difficulty_colors['easy'];
            $status      = $sess['status'] ?? 'waiting';
            $stat_color  = $status_colors[$status] ?? $status_colors['waiting'];
            $quiz_type   = str_replace('_', ' ', ucfirst($sess['quiz_type'] ?? 'multiple choice'));
            $created     = date('M d, Y · h:i A', strtotime($sess['created_at']));
            $pcount      = intval($sess['participant_count']);
            $top_score   = !empty($rankings) ? intval($rankings[0]['total_score']) : 0;
            $avg_correct = !empty($rankings)
                ? round(array_sum(array_column($rankings, 'correct_answers')) / count($rankings), 1)
                : 0;
        ?>
        <div class="session-card" data-status="<?php echo $status; ?>"
             data-title="<?php echo strtolower($sess['title']); ?>"
             data-room="<?php echo strtolower($sess['room_code']); ?>">

            <!-- Clickable header row -->
            <div class="card-header" onclick="toggleCard(<?php echo $sid; ?>)">
                <div class="session-icon">⚡</div>

                <div class="card-meta">
                    <div class="card-title"><?php echo htmlspecialchars($sess['title']); ?></div>
                    <div class="card-subtitle">
                        <span><i class="fa-solid fa-calendar-days"></i> <?php echo $created; ?></span>
                        <span>·</span>
                        <span><i class="fa-solid fa-book"></i> <?php echo htmlspecialchars($sess['material_title'] ?? 'No material'); ?></span>
                        <span>·</span>
                        <span><i class="fa-solid fa-users"></i> <?php echo $pcount; ?> participants</span>
                    </div>
                </div>

            <div class="card-tags">
                    <span class="tag-pill" style="background:<?php echo $diff_color['bg']; ?>;color:<?php echo $diff_color['text']; ?>;">
                        <?php echo strtoupper($diff); ?>
                    </span>
                    <span class="tag-pill" style="background:#f5f3ff;color:#4f46e5;">
                        <?php echo htmlspecialchars($quiz_type); ?>
                    </span>
                    <span class="tag-pill" style="background:<?php echo $stat_color['bg']; ?>;color:<?php echo $stat_color['text']; ?>;">
                        <?php echo strtoupper($status); ?>
                    </span>
                    <div class="room-code-badge"><?php echo htmlspecialchars($sess['room_code']); ?></div>
                    <button class="expand-btn" id="expand-btn-<?php echo $sid; ?>">
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                </div>
                <button type="button" class="delete-session-btn" onclick="deleteSession(event, <?php echo $sid; ?>)">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>

            <!-- Expandable body -->
            <div class="card-body" id="card-body-<?php echo $sid; ?>">

                <!-- Stats Row -->
                <div class="session-stats-row">
                    <div class="sstat">
                        <div class="sstat-icon" style="background:#ede9fe;color:#6366f1;">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="sstat-text">
                            <span class="val"><?php echo $pcount; ?></span>
                            <span class="lbl">Participants</span>
                        </div>
                    </div>
                    <div class="sstat">
                        <div class="sstat-icon" style="background:#fef9c3;color:#ca8a04;">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <div class="sstat-text">
                            <span class="val"><?php echo number_format($top_score); ?></span>
                            <span class="lbl">Top Score</span>
                        </div>
                    </div>
                    <div class="sstat">
                        <div class="sstat-icon" style="background:#dcfce7;color:#16a34a;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="sstat-text">
                            <span class="val"><?php echo $avg_correct; ?></span>
                            <span class="lbl">Avg Correct</span>
                        </div>
                    </div>
                    <div class="sstat">
                        <div class="sstat-icon" style="background:#fee2e2;color:#dc2626;">
                            <i class="fa-solid fa-list-ol"></i>
                        </div>
                        <div class="sstat-text">
                            <span class="val"><?php echo intval($sess['item_count']); ?></span>
                            <span class="lbl">Questions</span>
                        </div>
                    </div>
                </div>

                <!-- Rankings Table -->
                <div class="section-title">
                    <i class="fa-solid fa-ranking-star" style="color:var(--gold)"></i>
                    Student Rankings & Participation
                </div>

                <?php if (empty($rankings)): ?>
                <div class="empty-rankings">
                    <i class="fa-solid fa-ghost"></i>
                    No student scores recorded for this session yet.
                </div>
                <?php else: ?>
                <div style="background:white; border-radius:16px; overflow:hidden; border:1px solid var(--border-color);">
                    <table class="rankings-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">Rank</th>
                                <th>Student</th>
                                <th>Result</th>
                                <th>Correct</th>
                                <th>Out of</th>
                                <th>Accuracy</th>
                                <th>Streak</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $medals = ['🥇', '🥈', '🥉'];
                            foreach ($rankings as $ri => $r):
                                $correct   = intval($r['correct_answers']);
                                $total_q   = intval($sess['item_count']);
                                $accuracy  = $total_q > 0 ? round(($correct / $total_q) * 100) : 0;
                                $streak    = intval($r['streak']);
                                $av_file   = $avatars[$r['avatar_key']] ?? null;
                                $nick_init = strtoupper(substr($r['nickname'], 0, 1));
                            ?>
                            <tr>
                                <td style="text-align:center;">
                                    <?php if ($ri < 3): ?>
                                        <span class="rank-medal"><?php echo $medals[$ri]; ?></span>
                                    <?php else: ?>
                                        <span class="rank-num"><?php echo $ri + 1; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="player-cell">
                                        <?php if ($av_file): ?>
                                            <img class="player-av" src="<?php echo $av_file; ?>"
                                                 alt="<?php echo htmlspecialchars($r['nickname']); ?>"
                                                 onerror="this.style.display='none'">
                                        <?php else: ?>
                                            <div class="player-av-placeholder"><?php echo $nick_init; ?></div>
                                        <?php endif; ?>
                                        <span class="player-name"><?php echo htmlspecialchars($r['nickname']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="score-pill"><?php echo $correct; ?>/<?php echo $total_q; ?> · <?php echo $accuracy; ?>%</span>
                                    <small class="points-note"><?php echo number_format(intval($r['total_score'])); ?> pts</small>
                                </td>
                                <td><span class="correct-badge-sm">✓ <?php echo $correct; ?></span></td>
                                <td style="color:var(--text-gray); font-weight:600;"><?php echo $total_q; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="width:80px;height:7px;background:#f1f5f9;border-radius:10px;overflow:hidden;">
                                            <div style="width:<?php echo $accuracy; ?>%;height:100%;background:<?php echo $accuracy >= 75 ? '#22c55e' : ($accuracy >= 50 ? '#f59e0b' : '#ef4444'); ?>;border-radius:10px;"></div>
                                        </div>
                                        <span style="font-weight:700;font-size:13px;"><?php echo $accuracy; ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($streak >= 2): ?>
                                        <span class="streak-fire">🔥 <?php echo $streak; ?>x</span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e0;font-size:13px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </div><!-- end card-body -->
        </div><!-- end session-card -->
        <?php endforeach; ?>
        <?php endif; ?>

    </div><!-- end sessions-list -->
</div><!-- end main -->

<div class="pq-toast" id="pqToast" role="status" aria-live="polite">
    <i class="fa-solid fa-circle-check" id="pqToastIcon"></i>
    <span id="pqToastText"></span>
    <button type="button" onclick="hidePqToast()" aria-label="Close notification">&times;</button>
</div>
<div class="pq-confirm-backdrop" id="pqConfirmBackdrop" role="dialog" aria-modal="true" aria-labelledby="pqConfirmTitle">
    <div class="pq-confirm">
        <div class="pq-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
        <h3 id="pqConfirmTitle">Delete session history?</h3>
        <p id="pqConfirmText">This will permanently delete the session, rankings, answers, and participation records.</p>
        <div class="pq-confirm-actions">
            <button type="button" class="pq-confirm-cancel" id="pqConfirmCancel">Keep history</button>
            <button type="button" class="pq-confirm-delete" id="pqConfirmDelete">Delete history</button>
        </div>
    </div>
</div>

<script>
// ── Toggle card expand/collapse ────────────────────────────────────────────
function toggleCard(sid) {
    const body = document.getElementById('card-body-' + sid);
    const btn  = document.getElementById('expand-btn-' + sid);
    if (!body || !btn) return;
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
}

// ── Filter by status ──────────────────────────────────────────────────────
function filterSessions(status, el) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    currentFilter = status;
    applyFilters();
}

let currentFilter = 'all';

// ── Search ────────────────────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', applyFilters);

function applyFilters() {
    const query  = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards  = document.querySelectorAll('.session-card');
    const noMatchCard = document.getElementById('noMatchCard');
    const noMatchMsg  = document.getElementById('noMatchMessage');
    let visibleCount = 0;

    cards.forEach(card => {
        const statusMatch = currentFilter === 'all' || card.dataset.status === currentFilter;
        const searchMatch = !query
            || card.dataset.title.includes(query)
            || card.dataset.room.includes(query);
        const isVisible = statusMatch && searchMatch;
        card.style.display = isVisible ? 'block' : 'none';
        if (isVisible) visibleCount++;
    });

    // TC-61: no sessions matched the current search/filter — show a clear,
    // in-theme notification instead of leaving a blank page or throwing an error.
    if (cards.length > 0 && visibleCount === 0) {
        if (noMatchMsg) {
            noMatchMsg.innerHTML = query
                ? `No session title or room code matches <span class="no-match-term">"${escapeHtml(query)}"</span>.`
                : `No sessions match the selected filter.`;
        }
        if (noMatchCard) noMatchCard.classList.add('show');
    } else if (noMatchCard) {
        noMatchCard.classList.remove('show');
    }
}

function resetSessionFilters() {
    document.getElementById('searchInput').value = '';
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    document.querySelector('.filter-pill')?.classList.add('active');
    currentFilter = 'all';
    applyFilters();
}

function escapeHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function deleteSession(event, sessionId) {
    event.stopPropagation();
    const approved = await askDeleteConfirmation();
    if (!approved) return;
    const body = new URLSearchParams({session_id: String(sessionId)});
    try {
        const response = await fetch('delete_session_history.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body
        });
        const rawResponse = await response.text();
        let data;
        try {
            data = JSON.parse(rawResponse);
        } catch (parseError) {
            console.error('Session history delete response:', rawResponse);
            throw new Error('The server returned an invalid response. Please check the database connection.');
        }
        if (!data.success) throw new Error(data.error || 'Delete failed');
        showPqToast('Session history deleted successfully.', 'success');
        setTimeout(() => window.location.reload(), 800);
    } catch (error) {
        showPqToast(error.message || 'Could not delete this session.', 'error');
    }
}

let pqToastTimer;
function showPqToast(message, type = 'success') {
    const toast = document.getElementById('pqToast');
    const icon = document.getElementById('pqToastIcon');
    const text = document.getElementById('pqToastText');
    if (!toast || !icon || !text) return;
    toast.className = `pq-toast ${type} show`;
    icon.className = `fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'}`;
    text.textContent = message;
    clearTimeout(pqToastTimer);
    pqToastTimer = setTimeout(hidePqToast, 5000);
}
function hidePqToast() {
    document.getElementById('pqToast')?.classList.remove('show');
}
function askDeleteConfirmation() {
    return new Promise(resolve => {
        const backdrop = document.getElementById('pqConfirmBackdrop');
        const cancel = document.getElementById('pqConfirmCancel');
        const remove = document.getElementById('pqConfirmDelete');
        if (!backdrop || !cancel || !remove) return resolve(false);
        backdrop.classList.add('show');
        const finish = value => {
            backdrop.classList.remove('show');
            cancel.onclick = null;
            remove.onclick = null;
            backdrop.onclick = null;
            resolve(value);
        };
        cancel.onclick = () => finish(false);
        remove.onclick = () => finish(true);
        backdrop.onclick = event => {
            if (event.target === backdrop) finish(false);
        };
    });
}

// ── Auto-open first card if only one session ──────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.session-card');
    if (cards.length === 1) {
        const sid = cards[0].querySelector('.expand-btn')?.id?.replace('expand-btn-', '');
        if (sid) toggleCard(sid);
    }
});
</script>
</body>
</html>