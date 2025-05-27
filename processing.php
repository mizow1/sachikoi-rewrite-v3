<?php
require_once 'config.php';
require_once 'functions.php';

// セッションから処理対象のURLリストと現在のインデックスを取得
if (!isset($_SESSION['processing_urls']) || !isset($_SESSION['current_index'])) {
    // セッションデータがない場合はトップページにリダイレクト
    header('Location: index.php');
    exit;
}

$processingUrls = $_SESSION['processing_urls'];
$currentIndex = $_SESSION['current_index'];
$total = count($processingUrls);
$progress = ($total > 0) ? floor(($currentIndex / $total) * 100) : 0;

// ページタイトル
$pageTitle = "処理中...";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="refresh" content="2;url=process_single.php">
    <style>
        .progress-container {
            width: 100%;
            background-color: #f1f1f1;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .progress-bar {
            height: 30px;
            background-color: #4a6fa5;
            border-radius: 5px;
            text-align: center;
            line-height: 30px;
            color: white;
            transition: width 0.5s;
        }
        
        .processing-info {
            margin: 20px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .current-url {
            font-weight: bold;
            word-break: break-all;
            margin: 10px 0;
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(74, 111, 165, 0.3);
            border-radius: 50%;
            border-top-color: #4a6fa5;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo $pageTitle; ?></h1>
            <p>記事のリライト処理を実行中です</p>
        </header>

        <main>
            <div class="processing-info">
                <h2>処理状況</h2>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                        <?php echo $progress; ?>%
                    </div>
                </div>
                
                <p><span class="spinner"></span> 処理中のURL:</p>
                <p class="current-url">
                    <?php 
                        if ($currentIndex < $total) {
                            echo htmlspecialchars($processingUrls[$currentIndex]);
                        } else {
                            echo "すべての処理が完了しました";
                        }
                    ?>
                </p>
                
                <p>処理済み: <?php echo $currentIndex; ?> / <?php echo $total; ?></p>
                
                <div class="note">
                    <p>この処理には時間がかかる場合があります。ページを閉じないでください。</p>
                    <p>自動的に次の処理に進みます。</p>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> 記事リライトツール</p>
        </footer>
    </div>
</body>
</html>
