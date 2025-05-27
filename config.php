<?php
// 環境変数の読み込み
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // コメント行をスキップ
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        // 環境変数を設定
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// Google Sheets API設定
define('GOOGLE_SHEETS_API_KEY', getenv('GOOGLE_SHEETS_API_KEY'));
define('SPREADSHEET_ID', getenv('SPREADSHEET_ID'));
define('SHEET_NAME', getenv('SHEET_NAME'));
define('SERVICE_ACCOUNT_JSON', getenv('SERVICE_ACCOUNT_JSON'));
define('USE_SERVICE_ACCOUNT', getenv('USE_SERVICE_ACCOUNT') === 'true');

// OpenAI API設定
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY'));
define('OPENAI_MODEL', getenv('OPENAI_MODEL'));

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッション開始
session_start();
