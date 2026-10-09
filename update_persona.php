<?php
// update_persona.php
// AJAX: saves display_name + avatar_key to DB, updates session, checks achievements

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/xp_config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$conn    = new mysqli("localhost", "root", "", "pinnaquest_db");
$user_id = intval($_SESSION['user_id']);

// ── Validate input ────────────────────────────────────────────────
$display_name = trim($_POST['display_name'] ?? '');
$avatar_key   = trim($_POST['avatar_key']   ?? 'default');

if (empty($display_name)) {
    echo json_encode(['success' => false, 'error' => 'Name cannot be empty']);
    exit();
}
if (mb_strlen($display_name) > 30) {
    echo json_encode(['success' => false, 'error' => 'Name too long (max 30 chars)']);
    exit();
}

$allowed_avatars = ['default','ninja','robot','ghost','astro','knight','fire','dragon','cat','crown'];
if (!in_array($avatar_key, $allowed_avatars)) {
    $avatar_key = 'default';
}

$display_name_safe = $conn->real_escape_string($display_name);
$avatar_key_safe   = $conn->real_escape_string($avatar_key);

// ── Save to DB ────────────────────────────────────────────────────
$ok = $conn->query(
    "UPDATE users 
     SET display_name = '$display_name_safe', avatar_key = '$avatar_key_safe'
     WHERE id = $user_id"
);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'DB update failed: ' . $conn->error]);
    exit();
}

// ── Update session so all future page loads reflect new name ──────
$_SESSION['user_name']       = $display_name;
$_SESSION['user_avatar_key'] = $avatar_key;

// ── Return fresh profile data so JS can update UI instantly ───────
// (Re-read level/XP from DB in case it changed concurrently)
$user_res = $conn->query("SELECT xp, level FROM users WHERE id = $user_id");
$user     = $user_res->fetch_assoc();

$total_xp     = intval($user['xp'] ?? 0);
$xp_per_level = PINNAQUEST_XP_PER_LEVEL;
$level        = max(1, floor($total_xp / $xp_per_level) + 1);
$xp_this_lvl  = $total_xp % $xp_per_level;
$progress_pct = round(($xp_this_lvl / $xp_per_level) * 100);

echo json_encode([
    'success'       => true,
    'display_name'  => $display_name,
    'avatar_key'    => $avatar_key,
    'initial'       => strtoupper(mb_substr($display_name, 0, 1)),
    'xp'            => $total_xp,
    'level'         => $level,
    'progress_pct'  => $progress_pct,
    'xp_this_level' => $xp_this_lvl,
    'xp_per_level'  => $xp_per_level,
]);

$conn->close();
?>