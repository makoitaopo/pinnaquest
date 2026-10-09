<?php
// teacher_materials.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginpanel.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

// ── Ensure display_name column exists (lazy schema upgrade, safe to re-run) ──
$colCheck = $conn->query("SHOW COLUMNS FROM teacher_materials LIKE 'display_name'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE teacher_materials ADD COLUMN display_name VARCHAR(255) NULL AFTER title");
}

// I-check kung may session data para sa user, kung wala ay gumamit ng placeholders
$user_initial = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'T';
$user_full_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Teacher Account';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Teacher Materials</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="notifications.js"></script>
    <style>
        :root {
            --brand-green: #1db968;
            --brand-dark-green: #14452b;
            --brand-light: #f0fff4;
            --sidebar-white: #ffffff;
            --bg-light: #f8fafc;
            --text-dark: #1a202c;
            --text-gray: #718096;
            --border-color: #f1f5f9;
            --glow-green: rgba(29, 185, 104, 0.03);
            --glow-yellow: rgba(255, 235, 204, 0.05);
            
            --synchro-purple: #6366f1; 
            --icon-materials: #3b82f6; 
            --icon-quizzes: #f59e0b;   
            --icon-leaderboard: #10b981; 
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        /* --- SIDEBAR --- */
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

        .logo-box { margin-bottom: 40px; text-align: center; }
        .logo-box img { width: 180px; height: auto; transition: transform 0.3s ease; cursor: pointer; }
        .logo-box img:hover { transform: scale(1.05); }

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
        
        .nav-link.active { background-color: var(--brand-green); color: white !important; }
        .nav-link.active i { color: white !important; text-shadow: none !important; }
        .nav-link:hover:not(.active) { background: var(--brand-light); color: var(--brand-green); }

        .nav-link i.fa-house { color: var(--brand-green); text-shadow: 0 0 8px rgba(29, 185, 104, 0.4); }
        .nav-link i.fa-file-invoice { color: var(--icon-materials); text-shadow: 0 0 8px rgba(59, 130, 246, 0.4); }
        .nav-link i.fa-brain { color: var(--icon-quizzes); text-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
        .nav-link i.fa-chart-line { color: var(--icon-leaderboard); text-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
        .nav-link i.fa-bolt { color: var(--synchro-purple); text-shadow: 0 0 8px rgba(99, 102, 241, 0.4); }

        .sidebar-footer { margin-top: auto; padding-top: 20px; }

        .user-profile-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 16px;
            margin-bottom: 10px;
        }

        .user-avatar-mini {
            width: 35px;
            height: 35px;
            background: var(--brand-green);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-family: 'Lexend';
            font-size: 16px;
        }

        .user-details { display: flex; flex-direction: column; overflow: hidden; }
        .user-name-label { font-size: 13px; font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role-label { font-size: 11px; color: var(--text-gray); font-weight: 500; }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-gray);
            font-size: 14px;
            font-weight: 600;
            padding: 14px 18px;
            transition: 0.2s;
            border-radius: 12px;
        }
        .logout-link:hover { background: #fff5f5; color: #e53e3e; }

        /* --- MAIN CONTENT --- */
        .main { flex: 1; margin-left: 260px; padding: 40px 60px; }
        .breadcrumb { font-size: 14px; color: var(--brand-dark-green); font-weight: 600; display: flex; align-items: center; gap: 12px; margin-bottom: 25px; }
        
        .materials-header {
            background: white;
            padding: 35px;
            border-radius: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
            margin-bottom: 40px;
        }

        .header-title-container { display: flex; align-items: center; gap: 15px; }
        .header-icon { font-size: 28px; color: var(--brand-green); }
        .header-title h2 { font-family: "Lexend"; font-weight: 700; font-size: 26px; color: var(--brand-dark-green); }
        .header-title p { color: var(--text-gray); font-size: 14px; }

        .btn-add {
            background: var(--brand-green);
            color: white; border: none; padding: 12px 24px; border-radius: 15px;
            font-family: "Lexend"; font-weight: 700; font-size: 15px; cursor: pointer;
            display: flex; align-items: center; gap: 10px; transition: 0.3s;
        }
        .btn-add:hover { background: var(--brand-dark-green); transform: translateY(-3px); box-shadow: 0 8px 15px rgba(29, 185, 104, 0.2); }

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
        }
        .material-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.05); }

        .empty-state-card {
            grid-column: 1 / -1;
            background: white; border: 2px dashed #e2e8f0; border-radius: 24px;
            padding: 80px 20px; text-align: center;
        }

        /* --- MODAL UPDATED TO MATCH IMAGE --- */
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px); 
            display: none; justify-content: center; align-items: center; z-index: 2000; 
        }
        .modal-overlay.active { display: flex; }
        
        .modal-content { 
            background: white; width: 95%; max-width: 550px; 
            border-radius: 28px; padding: 40px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.1); 
            position: relative;
        }

        .modal-close-btn {
            position: absolute; top: 25px; right: 25px;
            background: none; border: none; color: #cbd5e0;
            font-size: 20px; cursor: pointer; transition: 0.2s;
        }
        .modal-close-btn:hover { color: #718096; }

        .modal-content h3 { 
            font-family: 'Lexend'; font-weight: 700; font-size: 24px;
            margin-bottom: 25px; color: var(--brand-dark-green); 
        }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { 
            display: block; font-size: 14px; font-weight: 700; 
            margin-bottom: 10px; color: var(--text-dark); 
        }
        
        .form-group input[type="text"] { 
            width: 100%; padding: 15px; border: 1.5px solid #edf2f7; 
            border-radius: 12px; outline: none; font-family: inherit;
            background: #fafafa; transition: 0.2s;
        }
        .form-group input[type="text"]:focus { border-color: var(--brand-green); background: white; }

        .form-group select {
            width: 100%; padding: 15px; border: 1.5px solid #edf2f7;
            border-radius: 12px; outline: none; font-family: inherit;
            background: #fafafa; color: var(--text-dark); cursor: pointer;
            transition: 0.2s;
        }
        .form-group select:focus { border-color: var(--brand-green); background: white; }

        /* Custom File Upload Area (Drag & Drop Look) */
        .file-upload-wrapper {
            border: 2px dashed #cbd5e0;
            border-radius: 16px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
            position: relative;
            background: #fff;
        }
        .file-upload-wrapper:hover { border-color: var(--brand-green); background: var(--brand-light); }
        .file-upload-wrapper i { font-size: 35px; color: var(--brand-green); margin-bottom: 15px; }
        .file-upload-wrapper p { font-size: 15px; color: var(--text-dark); font-weight: 500; }
        
        /* Itatago ang default file input pero click-able pa rin */
        #material_file {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0; cursor: pointer;
        }

        .modal-footer { 
            display: flex; gap: 15px; margin-top: 35px; 
            justify-content: center; /* Centered buttons based on image */
        }
        
        .btn-cancel { 
            padding: 14px 40px; background: #f1f5f9; color: var(--text-gray); 
            border: none; border-radius: 12px; font-weight: 700; cursor: pointer; 
            font-family: 'Lexend'; transition: 0.2s;
        }
        .btn-cancel:hover { background: #e2e8f0; }

        .btn-submit { 
            padding: 14px 40px; background: var(--brand-green); color: white; 
            border: none; border-radius: 12px; font-weight: 700; cursor: pointer; 
            font-family: 'Lexend'; transition: 0.2s;
        }
        .btn-submit:hover { background: var(--brand-dark-green); box-shadow: 0 4px 12px rgba(29, 185, 104, 0.2); }

        /* --- DELETE CONFIRMATION MODAL (themed, replaces browser confirm()) --- */
        .confirm-modal-content {
            width: 90%;
            max-width: 400px;
            text-align: center;
            padding: 36px 32px 28px;
        }
        .confirm-icon-box {
            width: 64px;
            height: 64px;
            background: #fff1f2;
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 20px;
        }
        .confirm-modal-content h3 {
            font-family: 'Lexend';
            font-weight: 700;
            font-size: 19px;
            color: var(--brand-dark-green);
            margin-bottom: 10px;
        }
        .confirm-modal-content p {
            font-size: 14px;
            color: var(--text-gray);
            line-height: 1.6;
            margin-bottom: 6px;
        }
        .confirm-modal-content p .material-name-highlight {
            font-weight: 700;
            color: var(--text-dark);
        }
        .confirm-modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 26px;
        }
        .confirm-modal-actions button {
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
        .btn-confirm-cancel {
            background: #f1f5f9;
            color: var(--text-gray);
        }
        .btn-confirm-cancel:hover { background: #e2e8f0; }
        .btn-confirm-delete {
            background: #ef4444;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-confirm-delete:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239,68,68,0.25); }
    </style>
</head>
<body>
    <?php if (isset($_GET['upload_status'], $_GET['upload_message'])): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          pqNotify(
            <?php echo json_encode($_GET['upload_message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            <?php echo json_encode($_GET['upload_status'] === 'success' ? 'success' : 'error'); ?>
          );
          if (window.history.replaceState) {
            window.history.replaceState({}, document.title, 'teacher_materials.php');
          }
        });
      </script>
    <?php endif; ?>

    <div class="sidebar">
        <div class="logo-box">
            <img src="pinnaquest logo.JPG" alt="PinnaQuest">
        </div>
        <p class="menu-heading">Menu</p>
        <nav>
            <a href="teacherdashboard.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>
            <a href="teacher_materials.php" class="nav-link active">
                <i class="fa-solid fa-file-invoice"></i> Materials
            </a>
            <a href="teacher_session_history.php" class="nav-link">
                <i class="fa-solid fa-brain"></i> Session History
            </a>
            <a href="student_progress_report.php" class="nav-link">
                <i class="fa-solid fa-chart-line"></i> Student Progress
            </a>
            <a href="synchro_manage.php" class="nav-link">
                <i class="fa-solid fa-bolt"></i> Synchro-Quiz
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-profile-box">
                <div class="user-avatar-mini"><?php echo $user_initial; ?></div>
                <div class="user-details">
                    <span class="user-name-label"><?php echo $user_full_name; ?></span>
                    <span class="user-role-label">Professor</span>
                </div>
            </div>
            <a href="logout.php" class="logout-link">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="main">
        <div class="breadcrumb">
            <i class="fa-solid fa-file-invoice"></i>
            <span>Teacher Materials</span>
        </div>

        <div class="materials-header">
            <div class="header-title-container">
                <i class="fa-solid fa-file-shield header-icon"></i>
                <div class="header-title">
                    <h2>Professor's Vault</h2>
                    <p>Manage learning resources specifically for your sessions.</p>
                </div>
            </div>
            <button class="btn-add" id="openModalBtn"><i class="fa-solid fa-plus"></i> Upload Resource</button>
        </div>

        <div class="materials-grid">
            <?php
            $result = $conn->query("SELECT * FROM teacher_materials WHERE user_id = $user_id ORDER BY date_uploaded DESC");

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $file_ext = pathinfo($row['file_path'], PATHINFO_EXTENSION);
                    $icon = ($file_ext == 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
                    $icon_color = ($file_ext == 'pdf') ? '#e11d48' : '#3b82f6';
                    $label = !empty($row['display_name']) ? $row['display_name'] : $row['title'];
                    
                    echo '
                    <div class="material-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <i class="fa-solid '.$icon.'" style="font-size: 32px; color: '.$icon_color.';"></i>
                            <a href="javascript:void(0)"
                               onclick="openDeleteConfirm(\'tcdelete_material.php?id='.$row['id'].'\', '.htmlspecialchars(json_encode($label), ENT_QUOTES).')"
                               style="color: #cbd5e0; transition: 0.3s;"
                               onmouseover="this.style.color=\'#ef4444\'" 
                               onmouseout="this.style.color=\'#cbd5e0\'">
                                 <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                        <h3 style="font-size: 16px; font-family: \'Lexend\'; margin-bottom: 5px; color: var(--brand-dark-green);">'.htmlspecialchars($label).'</h3>
                        <p style="font-size: 11px; color: var(--text-gray); margin-bottom:2px;">Subject: '.htmlspecialchars($row['title']).'</p>
                        <p style="font-size: 11px; color: var(--text-gray);">Uploaded: '.date('M d, Y', strtotime($row['date_uploaded'])).'</p>
                        
                        <div style="margin-top: 15px; display: flex; gap: 15px;">
                            <a href="'.$row['file_path'].'" target="_blank" style="text-decoration: none; font-size: 13px; color: var(--brand-green); font-weight: 700;">
                                <i class="fa-solid fa-eye"></i> View File
                            </a>
                        </div>
                    </div>';
                }
            } else {
                echo '<div class="empty-state-card"><h3>No materials yet.</h3><p style="color:var(--text-gray)">Your library is empty. Upload your first study material!</p></div>';
            }
            ?>
        </div>
    </div>

    <div class="modal-overlay" id="materialModal">
      <div class="modal-content">
        <button class="modal-close-btn" id="xBtn"><i class="fa-solid fa-xmark"></i></button>
        <h3>Add Material</h3>
        <!-- Subject restriction notice -->
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:13px 16px;margin-bottom:22px;display:flex;gap:10px;align-items:flex-start;">
          <i class="fa-solid fa-triangle-exclamation" style="color:#d97706;margin-top:2px;flex-shrink:0;font-size:15px;"></i>
          <div style="font-size:13px;color:#92400e;line-height:1.55;">
            <strong>GE Subjects only.</strong> This system covers:
            Readings in Philippine History, Understanding the Self, Art Appreciation,
            Physical Education, and Science and Development of Reading.
          </div>
        </div>

        <form action="tcupload_material.php" method="POST" enctype="multipart/form-data">
          <div class="form-group">
            <label>File Name</label>
            <input type="text" name="display_name" id="tcDisplayNameInput"
              placeholder="e.g. Week 8 Reviewer - HIS101" required
              style="width:100%;padding:15px;border:1.5px solid #edf2f7;border-radius:12px;outline:none;background:#fafafa;" />
          </div>

          <div class="form-group">
            <label>Subject</label>
            <select name="title" required>
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
            <div class="file-upload-wrapper" id="uploadArea">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p id="fileNameDisplay">Click to browse PDF files</p>
                <input type="file" name="material_file" id="material_file" accept=".pdf" required />
            </div>
          </div>

          <div class="modal-footer">
              <button type="button" class="btn-cancel" id="closeModalBtn">Cancel</button>
              <button type="submit" class="btn-submit">Upload</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal (themed, replaces browser confirm()) -->
    <div class="modal-overlay" id="deleteConfirmModal">
      <div class="modal-content confirm-modal-content">
        <div class="confirm-icon-box">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3>Delete this material?</h3>
        <p>Are you sure you want to delete <span class="material-name-highlight" id="deleteMaterialName"></span>? This action cannot be undone.</p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn-confirm-cancel" id="cancelDeleteBtn">Cancel</button>
            <button type="button" class="btn-confirm-delete" id="confirmDeleteBtn">
                <i class="fa-solid fa-trash-can"></i> Delete
            </button>
        </div>
      </div>
    </div>

    <script>
      const modal = document.getElementById("materialModal");
      const openBtn = document.getElementById("openModalBtn");
      const closeBtn = document.getElementById("closeModalBtn");
      const xBtn = document.getElementById("xBtn");
      const fileInput = document.getElementById("material_file");
      const fileNameDisplay = document.getElementById("fileNameDisplay");
      const tcDisplayNameInput = document.getElementById("tcDisplayNameInput");

      openBtn.onclick = () => modal.classList.add("active");
      
      const closeModal = () => {
          modal.classList.remove("active");
          fileNameDisplay.innerText = "Click to browse files"; // Reset text
      };

      closeBtn.onclick = closeModal;
      xBtn.onclick = closeModal;

      // Ipakita ang pangalan ng file pag may napili, at auto-suggest ang File Name field
      fileInput.onchange = () => {
          if (fileInput.files.length > 0) {
              const chosenFile = fileInput.files[0];
              fileNameDisplay.innerText = "Selected: " + chosenFile.name;
              if (!tcDisplayNameInput.value.trim()) {
                  // auto-suggest gamit ang filename (walang .pdf extension)
                  tcDisplayNameInput.value = chosenFile.name.replace(/\.pdf$/i, '');
              }
          }
      };

      // ── Themed delete confirmation (replaces browser confirm()) ──
      const deleteConfirmModal = document.getElementById("deleteConfirmModal");
      const deleteMaterialName = document.getElementById("deleteMaterialName");
      const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
      const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
      let pendingDeleteUrl = null;

      function openDeleteConfirm(url, name) {
          pendingDeleteUrl = url;
          deleteMaterialName.textContent = name || "this material";
          deleteConfirmModal.classList.add("active");
      }

      function closeDeleteConfirm() {
          deleteConfirmModal.classList.remove("active");
          pendingDeleteUrl = null;
      }

      cancelDeleteBtn.onclick = closeDeleteConfirm;
      confirmDeleteBtn.onclick = () => {
          if (pendingDeleteUrl) window.location.href = pendingDeleteUrl;
      };

      window.onclick = (event) => {
        if (event.target == modal) {
          closeModal();
        }
        if (event.target == deleteConfirmModal) {
          closeDeleteConfirm();
        }
      }
    </script>
</body>
</html>