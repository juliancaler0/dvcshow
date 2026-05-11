<?php
/**
 * DVCSHOW Editor API
 *
 * Endpoints (action via ?action=...):
 *   verify  : POST   - just checks the password
 *   save    : POST   - body is the new index.html (text/html). Backs up current first.
 *   upload  : POST   - multipart/form-data with field "image". Saves to Images/.
 *   backups : GET    - returns list of available backups
 *   restore : POST   - form field "filename" - restores a backup (backs up current first)
 *
 * All endpoints require the header X-Editor-Password matching the configured password.
 */

// --- safety: disallow direct error output that could leak paths ---
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// --- load config ---
$configPath = __DIR__ . '/editor-config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'editor-config.php not found. See editor-config.example.php']);
    exit;
}
require $configPath;

if (!isset($EDITOR_PASSWORD_HASH) || !is_string($EDITOR_PASSWORD_HASH)) {
    http_response_code(500);
    echo json_encode(['error' => 'EDITOR_PASSWORD_HASH not configured']);
    exit;
}

// --- collect & verify password from header ---
function get_password_header() {
    // Some setups don't expose custom headers via $_SERVER; getallheaders is more reliable
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'X-Editor-Password') === 0) return $v;
        }
    }
    return $_SERVER['HTTP_X_EDITOR_PASSWORD'] ?? '';
}

$provided = get_password_header();

// brief delay to slow down brute-force from the same connection
usleep(150 * 1000);

if (!is_string($provided) || $provided === '' || !password_verify($provided, $EDITOR_PASSWORD_HASH)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid password']);
    exit;
}

$action = $_GET['action'] ?? '';

$SITE_ROOT = __DIR__;
$INDEX_PATH = $SITE_ROOT . '/index.html';
$IMAGES_DIR = $SITE_ROOT . '/Images';
$BACKUP_DIR = $SITE_ROOT . '/.editor-backups';
$MAX_BACKUPS = 20;
$MAX_UPLOAD_BYTES = 8 * 1024 * 1024; // 8 MB
$ALLOWED_MIME = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
$ALLOWED_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

