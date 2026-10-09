<?php
// teacherdashboard.php — Gamified Teacher Dashboard
session_start();
include('db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: loginpanel.php");
    exit();
}
 // NEW — role guard
    if (($_SESSION['role'] ?? '') !== 'teacher') {
        header("Location: studentdashboard.php");
        exit();
    }
$user_id = intval($_SESSION['user_id']);

// ── Load user ────────────────────────────────────────────────────
$user_res = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user     = $user_res ? $user_res->fetch_assoc() : [];

$display_name = !empty($user['display_name']) ? $user['display_name']
              : (!empty($user['full_name'])   ? $user['full_name'] : 'Professor');
$initial      = strtoupper(mb_substr($display_name, 0, 1));

$_SESSION['user_name'] = $display_name;

// ── Stats ─────────────────────────────────────────────────────────
$mat_res      = $conn->query("SELECT COUNT(*) as cnt FROM teacher_materials");
$mat_count    = $mat_res ? intval($mat_res->fetch_assoc()['cnt']) : 0;

$sess_res     = $conn->query("SELECT COUNT(*) as cnt FROM synchro_sessions");
$sess_count   = $sess_res ? intval($sess_res->fetch_assoc()['cnt']) : 0;

$fin_res      = $conn->query("SELECT COUNT(*) as cnt FROM synchro_sessions WHERE status = 'finished'");
$fin_count    = $fin_res ? intval($fin_res->fetch_assoc()['cnt']) : 0;

$part_res     = $conn->query("SELECT COUNT(DISTINCT sp.nickname) as cnt FROM synchro_participants sp");
$part_count   = $part_res ? intval($part_res->fetch_assoc()['cnt']) : 0;

