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

// Gemini API設定
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('GEMINI_MODEL', getenv('GEMINI_MODEL'));

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
    ],
    'gemini-2.0-flash' => [
        'name' => 'gemini-2.0-flash',
        'provider' => 'google',
        'max_tokens' => 8192,
        'temperature' => 0.7,
        'cost' => '安い',
        'speed' => '速い',
        'quality' => '高い',
        'description' => 'Google Geminiの短文生成モデル。'
    ]
]);

// デフォルトAIモデル設定
define('DEFAULT_AI_MODEL', 'gpt-4o');

// .envから利用可能なモデルを動的に取得
$availableModels = [];

// OpenAIモデルが設定されている場合
if (!empty(OPENAI_API_KEY) && !empty(OPENAI_MODEL)) {
    $openaiModel = OPENAI_MODEL;
    if (isset(AI_MODELS[$openaiModel])) {
        $availableModels[$openaiModel] = AI_MODELS[$openaiModel];
    } else {
        // AI_MODELSに定義がない場合は基本情報を追加
        $availableModels[$openaiModel] = [
            'name' => $openaiModel,
            'provider' => 'openai',
            'description' => 'OpenAI モデル (.envで設定)'
        ];
    }
}

// Geminiモデルが設定されている場合
if (!empty(GEMINI_API_KEY) && !empty(GEMINI_MODEL)) {
    $geminiModel = GEMINI_MODEL;
    if (isset(AI_MODELS[$geminiModel])) {
        $availableModels[$geminiModel] = AI_MODELS[$geminiModel];
    } else {
        // AI_MODELSに定義がない場合は基本情報を追加
        $availableModels[$geminiModel] = [
            'name' => $geminiModel,
            'provider' => 'google',
            'description' => 'Google Gemini モデル (.envで設定)'
        ];
    }
}

define('AVAILABLE_MODELS', $availableModels);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// セッション開始
session_start();
