<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: loginpanel.php");
    exit();
}
$teacher_user_id = intval($_SESSION['user_id']);

require_once __DIR__ . '/synchro_identity.php';
require_once __DIR__ . '/synchro_invite_support.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
if ($conn->connect_error) {
    die("Database connection failed.");
}
ensureSynchroIdentitySchema($conn);
ensureSynchroInviteSchema($conn);

// 1. Inayos ang $_GET['room'] (dahil room=$room_code ang galing sa process.php)
$room_code = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';

// 2. Inayos ang query: 'title' imbes na 'room_name' para mag-match sa database natin
$session_query = $conn->query("SELECT id, title, teacher_user_id FROM synchro_sessions WHERE room_code = '$room_code'");

if (!$session_query || $session_query->num_rows == 0) { 
    die("Invalid Room Code. Please go back and try again."); 
}

$session = $session_query->fetch_assoc();
$session_id = $session['id'];
$session_owner_id = intval($session['teacher_user_id'] ?? 0);

// Claim an older session only when it is still the room this teacher created
// in their current login session.
if ($session_owner_id === 0 && ($_SESSION['active_room_code'] ?? '') === $room_code) {
    $claim_result = $conn->query(
        "UPDATE synchro_sessions
         SET teacher_user_id = $teacher_user_id
         WHERE id = " . intval($session_id) . " AND teacher_user_id IS NULL"
    );
    if ($claim_result && $conn->affected_rows === 1) {
        $session_owner_id = $teacher_user_id;
    } else {
        $owner_result = $conn->query(
            "SELECT teacher_user_id FROM synchro_sessions WHERE id = " . intval($session_id) . " LIMIT 1"
        );
        $owner_row = $owner_result ? $owner_result->fetch_assoc() : null;
        $session_owner_id = intval($owner_row['teacher_user_id'] ?? 0);
    }
}
if ($session_owner_id !== $teacher_user_id) {
    http_response_code(403);
    die("You do not have access to this Synchro-Quiz session.");
}

