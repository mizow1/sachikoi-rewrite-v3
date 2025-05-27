<?php
/**
 * スプレッドシートからデータを取得する関数
 * 
 * @return array スプレッドシートのデータ
 */
function getSheetData() {
    $spreadsheetId = SPREADSHEET_ID;
    $sheetName = SHEET_NAME;
    $useServiceAccount = defined('USE_SERVICE_ACCOUNT') ? USE_SERVICE_ACCOUNT : false;
    $serviceAccountJson = defined('SERVICE_ACCOUNT_JSON') ? SERVICE_ACCOUNT_JSON : '';
    
    // デバッグ情報を出力
    error_log("Spreadsheet ID: " . $spreadsheetId);
    error_log("Sheet Name: " . $sheetName);
    error_log("Use Service Account: " . ($useServiceAccount ? 'true' : 'false'));
    
    if ($useServiceAccount && file_exists($serviceAccountJson)) {
        // サービスアカウントを使用した認証
        error_log("Using service account authentication with file: " . $serviceAccountJson);
        
        try {
            // サービスアカウントのJSONファイルを読み込み
            $serviceAccountData = json_decode(file_get_contents($serviceAccountJson), true);
            
            if (!$serviceAccountData) {
                error_log("Failed to parse service account JSON file");
                throw new Exception("Failed to parse service account JSON file");
            }
            
            // JWTトークンの生成は複雑なので、ここでは簡略して直接APIキーを使用する方法に切り替えます
            error_log("Falling back to API key authentication");
        } catch (Exception $e) {
            error_log("Service account authentication error: " . $e->getMessage());
            error_log("Falling back to API key authentication");
        }
    }
    
    // APIキーを使用してスプレッドシートにアクセス
    $apiKey = GOOGLE_SHEETS_API_KEY;
    // シート名をURLエンコード
    $encodedSheetName = urlencode($sheetName);
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$encodedSheetName}?key={$apiKey}";
    error_log("API URL: " . $url);
    error_log("Encoded Sheet Name: " . $encodedSheetName);
    
    // cURLを使用してAPIリクエスト
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書の検証をスキップ（デバッグ用）
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        return [];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("HTTP Response Code: " . $httpCode);
    
    curl_close($ch);
    
    // デバッグ用にレスポンスを出力
    error_log("API Response: " . substr($response, 0, 1000)); // 長すぎる場合は切り詰める
    
    // レスポンスをJSONからデコード
    $data = json_decode($response, true);
    
    if (isset($data['values'])) {
        // ヘッダー行を削除（最初の行）
        $rows = $data['values'];
        $header = array_shift($rows);
        error_log("Found " . count($rows) . " rows of data");
        return $rows;
    } else {
        // エラーの詳細を記録
        if (isset($data['error'])) {
            error_log("API Error: " . json_encode($data['error']));
        } else {
            error_log("No values found in response");
        }
    }
    
    return [];
}

/**
 * JWT（JSON Web Token）を生成する関数
 * 
 * @param string $privateKey 秘密鍵
 * @param string $clientEmail クライアントメール
 * @return string JWTトークン
 */
function generateJWT($privateKey, $clientEmail) {
    $now = time();
    $exp = $now + 3600; // 1時間の有効期限
    
    // JWTヘッダー
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];
    
    // JWTクレーム
    $payload = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $exp,
        'iat' => $now
    ];
    
    // Base64Urlエンコード
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    
    // 署名対象文字列
    $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;
    
    // 署名を生成
    $privateKeyId = openssl_pkey_get_private($privateKey);
    openssl_sign($signatureInput, $signature, $privateKeyId, 'SHA256');
    openssl_free_key($privateKeyId);
    
    // Base64Urlエンコードされた署名
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    // JWTを組み立てる
    return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
}

/**
 * サービスアカウントを使用してアクセストークンを取得する関数
 * 
 * @param string $serviceAccountJson サービスアカウントのJSONファイルパス
 * @return string|アクセストークンまたはnull
 */
