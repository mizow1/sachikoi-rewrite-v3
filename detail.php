<?php
require_once 'config.php';
require_once 'functions.php';

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

        // 6列セットでデータを処理
        while (isset($row[$columnIndex + 5])) { // 6列セットで確認
            if (!empty($row[$columnIndex])) {
                // リライトデータを追加
                // スプレッドシート構造：
                // [$columnIndex]: 元記事内容
                // [$columnIndex + 1]: 問題点
                // [$columnIndex + 2]: 日時
                // [$columnIndex + 3]: 改善後のタイトル
                // [$columnIndex + 4]: 改善後のメタディスクリプション
                // [$columnIndex + 5]: 改善後の本文
                $rewriteHistory[] = [
                    'original' => $row[$columnIndex],
                    'issues' => $row[$columnIndex + 1],
                    'datetime' => $row[$columnIndex + 2],
                    'improved_title' => $row[$columnIndex + 3],
                    'improved_description' => $row[$columnIndex + 4],
                    'improved_content' => $row[$columnIndex + 5]
                ];
            }
            $columnIndex += 6; // 次の6列セットへ
        }

        break;
    }
}

// 最新のリライトデータ
$latestRewrite = !empty($rewriteHistory) ? $rewriteHistory[count($rewriteHistory) - 1] : null;

// 最新の改善された記事データを$rewriteHistoryから取得
$latestImprovedData = null;
if ($latestRewrite) {
    $latestImprovedData = [
        'title' => !empty($latestRewrite['improved_title']) ? $latestRewrite['improved_title'] : 'タイトルが取得できませんでした',
        'description' => !empty($latestRewrite['improved_description']) ? $latestRewrite['improved_description'] : 'メタディスクリプションが取得できませんでした',
        'content' => !empty($latestRewrite['improved_content']) ? $latestRewrite['improved_content'] : '本文が取得できませんでした'
    ];
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
                <form action="process.php" method="post" style="display: flex; align-items: center; gap: 15px;">
                    <input type="hidden" name="selected_urls[]" value="<?php echo htmlspecialchars($url); ?>">

                    <div class="ai-selection">
                        <label for="ai_model">使用するAI:</label>
                        <select name="ai_model" id="ai_model">
                            <?php
                            // .envから読み込んだ利用可能なモデルのみ表示
                            $availableModels = defined('AVAILABLE_MODELS') ? AVAILABLE_MODELS : [];

                            if (empty($availableModels)) {
                                echo '<option value="">モデルが設定されていません (.envを確認)</option>';
                            } else {
                                $firstModel = array_key_first($availableModels);
                                foreach ($availableModels as $modelId => $modelInfo):
                                    $selected = ($modelId === $firstModel) ? 'selected' : '';
                                    $displayName = isset($modelInfo['name']) ? $modelInfo['name'] : $modelId;
                                    $description = isset($modelInfo['description']) ? $modelInfo['description'] : '';
                                    $provider = isset($modelInfo['provider']) ? $modelInfo['provider'] : 'unknown';
                                ?>
                                    <option value="<?php echo htmlspecialchars($modelId); ?>"
                                            data-provider="<?php echo htmlspecialchars($provider); ?>"
                                            data-description="<?php echo htmlspecialchars($description); ?>"
                                            <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($displayName); ?> (<?php echo htmlspecialchars($provider); ?>)
                                    </option>
                                <?php endforeach;
                            }
                            ?>
                        </select>
                    </div>

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
                        <div class="article-issues">
                            <h3>問題点</h3>
                            <div class="issues-content">
                                <?php echo nl2br(htmlspecialchars($latestRewrite['issues'])); ?>
                            </div>
                        </div>

                    </div>

                    <?php if ($latestRewrite): ?>

                        <div class="improved-article">
                            <h3>最新の改善記事</h3>

                            <!-- デバッグ情報表示 -->
                            <div class="debug-data" style="background-color: #f5f5f5; padding: 10px; margin-bottom: 20px; border: 1px solid #ddd; font-size: 12px;">
                                <strong>データ取得状況:</strong>
                                <ul>
                                    <li>タイトル: <?php echo ($latestImprovedData && !empty($latestImprovedData['title']) && $latestImprovedData['title'] !== 'タイトルが取得できませんでした') ? '取得成功' : '取得失敗'; ?></li>
                                    <li>メタディスクリプション: <?php echo ($latestImprovedData && !empty($latestImprovedData['description']) && $latestImprovedData['description'] !== 'メタディスクリプションが取得できませんでした') ? '取得成功' : '取得失敗'; ?></li>
                                    <li>本文: <?php echo ($latestImprovedData && !empty($latestImprovedData['content']) && $latestImprovedData['content'] !== '本文が取得できませんでした') ? '取得成功' : '取得失敗'; ?></li>
                                </ul>

                                <strong>リライト履歴:</strong>
                                <ul>
                                    <li>履歴数: <?php echo count($rewriteHistory); ?></li>
                                    <li>最新リライト日時: <?php echo $latestRewrite ? htmlspecialchars($latestRewrite['datetime']) : 'N/A'; ?></li>
                                </ul>
                            </div>

                            <!-- タイトルとメタディスクリプション -->
                            <div class="article-meta">
                                <div class="meta-item">
                                    <strong>タイトル:</strong>
                                    <p><?php echo htmlspecialchars($latestImprovedData['title']); ?></p>
                                </div>
                                <div class="meta-item">
                                    <strong>メタディスクリプション:</strong>
                                    <p><?php echo htmlspecialchars($latestImprovedData['description']); ?></p>
                                </div>
                            </div>

                            <!-- 本文 -->
                            <div class="article-content">
                                <strong>本文:</strong>
                                <div class="content-preview">
                                    <?php echo convertMarkdownToHtml($latestImprovedData['content']); ?>
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
                                <?php if ($index > 0): // 最新のリライトは既に表示しているのでスキップ
                                ?>
                                    <div class="history-item">
                                        <div class="history-datetime">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars($rewrite['datetime']); ?>
                                        </div>
                                        <div class="history-content">
                                            <div class="article-meta">
                                                <div class="meta-item">
                                                    <strong>タイトル:</strong>
                                                    <p><?php echo htmlspecialchars($rewrite['improved_title']); ?></p>
                                                </div>
                                                <div class="meta-item">
                                                    <strong>メタディスクリプション:</strong>
                                                    <p><?php echo htmlspecialchars($rewrite['improved_description']); ?></p>
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
                                                    <?php echo convertMarkdownToHtml($rewrite['improved_content']); ?>
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