<?php
/**
 * Google Gemini (Generative AI) 関連の機能
 * 環境変数 GEMINI_API_KEY に API Key を設定してください。
 */

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
}

// APIバージョン (v1 or v1beta) を環境変数で切替可能に。未指定の場合は v1 を使用。
if (!defined('GEMINI_API_VERSION')) {
    $ver = getenv('GEMINI_API_VERSION');
    if (!$ver) { $ver = 'v1'; }
    define('GEMINI_API_VERSION', $ver);
}

// 利用モデル名（例: gemini-1.5-pro）。公式ドキュメントに沿って環境変数で変更可。
if (!defined('GEMINI_MODEL')) {
    $model = getenv('GEMINI_MODEL');
    if (!$model) { $model = 'gemini-1.5-pro'; }
    define('GEMINI_MODEL', $model);
}

/**
 * Gemini API へリクエストを送信する共通関数
 *
 * @param string $systemPrompt システムプロンプト
 * @param string $userPrompt   ユーザープロンプト
 * @param int    $maxTokens    最大トークン数
 * @param float  $temperature  温度
 * @return string 生成されたテキスト
 */
function geminiGenerateContent($systemPrompt, $userPrompt, $maxTokens = 1024, $temperature = 0.7) {
    $apiKey = GEMINI_API_KEY;
    if (!$apiKey) {
        error_log('GEMINI_API_KEY が設定されていません。');
        return 'Gemini APIキーが設定されていません。';
    }

    $url = 'https://generativelanguage.googleapis.com/' . GEMINI_API_VERSION . '/models/' . rawurlencode(GEMINI_MODEL) . ':generateContent?key=' . urlencode($apiKey);

    // Google Generative Language API の期待フォーマット
    $requestBody = [
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [ ['text' => $systemPrompt . "\n\n" . $userPrompt ] ]
            ]
        ],
        'generationConfig' => [
            'temperature'   => $temperature,
            'maxOutputTokens' => $maxTokens,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('Gemini curl error: ' . curl_error($ch));
        curl_close($ch);
        return 'Gemini API通信エラーが発生しました。ログを確認してください。';
    }
    curl_close($ch);

    // ==== ログ出力: レスポンス全文を保存 ====
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/gemini_' . date('Ymd') . '.log';
    file_put_contents($logFile, "==== " . date('c') . " geminiGenerateContent RAW ====\n" . $response . "\n\n", FILE_APPEND);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonErr = 'JSON_ERROR ' . json_last_error() . ': ' . json_last_error_msg();
        file_put_contents($logFile, "[json_decode error] $jsonErr\n", FILE_APPEND);
    }

    // APIエラー応答処理
    if (isset($data['error'])) {
        $errMsg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
        $errCode = isset($data['error']['code']) ? $data['error']['code'] : 0;
        file_put_contents($logFile, "[gemini error {$errCode}] {$errMsg}\n", FILE_APPEND);
        return "Gemini APIエラー({$errCode}): {$errMsg}";
    }

    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    // エラー情報を記録
    error_log('Gemini API 応答エラー: ' . substr($response, 0, 1000));
    return 'Gemini API応答を解析できませんでした。ログを確認してください。';
}

/*
 * Gemini 版 analyzeArticleIssues
 */
function analyzeArticleIssuesGemini($title, $description, $content) {
    // HTMLタグ除去
    $plainContent = strip_tags($content);
    $maxContentLength = 4000;
    if (mb_strlen($plainContent) > $maxContentLength) {
        $plainContent = mb_substr($plainContent, 0, $maxContentLength) . '...（以下省略）';
    }

    $systemPrompt = "あなたはSEOと記事改善のプロフェッショナルです。与えられた記事を分析し、以下の観点から問題点を指摘してください：\n" .
                    "1. SEO観点（タイトル、メタディスクリプション、見出し構造、キーワード使用など）\n" .
                    "2. 読みやすさ（文章構造、段落分け、専門用語の説明など）\n" .
                    "3. ユーザー体験（情報の網羅性、価値提供、CTAの有無など）\n" .
                    "4. 信頼性（情報の正確さ、データの裏付け、専門性の表現など）\n\n" .
                    "分析結果は箇条書きで簡潔に記載し、改善すべき点を具体的に示してください。\n" .
                    "目次の追加は改善案として禁止です。目次は同ページ内の他の箇所に既に存在しているため不要です。";

    $userPrompt = "以下の記事を分析し、問題点を指摘してください：\n\n" .
                  "【タイトル】\n{$title}\n\n" .
                  "【メタディスクリプション】\n{$description}\n\n" .
                  "【本文】\n{$plainContent}";

    return geminiGenerateContent($systemPrompt, $userPrompt, 1024, 0.7);
}