// Recent sessions
$recent_res   = $conn->query(
    "SELECT s.*, tm.title as mat_title, COUNT(sp.id) as pcount
     FROM synchro_sessions s
     LEFT JOIN teacher_materials tm ON s.material_id = tm.id
     LEFT JOIN synchro_participants sp ON s.id = sp.session_id
     GROUP BY s.id ORDER BY s.created_at DESC LIMIT 3"
);
$recent_sessions = [];
if ($recent_res) while ($r = $recent_res->fetch_assoc()) $recent_sessions[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PinnaQuest | Professor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Lexend:wght@400;600;700;800&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* ══════════════════════════════════════════════════════════════
       CSS VARIABLES & RESET
    ══════════════════════════════════════════════════════════════ */
    :root {
        --brand-green:      #1db968;
        --brand-dark:       #14452b;
        --brand-mid:        #1a5c38;
        --brand-light:      #f0fff4;
        --gold:             #f59e0b;
        --gold-dark:        #d97706;
        --purple:           #6366f1;
        --sidebar-bg:       #ffffff;
        --text-dark:        #1a202c;
        --text-gray:        #718096;
        --border:           #f1f5f9;
        --bg:               #f4f7f4;
        --surface:          #ffffff;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Nunito', sans-serif;
        background: var(--bg);
        display: flex;
        color: var(--text-dark);
        min-height: 100vh;
    }

    /* ══════════════════════════════════════════════════════════════
       SIDEBAR
    ══════════════════════════════════════════════════════════════ */
    .sidebar {
        width: 260px;
        background: var(--sidebar-bg);
        height: 100vh;
        display: flex;
        flex-direction: column;
        padding: 30px 18px;
        position: fixed;
        top: 0; left: 0;
        border-right: 1px solid var(--border);
        z-index: 1000;
    }

    .logo-box {
        margin-bottom: 36px;
        display: flex;
        justify-content: center;
    }
    .logo-box img { width: 170px; height: auto; }

    .menu-heading {
        font-size: 10px;
        font-weight: 800;
        color: #cbd5e0;
        text-transform: uppercase;
        letter-spacing: .08em;
        margin: 18px 0 8px 10px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 13px;
        padding: 13px 16px;
        text-decoration: none;
        color: var(--text-gray);
        font-weight: 700;
        font-size: 14px;
        border-radius: 14px;
        margin-bottom: 4px;
        transition: .2s;
        position: relative;
    }
    .nav-link.active {
        background: linear-gradient(90deg, var(--brand-green), #25d075);
        color: white;
        box-shadow: 0 4px 15px rgba(29,185,104,.3);
    }
    .nav-link.active i { color: white !important; }
    .nav-link:hover:not(.active) {
        background: var(--brand-light);
        color: var(--brand-green);
    }

    .nav-link i.fa-house         { color: var(--brand-green); }
    .nav-link i.fa-file-invoice  { color: #3b82f6; }
    .nav-link i.fa-chart-bar     { color: var(--gold); }
    .nav-link i.fa-chart-line    { color: #0d9488; }
    .nav-link i.fa-bolt          { color: var(--purple); }

    /* Live badge on Synchro */
    .live-dot {
        margin-left: auto;
        width: 8px; height: 8px;
        background: #ef4444;
        border-radius: 50%;
        animation: livePulse 1.5s ease-in-out infinite;
    }
    @keyframes livePulse { 0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)} }

    .sidebar-footer { margin-top: auto; }

    /* Prof card at bottom */
    .prof-card {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand-mid) 100%);
        border-radius: 18px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: .25s;
        border: 1px solid rgba(255,255,255,.08);
    }
    .prof-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(20,69,43,.25);
    }
    .prof-avatar {
        width: 40px; height: 40px;
        background: rgba(255,255,255,.15);
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-family: 'Luckiest Guy', cursive;
        font-size: 18px;
        color: white;
        flex-shrink: 0;
        border: 1.5px solid rgba(255,255,255,.2);
    }
    .prof-info h4 { font-size: 13px; font-weight: 800; color: white; margin: 0; }
    .prof-info span { font-size: 11px; color: rgba(255,255,255,.55); font-weight: 600; }
    .prof-edit-icon { margin-left: auto; color: rgba(255,255,255,.4); font-size: 12px; flex-shrink: 0; }

    .logout-link {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: var(--text-gray);
        font-size: 13px;
        font-weight: 700;
        padding: 11px 15px;
        border-radius: 12px;
        transition: .2s;
    }
    .logout-link:hover { background: #fff5f5; color: #e53e3e; }

    /* ══════════════════════════════════════════════════════════════
       MAIN CONTENT
    ══════════════════════════════════════════════════════════════ */
    .main {
        flex: 1;
        margin-left: 260px;
        padding: 32px 46px;
    }

    .breadcrumb {
        font-size: 13px;
        color: var(--text-gray);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 28px;
    }

    /* ══════════════════════════════════════════════════════════════
       HERO BANNER
    ══════════════════════════════════════════════════════════════ */
    .hero-banner {
        background: linear-gradient(135deg, var(--brand-dark) 0%, #1a5c38 55%, #206b42 100%);
        border-radius: 32px;
        padding: 0;
        margin-bottom: 28px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 20px 50px rgba(20,69,43,.25);
    }

    /* Decorative circles */
    .hero-banner::before {
        content: '';
        position: absolute;
        top: -60px; right: -60px;
        width: 280px; height: 280px;
        background: rgba(29,185,104,.12);
        border-radius: 50%;
    }
    .hero-banner::after {
        content: '';
        position: absolute;
        bottom: -80px; left: 30%;
        width: 200px; height: 200px;
        background: rgba(245,158,11,.07);
        border-radius: 50%;
    }

    .hero-inner {
        position: relative;
        z-index: 1;
        padding: 36px 44px;
        display: flex;
        align-items: center;
        gap: 32px;
        flex-wrap: wrap;
    }

    /* Professor avatar in hero */
    .hero-avatar-ring {
        width: 88px; height: 88px;
        border-radius: 50%;
        background: rgba(255,255,255,.12);
        border: 3px solid rgba(255,255,255,.25);
        display: flex; align-items: center; justify-content: center;
        position: relative;
        flex-shrink: 0;
    }
    .hero-avatar-ring::after {
        content: '🎓';
        position: absolute;
        bottom: -4px; right: -4px;
        font-size: 22px;
        background: var(--brand-dark);
        border-radius: 50%;
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid rgba(255,255,255,.2);
    }
    .hero-initial {
        font-family: 'Luckiest Guy', cursive;
        font-size: 36px;
        color: white;
        letter-spacing: 1px;
    }

    .hero-text { flex: 1; min-width: 200px; }
    .hero-eyebrow {
        font-size: 11px;
        font-weight: 800;
        color: var(--brand-green);
        text-transform: uppercase;
        letter-spacing: .12em;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 7px;
    }
    .hero-eyebrow::before {
        content: '';
        width: 22px; height: 2px;
        background: var(--brand-green);
        display: inline-block;
    }
    .hero-title {
        font-family: 'Luckiest Guy', cursive;
        font-size: 34px;
        color: white;
        letter-spacing: 1px;
        line-height: 1.1;
        margin-bottom: 8px;
        text-shadow: 0 2px 12px rgba(0,0,0,.2);
    }
    .hero-subtitle { font-size: 14px; color: rgba(255,255,255,.65); font-weight: 600; }

    /* Stat pills inside hero */
    .hero-stats {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-left: auto;
    }

    .hero-stat-pill {
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.15);
        border-radius: 20px;
        padding: 12px 22px;
        text-align: center;
        backdrop-filter: blur(8px);
        min-width: 90px;
        transition: .25s;
    }
    .hero-stat-pill:hover {
        background: rgba(255,255,255,.18);
        transform: translateY(-3px);
    }
    .hero-stat-val {
        font-family: 'Luckiest Guy', cursive;
        font-size: 30px;
        color: white;
        letter-spacing: 1px;
        display: block;
        line-height: 1;
    }
    .hero-stat-val.gold  { color: var(--gold); }
    .hero-stat-val.green { color: #6ee7b7; }
    .hero-stat-lbl {
        font-size: 10px;
        font-weight: 800;
        color: rgba(255,255,255,.5);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-top: 4px;
        display: block;
    }

    /* Bottom ticker in hero */
    .hero-ticker {
        background: rgba(0,0,0,.2);
        border-top: 1px solid rgba(255,255,255,.06);
        padding: 12px 44px;
        display: flex;
        align-items: center;
        gap: 28px;
        font-size: 12px;
        color: rgba(255,255,255,.5);
        font-weight: 700;
        flex-wrap: wrap;
    }
    .ticker-item { display: flex; align-items: center; gap: 7px; }
    .ticker-dot {
        width: 6px; height: 6px;
        border-radius: 50%;
    }

    /* ══════════════════════════════════════════════════════════════
       ACTION CARDS GRID
    ══════════════════════════════════════════════════════════════ */
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 28px;
    }

    .action-card {
        background: var(--surface);
        border-radius: 26px;
        overflow: hidden;
        border: 1.5px solid var(--border);
        transition: .3s cubic-bezier(.34,1.56,.64,1);
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        cursor: pointer;
        position: relative;
    }
    .action-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0,0,0,.08);
        border-color: transparent;
    }

    /* Top accent stripe */
    .card-stripe {
        height: 6px;
        width: 100%;
    }
    .stripe-green  { background: linear-gradient(90deg, var(--brand-green), #25d075); }
    .stripe-gold   { background: linear-gradient(90deg, var(--gold), #fbbf24); }
    .stripe-purple { background: linear-gradient(90deg, var(--purple), #818cf8); }

    .card-body-inner {
        padding: 28px 28px 24px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .card-icon-box {
        width: 56px; height: 56px;
        border-radius: 18px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
        position: relative;
    }
    .icon-bg-green  { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: var(--brand-green); }
    .icon-bg-gold   { background: linear-gradient(135deg, #fef9c3, #fde68a); color: var(--gold-dark); }
    .icon-bg-purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe); color: var(--purple); }

    /* Glow shimmer on icon */
    .card-icon-box::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(255,255,255,.6) 0%, transparent 60%);
        pointer-events: none;
    }

    .card-title {
        font-family: 'Lexend', sans-serif;
        font-size: 18px;
        font-weight: 800;
        color: var(--brand-dark);
        margin-bottom: 8px;
    }
    .card-desc {
        font-size: 13px;
        color: var(--text-gray);
        font-weight: 600;
        line-height: 1.55;
        flex: 1;
        margin-bottom: 24px;
    }

    .card-cta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--bg);
        border-radius: 14px;
        padding: 13px 18px;
        font-weight: 800;
        font-size: 13px;
        color: var(--brand-dark);
        border: none;
        width: 100%;
        cursor: pointer;
        transition: .2s;
        font-family: 'Nunito', sans-serif;
        text-decoration: none;
    }
    .card-cta:hover { background: var(--brand-light); color: var(--brand-green); }
    .card-cta .arrow-icon { font-size: 14px; transition: transform .2s; }
    .action-card:hover .card-cta .arrow-icon { transform: translateX(4px); }

    /* ══════════════════════════════════════════════════════════════
       BOTTOM ROW — Synchro Launch + Recent Sessions
    ══════════════════════════════════════════════════════════════ */
    .bottom-grid {
        display: grid;
        grid-template-columns: 1fr 1.4fr;
        gap: 20px;
    }

    /* Synchro Launch Card */
    .synchro-launch {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 55%, #3730a3 100%);
        border-radius: 26px;
        padding: 32px;
        position: relative;
        overflow: hidden;
        border: 1.5px solid rgba(99,102,241,.35);
        box-shadow: 0 12px 35px rgba(99,102,241,.2);
        display: flex;
        flex-direction: column;
        text-decoration: none;
    }
    .synchro-launch::before {
        content: '';
        position: absolute;
        top: -50px; right: -50px;
        width: 160px; height: 160px;
        background: rgba(99,102,241,.2);
        border-radius: 50%;
    }
    .synchro-launch::after {
        content: '';
        position: absolute;
        bottom: -40px; left: -20px;
        width: 120px; height: 120px;
        background: rgba(139,92,246,.15);
        border-radius: 50%;
    }
    .synchro-inner { position: relative; z-index: 1; }
    .synchro-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.2);
        color: rgba(255,255,255,.85);
        font-size: 10px;
        font-weight: 800;
        padding: 5px 12px;
        border-radius: 20px;
        margin-bottom: 18px;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .synchro-badge .pulse-dot {
        width: 6px; height: 6px;
        background: #ef4444;
        border-radius: 50%;
        animation: livePulse 1.2s ease-in-out infinite;
    }
    .synchro-title {
        font-family: 'Luckiest Guy', cursive;
        font-size: 26px;
        color: white;
        letter-spacing: 1px;
        margin-bottom: 8px;
        text-shadow: 0 2px 10px rgba(0,0,0,.3);
    }
    .synchro-desc {
        font-size: 13px;
        color: rgba(255,255,255,.55);
        font-weight: 600;
        line-height: 1.5;
        margin-bottom: 28px;
    }
    .synchro-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        padding: 14px 26px;
        border-radius: 15px;
        font-family: 'Lexend', sans-serif;
        font-weight: 800;
        font-size: 15px;
        cursor: pointer;
        transition: .25s;
        text-decoration: none;
        align-self: flex-start;
        box-shadow: 0 6px 18px rgba(99,102,241,.4);
    }
    .synchro-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(99,102,241,.55);
        background: linear-gradient(135deg, #4f46e5, #6366f1);
    }

    /* Recent Sessions Panel */
    .recent-panel {
        background: var(--surface);
        border-radius: 26px;
        border: 1.5px solid var(--border);
        overflow: hidden;
    }
    .panel-header {
        padding: 22px 28px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border);
    }
    .panel-header h3 {
        font-family: 'Lexend', sans-serif;
        font-size: 16px;
        font-weight: 800;
        color: var(--brand-dark);
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .panel-header h3 i { color: var(--gold); }
    .panel-view-all {
        font-size: 12px;
        font-weight: 800;
        color: var(--brand-green);
        text-decoration: none;
        padding: 6px 14px;
        background: var(--brand-light);
        border-radius: 20px;
        transition: .2s;
    }
    .panel-view-all:hover { background: #c6f6d5; }

    .session-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px 28px;
        border-bottom: 1px solid var(--border);
        transition: .2s;
    }
    .session-row:last-child { border-bottom: none; }
    .session-row:hover { background: #f9fffe; }

    .session-icon-sm {
        width: 44px; height: 44px;
        border-radius: 13px;
        background: linear-gradient(135deg, var(--purple), #818cf8);
        display: flex; align-items: center; justify-content: center;
        font-size: 19px;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(99,102,241,.25);
    }

    .session-info { flex: 1; min-width: 0; }
    .session-title-sm {
        font-weight: 800;
        font-size: 14px;
        color: var(--text-dark);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 3px;
    }
    .session-meta-sm {
        font-size: 11px;
        color: var(--text-gray);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .session-status-badge {
        font-size: 10px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: .04em;
        flex-shrink: 0;
    }
    .status-finished { background: #dcfce7; color: #15803d; }
    .status-started  { background: #fef9c3; color: #854d0e; }
    .status-waiting  { background: #f0f9ff; color: #0369a1; }

    /* Empty recent */
    .empty-recent {
        padding: 50px 28px;
        text-align: center;
        color: var(--text-gray);
    }
    .empty-recent i { font-size: 36px; opacity: .25; margin-bottom: 12px; display: block; }
    .empty-recent p { font-size: 13px; font-weight: 700; }

    /* ══════════════════════════════════════════════════════════════
       NAME EDIT MODAL
    ══════════════════════════════════════════════════════════════ */
    .modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        backdrop-filter: blur(8px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    .modal-overlay.open { display: flex; }

    .name-modal {
        background: white;
        border-radius: 28px;
        width: 90%;
        max-width: 420px;
        overflow: hidden;
        animation: modalPop .3s cubic-bezier(.34,1.56,.64,1);
        box-shadow: 0 30px 60px rgba(0,0,0,.15);
    }
    @keyframes modalPop {
        from { transform: scale(.85); opacity: 0; }
        to   { transform: scale(1);   opacity: 1; }
    }

    .modal-top {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand-mid));
        padding: 30px 30px 26px;
        position: relative;
    }
    .modal-top h2 {
        font-family: 'Luckiest Guy', cursive;
        font-size: 22px;
        color: white;
        letter-spacing: 1px;
        margin-bottom: 4px;
    }
    .modal-top p { font-size: 13px; color: rgba(255,255,255,.6); font-weight: 600; }
    .modal-close {
        position: absolute;
        top: 18px; right: 20px;
        background: rgba(255,255,255,.15);
        border: none;
        color: white;
        width: 32px; height: 32px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 16px;
        display: flex; align-items: center; justify-content: center;
        transition: .2s;
    }
    .modal-close:hover { background: rgba(255,255,255,.28); }

    .modal-body { padding: 28px 30px 30px; }

    .input-label {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-gray);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 10px;
        display: block;
    }
    .name-input-field {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid var(--border);
        border-radius: 14px;
        font-family: 'Nunito', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: var(--text-dark);
        outline: none;
        transition: .2s;
        background: #f8fafc;
        margin-bottom: 8px;
    }
    .name-input-field:focus {
        border-color: var(--brand-green);
        background: white;
        box-shadow: 0 0 0 4px rgba(29,185,104,.08);
    }

    .input-hint {
        font-size: 11px;
        color: var(--text-gray);
        font-weight: 600;
        margin-bottom: 22px;
    }

    #name-error {
        font-size: 12px;
        color: #ef4444;
        font-weight: 700;
        margin-bottom: 14px;
        display: none;
    }

    .modal-actions { display: flex; gap: 12px; }
    .btn-cancel-sm {
        flex: 1;
        padding: 13px;
        border-radius: 14px;
        border: 2px solid var(--border);
        background: white;
        font-family: 'Nunito', sans-serif;
        font-weight: 800;
        color: var(--text-gray);
        cursor: pointer;
        transition: .2s;
        font-size: 14px;
    }
    .btn-cancel-sm:hover { background: var(--bg); }
    .btn-save-name {
        flex: 2;
        padding: 13px;
        border-radius: 14px;
        border: none;
        background: linear-gradient(135deg, var(--brand-green), #25d075);
        color: white;
        font-family: 'Nunito', sans-serif;
        font-weight: 800;
        font-size: 14px;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(29,185,104,.3);
        transition: .2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-save-name:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(29,185,104,.4); }
    .btn-save-name:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    /* ══════════════════════════════════════════════════════════════
       TOAST
    ══════════════════════════════════════════════════════════════ */
    .toast {
        position: fixed;
        bottom: 28px; right: 28px;
        background: var(--brand-dark);
        color: white;
        padding: 14px 22px;
        border-radius: 16px;
        font-weight: 800;
        font-size: 14px;
        z-index: 99999;
        transform: translateY(80px);
        opacity: 0;
        transition: .3s;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,.2);
    }
    .toast.show { transform: translateY(0); opacity: 1; }
    .toast.success { background: var(--brand-green); }
    .toast.error   { background: #ef4444; }

    /* ══════════════════════════════════════════════════════════════
       ANIMATIONS
    ══════════════════════════════════════════════════════════════ */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .hero-banner   { animation: fadeUp .5s ease both; }
    .cards-grid    { animation: fadeUp .5s ease .1s both; }
    .bottom-grid   { animation: fadeUp .5s ease .2s both; }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════ -->
<div class="sidebar">
    <div class="logo-box">
        <img src="pinnaquest logo.JPG" alt="PinnaQuest">
    </div>

    <p class="menu-heading">Menu</p>
    <nav>
        <a href="teacherdashboard.php" class="nav-link active">
            <i class="fa-solid fa-house"></i> Dashboard
        </a>
        <a href="teacher_materials.php" class="nav-link">
            <i class="fa-solid fa-file-invoice"></i> Materials
        </a>
        <a href="teacher_session_history.php" class="nav-link">
            <i class="fa-solid fa-chart-bar"></i> Session History
        </a>
        <a href="student_progress_report.php" class="nav-link">
            <i class="fa-solid fa-chart-line"></i> Student Progress
        </a>
        <a href="synchro_manage.php" class="nav-link">
            <i class="fa-solid fa-bolt"></i> Synchro-Quiz
            <span class="live-dot"></span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <!-- Prof card — click to edit name -->
        <div class="prof-card" onclick="openNameModal()" title="Edit your name">
            <div class="prof-avatar" id="sidebar-avatar-initial">
                <?php echo $initial; ?>
            </div>
            <div class="prof-info">
                <h4 id="sidebar-name"><?php echo htmlspecialchars($display_name); ?></h4>
                <span>Professor · UC</span>
            </div>
            <i class="fa-solid fa-pen prof-edit-icon"></i>
        </div>
        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     MAIN
═══════════════════════════════════════════ -->
<div class="main">
    <div class="breadcrumb">
        <i class="fa-solid fa-table-columns" style="color:var(--brand-green)"></i>
        <span>Dashboard</span>
    </div>

    <!-- ── HERO BANNER ── -->
    <div class="hero-banner">
        <div class="hero-inner">
            <div class="hero-avatar-ring">
                <span class="hero-initial" id="hero-initial"><?php echo $initial; ?></span>
            </div>
            <div class="hero-text">
                <div class="hero-eyebrow">Professor HQ</div>
                <div class="hero-title" id="hero-name">
                    Welcome back,<br><?php echo htmlspecialchars($display_name); ?>!
                </div>
                <div class="hero-subtitle">
                    Your command center for forging quests and tracking student progress.
                </div>
            </div>
            <div class="hero-stats">
                <div class="hero-stat-pill">
                    <span class="hero-stat-val green"><?php echo $mat_count; ?></span>
                    <span class="hero-stat-lbl">Materials</span>
                </div>
                <div class="hero-stat-pill">
                    <span class="hero-stat-val gold"><?php echo $sess_count; ?></span>
                    <span class="hero-stat-lbl">Sessions</span>
                </div>
                <div class="hero-stat-pill">
                    <span class="hero-stat-val"><?php echo $part_count; ?></span>
                    <span class="hero-stat-lbl">Students</span>
                </div>
                <div class="hero-stat-pill">
                    <span class="hero-stat-val gold"><?php echo $fin_count; ?></span>
                    <span class="hero-stat-lbl">Completed</span>
                </div>
            </div>
        </div>
        <div class="hero-ticker">
            <div class="ticker-item">
                <div class="ticker-dot" style="background:var(--brand-green)"></div>
                <?php echo $mat_count; ?> material<?php echo $mat_count !== 1 ? 's' : ''; ?> uploaded
            </div>
            <div class="ticker-item">
                <div class="ticker-dot" style="background:var(--gold)"></div>
                <?php echo $sess_count; ?> synchro session<?php echo $sess_count !== 1 ? 's' : ''; ?> created
            </div>
            <div class="ticker-item">
                <div class="ticker-dot" style="background:#6ee7b7"></div>
                <?php echo $part_count; ?> unique student<?php echo $part_count !== 1 ? 's' : ''; ?> participated
            </div>
            <div class="ticker-item" style="margin-left:auto; color:rgba(255,255,255,.35);">
                <i class="fa-solid fa-clock" style="font-size:10px;"></i>
                <?php echo date('M d, Y'); ?>
            </div>
        </div>
    </div>

    <!-- ── ACTION CARDS ── -->
    <div class="cards-grid">

        <!-- Materials -->
        <a href="teacher_materials.php" class="action-card" style="text-decoration:none;">
            <div class="card-stripe stripe-green"></div>
            <div class="card-body-inner">
                <div class="card-icon-box icon-bg-green">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div class="card-title">Professor's Vault</div>
                <div class="card-desc">
                    Upload and manage PDF materials. Your uploaded files power the AI question engine.
                </div>
                <div class="card-cta">
                    <span>Manage Materials</span>
                    <i class="fa-solid fa-arrow-right arrow-icon"></i>
                </div>
            </div>
        </a>

        <!-- Session History -->
        <a href="teacher_session_history.php" class="action-card" style="text-decoration:none;">
            <div class="card-stripe stripe-gold"></div>
            <div class="card-body-inner">
                <div class="card-icon-box icon-bg-gold">
                    <i class="fa-solid fa-chart-bar"></i>
                </div>
                <div class="card-title">Session History</div>
                <div class="card-desc">
                    Review past Synchro-Quiz sessions, student rankings, accuracy rates, and participation logs.
                </div>
                <div class="card-cta">
                    <span>View Records</span>
                    <i class="fa-solid fa-arrow-right arrow-icon"></i>
                </div>
            </div>
        </a>

        <!-- Student Progress -->
        <a href="student_progress_report.php" class="action-card" style="text-decoration:none;">
            <div class="card-stripe" style="background: linear-gradient(90deg, #0d9488, #14b8a6);"></div>
            <div class="card-body-inner">
                <div class="card-icon-box" style="background: linear-gradient(135deg, #ccfbf1, #99f6e4); color: #0d9488;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="card-title">Student Progress</div>
                <div class="card-desc">
                    See each student's SynchroQuiz score and accuracy trend from one quiz to the next.
                </div>
                <div class="card-cta">
                    <span>View Progress</span>
                    <i class="fa-solid fa-arrow-right arrow-icon"></i>
                </div>
            </div>
        </a>

    </div>

    <!-- ── BOTTOM ROW ── -->
    <div class="bottom-grid">

        <!-- Synchro Launch -->
        <div class="synchro-launch">
            <div class="synchro-inner">
                <div class="synchro-badge">
                    <span class="pulse-dot"></span>
                    Live Feature
                </div>
                <div class="synchro-title">⚡ Synchro-Quiz</div>
                <div class="synchro-desc">
                    Host a real-time quiz battle. Students join via room code and compete live on the leaderboard.
                </div>
                <a href="synchro_manage.php" class="synchro-btn">
                    <i class="fa-solid fa-tower-broadcast"></i>
                    Launch a Session
                </a>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="recent-panel">
            <div class="panel-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Sessions</h3>
                <a href="teacher_session_history.php" class="panel-view-all">View All →</a>
            </div>

            <?php if (empty($recent_sessions)): ?>
            <div class="empty-recent">
                <i class="fa-solid fa-bolt"></i>
                <p>No sessions yet — launch your first Synchro-Quiz!</p>
            </div>
            <?php else: ?>
            <?php foreach ($recent_sessions as $rs):
                $sc = $rs['status'];
                $badge_class = $sc === 'finished' ? 'status-finished'
                             : ($sc === 'started'  ? 'status-started' : 'status-waiting');
                $badge_label = strtoupper($sc);
                $created     = date('M d · h:i A', strtotime($rs['created_at']));
            ?>
            <div class="session-row">
                <div class="session-icon-sm">⚡</div>
                <div class="session-info">
                    <div class="session-title-sm"><?php echo htmlspecialchars($rs['title']); ?></div>
                    <div class="session-meta-sm">
                        <span><?php echo $rs['room_code']; ?></span>
                        <span>·</span>
                        <span><?php echo $rs['pcount']; ?> players</span>
                        <span>·</span>
                        <span><?php echo $created; ?></span>
                    </div>
                </div>
                <span class="session-status-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════
     NAME EDIT MODAL
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="nameModal">
    <div class="name-modal">
        <div class="modal-top">
            <h2>Edit Your Name</h2>
            <p>Update your display name across PinnaQuest.</p>
            <button class="modal-close" onclick="closeNameModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <label class="input-label" for="nameInput">Display Name</label>
            <input type="text" class="name-input-field" id="nameInput"
                   value="<?php echo htmlspecialchars($display_name); ?>"
                   placeholder="Enter your name..." maxlength="30">
            <div class="input-hint">Max 30 characters. This name appears across all sessions.</div>
            <div id="name-error"></div>
            <div class="modal-actions">
                <button class="btn-cancel-sm" onclick="closeNameModal()">Cancel</button>
                <button class="btn-save-name" id="saveNameBtn" onclick="saveName()">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// ── Modal ──────────────────────────────────────────────────────────
const modal = document.getElementById('nameModal');
function openNameModal()  { modal.classList.add('open'); document.getElementById('nameInput').focus(); }
function closeNameModal() { modal.classList.remove('open'); }
window.addEventListener('click', e => { if (e.target === modal) closeNameModal(); });

// ── Save Name ──────────────────────────────────────────────────────
async function saveName() {
    const input   = document.getElementById('nameInput');
    const errEl   = document.getElementById('name-error');
    const btn     = document.getElementById('saveNameBtn');
    const name    = input.value.trim();

    errEl.style.display = 'none';

    if (!name) {
        errEl.innerText = 'Name cannot be empty.';
        errEl.style.display = 'block';
        return;
    }
    if (name.length > 30) {
        errEl.innerText = 'Name too long (max 30 characters).';
        errEl.style.display = 'block';
        return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    try {
        const res  = await fetch('update_persona.php', {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : `display_name=${encodeURIComponent(name)}&avatar_key=default`,
        });
        const data = await res.json();

        if (data.success) {
            // Update all displayed names
            document.getElementById('sidebar-name').innerText    = name;
            document.getElementById('sidebar-avatar-initial').innerText = data.initial;
            document.getElementById('hero-initial').innerText    = data.initial;
            document.getElementById('hero-name').innerHTML       =
                `Welcome back,<br>${escHtml(name)}!`;

            closeNameModal();
            showToast('✅ Name updated successfully!', 'success');
        } else {
            errEl.innerText     = data.error || 'Something went wrong.';
            errEl.style.display = 'block';
        }
    } catch(e) {
        errEl.innerText     = 'Network error. Please try again.';
        errEl.style.display = 'block';
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
}

// Allow Enter key to save
document.getElementById('nameInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') saveName();
});

// ── Toast ──────────────────────────────────────────────────────────
function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.innerText   = msg;
    t.className   = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3000);
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>