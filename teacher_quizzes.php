<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginpanel.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

// I-check kung may session data para sa user
$user_initial = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'P';
$user_full_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Professor';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Teacher Quizzes</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

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
        
        --synchro-purple: #6366f1; 
        --icon-materials: #3b82f6; 
        --icon-quizzes: #f59e0b;   
      }

      * { margin: 0; padding: 0; box-sizing: border-box; }

      body {
        font-family: "Inter", sans-serif;
        background: radial-gradient(circle at 10% 10%, var(--glow-green) 0%, transparent 40%), #fcfdfa;
        background-attachment: fixed;
        display: flex;
        min-height: 100vh;
        color: var(--text-dark);
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
        top: 0; left: 0;
        border-right: 1px solid var(--border-color);
        z-index: 1000;
      }

      .logo-box { margin-bottom: 40px; text-align: center; }
      .logo-box img { width: 180px; height: auto; transition: transform 0.3s ease; cursor: pointer; }
      .logo-box img:hover { transform: scale(1.05); }

      .menu-heading { 
        font-size: 11px; font-weight: 700; color: #cbd5e0; 
        text-transform: uppercase; margin: 20px 0 10px 10px; letter-spacing: 0.05em; 
      }

      .nav-link {
        display: flex; align-items: center; gap: 15px; padding: 14px 18px;
        text-decoration: none; color: var(--text-gray); font-weight: 500;
        font-size: 14px; border-radius: 12px; margin-bottom: 5px; transition: 0.2s;
      }

      .nav-link.active { background-color: var(--brand-green); color: white !important; }
      .nav-link.active i { color: white !important; text-shadow: none !important; }
      .nav-link:hover:not(.active) { background: #f0fff4; color: var(--brand-green); }

      .nav-link i.fa-house { color: var(--brand-green); }
      .nav-link i.fa-file-invoice { color: var(--icon-materials); }
      .nav-link i.fa-brain { color: var(--icon-quizzes); }
      .nav-link i.fa-bolt { color: var(--synchro-purple); }

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

      /* --- MAIN CONTENT --- */
      .main { flex: 1; margin-left: 260px; padding: 30px 50px; }
      .breadcrumb { font-size: 14px; color: var(--text-gray); font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }

      .quest-header {
        background: white; padding: 25px 35px; border-radius: 24px;
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03); border: 1px solid var(--border-color);
        margin-bottom: 30px;
      }

      .header-title-container { display: flex; align-items: flex-start; gap: 15px; }
      .header-icon { font-size: 24px; color: var(--quiz-gold); margin-top: 5px; }
      .header-title h2 { font-family: "Lexend"; font-weight: 800; font-size: 28px; color: var(--brand-dark-green); letter-spacing: -0.02em; }
      .header-title p { color: var(--text-gray); font-size: 14px; margin-top: 4px; }

      .btn-forge {
        background: var(--quiz-gold); color: #1a1a1a; border: none;
        padding: 14px 28px; border-radius: 14px; font-family: "Lexend";
        font-weight: 700; font-size: 15px; cursor: pointer; box-shadow: 0 4px 0px var(--brand-dark-green);
        display: flex; align-items: center; gap: 10px; transition: all 0.1s ease;
      }
      .btn-forge:hover { background: #f5bc16; transform: translateY(-1px); }
      .btn-forge:active { transform: translateY(2px); box-shadow: 0 2px 0px var(--brand-dark-green); }

      /* --- EMPTY STATE --- */
      .empty-state-card {
        background: white; border: 2px dashed #e2e8f0; border-radius: 28px;
        padding: 100px 20px; text-align: center; display: flex;
        flex-direction: column; align-items: center;
      }
      .empty-icon-box {
        width: 70px; height: 70px; background: var(--soft-gold);
        color: #8b6e37; border-radius: 50%; display: flex;
        align-items: center; justify-content: center; font-size: 28px; margin-bottom: 20px;
      }
      .empty-state-card h3 { font-family: "Lexend"; font-size: 22px; color: var(--brand-dark-green); margin-bottom: 10px; font-weight: 800; }
      .empty-state-card p { color: var(--text-gray); max-width: 400px; font-size: 14px; line-height: 1.6; }

      /* --- MODAL STYLES --- */
      .modal-overlay { 
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); 
        display: none; justify-content: center; align-items: center; z-index: 9999; 
      }
      .modal-overlay.active { display: flex; }

      .forge-modal { max-width: 480px; width: 90%; padding: 0; overflow: hidden; border: none; background: white; border-radius: 20px; animation: slideUp 0.3s ease-out; }
      @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

      .forge-header { background: #eab308; color: #1a4d2e; padding: 25px 30px; }
      .forge-form { padding: 25px 30px; }
      .forge-form label { display: block; font-family: 'Inter'; font-weight: 600; font-size: 14px; color: #475569; margin-bottom: 8px; }
      .form-group { margin-bottom: 15px; }
      .forge-form input[type="text"], .forge-form input[type="number"], .forge-form select { 
        width: 100%; padding: 12px 15px; border: 2px solid #f1f5f9; border-radius: 12px; outline: none; font-family: 'Inter'; transition: 0.2s; 
      }
      .forge-form input:focus, .forge-form select:focus { border-color: #eab308; }
      
      .difficulty-options { display: flex; gap: 10px; }
      .diff-btn { flex: 1; text-align: center; padding: 12px; border: 2px solid #f1f5f9; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 13px; color: #94a3b8; transition: 0.3s; }
      input[type="radio"]:checked + .diff-btn { border-color: #eab308; background: #fefce8; color: #854d0e; }
      
      .forge-footer { display: flex; justify-content: flex-end; align-items: center; gap: 20px; margin-top: 30px; }
      .btn-cancel { background: none; border: none; color: #64748b; font-weight: 600; cursor: pointer; }
      .btn-forge-submit { background: #eab308; color: #1a4d2e; padding: 12px 30px; border-radius: 12px; border: none; font-weight: 700; font-family: 'Lexend'; cursor: pointer; box-shadow: 0 4px 0 #ca8a04; transition: 0.2s; }
      .btn-forge-submit:active { transform: translateY(3px); box-shadow: 0 1px 0 #ca8a04; }

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
    </style>
  </head>
  <body>
    <div class="sidebar">
      <div class="logo-box">
        <img src="pinnaquest logo.JPG" alt="PinnaQuest" />
      </div>

      <p class="menu-heading">Menu</p>
      <nav>
        <a href="teacherdashboard.php" class="nav-link">
          <i class="fa-solid fa-house"></i> Dashboard
        </a>
        <a href="teacher_materials.php" class="nav-link">
          <i class="fa-solid fa-file-invoice"></i> Materials
        </a>
        <a href="teacher_quizzes.php" class="nav-link active">
          <i class="fa-solid fa-brain"></i> Quizzes
        </a>
        <a href="synchro_manage.php" class="nav-link">
          <i class="fa-solid fa-bolt"></i> Synchro-Quiz
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="user-profile-bottom">
          <div class="avatar"><?php echo $user_initial; ?></div>
          <div class="user-details">
            <h4><?php echo $user_full_name; ?></h4>
            <p>Professor</p>
          </div>
        </div>
        <a href="logout.php" class="logout-link">
          <i class="fa-solid fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>

    <div class="main">
      <div class="breadcrumb">
        <i class="fa-solid fa-brain" style="color: var(--icon-quizzes)"></i>
        <span>Quiz Management</span>
      </div>

      <div class="quest-header">
        <div class="header-title-container">
          <i class="fa-solid fa-wand-magic-sparkles header-icon"></i>
          <div class="header-title">
            <h2>Professor's Forge</h2>
            <p>Create and manage quizzes for your students.</p>
          </div>
        </div>
        <button class="btn-forge" id="openForgeBtn">
          <i class="fa-solid fa-fire"></i> Forge New Quiz
        </button>
      </div>

      <div class="empty-state-card">
        <div class="empty-icon-box"><i class="fa-solid fa-brain"></i></div>
        <h3>No quizzes created yet</h3>
        <p>Use the Forge to generate a new quiz from your materials.</p>
      </div>

      <div class="modal-overlay" id="forgeModal">
        <div class="forge-modal">
            <div class="forge-header">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 style="font-family:'Lexend'; font-weight:800; font-size:24px;">Forge a New Quiz</h2>
                    <button type="button" id="closeForgeBtn" style="background:none; border:none; font-size:24px; cursor:pointer; color:rgba(0,0,0,0.3);">&times;</button>
                </div>
                <p style="font-size:13px; margin-top:5px; opacity:0.8;">The system will generate questions based on the professor's selected vault material.</p>
            </div>

            <form action="forge_logic.php" method="POST" class="forge-form">
                <div class="form-group">
                    <label>Quiz Title</label>
                    <input type="text" name="quest_title" placeholder="e.g. Lesson 1 Review" required />
                </div>

                <div class="form-group">
                    <label>Source Material</label>
                    <select name="source_material" required>
                        <option value="" disabled selected>Select from your Vault...</option>
                        <?php
                        $mats = $conn->query("SELECT id, title, file_path FROM teacher_materials WHERE user_id = $user_id ORDER BY title ASC");
                        while($m = $mats->fetch_assoc()) {
                            echo "<option value='".htmlspecialchars($m['file_path'])."'>".htmlspecialchars($m['title'])."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Difficulty Level</label>
                    <div class="difficulty-options">
                        <input type="radio" name="difficulty" value="easy" id="easy" checked hidden>
                        <label for="easy" class="diff-btn">EASY</label>
                        <input type="radio" name="difficulty" value="medium" id="medium" hidden>
                        <label for="medium" class="diff-btn">MEDIUM</label>
                        <input type="radio" name="difficulty" value="hard" id="hard" hidden>
                        <label for="hard" class="diff-btn">HARD</label>
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
                        <label>Items</label>
            <input type="number" name="item_count" min="5" max="30" value="10" />
                    </div>
                </div>

                <div class="forge-footer">
                    <button type="button" id="cancelForgeBtn" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-forge-submit">Forge Quiz</button>
                </div>
            </form>
        </div>
      </div>
    </div>

    <!-- Toast notification container -->
    <div class="toast" id="toast"></div>

    <script>
      const forgeModal = document.getElementById("forgeModal");
      const openForgeBtn = document.getElementById("openForgeBtn");
      const closeForgeBtn = document.getElementById("closeForgeBtn");
      const cancelForgeBtn = document.getElementById("cancelForgeBtn");

      openForgeBtn.onclick = () => { forgeModal.classList.add("active"); };
      const closeForge = () => { forgeModal.classList.remove("active"); };
      
      closeForgeBtn.onclick = closeForge;
      cancelForgeBtn.onclick = closeForge;

      window.onclick = (event) => {
          if (event.target == forgeModal) closeForge();
      };

      // Siguraduhin na ang form ay nagsusubmit
      document.querySelector('.forge-form').onsubmit = function() {
        console.log("Form is being submitted...");
        // Maaari kang maglagay ng loading state dito kung gusto mo
        return true; 
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