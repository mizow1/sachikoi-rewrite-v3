<?php
require_once 'config.php';
require_once 'functions.php';

// エラー表示を有効化
ini_set('display_errors', 1);
error_reporting(E_ALL);

// スプレッドシートからデータを取得
$sheetData = getSheetData();

// デバッグ情報
$debugInfo = [];
$debugInfo['環境変数'] = [
    'SPREADSHEET_ID' => defined('SPREADSHEET_ID') ? SPREADSHEET_ID : 'Not defined',
    'SHEET_NAME' => defined('SHEET_NAME') ? SHEET_NAME : 'Not defined',
    'GOOGLE_SHEETS_API_KEY' => defined('GOOGLE_SHEETS_API_KEY') ? substr(GOOGLE_SHEETS_API_KEY, 0, 10) . '...' : 'Not defined'
];
$debugInfo['データ取得結果'] = [
    'シートデータ取得' => !empty($sheetData) ? '成功 (' . count($sheetData) . '行)' : '失敗'
];

// キーワードとソート条件を取得
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'impressions_desc';

// フィルタリングされたURLリストを取得
$filteredUrls = getFilteredUrls($sheetData, $keyword, $sortBy);
$debugInfo['データ取得結果']['フィルタリング後'] = !empty($filteredUrls) ? '成功 (' . count($filteredUrls) . '行)' : '失敗';

// リライト回数を計算
$rewriteCounts = calculateRewriteCounts($sheetData);
$debugInfo['データ取得結果']['リライト回数計算'] = !empty($rewriteCounts) ? '成功 (' . count($rewriteCounts) . '件)' : '失敗';

// ページタイトル
$pageTitle = "記事リライトツール";
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
            <p>サーチコンソールデータを元に記事を改善するツールです</p>
        </header>

        <main>
            <?php if (empty($sheetData)): ?>
            <div class="debug-info">
                <h2>デバッグ情報</h2>
                <div class="debug-section">
                    <h3>環境変数</h3>
                    <table class="debug-table">
                        <?php foreach ($debugInfo['環境変数'] as $key => $value): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($key); ?></th>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="debug-section">
                    <h3>データ取得結果</h3>
                    <table class="debug-table">
                        <?php foreach ($debugInfo['データ取得結果'] as $key => $value): ?>
                        <tr>
                            <th><?php echo htmlspecialchars($key); ?></th>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="debug-section">
                    <h3>エラーログ</h3>
                    <p>サーバーのエラーログを確認してください。通常は <code>/var/log/apache2/error.log</code> または <code>php_error.log</code> にあります。</p>
                </div>
            </div>
            <?php endif; ?>
            <form id="rewriteForm" action="process.php" method="post">
                <div class="control-panel">
                    <button type="submit" class="btn btn-primary">選択した記事をリライト</button>
                    <div class="filter-controls">
                        <div class="filter-item">
                            <label for="keyword">キーワード検索:</label>
                            <input type="text" id="keyword" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="URLで検索">
                            <button type="button" id="searchBtn" class="btn btn-small">検索</button>
                        </div>
                        <div class="filter-item">
                            <label for="sortBy">並び替え:</label>
                            <select id="sortBy" name="sortBy">
                                <option value="impressions_asc" <?php echo $sortBy === 'impressions_asc' ? 'selected' : ''; ?>>表示回数（少ない順）</option>
                                <option value="impressions_desc" <?php echo $sortBy === 'impressions_desc' ? 'selected' : ''; ?>>表示回数（多い順）</option>
                                <option value="clicks_asc" <?php echo $sortBy === 'clicks_asc' ? 'selected' : ''; ?>>クリック数（少ない順）</option>
                                <option value="clicks_desc" <?php echo $sortBy === 'clicks_desc' ? 'selected' : ''; ?>>クリック数（多い順）</option>
                                <option value="position_asc" <?php echo $sortBy === 'position_asc' ? 'selected' : ''; ?>>平均掲載順位（上位順）</option>
                                <option value="position_desc" <?php echo $sortBy === 'position_desc' ? 'selected' : ''; ?>>平均掲載順位（下位順）</option>
                                <option value="rewrite_count_asc" <?php echo $sortBy === 'rewrite_count_asc' ? 'selected' : ''; ?>>リライト回数（少ない順）</option>
                                <option value="rewrite_count_desc" <?php echo $sortBy === 'rewrite_count_desc' ? 'selected' : ''; ?>>リライト回数（多い順）</option>
                                <option value="url_asc" <?php echo $sortBy === 'url_asc' ? 'selected' : ''; ?>>URL（昇順）</option>
                                <option value="url_desc" <?php echo $sortBy === 'url_desc' ? 'selected' : ''; ?>>URL（降順）</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="url-list">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>URL</th>
                                <th>表示回数</th>
                                <th>クリック数</th>
                                <th>CTR</th>
                                <th>平均掲載順位</th>
                                <th>リライト回数</th>
                                <th>詳細</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredUrls as $index => $row): ?>
                                <?php 
                                    $url = $row[0]; 
                                    $clicks = isset($row[1]) ? $row[1] : 0;
                                    $impressions = isset($row[2]) ? $row[2] : 0;
                                    $ctr = isset($row[3]) ? $row[3] : '0%';
                                    $position = isset($row[4]) ? $row[4] : 0;
                                    $rewriteCount = isset($rewriteCounts[$url]) ? $rewriteCounts[$url] : 0;
                                    $encodedUrl = urlencode($url);
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_urls[]" value="<?php echo htmlspecialchars($url); ?>"></td>
                                    <td class="url-cell"><?php echo htmlspecialchars($url); ?></td>
                                    <td><?php echo $impressions; ?></td>
                                    <td><?php echo $clicks; ?></td>
                                    <td><?php echo $ctr; ?></td>
                                    <td><?php echo $position; ?></td>
                                    <td><?php echo $rewriteCount; ?></td>
                                    <td><a href="detail.php?url=<?php echo $encodedUrl; ?>" class="btn btn-small">詳細</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </main>

        <footer>
            <p>&copy; <?php echo date('Y'); ?> 記事リライトツール</p>
        </footer>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
