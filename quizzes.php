<?php
session_start();
include_once('db.php');
require_once __DIR__ . '/xp_policy.php';
require_once __DIR__ . '/synchro_identity.php';
if (!isset($_SESSION['user_id'])) { header("Location: loginpanel.php"); exit(); }
$user_id = intval($_SESSION['user_id']);
ensureQuizXpSchema($conn);
ensureSynchroIdentitySchema($conn);
$u = $conn->query("SELECT * FROM users WHERE id=$user_id");
$user = $u ? $u->fetch_assoc() : [];
$display_name = !empty($user['display_name']) ? $user['display_name'] : ($user['full_name'] ?? 'Student');
$initial = strtoupper(mb_substr($display_name, 0, 1));
$level = max(1, floor(intval($user['xp'] ?? 0) / PINNAQUEST_XP_PER_LEVEL) + 1);

// ── Student quiz history ──────────────────────────────────────────
// Both history types are tied to the account. Synchro records use user_id,
// not a reusable nickname, so another student's similarly named record cannot
// appear in this student's history.
$solo_history = [];
$solo_history_res = $conn->query(
    "SELECT id, quiz_title, score, correct_answers, total_questions, xp_earned, completed_at
     FROM solo_quiz_results
     WHERE user_id = $user_id
     ORDER BY completed_at DESC, id DESC
     LIMIT 50"
);
if ($solo_history_res) {
    while ($history_row = $solo_history_res->fetch_assoc()) {
        $solo_history[] = $history_row;
    }
}

$synchro_history = [];
{
    $synchro_history_res = $conn->query(
        "SELECT sc.id AS score_id, s.id, s.title, s.room_code, s.quiz_type, s.difficulty, s.item_count,
                s.created_at, s.status, sc.total_score, sc.correct_answers, sc.streak
         FROM synchro_scores sc
         INNER JOIN synchro_sessions s ON s.id = sc.session_id
         WHERE sc.user_id = $user_id
           AND s.status = 'finished'
         ORDER BY s.created_at DESC, sc.id DESC
         LIMIT 50"
    );
    if ($synchro_history_res) {
        while ($history_row = $synchro_history_res->fetch_assoc()) {
            $synchro_history[] = $history_row;
        }
    }
}

