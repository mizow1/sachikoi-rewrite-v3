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
                // 必要なデータは列の位置に応じて取得
                $rewriteHistory[] = [
                    'original' => $row[$columnIndex],
                    'issues' => $row[$columnIndex + 1],
                    'datetime' => $row[$columnIndex + 2],
                    'improved' => $row[$columnIndex + 3]
                    // 残りの2列は現在使用していないが、必要に応じて追加可能
                    // 'extra1' => $row[$columnIndex + 4],
                    // 'extra2' => $row[$columnIndex + 5]
                ];
            }
            $columnIndex += 6; // 次の6列セットへ
        }

        break;
    }
}

// 最新のリライトデータ
$latestRewrite = !empty($rewriteHistory) ? $rewriteHistory[count($rewriteHistory) - 1] : null;

// 最新の改善された記事データを解析
$latestImprovedData = null;

// URLを手がかりにスプレッドシートから直接データを取得
$originalTitle = '';
$originalDescription = '';
$improvedTitle = '';
$improvedDescription = '';
$improvedContent = '';

// デバッグ用の配列
$debugInfo = [];
$debugInfo['row_data'] = [];

// スプレッドシートのデータからURLに一致する行を探す
foreach ($sheetData as $row) {
    if ($row[0] === $url) {
        // デバッグ用に行データを保存
        $debugInfo['row_found'] = true;
        $debugInfo['row_length'] = count($row);
        
        // 元の記事のタイトルとディスクリプションを取得
        $originalTitle = isset($row[1]) ? $row[1] : '';
        $originalDescription = isset($row[2]) ? $row[2] : '';
        
        // 最新の改善記事のデータを取得
        // スプレッドシートの最後の列からデータを取得
        $lastColumnIndex = count($row) - 1;
        $debugInfo['last_column_index'] = $lastColumnIndex;
        
        // 最後から逆に探して、最新の改善記事のデータを取得
        for ($i = $lastColumnIndex; $i >= 3; $i--) {
            if (!empty($row[$i])) {
                // デバッグ用に列データを保存
                $debugInfo['row_data'][$i] = substr($row[$i], 0, 100) . (strlen($row[$i]) > 100 ? '...' : '');
                
                // データがプレーンテキストの場合の処理
                // 最初の行をタイトルとして使用
                $lines = explode("\n", $row[$i]);
                if (empty($improvedTitle) && !empty($lines[0])) {
                    $improvedTitle = trim($lines[0]);
                    $debugInfo['title_from_line'] = true;
                }
                
                // 改善記事のタイトル、ディスクリプション、本文を取得
                if (empty($improvedTitle) && preg_match('/タイトル[\s]*[:：][\s]*([^\n\r]+)/u', $row[$i], $matches)) {
                    $improvedTitle = trim($matches[1]);
                    $debugInfo['title_from_regex'] = true;
                }
                if (empty($improvedDescription) && preg_match('/メタディスクリプション[\s]*[:：][\s]*([^\n\r]+)/u', $row[$i], $matches)) {
                    $improvedDescription = trim($matches[1]);
                    $debugInfo['description_from_regex'] = true;
                }
                if (empty($improvedContent) && preg_match('/本文[\s]*[:：][\s]*([\s\S]+)$/u', $row[$i], $matches)) {
                    $improvedContent = trim($matches[1]);
                    $debugInfo['content_from_regex'] = true;
                }
                
                // ディスクリプションが取得できない場合、元のディスクリプションを使用
                if (empty($improvedDescription) && !empty($originalDescription)) {
                    $improvedDescription = $originalDescription;
                    $debugInfo['description_from_original'] = true;
                }
                
                // 本文が取得できない場合、生データを使用
                if (empty($improvedContent) && !empty($row[$i])) {
                    // タイトル行を除いた内容を本文として使用
                    if (count($lines) > 1) {
                        array_shift($lines); // 最初の行（タイトル）を除去
                        $improvedContent = trim(implode("\n", $lines));
                        $debugInfo['content_from_lines'] = true;
                    } else {
                        $improvedContent = $row[$i];
                        $debugInfo['content_from_raw'] = true;
                    }
                }
                
                // 全てのデータが取得できたらループを終了
                if (!empty($improvedTitle) && !empty($improvedDescription) && !empty($improvedContent)) {
                    $debugInfo['all_data_found'] = true;
                    break;
                }
            }
        }
        
        break; // URLが一致した行が見つかったらループを終了
    }
}

// デバッグ情報を記録
error_log("Debug info: " . json_encode($debugInfo));

// 取得したデータをlatestImprovedDataに設定
$latestImprovedData = [
    'title' => !empty($improvedTitle) ? $improvedTitle : 'タイトルが取得できませんでした',
    'description' => !empty($improvedDescription) ? $improvedDescription : 'メタディスクリプションが取得できませんでした',
    'content' => !empty($improvedContent) ? $improvedContent : '本文が取得できませんでした'
];

