<?php
// studentdashboard.php — FULLY FUNCTIONAL
session_start();
include('db.php');
require_once __DIR__ . '/xp_config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: loginpanel.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);

// ── Load user ────────────────────────────────────────────────────
$user_res = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user     = $user_res ? $user_res->fetch_assoc() : [];

// The database account is the source of truth. The session role can be stale
// after switching accounts, which used to send a student back to the teacher
// dashboard when returning from a Synchro-Quiz.
$account_role = strtolower(trim((string)($user['role'] ?? '')));
if ($account_role !== 'student') {
    header("Location: teacherdashboard.php");
    exit();
}
$_SESSION['role'] = $account_role;

$display_name = !empty($user['display_name']) ? $user['display_name'] : ($user['full_name'] ?? 'Adventurer');
$initial      = strtoupper(mb_substr($display_name, 0, 1));
$avatar_key   = !empty($user['avatar_key']) ? $user['avatar_key'] : 'default';
$total_xp     = intval($user['xp'] ?? 0);

// Keep session in sync
$_SESSION['user_name'] = $display_name;

// ── Level / XP calculations (300 XP per level) ───────────────────
$xp_per_level = PINNAQUEST_XP_PER_LEVEL;
$level        = max(1, floor($total_xp / $xp_per_level) + 1);
$xp_this_lvl  = $total_xp % $xp_per_level;
$progress_pct = round(($xp_this_lvl / $xp_per_level) * 100);
$xp_needed    = $xp_per_level - $xp_this_lvl;

// ── Achievements ─────────────────────────────────────────────────
$ach_res  = $conn->query("SELECT achievement_key, unlocked_at FROM user_achievements WHERE user_id = $user_id");
$unlocked = [];
while ($row = $ach_res->fetch_assoc()) {
    $unlocked[$row['achievement_key']] = $row['unlocked_at'];
}