$history_total = count($solo_history) + count($synchro_history);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Quizzes</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />

    <style>
      :root {
        --brand-green: #1db968;
        --brand-dark-green: #1a4d2e;
        --quiz-gold: #ebb412;
        --quiz-gold-dark: #d49d10;
        --soft-gold: #fdf6e3;
        --sidebar-white: #ffffff;
        --text-dark: #1a202c;
        --text-gray: #718096;
        --border-color: #f1f5f9;
        --bg-light: #fcfdfa;
        --glow-green: rgba(29, 185, 104, 0.03);
        
        /* Iba't ibang kulay para sa icons */
        --synchro-purple: #6366f1; 
        --icon-materials: #3b82f6; 
        --icon-quizzes: #f59e0b;   
        --icon-leaderboard: #10b981;
      }

      /* Custom Persona Colors matching your dashboard */
      .persona-link-style {
        color: #94a3b8 !important;
      }
      .persona-link-style:hover {
        background-color: #f0f7ff !important;
        color: #3b82f6 !important;
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: "Inter", sans-serif;
        background:
          radial-gradient(
            circle at 10% 10%,
            var(--glow-green) 0%,
            transparent 40%
          ),
          #fcfdfa;
        background-attachment: fixed;
        display: flex;
        min-height: 100vh;
        color: var(--text-dark);
      }

      /* --- SIDEBAR (Standardized) --- */
      .sidebar {
        width: 260px;
        background: var(--sidebar-white);
        height: 100vh;
        display: flex;
        flex-direction: column;
        padding: 30px 20px;
        position: fixed;
        top: 0;
        left: 0;
        border-right: 1px solid var(--border-color);
        z-index: 1000;
      }

      .logo-box {
        margin-bottom: 40px;
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
      }

      .logo-box img {
        width: 180px;
        height: auto;
        transition: transform 0.3s ease;
        cursor: pointer;
      }

      .logo-box img:hover {
        transform: scale(1.08);
      }

      .menu-heading {
        font-size: 11px;
        font-weight: 700;
        color: #cbd5e0;
        text-transform: uppercase;
        margin: 20px 0 10px 10px;
        letter-spacing: 0.05em;
      }

      .nav-link {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px 18px;
        text-decoration: none;
        color: var(--text-gray);
        font-weight: 500;
        font-size: 14px;
        border-radius: 12px;
        margin-bottom: 5px;
        transition: 0.2s;
      }

      .nav-link.active {
        background-color: var(--brand-green);
        color: white !important;
      }

      /* Puti ang icon kapag active */
      .nav-link.active i {
        color: white !important;
        text-shadow: none !important;
      }

      .nav-link:hover:not(.active) {
        background: #f0fff4;
        color: var(--brand-green);
      }

      /* --- ICON COLORING & GLOW --- */
      .nav-link i.fa-house { color: var(--brand-green); text-shadow: 0 0 8px rgba(29, 185, 104, 0.4); }
      .nav-link i.fa-file-invoice { color: var(--icon-materials); text-shadow: 0 0 8px rgba(59, 130, 246, 0.4); }
      .nav-link i.fa-brain { color: var(--icon-quizzes); text-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
      .nav-link i.fa-bolt { color: var(--synchro-purple); text-shadow: 0 0 8px rgba(99, 102, 241, 0.4); }
      .nav-link i.fa-trophy { color: var(--icon-leaderboard); text-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
      .nav-link i.fa-user-astronaut { color: #3b82f6; text-shadow: 0 0 8px rgba(59, 130, 246, 0.3); }

      .sidebar-footer {
        margin-top: auto;
      }

      .user-profile-bottom {
        margin-top: auto;
        background: #f8fafc;
        padding: 15px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
      }

      .user-profile-bottom .avatar {
        width: 35px;
        height: 35px;
        background: var(--brand-green);
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
      }
        .sidebar-avatar{width:35px;height:35px;background:var(--brand-green);border-radius:8px;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;flex-shrink:0;}


      .user-details h4 {
        font-size: 13px;
        font-weight: 700;
        color: #2d3748;
      }

      .user-details p {
        font-size: 11px;
        color: var(--text-gray);
      }

      .logout-link {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: var(--text-gray);
        font-size: 13px;
        font-weight: 600;
        padding: 10px 15px;
        transition: 0.2s;
      }

      .logout-link:hover {
        color: #e53e3e;
      }

      /* --- MAIN CONTENT --- */
      .main {
        flex: 1;
        margin-left: 260px;
        padding: 30px 50px;
      }

      .breadcrumb {
        font-size: 14px;
        color: var(--text-gray);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 30px;
      }

      /* --- QUEST BOARD HEADER --- */
      .quest-header {
        background: white;
        padding: 25px 35px;
        border-radius: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid var(--border-color);
        margin-bottom: 30px;
      }

      .header-title-container {
        display: flex;
        align-items: flex-start;
        gap: 15px;
      }

      .header-icon {
        font-size: 24px;
        color: var(--quiz-gold);
        margin-top: 5px;
      }

      .header-title h2 {
        font-family: "Lexend";
        font-weight: 800;
        font-size: 28px;
        color: var(--brand-dark-green);
        letter-spacing: -0.02em;
      }

      .header-title p {
        color: var(--text-gray);
        font-size: 14px;
        margin-top: 4px;
      }

      /* --- FORGE BUTTON --- */
      .btn-forge {
        background: var(--quiz-gold);
        color: #1a1a1a;
        border: none;
        padding: 14px 28px;
        border-radius: 14px;
        font-family: "Lexend";
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        box-shadow: 0 4px 0px var(--brand-dark-green);
        transition: all 0.1s ease;
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .btn-forge:hover {
        background: #f5bc16;
        transform: translateY(-1px);
      }

      .btn-forge:active {
        transform: translateY(2px);
        box-shadow: 0 2px 0px var(--brand-dark-green);
      }

      /* --- EMPTY STATE --- */
      .empty-state-card {
        background: white;
        border: 2px dashed #e2e8f0;
        border-radius: 28px;
        padding: 100px 20px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      .empty-icon-box {
        width: 70px;
        height: 70px;
        background: var(--soft-gold);
        color: #8b6e37;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 20px;
      }

      .empty-state-card h3 {
        font-family: "Lexend";
        font-size: 22px;
        color: var(--brand-dark-green);
        margin-bottom: 10px;
        font-weight: 800;
      }

      .empty-state-card p {
        color: var(--text-gray);
        max-width: 400px;
        font-size: 14px;
        line-height: 1.6;
      }

      /* --- QUIZ HISTORY --- */
      .history-section {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 24px;
        margin-top: 30px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
      }
      .history-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 22px;
      }
      .history-heading h3 {
        color: var(--brand-dark-green);
        font-family: "Lexend";
        font-size: 22px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .history-heading h3 i { color: var(--brand-green); }
      .history-heading p {
        color: var(--text-gray);
        font-size: 13px;
        margin-top: 5px;
      }
      .history-count {
        background: #f0fff4;
        color: #15803d;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
      }
      .history-tabs {
        display: flex;
        gap: 8px;
        padding-bottom: 18px;
        border-bottom: 1px solid #f1f5f9;
      }
      .history-tab {
        border: 1px solid #e2e8f0;
        background: white;
        color: #64748b;
        border-radius: 10px;
        padding: 9px 14px;
        font: 700 12px "Inter";
        cursor: pointer;
        transition: .2s;
      }
      .history-tab.active, .history-tab:hover {
        background: var(--brand-dark-green);
        border-color: var(--brand-dark-green);
        color: white;
      }
      .history-list { display: grid; gap: 10px; margin-top: 18px; }
      .history-row {
        display: grid;
        grid-template-columns: minmax(210px, 1fr) auto auto auto;
        align-items: center;
        gap: 18px;
        padding: 15px 16px;
        border: 1px solid #f1f5f9;
        border-radius: 16px;
        background: #fcfdfa;
        transition: .2s;
      }
      .history-row:hover {
        border-color: #ccefdc;
        box-shadow: 0 5px 15px rgba(29, 185, 104, .07);
        transform: translateY(-1px);
      }
      .history-title {
        color: #1f2937;
        font-weight: 800;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .history-title i { color: var(--quiz-gold); width: 18px; text-align: center; }
      .history-meta {
        color: #94a3b8;
        font-size: 11px;
        margin-top: 5px;
        padding-left: 28px;
      }
      .history-type {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 999px;
        padding: 6px 9px;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
      }
      .history-type.solo { color: #a16207; background: #fef9c3; }
      .history-type.synchro { color: #4338ca; background: #eef2ff; }
      .history-stat { text-align: right; min-width: 70px; }
      .history-stat strong { display: block; color: #1f2937; font: 800 14px "Lexend"; }
      .history-stat span { display: block; color: #94a3b8; font-size: 10px; margin-top: 3px; }
      .history-retake {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 0;
        border-radius: 10px;
        padding: 9px 11px;
        background: #ecfdf3;
        color: #15803d;
        font: 800 11px "Inter";
        text-decoration: none;
        white-space: nowrap;
        transition: .2s;
      }
      .history-retake:hover { background: #1db968; color: white; transform: translateY(-1px); }
      .history-delete, .delete-session-btn, .delete-student-btn {
        border: 0; cursor: pointer; font: 800 11px "Inter";
      }
      .history-delete {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        border-radius: 10px; padding: 9px 11px; background: #fff1f2; color: #be123c;
        white-space: nowrap;
      }
      .history-delete:hover { background: #e11d48; color: white; }
      .history-empty {
        color: #94a3b8;
        text-align: center;
        padding: 28px 15px 10px;
        font-size: 13px;
      }
      .history-empty i { color: #cbd5e1; font-size: 20px; margin-right: 6px; }
      @media (max-width: 760px) {
        .history-section { padding: 20px 16px; }
        .history-heading { display: block; }
        .history-count { display: inline-block; margin-top: 12px; }
        .history-row { grid-template-columns: 1fr auto; gap: 10px; }
        .history-row .history-type { grid-row: 2; }
        .history-row .history-stat { grid-row: 2; }
        .history-stat { min-width: auto; }
      }

      /* --- PERSONA MODAL STYLING (From Dashboard) --- */
      .persona-modal-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(8px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
      }
      .persona-modal-content {
        background: white;
        width: 90%;
        max-width: 450px;
        border-radius: 30px;
        padding: 30px;
        animation: slideUp 0.3s ease-out;
      }
      @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
      .avatar-gallery {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        margin-bottom: 25px;
      }
      .avatar-option {
        width: 60px; height: 60px;
        background: #f8fafc;
        border: 2px solid transparent;
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; cursor: pointer; transition: 0.3s; color: #64748b;
      }
      .avatar-option.active {
        border-color: var(--brand-green);
        background: #f0fff4;
        color: var(--brand-green);
      }
      .save-persona-btn {
        width: 100%;
        background: var(--brand-green);
        color: white;
        border: none;
        padding: 15px;
        border-radius: 15px;
        font-weight: 700;
        cursor: pointer;
      }

      /* Forge Modal Styles */
      .forge-modal { max-width: 480px; padding: 0; overflow: hidden; border: none; background: white; border-radius: 20px; margin: auto; }
      .forge-header { background: #eab308; color: #1a4d2e; padding: 25px 30px; }
      .forge-form { padding: 25px 30px; }
      .forge-form label { display: block; font-family: 'Inter'; font-weight: 600; font-size: 14px; color: #475569; margin-bottom: 8px; }
      .form-group { margin-bottom: 15px; }
      .forge-form input[type="text"], .forge-form input[type="number"], .forge-form select { width: 100%; padding: 12px 15px; border: 2px solid #f1f5f9; border-radius: 12px; outline: none; font-family: 'Inter'; transition: 0.2s; }
      .forge-form input:focus, .forge-form select:focus { border-color: #eab308; }
      .difficulty-options { display: flex; gap: 10px; }
      .diff-btn { flex: 1; text-align: center; padding: 12px; border: 2px solid #f1f5f9; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 13px; color: #94a3b8; transition: 0.3s; }
      input[type="radio"]:checked + .diff-btn { border-color: #eab308; background: #fefce8; color: #854d0e; }
      .forge-footer { display: flex; justify-content: flex-end; align-items: center; gap: 20px; margin-top: 30px; }
      .btn-cancel { background: none; border: none; color: #64748b; font-weight: 600; cursor: pointer; }
      .btn-forge-submit { background: #eab308; color: #1a4d2e; padding: 12px 30px; border-radius: 12px; border: none; font-weight: 700; font-family: 'Lexend'; cursor: pointer; box-shadow: 0 4px 0 #ca8a04; transition: 0.2s; }
      .btn-forge-submit:active { transform: translateY(3px); box-shadow: 0 1px 0 #ca8a04; }
      .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 2000; }
      .modal-overlay.active { display: flex !important; }

      /* --- TOAST NOTIFICATION (replaces browser alert() popups) --- */
      .toast {
        position: fixed;
        bottom: 28px;
        right: 28px;
        background: #1a202c;
        color: white;
        padding: 16px 20px;
        border-radius: 16px;
        font-weight: 600;
        font-size: 13.5px;
        line-height: 1.5;
        z-index: 99999;
        max-width: 380px;
        transform: translateY(100px);
        opacity: 0;
        pointer-events: none;
        transition: transform 0.35s cubic-bezier(.34,1.56,.64,1), opacity 0.25s;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.25);
      }
      .toast.show { transform: translateY(0); opacity: 1; pointer-events: all; }
      .toast.success { background: var(--brand-green); }
      .toast.error   { background: #e53e3e; }
      .toast i.toast-icon { font-size: 18px; margin-top: 1px; flex-shrink: 0; }
      .toast .toast-text { flex: 1; }
      .toast .toast-close {
        background: none; border: none; color: rgba(255,255,255,0.85);
        font-size: 16px; cursor: pointer; line-height: 1; padding: 0; margin-left: 4px;
        transition: 0.2s;
      }
      .toast .toast-close:hover { color: white; }

      /* --- CUSTOM CONFIRM MODAL (replaces browser confirm() "localhost says") --- */
.confirm-overlay {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(20, 69, 43, 0.35);
  backdrop-filter: blur(6px);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 10000;
}
.confirm-overlay.active { display: flex; }
.confirm-box {
  background: white;
  width: 90%;
  max-width: 380px;
  border-radius: 24px;
  padding: 30px;
  text-align: center;
  box-shadow: 0 25px 50px rgba(0,0,0,0.15);
  animation: confirmPop 0.25s cubic-bezier(.34,1.56,.64,1);
}
@keyframes confirmPop {
  from { transform: scale(0.9); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
.confirm-icon {
  width: 60px; height: 60px;
  background: #fff1f2;
  color: #e11d48;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 24px;
  margin: 0 auto 18px;
}
.confirm-box h3 {
  font-family: 'Lexend';
  font-size: 18px;
  font-weight: 800;
  color: var(--brand-dark-green);
  margin-bottom: 8px;
}
.confirm-box p {
  font-size: 13.5px;
  color: var(--text-gray);
  line-height: 1.5;
  margin-bottom: 24px;
}
.confirm-actions {
  display: flex;
  gap: 10px;
}
.confirm-actions button {
  flex: 1;
  padding: 13px;
  border-radius: 12px;
  border: none;
  font-family: 'Lexend';
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: 0.2s;
}
.confirm-btn-cancel {
  background: #f1f5f9;
  color: var(--text-gray);
}
.confirm-btn-cancel:hover { background: #e2e8f0; }
.confirm-btn-danger {
  background: #e11d48;
  color: white;
  box-shadow: 0 4px 0 #9f1239;
}
.confirm-btn-danger:hover { background: #be123c; }
.confirm-btn-danger:active { transform: translateY(3px); box-shadow: 0 1px 0 #9f1239; }
    </style>
  </head>
  <body>
    <div class="sidebar">
    <div class="logo-box"><img src="pinnaquest logo.JPG" alt="PinnaQuest"></div>
    <p class="menu-heading">Menu</p>
    <a href="studentdashboard.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a>
    <a href="materials.php" class="nav-link"><i class="fa-solid fa-file-invoice"></i> Materials</a>
    <a href="quizzes.php" class="nav-link active"><i class="fa-solid fa-brain"></i> Quizzes</a>
    <a href="synchro_portal.php" class="nav-link"><i class="fa-solid fa-bolt-lightning"></i> Synchro-Quiz</a>
    <a href="leaderboard.php" class="nav-link"><i class="fa-solid fa-trophy"></i> Mission Map</a>
    <a href="javascript:void(0)" class="nav-link persona-link-style" onclick="openPersona()"><i class="fa-solid fa-user-astronaut"></i> Quest Persona</a>
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

    <div class="main">
      <div class="breadcrumb">
        <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--quiz-gold)"></i>
        <span>Quizzes</span>
      </div>

      <div class="quest-header">
        <div class="header-title-container">
          <i class="fa-solid fa-wand-magic-sparkles header-icon"></i>
          <div class="header-title">
            <h2>Quest Board</h2>
            <p>Select a quiz to test your knowledge and earn XP.</p>
          </div>
        </div>
        <button class="btn-forge" id="openForgeBtn">
          <i class="fa-solid fa-fire"></i> Forge New Quiz
        </button>
      </div>

      <div class="empty-state-card">
        <div class="empty-icon-box">
          <i class="fa-solid fa-brain"></i>
        </div>
        <h3>No quests available</h3>
        <p>Forge a new quiz from your materials to start playing.</p>
      </div>

      <section class="history-section" aria-labelledby="history-title">
        <div class="history-heading">
          <div>
            <h3 id="history-title"><i class="fa-solid fa-clock-rotate-left"></i> Quiz History</h3>
            <p>Your completed solo quizzes and SynchroQuiz battles in one place.</p>
          </div>
          <span class="history-count"><?php echo $history_total; ?> <?php echo $history_total === 1 ? 'attempt' : 'attempts'; ?></span>
        </div>

        <div class="history-tabs" role="tablist" aria-label="Quiz history filters">
          <button class="history-tab active" type="button" data-history-filter="all">All</button>
          <button class="history-tab" type="button" data-history-filter="solo">Solo Quiz</button>
          <button class="history-tab" type="button" data-history-filter="synchro">SynchroQuiz</button>
        </div>

        <div class="history-list" id="historyList">
          <?php foreach ($solo_history as $history): ?>
            <?php
              $solo_total = max(0, intval($history['total_questions']));
              $solo_correct = max(0, intval($history['correct_answers']));
              $solo_percent = $solo_total > 0 ? round(($solo_correct / $solo_total) * 100) : 0;
              $solo_date = !empty($history['completed_at']) ? date('M d, Y · g:i A', strtotime($history['completed_at'])) : 'Date unavailable';
            ?>
            <article class="history-row" data-history-type="solo">
              <div>
                <div class="history-title"><i class="fa-solid fa-brain"></i><?php echo htmlspecialchars($history['quiz_title'] ?: 'Solo Quiz'); ?></div>
                <div class="history-meta"><?php echo htmlspecialchars($solo_date); ?> · <?php echo $solo_total; ?> questions</div>
              </div>
              <span class="history-type solo"><i class="fa-solid fa-user"></i> SOLO QUIZ</span>
              <div class="history-stat"><strong><?php echo $solo_correct; ?>/<?php echo $solo_total; ?></strong><span>correct</span></div>
              <div class="history-stat"><strong><?php echo $solo_percent; ?>%</strong><span><?php echo intval($history['xp_earned']); ?> XP earned</span></div>
              <a class="history-retake" href="retake_quiz.php?result_id=<?php echo intval($history['id']); ?>" title="Retake this solo quiz">
                <i class="fa-solid fa-rotate-right"></i> Retake
              </a>
              <button class="history-delete" type="button" onclick="deleteHistory('solo', <?php echo intval($history['id']); ?>)">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </article>
          <?php endforeach; ?>

          <?php foreach ($synchro_history as $history): ?>
            <?php
              $synchro_total = max(0, intval($history['item_count']));
              $synchro_correct = max(0, intval($history['correct_answers']));
              $synchro_percent = $synchro_total > 0 ? round(($synchro_correct / $synchro_total) * 100) : 0;
              $synchro_date = !empty($history['created_at']) ? date('M d, Y · g:i A', strtotime($history['created_at'])) : 'Date unavailable';
              $synchro_type = str_replace('_', ' ', (string)($history['quiz_type'] ?? 'quiz'));
            ?>
            <article class="history-row" data-history-type="synchro">
              <div>
                <div class="history-title"><i class="fa-solid fa-bolt-lightning"></i><?php echo htmlspecialchars($history['title'] ?: 'SynchroQuiz'); ?></div>
                <div class="history-meta"><?php echo htmlspecialchars($synchro_date); ?> · Room <?php echo htmlspecialchars($history['room_code']); ?> · <?php echo $synchro_total; ?> questions</div>
              </div>
              <span class="history-type synchro"><i class="fa-solid fa-users"></i> SYNCHROQUIZ</span>
              <div class="history-stat"><strong><?php echo $synchro_correct; ?>/<?php echo $synchro_total; ?></strong><span><?php echo $synchro_percent; ?>% correct</span></div>
              <div class="history-stat"><strong><?php echo $synchro_correct; ?>/<?php echo $synchro_total; ?></strong><span><?php echo $synchro_percent; ?>% result</span></div>
              <button class="history-delete" type="button" onclick="deleteHistory('synchro', <?php echo intval($history['score_id']); ?>)">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </article>
          <?php endforeach; ?>

          <?php if ($history_total === 0): ?>
            <div class="history-empty" data-history-empty="true">
              <i class="fa-solid fa-hourglass-start"></i> No completed quizzes yet. Your results will appear here after your first quest.
            </div>
          <?php endif; ?>
        </div>
      </section>

     <div class="modal-overlay" id="forgeModal">
        <div class="modal-content forge-modal">
            <div class="forge-header">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 style="font-family:'Lexend'; font-weight:800; font-size:24px;">Forge a New Quiz</h2>
                    <button type="button" id="closeForgeBtn" style="background:none; border:none; font-size:20px; cursor:pointer; color:rgba(0,0,0,0.3);">&times;</button>
                </div>
                <p style="font-size:13px; margin-top:5px; opacity:0.8;">The system will use rules to generate questions from your selected material.</p>
            </div>

            <form action="forge_logic.php" method="POST" class="forge-form">
    <div class="form-group">
        <label>Source Material</label>
        <select name="source_material" required>
            <option value="" disabled selected>Select a material...</option>
            <?php
            include_once("db.php");
            $mats = $conn->query("SELECT id, title, display_name, file_path FROM materials WHERE user_id = $user_id ORDER BY COALESCE(display_name, title) ASC");
            while($m = $mats->fetch_assoc()) {
                $label = !empty($m['display_name']) ? $m['display_name'] : $m['title'];
                echo "<option value='".htmlspecialchars($m['file_path'])."'>"
                    .htmlspecialchars($label)." (".htmlspecialchars($m['title']).")</option>";
            }
            ?>
        </select>
    </div>

    <div class="form-group">
        <label>Quest Title</label>
        <input type="text" name="quest_title" placeholder="e.g. Cell Biology Challenge" required />
    </div>

    <div class="form-group">
        <label>Difficulty Level</label>
        <div class="difficulty-options">
            <input type="radio" name="difficulty" value="easy" id="easy" checked hidden>
            <label for="easy" class="diff-btn">EASY</label>
            
            <input type="radio" name="difficulty" value="medium" id="medium" hidden>
            <label for="medium" class="diff-btn">MODERATE</label>
            
            <input type="radio" name="difficulty" value="hard" id="hard" hidden>
            <label for="hard" class="diff-btn">DIFFICULT</label>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
        <div class="form-group">
            <label>Quiz Type</label>
            <select name="quiz_type">
                <option value="multiple_choice">Multiple Choice</option>
                <option value="fill_blanks">Fill in the Blanks</option>
            </select>
        </div>
        <div class="form-group">
            <label>Questions</label>
    <input type="number" name="item_count" min="5" max="30" value="10" />
        </div>
    </div>

    <div class="form-group">
    <label>Time Limit (Optional)</label>
    <select name="timer_mins"> <option value="0">No Timer</option>
        <option value="5">5 Minutes</option>
        <option value="10">10 Minutes</option>
        <option value="20">20 Minutes</option>
        <option value="30">30 Minutes</option>
    </select>
</div>

    <div class="forge-footer">
        <button type="button" id="cancelForgeBtn" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-forge-submit">Forge Quiz</button>
    </div>
</form>
        </div>
      </div>

      <div id="personaModal" class="persona-modal-overlay">
        <div class="persona-modal-content">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-family: 'Lexend'"><i class="fa-solid fa-user-astronaut" style="color: #3b82f6"></i> Edit Persona</h3>
            <button onclick="closePersona()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
          </div>
          <div class="avatar-gallery">
            <div class="avatar-option active" onclick="selectAvatar(this, 'Q')">Q</div>
            <div class="avatar-option" onclick="selectAvatar(this, 'ninja')"><i class="fa-solid fa-user-ninja"></i></div>
            <div class="avatar-option" onclick="selectAvatar(this, 'robot')"><i class="fa-solid fa-robot"></i></div>
            <div class="avatar-option" onclick="selectAvatar(this, 'ghost')"><i class="fa-solid fa-ghost"></i></div>
            <div class="avatar-option" onclick="selectAvatar(this, 'astro')"><i class="fa-solid fa-user-astronaut"></i></div>
          </div>
          <div style="margin-bottom: 15px">
            <label style="display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 5px;">Adventurer Name</label>
            <input type="text" id="name-input" value="qwer qwer" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; outline: none;" />
          </div>
          <button class="save-persona-btn" onclick="savePersona()">Save Changes</button>
        </div>
      </div>

      <!-- Custom Confirm Modal (replaces browser confirm()) -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <div class="confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
    <h3 id="confirmTitle">Delete this quiz history?</h3>
    <p id="confirmMessage">This action cannot be undone.</p>
    <div class="confirm-actions">
      <button class="confirm-btn-cancel" id="confirmCancelBtn">Cancel</button>
      <button class="confirm-btn-danger" id="confirmOkBtn">Delete</button>
    </div>
  </div>
</div>
    

    <!-- Toast notification container -->
    <div class="toast" id="toast"></div>

    <script>
      // --- FORGE MODAL LOGIC ---
      const forgeModal = document.getElementById("forgeModal");
      const openForgeBtn = document.getElementById("openForgeBtn");
      const closeForgeBtn = document.getElementById("closeForgeBtn");
      const cancelForgeBtn = document.getElementById("cancelForgeBtn");

      openForgeBtn.onclick = () => { forgeModal.classList.add("active"); };
      const closeForge = () => { forgeModal.classList.remove("active"); };
      closeForgeBtn.onclick = closeForge;
      cancelForgeBtn.onclick = closeForge;

      // --- HISTORY FILTERS ---
      const historyTabs = document.querySelectorAll("[data-history-filter]");
      const historyRows = document.querySelectorAll("[data-history-type]");
      historyTabs.forEach((tab) => {
        tab.addEventListener("click", () => {
          const filter = tab.dataset.historyFilter;
          historyTabs.forEach((item) => item.classList.toggle("active", item === tab));
          historyRows.forEach((row) => {
            row.style.display = filter === "all" || row.dataset.historyType === filter ? "grid" : "none";
          });
        });
      });

      function showConfirm(message) {
  return new Promise((resolve) => {
    const overlay = document.getElementById('confirmOverlay');
    const msgEl = document.getElementById('confirmMessage');
    const okBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');

    msgEl.textContent = message;
    overlay.classList.add('active');

    function cleanup(result) {
      overlay.classList.remove('active');
      okBtn.removeEventListener('click', onOk);
      cancelBtn.removeEventListener('click', onCancel);
      resolve(result);
    }
    function onOk() { cleanup(true); }
    function onCancel() { cleanup(false); }

    okBtn.addEventListener('click', onOk);
    cancelBtn.addEventListener('click', onCancel);
  });
}

async function deleteHistory(type, id) {
  const confirmed = await showConfirm('Delete this quiz history? This cannot be undone.');
  if (!confirmed) return;

  const body = new URLSearchParams({type, id: String(id)});
  try {
    const response = await fetch('delete_quiz_history.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body
    });
    const rawResponse = await response.text();
    let data;
    try {
      data = JSON.parse(rawResponse);
    } catch (parseError) {
      console.error('Quiz history delete response:', rawResponse);
      throw new Error('The server returned an invalid response. Please check the database connection.');
    }
    if (!data.success) throw new Error(data.error || 'Delete failed');
    window.location.reload();
  } catch (error) {
    showToast(error.message || 'Could not delete this history item.', 'error');
  }
}
      // --- PERSONA MODAL LOGIC ---
      const pModal = document.getElementById("personaModal");
      function openPersona() { pModal.style.display = "flex"; }
      function closePersona() { pModal.style.display = "none"; }

      let currentSelectedAvatar = "Q";
      function selectAvatar(el, type) {
        document.querySelectorAll(".avatar-option").forEach((opt) => opt.classList.remove("active"));
        el.classList.add("active");
        currentSelectedAvatar = el.innerHTML;
      }

      function savePersona() {
        const newName = document.getElementById("name-input").value;
        if (!newName) return;
        document.getElementById("side-name").innerText = newName;
        document.getElementById("side-avatar").innerHTML = currentSelectedAvatar;
        closePersona();
      }

      window.onclick = (event) => {
          if (event.target == forgeModal) closeForge();
          if (event.target == pModal) closePersona();
      };

      // ── Toast notification helpers (replaces localhost alert() popups) ──
      let __toastTimer;
      function showToast(msg, type) {
        const t = document.getElementById('toast');
        if (!t) return;
        const iconClass = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
        t.innerHTML =
            '<i class="fa-solid ' + iconClass + ' toast-icon"></i>' +
            '<span class="toast-text">' + msg.replace(/\n/g, '<br>') + '</span>' +
            '<button type="button" class="toast-close" onclick="hideToast()">&times;</button>';
        t.className = 'toast ' + (type === 'error' ? 'error' : 'success') + ' show';
        clearTimeout(__toastTimer);
        __toastTimer = setTimeout(hideToast, 6000);
      }
      function hideToast() {
        const t = document.getElementById('toast');
        if (t) t.classList.remove('show');
      }

      <?php if (!empty($_GET['notice'])): ?>
      window.addEventListener('DOMContentLoaded', () => {
        showToast(
            <?php echo json_encode($_GET['notice']); ?>,
            <?php echo json_encode($_GET['notice_type'] ?? 'success'); ?>
        );
        // If it's a "quiz generation failed" notice, also re-open the Forge modal
        // so the student can immediately pick a different material.
        <?php if (($_GET['notice_type'] ?? '') === 'error'): ?>
        forgeModal.classList.add('active');
        <?php endif; ?>
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('notice');
            url.searchParams.delete('notice_type');
            window.history.replaceState({}, document.title, url.toString());
        }
      });
      <?php endif; ?>
    </script>
  </body>
</html>