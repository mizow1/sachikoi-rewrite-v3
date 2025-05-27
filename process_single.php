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

// セッションから処理対象のURLリストと現在のインデックスを取得
if (!isset($_SESSION['processing_urls']) || !isset($_SESSION['current_index'])) {
    // セッションデータがない場合はトップページにリダイレクト
    header('Location: index.php');
    exit;
}

$processingUrls = $_SESSION['processing_urls'];
$currentIndex = $_SESSION['current_index'];
$results = isset($_SESSION['results']) ? $_SESSION['results'] : [];

// すべてのURLの処理が完了している場合
if ($currentIndex >= count($processingUrls)) {
    // 結果をセッションに保存
    $_SESSION['rewrite_results'] = $results;
    unset($_SESSION['processing_urls']);
    unset($_SESSION['current_index']);
    
    // 結果ページにリダイレクト
    header('Location: results.php');
    exit;
}

// 現在のURLを取得
$currentUrl = $processingUrls[$currentIndex];

// スプレッドシートからデータを取得
$sheetData = getSheetData();

// 元の記事データを取得
$originalArticle = getOriginalArticle($sheetData, $currentUrl);

if ($originalArticle) {
    try {
        // 問題点を分析
        $issues = analyzeArticleIssues(
            $originalArticle['title'],
            $originalArticle['description'],
            $originalArticle['content']
        );
        
        // 記事を改善
        $improvedArticle = improveArticle(
            $originalArticle['title'],
            $originalArticle['description'],
            $originalArticle['content'],
            $issues
        );
        
        // リライト結果をスプレッドシートに書き込み
        $success = writeRewriteResult($currentUrl, $originalArticle['content'], $issues, $improvedArticle);
        
        $results[$currentUrl] = [
            'success' => $success,
            'issues' => $issues,
            'improved' => $improvedArticle
        ];
    } catch (Exception $e) {
        error_log("Error processing URL $currentUrl: " . $e->getMessage());
        $results[$currentUrl] = [
            'success' => false,
            'error' => '処理中にエラーが発生しました: ' . $e->getMessage()
        ];
    }
} else {
    $results[$currentUrl] = [
        'success' => false,
        'error' => '元の記事データが見つかりませんでした。'
    ];
}

// 結果を保存して次のインデックスに進む
$_SESSION['results'] = $results;
$_SESSION['current_index'] = $currentIndex + 1;

// 進捗状況を表示するページにリダイレクト
header('Location: processing.php');
exit;
?>
