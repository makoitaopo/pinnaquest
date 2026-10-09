<?php
session_start();
include_once('db.php');
require_once __DIR__ . '/xp_config.php';
if (!isset($_SESSION['user_id'])) { header("Location: loginpanel.php"); exit(); }
$user_id = intval($_SESSION['user_id']);
$u = $conn->query("SELECT * FROM users WHERE id=$user_id");
$user = $u ? $u->fetch_assoc() : [];
$display_name = !empty($user['display_name']) ? $user['display_name'] : ($user['full_name'] ?? 'Student');
$initial = strtoupper(mb_substr($display_name, 0, 1));
$level = max(1, floor(intval($user['xp'] ?? 0) / PINNAQUEST_XP_PER_LEVEL) + 1);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Materials</title>
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
        --soft-green: #f0fff4;
        --sidebar-white: #ffffff;
        --bg-light: #f8fafc;
        --text-dark: #1a202c;
        --text-gray: #718096;
        --border-color: #f1f5f9;
        --glow-green: rgba(29, 185, 104, 0.03);
        --glow-yellow: rgba(255, 235, 204, 0.05);
        
        /* Bagong colors mula sa Dashboard */
        --synchro-purple: #6366f1; 
        --icon-materials: #3b82f6; /* Blue */
        --icon-quizzes: #f59e0b;   /* Orange/Gold */
        --icon-leaderboard: #10b981; /* Emerald */
      }

      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: "Inter", sans-serif;
        background: radial-gradient(circle at 10% 10%, var(--glow-green) 0%, transparent 40%),
                    radial-gradient(circle at 90% 90%, var(--glow-yellow) 0%, transparent 40%),
                    #fcfdfa;
        background-attachment: fixed;
        display: flex;
        color: var(--text-dark);
        min-height: 100vh;
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

      .logo-box { margin-bottom: 40px; display: flex; justify-content: center; align-items: center; width: 100%; }
      .logo-box img { width: 180px; height: auto; transition: transform 0.3s ease; cursor: pointer; }
      .logo-box img:hover { transform: scale(1.08); }

      .menu-heading { font-size: 11px; font-weight: 700; color: #cbd5e0; text-transform: uppercase; margin: 20px 0 10px 10px; letter-spacing: 0.05em; }
      
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
      
      .nav-link.active { background-color: var(--brand-green); color: white !important; }
      
      /* Kapag active ang link, dapat puti lahat ng icon */
      .nav-link.active i { color: white !important; text-shadow: none !important; }

      .nav-link:hover:not(.active) { background: var(--soft-green); color: var(--brand-green); }

      /* --- ICON COLORING & GLOW --- */
      /* Kulay at Glow para sa bawat icon base sa dashboard mo */
      .nav-link i.fa-house { color: var(--brand-green); text-shadow: 0 0 8px rgba(29, 185, 104, 0.4); }
      .nav-link i.fa-file-lines { color: var(--icon-materials); text-shadow: 0 0 8px rgba(59, 130, 246, 0.4); }
      .nav-link i.fa-brain { color: var(--icon-quizzes); text-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
      .nav-link i.fa-bolt { color: var(--synchro-purple); text-shadow: 0 0 8px rgba(99, 102, 241, 0.4); }
      .nav-link i.fa-trophy { color: var(--icon-leaderboard); text-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
      .nav-link i.fa-user-astronaut { color: #3b82f6; text-shadow: 0 0 8px rgba(59, 130, 246, 0.3); }

      .persona-link-style { color: #94a3b8 !important; }
      .persona-link-style:hover { background-color: #f0f7ff !important; color: #3b82f6 !important; }

      /* User Profile Section sa Baba */
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
        font-size: 16px;
      }
        .sidebar-avatar{width:35px;height:35px;background:var(--brand-green);border-radius:8px;color:white;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;flex-shrink:0;}


      .user-details h4 { font-size: 13px; font-weight: 700; color: #2d3748; margin: 0; }
      .user-details p { font-size: 11px; color: var(--text-gray); margin: 0; }

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
      .logout-link:hover { color: #e53e3e; }

      /* --- MAIN CONTENT --- */
      .main { flex: 1; margin-left: 260px; padding: 30px 50px; }
      .breadcrumb { font-size: 14px; color: var(--text-gray); font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 30px; }
      
      .materials-header {
        background: white;
        padding: 25px 35px;
        border-radius: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
        margin-bottom: 30px;
      }

      .header-title-container { display: flex; align-items: center; gap: 15px; }
      .header-icon { font-size: 28px; color: var(--brand-green); }
      .header-title h2 { font-family: "Lexend"; font-weight: 700; font-size: 26px; color: var(--brand-dark-green); }
      .header-title p { color: var(--text-gray); font-size: 14px; }

      .btn-add {
        background: var(--brand-green);
        color: white; border: none; padding: 12px 24px; border-radius: 12px;
        font-family: "Lexend"; font-weight: 600; font-size: 15px; cursor: pointer;
        display: flex; align-items: center; gap: 10px; transition: 0.3s;
      }
      .btn-add:hover { background: var(--brand-dark-green); transform: translateY(-2px); }

      /* Grid for Cards */
      .materials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 25px;
      }

      .material-card {
        background: white;
        padding: 24px;
        border-radius: 20px;
        border: 1px solid var(--border-color);
        transition: 0.3s ease;
        position: relative;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
      }
      .material-card:hover { transform: translateY(-5px); box-shadow: 0 12px 20px rgba(0,0,0,0.05); }

      .card-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
      .btn-delete { color: #cbd5e0; transition: 0.3s; cursor: pointer; text-decoration: none; background: none; border: none; font-size: inherit; padding: 0; }
      .btn-delete:hover { color: #ef4444; }

      .empty-state-card {
        grid-column: 1 / -1;
        background: white; border: 2px dashed #e2e8f0; border-radius: 24px;
        padding: 80px 20px; text-align: center; display: flex; flex-direction: column; align-items: center;
      }

      /* --- MODAL --- */
      .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 2000; }
      .modal-overlay.active { display: flex; }
      .modal-content { background: white; width: 90%; max-width: 500px; border-radius: 24px; padding: 30px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1); }
      .form-group { margin-bottom: 20px; }
      .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; }
      .form-group input { width: 100%; padding: 12px; border: 2px solid var(--border-color); border-radius: 12px; outline: none; }
      .file-upload-box { border: 2px dashed #cbd5e0; padding: 25px; text-align: center; border-radius: 12px; cursor: pointer; }

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

      /* --- DELETE CONFIRMATION MODAL (PinnaQuest themed, replaces "localhost says") --- */
      .pq-confirm-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(20, 40, 30, 0.45);
        backdrop-filter: blur(4px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 3000;
      }
      .pq-confirm-overlay.active {
        display: flex;
        animation: pqFadeIn 0.2s ease;
      }
      @keyframes pqFadeIn { from { opacity: 0; } to { opacity: 1; } }

      .pq-confirm-card {
        background: white;
        width: 90%;
        max-width: 400px;
        border-radius: 24px;
        padding: 32px 30px 26px;
        text-align: center;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.18);
        border: 1px solid var(--border-color);
        animation: pqPopIn 0.25s cubic-bezier(.34,1.56,.64,1);
      }
      @keyframes pqPopIn {
        from { transform: scale(0.9) translateY(10px); opacity: 0; }
        to   { transform: scale(1) translateY(0); opacity: 1; }
      }

      .pq-confirm-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 18px;
        border-radius: 50%;
        background: #fff1f2;
        color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
      }

      .pq-confirm-card h3 {
        font-family: "Lexend";
        font-weight: 700;
        font-size: 19px;
        color: var(--text-dark);
        margin-bottom: 8px;
      }

      .pq-confirm-card p {
        font-size: 13.5px;
        color: var(--text-gray);
        line-height: 1.55;
        margin-bottom: 24px;
      }

      .pq-confirm-card p strong {
        color: var(--brand-dark-green);
        font-weight: 700;
      }

      .pq-confirm-actions {
        display: flex;
        gap: 12px;
      }

      .pq-btn-cancel {
        flex: 1;
        padding: 12px;
        background: #f1f5f9;
        color: var(--text-gray);
        border: none;
        border-radius: 12px;
        font-family: "Lexend";
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: 0.2s;
      }
      .pq-btn-cancel:hover { background: #e2e8f0; color: var(--text-dark); }

      .pq-btn-confirm-delete {
        flex: 1;
        padding: 12px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 12px;
        font-family: "Lexend";
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: 0.2s;
      }
      .pq-btn-confirm-delete:hover { background: #dc2626; transform: translateY(-1px); }
      .pq-btn-confirm-delete:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    </style>
  </head>
  <body>
    <div class="sidebar">
    <div class="logo-box"><img src="pinnaquest logo.JPG" alt="PinnaQuest"></div>
    <p class="menu-heading">Menu</p>
    <a href="studentdashboard.php" class="nav-link"><i class="fa-solid fa-house"></i> Dashboard</a>
    <a href="materials.php" class="nav-link active"><i class="fa-solid fa-file-invoice"></i> Materials</a>
    <a href="quizzes.php" class="nav-link"><i class="fa-solid fa-brain"></i> Quizzes</a>
    <a href="synchro_portal.php" class="nav-link"><i class="fa-solid fa-bolt-lightning"></i> Synchro-Quiz</a>
    <a href="leaderboard.php" class="nav-link"><i class="fa-solid fa-trophy"></i> Mission Map</a>
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

    <div class="main">
      <div class="breadcrumb">
        <i class="fa-solid fa-grip-lines-vertical" style="color: #cbd5e0"></i>
        <span>Materials</span>
      </div>

      <div class="materials-header">
        <div class="header-title-container">
          <i class="fa-solid fa-file-circle-plus header-icon"></i>
          <div class="header-title">
            <h2>Learning Library</h2>
            <p>Upload texts, PDFs, or images to fuel your quest.</p>
          </div>
        </div>
        <button class="btn-add" id="openModalBtn"><i class="fa-solid fa-plus"></i> Add Material</button>
      </div>

      <div class="materials-grid">
        <?php
include_once("db.php");
$result = $conn->query("SELECT * FROM materials WHERE user_id = $user_id ORDER BY date_uploaded DESC");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
    $file_ext = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $icon = ($file_ext == 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
    $icon_color = ($file_ext == 'pdf') ? '#e11d48' : '#3b82f6';
    $label = !empty($row['display_name']) ? $row['display_name'] : $row['title'];

    echo '
    <div class="material-card" style="background: white; padding: 20px; border-radius: 16px; border: 1px solid #f1f5f9; position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
            <i class="fa-solid '.$icon.'" style="font-size: 32px; color: '.$icon_color.';"></i>
            <button type="button" class="btn-delete"
                data-id="'.$row['id'].'"
                data-label="'.htmlspecialchars($label, ENT_QUOTES).'"
                onclick="openDeleteConfirm(this)">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
        <h3 style="font-size: 16px; font-family: \'Lexend\'; margin-bottom: 5px; color: var(--brand-dark-green);">'.htmlspecialchars($label).'</h3>
        <p style="font-size: 11px; color: var(--text-gray); margin-bottom:2px;">Subject: '.htmlspecialchars($row['title']).'</p>
        <p style="font-size: 11px; color: var(--text-gray);">'.date('M d, Y', strtotime($row['date_uploaded'])).'</p>
        
        <div style="margin-top: 15px; display: flex; gap: 15px;">
            <a href="'.$row['file_path'].'" target="_blank" style="text-decoration: none; font-size: 13px; color: var(--text-gray); font-weight: 600;">
                <i class="fa-solid fa-eye"></i> View
            </a>
            <a href="'.$row['file_path'].'" download style="text-decoration: none; font-size: 13px; color: var(--brand-green); font-weight: 600;">
                <i class="fa-solid fa-download"></i> Download
            </a>
        </div>
    </div>';

            }
        } else {
            echo '
            <div class="empty-state-card">
                <div class="empty-icon-box" style="width:70px; height:70px; background:var(--soft-green); color:var(--brand-green); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:28px; margin-bottom:20px;">
                    <i class="fa-solid fa-upload"></i>
                </div>
                <h3 style="font-family: \'Lexend\'; margin-bottom: 10px">No materials yet</h3>
                <p style="color: var(--text-gray); max-width: 400px">Your library is empty. Upload your first study material!</p>
            </div>';
        }
        $conn->close();
        ?>
      </div>
    </div>

    <div class="modal-overlay" id="materialModal">
      <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <h2 style="font-family:'Lexend'; color:var(--brand-dark-green);">Add Material</h2>
          <button id="closeModalBtn" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-gray);">&times;</button>
        </div>
        <!-- Subject notice banner -->
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start;">
          <i class="fa-solid fa-circle-info" style="color:#d97706;margin-top:2px;flex-shrink:0;"></i>
          <div style="font-size:13px;color:#92400e;line-height:1.5;">
            <strong>Accepted subjects only:</strong> Readings in Philippine History, Understanding the Self,
            Art Appreciation, Physical Education, and Science and Development of Reading.
          </div>
        </div>

        <form action="upload_material.php" method="POST" enctype="multipart/form-data" id="uploadForm">
  <div class="form-group">
    <label>File Name <span style="font-weight:400;color:var(--text-gray);"></span></label>
    <input type="text" name="display_name" id="displayNameInput" required
      placeholder="e.g. Week 8 Reviewer - HIS101"
      style="width:100%;padding:12px;border:2px solid var(--border-color);border-radius:12px;outline:none;" />
  </div>
          <div class="form-group">
            <label>Subject</label>
            <select name="title" id="subjectSelect" required
              style="width:100%;padding:12px;border:2px solid var(--border-color);border-radius:12px;outline:none;font-family:'Inter';font-size:14px;background:white;color:var(--text-dark);cursor:pointer;transition:.2s;">
              <option value="" disabled selected>— Select a subject —</option>
              <option value="Readings in Philippine History">Readings in Philippine History</option>
              <option value="Understanding the Self">Understanding the Self</option>
              <option value="Art Appreciation">Art Appreciation</option>
              <option value="Physical Education">Physical Education</option>
              <option value="Science and Development of Reading">Science and Development of Reading</option>
            </select>
          </div>
          <div class="form-group">
            <label>Select PDF File</label>
            <div class="file-upload-box" onclick="document.getElementById('fileInput').click()">
              <i class="fa-solid fa-cloud-arrow-up" style="font-size:30px; color:var(--brand-green); margin-bottom:10px;"></i>
              <p id="fileNameDisplay">Click to browse files</p>
              <input type="file" id="fileInput" name="material_file" accept=".pdf" hidden required />
            </div>
          </div>
          <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
            <button type="button" id="cancelBtn" style="padding:12px 20px; background:#f1f5f9; border:none; border-radius:12px; cursor:pointer;">Cancel</button>
            <button type="submit" style="padding:12px 25px; background:var(--brand-green); color:white; border:none; border-radius:12px; cursor:pointer; font-weight:600;">Upload</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete confirmation modal (PinnaQuest themed) -->
    <div class="pq-confirm-overlay" id="deleteConfirmModal">
      <div class="pq-confirm-card">
        <div class="pq-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
        <h3>Delete this material?</h3>
        <p><strong id="deleteConfirmLabel">this material</strong> will be permanently removed from your Learning Library. This cannot be undone.</p>
        <div class="pq-confirm-actions">
          <button type="button" class="pq-btn-cancel" id="deleteCancelBtn">Cancel</button>
          <button type="button" class="pq-btn-confirm-delete" id="deleteConfirmBtn">
            <i class="fa-solid fa-trash-can"></i> Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Toast notification container -->
    <div class="toast" id="toast"></div>

    <script>
      const modal = document.getElementById("materialModal");
      const openBtn = document.getElementById("openModalBtn");
      const closeBtn = document.getElementById("closeModalBtn");
      const cancelBtn = document.getElementById("cancelBtn");
      const fileInput = document.getElementById("fileInput");
      const fileNameDisplay = document.getElementById("fileNameDisplay");

      openBtn.onclick = () => modal.classList.add("active");
      const closeM = () => modal.classList.remove("active");
      closeBtn.onclick = closeM;
      cancelBtn.onclick = closeM;

      fileInput.onchange = function() {
    if(this.files[0]) {
        fileNameDisplay.innerText = "Selected: " + this.files[0].name;
        const nameField = document.getElementById('displayNameInput');
        if (!nameField.value.trim()) {
            // auto-suggest gamit ang filename (walang .pdf extension)
            nameField.value = this.files[0].name.replace(/\.pdf$/i, '');
        }
    }
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

      // ── Delete confirmation modal (replaces "localhost says" confirm()) ──
      const deleteModal      = document.getElementById('deleteConfirmModal');
      const deleteLabelEl    = document.getElementById('deleteConfirmLabel');
      const deleteCancelBtn  = document.getElementById('deleteCancelBtn');
      const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
      let pendingDeleteId = null;

      function openDeleteConfirm(btn) {
        pendingDeleteId = btn.getAttribute('data-id');
        const label = btn.getAttribute('data-label') || 'this material';
        deleteLabelEl.textContent = label;
        deleteModal.classList.add('active');
      }

      function closeDeleteConfirm() {
        deleteModal.classList.remove('active');
        pendingDeleteId = null;
      }

      deleteCancelBtn.onclick = closeDeleteConfirm;

      deleteConfirmBtn.onclick = () => {
        if (!pendingDeleteId) return;
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        window.location.href = 'delete_material.php?id=' + encodeURIComponent(pendingDeleteId);
      };

      // Close on backdrop click
      deleteModal.addEventListener('click', (e) => {
        if (e.target === deleteModal) closeDeleteConfirm();
      });

      // Close on Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && deleteModal.classList.contains('active')) closeDeleteConfirm();
      });

      <?php if (!empty($_GET['notice'])): ?>
      window.addEventListener('DOMContentLoaded', () => {
        showToast(
            <?php echo json_encode($_GET['notice']); ?>,
            <?php echo json_encode($_GET['notice_type'] ?? 'success'); ?>
        );
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