function getAccessToken($serviceAccountJson) {
    try {
        // サービスアカウントのJSONファイルを読み込み
        $serviceAccount = json_decode(file_get_contents($serviceAccountJson), true);
        
        if (!$serviceAccount) {
            error_log("Failed to parse service account JSON file");
            return null;
        }
        
        // 必要な情報を取得
        $privateKey = $serviceAccount['private_key'];
        $clientEmail = $serviceAccount['client_email'];
        
        // JWTを生成
        $jwt = generateJWT($privateKey, $clientEmail);
        
        // アクセストークンを取得するリクエスト
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書の検証をスキップ（デバッグ用）
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('Curl error when getting access token: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            error_log("Failed to get access token. HTTP code: " . $httpCode);
            error_log("Response: " . $response);
            return null;
        }
        
        $tokenData = json_decode($response, true);
        return $tokenData['access_token'] ?? null;
        
    } catch (Exception $e) {
        error_log("Error getting access token: " . $e->getMessage());
        return null;
    }
}

/**
 * スプレッドシートにデータを書き込む関数
 * 
 * @param string $range 書き込む範囲（例: 'A1:E10'）
 * @param array $values 書き込むデータの配列
 * @return bool 成功したかどうか
 */
function writeToSheet($range, $values) {
    $spreadsheetId = SPREADSHEET_ID;
    $sheetName = SHEET_NAME;
    $useServiceAccount = defined('USE_SERVICE_ACCOUNT') ? USE_SERVICE_ACCOUNT : false;
    $serviceAccountJson = defined('SERVICE_ACCOUNT_JSON') ? SERVICE_ACCOUNT_JSON : '';
    
    // シート名をURLエンコード
    $encodedSheetName = urlencode($sheetName);
    $fullRange = $encodedSheetName . '!' . $range;
    
    // デバッグ情報
    error_log("Write API URL base: https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$fullRange}");
    error_log("Encoded Sheet Name: " . $encodedSheetName);
    error_log("Use Service Account: " . ($useServiceAccount ? 'true' : 'false'));
    
    // リクエストボディ
    $bodyArray = ['values' => $values];
    $body = json_encode($bodyArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON encoding error: ' . json_last_error_msg());
        // JSONエンコードエラーの詳細をログに出力
        error_log('Failed to encode data: ' . print_r($bodyArray, true));
        return false;
    }
    
    // データサイズの確認
    $bodySize = strlen($body);
    error_log("Request body size: " . $bodySize . " bytes");
    
    // Google Sheets APIのリクエストサイズ制限（10MB）を確認
    if ($bodySize > 10 * 1024 * 1024) {
        error_log("Request body too large: " . $bodySize . " bytes");
        return false;
    }
    
    $accessToken = null;
    $apiUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$fullRange}?valueInputOption=USER_ENTERED";
    
    // サービスアカウントを使用した認証
    if ($useServiceAccount && file_exists($serviceAccountJson)) {
        error_log("Using service account authentication with file: " . $serviceAccountJson);
        $accessToken = getAccessToken($serviceAccountJson);
        
        if (!$accessToken) {
            error_log("Failed to get access token from service account");
            return false;
        }
        
        error_log("Successfully obtained access token");
    } else {
        error_log("Service account not configured or file not found. Using API key instead.");
        $apiUrl .= "&key=" . GOOGLE_SHEETS_API_KEY;
    }
    
    error_log("Final API URL: " . $apiUrl);
    
    // cURLを使用してAPIリクエスト（PUT）
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    
    $headers = ['Content-Type: application/json', 'Content-Length: ' . $bodySize];
    
    // アクセストークンがあれば追加
    if ($accessToken) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書の検証をスキップ（デバッグ用）
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // レスポンスの詳細をログに出力
    error_log("Sheets API write response code: " . $httpCode);
    error_log("Sheets API write response: " . substr($response, 0, 1000));
    
    return ($httpCode >= 200 && $httpCode < 300);
}

/**
 * フィルタリングされたURLリストを取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @return array フィルタリングされたURLリスト
 */
