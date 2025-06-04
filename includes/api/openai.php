<?php
/**
 * OpenAI API関連の機能
 */

/**
 * 記事の問題点を分析する関数（OpenAI API使用）
 * 
 * @param string $title 記事タイトル
 * @param string $description メタディスクリプション
 * @param string $content 記事本文
 * @return string 問題点の分析結果
 */
function analyzeArticleIssues($title, $description, $content) {
    $apiKey = OPENAI_API_KEY;
    $model = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo';
    
    // HTMLタグを除去してプレーンテキストに変換
    $plainContent = strip_tags($content);
    
    // 文字数制限（トークン数を減らすため）
    $maxContentLength = 4000;
    if (mb_strlen($plainContent) > $maxContentLength) {
        $plainContent = mb_substr($plainContent, 0, $maxContentLength) . '...（以下省略）';
    }
    
    // システムプロンプト
    $systemPrompt = "あなたはSEOと記事改善のプロフェッショナルです。与えられた記事を分析し、以下の観点から問題点を指摘してください：
1. SEO観点（タイトル、メタディスクリプション、見出し構造、キーワード使用など）
2. 読みやすさ（文章構造、段落分け、専門用語の説明など）
3. ユーザー体験（情報の網羅性、価値提供、CTAの有無など）
4. 信頼性（情報の正確さ、データの裏付け、専門性の表現など）

分析結果は箇条書きで簡潔に記載し、改善すべき点を具体的に示してください。
目次の追加は改善案として禁止です。目次は同ページ内の他の箇所に既に存在しているため不要です。";
    
    // ユーザープロンプト
    $userPrompt = "以下の記事を分析し、問題点を指摘してください：\n\n";
    $userPrompt .= "【タイトル】\n" . $title . "\n\n";
    $userPrompt .= "【メタディスクリプション】\n" . $description . "\n\n";
    $userPrompt .= "【本文】\n" . $plainContent;
    
    // APIリクエストデータ
    $requestData = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userPrompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    // APIリクエスト
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return "APIエラーが発生しました。詳細はログを確認してください。";
    }
    
    curl_close($ch);
    
    // レスポンスをJSONからデコード
    $data = json_decode($response, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        return $data['choices'][0]['message']['content'];
    } else {
        error_log("OpenAI API error: " . json_encode($data));
        return "APIからの応答を解析できませんでした。詳細はログを確認してください。";
    }
}

/**
 * 記事を改善する関数（OpenAI API使用）
 * 
 * @param string $title 元の記事タイトル
 * @param string $description 元のメタディスクリプション
 * @param string $content 元の記事本文
 * @param string $issues 分析された問題点
 * @return array 改善された記事（タイトル、ディスクリプション、本文）
 */
function improveArticle($title, $description, $content, $issues) {
    $apiKey = OPENAI_API_KEY;
    $model = defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo';
    
    // HTMLタグを除去してプレーンテキストに変換
    $plainContent = strip_tags($content);
    
    // 文字数制限（トークン数を減らすため）
    $maxContentLength = 4000;
    if (mb_strlen($plainContent) > $maxContentLength) {
        $plainContent = mb_substr($plainContent, 0, $maxContentLength) . '...（以下省略）';
    }
    
    // システムプロンプト
    $systemPrompt = "あなたはSEOに強い日本語Webライターです。以下の条件を必ず守ってください：
- 出力は必ずJSON形式で行うこと
- title, description, contentの3つのキーを含めること
- contentはHTML形式で出力すること
- 改善点や問題点を必ず反映すること
- タイトル・メタディスクリプションにも必ず全ての分析された問題点・改善点を反映し、改善点が明確にわかるようにリライトしてください
- 句点のあとには必ず<br>を挿入すること
- 不要な説明や注釈は一切出力しないこと

出力例：
```
{
  \"title\": \"改善されたタイトル\",
  \"description\": \"改善されたメタディスクリプション\",
  \"content\": \"改善された本文（HTML形式）\"
}
```

本文はHTML形式で出力し、適切な見出し構造（h2, h3,h4など）、段落（p）、改行（br）、リスト（ul, li）、強調（strong, em）などを使用してください。
ただし、以下の点に注意してください：
1. 元の記事の主要な情報や意図を保持すること
2. SEO的に最適化されたタイトル、メタディスクリプション、見出し構造にすること
3. 読みやすく、ユーザーにとって価値のある内容にすること
4. 必要に応じて情報を追加・削除・並べ替えること
5. h1はこの本文の前に既に表示されているので作成してはいけません。
6. このサイトにコメント欄はありません。「感想をコメント欄で教えて」などコメント欄への記入を促す文章は禁止です。
7. 記事本文に該当する部分のみ出力してください。問題点、改善点など本文以外の文章は禁止です。
8. 記事内に目次は作成禁止です。目次はページ内の別の箇所に既に存在しているので不要です。

JSONのみを出力し、他の説明は禁止です。
句点のあとには常に<br>を挿入してください。
";
    
    // ユーザープロンプト
    $userPrompt = "以下の記事を改善してください：\n\n";
    $userPrompt .= "【元のタイトル】\n" . $title . "\n\n";
    $userPrompt .= "【元のメタディスクリプション】\n" . $description . "\n\n";
    $userPrompt .= "【元の本文】\n" . $plainContent . "\n\n";
    $userPrompt .= "【分析された問題点】\n" . $issues . "\n\n上記の分析された問題点を必ずすべて反映し、タイトル・メタディスクリプション・本文すべてに改善点が明確にわかるように記事をリライトしてください。";
    
    // APIリクエストデータ
    $requestData = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userPrompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000
    ];
    
    // APIリクエスト
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return [
            'title' => $title,
            'description' => $description,
            'content' => "<p>APIエラーが発生しました。詳細はログを確認してください。</p>"
        ];
    }
    
    curl_close($ch);
    
    // レスポンスをJSONからデコード
    $data = json_decode($response, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        
        // JSONを抽出
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonStr = $matches[1];
        } else {
            $jsonStr = $content;
        }
        
        // JSONをデコード
        $improvedData = json_decode($jsonStr, true);
        
        if (is_array($improvedData) && 
            isset($improvedData['title']) && 
            isset($improvedData['description']) && 
            isset($improvedData['content'])) {
            return $improvedData;
        } else {
            error_log("Failed to parse JSON from OpenAI response: " . $content);
            return [
                'title' => $title,
                'description' => $description,
                'content' => "<p>APIレスポンスの解析に失敗しました。詳細はログを確認してください。</p><pre>" . htmlspecialchars($content) . "</pre>"
            ];
        }
    } else {
        error_log("OpenAI API error: " . json_encode($data));
        return [
            'title' => $title,
            'description' => $description,
            'content' => "<p>APIからの応答を解析できませんでした。詳細はログを確認してください。</p>"
        ];
    }
}