// デバッグ情報を記録
if ($latestRewrite) {
    error_log("Latest improved data raw: " . substr($latestRewrite['improved'], 0, 500));
}
error_log("Latest improved data from sheet: " . json_encode($latestImprovedData));

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
                                    <li>タイトル: <?php echo !empty($improvedTitle) ? '取得成功' : '取得失敗'; ?></li>
                                    <li>メタディスクリプション: <?php echo !empty($improvedDescription) ? '取得成功' : '取得失敗'; ?></li>
                                    <li>本文: <?php echo !empty($improvedContent) ? '取得成功' : '取得失敗'; ?></li>
                                </ul>
                                
                                <strong>デバッグ情報詳細:</strong>
                                <ul>
                                    <li>行見つかった: <?php echo isset($debugInfo['row_found']) ? 'Yes' : 'No'; ?></li>
                                    <li>行の長さ: <?php echo isset($debugInfo['row_length']) ? $debugInfo['row_length'] : 'N/A'; ?></li>
                                    <li>最後の列インデックス: <?php echo isset($debugInfo['last_column_index']) ? $debugInfo['last_column_index'] : 'N/A'; ?></li>
                                    <li>タイトル取得方法: 
                                        <?php 
                                        if (isset($debugInfo['title_from_line'])) echo '行から取得'; 
                                        elseif (isset($debugInfo['title_from_regex'])) echo '正規表現から取得'; 
                                        else echo '取得失敗';
                                        ?>
                                    </li>
                                    <li>ディスクリプション取得方法: 
                                        <?php 
                                        if (isset($debugInfo['description_from_regex'])) echo '正規表現から取得'; 
                                        elseif (isset($debugInfo['description_from_original'])) echo '元のディスクリプションから取得'; 
                                        else echo '取得失敗';
                                        ?>
                                    </li>
                                    <li>本文取得方法: 
                                        <?php 
                                        if (isset($debugInfo['content_from_regex'])) echo '正規表現から取得'; 
                                        elseif (isset($debugInfo['content_from_lines'])) echo '行から取得'; 
                                        elseif (isset($debugInfo['content_from_raw'])) echo '生データから取得'; 
                                        else echo '取得失敗';
                                        ?>
                                    </li>
                                    <li>全データ取得: <?php echo isset($debugInfo['all_data_found']) ? 'Yes' : 'No'; ?></li>
                                </ul>
                                
                                <strong>スプレッドシートデータプレビュー:</strong>
                                <ul>
                                <?php foreach ($debugInfo['row_data'] as $index => $data): ?>
                                    <li>列<?php echo $index; ?>: <?php echo htmlspecialchars($data); ?></li>
                                <?php endforeach; ?>
                                </ul>
                                
                                <?php if ($latestRewrite): ?>
                                <strong>生データプレビュー（最初の500文字）:</strong>
                                <pre style="white-space: pre-wrap; word-break: break-all;"><?php echo htmlspecialchars(substr($latestRewrite['improved'], 0, 500)); ?></pre>
                                <?php endif; ?>
                            </div>

                            <!-- タイトルとメタディスクリプション -->
                            <div class="article-meta">
                                <div class="meta-item">
                                    <strong>タイトル:</strong>
                                    <p>
                                    <?php 
                                        // 生のデータを確認
                                        $rawImproved = $latestRewrite['improved'];
                                        
                                        // データがシンプルなテキストの場合、最初の行をタイトルとして使用
                                        $lines = explode("\n", $rawImproved);
                                        if (!empty($lines[0])) {
                                            echo htmlspecialchars(trim($lines[0]));
                                        } else {
                                            echo 'タイトルが見つかりませんでした';
                                        }
                                    ?>
                                    </p>
                                </div>
                                <div class="meta-item">
                                    <strong>メタディスクリプション:</strong>
                                    <p>
                                    <?php 
                                        // データからメタディスクリプションを取得
                                        if (count($lines) > 1) {
                                            // 2行目があれば、それをメタディスクリプションとして使用
                                            echo htmlspecialchars(trim($lines[1]));
                                        } elseif (isset($latestImprovedData['description']) && !empty($latestImprovedData['description'])) {
                                            // parseImprovedArticleData関数からのデータがあれば使用
                                            echo htmlspecialchars($latestImprovedData['description']);
                                        } else {
                                            // データがない場合はその旨を表示
                                            echo 'メタディスクリプションが見つかりませんでした';
                                        }
                                    ?>
                                    </p>
                                </div>
                            </div>

                            <!-- 本文 -->
                            <div class="article-content">
                                <strong>本文:</strong>
                                <div class="content-preview">
                                <?php 
                                    // データがシンプルなテキストの場合、生のデータをそのまま使用
                                    if (!empty($rawImproved)) {
                                        // タイトル行を除いた本文を表示
                                        $contentLines = $lines;
                                        if (count($contentLines) > 0) {
                                            // 最初の行（タイトル）を除去
                                            array_shift($contentLines);
                                        }
                                        
                                        // 本文が空でない場合のみ表示
                                        if (!empty($contentLines)) {
                                            echo nl2br(htmlspecialchars(implode("\n", $contentLines)));
                                        } else {
                                            // 本文が空の場合は生データをそのまま表示
                                            echo nl2br(htmlspecialchars($rawImproved));
                                        }
                                    } else {
                                        echo '本文が見つかりませんでした';
                                    }
                                ?>
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