<?php
/**
 * HTML処理関連の機能
 */

/**
 * マークダウン形式のテキストをHTMLに変換する関数
 * 
 * @param string $markdown マークダウン形式のテキスト
 * @return string HTML形式のテキスト
 */
function convertMarkdownToHtml($markdown) {
    // 改行を処理
    $html = nl2br($markdown);
    
    // 見出しを処理
    $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^##### (.*?)$/m', '<h5>$1</h5>', $html);
    
    // 強調を処理
    $html = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $html);
    
    // リストを処理
    $html = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/^\d+\. (.*?)$/m', '<li>$1</li>', $html);
    
    // リストアイテムをリストで囲む
    $html = preg_replace('/((?:<li>.*?<\/li>\s*)+)/s', '<ul>$1</ul>', $html);
    
    // リンクを処理
    $html = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', $html);
    
    // コードブロックを処理
    $html = preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $html);
    $html = preg_replace('/`(.*?)`/s', '<code>$1</code>', $html);
    
    // 引用を処理
    $html = preg_replace('/^> (.*?)$/m', '<blockquote>$1</blockquote>', $html);
    
    // 水平線を処理
    $html = preg_replace('/^---$/m', '<hr>', $html);
    
    // 段落を処理
    $html = preg_replace('/(<br \/>){2,}/', '</p><p>', $html);
    $html = '<p>' . $html . '</p>';
    
    // 不要なタグの入れ子を修正
    $html = str_replace("<p><h1>", "<h1>", $html);
    $html = str_replace("</h1></p>", "</h1>", $html);
    $html = str_replace("<p><h2>", "<h2>", $html);
    $html = str_replace("</h2></p>", "</h2>", $html);
    $html = str_replace("<p><h3>", "<h3>", $html);
    $html = str_replace("</h3></p>", "</h3>", $html);
    $html = str_replace("<p><h4>", "<h4>", $html);
    $html = str_replace("</h4></p>", "</h4>", $html);
    $html = str_replace("<p><h5>", "<h5>", $html);
    $html = str_replace("</h5></p>", "</h5>", $html);
    
    return $html;
}

/**
 * 改善された記事データをJSONから解析する関数
 * 
 * @param string $jsonData JSON形式の記事データ
 * @return array 解析された記事データ
 */
function parseImprovedArticleData($jsonData) {
    // デバッグ情報を出力
    error_log("Parsing improved article data, first 100 chars: " . substr($jsonData, 0, 100));
    
    $data = json_decode($jsonData, true);
    
    if (is_array($data) && isset($data['title']) && isset($data['description']) && isset($data['content'])) {
        error_log("JSON data successfully parsed with title, description, and content");
        // 本文がマークダウン形式の場合はHTMLに変換
        if (isset($data['content'])) {
            $data['content'] = convertMarkdownToHtml($data['content']);
            // ```html ```タグを削除する
            $data['content'] = preg_replace('/^```html\s*|\s*```$/s', '', $data['content']);
        }
        return $data;
    }
    
    // JSONデコードに失敗した場合のデバッグ情報
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
    }
    
    // JSONでない場合は文字列から解析を試みる
    $title = '';
    $description = '';
    $content = $jsonData;
    
    if (preg_match('/タイトル:\s*(.+?)(?=\nメタディスクリプション:|$)/s', $jsonData, $titleMatches)) {
        $title = trim($titleMatches[1]);
        // クリック率などの余分な情報を除去
        // 括弧内の情報を削除
        $title = preg_replace('/\s*\([^)]*\)\s*$/', '', $title);
        // 「」や『』の外側にある余分な情報を削除
        $title = preg_replace('/^.*?[「『]([^」』]*)[」』].*$/', '\1', $title);
        // 「」や『』がない場合はそのまま使用
        if (!preg_match('/[「『」』]/', $title)) {
            $title = preg_replace('/\s*[:|\-]\s*.*$/', '', $title);
        }
    }
    
    if (preg_match('/メタディスクリプション:\s*(.+?)(?=\n本文:|$)/s', $jsonData, $descMatches)) {
        $description = trim($descMatches[1]);
        // 括弧内の余分な情報を削除
        $description = preg_replace('/\s*\([^)]*\)\s*$/', '', $description);
        // コロンやダッシュ以降の余分な情報を削除
        $description = preg_replace('/\s*[:|\-]\s*.*$/', '', $description);
    }
    
    if (preg_match('/本文:\s*(.+?)$/s', $jsonData, $bodyMatches)) {
        $content = trim($bodyMatches[1]);
        
        // HTMLタグや不要な要素を除去
        // <title>、<meta>タグを除去
        $content = preg_replace('/<title>.*?<\/title>/is', '', $content);
        $content = preg_replace('/<meta[^>]*>/is', '', $content);
        
        // <body>タグの中身だけを抽出
        if (preg_match('/<body>(.*?)<\/body>/is', $content, $bodyContentMatches)) {
            $content = trim($bodyContentMatches[1]);
        } else {
            // <body>タグがない場合は、タイトルとメタディスクリプションの行を除去
            $content = preg_replace('/^タイトル:.+?\n/m', '', $content);
            $content = preg_replace('/^メタディスクリプション:.+?\n/m', '', $content);
        }
        
        // マークダウンからHTMLに変換
        $content = convertMarkdownToHtml($content);
        
        // ```html ```タグを削除する
        $content = preg_replace('/^```html\s*|\s*```$/s', '', $content);
    }
    
    return [
        'title' => $title,
        'description' => $description,
        'content' => $content
    ];
}