$ACHIEVEMENTS = [
    'first_quest'   => ['name' => 'First Quest',    'desc' => 'Complete your first solo quiz',       'icon' => 'fa-scroll',        'color' => '#1db968', 'bg' => '#f0fff4'],
    'synchro_debut' => ['name' => 'Synchro Debut',  'desc' => 'Join your first Synchro-Quiz',        'icon' => 'fa-bolt',          'color' => '#6366f1', 'bg' => '#f5f3ff'],
    'sharp_shooter' => ['name' => 'Sharp Shooter',  'desc' => 'Answer 10 questions correctly',       'icon' => 'fa-crosshairs',    'color' => '#f59e0b', 'bg' => '#fffbeb'],
    'centurion'     => ['name' => 'Centurion',       'desc' => 'Answer 50 questions correctly',       'icon' => 'fa-shield-halved', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
    'xp_warrior'    => ['name' => 'XP Warrior',      'desc' => 'Earn your first 500 XP',              'icon' => 'fa-star',          'color' => '#ef4444', 'bg' => '#fff1f2'],
    'streak_master' => ['name' => 'Streak Master',   'desc' => 'Land a 5-answer streak in Synchro',  'icon' => 'fa-fire',          'color' => '#f97316', 'bg' => '#fff7ed'],
    'legend'        => ['name' => 'Legend',          'desc' => 'Reach Level 5',                      'icon' => 'fa-crown',         'color' => '#eab308', 'bg' => '#fefce8'],
    'perfect_run'   => ['name' => 'Perfect Run',     'desc' => 'Finish a quiz with 100% score',      'icon' => 'fa-circle-check',  'color' => '#10b981', 'bg' => '#ecfdf5'],
];

// ── Top 5 leaderboard (by XP) ────────────────────────────────────
$lb_res = $conn->query(
    "SELECT COALESCE(display_name, full_name) as name, xp, avatar_key
     FROM users WHERE role = 'student' ORDER BY xp DESC LIMIT 5"
);
$leaderboard = [];
$my_rank     = '—';
$rank_ctr    = 0;
while ($lb_row = $lb_res->fetch_assoc()) {
    $rank_ctr++;
    $leaderboard[] = $lb_row;
    if ($lb_row['name'] === $display_name) $my_rank = '#' . $rank_ctr;
}

// ── Solo quiz count ───────────────────────────────────────────────
$qc_res    = $conn->query("SELECT COUNT(*) as cnt FROM solo_quiz_results WHERE user_id = $user_id");
$quiz_count = $qc_res ? intval($qc_res->fetch_assoc()['cnt']) : 0;

// ── Avatar icon helper ───────────────────────────────────────────
function avatarIcon(string $key): string {
    $map = [
        'ninja'  => 'fa-user-ninja',   'robot'  => 'fa-robot',
        'ghost'  => 'fa-ghost',        'astro'  => 'fa-user-astronaut',
        'knight' => 'fa-chess-knight', 'fire'   => 'fa-fire',
        'dragon' => 'fa-dragon',       'cat'    => 'fa-cat',
        'crown'  => 'fa-crown',
    ];
    return isset($map[$key]) ? '<i class="fa-solid '.$map[$key].'"></i>' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PinnaQuest | Student Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --brand-green:#1db968; --brand-dark-green:#1a4d2e;
        --brand-gradient:linear-gradient(90deg,#1db968 0%,#38e08a 60%,#ffffff 100%);
        --soft-green:#f0fff4; --quiz-gold:#ebb412;
        --sidebar-white:#ffffff; --text-dark:#1a202c; --text-gray:#718096;
        --border-color:#f1f5f9; --synchro-purple:#6366f1;
        --icon-materials:#3b82f6; --icon-quizzes:#f59e0b; --icon-leaderboard:#10b981;
    }

    .nav-link i.fa-house        { color:var(--brand-green); }
    .nav-link i.fa-file-invoice { color:var(--icon-materials); }
    .nav-link i.fa-brain        { color:var(--icon-quizzes); }
    .nav-link i.fa-bolt-lightning{ color:var(--synchro-purple); }
    .nav-link i.fa-trophy       { color:var(--icon-leaderboard); }
    .nav-link.active i          { color:white!important; }

    .persona-link-style         { color:#94a3b8!important; }
    .persona-link-style:hover   { background-color:#f0f7ff!important; color:#3b82f6!important; }

    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:"Inter",sans-serif;background:radial-gradient(circle at 10% 10%,rgba(29,185,104,.03) 0%,transparent 40%),radial-gradient(circle at 90% 90%,rgba(255,235,204,.05) 0%,transparent 40%),#fcfdfa;background-attachment:fixed;display:flex;color:var(--text-dark);min-height:100vh;}

    /* ── Sidebar ── */
    .sidebar{width:260px;background:var(--sidebar-white);height:100vh;display:flex;flex-direction:column;padding:30px 20px;position:fixed;top:0;left:0;border-right:1px solid var(--border-color);z-index:1000;}
    .logo-box{margin-bottom:40px;display:flex;justify-content:center;align-items:center;}
    .logo-box img{width:180px;height:auto;transition:.3s;cursor:pointer;}
    .logo-box img:hover{transform:scale(1.08);}
    .menu-heading{font-size:11px;font-weight:700;color:#cbd5e0;text-transform:uppercase;margin:20px 0 10px 10px;}
    .nav-link{display:flex;align-items:center;gap:15px;padding:14px 18px;text-decoration:none;color:var(--text-gray);font-weight:500;font-size:14px;border-radius:12px;margin-bottom:5px;transition:.2s;cursor:pointer;}
    .nav-link.active{background-color:var(--brand-green);color:white;}
    .nav-link:hover:not(.active){background:var(--soft-green);color:var(--brand-green);}

    /* Sidebar footer */
    .user-profile-bottom{margin-top:auto;background:#f8fafc;padding:15px;border-radius:16px;display:flex;align-items:center;gap:12px;}
    .sidebar-avatar{width:35px;height:35px;background:var(--brand-green);border-radius:8px;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;overflow:hidden;}
    .user-details h4{font-size:13px;font-weight:700;color:#2d3748;}
    .user-details p{font-size:11px;color:var(--text-gray);}
    .logout-link{margin-top:12px;display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text-gray);font-size:13px;font-weight:600;padding-left:15px;transition:.2s;}
    .logout-link:hover{color:#e53e3e;}

    /* ── Main ── */
    .main{flex:1;margin-left:260px;padding:30px 50px;}
    .breadcrumb{font-size:14px;color:var(--text-gray);font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:30px;}

    /* ── Quest Log Banner ── */
    .quest-log-card{background:var(--brand-gradient);border-radius:28px;padding:40px;display:flex;align-items:center;gap:30px;margin-bottom:30px;position:relative;box-shadow:0 20px 25px -5px rgba(0,0,0,.1),0 15px 50px -12px rgba(29,185,104,.3);border:1px solid rgba(255,255,255,.2);overflow:hidden;transition:.3s;}
    .avatar-circle{width:100px;height:100px;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.5);border-radius:50%;display:flex;align-items:center;justify-content:center;position:relative;font-size:42px;flex-shrink:0;}
    .avatar-circle .initial-text{font-family:"Lexend";font-size:42px;font-weight:800;color:white;text-transform:uppercase;}
    .avatar-circle .avatar-icon-lg{font-size:38px;color:white;}
    .lvl-tag{position:absolute;bottom:5px;right:-5px;background:#ffb100;color:white;font-size:11px;font-weight:900;padding:4px 8px;border-radius:12px;border:3px solid #1db968;}
    .info-section{flex:1;}
    .quest-title{font-family:"Lexend";color:white;font-size:32px;font-weight:800;margin-bottom:4px;}
    .quest-subtitle{color:rgba(255,255,255,.9);font-size:15px;margin-bottom:25px;}
    .glass-progress-container{background:rgba(0,0,0,.2);backdrop-filter:blur(8px);border-radius:20px;padding:12px 20px;max-width:480px;border:1px solid rgba(255,255,255,.1);}
    .progress-labels{display:flex;justify-content:space-between;margin-bottom:10px;}
    .xp-needed{color:rgba(255,255,255,.7);font-size:11px;font-weight:700;}
    .xp-ratio{color:#fff;font-size:13px;font-weight:800;}
    .progress-track{background:rgba(255,255,255,.15);height:14px;border-radius:10px;overflow:hidden;}
    .progress-thumb{background:linear-gradient(90deg,#fff 0%,#e0e0e0 100%);height:100%;border-radius:10px;box-shadow:0 0 15px rgba(255,255,255,.8);transition:width 1s ease-in-out;}

    .stats-column{display:flex;flex-direction:column;gap:12px;margin-right:20px;z-index:1;}
    .glass-stat-pill{background:rgba(0,0,0,.1);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2);border-radius:18px;padding:10px 15px;display:flex;align-items:center;gap:12px;min-width:150px;}
    .stat-icon{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;}
    .yellow-bg{background:#ffebcc;color:#f6ad55;}
    .stat-text small{display:block;color:rgba(255,255,255,.8)!important;font-size:9px;font-weight:800;}
    .stat-text b{color:white!important;font-size:20px;font-weight:800;}

    /* ── Cards ── */
    .cards-container{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:stretch;height: 500px;}
    .card-link{text-decoration:none;color:inherit;display:flex;align-self:stretch;}
    .action-card{padding:25px;border-radius:24px;transition:.3s cubic-bezier(.175,.885,.32,1.275);cursor:pointer;display:flex;flex-direction:column;width:100%;flex:1;background:white;border:1px solid #f1f5f9;}
    .action-card:hover{transform:translateY(-8px);box-shadow:0 15px 30px rgba(0,0,0,.05);}
    .action-card.quiz-theme{background-color:#fffcf0;border:1px solid #f9f0e4;}
    .action-card.leaderboard-theme{background-color:#f7fdf9;border:1px solid #edf7f1;}
    .action-card.synchro-theme{background-color:#f5f3ff;border:1px solid #ede9fe;}
    .card-icon{width:50px;height:50px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:20px;}
    .icon-gold{background:#fff1de;color:var(--quiz-gold);}
    .icon-green{background:#e8f8f0;color:#16a34a;}
    .icon-purple{background:#ede9fe;color:var(--synchro-purple);}
    .action-card h3{font-family:"Lexend";font-size:20px;margin-bottom:8px;color:var(--brand-dark-green);}
    .action-card p{color:var(--text-gray);font-size:14px;margin-bottom:25px;line-height:1.5;}
    .synchro-input-group{display:flex;gap:8px;margin-top:auto;}
    .synchro-input-group input{flex:1;padding:10px;border-radius:10px;border:2px solid #ddd6fe;outline:none;font-weight:700;text-align:center;text-transform:uppercase;font-family:"Lexend";}
    .btn-synchro{background:var(--synchro-purple);color:white;border:none;padding:10px 15px;border-radius:10px;cursor:pointer;font-weight:700;transition:.2s;}
    .btn-synchro:hover{background:#4f46e5;}
    .card-bottom{margin-top:auto;display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:14px;}

    /* ── Mission Map ── */
    .mission-map-card{border-radius:24px;overflow:hidden;background:linear-gradient(160deg,#06111f 0%,#0a1a10 45%,#10082a 100%);border:1px solid rgba(99,102,241,.18);display:flex;flex-direction:column;}
    .mm-header{padding:16px 20px 12px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
    .mm-header-left h3{font-family:"Lexend";font-size:15px;font-weight:800;color:#fff;display:flex;align-items:center;gap:8px;}
    .mm-header-left h3 i{color:#6366f1;}
    .mm-header-left p{font-size:11px;color:#64748b;font-weight:600;margin-top:2px;}
    .mm-world-badge{font-size:10px;font-weight:800;color:#a78bfa;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.25);padding:4px 10px;border-radius:20px;letter-spacing:.04em;}

    /* Scrollable path area */
    .mm-scroll{flex:1;overflow-y:auto;padding:16px 20px;position:relative;min-height:0;}
    .mm-scroll::-webkit-scrollbar{width:4px;}
    .mm-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:10px;}

    /* Winding connector line */
    .mm-line{position:absolute;left:50%;top:0;bottom:0;width:2px;background:linear-gradient(to bottom,#1db968 0%,#1db968 30%,rgba(99,102,241,.4) 50%,rgba(255,255,255,.06) 100%);transform:translateX(-50%);z-index:0;}

    /* Individual nodes */
    .mm-node{position:relative;z-index:1;display:flex;align-items:center;gap:14px;margin-bottom:6px;}
    .mm-node:nth-child(even){flex-direction:row-reverse;}
    .mm-node:nth-child(even) .mm-node-info{text-align:right;}
    .mm-node:nth-child(even) .mm-stars{justify-content:flex-end;}
    .mm-node:nth-child(even) .mm-xp-badge{margin-left:auto;}

    .mm-icon-wrap{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;border:3px solid transparent;position:relative;transition:.25s;cursor:default;}
    .mm-node:hover .mm-icon-wrap{transform:scale(1.12);}

    /* State: done */
    .mm-node.done .mm-icon-wrap{background:rgba(29,185,104,.15);border-color:#1db968;box-shadow:0 0 18px rgba(29,185,104,.3);}
    .mm-node.done .mm-icon-wrap::after{content:'✓';position:absolute;bottom:-3px;right:-3px;width:17px;height:17px;background:#1db968;border-radius:50%;font-size:9px;color:#fff;font-weight:900;display:flex;align-items:center;justify-content:center;border:2px solid #06111f;}

    /* State: active (current) */
    .mm-node.now .mm-icon-wrap{background:rgba(245,200,66,.15);border-color:#f5c842;box-shadow:0 0 24px rgba(245,200,66,.45);animation:mmPulse 2s ease-in-out infinite;}
    @keyframes mmPulse{0%,100%{box-shadow:0 0 18px rgba(245,200,66,.4);}50%{box-shadow:0 0 36px rgba(245,200,66,.75);}}

    /* State: locked */
    .mm-node.locked .mm-icon-wrap{background:rgba(255,255,255,.03);border-color:rgba(255,255,255,.08);filter:grayscale(.85);opacity:.45;}
    .mm-node.locked .mm-node-name{color:#64748b;}

    /* State: boss */
    .mm-node.boss .mm-icon-wrap{width:60px;height:60px;font-size:26px;background:rgba(99,102,241,.18);border-color:#6366f1;box-shadow:0 0 28px rgba(99,102,241,.45);}

    /* Connector between nodes */
    .mm-connector{height:22px;display:flex;justify-content:center;align-items:center;position:relative;z-index:1;}
    .mm-connector-dot{width:6px;height:6px;border-radius:50%;background:#1db968;}
    .mm-connector-dot.dim{background:rgba(255,255,255,.1);}

    .mm-node-info{flex:1;min-width:0;}
    .mm-node-name{font-size:12px;font-weight:800;color:#e2e8f0;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .mm-node-desc{font-size:10px;color:#64748b;font-weight:700;}
    .mm-stars{display:flex;gap:2px;margin-top:3px;}
    .star{font-size:10px;color:rgba(255,255,255,.12);}
    .star.on{color:#f5c842;}
    .mm-xp-badge{display:inline-block;margin-top:4px;font-size:9px;font-weight:900;background:rgba(245,200,66,.1);color:#f5c842;border:1px solid rgba(245,200,66,.25);padding:2px 7px;border-radius:20px;font-family:"Lexend";}
    .mm-xp-badge.purple{background:rgba(99,102,241,.1);color:#a78bfa;border-color:rgba(99,102,241,.25);}

    /* Active node progress mini-bar */
    .mm-mini-bar-wrap{margin-top:5px;}
    .mm-mini-bar-label{font-size:9px;font-weight:800;color:#64748b;margin-bottom:3px;}
    .mm-mini-bar{background:rgba(255,255,255,.06);height:5px;border-radius:10px;overflow:hidden;width:100%;}
    .mm-mini-bar-fill{height:100%;border-radius:10px;background:linear-gradient(90deg,#f5c842,#f97316);box-shadow:0 0 6px rgba(245,200,66,.5);}
    .mm-mini-count{font-size:9px;color:#f5c842;font-weight:900;margin-top:2px;}

    /* Legend */
    .mm-legend{padding:10px 20px;border-top:1px solid rgba(255,255,255,.06);display:flex;flex-wrap:wrap;gap:12px;flex-shrink:0;}
    .mm-leg-item{display:flex;align-items:center;gap:5px;font-size:10px;color:#64748b;font-weight:700;}
    .mm-leg-dot{width:7px;height:7px;border-radius:50%;}

    /* ── Achievements (inside persona modal) ── */
    .ach-modal-section{margin-top:24px;padding-top:20px;border-top:1px solid #f1f5f9;}
    .ach-modal-section h4{font-family:"Lexend";font-size:16px;font-weight:800;color:var(--brand-dark-green);margin-bottom:6px;display:flex;align-items:center;gap:8px;}
    .ach-unlock-counter{font-size:11px;font-weight:700;color:#94a3b8;margin-bottom:16px;}
    .ach-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .ach-card{border-radius:16px;padding:14px;display:flex;align-items:center;gap:12px;border:1.5px solid;transition:.25s;position:relative;overflow:hidden;}
    .ach-card:hover{transform:translateY(-2px);}
    .ach-card.locked{background:#f8fafc;border-color:#e2e8f0;opacity:.5;filter:grayscale(1);}
    .ach-icon-box{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .ach-info strong{display:block;font-size:12px;font-weight:800;margin-bottom:2px;}
    .ach-info span{font-size:10px;color:var(--text-gray);line-height:1.3;}
    .ach-date{font-size:9px;color:var(--brand-green);font-weight:700;margin-top:3px;}
    /* Shimmer on unlocked badges */
    .ach-card:not(.locked)::before{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent);animation:achShimmer 3.5s ease-in-out infinite;}
    @keyframes achShimmer{0%,70%{left:-60%;}100%{left:120%;}}

    /* ── Persona Modal ── */
    .modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);backdrop-filter:blur(8px);display:none;justify-content:center;align-items:center;z-index:9999;}
    .modal-content{background:white;width:90%;max-width:500px;border-radius:30px;padding:30px;animation:slideUp .3s ease-out;max-height:90vh;overflow-y:auto;}
    @keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}

    /* Avatar Picker */
    .avatar-picker{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:20px;}
    .av-opt{width:56px;height:56px;background:#f8fafc;border:2.5px solid transparent;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;cursor:pointer;transition:.2s;color:#64748b;}
    .av-opt:hover{background:#f0fff4;border-color:#a7f3d0;}
    .av-opt.selected{border-color:var(--brand-green);background:#f0fff4;color:var(--brand-green);}
    .av-opt.initial-av{font-weight:800;font-size:20px;}

    /* Name input */
    .persona-name-input{width:100%;padding:14px;border-radius:12px;border:2px solid #e2e8f0;outline:none;font-family:"Lexend";font-size:16px;font-weight:700;transition:.2s;}
    .persona-name-input:focus{border-color:var(--brand-green);}
    .save-persona-btn{width:100%;background:var(--brand-green);color:white;border:none;padding:16px;border-radius:15px;font-weight:700;cursor:pointer;font-size:16px;font-family:"Lexend";transition:.3s;display:flex;align-items:center;justify-content:center;gap:10px;}
    .save-persona-btn:hover{background:var(--brand-dark-green);}
    .save-persona-btn:disabled{opacity:.7;cursor:not-allowed;}

    /* Toast */
    .toast{position:fixed;bottom:25px;right:25px;background:#1a202c;color:white;padding:14px 22px;border-radius:14px;font-weight:700;font-size:14px;z-index:99999;transform:translateY(80px);opacity:0;transition:.3s;display:flex;align-items:center;gap:10px;}
    .toast.show{transform:translateY(0);opacity:1;}
    .toast.success{background:#1db968;}
    .toast.error{background:#ef4444;}

    /* XP gain flash */
    @keyframes xpFlash{0%{transform:scale(1)}50%{transform:scale(1.08)}100%{transform:scale(1)}}
    .xp-flash{animation:xpFlash .4s ease;}
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
    <div class="logo-box"><img src="pinnaquest logo.JPG" alt="PinnaQuest"></div>
    <p class="menu-heading">Menu</p>
    <a href="studentdashboard.php" class="nav-link active"><i class="fa-solid fa-house"></i> Dashboard</a>
    <a href="materials.php"        class="nav-link"><i class="fa-solid fa-file-invoice"></i> Materials</a>
    <a href="quizzes.php"          class="nav-link"><i class="fa-solid fa-brain"></i> Quizzes</a>
    <a href="synchro_portal.php"   class="nav-link"><i class="fa-solid fa-bolt-lightning"></i> Synchro-Quiz</a>
    <a href="leaderboard.php"      class="nav-link"><i class="fa-solid fa-trophy"></i> Mission Map</a>
    <a href="javascript:void(0)"   class="nav-link persona-link-style" onclick="openPersona()">
        <i class="fa-solid fa-user-astronaut"></i> Quest Persona
        <a href="account_settings.php" class="nav-link"><i class="fa-solid fa-gear" style="color:#f59e0b"></i> Account Settings</a>
    </a>

    <div class="user-profile-bottom">
        <div class="sidebar-avatar" id="sidebar-avatar">
            <?php echo $avatar_key !== 'default' ? avatarIcon($avatar_key) : '<span id="sidebar-initial">'.$initial.'</span>'; ?>
        </div>
        <div class="user-details">
            <h4 id="sidebar-name"><?php echo htmlspecialchars($display_name); ?></h4>
            <p>Student · Lv <?php echo $level; ?></p>
        </div>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
</div>

<!-- ── MAIN ── -->
<div class="main">
    <div class="breadcrumb"><i class="fa-solid fa-grip-lines-vertical" style="color:#cbd5e0"></i> Dashboard</div>

    <!-- Quest Log Banner -->
    <div class="quest-log-card">
        <div class="avatar-circle" id="banner-avatar-circle">
            <?php if ($avatar_key !== 'default'): ?>
                <span class="avatar-icon-lg"><?php echo avatarIcon($avatar_key); ?></span>
            <?php else: ?>
                <span class="initial-text" id="banner-initial"><?php echo $initial; ?></span>
            <?php endif; ?>
            <div class="lvl-tag" id="banner-lvl">L<?php echo $level; ?></div>
        </div>

        <div class="info-section">
            <h1 class="quest-title" id="banner-name">Quest Log: <?php echo htmlspecialchars($display_name); ?></h1>
            <p class="quest-subtitle">Master your subjects and climb the leaderboard!</p>

            <div class="glass-progress-container">
                <div class="progress-labels">
                    <span class="xp-needed" id="xp-label">
                        <?php echo $xp_needed; ?> XP to Level <?php echo $level + 1; ?>
                    </span>
                    <span class="xp-ratio" id="xp-ratio">
                        <?php echo $xp_this_lvl; ?> / <?php echo $xp_per_level; ?>
                    </span>
                </div>
                <div class="progress-track">
                    <div class="progress-thumb" id="xp-bar" style="width:<?php echo $progress_pct; ?>%"></div>
                </div>
            </div>
        </div>

        <div class="stats-column">
            <div class="glass-stat-pill">
                <div class="stat-icon yellow-bg"><i class="fa-solid fa-star"></i></div>
                <div class="stat-text">
                    <small>TOTAL XP</small>
                    <b id="total-xp-display"><?php echo number_format($total_xp); ?></b>
                </div>
            </div>
            <div class="glass-stat-pill">
                <div class="stat-icon" style="background:rgba(255,255,255,.2);color:white;"><i class="fa-solid fa-trophy"></i></div>
                <div class="stat-text">
                    <small></small>
                    <b><?php echo $my_rank; ?></b>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Grid -->
    <div class="cards-container">

        <!-- Take a Quiz -->
        <a href="quizzes.php" class="card-link">
            <div class="action-card quiz-theme">
                <div class="card-icon icon-gold"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <h3>Take a Quiz</h3>
                <p>Enter the Quest Board and practice topics to earn XP and level up.</p>
                <div class="card-bottom" style="color:var(--quiz-gold)">
                    <span><?php echo $quiz_count; ?> Quest<?php echo $quiz_count !== 1 ? 's' : ''; ?> Completed</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </div>
            </div>
        </a>

        <!-- Mission Map (replaces Leaderboard card) -->
        <div class="mission-map-card">
                <div class="mm-header">
                    <div class="mm-header-left">
                        <h3><i class="fa-solid fa-map-location-dot"></i> Mission Map</h3>
                        <p>Your learning journey</p>
                    </div>
                    <div class="mm-world-badge">⚔ WORLD 1</div>
                </div>

                <div class="mm-scroll">
                    <!-- Vertical center line -->
                    <div class="mm-line"></div>

                    <?php
                    // Build mission nodes from achievements
                    $MISSIONS = [
                        [
                            'key'    => 'first_quest',
                            'emoji'  => '📖',
                            'name'   => 'First Quest',
                            'desc'   => 'Complete first solo quiz',
                            'xp'     => '+50 XP',
                            'stars'  => 3,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'synchro_debut',
                            'emoji'  => '⚡',
                            'name'   => 'Synchro Debut',
                            'desc'   => 'Join a live Synchro-Quiz',
                            'xp'     => '+30 XP',
                            'stars'  => 2,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'sharp_shooter',
                            'emoji'  => '🎯',
                            'name'   => 'Sharp Shooter',
                            'desc'   => 'Answer 10 Qs correctly',
                            'xp'     => '+75 XP',
                            'stars'  => 3,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'streak_master',
                            'emoji'  => '🔥',
                            'name'   => 'Streak Master',
                            'desc'   => '5-answer streak in Synchro',
                            'xp'     => '+75 XP',
                            'stars'  => 2,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'centurion',
                            'emoji'  => '🛡️',
                            'name'   => 'Centurion',
                            'desc'   => 'Answer 50 Qs correctly',
                            'xp'     => '+100 XP',
                            'stars'  => 3,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'xp_warrior',
                            'emoji'  => '⭐',
                            'name'   => 'XP Warrior',
                            'desc'   => 'Earn 500 XP total',
                            'xp'     => '+50 XP',
                            'stars'  => 2,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'perfect_run',
                            'emoji'  => '💎',
                            'name'   => 'Perfect Run',
                            'desc'   => 'Finish a quiz with 100%',
                            'xp'     => '+100 XP',
                            'stars'  => 3,
                            'type'   => 'normal',
                        ],
                        [
                            'key'    => 'legend',
                            'emoji'  => '👑',
                            'name'   => 'LEGEND BOSS',
                            'desc'   => 'Reach Level 5',
                            'xp'     => '+200 XP',
                            'stars'  => 3,
                            'type'   => 'boss',
                        ],
                    ];

                    // Find the first unlocked mission to mark the "active" node
                    $found_active = false;
                    foreach ($MISSIONS as $idx => $m) {
                        $is_done   = isset($unlocked[$m['key']]);
                        $is_active = !$is_done && !$found_active;
                        if ($is_active) $found_active = true;

                        $state = $is_done ? 'done' : ($is_active ? 'now' : 'locked');
                        $boss  = $m['type'] === 'boss' ? ' boss' : '';

                        // Stars filled
                        $stars_html = '';
                        for ($s = 1; $s <= 3; $s++) {
                            $on = ($is_done && $s <= $m['stars']) ? ' on' : '';
                            $stars_html .= '<span class="star'.$on.'">★</span>';
                        }

                        $xp_class = $m['type'] === 'boss' ? 'mm-xp-badge purple' : 'mm-xp-badge';

                        echo '<div class="mm-node '.$state.$boss.'">';
                        echo   '<div class="mm-icon-wrap">'.$m['emoji'].'</div>';
                        echo   '<div class="mm-node-info">';
                        echo     '<div class="mm-node-name">'.htmlspecialchars($m['name']).'</div>';
                        echo     '<div class="mm-node-desc">'.htmlspecialchars($m['desc']).'</div>';

                        if ($is_active) {
                            $prog_map = [
                                'sharp_shooter' => [$quiz_count * 2, 10],
                                'centurion'     => [$quiz_count * 2, 50],
                                'xp_warrior'    => [$total_xp, 500],
                                'legend'        => [$level, 5],
                                'first_quest'   => [$quiz_count, 1],
                            ];
                            $cur = 0; $max = 1;
                            if (isset($prog_map[$m['key']])) {
                                [$cur, $max] = $prog_map[$m['key']];
                            }
                            $cur = min($cur, $max);
                            $bar_pct = $max > 0 ? round(($cur/$max)*100) : 0;
                            echo '<div class="mm-mini-bar-wrap">';
                            echo '<div class="mm-mini-bar-label">PROGRESS</div>';
                            echo '<div class="mm-mini-bar"><div class="mm-mini-bar-fill" style="width:'.$bar_pct.'%"></div></div>';
                            echo '<div class="mm-mini-count">'.$cur.' / '.$max.'</div>';
                            echo '</div>';
                        } else {
                            echo '<div class="mm-stars">'.$stars_html.'</div>';
                            if ($is_done) echo '<div class="'.$xp_class.'">'.$m['xp'].'</div>';
                        }

                        echo   '</div>';
                        echo '</div>';

                        // Connector dot between nodes (not after last)
                        if ($idx < count($MISSIONS) - 1) {
                            $dot_dim = ($state === 'locked') ? ' dim' : '';
                            echo '<div class="mm-connector"><div class="mm-connector-dot'.$dot_dim.'"></div></div>';
                        }
                    }
                    ?>
                </div><!-- end mm-scroll -->

                <div class="mm-legend">
                    <div class="mm-leg-item"><div class="mm-leg-dot" style="background:#1db968"></div> Cleared</div>
                    <div class="mm-leg-item"><div class="mm-leg-dot" style="background:#f5c842"></div> Active</div>
                    <div class="mm-leg-item"><div class="mm-leg-dot" style="background:rgba(255,255,255,.15)"></div> Locked</div>
                    <div class="mm-leg-item"><div class="mm-leg-dot" style="background:#6366f1"></div> Boss</div>
                </div>
        </div><!-- end mission map -->

        <!-- Synchro Portal -->
        <div class="action-card synchro-theme" style="grid-column:1 / span 2;flex-direction:column;">
            <div class="card-icon icon-purple"><i class="fa-solid fa-bolt"></i></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;">
                <div>
                    <h3>Synchro-Quiz Portal</h3>
                    <p>Enter the party code from your teacher to join a live quest.</p>
                </div>
                <form action="process_join.php" method="POST" class="synchro-input-group" style="min-width:250px;">
                    <input type="text" name="room_code" placeholder="PQ-000000" maxlength="9" required style="font-family:'Lexend';">
                    <button type="submit" class="btn-synchro">JOIN</button>
                </form>
            </div>
        </div>

        <!-- Quest Persona trigger card -->
        <div class="action-card" onclick="openPersona()" style="grid-column:1 / span 2;flex-direction:row;align-items:center;gap:15px;height:auto;padding:20px 25px;cursor:pointer;">
            <div style="width:50px;height:50px;background:#f0f7ff;border-radius:15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-user-astronaut" style="color:#3b82f6;font-size:20px;"></i>
            </div>
            <div>
                <h3 style="font-family:'Lexend';font-size:16px;margin-bottom:2px;">Quest Persona</h3>
                <p style="font-size:12px;color:#94a3b8;margin:0;">Customize your adventurer's name and avatar</p>
            </div>
            <i class="fa-solid fa-chevron-right" style="margin-left:auto;color:#cbd5e0;"></i>
        </div>

    </div><!-- end .cards-container -->
</div><!-- end .main -->

<!-- ── PERSONA MODAL ── -->
<div id="personaModal" class="modal-overlay">
    <div class="modal-content" style="max-width:560px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-family:'Lexend';font-size:20px;font-weight:800;">
                <i class="fa-solid fa-user-astronaut" style="color:#3b82f6"></i> Edit Quest Persona
            </h3>
            <button onclick="closePersona()" style="background:none;border:none;font-size:24px;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>

        <!-- Avatar picker -->
        <p style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Choose Avatar</p>
        <div class="avatar-picker">
            <!-- 'default' = initials -->
            <div class="av-opt initial-av <?php echo $avatar_key==='default'?'selected':''; ?>"
                 onclick="pickAvatar('default', this)"
                 id="av-default"
                 title="Use initials">
                <span id="modal-initial-preview"><?php echo $initial; ?></span>
            </div>
            <?php
            $icon_opts = [
                'ninja'  => ['fa-user-ninja',    'Ninja'],
                'robot'  => ['fa-robot',          'Robot'],
                'ghost'  => ['fa-ghost',          'Ghost'],
                'astro'  => ['fa-user-astronaut', 'Astronaut'],
                'knight' => ['fa-chess-knight',   'Knight'],
                'fire'   => ['fa-fire',           'Fire'],
                'dragon' => ['fa-dragon',         'Dragon'],
                'cat'    => ['fa-cat',            'Cat'],
                'crown'  => ['fa-crown',          'Crown'],
            ];
            foreach ($icon_opts as $k => [$fa, $label]):
            ?>
            <div class="av-opt <?php echo $avatar_key===$k?'selected':''; ?>"
                 onclick="pickAvatar('<?php echo $k; ?>', this)"
                 title="<?php echo $label; ?>">
                <i class="fa-solid <?php echo $fa; ?>"></i>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Name input -->
        <p style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">Adventurer Name</p>
        <input type="text" id="persona-name-input" class="persona-name-input"
               value="<?php echo htmlspecialchars($display_name); ?>"
               placeholder="Enter your name..." maxlength="30">
        <p id="persona-error" style="color:#ef4444;font-size:12px;margin-top:6px;display:none;"></p>

        <!-- Save button -->
        <button class="save-persona-btn" id="save-persona-btn" onclick="savePersona()" style="margin-top:20px;">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
        </button>

        <!-- Current XP / Level summary inside modal -->
        <div style="margin-top:20px;padding:15px;background:#f8fafc;border-radius:14px;display:flex;gap:20px;justify-content:center;">
            <div style="text-align:center;">
                <div style="font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;">LEVEL</div>
                <div style="font-size:22px;font-weight:800;color:var(--brand-dark-green);" id="modal-level">
                    <?php echo $level; ?>
                </div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;">TOTAL XP</div>
                <div style="font-size:22px;font-weight:800;color:var(--brand-green);" id="modal-xp">
                    <?php echo number_format($total_xp); ?>
                </div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:10px;font-weight:700;color:#94a3b8;margin-bottom:4px;">NEXT LV</div>
                <div style="font-size:22px;font-weight:800;color:var(--quiz-gold);" id="modal-next-xp">
                    <?php echo $xp_needed; ?> XP
                </div>
            </div>
        </div>

        <!-- ══ ACHIEVEMENT BADGES (inside persona modal) ══ -->
        <div class="ach-modal-section">
            <h4>
                <i class="fa-solid fa-medal" style="color:var(--quiz-gold)"></i>
                Achievement Badges
            </h4>
            <?php $unlocked_count = count($unlocked); $total_ach = count($ACHIEVEMENTS); ?>
            <div class="ach-unlock-counter">
                🏅 <?php echo $unlocked_count; ?> / <?php echo $total_ach; ?> unlocked
                <?php if ($unlocked_count === $total_ach): ?>
                  — <span style="color:var(--brand-green);font-weight:800;">All Badges Collected! 🎉</span>
                <?php endif; ?>
            </div>
            <div class="ach-modal-grid">
                <?php foreach ($ACHIEVEMENTS as $key => $ach):
                    $is_unlocked = isset($unlocked[$key]);
                    $date_label  = $is_unlocked ? date('M d, Y', strtotime($unlocked[$key])) : '';
                    $border_col  = $is_unlocked ? $ach['color'] : '#e2e8f0';
                ?>
                <div class="ach-card <?php echo $is_unlocked ? '' : 'locked'; ?>"
                     style="background:<?php echo $is_unlocked ? $ach['bg'] : '#f8fafc'; ?>;border-color:<?php echo $border_col; ?>;">
                    <div class="ach-icon-box" style="background:<?php echo $is_unlocked ? $ach['color'].'22' : '#e2e8f0'; ?>;">
                        <i class="fa-solid <?php echo $ach['icon']; ?>"
                           style="color:<?php echo $is_unlocked ? $ach['color'] : '#94a3b8'; ?>;"></i>
                    </div>
                    <div class="ach-info">
                        <strong style="color:<?php echo $is_unlocked ? '#1a202c' : '#94a3b8'; ?>;">
                            <?php echo $ach['name']; ?>
                        </strong>
                        <span><?php echo $ach['desc']; ?></span>
                        <?php if ($is_unlocked): ?>
                        <div class="ach-date"><i class="fa-solid fa-check-circle"></i> <?php echo $date_label; ?></div>
                        <?php else: ?>
                        <div style="font-size:9px;color:#cbd5e0;font-weight:700;margin-top:3px;">🔒 Not yet unlocked</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- end achievements -->

    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// ── Avatar state ──────────────────────────────────────────────────────────────
let selectedAvatarKey = "<?php echo $avatar_key; ?>";

function pickAvatar(key, el) {
    selectedAvatarKey = key;
    document.querySelectorAll('.av-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
}

// ── Modal open/close ──────────────────────────────────────────────────────────
const modal = document.getElementById('personaModal');
function openPersona() { modal.style.display = 'flex'; }
function closePersona(){ modal.style.display = 'none'; }
window.onclick = e => { if (e.target === modal) closePersona(); };

// Open automatically if redirected from another page
window.onload = () => {
    const p = new URLSearchParams(window.location.search);
    if (p.get('openPersona') === 'true') openPersona();
};

// ── Save Persona (AJAX) ───────────────────────────────────────────────────────
async function savePersona() {
    const nameInput = document.getElementById('persona-name-input');
    const errEl     = document.getElementById('persona-error');
    const btn       = document.getElementById('save-persona-btn');
    const name      = nameInput.value.trim();

    errEl.style.display = 'none';

    if (!name) {
        errEl.innerText = 'Please enter a name.';
        errEl.style.display = 'block';
        return;
    }
    if (name.length > 30) {
        errEl.innerText = 'Name too long (max 30 characters).';
        errEl.style.display = 'block';
        return;
    }

    btn.disabled    = true;
    btn.innerHTML   = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const res  = await fetch('update_persona.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : `display_name=${encodeURIComponent(name)}&avatar_key=${selectedAvatarKey}`,
        });
        const data = await res.json();

        if (data.success) {
            // ── Update ALL visible UI elements immediately ──────────────
            updateAllUIWithPersona(data);
            closePersona();
            showToast('✅ Persona saved!', 'success');
        } else {
            errEl.innerText = data.error || 'Something went wrong.';
            errEl.style.display = 'block';
        }
    } catch (e) {
        errEl.innerText = 'Network error. Please try again.';
        errEl.style.display = 'block';
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
}

function updateAllUIWithPersona(data) {
    const name    = data.display_name;
    const initial = data.initial;
    const avKey   = data.avatar_key;

    // Sidebar name
    document.getElementById('sidebar-name').innerText = name;

    // Sidebar avatar
    const sav = document.getElementById('sidebar-avatar');
    sav.innerHTML = renderAvatarHTML(avKey, initial);

    // Banner name
    document.getElementById('banner-name').innerText = `Quest Log: ${name}`;

    // Banner avatar
    const bav = document.getElementById('banner-avatar-circle');
    // Rebuild inner HTML (keep lvl-tag)
    const lvlTag = bav.querySelector('.lvl-tag').outerHTML;
    bav.innerHTML = renderAvatarHTML(avKey, initial, true) + lvlTag;

    // XP bar
    document.getElementById('xp-bar').style.width   = data.progress_pct + '%';
    document.getElementById('xp-ratio').innerText   = `${data.xp_this_level} / ${data.xp_per_level}`;
    const needed = data.xp_per_level - data.xp_this_level;
    document.getElementById('xp-label').innerText   = `${needed} XP to Level ${data.level + 1}`;
    document.getElementById('total-xp-display').innerText = data.xp.toLocaleString();

    // Banner level tag (find it inside the avatar circle)
    const lvlEls = document.querySelectorAll('.lvl-tag');
    lvlEls.forEach(el => el.innerText = `L${data.level}`);

    // Modal stats
    document.getElementById('modal-level').innerText   = data.level;
    document.getElementById('modal-xp').innerText      = data.xp.toLocaleString();
    document.getElementById('modal-next-xp').innerText = `${needed} XP`;

    // Update initial preview in modal
    const preview = document.getElementById('modal-initial-preview');
    if (preview) preview.innerText = initial;
}

// Returns HTML for avatar icon or initial
function renderAvatarHTML(key, initial, large = false) {
    const iconMap = {
        ninja:'fa-user-ninja', robot:'fa-robot', ghost:'fa-ghost',
        astro:'fa-user-astronaut', knight:'fa-chess-knight', fire:'fa-fire',
        dragon:'fa-dragon', cat:'fa-cat', crown:'fa-crown',
    };
    if (iconMap[key]) {
        const cls = large ? 'avatar-icon-lg' : '';
        return `<span class="${cls}"><i class="fa-solid ${iconMap[key]}"></i></span>`;
    }
    // Default: show initial
    if (large) return `<span class="initial-text" style="">${initial}</span>`;
    return `<span style="font-weight:800;">${initial}</span>`;
}

// ── Toast helper ──────────────────────────────────────────────────────────────
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.innerText   = msg;
    t.className   = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Animate XP bar on load ────────────────────────────────────────────────────
window.addEventListener('load', () => {
    const bar = document.getElementById('xp-bar');
    if (bar) {
        const target = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => { bar.style.width = target; }, 200);
    }
});
</script>
</body>
</html>