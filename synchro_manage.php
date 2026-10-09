<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginpanel.php");
    exit();
}
$user_id = intval($_SESSION['user_id']);
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

$user_initial = isset($_SESSION['user_name']) ? strtoupper(substr($_SESSION['user_name'], 0, 1)) : 'P';
$user_full_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Professor';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PinnaQuest | Synchro-Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        :root {
            --brand-green: #1db968;
            --brand-dark-green: #1a4d2e;
            --synchro-purple: #6366f1; 
            --synchro-purple-dark: #4f46e5;
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

        .nav-link i.fa-house { color: var(--brand-green); }
        .nav-link i.fa-file-invoice { color: #3b82f6; }
        .nav-link i.fa-chart-bar { color: #f59e0b; }
        .nav-link i.fa-chart-line { color: #0d9488; }
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

        /* --- MAIN CONTENT --- */
        .main { flex: 1; margin-left: 260px; padding: 30px 50px; }
        .breadcrumb { font-size: 12px; color: var(--text-gray); font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 25px; }

        /* Portal Card */
        .portal-header-card {
            background: white; padding: 60px 20px; border-radius: 24px;
            text-align: center; display: flex; flex-direction: column; align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02); border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .portal-icon-box {
            width: 70px; height: 70px; background: #f5f3ff;
            color: var(--synchro-purple); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 20px;
            position: relative;
            animation: pulse-purple 2s infinite;
        }

        @keyframes pulse-purple {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(99, 102, 241, 0); }
            100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
        }

        .portal-header-card h2 { font-family: "Lexend"; font-weight: 800; font-size: 32px; color: var(--brand-dark-green); margin-bottom: 8px; }
        .portal-header-card p { color: var(--text-gray); font-size: 14px; margin-bottom: 30px; }

        .btn-initialize {
            background: var(--synchro-purple); color: white; border: none;
            padding: 14px 32px; border-radius: 14px; font-family: "Lexend";
            font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px;
            transition: 0.2s; box-shadow: 0 4px 0 #4338ca;
        }
        .btn-initialize:active { transform: translateY(2px); box-shadow: 0 2px 0 #4338ca; }

        /* --- VIOLET THEME MODAL --- */
        .modal-overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); 
            display: none; justify-content: center; align-items: center; z-index: 9999; 
        }
        .modal-overlay.active { display: flex !important; }
        
        .forge-modal { 
            background: white; width: 480px; border-radius: 30px; 
            overflow: hidden; animation: slideUp 0.3s ease; 
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .forge-header { background: var(--synchro-purple); color: white; padding: 30px; position: relative; }
        .forge-header h2 { font-family: 'Lexend'; font-weight: 800; font-size: 26px; }
        .forge-header p { font-size: 13px; opacity: 0.9; margin-top: 4px; }

        .forge-form { padding: 30px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #475569; margin-bottom: 8px; }
        
        .form-group input[type="text"], 
        .form-group input[type="number"], 
        .form-group select { 
            width: 100%; padding: 12px 16px; border: 2px solid #f1f5f9; 
            border-radius: 14px; outline: none; font-family: 'Inter'; 
        }

        .difficulty-options { display: flex; gap: 10px; }
        .diff-btn { 
            flex: 1; text-align: center; padding: 12px; border: 2px solid #f1f5f9; 
            border-radius: 14px; cursor: pointer; font-weight: 700; font-size: 13px; 
            color: #94a3b8; transition: 0.3s; 
        }
        input[type="radio"]:checked + .diff-btn { 
            border-color: var(--synchro-purple); background: #f5f3ff; color: var(--synchro-purple); 
        }

        .forge-footer { display: flex; justify-content: flex-end; align-items: center; gap: 25px; margin-top: 30px; }
        .btn-cancel { background: none; border: none; color: #64748b; font-weight: 600; cursor: pointer; }
        
        .btn-forge-submit { 
            background: var(--synchro-purple); color: white; padding: 14px 30px; 
            border-radius: 14px; border: none; font-weight: 700; 
            font-family: 'Lexend'; cursor: pointer; box-shadow: 0 4px 0 var(--synchro-purple-dark);
        }
    </style>
</head>
<body>
    <div class="sidebar">
    <div class="logo-box">
        <img src="pinnaquest logo.JPG" alt="PinnaQuest">
    </div>

    <p class="menu-heading">Menu</p>
    <nav>
        <a href="teacherdashboard.php" class="nav-link">
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
        <a href="synchro_manage.php" class="nav-link active" aria-current="page">
            <i class="fa-solid fa-bolt"></i> Synchro-Quiz
            <span class="live-dot"></span>
        </a>
    </nav>

    

        <div class="sidebar-footer">
            <div class="user-profile-bottom">
                <div class="avatar"><?php echo $user_initial; ?></div>
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
            <i class="fa-solid fa-bolt" style="color: var(--synchro-purple)"></i>
            <span>Synchro-Portal</span>
        </div>

        <div class="portal-header-card">
            <div class="portal-icon-box"><i class="fa-solid fa-bolt"></i></div>
            <h2>Synchro-Portal</h2>
            <p>Host a live quiz session and let your students join in real-time.</p>
            <button class="btn-initialize" id="openForgeBtn">
                <i class="fa-solid fa-tower-broadcast"></i> Initialize Session
            </button>
        </div>

        <div class="stats-grid">
             <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; width: 100%;">
                <div style="background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--border-color);">
                    <h3 style="font-family: 'Lexend'; font-size: 16px; margin-bottom: 10px;"><i class="fa-solid fa-clock-rotate-left"></i> Recent Sessions</h3>
                    <p style="font-size: 13px; color: var(--text-gray);">No active sessions found.</p>
                </div>
                <div style="background: white; padding: 25px; border-radius: 20px; border: 1px solid var(--border-color);">
                    <h3 style="font-family: 'Lexend'; font-size: 16px; margin-bottom: 10px;"><i class="fa-solid fa-chart-simple"></i> Live Stats</h3>
                    <p style="font-size: 13px; color: var(--text-gray);">Launch a session to see participation data.</p>
                </div>
             </div>
        </div>
    </div>

    <div class="modal-overlay" id="forgeModal">
        <div class="forge-modal">
            <div class="forge-header">
                <button type="button" id="closeForgeBtn" style="position:absolute; top:20px; right:25px; background:none; border:none; font-size:24px; cursor:pointer; color:white; opacity:0.7;">&times;</button>
                <h2>Forge a New Quiz</h2>
                <p>The system will use rules to generate questions from your selected material.</p>
            </div>

            <form action="init_synchro_process.php" method="POST" class="forge-form">
                <div class="form-group">
                    <label>Quest Title</label>
                    <input type="text" name="quest_title" placeholder="e.g. Cell Biology Challenge" required />
                </div>

                <div class="form-group">
                    <label>Source Material</label>
                    <select name="source_material" required>
                        <option value="" disabled selected>Select from your Vault...</option>
                        <?php
                        $mats = $conn->query("SELECT id, title FROM teacher_materials WHERE user_id = $user_id ORDER BY title ASC");
                        while($m = $mats->fetch_assoc()) {
                            // In-update: 'id' ang ipapasa natin para madaling i-query sa database session
                            echo "<option value='".htmlspecialchars($m['id'])."'>".htmlspecialchars($m['title'])."</option>";
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
                            <option value="identification">Identification</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Questions</label>
                        <input type="number" name="item_count" min="5" max="50" value="10" />
                    </div>
                </div>

                <div class="form-group">
                    <label>Time Limit (Optional)</label>
                    <select name="timer_mins"> 
                        <option value="0">No Timer</option>
                        <option value="5">5 Minutes</option>
                        <option value="10">10 Minutes</option>
                        <option value="20">20 Minutes</option>
                    </select>
                </div>

                <div class="forge-footer">
                    <button type="button" id="cancelForgeBtn" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-forge-submit">Forge Quiz</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    const forgeModal = document.getElementById("forgeModal");
    const openForgeBtn = document.getElementById("openForgeBtn");
    const closeForgeBtn = document.getElementById("closeForgeBtn");
    const cancelForgeBtn = document.getElementById("cancelForgeBtn");

    // Buksan ang modal
    if(openForgeBtn) {
        openForgeBtn.onclick = (e) => { 
            e.preventDefault(); // Iwasan ang default action
            forgeModal.classList.add("active"); 
        };
    }

    // Isara ang modal function
    const closeForge = () => { 
        forgeModal.classList.remove("active"); 
    };

    if(closeForgeBtn) closeForgeBtn.onclick = closeForge;
    if(cancelForgeBtn) cancelForgeBtn.onclick = closeForge;

    // Isara kapag pinindot sa labas ng modal
    window.onclick = (event) => {
        if (event.target == forgeModal) closeForge();
    };

    // Siguraduhin na ang form ay nagsusubmit
    document.querySelector('.forge-form').onsubmit = function() {
        console.log("Form is being submitted...");
        // Maaari kang maglagay ng loading state dito kung gusto mo
        return true; 
    };
</script>
</body>
</html>