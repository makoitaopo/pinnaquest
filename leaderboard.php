<?php
session_start();
include('db.php');
require_once __DIR__ . '/mission_progress.php';

// ── User Data ─────────────────────────────────────────────────────
$user_id      = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($user_id <= 0 || ($_SESSION['role'] ?? '') !== 'student') {
    header('Location: loginpanel.php');
    exit();
}

$missionProgress = pinnaquestMissionProgress($conn, $user_id);
pinnaquestSyncMissionAchievements($conn, $user_id);
$user         = $missionProgress['user'];
$stats        = $missionProgress['stats'];
$unlocked     = $missionProgress['unlocked'];
$MISSIONS     = $missionProgress['missions'];
$display_name = trim((string)($user['display_name'] ?: $user['full_name'] ?: 'Adventurer'));
$avatar_key   = $user['avatar_key'] ?? 'default';
$total_xp     = intval($stats['total_xp']);
$level        = intval($stats['level']);
$initial      = strtoupper(mb_substr($display_name, 0, 1));

// ── Top 5 Leaderboard ─────────────────────────────────────────────
$lb_res      = $conn->query("SELECT COALESCE(display_name, full_name) as name, xp, avatar_key FROM users WHERE role='student' ORDER BY xp DESC LIMIT 5");
$leaderboard = [];
if ($lb_res) while ($r = $lb_res->fetch_assoc()) $leaderboard[] = $r;

function avatarIcon(string $key, int $size = 28): string {
    $map = ['ninja'=>'fa-user-ninja','robot'=>'fa-robot','ghost'=>'fa-ghost','astro'=>'fa-user-astronaut','knight'=>'fa-chess-knight','fire'=>'fa-fire','dragon'=>'fa-dragon','cat'=>'fa-cat','crown'=>'fa-crown'];
    return isset($map[$key]) ? '<i class="fa-solid '.$map[$key].'" style="font-size:'.$size.'px"></i>' : '';
}

$zones = [
    1 => ['name'=>'The Scholar\'s Road', 'color'=>'#1db968', 'glow'=>'rgba(29,185,104,.5)',  'bg'=>'#0a1f0f', 'icon'=>'📚'],
    2 => ['name'=>'The Battle Arena',    'color'=>'#f59e0b', 'glow'=>'rgba(245,158,11,.5)',  'bg'=>'#1f1500', 'icon'=>'⚔️'],
    3 => ['name'=>'The Pinnacle Keep',   'color'=>'#6366f1', 'glow'=>'rgba(99,102,241,.5)',  'bg'=>'#0d0a1f', 'icon'=>'🏰'],
];

