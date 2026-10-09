<?php
// student_waiting_room.php - FIXED
// - No longer inserts participant on page load (process_join.php doesn't do it either)
// - Participant insert happens ONLY when student confirms nickname + avatar
// - Redirect to student_quiz_game.php when teacher starts

session_start();
if (!isset($_SESSION['joined_room_code']) || !isset($_SESSION['current_session_id'])) {
    header("Location: synchro_portal.php");
    exit();
}

$room_code = $_SESSION['joined_room_code'];

$avatars = [
    'gamer_girl' => '1a.JPG',
    'blue_robot' => '2a.JPG',
    'gorilla_vr' => '3a.JPG',
    'grey_cat'   => '4a.JPG',
    'monkey_cap' => '5a.JPG',
    'astronaut'  => '6a.JPG',
    'bear_angry' => '7a.JPG',
    'bear_bee'   => '8a.JPG',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting for Quest... | PinnaQuest</title>
    <script src="notification.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Press+Start+2P&family=Lexend:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Lexend', sans-serif; background: radial-gradient(circle, #6366f1 0%, #4338ca 100%); color: white; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; overflow: hidden; }

        /* Setup overlay */
        .setup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.8); display: flex; align-items: center; justify-content: center; z-index: 1000; }
        .setup-card { background: white; color: #1a202c; padding: 30px; border-radius: 30px; text-align: center; max-width: 500px; width: 90%; box-shadow: 0 15px 0 #4338ca; border: 4px solid #1a202c; animation: slideIn .5s ease; }
        @keyframes slideIn { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .gamified-title { font-family: 'Luckiest Guy', cursive; font-size: 28px; color: #4338ca; margin-bottom: 15px; letter-spacing: 1px; }

        .nick-input { width: 100%; padding: 12px; border-radius: 12px; border: 3px solid #cbd5e0; text-align: center; font-size: 18px; font-weight: 700; font-family: 'Lexend'; margin-bottom: 20px; text-transform: uppercase; box-sizing: border-box; }
        .nick-input:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 10px rgba(99,102,241,.2); }

        .avatar-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 25px; max-height: 250px; overflow-y: auto; padding: 10px; background: #f8fafc; border-radius: 15px; }
        .avatar-option { cursor: pointer; border-radius: 15px; border: 4px solid transparent; transition: all .2s; overflow: hidden; background: white; aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; }
        .avatar-option img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .avatar-option.selected { border-color: #000; transform: scale(1.05) rotate(3deg); box-shadow: 0 5px 15px rgba(0,0,0,.2); background: #fefce8; }
        .avatar-option:hover:not(.selected) { border-color: #c7d2fe; transform: translateY(-3px); }

        .avatar-grid::-webkit-scrollbar { width: 6px; }
        .avatar-grid::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 10px; }

        /* Error state */
        .setup-error { display: none; color: #e11d48; font-size: 13px; font-weight: 700; margin-bottom: 10px; background: #fff1f2; padding: 8px 12px; border-radius: 8px; }

        /* Waiting card */
        .waiting-card { background: white; color: #1a202c; padding: 40px; border-radius: 30px; text-align: center; box-shadow: 0 15px 0 #4338ca; max-width: 450px; width: 90%; border: 4px solid #1a202c; display: none; }
        .user-avatar-display { width: 120px; height: 120px; border-radius: 50%; border: 8px solid white; box-shadow: 0 8px 15px rgba(0,0,0,.2); margin: -100px auto 20px; overflow: hidden; background: #f1f5f9; }
        .user-avatar-display img { width: 100%; height: 100%; object-fit: cover; }
        .player-name { font-family: 'Luckiest Guy', cursive; font-size: 36px; color: #000; margin-bottom: 10px; }

        .btn-ready { background: #1db968; color: white; border: none; padding: 15px; border-radius: 15px; font-family: 'Luckiest Guy'; font-size: 22px; cursor: pointer; box-shadow: 0 6px 0 #1a4d2e; width: 100%; transition: .1s; }
        .btn-ready:active { transform: translateY(3px); box-shadow: 0 3px 0 #1a4d2e; }
        .btn-ready:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .loading-text { font-family: 'Press Start 2P', cursive; font-size: 12px; color: #000; margin-top: 25px; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .5; } }
    </style>
</head>
<body>

    <!-- STEP 1: Choose persona -->
    <div id="setupOverlay" class="setup-overlay">
        <div class="setup-card">
            <h1 class="gamified-title">CHOOSE YOUR PERSONA</h1>
            <input type="text" id="nickInput" class="nick-input" placeholder="ENTER NICKNAME..." maxlength="12" autocomplete="off" required>
            <div class="setup-error" id="setupError"></div>

            <div class="avatar-grid">
                <?php foreach ($avatars as $key => $filename): ?>
                    <div class="avatar-option" onclick="selectAvatar('<?php echo $key; ?>','<?php echo $filename; ?>',this)">
                        <img src="<?php echo $filename; ?>" alt="<?php echo $key; ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="btn-ready" id="readyBtn" onclick="confirmSetup()">READY UP!</button>
        </div>
    </div>

    <!-- STEP 2: Waiting for teacher -->
    <div id="mainWaiting" class="waiting-card">
        <div class="user-avatar-display">
            <img id="displayAvatarImg" src="" alt="Avatar">
        </div>
        <h1 class="player-name" id="displayName"></h1>
        <div class="loading-text">WAITING FOR TEACHER...</div>
        <div style="font-size:14px;color:#718096;margin-top:25px;border-top:2px dashed #e2e8f0;padding-top:20px;">
            Quest Code: <b><?php echo $room_code; ?></b>
        </div>
    </div>

    <script>
        const sessionId = "<?php echo intval($_SESSION['current_session_id']); ?>";
        let selectedAvatarKey  = '';
        let selectedAvatarFile = '';
        let participantSaved   = false;

        function selectAvatar(key, filename, el) {
            selectedAvatarKey  = key;
            selectedAvatarFile = filename;
            document.querySelectorAll('.avatar-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
        }

        function confirmSetup() {
            const nickname = document.getElementById('nickInput').value.trim();
            const errEl    = document.getElementById('setupError');
            errEl.style.display = 'none';

            if (!nickname) {
                errEl.innerText = 'Please enter a nickname!';
                errEl.style.display = 'block';
                return;
            }
            if (!selectedAvatarKey) {
                errEl.innerText = 'Please choose an avatar!';
                errEl.style.display = 'block';
                return;
            }

            const btn = document.getElementById('readyBtn');
            btn.disabled    = true;
            btn.innerText   = 'Saving...';

            // Save participant to DB (and update session on server via PHP session)
            fetch('add_participant.php', {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : `session_id=${sessionId}&nickname=${encodeURIComponent(nickname)}&avatar_key=${selectedAvatarKey}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    participantSaved = true;
                    document.getElementById('displayAvatarImg').src   = selectedAvatarFile;
                    document.getElementById('displayName').innerText  = nickname.toUpperCase();
                    document.getElementById('setupOverlay').style.display = 'none';
                    document.getElementById('mainWaiting').style.display  = 'block';
                } else {
                    btn.disabled  = false;
                    btn.innerText = 'READY UP!';
                    errEl.innerText     = data.error || 'Something went wrong. Try again.';
                    errEl.style.display = 'block';
                    if (typeof pqNotify === 'function') {
                        pqNotify(data.error || 'Something went wrong. Try again.', 'error');
                    }
                }
            })
            .catch(() => {
                btn.disabled  = false;
                btn.innerText = 'READY UP!';
                errEl.innerText     = 'Network error. Please try again.';
                errEl.style.display = 'block';
                if (typeof pqNotify === 'function') {
                    pqNotify('Network error. Please try again.', 'error');
                }
            });
        }

        // ── Poll for session start ────────────────────────────────────────────
        function checkSessionStatus() {
            if (!participantSaved) return; // Don't redirect until we've saved

            fetch(`check_session_status.php?session_id=${sessionId}`)
                .then(r => r.json())
                .then(data => {
                    const goNow = ['started', 'active', 'question', 'results', 'leaderboard', 'finished'];
                    if (goNow.includes(data.status)) {
                        window.location.href = 'student_quiz_game.php';
                    }
                })
                .catch(err => console.error('Status check error:', err));
        }

        setInterval(checkSessionStatus, 2000);
    </script>
</body>
</html>