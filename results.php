<?php
require_once 'config.php';
require_once 'functions.php';

// セッションから結果を取得
$results = isset($_SESSION['rewrite_results']) ? $_SESSION['rewrite_results'] : [];

// 使用したAIモデルを取得
$aiModel = isset($_SESSION['ai_model']) ? $_SESSION['ai_model'] : 'gpt-5-mini';

// 結果が空の場合はトップページにリダイレクト
if (empty($results)) {
    header('Location: index.php');
    exit;
}

// ページタイトル
$pageTitle = "リライト結果";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo $pageTitle; ?></h1>
            <nav>
                <a href="index.php" class="btn"><i class="fas fa-arrow-left"></i> 一覧に戻る</a>
            </nav>
        </header>

        <main>
            <div class="results-summary">
                <h2>処理結果</h2>
                <p>処理されたURL数: <?php echo count($results); ?></p>
                <p class="ai-model-used">使用したAIモデル: <strong><?php echo htmlspecialchars($aiModel); ?></strong></p>
            </div>

            <div class="results-list">
                <?php foreach ($results as $url => $result): ?>
                    <div class="result-item <?php echo $result['success'] ? 'success' : 'error'; ?>">
                        <h3><?php echo htmlspecialchars($url); ?></h3>
                        
                        <?php if ($result['success']): ?>
                            <div class="result-status success">
                                <i class="fas fa-check-circle"></i> 処理成功
                            </div>
                            
                            <div class="result-details">
                                <div class="result-section">
                                    <h4>問題点</h4>
                                    <div class="issues-content">
                                        <?php echo nl2br(htmlspecialchars($result['issues'])); ?>
                                    </div>
                                </div>
                                
                                <div class="result-section">
                                    <h4>改善後の記事</h4>
                                    <div class="improved-content">
                                        <div class="meta-item">
                                            <strong>タイトル:</strong>
                                            <p><?php echo htmlspecialchars($result['improved']['title']); ?></p>
                                        </div>
                                        <div class="meta-item">
                                            <strong>メタディスクリプション:</strong>
                                            <p><?php echo htmlspecialchars($result['improved']['description']); ?></p>
                                        </div>
                                        <div class="content-preview">
                                            <strong>本文:</strong>
                                            <?php echo $result['improved']['content']; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="result-actions">
                                <a href="detail.php?url=<?php echo urlencode($url); ?>" class="btn">詳細ページへ</a>
                            </div>
                        <?php else: ?>
                            <div class="result-status error">
                                <i class="fas fa-times-circle"></i> 処理失敗
                            </div>
                            <div class="error-message">
                                <?php echo htmlspecialchars($result['error'] ?? '不明なエラーが発生しました。'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> 記事リライトツール</p>
        </footer>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