$done_count = count(array_filter($MISSIONS, fn($m)=>$m['state']==='done'));
$total_missions = count($MISSIONS);
$overall_pct = $total_missions > 0 ? round(($done_count / $total_missions) * 100) : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>PinnaQuest | Hall of Fame</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&family=Luckiest+Guy&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <style>
    :root {
        --brand-green:#1db968; --brand-dark-green:#1a4d2e; --brand-light:#f0fff4;
        --sidebar-white:#ffffff; --text-dark:#1a202c; --text-gray:#718096;
        --border-color:#f1f5f9; --gold:#f59e0b; --silver:#a0aec0; --bronze:#cd7f32;
        --synchro-purple:#6366f1; --icon-materials:#3b82f6; --icon-quizzes:#f59e0b; --icon-leaderboard:#10b981;
        --mm-bg:#060b14;
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:"Inter",sans-serif;background:#fcfdfa;display:flex;color:var(--text-dark);min-height:100vh;}

    /* ── SIDEBAR ── */
    .sidebar{width:260px;background:var(--sidebar-white);height:100vh;display:flex;flex-direction:column;padding:30px 20px;position:fixed;top:0;left:0;border-right:1px solid var(--border-color);z-index:1000;}
    .logo-box{margin-bottom:40px;display:flex;justify-content:center;align-items:center;}
    .logo-box img{width:180px;height:auto;transition:.3s;}
    .logo-box img:hover{transform:scale(1.08);}
    .menu-heading{font-size:11px;font-weight:700;color:#cbd5e0;text-transform:uppercase;margin:20px 0 10px 10px;letter-spacing:.05em;}
    .nav-link{display:flex;align-items:center;gap:15px;padding:14px 18px;text-decoration:none;color:var(--text-gray);font-weight:500;font-size:14px;border-radius:12px;margin-bottom:5px;transition:.2s;}
    .nav-link.active{background-color:var(--brand-green);color:white!important;}
    .nav-link.active i{color:white!important;text-shadow:none!important;}
    .nav-link:hover:not(.active){background:var(--brand-light);color:var(--brand-green);}
    .nav-link i.fa-house{color:var(--brand-green);text-shadow:0 0 8px rgba(29,185,104,.4);}
    .nav-link i.fa-file-invoice{color:var(--icon-materials);text-shadow:0 0 8px rgba(59,130,246,.4);}
    .nav-link i.fa-brain{color:var(--icon-quizzes);text-shadow:0 0 8px rgba(245,158,11,.4);}
    .nav-link i.fa-bolt{color:var(--synchro-purple);text-shadow:0 0 8px rgba(99,102,241,.4);}
    .nav-link i.fa-trophy{color:var(--icon-leaderboard);text-shadow:0 0 8px rgba(16,185,129,.4);}
    .nav-link i.fa-user-astronaut{color:#3b82f6;}
    .persona-link-style{color:#94a3b8!important;}
    .persona-link-style:hover{background-color:#f0f7ff!important;color:#3b82f6!important;}
    .sidebar-footer{margin-top:auto;}
    .user-profile-bottom{margin-top:auto;background:#f8fafc;padding:15px;border-radius:16px;display:flex;align-items:center;gap:12px;margin-bottom:10px;}
    .user-profile-bottom .avatar{width:35px;height:35px;background:var(--brand-green);border-radius:8px;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;}
        .sidebar-avatar{width:35px;height:35px;background:var(--brand-green);border-radius:8px;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;flex-shrink:0;}

    .user-details h4{font-size:13px;font-weight:700;color:#2d3748;}
    .user-details p{font-size:11px;color:var(--text-gray);}
    .logout-link{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--text-gray);font-size:13px;font-weight:600;padding:10px 15px;transition:.2s;}
    .logout-link:hover{color:#e53e3e;}

    /* ── MAIN ── */
    .main{flex:1;margin-left:260px;padding:30px 50px;}
    .breadcrumb{font-size:14px;color:var(--text-gray);font-weight:600;display:flex;align-items:center;gap:10px;margin-bottom:30px;}
    .header-section h1{font-family:"Lexend";font-size:32px;font-weight:800;margin-bottom:25px;color:var(--brand-dark-green);}

    /* ── TABS ── */
    .tab-navigation{display:flex;gap:30px;margin-bottom:30px;border-bottom:2px solid var(--border-color);}
    .tab-btn{background:none;border:none;font-family:"Lexend";font-size:16px;font-weight:700;color:var(--text-gray);cursor:pointer;padding-bottom:12px;transition:.3s;position:relative;}
    .tab-btn.active{color:var(--brand-green);}
    .tab-btn.active::after{content:"";position:absolute;bottom:-2px;left:0;width:100%;height:4px;background:var(--brand-green);border-radius:10px;}
    .leaderboard-view{display:none;}
    .leaderboard-view.active{display:block;animation:fadeIn .4s ease-out;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

    /* ── OVERALL / SECTION VIEWS ── */
    .podium-wrapper{display:flex;justify-content:center;align-items:flex-end;gap:20px;margin:50px 0;}
    .podium-card{background:white;border-radius:24px;width:170px;padding:30px 15px;text-align:center;position:relative;box-shadow:0 10px 25px rgba(0,0,0,.05);border:1px solid var(--border-color);transition:.3s;}
    .podium-card:hover{transform:translateY(-8px);}
    .rank-1{width:200px;padding:50px 20px;border:2.5px solid var(--gold);background:linear-gradient(to bottom,#ffffff,#fffdf2);}
    .podium-avatar{width:65px;height:65px;background:#f7fafc;border-radius:50%;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold;}
    .rank-1 .podium-avatar{background:var(--gold);color:white;border:4px solid #fff;box-shadow:0 8px 20px rgba(255,177,0,.3);}
    .podium-name{display:block;font-weight:700;font-size:16px;color:var(--text-dark);}
    .podium-xp{font-weight:800;color:var(--brand-green);font-size:14px;}
    .rank-tag{position:absolute;bottom:-18px;left:50%;transform:translateX(-50%);width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:white;font-weight:900;box-shadow:0 4px 10px rgba(0,0,0,.15);}
    .rankings-list{max-width:900px;margin:0 auto;}
    .list-item{background:white;margin-bottom:12px;padding:18px 30px;border-radius:18px;display:grid;grid-template-columns:60px 1fr 120px 120px;align-items:center;border:1px solid var(--border-color);transition:.2s;}
    .list-item:hover{border-color:var(--brand-green);background:var(--brand-light);transform:scale(1.01);}
    .row-rank{font-weight:800;color:var(--text-gray);}
    .row-player{font-weight:700;display:flex;align-items:center;gap:10px;}
    .row-lvl{background:#fef3c7;color:#d97706;padding:4px 10px;border-radius:8px;font-size:11px;font-weight:800;width:fit-content;}
    .row-xp{font-weight:800;color:var(--brand-green);text-align:right;}
    .section-selector{display:flex;gap:10px;margin-bottom:25px;justify-content:center;}
    .section-pill{padding:10px 20px;background:white;border:1px solid var(--border-color);border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;transition:.2s;}
    .section-pill.active{background:var(--brand-green);color:white;border-color:var(--brand-green);}

    /* ══════════════════════════════════════════════════
       MISSION MAP — Gamified World Design
    ══════════════════════════════════════════════════ */
    #mission-view {
        display: none;
    }
    #mission-view.active {
        display: block;
    }

    .mm-wrapper {
        background: var(--mm-bg);
        border-radius: 28px;
        overflow: hidden;
        position: relative;
        min-height: 750px;
        border: 1px solid rgba(255,255,255,.05);
        box-shadow: 0 30px 80px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.05);
    }

    /* Star field background */
    .mm-stars-bg {
        position: absolute;
        inset: 0;
        overflow: hidden;
        pointer-events: none;
        z-index: 0;
    }
    .star-dot {
        position: absolute;
        border-radius: 50%;
        background: white;
        animation: twinkle var(--dur, 3s) ease-in-out infinite;
        animation-delay: var(--del, 0s);
    }
    @keyframes twinkle {
        0%,100%{opacity:.2;transform:scale(1);}
        50%{opacity:1;transform:scale(1.3);}
    }

    /* Floating particles */
    .mm-particle {
        position: absolute;
        pointer-events: none;
        z-index: 0;
        border-radius: 50%;
        animation: floatUp var(--dur,8s) ease-in-out infinite;
        animation-delay: var(--del,0s);
        opacity: .3;
    }
    @keyframes floatUp {
        0%{transform:translateY(0) rotate(0deg);opacity:.3;}
        50%{opacity:.6;}
        100%{transform:translateY(-120px) rotate(360deg);opacity:0;}
    }

    /* Top progress bar + header */
    .mm-top-bar {
        position: relative;
        z-index: 10;
        padding: 24px 32px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255,255,255,.06);
        background: rgba(0,0,0,.3);
        backdrop-filter: blur(10px);
    }
    .mm-title-block h2 {
        font-family: 'Cinzel', serif;
        font-size: 22px;
        font-weight: 900;
        color: #fff;
        letter-spacing: 2px;
        text-shadow: 0 0 20px rgba(245,158,11,.4);
    }
    .mm-title-block p {
        font-size: 12px;
        color: rgba(255,255,255,.4);
        font-weight: 600;
        margin-top: 2px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
    .mm-player-hud {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .mm-hud-chip {
        background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: 12px;
        padding: 8px 16px;
        text-align: center;
        min-width: 80px;
    }
    .mm-hud-chip .val {
        font-size: 20px;
        font-weight: 900;
        font-family: 'Lexend';
        color: #f59e0b;
        display: block;
    }
    .mm-hud-chip .lbl {
        font-size: 9px;
        color: rgba(255,255,255,.4);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1px;
    }
    .mm-overall-bar-wrap {
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .mm-overall-bar {
        width: 160px;
        height: 8px;
        background: rgba(255,255,255,.08);
        border-radius: 10px;
        overflow: hidden;
    }
    .mm-overall-fill {
        height: 100%;
        background: linear-gradient(90deg, #1db968, #f59e0b);
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(245,158,11,.5);
        transition: width 1.2s ease-out;
    }
    .mm-overall-label {
        font-size: 11px;
        font-weight: 800;
        color: rgba(255,255,255,.5);
    }

    /* World Zones */
    .mm-zones {
        position: relative;
        z-index: 5;
        padding: 30px 40px 50px;
    }
    .mm-zone {
        position: relative;
        margin-bottom: 50px;
    }
    .mm-zone-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 35px;
        padding: 10px 20px;
        border-radius: 20px;
        background: rgba(255,255,255,.03);
        border: 1px solid;
        width: fit-content;
    }
    .mm-zone-emoji { font-size: 22px; }
    .mm-zone-name {
        font-family: 'Cinzel', serif;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
    }
    .mm-zone-num {
        font-size: 10px;
        font-weight: 800;
        opacity: .5;
        letter-spacing: 1px;
    }

    /* The winding path SVG connector */
    .mm-path-svg {
        position: absolute;
        left: 0;
        top: 60px;
        width: 100%;
        pointer-events: none;
        z-index: 1;
        overflow: visible;
    }

    /* Nodes row */
    .mm-nodes-row {
        display: flex;
        justify-content: space-around;
        align-items: center;
        position: relative;
        z-index: 2;
    }
    /* Alternate row height for winding effect */
    .mm-nodes-row .mm-node:nth-child(even) {
        margin-top: 60px;
    }
    .mm-nodes-row .mm-node:nth-child(odd) {
        margin-top: 0;
    }

    /* Individual node */
    .mm-node {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        cursor: default;
        position: relative;
        width: 110px;
        text-align: center;
    }

    .mm-node-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        border: 4px solid transparent;
        position: relative;
        transition: transform .25s cubic-bezier(.34,1.56,.64,1);
        cursor: pointer;
    }
    .mm-node:hover .mm-node-icon { transform: scale(1.15); }

    /* BOSS node bigger */
    .mm-node.boss .mm-node-icon {
        width: 86px;
        height: 86px;
        font-size: 34px;
    }

    /* DONE state */
    .mm-node.done .mm-node-icon {
        background: radial-gradient(circle, rgba(29,185,104,.3) 0%, rgba(29,185,104,.05) 100%);
        border-color: #1db968;
        box-shadow: 0 0 25px rgba(29,185,104,.5), inset 0 0 15px rgba(29,185,104,.1);
    }
    .mm-node.done .mm-node-icon::after {
        content: '✓';
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 20px;
        height: 20px;
        background: #1db968;
        border-radius: 50%;
        font-size: 10px;
        color: #fff;
        font-weight: 900;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--mm-bg);
    }
    .mm-node.boss.done .mm-node-icon::after { width: 24px; height: 24px; font-size: 12px; }

    /* ACTIVE state */
    .mm-node.active .mm-node-icon {
        background: radial-gradient(circle, rgba(245,200,66,.25) 0%, rgba(245,200,66,.05) 100%);
        border-color: #f5c842;
        box-shadow: 0 0 0 0 rgba(245,200,66,.6);
        animation: nodeGlow 2s ease-in-out infinite;
    }
    @keyframes nodeGlow {
        0%,100%{box-shadow:0 0 20px rgba(245,200,66,.4),0 0 0 0 rgba(245,200,66,.3);}
        50%{box-shadow:0 0 40px rgba(245,200,66,.7),0 0 15px 5px rgba(245,200,66,.2);}
    }
    .mm-node.active .mm-node-icon::before {
        content: '';
        position: absolute;
        inset: -8px;
        border-radius: 50%;
        border: 2px dashed rgba(245,200,66,.4);
        animation: spinRing 4s linear infinite;
    }
    @keyframes spinRing { to { transform: rotate(360deg); } }

    /* Active label badge */
    .mm-node.active .mm-node-name-wrap::after {
        content: '▶ CURRENT';
        display: block;
        font-size: 8px;
        font-weight: 900;
        color: #f5c842;
        letter-spacing: 1px;
        margin-top: 3px;
        animation: flashText 1.5s ease-in-out infinite;
    }
    @keyframes flashText { 0%,100%{opacity:1;}50%{opacity:.4;} }

    /* BOSS active special */
    .mm-node.boss.active .mm-node-icon {
        background: radial-gradient(circle, rgba(99,102,241,.3) 0%, rgba(99,102,241,.05) 100%);
        border-color: #6366f1;
        animation: bossGlow 1.5s ease-in-out infinite;
    }
    @keyframes bossGlow {
        0%,100%{box-shadow:0 0 30px rgba(99,102,241,.5),0 0 60px rgba(99,102,241,.2);}
        50%{box-shadow:0 0 60px rgba(99,102,241,.8),0 0 100px rgba(99,102,241,.4);}
    }

    /* LOCKED state */
    .mm-node.locked .mm-node-icon {
        background: rgba(255,255,255,.03);
        border-color: rgba(255,255,255,.08);
        filter: grayscale(.9);
        opacity: .35;
    }

    /* BOSS locked */
    .mm-node.boss.locked .mm-node-icon {
        border-color: rgba(99,102,241,.2);
        filter: grayscale(.7);
        opacity: .4;
    }

    /* Node label */
    .mm-node-name-wrap {
        text-align: center;
    }
    .mm-node-name {
        font-size: 11px;
        font-weight: 800;
        color: rgba(255,255,255,.9);
        font-family: 'Lexend';
        line-height: 1.3;
    }
    .mm-node.locked .mm-node-name { color: rgba(255,255,255,.3); }
    .mm-node.boss .mm-node-name {
        font-family: 'Cinzel', serif;
        font-size: 12px;
        color: #a78bfa;
        text-shadow: 0 0 10px rgba(167,139,250,.5);
    }
    .mm-node.boss.done .mm-node-name { color: #f59e0b; }

    /* Stars */
    .mm-stars {
        display: flex;
        justify-content: center;
        gap: 2px;
        margin-top: 2px;
    }
    .mm-star { font-size: 10px; color: rgba(255,255,255,.1); transition: .3s; }
    .mm-star.on { color: #f5c842; text-shadow: 0 0 6px rgba(245,200,66,.7); }

    /* XP badge */
    .mm-xp-tag {
        font-size: 9px;
        font-weight: 900;
        padding: 2px 8px;
        border-radius: 20px;
        font-family: 'Lexend';
        letter-spacing: .5px;
    }
    .mm-xp-tag.green { background: rgba(29,185,104,.15); color: #4ade80; border: 1px solid rgba(29,185,104,.3); }
    .mm-xp-tag.gold  { background: rgba(245,158,11,.15);  color: #f5c842; border: 1px solid rgba(245,158,11,.3); }
    .mm-xp-tag.purple{ background: rgba(99,102,241,.15);  color: #a78bfa; border: 1px solid rgba(99,102,241,.3); }
    .mm-xp-tag.grey  { background: rgba(255,255,255,.04); color: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.06); }

    /* Zone divider line */
    .mm-zone-divider {
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.08), transparent);
        margin: 10px 0 40px;
        position: relative;
    }
    .mm-zone-divider::after {
        content: '✦';
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%,-50%);
        font-size: 16px;
        color: rgba(255,255,255,.15);
        background: var(--mm-bg);
        padding: 0 12px;
    }

    /* Tooltip on hover */
    .mm-tooltip {
        position: absolute;
        bottom: calc(100% + 15px);
        left: 50%;
        transform: translateX(-50%);
        background: rgba(15,15,30,.95);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 14px;
        padding: 12px 16px;
        width: 175px;
        pointer-events: none;
        opacity: 0;
        transition: opacity .2s;
        z-index: 100;
        box-shadow: 0 15px 30px rgba(0,0,0,.5);
        backdrop-filter: blur(10px);
    }
    .mm-node:hover .mm-tooltip { opacity: 1; }
    .mm-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: rgba(255,255,255,.12);
    }
    .mm-tooltip-title { font-size: 13px; font-weight: 800; color: white; margin-bottom: 4px; font-family: 'Lexend'; }
    .mm-tooltip-desc  { font-size: 11px; color: rgba(255,255,255,.5); line-height: 1.4; }
    .mm-tooltip-xp    { font-size: 11px; font-weight: 800; color: #f5c842; margin-top: 6px; }
    .mm-tooltip-progress { font-size: 10px; color: rgba(255,255,255,.65); margin-top: 6px; }
    .mm-node-cta {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 7px;
        padding: 5px 9px;
        border-radius: 8px;
        color: #101827;
        background: #f5c842;
        text-decoration: none;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .4px;
        text-transform: uppercase;
        transition: transform .2s, background .2s;
    }
    .mm-node-cta:hover { transform: translateY(-1px); background: #fde68a; }
    .mm-stats-strip {
        position: relative;
        z-index: 10;
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: rgba(255,255,255,.08);
        border-bottom: 1px solid rgba(255,255,255,.06);
    }
    .mm-stat {
        padding: 13px 16px;
        background: rgba(0,0,0,.22);
        text-align: center;
    }
    .mm-stat strong { display:block; color:#fff; font: 800 18px 'Lexend'; }
    .mm-stat span { color: rgba(255,255,255,.42); font-size:9px; font-weight:800; letter-spacing:1px; text-transform:uppercase; }

    /* Path connections drawn via SVG inline */
    .mm-path-line {
        stroke-width: 4;
        fill: none;
        stroke-dasharray: 8,6;
        stroke-linecap: round;
    }
    .mm-path-line.done  { stroke: #1db968; opacity: .7; }
    .mm-path-line.active{ stroke: #f5c842; opacity: .5; stroke-dasharray: 6,4; animation: dashFlow .8s linear infinite; }
    .mm-path-line.locked{ stroke: rgba(255,255,255,.1); }
    @keyframes dashFlow { to { stroke-dashoffset: -20; } }

    /* Bottom summary bar */
    .mm-summary-bar {
        position: relative;
        z-index: 10;
        padding: 18px 32px;
        background: rgba(0,0,0,.4);
        border-top: 1px solid rgba(255,255,255,.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        backdrop-filter: blur(10px);
        flex-wrap: wrap;
        gap: 15px;
    }
    .mm-legend { display: flex; gap: 20px; flex-wrap: wrap; }
    .mm-leg {
        display: flex; align-items: center; gap: 7px;
        font-size: 11px; font-weight: 700; color: rgba(255,255,255,.4);
    }
    .mm-leg-dot { width: 10px; height: 10px; border-radius: 50%; }
    .mm-progress-text {
        font-family: 'Cinzel', serif;
        font-size: 13px;
        color: rgba(255,255,255,.6);
        letter-spacing: 1px;
    }
    .mm-progress-text span {
        color: #f5c842;
        font-weight: 900;
    }
    @media (max-width: 900px) {
        .sidebar { width: 82px; padding: 24px 10px; }
        .logo-box img { width: 62px; }
        .menu-heading, .nav-link { font-size: 0; }
        .menu-heading { height: 12px; margin-left: 0; }
        .nav-link { justify-content: center; padding: 14px 10px; }
        .nav-link i { font-size: 18px; }
        .user-profile-bottom { justify-content: center; padding: 10px 6px; }
        .user-details, .logout-link { display: none; }
        .main { margin-left: 82px; padding: 24px 20px; }
        .mm-top-bar { align-items: flex-start; gap: 18px; flex-direction: column; }
        .mm-player-hud { width: 100%; justify-content: space-between; }
        .mm-zones { padding-left: 20px; padding-right: 20px; }
        .mm-nodes-row { gap: 8px; }
        .mm-node { width: 94px; }
        .mm-node:nth-child(even) { margin-top: 35px !important; }
    }
    @media (max-width: 600px) {
        .main { padding: 16px 10px; }
        .breadcrumb { margin-bottom: 18px; }
        .tab-navigation { margin-bottom: 18px; }
        .mm-wrapper { border-radius: 18px; }
        .mm-top-bar { padding: 18px 16px; }
        .mm-title-block h2 { font-size: 17px; }
        .mm-player-hud { gap: 6px; }
        .mm-hud-chip { min-width: 0; flex: 1; padding: 8px 5px; }
        .mm-hud-chip .val { font-size: 16px; }
        .mm-stats-strip { grid-template-columns: repeat(2, 1fr); }
        .mm-zones { padding: 22px 10px 30px; }
        .mm-zone-header { margin-bottom: 20px; }
        .mm-nodes-row { justify-content: space-between; }
        .mm-node { width: 76px; }
        .mm-node-icon { width: 54px; height: 54px; font-size: 22px; border-width: 3px; }
        .mm-node.boss .mm-node-icon { width: 66px; height: 66px; font-size: 27px; }
        .mm-node-name { font-size: 9px; }
        .mm-xp-tag { font-size: 8px; padding: 2px 5px; }
        .mm-path-svg { display: none; }
        .mm-summary-bar { padding: 16px; }
        .mm-legend { gap: 10px; }
    }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar">
    <div class="logo-box"><img src="pinnaquest logo.JPG" alt="PinnaQuest"></div>
    <p class="menu-heading">Menu</p>
    <a href="studentdashboard.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a>
    <a href="materials.php" class="nav-link"><i class="fa-solid fa-file-invoice"></i> Materials</a>
    <a href="quizzes.php" class="nav-link"><i class="fa-solid fa-brain"></i> Quizzes</a>
    <a href="synchro_portal.php" class="nav-link"><i class="fa-solid fa-bolt-lightning"></i> Synchro-Quiz</a>
    <a href="leaderboard.php" class="nav-link active"><i class="fa-solid fa-trophy"></i> Mission Map</a>
    <a href="studentdashboard.php?openPersona=true" class="nav-link persona-link-style"><i class="fa-solid fa-user-astronaut"></i> Quest Persona</a>
    <a href="account_settings.php" class="nav-link"><i class="fa-solid fa-gear" style="color:#f59e0b"></i> Account Settings</a>
    <div class="user-profile-bottom">
        <div class="sidebar-avatar"><?php echo $initial; ?></div>
        <div class="user-details">
            <h4><?php echo htmlspecialchars($display_name); ?></h4>
            <p>Student &middot; Lv <?php echo $level; ?></p>
        </div>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
</div>

<!-- ── MAIN ── -->
<div class="main">
    <div class="breadcrumb"><i class="fa-solid fa-trophy" style="color:var(--gold)"></i><span>Journey</span></div>

    <div class="tab-navigation">
       
        <button class="tab-btn" onclick="switchTab('mission',this)">Mission Map</button>
    </div>
    

    <!-- ═══════════════════════════════════════════════════
         MISSION MAP TAB
    ═══════════════════════════════════════════════════ -->
    <div id="mission-view" class="leaderboard-view active">
        <div class="mm-wrapper" id="mm-wrapper">

            <!-- Star field -->
            <div class="mm-stars-bg" id="mm-stars"></div>

            <!-- Top HUD bar -->
            <div class="mm-top-bar">
                <div class="mm-title-block">
                    <h2>⚔ THE QUEST MAP</h2>
                    <p>Your legendary journey through PinnaQuest</p>
                    <div class="mm-overall-bar-wrap" style="margin-top:8px;">
                        <div class="mm-overall-bar">
                            <div class="mm-overall-fill" id="mm-fill" style="width:0%"></div>
                        </div>
                        <span class="mm-overall-label"><?php echo $done_count; ?>/<?php echo $total_missions; ?> Cleared</span>
                    </div>
                </div>
                <div class="mm-player-hud">
                    <div class="mm-hud-chip">
                        <span class="val"><?php echo $level; ?></span>
                        <span class="lbl">Level</span>
                    </div>
                    <div class="mm-hud-chip">
                        <span class="val"><?php echo number_format($total_xp); ?></span>
                        <span class="lbl">Total XP</span>
                    </div>
                    <div class="mm-hud-chip">
                        <span class="val"><?php echo $done_count; ?></span>
                        <span class="lbl">Cleared</span>
                    </div>
                </div>
            </div>

            <div class="mm-stats-strip" aria-label="Learning progress summary">
                <div class="mm-stat">
                    <strong><?php echo $stats['quiz_count']; ?></strong>
                    <span>Solo Quizzes</span>
                </div>
                <div class="mm-stat">
                    <strong><?php echo $stats['total_correct']; ?></strong>
                    <span>Correct Answers</span>
                </div>
                <div class="mm-stat">
                    <strong><?php echo $stats['synchro_count']; ?></strong>
                    <span>Live Battles</span>
                </div>
                <div class="mm-stat">
                    <strong><?php echo $stats['max_streak']; ?></strong>
                    <span>Best Streak</span>
                </div>
            </div>

            <!-- Zones + Nodes -->
            <div class="mm-zones">
                <?php
                $zone_missions = [1=>[], 2=>[], 3=>[]];
                foreach ($MISSIONS as $m) $zone_missions[$m['zone']][] = $m;

                $zone_colors = [
                    1 => ['col'=>'#1db968', 'border'=>'rgba(29,185,104,.3)', 'text'=>'#4ade80'],
                    2 => ['col'=>'#f59e0b', 'border'=>'rgba(245,158,11,.3)', 'text'=>'#fcd34d'],
                    3 => ['col'=>'#6366f1', 'border'=>'rgba(99,102,241,.3)', 'text'=>'#a78bfa'],
                ];

                foreach ($zones as $zid => $zone):
                    $zc = $zone_colors[$zid];
                    $zmissions = $zone_missions[$zid];
                    $zdone = count(array_filter($zmissions, fn($m)=>$m['state']==='done'));
                    $ztotal = count($zmissions);
                ?>
                <div class="mm-zone">
                    <!-- Zone label -->
                    <div class="mm-zone-header"
                         style="border-color:<?php echo $zc['border']; ?>;
                                background: linear-gradient(135deg, <?php echo str_replace(')',', .08)',$zc['border']); ?>, rgba(0,0,0,.2));">
                        <span class="mm-zone-emoji"><?php echo $zone['icon']; ?></span>
                        <div>
                            <div class="mm-zone-name" style="color:<?php echo $zc['text']; ?>">
                                <?php echo $zone['name']; ?>
                            </div>
                            <div class="mm-zone-num" style="color:<?php echo $zc['text']; ?>">
                                ZONE <?php echo $zid; ?> · <?php echo $zdone; ?>/<?php echo $ztotal; ?> CLEARED
                            </div>
                        </div>
                    </div>

                    <!-- Nodes with winding path -->
                    <div style="position:relative; padding: 15px 20px 30px;">
                        <!-- SVG path connector -->
                        <svg class="mm-path-svg" id="svg-zone-<?php echo $zid; ?>" height="140" preserveAspectRatio="none">
                            <!-- JS will draw paths -->
                        </svg>

                        <!-- Nodes row -->
                        <div class="mm-nodes-row" id="nodes-zone-<?php echo $zid; ?>">
                            <?php foreach ($zmissions as $ni => $m):
                                $xp_cls = ($m['zone']==1) ? 'green' : (($m['zone']==2) ? 'gold' : 'purple');
                                if ($m['state']==='locked') $xp_cls = 'grey';
                                $stars_done = ($m['state']==='done') ? $m['stars'] : (($m['state']==='active') ? 1 : 0);
                            ?>
                            <div class="mm-node <?php echo $m['state']; ?><?php echo $m['boss']?' boss':''; ?>"
                                 id="node-<?php echo $m['key']; ?>">

                                <!-- Tooltip -->
                                <div class="mm-tooltip">
                                    <div class="mm-tooltip-title"><?php echo htmlspecialchars($m['name']); ?></div>
                                    <div class="mm-tooltip-desc"><?php echo htmlspecialchars($m['desc']); ?></div>
                                     <div class="mm-tooltip-xp">Milestone value: <?php echo $m['xp']; ?> XP</div>
                                     <div class="mm-tooltip-progress">
                                         Progress: <?php echo intval($m['progress_value']); ?>/<?php echo intval($m['progress_target']); ?>
                                         <?php echo htmlspecialchars($m['progress_label']); ?>
                                     </div>
                                </div>

                                <div class="mm-node-icon"><?php echo $m['emoji']; ?></div>

                                <div class="mm-node-name-wrap">
                                    <div class="mm-node-name"><?php echo htmlspecialchars($m['name']); ?></div>
                                    <div class="mm-stars">
                                        <?php for ($s=1;$s<=3;$s++): ?>
                                        <span class="mm-star <?php echo $s<=$stars_done?'on':''; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="mm-xp-tag <?php echo $xp_cls; ?>">+<?php echo $m['xp']; ?> XP</span>
                                    <?php if ($m['state'] === 'active' && !empty($m['href'])): ?>
                                    <a class="mm-node-cta" href="<?php echo htmlspecialchars($m['href']); ?>">
                                        Start <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($zid < 3): ?>
                    <div class="mm-zone-divider"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div><!-- end mm-zones -->

            <!-- Bottom summary -->
            <div class="mm-summary-bar">
                <div class="mm-legend">
                    <div class="mm-leg"><div class="mm-leg-dot" style="background:#1db968;box-shadow:0 0 6px #1db968;"></div>Cleared</div>
                    <div class="mm-leg"><div class="mm-leg-dot" style="background:#f5c842;box-shadow:0 0 6px #f5c842;"></div>Active</div>
                    <div class="mm-leg"><div class="mm-leg-dot" style="background:rgba(255,255,255,.15);"></div>Locked</div>
                    <div class="mm-leg"><div class="mm-leg-dot" style="background:#6366f1;box-shadow:0 0 6px #6366f1;"></div>Boss</div>
                </div>
                <div class="mm-progress-text">
                    Quest Progress: <span><?php echo $overall_pct; ?>%</span> Complete
                    <?php if ($overall_pct >= 100): ?>
                    — <span style="color:#f5c842;">🏆 Legendary!</span>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- end mm-wrapper -->
    </div><!-- end mission-view -->

</div><!-- end main -->

<script>
/* ── Tab switcher ──────────────────────────────────── */
function switchTab(viewId, btn) {
    document.querySelectorAll('.leaderboard-view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(viewId + '-view').classList.add('active');
    btn.classList.add('active');
    if (viewId === 'mission') initMissionMap();
}

/* ── Section pill clicks ──────────────────────────── */
document.querySelectorAll('.section-pill').forEach(p => {
    p.onclick = () => {
        document.querySelectorAll('.section-pill').forEach(x => x.classList.remove('active'));
        p.classList.add('active');
    };
});

/* ══════════════════════════════════════════
   MISSION MAP INIT
══════════════════════════════════════════ */
let mmInitialized = false;

function initMissionMap() {
    if (mmInitialized) return;
    mmInitialized = true;

    spawnStars();
    spawnParticles();
    animateProgressBar();
    drawPaths();
    staggerNodeAnimations();
}

/* Star field */
function spawnStars() {
    const container = document.getElementById('mm-stars');
    for (let i = 0; i < 120; i++) {
        const s = document.createElement('div');
        const size = Math.random() * 2.5 + .5;
        s.className = 'star-dot';
        s.style.cssText = `
            left:${Math.random()*100}%;
            top:${Math.random()*100}%;
            width:${size}px;
            height:${size}px;
            --dur:${2+Math.random()*4}s;
            --del:${Math.random()*4}s;
            opacity:${.1+Math.random()*.4};
        `;
        container.appendChild(s);
    }
}

/* Floating color particles */
function spawnParticles() {
    const wrapper = document.getElementById('mm-wrapper');
    const colors = ['#1db968','#f59e0b','#6366f1','#ef4444','#3b82f6','#a78bfa'];
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        const sz = 3 + Math.random() * 6;
        p.className = 'mm-particle';
        p.style.cssText = `
            left:${Math.random()*100}%;
            top:${30+Math.random()*60}%;
            width:${sz}px;
            height:${sz}px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            --dur:${6+Math.random()*8}s;
            --del:${Math.random()*6}s;
        `;
        wrapper.appendChild(p);
    }
}

/* Animate progress bar */
function animateProgressBar() {
    const fill = document.getElementById('mm-fill');
    const pct  = <?php echo $overall_pct; ?>;
    setTimeout(() => { fill.style.width = pct + '%'; }, 200);
}

/* Draw SVG winding path between nodes */
function drawPaths() {
    [1,2,3].forEach(zoneId => {
        const row = document.getElementById('nodes-zone-' + zoneId);
        const svg = document.getElementById('svg-zone-' + zoneId);
        if (!row || !svg) return;

        const nodes = row.querySelectorAll('.mm-node');
        if (nodes.length < 2) return;

        const rowRect = row.getBoundingClientRect();
        const svgRect = svg.getBoundingClientRect();

        let pathData = '';
        let prevX = 0, prevY = 0;

        nodes.forEach((node, i) => {
            const iconEl = node.querySelector('.mm-node-icon');
            const r      = iconEl.getBoundingClientRect();
            // Center of icon relative to SVG
            const cx = r.left - svgRect.left + r.width / 2;
            const cy = r.top  - svgRect.top  + r.height / 2;

            if (i === 0) {
                prevX = cx; prevY = cy;
                return;
            }

            // Determine line state from node
            const state = node.classList.contains('done') ? 'done'
                        : node.classList.contains('active') ? 'active'
                        : 'locked';

            // Bezier curve for winding look
            const midX = (prevX + cx) / 2;
            const midY = (prevY + cy) / 2;
            const cpY  = midY + (i % 2 === 0 ? 30 : -30);

            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            line.setAttribute('d', `M ${prevX} ${prevY} Q ${midX} ${cpY} ${cx} ${cy}`);
            line.setAttribute('class', 'mm-path-line ' + state);
            // Animate done lines with dash
            if (state === 'done') {
                const len = line.getTotalLength ? line.getTotalLength() : 200;
                line.style.strokeDasharray = len;
                line.style.strokeDashoffset = len;
                line.style.animation = `drawLine 1s ease ${i * .15}s forwards`;
            }
            svg.appendChild(line);

            prevX = cx; prevY = cy;
        });
    });
}

/* Inject draw-line keyframes */
const style = document.createElement('style');
style.textContent = `
@keyframes drawLine {
    to { stroke-dashoffset: 0; }
}`;
document.head.appendChild(style);

/* Stagger node entrance animations */
function staggerNodeAnimations() {
    document.querySelectorAll('.mm-node').forEach((node, i) => {
        node.style.opacity = '0';
        node.style.transform = 'scale(0.6) translateY(20px)';
        node.style.transition = `opacity .4s ease, transform .4s cubic-bezier(.34,1.56,.64,1)`;
        setTimeout(() => {
            node.style.opacity = '';
            node.style.transform = '';
        }, 100 + i * 80);
    });
}

/* Auto-init if user refreshes directly on this tab (rare) */
window.addEventListener('DOMContentLoaded', initMissionMap);

/* Re-draw paths after fonts loaded (layout may shift) */
document.fonts.ready.then(() => {
    if (mmInitialized) {
        document.querySelectorAll('[id^="svg-zone-"]').forEach(s => s.innerHTML = '');
        drawPaths();
    }
});
</script>
</body>
</html>