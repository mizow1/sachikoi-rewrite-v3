<?php
// 以下のスクリプトは、functions.phpファイル内の重複した関数定義を削除するためのものです
$file = 'functions.php';
$content = file_get_contents($file);

// 最初の関数定義を保持し、2つ目の関数定義を削除
$pattern = '/\/\*\*\s*\n\s*\*\s*フィルタリングとソートされたURLリストを取得する関数\s*\n.*?function\s+getFilteredUrls\s*\(\s*\$sheetData\s*,\s*\$keyword\s*=\s*\'.*?\'\s*,\s*\$sortBy\s*=\s*\'.*?\'\s*\)\s*\{.*?return\s+\$filteredUrls;\s*\n\s*\}/s';

// 関数の出現回数を確認
preg_match_all($pattern, $content, $matches);
if (count($matches[0]) >= 2) {
    // 2つ目の関数定義を削除
    $content = preg_replace($pattern, '', $content, 1);
    // もう一度関数の出現回数を確認
    preg_match_all($pattern, $content, $matches);
    if (count($matches[0]) >= 1) {
        // 残った関数定義を保持
        file_put_contents($file, $content);
        echo "重複した関数定義を削除しました。";
    } else {
        echo "エラー: すべての関数定義が削除されました。";
    }
} else {
    echo "重複した関数定義が見つかりませんでした。";
}
?>