// Listahan ng avatars para sa mapping
$avatars = [
    'gamer_girl' => '1a.JPG', 
    'blue_robot' => '2a.JPG', 
    'gorilla_vr'  => '3a.JPG', 
    'grey_cat'    => '4a.JPG', 
    'monkey_cap'  => '5a.JPG', 
    'astronaut'   => '6a.JPG', 
    'bear_angry'  => '7a.JPG', 
    'bear_bee'    => '8a.JPG'
];
?>
<!doctype html>
<html lang="en">
<head>
<script src="notification.js"></script>
    <meta charset="UTF-8" />
    <title>PinnaQuest Lobby | <?php echo htmlspecialchars($room_code); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@700;800&family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
   <style>
    @import url('https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Press+Start+2P&family=Lexend:wght@700;800&display=swap');

    body { 
        font-family: 'Lexend', sans-serif; 
        background: radial-gradient(circle, #6366f1 0%, #4338ca 100%); 
        color: white; 
        margin: 0; 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        min-height: 100vh; 
        padding: 50px; 
    }
    
    .lobby-header { 
        background: white; 
        color: #1a202c; 
        padding: 25px 45px; 
        border-radius: 30px; 
        display: flex; 
        align-items: center; 
        box-shadow: 0 15px 0px #c7d2fe; 
        margin-bottom: 50px; 
        width: 100%; 
        max-width: 950px; 
        border: 4px solid #4338ca;
    }

    .code-box { 
        text-align: center; 
        border-right: 3px dashed #e2e8f0; 
        padding-right: 40px; 
        margin-right: 40px; 
    }

    .code-label { 
        font-size: 14px; 
        color: #6366f1; 
        text-transform: uppercase; 
        font-weight: 800;
        letter-spacing: 1px;
    }

    .room-code { 
        font-family: 'Luckiest Guy', cursive; 
        font-size: 42px; 
        color: #1a202c; 
        margin-top: 10px;
        text-shadow: 2px 2px #cbd5e0;
    }

    .quest-title-text {
        font-family: 'Luckiest Guy', cursive; 
        font-size: 32px; 
        color: #4338ca; 
        letter-spacing: 2px;
        margin: 0;
    }

    .status-text { 
        font-family: 'Luckiest Guy', cursive; 
        font-size: 36px; 
        color: #f7f6f6; 
        text-shadow: 3px 3px #000;
        margin-bottom: 30px;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); filter: brightness(1); }
        50% { transform: scale(1.05); filter: brightness(1.2); }
        100% { transform: scale(1); filter: brightness(1); }
    }

    /* Participant Grid Style */
    .participant-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 20px;
        width: 100%;
        max-width: 1000px;
        margin-bottom: 40px;
    }

    .student-card { 
        background: #ffffff; 
        color: #4338ca;
        padding: 20px; 
        border-radius: 20px; 
        text-align: center; 
        box-shadow: 0 8px 0px #312e81; 
        transition: transform 0.2s;
        border: 3px solid #1a202c;
    }
    .student-card:hover { transform: translateY(-5px); }

    .student-avatar-img {
        width: 80px;
        height: 80px;
        border-radius: 15px;
        object-fit: cover;
        background: #f1f5f9;
        border: 2px solid #e2e8f0;
    }

    .student-name { 
        font-family: 'Luckiest Guy', sans-serif; 
        font-size: 18px; 
        margin-top: 10px; 
        color: #1a202c;
        text-transform: uppercase;
    }

    .btn-start {
        background: #ffde59;
        color: #1a202c;
        border: 4px solid #1a202c;
        padding: 15px 40px;
        font-family: 'Luckiest Guy', cursive;
        font-size: 24px;
        border-radius: 20px;
        cursor: pointer;
        box-shadow: 0 8px 0px #b29a3e;
        display: none;
    }
    .btn-start:active { transform: translateY(4px); box-shadow: 0 4px 0px #b29a3e; }

    .invite-toolbar {
        width: min(1000px, 100%);
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin: -10px 0 28px;
        color: #fff;
        font: 600 13px/1.5 'Lexend', sans-serif;
    }
    .invite-trigger {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 9px;
        border: 2px solid #fff;
        border-radius: 14px;
        padding: 12px 18px;
        background: #fff;
        color: #4338ca;
        font: 800 14px 'Lexend', sans-serif;
        cursor: pointer;
        box-shadow: 0 5px 0 #312e81;
    }
    .invite-trigger:hover { transform: translateY(-2px); }
    .invite-overlay {
        position: fixed;
        inset: 0;
        z-index: 11000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, .62);
        backdrop-filter: blur(5px);
    }
    .invite-overlay.show { display: flex; }
    .invite-modal {
        width: min(620px, 100%);
        max-height: min(82vh, 760px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 22px;
        background: #fff;
        color: #1a202c;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .3);
    }
    .invite-modal-header, .invite-modal-footer { padding: 20px 24px; }
    .invite-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        border-bottom: 1px solid #e9eaf5;
    }
    .invite-modal-header h2 {
        margin: 0 0 5px;
        color: #3730a3;
        font: 800 20px 'Lexend', sans-serif;
    }
    .invite-modal-header p, .invite-hint {
        margin: 0;
        color: #64748b;
        font: 500 12px/1.5 'Inter', sans-serif;
    }
    .invite-close {
        border: 0;
        background: transparent;
        color: #64748b;
        font-size: 25px;
        cursor: pointer;
    }
    .invite-search-wrap { padding: 16px 24px 8px; }
    .invite-search-wrap label {
        display: block;
        margin-bottom: 7px;
        color: #334155;
        font: 700 12px 'Inter', sans-serif;
    }
    .invite-search {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 11px;
        outline: none;
        font: 500 14px 'Inter', sans-serif;
    }
    .invite-search:focus { border-color: #6366f1; box-shadow: 0 0 0 3px #e0e7ff; }
    .invite-hint { padding: 0 24px 8px; }
    .invite-student-list {
        min-height: 90px;
        max-height: 330px;
        overflow-y: auto;
        padding: 4px 24px 14px;
    }
    .invite-student-option {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 10px;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        font: 600 14px 'Inter', sans-serif;
        cursor: pointer;
    }
    .invite-student-option:hover { background: #f8fafc; }
    .invite-student-option input { width: 18px; height: 18px; accent-color: #4f46e5; }
    .invite-empty { padding: 24px 8px; text-align: center; color: #64748b; font: 500 13px 'Inter', sans-serif; }
    .invite-modal-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-top: 1px solid #e9eaf5;
    }
    .invite-selected-count { color: #475569; font: 700 12px 'Inter', sans-serif; }
    .invite-status { padding: 0 24px 12px; color: #166534; font: 700 12px/1.5 'Inter', sans-serif; }
    .invite-status.error { color: #be123c; }
    .invite-actions { display: flex; gap: 9px; }
    .invite-actions button {
        border: 0;
        border-radius: 10px;
        padding: 10px 14px;
        font: 800 12px 'Inter', sans-serif;
        cursor: pointer;
    }
    .invite-cancel { color: #475569; background: #f1f5f9; }
    .invite-send { color: #fff; background: #4f46e5; }
    .invite-send:disabled { cursor: not-allowed; opacity: .55; }
    @media (max-width: 620px) {
        .invite-toolbar { align-items: stretch; }
        .invite-toolbar > span { flex-basis: 100%; }
        .invite-trigger { justify-content: center; }
        .invite-modal-footer { flex-wrap: wrap; }
        .invite-selected-count { flex-basis: 100%; }
    }

    /* --- CUSTOM CONFIRM MODAL (replaces browser confirm() "localhost says") --- */
    .confirm-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(20, 20, 40, 0.55);
        backdrop-filter: blur(6px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
    }
    .confirm-overlay.active { display: flex; }

    .confirm-box {
        background: white;
        color: #1a202c;
        width: 90%;
        max-width: 400px;
        border-radius: 24px;
        padding: 35px 30px;
        text-align: center;
        border: 4px solid #4338ca;
        box-shadow: 0 20px 0px #312e81;
        animation: confirmPop 0.3s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes confirmPop {
        from { transform: scale(0.85); opacity: 0; }
        to   { transform: scale(1);    opacity: 1; }
    }

    .confirm-icon {
        width: 64px; height: 64px;
        background: #ede9fe;
        color: #6366f1;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px;
        margin: 0 auto 18px;
        border: 3px solid #c7d2fe;
    }

    .confirm-box h3 {
        font-family: 'Luckiest Guy', cursive;
        font-size: 22px;
        letter-spacing: 1px;
        color: #4338ca;
        margin-bottom: 10px;
    }

    .confirm-box p {
        font-family: 'Lexend', sans-serif;
        font-size: 13.5px;
        font-weight: 600;
        color: #718096;
        line-height: 1.5;
        margin-bottom: 26px;
    }

    .confirm-actions {
        display: flex;
        gap: 12px;
    }
    .confirm-actions button {
        flex: 1;
        padding: 13px;
        border-radius: 14px;
        font-family: 'Lexend', sans-serif;
        font-weight: 800;
        font-size: 14px;
        cursor: pointer;
        transition: 0.15s;
        border: none;
    }
    .confirm-btn-cancel {
        background: #f1f5f9;
        color: #64748b;
    }
    .confirm-btn-cancel:hover { background: #e2e8f0; }

    .confirm-btn-ok {
        background: #6366f1;
        color: white;
        box-shadow: 0 4px 0 #4338ca;
    }
    .confirm-btn-ok:hover { background: #4f46e5; }
    .confirm-btn-ok:active { transform: translateY(3px); box-shadow: 0 1px 0 #4338ca; }
</style>
</head>
<body>

  <div class="lobby-header">
    <div class="code-box">
        <div class="code-label">READY YOUR GEARS! JOIN CODE:</div>
        <div class="room-code"><?php echo $room_code; ?></div>
    </div>
    <div style="flex:1;">
        <p style="margin:0; font-size:12px; color:#718096; font-weight:bold; text-transform:uppercase;">Current Quest:</p>
        <h1 class="quest-title-text"><?php echo htmlspecialchars($session['title']); ?></h1>
        <div style="font-size:14px; color:#6366f1; font-weight:600;">Waiting for participants to enter...</div>
    </div>
</div>

<div class="invite-toolbar">
    <span>Send a Synchro-Quiz invitation directly to a student’s account.</span>
    <button type="button" class="invite-trigger" id="openInviteModal">
        <i class="fa-solid fa-user-plus"></i> Invite Students
    </button>
</div>

    <div class="status-text" id="statusMessage">Waiting for participants...</div>

    <div class="participant-grid" id="participantGrid"></div>

    <button class="btn-start" id="startQuizBtn">Start Quest <i class="fa-solid fa-arrow-right"> </i></button>

    <div class="invite-overlay" id="inviteOverlay" aria-hidden="true">
        <section class="invite-modal" role="dialog" aria-modal="true" aria-labelledby="inviteTitle">
            <header class="invite-modal-header">
                <div>
                    <h2 id="inviteTitle">Invite Students</h2>
                    <p>Search any registered student account and send an in-app invitation.</p>
                </div>
                <button type="button" class="invite-close" id="closeInviteModal" aria-label="Close">&times;</button>
            </header>
            <div class="invite-search-wrap">
                <label for="inviteSearch">Search by student name or email</label>
                <input class="invite-search" id="inviteSearch" type="search" autocomplete="off" placeholder="Type a name or email">
            </div>
            <p class="invite-hint">Up to 50 matches are shown. Students already invited or in the lobby are hidden.</p>
            <div class="invite-student-list" id="inviteStudentList" aria-live="polite"></div>
            <div class="invite-status" id="inviteStatus" role="status"></div>
            <footer class="invite-modal-footer">
                <span class="invite-selected-count" id="inviteSelectedCount">0 selected</span>
                <div class="invite-actions">
                    <button type="button" class="invite-cancel" id="cancelInviteModal">Close</button>
                    <button type="button" class="invite-send" id="sendInvitesBtn" disabled>Send Invites</button>
                </div>
            </footer>
        </section>
    </div>

    <!-- Custom Confirm Modal (replaces browser confirm()) -->
    <div class="confirm-overlay" id="confirmOverlay">
        <div class="confirm-box">
            <div class="confirm-icon"><i class="fa-solid fa-bolt"></i></div>
            <h3>Start the Quest?</h3>
            <p id="confirmMessage">Are you sure you want to start the quest?</p>
            <div class="confirm-actions">
                <button class="confirm-btn-cancel" id="confirmCancelBtn">Cancel</button>
                <button class="confirm-btn-ok" id="confirmOkBtn">Yes, Start!</button>
            </div>
        </div>
    </div>

<script>
        const sessionId = <?php echo $session_id; ?>;
        const avatarMap = <?php echo json_encode($avatars); ?>;
        const grid = document.getElementById('participantGrid');
        const statusMsg = document.getElementById('statusMessage');
        const startBtn = document.getElementById('startQuizBtn');
        const inviteOverlay = document.getElementById('inviteOverlay');
        const inviteList = document.getElementById('inviteStudentList');
        const inviteSearch = document.getElementById('inviteSearch');
        const inviteStatus = document.getElementById('inviteStatus');
        const inviteCount = document.getElementById('inviteSelectedCount');
        const sendInvitesBtn = document.getElementById('sendInvitesBtn');
        const selectedInviteStudents = new Map();
        let inviteSearchTimer;

        function updateInviteSelection() {
            inviteCount.textContent = `${selectedInviteStudents.size} selected`;
            sendInvitesBtn.disabled = selectedInviteStudents.size === 0;
        }

        function setInviteStatus(message, isError = false) {
            inviteStatus.textContent = message;
            inviteStatus.classList.toggle('error', isError);
        }

        async function loadInviteStudents() {
            inviteList.replaceChildren();
            const loading = document.createElement('div');
            loading.className = 'invite-empty';
            loading.textContent = 'Loading student accounts...';
            inviteList.appendChild(loading);

            const params = new URLSearchParams({
                session_id: String(sessionId),
                search: inviteSearch.value.trim()
            });
            try {
                const response = await fetch(`teacher_synchro_invites.php?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Could not load student accounts.');
                }

                inviteList.replaceChildren();
                if (!data.students.length) {
                    const empty = document.createElement('div');
                    empty.className = 'invite-empty';
                    empty.textContent = 'No available student accounts found.';
                    inviteList.appendChild(empty);
                    return;
                }

                data.students.forEach(student => {
                    const label = document.createElement('label');
                    label.className = 'invite-student-option';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = selectedInviteStudents.has(Number(student.id));
                    checkbox.addEventListener('change', () => {
                        const id = Number(student.id);
                        if (checkbox.checked && !selectedInviteStudents.has(id) && selectedInviteStudents.size >= 100) {
                            checkbox.checked = false;
                            setInviteStatus('You can invite up to 100 students at a time.', true);
                            return;
                        }
                        if (checkbox.checked) selectedInviteStudents.set(id, student.name);
                        else selectedInviteStudents.delete(id);
                        updateInviteSelection();
                    });
                    const name = document.createElement('span');
                    name.textContent = student.name || 'Student';
                    label.append(checkbox, name);
                    inviteList.appendChild(label);
                });
            } catch (error) {
                inviteList.replaceChildren();
                const message = document.createElement('div');
                message.className = 'invite-empty';
                message.textContent = error.message || 'Could not load student accounts.';
                inviteList.appendChild(message);
            }
        }

        function openInviteDialog() {
            inviteOverlay.classList.add('show');
            inviteOverlay.setAttribute('aria-hidden', 'false');
            inviteSearch.focus();
            setInviteStatus('');
            loadInviteStudents();
        }

        function closeInviteDialog() {
            inviteOverlay.classList.remove('show');
            inviteOverlay.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('openInviteModal').addEventListener('click', openInviteDialog);
        document.getElementById('closeInviteModal').addEventListener('click', closeInviteDialog);
        document.getElementById('cancelInviteModal').addEventListener('click', closeInviteDialog);
        inviteOverlay.addEventListener('click', event => {
            if (event.target === inviteOverlay) closeInviteDialog();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && inviteOverlay.classList.contains('show')) closeInviteDialog();
        });
        inviteSearch.addEventListener('input', () => {
            clearTimeout(inviteSearchTimer);
            inviteSearchTimer = setTimeout(loadInviteStudents, 250);
        });
        sendInvitesBtn.addEventListener('click', async () => {
            const studentIds = Array.from(selectedInviteStudents.keys());
            if (!studentIds.length) return;

            sendInvitesBtn.disabled = true;
            sendInvitesBtn.textContent = 'Sending...';
            setInviteStatus('');
            try {
                const body = new URLSearchParams({
                    session_id: String(sessionId),
                    student_ids: JSON.stringify(studentIds)
                });
                const response = await fetch('teacher_synchro_invites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Could not send invitations.');
                }
                setInviteStatus(data.message);
                selectedInviteStudents.clear();
                updateInviteSelection();
                await loadInviteStudents();
            } catch (error) {
                setInviteStatus(error.message || 'Could not send invitations.', true);
            } finally {
                sendInvitesBtn.textContent = 'Send Invites';
                updateInviteSelection();
            }
        });

        function checkParticipants() {
            fetch(`get_participants.php?session_id=${sessionId}`)
                .then(response => response.json())
                .then(data => {
                    let newHTML = ''; 
                    const currentCount = data.length;

                    if (currentCount > 0) {
                        statusMsg.innerText = `${currentCount} Adventurer${currentCount > 1 ? 's' : ''} Joined!`;
                        startBtn.style.display = 'block'; 
                        
                        data.forEach(student => {
                            const avatarFile = avatarMap[student.avatar_key] || 'default.jpg';
                            
                            newHTML += `
                                <div class="student-card">
                                    <img src="${avatarFile}" class="student-avatar-img" alt="Avatar">
                                    <div class="student-name">${student.nickname}</div>
                                </div>
                            `;
                        });

                        grid.innerHTML = newHTML;

                    } else {
                        statusMsg.innerText = "Waiting for participants...";
                        grid.innerHTML = ''; 
                        startBtn.style.display = 'none';
                    }
                })
                .catch(err => console.error("Error fetching participants:", err));
        }

        // --- Custom confirm modal helper (replaces browser confirm()) ---
        function showConfirm(message) {
            return new Promise((resolve) => {
                const overlay   = document.getElementById('confirmOverlay');
                const msgEl     = document.getElementById('confirmMessage');
                const okBtn     = document.getElementById('confirmOkBtn');
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

        // START QUEST LOGIC
        startBtn.addEventListener('click', async function() {
            const confirmed = await showConfirm("Are you sure you want to start the quest?");
            if (!confirmed) return;

            fetch('start_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `session_id=${sessionId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Kapag success ang update sa DB, lipat si Teacher sa monitor screen
                    window.location.href = "teacher_quiz_monitor.php?session_id=" + sessionId;
                } else {
                    pqNotify("Failed to start the quest. Try again.", "error");
                }
            })
            .catch(err => console.error("Error starting session:", err));
        });

        setInterval(checkParticipants, 2000);
        checkParticipants();
    </script>
</body>
</html>