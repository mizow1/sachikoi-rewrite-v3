<?php
/**
 * データ処理関連の機能
 */

/**
 * リライト回数を計算する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @return array URLごとのリライト回数
 */
function calculateRewriteCounts($sheetData) {
    $rewriteCounts = [];
    
    foreach ($sheetData as $row) {
        $url = $row[0];
        if (empty($url)) continue;
        
        $count = 0;
        $columnIndex = 5; // F列から開始（0ベースなので5）
        
        // 4列ごとにリライトデータがあるかチェック
        while (isset($row[$columnIndex + 3])) { // 元の記事、問題点、日時、改善後の記事の4列セット
            if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3])) {
                $count++;
            }
            $columnIndex += 4;
        }
        
        $rewriteCounts[$url] = $count;
    }
    
    return $rewriteCounts;
}

/**
 * フィルタリングとソートされたURLリストを取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $keyword 検索キーワード（オプション）
 * @param string $sortBy ソート条件（オプション）
 * @return array フィルタリングとソートされたURLリスト
 */
function getFilteredUrls($sheetData, $keyword = '', $sortBy = 'impressions_desc') {
    // キーワードでフィルタリング
    $filteredData = [];
    foreach ($sheetData as $row) {
        $url = $row[0];
        if (empty($url)) continue;
        
        // キーワードが指定されている場合は、URLに含まれるかチェック
        if (!empty($keyword) && stripos($url, $keyword) === false) {
            continue;
        }
        
        $filteredData[] = $row;
    }
    
    // リライト回数を計算
    $rewriteCounts = calculateRewriteCounts($sheetData);
    
    // ソート
    usort($filteredData, function($a, $b) use ($sortBy, $rewriteCounts) {
        $urlA = $a[0];
        $urlB = $b[0];
        $clicksA = isset($a[1]) ? (int)$a[1] : 0;
        $clicksB = isset($b[1]) ? (int)$b[1] : 0;
        $impressionsA = isset($a[2]) ? (int)$a[2] : 0;
        $impressionsB = isset($b[2]) ? (int)$b[2] : 0;
        $positionA = isset($a[4]) ? (float)$a[4] : 0;
        $positionB = isset($b[4]) ? (float)$b[4] : 0;
        $rewriteCountA = isset($rewriteCounts[$urlA]) ? $rewriteCounts[$urlA] : 0;
        $rewriteCountB = isset($rewriteCounts[$urlB]) ? $rewriteCounts[$urlB] : 0;
        
        switch ($sortBy) {
            case 'url_asc':
                return strcmp($urlA, $urlB);
            case 'url_desc':
                return strcmp($urlB, $urlA);
            case 'clicks_asc':
                return $clicksA - $clicksB;
            case 'clicks_desc':
                return $clicksB - $clicksA;
            case 'impressions_asc':
                return $impressionsA - $impressionsB;
            case 'impressions_desc':
                return $impressionsB - $impressionsA;
            case 'position_asc':
                return $positionA - $positionB;
            case 'position_desc':
                return $positionB - $positionA;
            case 'rewrite_count_asc':
                return $rewriteCountA - $rewriteCountB;
            case 'rewrite_count_desc':
                return $rewriteCountB - $rewriteCountA;
            default:
                return $impressionsB - $impressionsA; // デフォルトは表示回数の降順
        }
    });
    
    return $filteredData;
}

/**
 * 元の記事データを取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $url 対象のURL
 * @return array|null 元の記事データ（タイトル、ディスクリプション、本文）またはnull
 */
function getOriginalArticle($sheetData, $url) {
    foreach ($sheetData as $row) {
        if ($row[0] === $url) {
            // A列：URL
            // B列：クリック数
            // C列：表示回数
            // D列：CTR
            // E列：平均掲載順位
            // F列以降：リライトデータ
            
            // タイトル、ディスクリプション、本文を抽出
            $content = '';
            $title = '';
            $description = '';
            
            // F列（インデックス5）に元の記事内容がある場合
            if (isset($row[5]) && !empty($row[5])) {
                $content = $row[5];
                
                // タイトルとディスクリプションを抽出
                if (preg_match('/<title>(.*?)<\/title>/is', $content, $titleMatches)) {
                    $title = trim($titleMatches[1]);
                }
                
                if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/is', $content, $descMatches)) {
                    $description = trim($descMatches[1]);
                }
                
                // <body>タグの中身を抽出
                if (preg_match('/<body>(.*?)<\/body>/is', $content, $bodyMatches)) {
                    $content = trim($bodyMatches[1]);
                }
                
                return [
                    'title' => $title,
                    'description' => $description,
                    'content' => $content
                ];
            }
            
            // 元の記事内容が見つからない場合
            return null;
        }
    }
    
    return null;
}

/**
 * 最新のリライト記事データを取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $url 対象のURL
 * @return array|null 最新のリライト記事データ（タイトル、ディスクリプション、本文、問題点、日時）またはnull
 */
function getLatestRewriteData($sheetData, $url) {
    foreach ($sheetData as $row) {
        if ($row[0] === $url) {
            $latestRewriteData = null;
            $latestColumnIndex = null;
            
            // F列以降を4列ごとに確認
            $columnIndex = 5; // F列から開始（0ベースなので5）
            
            while (isset($row[$columnIndex + 3])) { // 元の記事、問題点、日時、改善後の記事の4列セット
                if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                    !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3])) {
                    $latestColumnIndex = $columnIndex;
                }
                $columnIndex += 4;
            }
            
            if ($latestColumnIndex !== null) {
                $originalContent = $row[$latestColumnIndex];
                $issues = $row[$latestColumnIndex + 1];
                $datetime = $row[$latestColumnIndex + 2];
                $improvedContent = $row[$latestColumnIndex + 3];
                
                // 改善された記事データを解析
                $improvedData = parseImprovedArticleData($improvedContent);
                
                return [
                    'original' => $originalContent,
                    'issues' => $issues,
                    'datetime' => $datetime,
                    'improved' => $improvedData,
                    'raw_improved' => $improvedContent
                ];
            }
            
            break;
        }
    }
    
    return null;
}
