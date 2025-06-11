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

// AIモデル設定
// モデル名、プロバイダ、トークン制限、温度、料金情報を定義
define('AI_MODELS', [
    'gpt-4o' => [
        'name' => 'GPT-4o',
        'provider' => 'openai',
        'max_tokens' => 10000,
        'temperature' => 0.7,
        'cost' => '安い',
        'speed' => '速い',
        'quality' => '普通',
        'description' => '最新のGPT-4モデル。高品質な記事生成が可能。'
    ],
    'gpt-4o-mini' => [
        'name' => 'GPT-4o Mini',
        'provider' => 'openai',
        'max_tokens' => 10000,
        'temperature' => 0.7,
        'cost' => '最安',
        'speed' => '速い',
        'quality' => '普通',
        'description' => 'GPT-4oの軽量版。コストパフォーマンスに優れたモデル。'
    ],
    'claude-3-7-sonnet-latest' => [
        'name' => 'Claude 3.7 Sonnet',
        'provider' => 'anthropic',
        'max_tokens' => 8000,
        'temperature' => 0.8,
        'cost' => '中程度',
        'speed' => '標準',
        'quality' => '高い',
        'description' => 'バランスの取れたAnthropicモデル。コストと品質のバランスが良い。'
    ],
    'claude-sonnet-4-20250514' => [
        'name' => 'Claude 4 Sonnet',
        'provider' => 'anthropic',
        'max_tokens' => 8000,
        'temperature' => 0.8,
        'cost' => '高い',
        'speed' => '標準',
        'quality' => '最高',
        'description' => 'Anthropicの最新モデル。高品質な記事生成が可能。'
    ],
    'claude-opus-4-20250514' => [
        'name' => 'Claude 4 Opus',
        'provider' => 'anthropic',
        'max_tokens' => 10000,
        'temperature' => 0.8,
        'cost' => '非常に高い',
        'speed' => '遅い',
        'quality' => '最高',
        'description' => 'Anthropicの最高品質モデル。複雑な分析と長文生成に最適。'
    ]
]);

// デフォルトAIモデル設定
define('DEFAULT_AI_MODEL', 'gpt-4o');

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッション開始
session_start();