function ensure_dir($path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

function rotate_backups($dir, $max) {
    $files = glob($dir . '/index-*.html');
    if (!$files) return;
    sort($files);
    while (count($files) > $max) {
        $f = array_shift($files);
        @unlink($f);
    }
}

function backup_current_index($indexPath, $backupDir) {
    if (!file_exists($indexPath)) return null;
    ensure_dir($backupDir);
    $name = 'index-' . date('Ymd-His') . '.html';
    $dest = $backupDir . '/' . $name;
    // If exists from same second, suffix it
    $i = 1;
    while (file_exists($dest)) {
        $dest = $backupDir . '/index-' . date('Ymd-His') . '-' . $i . '.html';
        $i++;
    }
    if (@copy($indexPath, $dest)) {
        return $dest;
    }
    return null;
}

function safe_image_basename($name) {
    // Strip directory parts
    $name = basename($name);
    // Lowercase extension match, normalize
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    if ($name === '' || $name[0] === '.') {
        $name = 'upload-' . bin2hex(random_bytes(4));
    }
    return $name;
}

function unique_image_path($dir, $basename) {
    $target = $dir . '/' . $basename;
    if (!file_exists($target)) return [$basename, $target];

    $dot = strrpos($basename, '.');
    $stem = $dot === false ? $basename : substr($basename, 0, $dot);
    $ext  = $dot === false ? '' : substr($basename, $dot);

    for ($i = 1; $i < 1000; $i++) {
        $cand = $stem . '-' . $i . $ext;
        $target = $dir . '/' . $cand;
        if (!file_exists($target)) return [$cand, $target];
    }
    // Fallback: random suffix
    $cand = $stem . '-' . bin2hex(random_bytes(3)) . $ext;
    return [$cand, $dir . '/' . $cand];
}

switch ($action) {

    case 'verify': {
        echo json_encode(['ok' => true]);
        break;
    }

    case 'save': {
        $body = file_get_contents('php://input');
        if ($body === false || strlen($body) < 200) {
            http_response_code(400);
            echo json_encode(['error' => 'HTML body too small or unreadable']);
            exit;
        }
        if (strlen($body) > 5 * 1024 * 1024) {
            http_response_code(413);
            echo json_encode(['error' => 'HTML body too large']);
            exit;
        }
        // Sanity check it looks like HTML
        if (stripos($body, '<html') === false || stripos($body, '</html>') === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload does not look like a full HTML document']);
            exit;
        }

        $backup = backup_current_index($INDEX_PATH, $BACKUP_DIR);
        rotate_backups($BACKUP_DIR, $MAX_BACKUPS);

        // Atomic write via temp file + rename
        $tmp = $INDEX_PATH . '.tmp.' . bin2hex(random_bytes(4));
        $bytes = @file_put_contents($tmp, $body);
        if ($bytes === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Cannot write temporary file. Check folder permissions.']);
            exit;
        }
        if (!@rename($tmp, $INDEX_PATH)) {
            @unlink($tmp);
            http_response_code(500);
            echo json_encode(['error' => 'Cannot replace index.html. Check folder permissions.']);
            exit;
        }
        @chmod($INDEX_PATH, 0644);

        echo json_encode([
            'ok' => true,
            'bytes' => $bytes,
            'backup' => $backup ? basename($backup) : null
        ]);
        break;
    }

    case 'upload': {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'No image uploaded or upload error']);
            exit;
        }
        $file = $_FILES['image'];
        if ($file['size'] <= 0 || $file['size'] > $MAX_UPLOAD_BYTES) {
            http_response_code(413);
            echo json_encode(['error' => 'Image too large']);
            exit;
        }
        // Verify it's really an image (PHP-side)
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info['mime'], $ALLOWED_MIME, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'File is not a recognized image (png/jpg/webp/gif only)']);
            exit;
        }
        $basename = safe_image_basename($file['name']);
        // Check extension is allowed; if not, derive from mime
        $extPart = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($extPart, $ALLOWED_EXT, true)) {
            $mimeToExt = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];
            $newExt = $mimeToExt[$info['mime']] ?? 'png';
            $basename = pathinfo($basename, PATHINFO_FILENAME) . '.' . $newExt;
        }
        ensure_dir($IMAGES_DIR);
        list($finalName, $finalPath) = unique_image_path($IMAGES_DIR, $basename);

        if (!@move_uploaded_file($file['tmp_name'], $finalPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not save uploaded file. Check Images/ permissions.']);
            exit;
        }
        @chmod($finalPath, 0644);

        echo json_encode([
            'ok' => true,
            'path' => 'Images/' . $finalName,
            'bytes' => filesize($finalPath)
        ]);
        break;
    }

    case 'backups': {
        if (!is_dir($BACKUP_DIR)) {
            echo json_encode(['backups' => []]);
            break;
        }
        $files = glob($BACKUP_DIR . '/index-*.html');
        $names = array_map('basename', $files ?: []);
        rsort($names);
        echo json_encode(['backups' => array_slice($names, 0, $MAX_BACKUPS)]);
        break;
    }

    case 'restore': {
        $filename = $_POST['filename'] ?? '';
        if (!is_string($filename) || !preg_match('/^index-[0-9]{8}-[0-9]{6}(-[0-9]+)?\.html$/', $filename)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid backup filename']);
            exit;
        }
        $source = $BACKUP_DIR . '/' . $filename;
        if (!file_exists($source)) {
            http_response_code(404);
            echo json_encode(['error' => 'Backup not found']);
            exit;
        }
        // Back up current first
        backup_current_index($INDEX_PATH, $BACKUP_DIR);
        rotate_backups($BACKUP_DIR, $MAX_BACKUPS);

        $tmp = $INDEX_PATH . '.tmp.' . bin2hex(random_bytes(4));
        if (!@copy($source, $tmp)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to stage restore']);
            exit;
        }
        if (!@rename($tmp, $INDEX_PATH)) {
            @unlink($tmp);
            http_response_code(500);
            echo json_encode(['error' => 'Failed to restore index.html']);
            exit;
        }
        @chmod($INDEX_PATH, 0644);
        echo json_encode(['ok' => true, 'restored' => $filename]);
        break;
    }

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown action']);
}