function getFilteredUrls($sheetData) {
    // フィルタリングロジックをここに実装
    // 現在はシンプルに全URLを返す
    return $sheetData;
}

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
        
        // F列以降のデータを確認し、リライト回数をカウント
        $rewriteCount = 0;
        $columnIndex = 5; // F列から開始（0ベースなので5）
        
        // 元の記事、問題点、書き直し日時、書き直し後の記事のパターンを検出
        while (isset($row[$columnIndex + 3])) { // 4列セットで確認
            if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3])) {
                $rewriteCount++;
            }
            $columnIndex += 4; // 次の4列セットへ
        }
        
        $rewriteCounts[$url] = $rewriteCount;
    }
    
    return $rewriteCounts;
}

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
    $model = OPENAI_MODEL;
    
    // HTMLタグを除去してプレーンテキストに変換
    $plainContent = strip_tags($content);
    
    // OpenAI APIへのリクエスト内容
    $prompt = "以下の記事のSEO観点での問題点を分析してください。具体的な改善ポイントを箇条書きで示してください。\n\n";
    $prompt .= "タイトル: {$title}\n";
    $prompt .= "メタディスクリプション: {$description}\n";
    $prompt .= "本文: {$plainContent}";
    
    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたはSEOに詳しい専門家です。記事の問題点を分析し、具体的な改善ポイントを提案してください。'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    // OpenAI APIにリクエスト
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return $result['choices'][0]['message']['content'];
    }
    
    return '分析中にエラーが発生しました。';
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
    // 処理時間の制限を設定（最大実行時間を180秒に設定）
    set_time_limit(180);
    
    // メモリ制限を緩和
    ini_set('memory_limit', '256M');
    
    try {
        // OpenAI APIのモデルとエンドポイント
        $model = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $apiKey = getenv('OPENAI_API_KEY');
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        error_log("Improving article with OpenAI API. Model: " . $model);
        
        // コンテンツが長すぎる場合は切り詰める
        if (strlen($content) > 3000) {
            error_log("Content too long, truncating to 3000 characters");
            $content = substr($content, 0, 3000) . "...";
        }
        
        // 問題点のリスト
        $issuesList = '';
        if (!empty($issues)) {
            if (is_array($issues)) {
                $issuesList = "問題点:\n" . implode("\n", $issues);
            } else {
                $issuesList = "問題点:\n" . $issues;
            }
        }
        
        // コンテンツをクリーンアップして特殊文字を処理
        $cleanTitle = mb_convert_encoding($title, 'UTF-8', 'auto');
        $cleanDescription = mb_convert_encoding($description, 'UTF-8', 'auto');
        $cleanContent = mb_convert_encoding($content, 'UTF-8', 'auto');
        
        // 特殊文字をエスケープしてJSONエラーを防止
        $cleanTitle = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanTitle);
        $cleanDescription = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanDescription);
        $cleanContent = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanContent);
        
        // APIリクエストデータ
        $systemContent = '以下の記事を改善してください。SEO的に最適化し、読みやすく、魅力的な内容にしてください。';
        $userContent = "元の記事:\nタイトル: {$cleanTitle}\nメタディスクリプション: {$cleanDescription}\n本文: {$cleanContent}\n\n{$issuesList}\n\n改善した記事を以下のフォーマットで出力してください：\nタイトル: [改善されたタイトル]\nメタディスクリプション: [改善されたメタディスクリプション]\n本文: [改善された本文]";
        
        $requestData = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemContent
                ],
                [
                    'role' => 'user',
                    'content' => $userContent
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];
        
        // JSONエンコードをデバッグ
        $jsonData = json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSONエンコードエラー: " . json_last_error_msg());
            throw new Exception("JSONエンコードエラー: " . json_last_error_msg());
        }
        
        // APIリクエストを実行
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // タイムアウトを120秒に設定
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書の検証をスキップ
        
        error_log("Sending request to OpenAI API");
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            error_log("cURL error: " . $curlError);
            curl_close($ch);
            throw new Exception("APIリクエストエラー: " . $curlError);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("OpenAI API response code: " . $httpCode);
        
        if ($httpCode != 200) {
            error_log("HTTP error in OpenAI API call: " . $httpCode);
            error_log("Response: " . substr($response, 0, 1000));
            throw new Exception("APIレスポンスエラー (HTTP {$httpCode})");
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            error_log("Failed to parse OpenAI response: " . substr($response, 0, 1000));
            throw new Exception("APIレスポンスの解析に失敗しました");
        }
        
        $improvedContent = $result['choices'][0]['message']['content'];
        error_log("Successfully received improved content from OpenAI");
        
        // 改善された内容を解析
        $improvedTitle = $title; // デフォルトは元のタイトル
        $improvedDescription = $description; // デフォルトは元の説明
        $improvedBody = $content; // デフォルトは元の本文
        
        // タイトルの抽出
        if (preg_match('/タイトル:\s*(.+?)(?=\nメタディスクリプション:|$)/s', $improvedContent, $titleMatches)) {
            $extractedTitle = trim($titleMatches[1]);
            if (!empty($extractedTitle)) {
                $improvedTitle = $extractedTitle;
                error_log("Extracted improved title: " . substr($improvedTitle, 0, 50));
            }
        }
        
        // メタディスクリプションの抽出
        if (preg_match('/メタディスクリプション:\s*(.+?)(?=\n本文:|$)/s', $improvedContent, $descMatches)) {
            $extractedDesc = trim($descMatches[1]);
            if (!empty($extractedDesc)) {
                $improvedDescription = $extractedDesc;
                error_log("Extracted improved description: " . substr($improvedDescription, 0, 50));
            }
        }
        
        // 本文の抽出
        if (preg_match('/本文:\s*(.+?)$/s', $improvedContent, $bodyMatches)) {
            $extractedBody = trim($bodyMatches[1]);
            if (!empty($extractedBody)) {
                $improvedBody = $extractedBody;
                error_log("Extracted improved body: length=" . strlen($improvedBody));
            }
        } else {
            // 本文タグが見つからない場合は、フォーマットが異なる可能性があるので全体を本文として扱う
            error_log("Body tag not found, using entire content as body");
            $improvedBody = $improvedContent;
        }
        
        return [
            'title' => $improvedTitle,
            'description' => $improvedDescription,
            'content' => $improvedBody
        ];
        
    } catch (Exception $e) {
        error_log("Error in improveArticle: " . $e->getMessage());
        return [
            'title' => $title,
            'description' => $description,
            'content' => "<h2>エラー</h2><p>記事の改善中にエラーが発生しました: " . $e->getMessage() . "</p>",
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 元の記事データを取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $url 対象のURL
 * @return array|null 元の記事データ（タイトル、ディスクリプション、本文）またはnull
 */
function getOriginalArticle($sheetData, $url) {
    error_log("Searching for original article data for URL: " . $url);
    
    // 処理時間の制限を設定（最大実行時間を180秒に設定）
    set_time_limit(180);
    
    foreach ($sheetData as $rowIndex => $row) {
        if ($row[0] === $url) {
            error_log("Found URL match at row index: " . $rowIndex);
            
            // まずスプレッドシートのデータを確認
            if (isset($row[5]) && !empty($row[5])) {
                error_log("Using existing content from spreadsheet");
                $originalContent = $row[5];
            } else {
                // URLから直接記事データを取得する
                error_log("Fetching article content from URL: " . $url);
                
                try {
                    // cURLを使用してURLからコンテンツを取得
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // リダイレクトを追跡
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL証明書の検証をスキップ
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // タイムアウト設定を短く
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'); // User-Agentを設定
                    $originalContent = curl_exec($ch);
                    
                    if (curl_errno($ch)) {
                        error_log("cURL error: " . curl_error($ch));
                        throw new Exception("Failed to fetch content from URL");
                    }
                    
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode != 200) {
                        error_log("HTTP error: " . $httpCode);
                        throw new Exception("HTTP error: " . $httpCode);
                    }
                } catch (Exception $e) {
                    error_log("Error fetching article: " . $e->getMessage());
                    // エラーの場合はダミーコンテンツを使用
                    $originalContent = "<h1>記事取得エラー</h1><p>URL: {$url}</p>";
                }
            }
            
            // データの形式を確認
            error_log("Content length: " . strlen($originalContent));
            error_log("Content preview: " . substr($originalContent, 0, 200));
            
            // タイトルとディスクリプションを抽出
            $title = '';
            $description = '';
            
            // タイトルを取得
            if (preg_match('/<title>(.*?)<\/title>/is', $originalContent, $titleMatches)) {
                $title = trim($titleMatches[1]);
                error_log("Extracted title from HTML: " . $title);
            }
            
            // ディスクリプションを取得
            if (preg_match('/<meta[^>]*name=["\']description["\'](\s+|\s+content=["\'](.*?)["\'](　+|>))/is', $originalContent, $descMatches)) {
                $description = isset($descMatches[2]) ? trim($descMatches[2]) : '';
                error_log("Extracted description from HTML: " . $description);
            }
            
            // タイトルが見つからない場合はURLから生成
            if (empty($title)) {
                $pathParts = explode('/', parse_url($url, PHP_URL_PATH));
                $lastPart = end($pathParts);
                $title = ucfirst(str_replace(['-', '_'], ' ', $lastPart));
                error_log("Generated title from URL: " . $title);
            }
            
            // ディスクリプションが見つからない場合はタイトルから生成
            if (empty($description)) {
                $description = $title;
                error_log("Using title as description");
            }
            
            // 本文部分を抽出する試み
            // コンテンツ部分を抽出する正規表現を改善
            $content = $originalContent;
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $originalContent, $bodyMatches)) {
                $content = $bodyMatches[1];
                error_log("Extracted body content");
            }
            
            error_log("Successfully fetched and parsed article content");
            return [
                'title' => $title,
                'description' => $description,
                'content' => $content
            ];
        }
    }
    
    error_log("No matching URL found in sheet data");
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
            // F列以降のデータを確認
            $columnIndex = 5; // F列から開始（0ベースなので5）
            $latestRewriteData = null;
            
            // 元の記事、問題点、書き直し日時、書き直し後の記事のパターンを検出
            while (isset($row[$columnIndex + 3])) { // 4列セットで確認
                if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                    !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3])) {
                    // 最新のリライトデータを更新
                    $latestRewriteData = [
                        'original' => $row[$columnIndex],
                        'issues' => $row[$columnIndex + 1],
                        'datetime' => $row[$columnIndex + 2],
                        'improved' => $row[$columnIndex + 3]
                    ];
                }
                $columnIndex += 4; // 次の4列セットへ
            }
            
            return $latestRewriteData;
        }
    }
    
    return null;
}

