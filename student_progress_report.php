<?php
// student_progress_report.php
// Teacher-facing report: pick a student from the roster, see a line graph
// of their SynchroQuiz score/accuracy progression from Quiz 1 onward, plus
// a per-quiz breakdown table.
session_start();
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

$user_initial   = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'P';
$user_full_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Professor';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Student Progress Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="notifications.js"></script>
    
    <style>
        :root {
            --brand-green: #1db968;
            --brand-dark-green: #1a4d2e;
            --synchro-purple: #6366f1;
            --synchro-purple-dark: #4f46e5;
            --accuracy-teal: #0d9488;
            --sidebar-white: #ffffff;
            --text-dark: #1a202c;
            --text-gray: #718096;
            --border-color: #f1f5f9;
            --bg-light: #fcfdfa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Inter", sans-serif;
            background: #fcfdfa;
            display: flex;
            min-height: 100vh;
            color: var(--text-dark);
        }

        /* ── SIDEBAR (matches existing teacher pages) ── */
        .sidebar {
            width: 260px; background: var(--sidebar-white); height: 100vh;
            display: flex; flex-direction: column; padding: 30px 20px;
            position: fixed; top: 0; left: 0; border-right: 1px solid var(--border-color);
            z-index: 1000;
        }
        .logo-box { margin-bottom: 40px; text-align: center; }
        .logo-box img { width: 180px; height: auto; }
        .menu-heading {
            font-size: 11px; font-weight: 700; color: #cbd5e0;
            text-transform: uppercase; margin: 20px 0 10px 10px; letter-spacing: 0.05em;
        }
        .nav-link {
            display: flex; align-items: center; gap: 15px; padding: 14px 18px;
            text-decoration: none; color: var(--text-gray); font-weight: 500;
            font-size: 14px; border-radius: 12px; margin-bottom: 5px; transition: 0.2s;
        }
        .nav-link.active { background-color: var(--synchro-purple); color: white !important; }
        .nav-link.active i { color: white !important; }
        .nav-link:hover:not(.active) { background: #f5f3ff; color: var(--synchro-purple); }
        .sidebar-footer { margin-top: auto; }
        .user-profile-bottom {
            background: #f8fafc; padding: 15px; border-radius: 16px;
            display: flex; align-items: center; gap: 12px; margin-bottom: 10px;
        }
        .avatar {
            width: 35px; height: 35px; background: var(--brand-green);
            border-radius: 8px; color: white; display: flex;
            align-items: center; justify-content: center; font-weight: 800;
        }
        .user-details h4 { font-size: 13px; font-weight: 700; color: #2d3748; }
        .user-details p { font-size: 11px; color: var(--text-gray); }
        .logout-link {
            display: flex; align-items: center; gap: 10px; text-decoration: none;
            color: var(--text-gray); font-size: 13px; font-weight: 600; padding: 10px 15px;
        }
        .logout-link:hover { color: #e53e3e; }

        /* ── MAIN ── */
        .main { flex: 1; margin-left: 260px; padding: 30px 44px 60px; }
        .breadcrumb {
            font-size: 12px; color: var(--text-gray); font-weight: 600;
            display: flex; align-items: center; gap: 8px; margin-bottom: 22px;
        }

        .page-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #3730a3 100%);
            border-radius: 26px; padding: 30px 38px; margin-bottom: 26px;
            display: flex; align-items: center; gap: 22px; position: relative; overflow: hidden;
            box-shadow: 0 12px 30px rgba(99,102,241,.2);
        }
        .page-header::before {
            content: ''; position: absolute; top: -50px; right: -40px;
            width: 180px; height: 180px; background: rgba(255,255,255,.06); border-radius: 50%;
        }
        .page-header-icon {
            width: 58px; height: 58px; border-radius: 16px; background: rgba(255,255,255,.12);
            display: flex; align-items: center; justify-content: center; font-size: 24px;
            color: #c7d2fe; flex-shrink: 0; position: relative; z-index: 1;
        }
        .page-header h1 {
            font-family: "Lexend"; font-size: 22px; font-weight: 800; color: white;
            position: relative; z-index: 1;
        }
        .page-header p { color: rgba(255,255,255,.65); font-size: 13px; margin-top: 4px; position: relative; z-index: 1; }

        /* ── LAYOUT ── */
        .report-grid { display: grid; grid-template-columns: 300px 1fr; gap: 22px; align-items: start; }

        /* ── ROSTER PANEL ── */
        .roster-panel {
            background: white; border: 1px solid var(--border-color); border-radius: 22px;
            overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.03);
        }
        .roster-search {
            padding: 16px 16px 12px; border-bottom: 1px solid var(--border-color);
        }
        .roster-search-box {
            display: flex; align-items: center; gap: 8px; background: #f8fafc;
            border: 1.5px solid var(--border-color); border-radius: 12px; padding: 9px 12px;
        }
        .roster-search-box i { color: #a0aec0; font-size: 13px; }
        .roster-search-box input {
            border: none; background: none; outline: none; font-size: 13px;
            font-family: 'Inter'; width: 100%; color: var(--text-dark);
        }
        .roster-list { max-height: 620px; overflow-y: auto; padding: 8px; }
        .roster-item {
            display: flex; align-items: center; gap: 10px; padding: 11px 10px;
            border-radius: 14px; cursor: pointer; transition: .15s; margin-bottom: 3px;
            border: 1.5px solid transparent;
        }
        .roster-item:hover { background: #f5f3ff; }
        .roster-item.selected { background: #eef2ff; border-color: #c7d2fe; }
        .roster-avatar {
            width: 36px; height: 36px; border-radius: 10px; object-fit: cover;
            background: var(--synchro-purple); flex-shrink: 0;
        }
        .roster-avatar-fallback {
            width: 36px; height: 36px; border-radius: 10px; background: var(--synchro-purple);
            color: white; display: flex; align-items: center; justify-content: center;
            font-family: 'Lexend'; font-weight: 800; font-size: 14px; flex-shrink: 0;
        }
        .roster-info { flex: 1; min-width: 0; }
        .roster-name {
            font-size: 13px; font-weight: 800; color: var(--text-dark);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .roster-meta { font-size: 11px; color: var(--text-gray); margin-top: 1px; }
        .roster-count-badge {
            font-size: 10px; font-weight: 800; background: #ede9fe; color: #5b21b6;
            padding: 3px 8px; border-radius: 20px; flex-shrink: 0;
        }
        .delete-student-btn {
            background: #fff1f2; color: #be123c; border-radius: 8px; padding: 7px 8px;
            flex-shrink: 0;
        }
        .delete-student-btn:hover { background: #e11d48; color: white; }
        .roster-empty { text-align: center; padding: 40px 20px; color: var(--text-gray); font-size: 13px; }
        .roster-empty i { font-size: 26px; color: #cbd5e0; margin-bottom: 10px; display: block; }

        /* ── DETAIL PANEL ── */
        .detail-panel { display: flex; flex-direction: column; gap: 20px; }

        .empty-state {
            background: white; border: 2px dashed #e2e8f0; border-radius: 24px;
            padding: 90px 30px; text-align: center; color: var(--text-gray);
        }
        .empty-state i { font-size: 40px; color: #c7d2fe; margin-bottom: 16px; display: block; }
        .empty-state h3 { font-family: 'Lexend'; color: var(--text-dark); font-size: 17px; margin-bottom: 6px; }
        .empty-state p { font-size: 13px; max-width: 320px; margin: 0 auto; line-height: 1.6; }

        /* Student header card */
        .student-header {
            background: white; border: 1px solid var(--border-color); border-radius: 22px;
            padding: 22px 26px; display: flex; align-items: center; gap: 18px;
            box-shadow: 0 4px 16px rgba(0,0,0,.03);
        }
        .student-header-avatar {
            width: 56px; height: 56px; border-radius: 16px; object-fit: cover;
            background: linear-gradient(135deg, var(--synchro-purple), #818cf8); flex-shrink: 0;
        }
        .student-header-avatar-fallback {
            width: 56px; height: 56px; border-radius: 16px;
            background: linear-gradient(135deg, var(--synchro-purple), #818cf8); color: white;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Lexend'; font-weight: 800; font-size: 22px; flex-shrink: 0;
        }
        .student-header-name { font-family: 'Lexend'; font-size: 19px; font-weight: 800; color: var(--text-dark); }
        .student-header-sub { font-size: 12.5px; color: var(--text-gray); margin-top: 2px; }
        .trend-chip {
            margin-left: auto; display: flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; flex-shrink: 0;
        }
        .trend-up   { background: #ecfdf5; color: #059669; }
        .trend-down { background: #fef2f2; color: #dc2626; }
        .trend-flat { background: #f1f5f9; color: #64748b; }

        /* Stat cards */
        .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .stat-card {
            background: white; border: 1px solid var(--border-color); border-radius: 18px;
            padding: 18px 20px; box-shadow: 0 4px 16px rgba(0,0,0,.03);
        }
        .stat-card .stat-label {
            font-size: 10.5px; font-weight: 800; color: var(--text-gray);
            text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px;
        }
        .stat-card .stat-value { font-family: 'Lexend'; font-size: 24px; font-weight: 800; color: var(--text-dark); }
        .stat-card .stat-value.purple { color: var(--synchro-purple); }
        .stat-card .stat-value.teal { color: var(--accuracy-teal); }
        .stat-card .stat-value.gold { color: #d97706; }

        /* Chart card */
        .chart-card {
            background: white; border: 1px solid var(--border-color); border-radius: 22px;
            padding: 26px 28px 18px; box-shadow: 0 4px 16px rgba(0,0,0,.03);
        }
        .chart-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; flex-wrap: wrap; gap: 10px; }
        .chart-card-head h3 { font-family: 'Lexend'; font-size: 15.5px; font-weight: 800; color: var(--text-dark); }
        .chart-legend { display: flex; gap: 16px; }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; color: var(--text-gray); }
        .legend-dot { width: 9px; height: 9px; border-radius: 50%; }
        .chart-wrap { position: relative; height: 300px; margin-top: 14px; }
        .chart-single-note {
            text-align: center; color: var(--text-gray); font-size: 12.5px;
            padding: 50px 20px; display: none;
        }

        /* History table */
        .history-card {
            background: white; border: 1px solid var(--border-color); border-radius: 22px;
            overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.03);
        }
        .history-card-head { padding: 20px 26px 14px; border-bottom: 1px solid var(--border-color); }
        .history-card-head h3 { font-family: 'Lexend'; font-size: 15.5px; font-weight: 800; color: var(--text-dark); }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th {
            text-align: left; font-size: 10.5px; font-weight: 800; color: var(--text-gray);
            text-transform: uppercase; letter-spacing: .04em; padding: 12px 26px; background: #fafbfc;
        }
        .history-table td { padding: 14px 26px; font-size: 13px; border-top: 1px solid var(--border-color); }
        .history-table tr:hover td { background: #fafbff; }
        .quiz-num-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 8px; background: #ede9fe; color: #5b21b6;
            font-weight: 800; font-size: 11.5px; margin-right: 10px;
        }
        .quiz-title-cell { font-weight: 700; color: var(--text-dark); }
        .quiz-title-cell .quiz-date { font-size: 11px; color: var(--text-gray); font-weight: 500; margin-top: 2px; }
        .diff-tag {
            font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 20px; text-transform: uppercase;
        }
        .diff-easy { background: #f0fff4; color: #15803d; }
        .diff-medium { background: #fffbeb; color: #b45309; }
        .diff-hard { background: #fef2f2; color: #b91c1c; }
        .score-cell { font-weight: 800; color: var(--synchro-purple); }
        .accuracy-cell { font-weight: 800; }
        .accuracy-good { color: #059669; }
        .accuracy-mid { color: #d97706; }
        .accuracy-low { color: #dc2626; }

        .loading-spin { text-align: center; padding: 60px; color: var(--text-gray); font-size: 13px; }
        .loading-spin i { font-size: 22px; margin-bottom: 10px; display: block; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 900px) {
            .report-grid { grid-template-columns: 1fr; }
            .stat-row { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── Custom SVG progress chart tooltip ── */
    .pq-chart-tooltip {
        position: fixed;
        z-index: 99999;
        background: #1a1a2e;
        color: #fff;
        padding: 10px 14px;
        border-radius: 12px;
        font-family: 'Inter', sans-serif;
        font-size: 12px;
        line-height: 1.5;
        pointer-events: none;
        opacity: 0;
        transition: opacity .15s ease;
        box-shadow: 0 10px 24px rgba(0,0,0,.28);
        max-width: 220px;
    }
    .pq-tt-title { font-family: 'Lexend', sans-serif; font-weight: 800; font-size: 12.5px; margin-bottom: 2px; }
    .pq-tt-date  { color: rgba(255,255,255,.55); font-size: 10.5px; margin-bottom: 6px; }
    .pq-tt-row   { display: flex; align-items: center; gap: 6px; font-weight: 700; margin-top: 2px; }
    .pq-tt-dot   { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

    /* ── THEMED DELETE CONFIRMATION MODAL (replaces browser confirm()) ── */
    .pq-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(30, 27, 75, 0.45); backdrop-filter: blur(4px);
        display: none; justify-content: center; align-items: center; z-index: 3000;
    }
    .pq-modal-overlay.active { display: flex; }
    .pq-confirm-modal {
        background: white; width: 90%; max-width: 400px;
        border-radius: 26px; padding: 36px 32px 28px; text-align: center;
        box-shadow: 0 25px 50px rgba(30,27,75,.18);
    }
    .pq-confirm-icon {
        width: 64px; height: 64px; background: #fff1f2; color: #be123c;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 26px; margin: 0 auto 20px;
    }
    .pq-confirm-modal h3 {
        font-family: 'Lexend'; font-weight: 700; font-size: 19px;
        color: var(--text-dark); margin-bottom: 10px;
    }
    .pq-confirm-modal p { font-size: 14px; color: var(--text-gray); line-height: 1.6; }
    .pq-confirm-modal p .pq-highlight { font-weight: 700; color: var(--text-dark); }
    .pq-confirm-actions { display: flex; gap: 12px; margin-top: 26px; }
    .pq-confirm-actions button {
        flex: 1; padding: 13px; border-radius: 12px; border: none;
        font-family: 'Lexend'; font-weight: 700; font-size: 14px; cursor: pointer; transition: .2s;
    }
    .pq-btn-cancel { background: #f1f5f9; color: var(--text-gray); }
    .pq-btn-cancel:hover { background: #e2e8f0; }
    .pq-btn-delete {
        background: #e11d48; color: white;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .pq-btn-delete:hover { background: #be123c; box-shadow: 0 4px 12px rgba(225,29,72,.25); }
     .download-report-btn {
         display: inline-flex; align-items: center; justify-content: center; gap: 9px;
         border: 0; border-radius: 12px; padding: 11px 15px;
         background: var(--synchro-purple); color: #fff; cursor: pointer;
         font: 700 12px 'Inter', sans-serif; white-space: nowrap;
         box-shadow: 0 5px 12px rgba(99,102,241,.2); transition: .2s;
     }
     .download-report-btn:hover { background: var(--synchro-purple-dark); transform: translateY(-1px); }
     .download-report-btn:focus-visible { outline: 3px solid #c7d2fe; outline-offset: 3px; }
     .print-report-meta { display: none; }

     @media (max-width: 640px) {
         .student-header { flex-wrap: wrap; }
         .download-report-btn { width: 100%; }
     }

     @page { size: A4 landscape; margin: 12mm; }
     @media print {
         html, body {
             display: block !important; width: 100% !important; min-height: 0 !important;
             height: auto !important; background: #fff !important; color: #1a202c !important;
             -webkit-print-color-adjust: exact; print-color-adjust: exact;
         }
         .sidebar, .breadcrumb, .page-header, .roster-panel,
         .download-report-btn, .pq-modal-overlay, .pq-chart-tooltip { display: none !important; }
         .main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
         .report-grid { display: block !important; }
         .detail-panel { display: flex !important; gap: 12px !important; width: 100% !important; }
         .print-report-meta {
             display: flex !important; align-items: center; justify-content: space-between;
             color: #475569; font-size: 10pt; border-bottom: 1px solid #cbd5e1;
             padding: 0 0 8px; margin-bottom: 10px;
         }
         .print-report-meta strong { color: #1e1b4b; font: 800 15pt 'Lexend', sans-serif; }
         .student-header, .stat-card, .chart-card {
             box-shadow: none !important; border-color: #dbe3ee !important;
             break-inside: avoid; page-break-inside: avoid;
         }
         .student-header { padding: 12px 16px !important; }
         .stat-row { gap: 8px !important; }
         .stat-card { padding: 12px 14px !important; }
         .stat-card .stat-value { font-size: 18pt !important; }
         .chart-card { padding: 14px 16px 10px !important; }
         .chart-wrap { height: 210px !important; }
         .chart-single-note { padding: 24px 12px !important; }
         .history-card { overflow: visible !important; break-inside: auto !important; page-break-inside: auto !important; }
         .history-table thead { display: table-header-group; }
         .history-card-head { padding: 12px 16px 8px !important; }
         .history-table th, .history-table td { padding: 7px 10px !important; }
         .history-table tr { break-inside: avoid; page-break-inside: avoid; }
         .history-table tr:hover td { background: transparent !important; }
     }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-box"><img src="pinnaquest logo.JPG" alt="PinnaQuest"></div>
        <p class="menu-heading">Menu</p>
        <nav>
            <a href="teacherdashboard.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="teacher_materials.php" class="nav-link"><i class="fa-solid fa-file-invoice"></i> Materials</a>
            <a href="teacher_session_history.php" class="nav-link"><i class="fa-solid fa-chart-bar"></i> Session History</a>
            <a href="student_progress_report.php" class="nav-link active"><i class="fa-solid fa-chart-line"></i> Student Progress</a>
            <a href="synchro_manage.php" class="nav-link"><i class="fa-solid fa-bolt"></i> Synchro-Quiz</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-profile-bottom">
                <div class="avatar"><?php echo htmlspecialchars($user_initial); ?></div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user_full_name); ?></h4>
                    <p>Professor</p>
                </div>
            </div>
            <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main">
        <div class="breadcrumb">
            <i class="fa-solid fa-chart-line" style="color:var(--synchro-purple)"></i>
            <span>Student Progress Report</span>
        </div>

        <div class="page-header">
            <div class="page-header-icon"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <h1>Student Progress Report</h1>
                <p>Pick a student to see how their SynchroQuiz scores moved from one quiz to the next.</p>
            </div>
        </div>

        <div class="report-grid">

            <!-- ROSTER -->
            <div class="roster-panel">
                <div class="roster-search">
                    <div class="roster-search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="rosterSearch" placeholder="Search student nickname...">
                    </div>
                </div>
                <div class="roster-list" id="rosterList">
                    <div class="loading-spin"><i class="fa-solid fa-circle-notch"></i>Loading students...</div>
                </div>
            </div>

            <!-- DETAIL -->
            <div class="detail-panel" id="detailPanel">
                <div class="empty-state">
                    <i class="fa-solid fa-user-graduate"></i>
                    <h3>No student selected</h3>
                    <p>Choose a student from the list on the left to view their quiz-by-quiz progress and score trend.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Themed Delete Confirmation Modal (replaces native confirm()) -->
    <div class="pq-modal-overlay" id="pqDeleteConfirmModal">
      <div class="pq-confirm-modal">
        <div class="pq-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
        <h3>Delete student progress?</h3>
        <p>Are you sure you want to delete <span class="pq-highlight" id="pqDeleteStudentName"></span>'s quiz history? This action cannot be undone.</p>
        <div class="pq-confirm-actions">
          <button type="button" class="pq-btn-cancel" id="pqCancelDeleteBtn">Cancel</button>
          <button type="button" class="pq-btn-delete" id="pqConfirmDeleteBtn"><i class="fa-solid fa-trash-can"></i> Delete</button>
        </div>
      </div>
    </div>

<script>
const AVATARS = {
    gamer_girl: '1a.JPG', blue_robot: '2a.JPG', gorilla_vr: '3a.JPG', grey_cat: '4a.JPG',
    monkey_cap: '5a.JPG', astronaut: '6a.JPG', bear_angry: '7a.JPG', bear_bee: '8a.JPG',
};

let selectedNickname = null;
let selectedUserId   = 0;
let progressChart    = null;
let rosterCache       = [];
let searchDebounce     = null;
let pendingDeleteStudent = null;

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function avatarTag(avatarKey, sizeClass, fallbackClass, nickname) {
    const file = AVATARS[avatarKey];
    const initial = (nickname || '?').trim().charAt(0).toUpperCase() || '?';
    if (!file) {
        return `<div class="${fallbackClass}">${escHtml(initial)}</div>`;
    }
    return `<img class="${sizeClass}" src="${file}" alt="" onerror="this.outerHTML='<div class=\\'${fallbackClass}\\'>${escHtml(initial)}</div>'">`;
}

function fmtDate(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d)) return dt;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/* ── ROSTER ─────────────────────────────────────────────────────────── */
function loadRoster(search = '') {
    fetch(`get_student_list.php?search=${encodeURIComponent(search)}`)
        .then(r => r.json())
        .then(data => {
            rosterCache = data.students || [];
            renderRoster(rosterCache);
        })
        .catch(() => {
            document.getElementById('rosterList').innerHTML =
                '<div class="roster-empty"><i class="fa-solid fa-triangle-exclamation"></i>Could not load students.</div>';
        });
}

function renderRoster(students) {
    const container = document.getElementById('rosterList');
    if (!students.length) {
        container.innerHTML = `
            <div class="roster-empty">
                <i class="fa-solid fa-user-group"></i>
                No students with completed SynchroQuiz sessions yet.
            </div>`;
        return;
    }
    container.innerHTML = students.map(s => `
        <div class="roster-item ${s.nickname === selectedNickname ? 'selected' : ''}" onclick="selectStudent(${Number(s.user_id) || 0}, '${escHtml(s.nickname).replace(/'/g,"\\'")}')">
            ${avatarTag(s.avatar_key, 'roster-avatar', 'roster-avatar-fallback', s.nickname)}
            <div class="roster-info">
                <div class="roster-name">${escHtml(s.nickname)}</div>
                <div class="roster-meta">Avg score ${s.avg_score.toLocaleString()} · ${s.avg_accuracy}% accuracy</div>
            </div>
            <div class="roster-count-badge">${s.quiz_count} quiz${s.quiz_count !== 1 ? 'zes' : ''}</div>
            <button type="button" class="delete-student-btn" title="Delete student progress"
                onclick="event.stopPropagation(); deleteStudentProgress(${Number(s.user_id) || 0}, '${escHtml(s.nickname).replace(/'/g,"\\'")}')">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `).join('');
}

/* ── THEMED DELETE CONFIRMATION (replaces native confirm()) ─────────── */
function deleteStudentProgress(userId, nickname) {
    pendingDeleteStudent = { userId, nickname };
    document.getElementById('pqDeleteStudentName').textContent = nickname;
    document.getElementById('pqDeleteConfirmModal').classList.add('active');
}

function closePqDeleteConfirm() {
    document.getElementById('pqDeleteConfirmModal').classList.remove('active');
    pendingDeleteStudent = null;
}

document.getElementById('pqCancelDeleteBtn').addEventListener('click', closePqDeleteConfirm);

document.getElementById('pqDeleteConfirmModal').addEventListener('click', (e) => {
    if (e.target.id === 'pqDeleteConfirmModal') closePqDeleteConfirm();
});

document.getElementById('pqConfirmDeleteBtn').addEventListener('click', async () => {
    if (!pendingDeleteStudent) return;
    const { userId, nickname } = pendingDeleteStudent;
    closePqDeleteConfirm();

    try {
        const response = await fetch('delete_student_progress.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({user_id: String(userId), nickname})
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Delete failed');

        if (selectedNickname === nickname) {
            selectedNickname = '';
            selectedUserId = 0;
            document.getElementById('detailPanel').innerHTML =
                '<div class="empty-state"><i class="fa-solid fa-user-graduate"></i><h3>No student selected</h3><p>Choose a student from the list on the left to view their quiz-by-quiz progress and score trend.</p></div>';
        }
        loadRoster(document.getElementById('rosterSearch').value);
        pqNotify(`${nickname}'s progress was deleted.`, 'success');
    } catch (error) {
        pqNotify(error.message || 'Could not delete student progress.', 'error');
    }
});

document.getElementById('rosterSearch').addEventListener('input', function() {
    clearTimeout(searchDebounce);
    const val = this.value;
    searchDebounce = setTimeout(() => loadRoster(val), 250);
});

/* ── STUDENT DETAIL ─────────────────────────────────────────────────── */
function selectStudent(userId, nickname) {
    selectedUserId = Number(userId) || 0;
    selectedNickname = nickname;
    renderRoster(rosterCache);

    document.getElementById('detailPanel').innerHTML =
        '<div class="loading-spin"><i class="fa-solid fa-circle-notch"></i>Loading progress...</div>';

    fetch(`get_student_progress.php?user_id=${selectedUserId}&nickname=${encodeURIComponent(nickname)}`)
        .then(r => r.json())
        .then(data => renderDetail(data))
        .catch(() => {
            document.getElementById('detailPanel').innerHTML =
                '<div class="empty-state"><i class="fa-solid fa-triangle-exclamation"></i><h3>Could not load progress</h3><p>Please try again.</p></div>';
        });
}

function accuracyClass(pct) {
    if (pct >= 75) return 'accuracy-good';
    if (pct >= 50) return 'accuracy-mid';
    return 'accuracy-low';
}

function diffClass(diff) {
    const d = String(diff || '').toLowerCase();
    if (d === 'hard') return 'diff-hard';
    if (d === 'medium') return 'diff-medium';
    return 'diff-easy';
}

function renderDetail(data) {
    const panel = document.getElementById('detailPanel');
    const attempts = data.attempts || [];
    const summary  = data.summary || {};

    if (!attempts.length) {
        panel.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <h3>No completed quizzes yet</h3>
                <p>${escHtml(data.nickname)} hasn't finished a SynchroQuiz session yet. Their progress will appear here once they do.</p>
            </div>`;
        return;
    }

    const trendMap = {
        up:   { cls: 'trend-up',   icon: 'fa-arrow-trend-up',   label: 'Improving' },
        down: { cls: 'trend-down', icon: 'fa-arrow-trend-down', label: 'Declining' },
        flat: { cls: 'trend-flat', icon: 'fa-minus',            label: 'Steady' },
    };
    const trend = trendMap[summary.trend] || trendMap.flat;
    const first = attempts[0];

    panel.innerHTML = `
        <div class="print-report-meta">
            <strong>Student Progress Report</strong>
            <span id="printGeneratedAt"></span>
        </div>
        <div class="student-header">
            ${avatarTag(first.avatar_key, 'student-header-avatar', 'student-header-avatar-fallback', data.nickname)}
            <div>
                <div class="student-header-name">${escHtml(data.nickname)}</div>
                <div class="student-header-sub">${summary.total_quizzes} SynchroQuiz session${summary.total_quizzes !== 1 ? 's' : ''} completed</div>
            </div>
            <div class="trend-chip ${trend.cls}"><i class="fa-solid ${trend.icon}"></i> ${trend.label}</div>
            <button type="button" class="download-report-btn" onclick="downloadStudentProgressPdf()"
                title="In the print window, choose Save as PDF to download this report.">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Download PDF
            </button>
        </div>

        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-label">Quizzes Taken</div>
                <div class="stat-value">${summary.total_quizzes}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Score</div>
            <div class="stat-value purple">${summary.avg_score.toLocaleString()} pts</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Accuracy</div>
                <div class="stat-value teal">${summary.avg_accuracy}%</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Best Streak</div>
                <div class="stat-value gold">🔥 ${summary.best_streak}</div>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-card-head">
                <h3><i class="fa-solid fa-chart-line" style="color:var(--synchro-purple);margin-right:6px;"></i>Score & Accuracy Progression</h3>
                <div class="chart-legend">
                    <div class="legend-item"><span class="legend-dot" style="background:#6366f1;"></span>Total Score</div>
                    <div class="legend-item"><span class="legend-dot" style="background:#0d9488;"></span>Accuracy %</div>
                </div>
            </div>
           <div class="chart-wrap" id="chartWrap">
        <div id="progressChartHost" style="width:100%;height:100%;"></div>
    </div>
    <div class="chart-single-note" id="chartSingleNote">Only one quiz on record so far — the trend line will appear once this student completes another SynchroQuiz.</div>

        <div class="history-card">
            <div class="history-card-head"><h3>Quiz-by-Quiz Breakdown</h3></div>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Difficulty</th>
                        <th>Result</th>
                        <th>Correct</th>
                        <th>Accuracy</th>
                        <th>Streak</th>
                    </tr>
                </thead>
                <tbody>
                    ${attempts.map((a, i) => `
                        <tr>
                            <td class="quiz-title-cell">
                                <span class="quiz-num-badge">${i + 1}</span>${escHtml(a.title)}
                                <div class="quiz-date">${fmtDate(a.created_at)} · Room ${escHtml(a.room_code)}</div>
                            </td>
                            <td><span class="diff-tag ${diffClass(a.difficulty)}">${escHtml(a.difficulty)}</span></td>
                            <td class="score-cell">
                                <strong>${a.correct_answers}/${a.item_count} · ${a.accuracy}%</strong>
                                <small>${a.total_score.toLocaleString()} pts</small>
                            </td>
                            <td>${a.correct_answers}/${a.item_count}</td>
                            <td class="accuracy-cell ${accuracyClass(a.accuracy)}">${a.accuracy}%</td>
                            <td>${a.streak > 0 ? '🔥 ' + a.streak : '—'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    renderProgressChart(attempts);
}

function downloadStudentProgressPdf() {
    if (!selectedUserId || !selectedNickname) return;

    const generatedAt = document.getElementById('printGeneratedAt');
    if (generatedAt) {
        generatedAt.textContent = `Generated ${new Intl.DateTimeFormat('en-PH', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(new Date())}`;
    }

    const safeStudentName = selectedNickname
        .normalize('NFKD')
        .replace(/[^\w\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .slice(0, 60) || 'Student';
    const originalTitle = document.title;
    document.title = `PinnaQuest-${safeStudentName}-Progress-Report`;
    window.addEventListener('afterprint', () => {
        document.title = originalTitle;
    }, { once: true });
    window.print();
}

 /* ── CUSTOM SVG LINE CHART (walang external dependency) ─────────────── */
    function renderProgressChart(attempts) {
        const chartWrap = document.getElementById('chartWrap');
        const host       = document.getElementById('progressChartHost');
        const note       = document.getElementById('chartSingleNote');
 
        if (attempts.length < 2) {
            chartWrap.style.display = 'none';
            note.style.display = 'block';
            return;
        }
        chartWrap.style.display = 'block';
        note.style.display = 'none';
        host.innerHTML = '';
 
        const W = 700, H = 280;
        const padL = 54, padR = 50, padT = 20, padB = 36;
        const innerW = W - padL - padR;
        const innerH = H - padT - padB;
        const n = attempts.length;
 
        const scores  = attempts.map(a => a.total_score);
        const scoreMax = Math.max(100, Math.ceil((Math.max(...scores) * 1.15) / 100) * 100);
        const xStep = n > 1 ? innerW / (n - 1) : 0;
 
        const xAt      = i => padL + i * xStep;
        const yScoreAt = v => padT + innerH - (v / scoreMax) * innerH;
        const yAccAt   = v => padT + innerH - (v / 100) * innerH;
 
        const svgns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(svgns, 'svg');
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');
        svg.style.overflow = 'visible';
        svg.style.fontFamily = "'Inter', sans-serif";
 
        // Gradient fill def
        const defs = document.createElementNS(svgns, 'defs');
        const grad = document.createElementNS(svgns, 'linearGradient');
        grad.setAttribute('id', 'pqScoreGradient');
        grad.setAttribute('x1', '0'); grad.setAttribute('y1', '0');
        grad.setAttribute('x2', '0'); grad.setAttribute('y2', '1');
        const stop1 = document.createElementNS(svgns, 'stop');
        stop1.setAttribute('offset', '0%');
        stop1.setAttribute('stop-color', '#6366f1');
        stop1.setAttribute('stop-opacity', '0.22');
        const stop2 = document.createElementNS(svgns, 'stop');
        stop2.setAttribute('offset', '100%');
        stop2.setAttribute('stop-color', '#6366f1');
        stop2.setAttribute('stop-opacity', '0');
        grad.appendChild(stop1);
        grad.appendChild(stop2);
        defs.appendChild(grad);
        svg.appendChild(defs);
 
        // Gridlines + dual axis labels
        [0, 0.25, 0.5, 0.75, 1].forEach(f => {
            const y = padT + innerH - f * innerH;
 
            const line = document.createElementNS(svgns, 'line');
            line.setAttribute('x1', padL);
            line.setAttribute('x2', W - padR);
            line.setAttribute('y1', y);
            line.setAttribute('y2', y);
            line.setAttribute('stroke', '#f1f5f9');
            line.setAttribute('stroke-width', '1');
            svg.appendChild(line);
 
            const scoreLbl = document.createElementNS(svgns, 'text');
            scoreLbl.setAttribute('x', padL - 10);
            scoreLbl.setAttribute('y', y + 4);
            scoreLbl.setAttribute('text-anchor', 'end');
            scoreLbl.setAttribute('font-size', '10');
            scoreLbl.setAttribute('font-weight', '700');
            scoreLbl.setAttribute('fill', '#6366f1');
            scoreLbl.textContent = Math.round(f * scoreMax).toLocaleString();
            svg.appendChild(scoreLbl);
 
            const accLbl = document.createElementNS(svgns, 'text');
            accLbl.setAttribute('x', W - padR + 10);
            accLbl.setAttribute('y', y + 4);
            accLbl.setAttribute('text-anchor', 'start');
            accLbl.setAttribute('font-size', '10');
            accLbl.setAttribute('font-weight', '700');
            accLbl.setAttribute('fill', '#0d9488');
            accLbl.textContent = Math.round(f * 100) + '%';
            svg.appendChild(accLbl);
        });
 
        // X-axis labels (Quiz 1, Quiz 2, ...)
        attempts.forEach((a, i) => {
            const t = document.createElementNS(svgns, 'text');
            t.setAttribute('x', xAt(i));
            t.setAttribute('y', H - 10);
            t.setAttribute('text-anchor', 'middle');
            t.setAttribute('font-size', '10.5');
            t.setAttribute('font-weight', '700');
            t.setAttribute('fill', '#718096');
            t.textContent = `Q${i + 1}`;
            svg.appendChild(t);
        });
 
        // Smooth path builder (quadratic through midpoints)
        function smoothPath(pts) {
            if (pts.length < 2) return `M ${pts[0].x} ${pts[0].y}`;
            let d = `M ${pts[0].x} ${pts[0].y}`;
            for (let i = 0; i < pts.length - 1; i++) {
                const p0 = pts[i], p1 = pts[i + 1];
                const midX = (p0.x + p1.x) / 2;
                const midY = (p0.y + p1.y) / 2;
                d += ` Q ${p0.x} ${p0.y} ${midX} ${midY}`;
            }
            const last = pts[pts.length - 1];
            d += ` T ${last.x} ${last.y}`;
            return d;
        }
 
        const scorePts = attempts.map((a, i) => ({ x: xAt(i), y: yScoreAt(a.total_score) }));
        const accPts   = attempts.map((a, i) => ({ x: xAt(i), y: yAccAt(a.accuracy) }));
 
        // Area fill under score line
        const areaPath = document.createElementNS(svgns, 'path');
        let areaD = smoothPath(scorePts);
        areaD += ` L ${scorePts[scorePts.length - 1].x} ${padT + innerH} L ${scorePts[0].x} ${padT + innerH} Z`;
        areaPath.setAttribute('d', areaD);
        areaPath.setAttribute('fill', 'url(#pqScoreGradient)');
        areaPath.setAttribute('stroke', 'none');
        svg.appendChild(areaPath);
 
        // Score line (solid, purple)
        const scoreLine = document.createElementNS(svgns, 'path');
        scoreLine.setAttribute('d', smoothPath(scorePts));
        scoreLine.setAttribute('fill', 'none');
        scoreLine.setAttribute('stroke', '#6366f1');
        scoreLine.setAttribute('stroke-width', '3');
        scoreLine.setAttribute('stroke-linecap', 'round');
        svg.appendChild(scoreLine);
 
        // Accuracy line (dashed, teal)
        const accLine = document.createElementNS(svgns, 'path');
        accLine.setAttribute('d', smoothPath(accPts));
        accLine.setAttribute('fill', 'none');
        accLine.setAttribute('stroke', '#0d9488');
        accLine.setAttribute('stroke-width', '2.5');
        accLine.setAttribute('stroke-dasharray', '6,4');
        accLine.setAttribute('stroke-linecap', 'round');
        svg.appendChild(accLine);
 
        // Shared tooltip element (create once, reuse across re-renders)
        let tooltip = document.getElementById('pqChartTooltip');
        if (!tooltip) {
            tooltip = document.createElement('div');
            tooltip.id = 'pqChartTooltip';
            tooltip.className = 'pq-chart-tooltip';
            document.body.appendChild(tooltip);
        }
 
        // Points + hover interaction
        attempts.forEach((a, i) => {
            const sDot = document.createElementNS(svgns, 'circle');
            sDot.setAttribute('cx', scorePts[i].x);
            sDot.setAttribute('cy', scorePts[i].y);
            sDot.setAttribute('r', 5);
            sDot.setAttribute('fill', '#6366f1');
            sDot.setAttribute('stroke', '#fff');
            sDot.setAttribute('stroke-width', '2');
            svg.appendChild(sDot);
 
            const aDot = document.createElementNS(svgns, 'circle');
            aDot.setAttribute('cx', accPts[i].x);
            aDot.setAttribute('cy', accPts[i].y);
            aDot.setAttribute('r', 4.5);
            aDot.setAttribute('fill', '#0d9488');
            aDot.setAttribute('stroke', '#fff');
            aDot.setAttribute('stroke-width', '2');
            svg.appendChild(aDot);
 
            const hitZone = document.createElementNS(svgns, 'rect');
            const hw = xStep > 0 ? xStep : 60;
            hitZone.setAttribute('x', xAt(i) - hw / 2);
            hitZone.setAttribute('y', padT);
            hitZone.setAttribute('width', hw);
            hitZone.setAttribute('height', innerH);
            hitZone.setAttribute('fill', 'transparent');
            hitZone.style.cursor = 'pointer';
 
            hitZone.addEventListener('mouseenter', () => {
                sDot.setAttribute('r', 7);
                aDot.setAttribute('r', 6);
                tooltip.innerHTML = `
                    <div class="pq-tt-title">${escHtml(a.title)}</div>
                    <div class="pq-tt-date">${fmtDate(a.created_at)} · Room ${escHtml(a.room_code)}</div>
                    <div class="pq-tt-row"><span class="pq-tt-dot" style="background:#6366f1;"></span>Score: <b>${a.total_score.toLocaleString()}</b></div>
                    <div class="pq-tt-row"><span class="pq-tt-dot" style="background:#0d9488;"></span>Accuracy: <b>${a.accuracy}%</b></div>
                `;
                tooltip.style.opacity = '1';
            });
            hitZone.addEventListener('mousemove', (e) => {
                tooltip.style.left = (e.clientX + 14) + 'px';
                tooltip.style.top  = (e.clientY - 10) + 'px';
            });
            hitZone.addEventListener('mouseleave', () => {
                sDot.setAttribute('r', 5);
                aDot.setAttribute('r', 4.5);
                tooltip.style.opacity = '0';
            });
 
            svg.appendChild(hitZone);
        });
 
        host.appendChild(svg);
    }

loadRoster();
</script>
</body>
</html>