/*
 * Gemini 版 improveArticle
 */
function improveArticleGemini($title, $description, $content, $issues) {
    // 元HTMLを保持
    $htmlContent = $content;
    $systemPrompt = "あなたは記事リライトのプロフェッショナルです。以下の制約を守り、記事を改善してください。\n" .
                    "本文はHTML形式で出力し、適切な見出し構造（h2, h3,h4など）、段落（p）、改行（br）、リスト（ul, li）、強調（strong, em）などを使用してください。 ただし、以下の点に注意してください： 1. 元の記事の主要な情報や意図を保持すること 2. SEO的に最適化されたタイトル、メタディスクリプション、見出し構造にすること 3. 読みやすく、ユーザーにとって価値のある内容にすること 4. 必要に応じて情報を追加・削除・並べ替えること 5. h1はこの本文の前に既に表示されているので作成してはいけません。 6. このサイトにコメント欄はありません。「感想をコメント欄で教えて」などコメント欄への記入を促す文章は禁止です。 7. 記事本文に該当する部分のみ出力してください。問題点、改善点など本文以外の文章は禁止です。 8. 記事内に目次は作成禁止です。目次はページ内の別の箇所に既に存在しているので不要です。\n" .
                    "夢占いとは？の説明は上部で説明しているので禁止。\n" .
                    "1. SEOを最適化（キーワード使用、タイトルタグ、メタディスクリプション最適化）\n" .
                    "2. 読みやすさ向上（段落分け、シンプルな表現）\n" .
                    "3. ユーザーに価値提供する具体的情報を追加\n" .
                    "4. 誤情報の修正と信頼性向上\n" .
                    "5. h1は本文前に既に表示されているので作成しない\n" .
                    "6. この記事にはコメント欄がないためコメントを促さない\n" .
                    "7. 目次を作成しない\n" .
                    "JSONのみを出力し、他の説明は禁止です。\n" .
                    "本文(content)はHTMLとして<p>タグなどを維持し、句点のあとには常に<br>を挿入してください。";

    $userPrompt = "以下の記事を改善してください：\n\n" .
                  "【元のタイトル】\n{$title}\n\n" .
                  "【元のメタディスクリプション】\n{$description}\n\n" .
                  "【元の本文】\n{$htmlContent}\n\n" .
                  "【分析された問題点】\n{$issues}\n\n" .
                  "上記の問題点を必ずすべて反映し、タイトル・メタディスクリプション・本文すべてに改善点が明確にわかるように記事をリライトしてください。";

    $responseText = geminiGenerateContent($systemPrompt, $userPrompt, 4096, 0.7);

    // Gemini はプレーンテキストで返すことが多いため、"```json" ブロックがあれば抽出
    if (preg_match('/```json\s*(.*?)\s*```/s', $responseText, $matches)) {
        $jsonStr = $matches[1];
    } else {
        $jsonStr = $responseText;
    }

    // 追加フォールバック: 本文中から最初のJSONオブジェクトらしき部分を抽出
    if ($jsonStr === $responseText) {
        $firstBracePos = strpos($responseText, '{');
        $lastBracePos  = strrpos($responseText, '}');
        if ($firstBracePos !== false && $lastBracePos !== false && $lastBracePos > $firstBracePos) {
            $possibleJson = substr($responseText, $firstBracePos, $lastBracePos - $firstBracePos + 1);
            if ($possibleJson) {
                $jsonStr = $possibleJson;
            }
        }
    }

    $improvedData = json_decode($jsonStr, true);
    if (is_array($improvedData)) {
        // キー名のバリエーション対応
        if (!isset($improvedData['description']) && isset($improvedData['meta_description'])) {
            $improvedData['description'] = $improvedData['meta_description'];
        }
        if (!isset($improvedData['content']) && isset($improvedData['body'])) {
            $improvedData['content'] = $improvedData['body'];
        }

        if (isset($improvedData['title']) && isset($improvedData['description']) && isset($improvedData['content'])) {
            return $improvedData;
        }
    }

    // JSON解析失敗時はそのまま返却
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . '/gemini_' . date('Ymd') . '.log';
    file_put_contents($logFile, "==== " . date('c') . " improveArticleGemini JSON parse error ====\n" . substr($responseText, 0, 2000) . "\n\n", FILE_APPEND);

    error_log('Gemini improveArticle JSON parse error. Raw response: ' . substr($responseText, 0, 1000));
    return [
        'title' => $title,
        'description' => $description,
        'content' => '<p>Gemini API応答の解析に失敗しました。</p>'
    ];
}
?>
