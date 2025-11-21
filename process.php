<?php
require_once 'config.php';
require_once 'functions.php';

// 処理時間の制限を設定（最大実行時間を180秒に設定）
set_time_limit(180);

// メモリ制限を緩和
ini_set('memory_limit', '256M');

// エラー表示を有効化
ini_set('display_errors', 1);
error_reporting(E_ALL);

// POSTリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 選択されたURLを取得
    $selectedUrls = isset($_POST['selected_urls']) ? $_POST['selected_urls'] : [];
    
    if (empty($selectedUrls)) {
        $_SESSION['message'] = '記事が選択されていません。';
        header('Location: index.php');
        exit;
    }
    
    // 選択されたAIモデルを取得
    $aiModel = isset($_POST['ai_model']) ? $_POST['ai_model'] : 'gpt-5-mini';
    
    // 処理を軸に分割して実行するためのパラメータをセッションに保存
    $_SESSION['processing_urls'] = $selectedUrls;
    $_SESSION['current_index'] = 0;
    $_SESSION['results'] = [];
    $_SESSION['ai_model'] = $aiModel;
    
    // 処理ページにリダイレクト
    header('Location: process_single.php');
    exit;
} elseif (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    // Ajaxリクエストの場合、処理状況を返す
    if (isset($_SESSION['processing_urls']) && isset($_SESSION['current_index'])) {
        $total = count($_SESSION['processing_urls']);
        $current = $_SESSION['current_index'];
        $progress = ($total > 0) ? floor(($current / $total) * 100) : 0;
        
        echo json_encode([
            'status' => 'processing',
            'progress' => $progress,
            'current' => $current,
            'total' => $total,
            'current_url' => ($current < $total) ? $_SESSION['processing_urls'][$current] : ''
        ]);
    } else {
        echo json_encode([
            'status' => 'idle',
            'progress' => 0
        ]);
    }
    exit;
} else {
    // GETリクエストの場合はトップページにリダイレクト
    header('Location: index.php');
    exit;
}
