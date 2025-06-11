<?php
/**
 * Google Sheets API関連の機能
 */

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
    
    // ヘッダーとペイロードをJSON形式にエンコード
    $headerJson = json_encode($header);
    $payloadJson = json_encode($payload);
    
    // Base64Url形式にエンコード
    $headerBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($headerJson));
    $payloadBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));
    
    // 署名対象の文字列を作成
    $signatureInput = $headerBase64 . '.' . $payloadBase64;
    
    // 署名を作成
    $signature = '';
    openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
    
    // 署名をBase64Url形式にエンコード
    $signatureBase64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    // JWTトークンを作成
    $jwt = $headerBase64 . '.' . $payloadBase64 . '.' . $signatureBase64;
    
    return $jwt;
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
        $serviceAccountData = json_decode(file_get_contents($serviceAccountJson), true);
        
        if (!$serviceAccountData) {
            error_log("Failed to parse service account JSON file");
            return null;
        }
        
        // 必要な情報を取得
        $privateKey = $serviceAccountData['private_key'];
        $clientEmail = $serviceAccountData['client_email'];
        
        // JWTトークンを生成
        $jwt = generateJWT($privateKey, $clientEmail);
        
        // アクセストークンを取得するためのリクエスト
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // レスポンスをJSONからデコード
        $data = json_decode($response, true);
        
        if (isset($data['access_token'])) {
            return $data['access_token'];
        } else {
            error_log("Failed to get access token: " . json_encode($data));
            return null;
        }
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
    $apiKey = GOOGLE_SHEETS_API_KEY;
    $useServiceAccount = defined('USE_SERVICE_ACCOUNT') ? USE_SERVICE_ACCOUNT : false;
    $serviceAccountJson = defined('SERVICE_ACCOUNT_JSON') ? SERVICE_ACCOUNT_JSON : '';
    
    // シート名を含む範囲を作成
    $fullRange = $sheetName . '!' . $range;
    
    // 認証方法を決定
    $accessToken = null;
    if ($useServiceAccount && file_exists($serviceAccountJson)) {
        $accessToken = getAccessToken($serviceAccountJson);
        if (!$accessToken) {
            error_log("Failed to get access token, falling back to API key");
        }
    }
    
    // リクエストデータを準備
    $requestData = [
        'range' => $fullRange,
        'majorDimension' => 'ROWS',
        'values' => $values
    ];
    
    // URLを構築
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$fullRange}";
    
    // アクセストークンがある場合はそれを使用し、なければAPIキーを使用
    if ($accessToken) {
        $url .= "?access_token=" . urlencode($accessToken);
    } else {
        $url .= "?key=" . urlencode($apiKey);
    }
    
    // 更新方法を指定（USER_ENTERED: ユーザーが入力したかのように処理）
    $url .= "&valueInputOption=USER_ENTERED";
    
    // cURLを使用してAPIリクエスト
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // レスポンスをJSONからデコード
    $data = json_decode($response, true);
    
    // 成功したかどうかを確認
    if ($httpCode >= 200 && $httpCode < 300 && isset($data['updatedCells'])) {
        error_log("Successfully updated {$data['updatedCells']} cells");
        return true;
    } else {
        error_log("Failed to update sheet: " . json_encode($data));
        return false;
    }
}

/**
 * スプレッドシートの次の空き列を取得する関数
 * 
 * @param array $sheetData スプレッドシートのデータ
 * @param string $url 対象のURL
 * @return int 次の空き列のインデックス
 */
function getNextEmptyColumn($sheetData, $url) {
    $rowIndex = getRowIndex($sheetData, $url);
    if ($rowIndex !== null) {
        $row = $sheetData[$rowIndex];
        // F列（インデックス5）から始め、6列ごとのブロックを確認
        $columnIndex = 5;
        while (
            isset($row[$columnIndex]) || isset($row[$columnIndex + 1]) ||
            isset($row[$columnIndex + 2]) || isset($row[$columnIndex + 3]) ||
            isset($row[$columnIndex + 4]) || isset($row[$columnIndex + 5])
        ) {
            $columnIndex += 6; // 次の6列ブロックへ
        }
        return $columnIndex;
    }
    return 5; // デフォルトはF列
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
            return $index;
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
        error_log("URL not found in sheet: " . $url);
        return false;
    }
    $columnIndex = getNextEmptyColumn($sheetData, $url);
    $startLetter = chr(65 + $columnIndex);
    $endLetter   = chr(65 + $columnIndex + 5); // 6列分
    $range = $startLetter . ($rowIndex + 2) . ':' . $endLetter . ($rowIndex + 2);

    // Datetime
    $datetime = date('Y-m-d H:i:s');

    // Prepare values (HTML content, not JSON)
    $values = [[
        $originalContent,
        $issues,
        $datetime,
        $improvedData['title'] ?? '',
        $improvedData['description'] ?? '',
        $improvedData['content'] ?? ''
    ]];

    $result = writeToSheet($range, $values);
    if ($result) {
        error_log("Successfully wrote rewrite result to sheet for URL: " . $url);
    } else {
        error_log("Failed to write rewrite result to sheet for URL: " . $url);
    }
    return $result;
}
