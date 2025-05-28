<?php
require_once 'config.php';
require_once 'functions_refactored.php';

// 処理時間の制限を設定（最大実行時間を300秒に増やす）
set_time_limit(300);

// メモリ制限を緩和
ini_set('memory_limit', '512M');

// タイムアウトを防ぐための設定
ini_set('max_execution_time', 300);
ini_set('default_socket_timeout', 300);

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

// 処理開始時間を記録
$startTime = microtime(true);

// スプレッドシートからデータを取得
$sheetData = getSheetData();

// 元の記事データを取得
$originalArticle = getOriginalArticle($sheetData, $currentUrl);

// 処理時間をチェックし、長すぎる場合は中断する
$currentTime = microtime(true);
$elapsedTime = $currentTime - $startTime;

// 240秒（4分）を超えた場合は処理を中断して次に進む
// Nginxのタイムアウトよりも短い時間を設定
$maxProcessTime = 240;
if ($elapsedTime > $maxProcessTime) {
    error_log("Processing time exceeded {$maxProcessTime} seconds. Moving to next article.");
    $_SESSION['results'][$currentUrl] = [
        'success' => false,
        'error' => "処理時間が{$maxProcessTime}秒を超えたため、次の記事に進みました。"
    ];
    $_SESSION['current_index'] = $currentIndex + 1;
    header('Location: processing.php');
    exit;
}

if ($originalArticle) {
    try {
        // 問題点を分析
        $issues = analyzeArticleIssues(
            $originalArticle['title'],
            $originalArticle['description'],
            $originalArticle['content']
        );
        
        // 分析後の処理時間をチェック
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - $startTime;
        if ($elapsedTime > $maxProcessTime) {
            error_log("Processing time after analysis exceeded {$maxProcessTime} seconds. Moving to next article.");
            $_SESSION['results'][$currentUrl] = [
                'success' => false,
                'error' => "分析後の処理時間が{$maxProcessTime}秒を超えたため、次の記事に進みました。分析結果: {$issues}"
            ];
            $_SESSION['current_index'] = $currentIndex + 1;
            header('Location: processing.php');
            exit;
        }
        
        // 記事を改善
        $improvedArticle = improveArticle(
            $originalArticle['title'],
            $originalArticle['description'],
            $originalArticle['content'],
            $issues
        );
        
        // 改善後の処理時間をチェック
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - $startTime;
        if ($elapsedTime > $maxProcessTime) {
            error_log("Processing time after improvement exceeded {$maxProcessTime} seconds. Moving to next article.");
            $_SESSION['results'][$currentUrl] = [
                'success' => false,
                'error' => "改善後の処理時間が{$maxProcessTime}秒を超えたため、スプレッドシートへの書き込みをスキップして次の記事に進みました。"
            ];
            $_SESSION['current_index'] = $currentIndex + 1;
            header('Location: processing.php');
            exit;
        }
        
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
