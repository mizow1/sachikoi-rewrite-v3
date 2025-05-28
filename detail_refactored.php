<?php
require_once 'config.php';
require_once 'functions_refactored.php';

// URLパラメータを取得
$url = isset($_GET['url']) ? $_GET['url'] : '';

if (empty($url)) {
    header('Location: index.php');
    exit;
}

// スプレッドシートからデータを取得
$sheetData = getSheetData();

// 元の記事データを取得
$originalArticle = getOriginalArticle($sheetData, $url);

// リライト履歴を取得
$rewriteHistory = [];
$rowIndex = null;

foreach ($sheetData as $index => $row) {
    if ($row[0] === $url) {
        $rowIndex = $index;
        
        // F列以降のデータを確認
        $columnIndex = 5; // F列から開始（0ベースなので5）
        
        // 元の記事、問題点、書き直し日時、書き直し後の記事のパターンを検出
        while (isset($row[$columnIndex + 3])) { // 4列セットで確認
            if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3])) {
                
                // リライトデータを追加
                $rewriteHistory[] = [
                    'original' => $row[$columnIndex],
                    'issues' => $row[$columnIndex + 1],
                    'datetime' => $row[$columnIndex + 2],
                    'improved' => $row[$columnIndex + 3]
                ];
            }
            $columnIndex += 4; // 次の4列セットへ
        }
        
        break;
    }
}

// 最新のリライトデータ
$latestRewrite = !empty($rewriteHistory) ? $rewriteHistory[count($rewriteHistory) - 1] : null;

// 最新の改善された記事データを解析
$latestImprovedData = null;
if ($latestRewrite) {
    // 改善された記事データを解析し、デバッグ情報を記録
    $latestImprovedData = parseImprovedArticleData($latestRewrite['improved']);
    error_log("Latest improved data: " . json_encode($latestImprovedData));
}

// ページタイトル
$pageTitle = "記事詳細: " . htmlspecialchars($url);
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
            <div class="detail-header">
                <h2><?php echo htmlspecialchars($url); ?></h2>
                <form action="process_refactored.php" method="post">
                    <input type="hidden" name="selected_urls[]" value="<?php echo htmlspecialchars($url); ?>">
                    <button type="submit" class="btn btn-primary">この記事をリライト</button>
                </form>
            </div>

            <?php if ($originalArticle): ?>
                <div class="article-comparison">
                    <div class="original-article">
                        <h3>元の記事</h3>
                        <div class="article-meta">
                            <div class="meta-item">
                                <strong>タイトル:</strong>
                                <p><?php echo htmlspecialchars($originalArticle['title']); ?></p>
                            </div>
                            <div class="meta-item">
                                <strong>メタディスクリプション:</strong>
                                <p><?php echo htmlspecialchars($originalArticle['description']); ?></p>
                            </div>
                        </div>
                        <div class="article-content">
                            <strong>本文:</strong>
                            <div class="content-preview">
                                <?php echo $originalArticle['content']; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($latestRewrite): ?>
                        <div class="improved-article">
                            <h3>最新の改善記事</h3>
                            
                            <!-- 問題点を最初に表示 -->
                            <div class="article-issues">
                                <strong>問題点:</strong>
                                <div class="issues-content">
                                    <?php echo nl2br(htmlspecialchars($latestRewrite['issues'])); ?>
                                </div>
                            </div>
                            
                            <!-- 改善後の記事メタデータ -->
                            <div class="article-meta">
                                <?php if (isset($latestImprovedData['title'])): ?>
                                <div class="meta-item">
                                    <strong>タイトル:</strong>
                                    <p><?php echo htmlspecialchars($latestImprovedData['title']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (isset($latestImprovedData['description'])): ?>
                                <div class="meta-item">
                                    <strong>メタディスクリプション:</strong>
                                    <p><?php echo htmlspecialchars($latestImprovedData['description']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- 本文 -->
                            <div class="article-content">
                                <strong>本文:</strong>
                                <div class="content-preview">
                                    <?php echo isset($latestImprovedData['content']) ? $latestImprovedData['content'] : ''; ?>
                                </div>
                            </div>
                            <div class="article-datetime">
                                <strong>リライト日時:</strong>
                                <p><?php echo htmlspecialchars($latestRewrite['datetime']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (count($rewriteHistory) > 1): ?>
                    <div class="rewrite-history">
                        <h3>リライト履歴</h3>
                        <div class="history-timeline">
                            <?php foreach (array_reverse($rewriteHistory) as $index => $rewrite): ?>
                                <?php if ($index > 0): // 最新のリライトは既に表示しているのでスキップ ?>
                                    <?php $improvedData = parseImprovedArticleData($rewrite['improved']); ?>
                                    <div class="history-item">
                                        <div class="history-datetime">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($rewrite['datetime']); ?>
                                        </div>
                                        <div class="history-content">
                                            <div class="article-meta">
                                                <div class="meta-item">
                                                    <strong>タイトル:</strong>
                                                    <p><?php echo htmlspecialchars($improvedData['title']); ?></p>
                                                </div>
                                                <div class="meta-item">
                                                    <strong>メタディスクリプション:</strong>
                                                    <p><?php echo htmlspecialchars($improvedData['description']); ?></p>
                                                </div>
                                            </div>
                                            <div class="article-issues">
                                                <strong>問題点:</strong>
                                                <div class="issues-content">
                                                    <?php echo nl2br(htmlspecialchars($rewrite['issues'])); ?>
                                                </div>
                                            </div>
                                            <div class="article-content">
                                                <strong>本文:</strong>
                                                <div class="content-preview">
                                                    <?php echo $improvedData['content']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="error-message">
                    <p>記事データが見つかりませんでした。</p>
                </div>
            <?php endif; ?>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> 記事リライトツール</p>
        </footer>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