/**
 * スプレッドシートの次の空き列を取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $url 対象のURL
 * @return int 次の空き列のインデックス
 */
function getNextEmptyColumn($sheetData, $url) {
    foreach ($sheetData as $rowIndex => $row) {
        if ($row[0] === $url) {
            $columnIndex = 5; // F列から開始（0ベースなので5）
            
            // 最後の列を探す
            while (isset($row[$columnIndex])) {
                $columnIndex++;
            }
            
            return $columnIndex;
        }
    }
    
    // URLが見つからない場合はF列（インデックス5）を返す
    return 5;
}

/**
 * スプレッドシートの行インデックスを取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $url 対象のURL
 * @return int|null 行インデックスまたはnull
 */
function getRowIndex($sheetData, $url) {
    foreach ($sheetData as $index => $row) {
        if ($row[0] === $url) {
            return $index + 2; // スプレッドシートは1ベース、ヘッダー行があるため+2
        }
    }
    
    return null;
}

/**
 * リライト結果をスプレッドシートに書き込む関数
 * 
 * @param string $url 対象のURL
 * @param string $originalContent 元の記事内容
 * @param string $issues 問題点
 * @param array $improvedData 改善された記事データ
 * @return bool 成功したかどうか
 */
function writeRewriteResult($url, $originalContent, $issues, $improvedData) {
    $sheetData = getSheetData();
    $rowIndex = getRowIndex($sheetData, $url);
    
    if ($rowIndex === null) {
        error_log("URL not found in spreadsheet: " . $url);
        return false;
    }
    
    $nextColumn = getNextEmptyColumn($sheetData, $url);
    $columnLetter = chr(65 + $nextColumn); // 列番号をアルファベットに変換（A=0, B=1, ...）
    
    // 書き込むデータ
    $datetime = date('Y-m-d H:i:s');
    
    // データサイズの制限
    // 元の記事内容を制限
    if (strlen($originalContent) > 10000) {
        error_log("Original content too long, truncating to 10000 characters");
        $originalContent = mb_substr($originalContent, 0, 10000, 'UTF-8') . "...(truncated)";
    }
    
    // 問題点を制限
    if (strlen($issues) > 5000) {
        error_log("Issues content too long, truncating to 5000 characters");
        $issues = mb_substr($issues, 0, 5000, 'UTF-8') . "...(truncated)";
    }
    
    // 改善されたコンテンツをJSON形式に変換
    try {
        // タイトルと説明を制限
        if (isset($improvedData['title']) && strlen($improvedData['title']) > 500) {
            $improvedData['title'] = mb_substr($improvedData['title'], 0, 500, 'UTF-8');
        }
        
        if (isset($improvedData['description']) && strlen($improvedData['description']) > 1000) {
            $improvedData['description'] = mb_substr($improvedData['description'], 0, 1000, 'UTF-8');
        }
        
        // 本文を制限
        if (isset($improvedData['content']) && strlen($improvedData['content']) > 20000) {
            $improvedData['content'] = mb_substr($improvedData['content'], 0, 20000, 'UTF-8') . "...(truncated)";
        }
        
        $improvedContent = json_encode($improvedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON encoding error: ' . json_last_error_msg());
            // フォールバック: 単純なテキスト形式で保存
            $improvedContent = "タイトル: " . $improvedData['title'] . "\nメタディスクリプション: " . $improvedData['description'] . "\n本文: " . $improvedData['content'];
        }
    } catch (Exception $e) {
        error_log('Error preparing improved content: ' . $e->getMessage());
        $improvedContent = "Error: Failed to encode improved content";
    }
    
    // 書き込む範囲を計算
    $range = $columnLetter . $rowIndex . ':' . chr(65 + $nextColumn + 3) . $rowIndex;
    error_log("Writing to range: " . $range);
    
    // 書き込むデータ
    $values = [[
        $originalContent,
        $issues,
        $datetime,
        $improvedContent
    ]];
    
    // スプレッドシートに書き込み
    $result = writeToSheet($range, $values);
    if (!$result) {
        error_log("Failed to write rewrite result to spreadsheet for URL: " . $url);
    } else {
        error_log("Successfully wrote rewrite result to spreadsheet for URL: " . $url);
    }
    
    return $result;
}

/**
 * 改善された記事データをJSONから解析する関数
 * 
 * @param string $jsonData JSON形式の記事データ
 * @return array 解析された記事データ
 */
function parseImprovedArticleData($jsonData) {
    $data = json_decode($jsonData, true);
    
    if (is_array($data) && isset($data['title']) && isset($data['description']) && isset($data['content'])) {
        return $data;
    }
    
    // JSONでない場合は文字列から解析を試みる
    $title = '';
    $description = '';
    $content = $jsonData;
    
    if (preg_match('/タイトル:\s*(.+?)(?=\nメタディスクリプション:|$)/s', $jsonData, $titleMatches)) {
        $title = trim($titleMatches[1]);
    }
    
    if (preg_match('/メタディスクリプション:\s*(.+?)(?=\n本文:|$)/s', $jsonData, $descMatches)) {
        $description = trim($descMatches[1]);
    }
    
    if (preg_match('/本文:\s*(.+?)$/s', $jsonData, $bodyMatches)) {
        $content = trim($bodyMatches[1]);
    }
    
    return [
        'title' => $title,
        'description' => $description,
        'content' => $content
    ];
}
