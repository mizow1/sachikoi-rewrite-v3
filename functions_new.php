<?php
/**
 * 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医°繧峨ョ繝ｼ繧ｿ繧貞叙蠕励☆繧矩未謨ｰ
 * 
 * @return array 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 */
function getSheetData() {
    $spreadsheetId = SPREADSHEET_ID;
    $sheetName = SHEET_NAME;
    $useServiceAccount = defined('USE_SERVICE_ACCOUNT') ? USE_SERVICE_ACCOUNT : false;
    $serviceAccountJson = defined('SERVICE_ACCOUNT_JSON') ? SERVICE_ACCOUNT_JSON : '';
    
    // 繝・ヰ繝・げ諠・ｱ繧貞・蜉・    error_log("Spreadsheet ID: " . $spreadsheetId);
    error_log("Sheet Name: " . $sheetName);
    error_log("Use Service Account: " . ($useServiceAccount ? 'true' : 'false'));
    
    if ($useServiceAccount && file_exists($serviceAccountJson)) {
        // 繧ｵ繝ｼ繝薙せ繧｢繧ｫ繧ｦ繝ｳ繝医ｒ菴ｿ逕ｨ縺励◆隱崎ｨｼ
        error_log("Using service account authentication with file: " . $serviceAccountJson);
        
        try {
            // 繧ｵ繝ｼ繝薙せ繧｢繧ｫ繧ｦ繝ｳ繝医・JSON繝輔ぃ繧､繝ｫ繧定ｪｭ縺ｿ霎ｼ縺ｿ
            $serviceAccountData = json_decode(file_get_contents($serviceAccountJson), true);
            
            if (!$serviceAccountData) {
                error_log("Failed to parse service account JSON file");
                throw new Exception("Failed to parse service account JSON file");
            }
            
            // JWT繝医・繧ｯ繝ｳ縺ｮ逕滓・縺ｯ隍・尅縺ｪ縺ｮ縺ｧ縲√％縺薙〒縺ｯ邁｡逡･縺励※逶ｴ謗･API繧ｭ繝ｼ繧剃ｽｿ逕ｨ縺吶ｋ譁ｹ豕輔↓蛻・ｊ譖ｿ縺医∪縺・            error_log("Falling back to API key authentication");
        } catch (Exception $e) {
            error_log("Service account authentication error: " . $e->getMessage());
            error_log("Falling back to API key authentication");
        }
    }
    
    // API繧ｭ繝ｼ繧剃ｽｿ逕ｨ縺励※繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医↓繧｢繧ｯ繧ｻ繧ｹ
    $apiKey = GOOGLE_SHEETS_API_KEY;
    // 繧ｷ繝ｼ繝亥錐繧旦RL繧ｨ繝ｳ繧ｳ繝ｼ繝・    $encodedSheetName = urlencode($sheetName);
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$encodedSheetName}?key={$apiKey}";
    error_log("API URL: " . $url);
    error_log("Encoded Sheet Name: " . $encodedSheetName);
    
    // cURL繧剃ｽｿ逕ｨ縺励※API繝ｪ繧ｯ繧ｨ繧ｹ繝・    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL險ｼ譏取嶌縺ｮ讀懆ｨｼ繧偵せ繧ｭ繝・・・医ョ繝舌ャ繧ｰ逕ｨ・・    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        return [];
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("HTTP Response Code: " . $httpCode);
    
    curl_close($ch);
    
    // 繝・ヰ繝・げ逕ｨ縺ｫ繝ｬ繧ｹ繝昴Φ繧ｹ繧貞・蜉・    error_log("API Response: " . substr($response, 0, 1000)); // 髟ｷ縺吶℃繧句ｴ蜷医・蛻・ｊ隧ｰ繧√ｋ
    
    // 繝ｬ繧ｹ繝昴Φ繧ｹ繧谷SON縺九ｉ繝・さ繝ｼ繝・    $data = json_decode($response, true);
    
    if (isset($data['values'])) {
        // 繝倥ャ繝繝ｼ陦後ｒ蜑企勁・域怙蛻昴・陦鯉ｼ・        $rows = $data['values'];
        $header = array_shift($rows);
        error_log("Found " . count($rows) . " rows of data");
        return $rows;
    } else {
        // 繧ｨ繝ｩ繝ｼ縺ｮ隧ｳ邏ｰ繧定ｨ倬鹸
        if (isset($data['error'])) {
            error_log("API Error: " . json_encode($data['error']));
        } else {
            error_log("No values found in response");
        }
    }
    
    return [];
}

/**
 * JWT・・SON Web Token・峨ｒ逕滓・縺吶ｋ髢｢謨ｰ
 * 
 * @param string $privateKey 遘伜ｯ・嵯
 * @param string $clientEmail 繧ｯ繝ｩ繧､繧｢繝ｳ繝医Γ繝ｼ繝ｫ
 * @return string JWT繝医・繧ｯ繝ｳ
 */
function generateJWT($privateKey, $clientEmail) {
    $now = time();
    $exp = $now + 3600; // 1譎る俣縺ｮ譛牙柑譛滄剞
    
    // JWT繝倥ャ繝繝ｼ
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];
    
    // JWT繧ｯ繝ｬ繝ｼ繝
    $payload = [
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $exp,
        'iat' => $now
    ];
    
    // Base64Url繧ｨ繝ｳ繧ｳ繝ｼ繝・    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    
    // 鄂ｲ蜷榊ｯｾ雎｡譁・ｭ怜・
    $signatureInput = $base64UrlHeader . '.' . $base64UrlPayload;
    
    // 鄂ｲ蜷阪ｒ逕滓・
    $privateKeyId = openssl_pkey_get_private($privateKey);
    openssl_sign($signatureInput, $signature, $privateKeyId, 'SHA256');
    openssl_free_key($privateKeyId);
    
    // Base64Url繧ｨ繝ｳ繧ｳ繝ｼ繝峨＆繧後◆鄂ｲ蜷・    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    // JWT繧堤ｵ・∩遶九※繧・    return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
}

/**
 * 繧ｵ繝ｼ繝薙せ繧｢繧ｫ繧ｦ繝ｳ繝医ｒ菴ｿ逕ｨ縺励※繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ繧貞叙蠕励☆繧矩未謨ｰ
 * 
 * @param string $serviceAccountJson 繧ｵ繝ｼ繝薙せ繧｢繧ｫ繧ｦ繝ｳ繝医・JSON繝輔ぃ繧､繝ｫ繝代せ
 * @return string|繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ縺ｾ縺溘・null
 */
function getAccessToken($serviceAccountJson) {
    try {
        // 繧ｵ繝ｼ繝薙せ繧｢繧ｫ繧ｦ繝ｳ繝医・JSON繝輔ぃ繧､繝ｫ繧定ｪｭ縺ｿ霎ｼ縺ｿ
        $serviceAccount = json_decode(file_get_contents($serviceAccountJson), true);
        
        if (!$serviceAccount) {
            error_log("Failed to parse service account JSON file");
            return null;
        }
        
        // 蠢・ｦ√↑諠・ｱ繧貞叙蠕・        $privateKey = $serviceAccount['private_key'];
        $clientEmail = $serviceAccount['client_email'];
        
        // JWT繧堤函謌・        $jwt = generateJWT($privateKey, $clientEmail);
        
        // 繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ繧貞叙蠕励☆繧九Μ繧ｯ繧ｨ繧ｹ繝・        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL險ｼ譏取嶌縺ｮ讀懆ｨｼ繧偵せ繧ｭ繝・・・医ョ繝舌ャ繧ｰ逕ｨ・・        
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
 * 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医↓繝・・繧ｿ繧呈嶌縺崎ｾｼ繧髢｢謨ｰ
 * 
 * @param string $range 譖ｸ縺崎ｾｼ繧遽・峇・井ｾ・ 'A1:E10'・・ * @param array $values 譖ｸ縺崎ｾｼ繧繝・・繧ｿ縺ｮ驟榊・
 * @return bool 謌仙粥縺励◆縺九←縺・°
 */
function writeToSheet($range, $values) {
    $spreadsheetId = SPREADSHEET_ID;
    $sheetName = SHEET_NAME;
    $useServiceAccount = defined('USE_SERVICE_ACCOUNT') ? USE_SERVICE_ACCOUNT : false;
    $serviceAccountJson = defined('SERVICE_ACCOUNT_JSON') ? SERVICE_ACCOUNT_JSON : '';
    
    // 繧ｷ繝ｼ繝亥錐繧旦RL繧ｨ繝ｳ繧ｳ繝ｼ繝・    $encodedSheetName = urlencode($sheetName);
    $fullRange = $encodedSheetName . '!' . $range;
    
    // 繝・ヰ繝・げ諠・ｱ
    error_log("Write API URL base: https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$fullRange}");
    error_log("Encoded Sheet Name: " . $encodedSheetName);
    error_log("Use Service Account: " . ($useServiceAccount ? 'true' : 'false'));
    
    // 繝ｪ繧ｯ繧ｨ繧ｹ繝医・繝・ぅ
    $bodyArray = ['values' => $values];
    $body = json_encode($bodyArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON encoding error: ' . json_last_error_msg());
        // JSON繧ｨ繝ｳ繧ｳ繝ｼ繝峨お繝ｩ繝ｼ縺ｮ隧ｳ邏ｰ繧偵Ο繧ｰ縺ｫ蜃ｺ蜉・        error_log('Failed to encode data: ' . print_r($bodyArray, true));
        return false;
    }
    
    // 繝・・繧ｿ繧ｵ繧､繧ｺ縺ｮ遒ｺ隱・    $bodySize = strlen($body);
    error_log("Request body size: " . $bodySize . " bytes");
    
    // Google Sheets API縺ｮ繝ｪ繧ｯ繧ｨ繧ｹ繝医し繧､繧ｺ蛻ｶ髯撰ｼ・0MB・峨ｒ遒ｺ隱・    if ($bodySize > 10 * 1024 * 1024) {
        error_log("Request body too large: " . $bodySize . " bytes");
        return false;
    }
    
    $accessToken = null;
    $apiUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$fullRange}?valueInputOption=USER_ENTERED";
    
    // 繧ｵ繝ｼ繝薙せ繧｢繧ｫ繧ｦ繝ｳ繝医ｒ菴ｿ逕ｨ縺励◆隱崎ｨｼ
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
    
    // cURL繧剃ｽｿ逕ｨ縺励※API繝ｪ繧ｯ繧ｨ繧ｹ繝茨ｼ・UT・・    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    
    $headers = ['Content-Type: application/json', 'Content-Length: ' . $bodySize];
    
    // 繧｢繧ｯ繧ｻ繧ｹ繝医・繧ｯ繝ｳ縺後≠繧後・霑ｽ蜉
    if ($accessToken) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL險ｼ譏取嶌縺ｮ讀懆ｨｼ繧偵せ繧ｭ繝・・・医ョ繝舌ャ繧ｰ逕ｨ・・    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Curl error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 繝ｬ繧ｹ繝昴Φ繧ｹ縺ｮ隧ｳ邏ｰ繧偵Ο繧ｰ縺ｫ蜃ｺ蜉・    error_log("Sheets API write response code: " . $httpCode);
    error_log("Sheets API write response: " . substr($response, 0, 1000));
    
    return ($httpCode >= 200 && $httpCode < 300);
}

/**
 * 繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ縺ｨ繧ｽ繝ｼ繝医＆繧後◆URL繝ｪ繧ｹ繝医ｒ蜿門ｾ励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @param string $keyword 讀懃ｴ｢繧ｭ繝ｼ繝ｯ繝ｼ繝会ｼ医が繝励す繝ｧ繝ｳ・・ * @param string $sortBy 繧ｽ繝ｼ繝域擅莉ｶ・医が繝励す繝ｧ繝ｳ・・ * @return array 繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ縺ｨ繧ｽ繝ｼ繝医＆繧後◆URL繝ｪ繧ｹ繝・ */
function getFilteredUrls($sheetData, $keyword = '', $sortBy = 'impressions_desc') {
    // 繝ｪ繝ｩ繧､繝亥屓謨ｰ繧定ｨ育ｮ・    $rewriteCounts = calculateRewriteCounts($sheetData);
    
    // 繧ｭ繝ｼ繝ｯ繝ｼ繝峨〒繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ
    $filteredUrls = [];
    foreach ($sheetData as $row) {
        $url = $row[0];
        
        // 繧ｭ繝ｼ繝ｯ繝ｼ繝峨′謖・ｮ壹＆繧後※縺・ｋ蝣ｴ蜷医ゞRL縺ｫ蜷ｫ縺ｾ繧後※縺・ｋ縺九メ繧ｧ繝・け
        if (!empty($keyword) && stripos($url, $keyword) === false) {
            continue;
        }
        
        // 繝ｪ繝ｩ繧､繝亥屓謨ｰ繧定ｿｽ蜉
        $row[] = isset($rewriteCounts[$url]) ? $rewriteCounts[$url] : 0;
        $filteredUrls[] = $row;
    }
    
    // 繧ｽ繝ｼ繝亥・逅・    usort($filteredUrls, function($a, $b) use ($sortBy) {
        $urlA = $a[0];
        $urlB = $b[0];
        $impressionsA = isset($a[1]) ? (int)$a[1] : 0;
        $impressionsB = isset($b[1]) ? (int)$b[1] : 0;
        $clicksA = isset($a[2]) ? (int)$a[2] : 0;
        $clicksB = isset($b[2]) ? (int)$b[2] : 0;
        $positionA = isset($a[4]) ? (float)$a[4] : 0;
        $positionB = isset($b[4]) ? (float)$b[4] : 0;
        $rewriteCountA = isset($a[count($a)-1]) ? (int)$a[count($a)-1] : 0;
        $rewriteCountB = isset($b[count($b)-1]) ? (int)$b[count($b)-1] : 0;
        
        switch ($sortBy) {
            case 'impressions_asc':
                return $impressionsA - $impressionsB;
            case 'impressions_desc':
                return $impressionsB - $impressionsA;
            case 'clicks_asc':
                return $clicksA - $clicksB;
            case 'clicks_desc':
                return $clicksB - $clicksA;
            case 'position_asc':
                return $positionA - $positionB;
            case 'position_desc':
                return $positionB - $positionA;
            case 'rewrite_count_asc':
                return $rewriteCountA - $rewriteCountB;
            case 'rewrite_count_desc':
                return $rewriteCountB - $rewriteCountA;
            case 'url_asc':
                return strcmp($urlA, $urlB);
            case 'url_desc':
                return strcmp($urlB, $urlA);
            default:
                return $impressionsB - $impressionsA; // 繝・ヵ繧ｩ繝ｫ繝医・陦ｨ遉ｺ蝗樊焚縺ｮ螟壹＞鬆・        }
    });
    
    return $filteredUrls;
}

/**
 * 繝ｪ繝ｩ繧､繝亥屓謨ｰ繧定ｨ育ｮ励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @return array URL縺斐→縺ｮ繝ｪ繝ｩ繧､繝亥屓謨ｰ
 */
function calculateRewriteCounts($sheetData) {
    $rewriteCounts = [];
    
    foreach ($sheetData as $row) {
        $url = $row[0];
        $columnIndex = 5; // F蛻励°繧蛾幕蟋具ｼ・繝吶・繧ｹ縺ｪ縺ｮ縺ｧ5・・        $count = 0;
        
        // F蛻嶺ｻ･髯阪ｒ3蛻励★縺､繝√ぉ繝・け・域律譎ゅ√ち繧､繝医Ν縲√Γ繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ・・        while (isset($row[$columnIndex]) && !empty($row[$columnIndex])) {
            $count++;
            $columnIndex += 3;
        }
        
        $rewriteCounts[$url] = $count;
    }
    
    return $rewriteCounts;
}

/**
 * 繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ縺ｨ繧ｽ繝ｼ繝医＆繧後◆URL繝ｪ繧ｹ繝医ｒ蜿門ｾ励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @param string $keyword 讀懃ｴ｢繧ｭ繝ｼ繝ｯ繝ｼ繝会ｼ医が繝励す繝ｧ繝ｳ・・ * @param string $sortBy 繧ｽ繝ｼ繝域擅莉ｶ・医が繝励す繝ｧ繝ｳ・・ * @return array 繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ縺ｨ繧ｽ繝ｼ繝医＆繧後◆URL繝ｪ繧ｹ繝・ */
function getFilteredUrls($sheetData, $keyword = '', $sortBy = 'impressions_desc') {
    // 繝ｪ繝ｩ繧､繝亥屓謨ｰ繧定ｨ育ｮ・    $rewriteCounts = calculateRewriteCounts($sheetData);
    
    // 繧ｭ繝ｼ繝ｯ繝ｼ繝峨〒繝輔ぅ繝ｫ繧ｿ繝ｪ繝ｳ繧ｰ
    $filteredUrls = [];
    foreach ($sheetData as $row) {
        $url = $row[0];
        
        // 繧ｭ繝ｼ繝ｯ繝ｼ繝峨′謖・ｮ壹＆繧後※縺・ｋ蝣ｴ蜷医ゞRL縺ｫ蜷ｫ縺ｾ繧後※縺・ｋ縺九メ繧ｧ繝・け
        if (!empty($keyword) && stripos($url, $keyword) === false) {
            continue;
        }
        
        // 繝ｪ繝ｩ繧､繝亥屓謨ｰ繧定ｿｽ蜉
        $row[] = isset($rewriteCounts[$url]) ? $rewriteCounts[$url] : 0;
        $filteredUrls[] = $row;
    }
    
    // 繧ｽ繝ｼ繝亥・逅・    usort($filteredUrls, function($a, $b) use ($sortBy) {
        $urlA = $a[0];
        $urlB = $b[0];
        $impressionsA = isset($a[1]) ? (int)$a[1] : 0;
        $impressionsB = isset($b[1]) ? (int)$b[1] : 0;
        $clicksA = isset($a[2]) ? (int)$a[2] : 0;
        $clicksB = isset($b[2]) ? (int)$b[2] : 0;
        $positionA = isset($a[4]) ? (float)$a[4] : 0;
        $positionB = isset($b[4]) ? (float)$b[4] : 0;
        $rewriteCountA = isset($a[count($a)-1]) ? (int)$a[count($a)-1] : 0;
        $rewriteCountB = isset($b[count($b)-1]) ? (int)$b[count($b)-1] : 0;
        
        switch ($sortBy) {
            case 'impressions_asc':
                return $impressionsA - $impressionsB;
            case 'impressions_desc':
                return $impressionsB - $impressionsA;
            case 'clicks_asc':
                return $clicksA - $clicksB;
            case 'clicks_desc':
                return $clicksB - $clicksA;
            case 'position_asc':
                return $positionA - $positionB;
            case 'position_desc':
                return $positionB - $positionA;
            case 'rewrite_count_asc':
                return $rewriteCountA - $rewriteCountB;
            case 'rewrite_count_desc':
                return $rewriteCountB - $rewriteCountA;
            case 'url_asc':
                return strcmp($urlA, $urlB);
            case 'url_desc':
                return strcmp($urlB, $urlA);
            default:
                return $impressionsB - $impressionsA; // 繝・ヵ繧ｩ繝ｫ繝医・陦ｨ遉ｺ蝗樊焚縺ｮ螟壹＞鬆・        }
    });
    
    return $filteredUrls;
}

/**
 * 險倅ｺ九・蝠城｡檎せ繧貞・譫舌☆繧矩未謨ｰ・・penAI API菴ｿ逕ｨ・・ * 
 * @param string $title 險倅ｺ九ち繧､繝医Ν
 * @param string $description 繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ
 * @param string $content 險倅ｺ区悽譁・ * @return string 蝠城｡檎せ縺ｮ蛻・梵邨先棡
 */
function analyzeArticleIssues($title, $description, $content) {
    $apiKey = OPENAI_API_KEY;
    $model = OPENAI_MODEL;
    
    // HTML繧ｿ繧ｰ繧帝勁蜴ｻ縺励※繝励Ξ繝ｼ繝ｳ繝・く繧ｹ繝医↓螟画鋤
    $plainContent = strip_tags($content);
    
    // OpenAI API縺ｸ縺ｮ繝ｪ繧ｯ繧ｨ繧ｹ繝亥・螳ｹ
    $prompt = "莉･荳九・險倅ｺ九・SEO隕ｳ轤ｹ縺ｧ縺ｮ蝠城｡檎せ繧貞・譫舌＠縺ｦ縺上□縺輔＞縲ょ・菴鍋噪縺ｪ謾ｹ蝟・・繧､繝ｳ繝医ｒ邂・擅譖ｸ縺阪〒遉ｺ縺励※縺上□縺輔＞縲・n\n";
    $prompt .= "繧ｿ繧､繝医Ν: {$title}\n";
    $prompt .= "繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ: {$description}\n";
    $prompt .= "譛ｬ譁・ {$plainContent}";
    
    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => '縺ゅ↑縺溘・SEO縺ｫ隧ｳ縺励＞蟆る摩螳ｶ縺ｧ縺吶りｨ倅ｺ九・蝠城｡檎せ繧貞・譫舌＠縲∝・菴鍋噪縺ｪ謾ｹ蝟・・繧､繝ｳ繝医ｒ謠先｡医＠縺ｦ縺上□縺輔＞縲・
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 1000
    ];
    
    // OpenAI API縺ｫ繝ｪ繧ｯ繧ｨ繧ｹ繝・    $ch = curl_init('https://api.openai.com/v1/chat/completions');
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
    
    return '蛻・梵荳ｭ縺ｫ繧ｨ繝ｩ繝ｼ縺檎匱逕溘＠縺ｾ縺励◆縲・;
}

/**
 * 險倅ｺ九ｒ謾ｹ蝟・☆繧矩未謨ｰ・・penAI API菴ｿ逕ｨ・・ * 
 * @param string $title 蜈・・險倅ｺ九ち繧､繝医Ν
 * @param string $description 蜈・・繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ
 * @param string $content 蜈・・險倅ｺ区悽譁・ * @param string $issues 蛻・梵縺輔ｌ縺溷撫鬘檎せ
 * @return array 謾ｹ蝟・＆繧後◆險倅ｺ具ｼ医ち繧､繝医Ν縲√ョ繧｣繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縲∵悽譁・ｼ・ */
function improveArticle($title, $description, $content, $issues) {
    // 蜃ｦ逅・凾髢薙・蛻ｶ髯舌ｒ險ｭ螳夲ｼ域怙螟ｧ螳溯｡梧凾髢薙ｒ180遘偵↓險ｭ螳夲ｼ・    set_time_limit(180);
    
    // 繝｡繝｢繝ｪ蛻ｶ髯舌ｒ邱ｩ蜥・    ini_set('memory_limit', '512M');
    
    try {
        // OpenAI API縺ｮ繝｢繝・Ν縺ｨ繧ｨ繝ｳ繝峨・繧､繝ｳ繝・        $model = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $apiKey = getenv('OPENAI_API_KEY');
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        
        error_log("Improving article with OpenAI API. Model: " . $model);
        
        // 險倅ｺ区悽譁・°繧・p>髢｢騾｣縺ｮ螟｢</p>莉･髯阪ｒ髯､螟悶＠縲√Μ繝ｩ繧､繝亥ｯｾ雎｡螟悶→縺吶ｋ
        $contentToRewrite = $content;
        $preservedContent = '';
        
        // <p>髢｢騾｣縺ｮ螟｢</p>繧貞性繧蝣ｴ蜷医√◎縺ｮ驛ｨ蛻・〒蛻・牡
        if (preg_match('/(<p>\s*髢｢騾｣縺ｮ螟｢\s*<\/p>.*)/is', $content, $matches)) {
            $contentToRewrite = str_replace($matches[1], '', $content);
            $preservedContent = $matches[1];
            error_log("Found related dream section, preserving it for later: " . substr($preservedContent, 0, 100) . "...");
        }
        
        // 繝ｪ繝ｩ繧､繝亥ｯｾ雎｡縺ｮ繧ｳ繝ｳ繝・Φ繝・′髟ｷ縺吶℃繧句ｴ蜷医・蛻・ｊ隧ｰ繧√ｋ
        if (strlen($contentToRewrite) > 5000) {
            error_log("Content to rewrite is too long, truncating to 5000 characters for API request");
            $contentToRewrite = substr($contentToRewrite, 0, 5000) . "...";
        }
        
        // 蜈・・繧ｳ繝ｳ繝・Φ繝・ｒ繝ｪ繝ｩ繧､繝亥ｯｾ雎｡縺ｮ繧ｳ繝ｳ繝・Φ繝・↓鄂ｮ縺肴鋤縺医ｋ
        $content = $contentToRewrite;
        
        // 蝠城｡檎せ縺ｮ繝ｪ繧ｹ繝・        $issuesList = '';
        if (!empty($issues)) {
            if (is_array($issues)) {
                $issuesList = "蝠城｡檎せ:\n" . implode("\n", $issues);
            } else {
                $issuesList = "蝠城｡檎せ:\n" . $issues;
            }
        }
        
        // 繧ｳ繝ｳ繝・Φ繝・ｒ繧ｯ繝ｪ繝ｼ繝ｳ繧｢繝・・縺励※迚ｹ谿頑枚蟄励ｒ蜃ｦ逅・        $cleanTitle = mb_convert_encoding($title, 'UTF-8', 'auto');
        $cleanDescription = mb_convert_encoding($description, 'UTF-8', 'auto');
        $cleanContent = mb_convert_encoding($content, 'UTF-8', 'auto');
        
        // 迚ｹ谿頑枚蟄励ｒ繧ｨ繧ｹ繧ｱ繝ｼ繝励＠縺ｦJSON繧ｨ繝ｩ繝ｼ繧帝亟豁｢
        $cleanTitle = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanTitle);
        $cleanDescription = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanDescription);
        $cleanContent = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanContent);
        
        // API繝ｪ繧ｯ繧ｨ繧ｹ繝医ョ繝ｼ繧ｿ
        $systemContent = '莉･荳九・險倅ｺ九ｒ螟ｧ蟷・↓謾ｹ蝟・＠縺ｦ縺上□縺輔＞縲４EO逧・↓譛驕ｩ蛹悶＠縲∬ｪｭ縺ｿ繧・☆縺上・ｭ・鴨逧・↑蜀・ｮｹ縺ｫ縺励※縺上□縺輔＞縲・
蜈ｷ菴鍋噪縺ｪ隕∽ｻｶ・・1. 繧ｿ繧､繝医Ν縺ｯ蠢・★蜈・・繧ｿ繧､繝医Ν縺ｨ縺ｯ逡ｰ縺ｪ繧九ｂ縺ｮ縺ｫ縺励・0-40譁・ｭ礼ｨ句ｺｦ縺ｧSEO逧・↓譛驕ｩ蛹悶☆繧・
2. 繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｯ髱槫ｸｸ縺ｫ驥崎ｦ√〒縺吶ゆｻ･荳九・轤ｹ縺ｫ豕ｨ諢上＠縺ｦ菴懈・縺励※縺上□縺輔＞・・   - 蠢・★蜈・・繧ゅ・縺ｨ縺ｯ螳悟・縺ｫ逡ｰ縺ｪ繧句・螳ｹ縺ｫ縺吶ｋ
   - 120-140譁・ｭ礼ｨ句ｺｦ縺ｧ繧ｯ繝ｪ繝・け邇・ｒ鬮倥ａ繧句・螳ｹ縺ｫ縺吶ｋ
   - 螳溽畑逧・↑諠・ｱ繧・・菴鍋噪縺ｪ繝｡繝ｪ繝・ヨ繧貞性繧√ｋ
   - 縲瑚ｧ｣隱ｬ縺励∪縺吶阪悟・譫舌＠縺ｾ縺吶阪↑縺ｩ縺ｮ螳壼梛逧・↑陦ｨ迴ｾ繧帝∩縺代ｋ
   - 繝ｦ繝ｼ繧ｶ繝ｼ縺ｮ諠・ｱ謗｢邏｢諢乗ｬｲ繧貞絢豼縺吶ｋ陦ｨ迴ｾ繧剃ｽｿ縺・   - 繧ｭ繝ｼ繝ｯ繝ｼ繝峨ｒ閾ｪ辟ｶ縺ｫ驟咲ｽｮ縺吶ｋ
   - 蠢・ｦ√↓蠢懊§縺ｦ謨ｰ蟄励ｄ迚ｹ蠕ｴ逧・↑陦ｨ迴ｾ繧貞・繧後ｋ

3. 險倅ｺ区悽譁・・8000譁・ｭ嶺ｻ･荳翫〒縲∬ｪｭ閠・′豎ゅａ繧区ュ蝣ｱ繧堤ｶｲ鄒・噪縺ｫ謠蝉ｾ帙☆繧・4. 隕句・縺励・h2縲”3縲”4縲”5縺ｮHTML繧ｿ繧ｰ繧剃ｽｿ逕ｨ縺励∵ｮｵ關ｽ縺ｯp繧ｿ繧ｰ繧剃ｽｿ逕ｨ縺吶ｋ
5. 繧ｭ繝ｼ繝ｯ繝ｼ繝峨ｒ閾ｪ辟ｶ縺ｫ驟咲ｽｮ縺励ヾEO蜉ｹ譫懊ｒ鬮倥ａ繧・6. 髢｢騾｣繝壹・繧ｸ縺ｮ邏ｹ莉九・蜷ｫ繧√↑縺・〒縺上□縺輔＞

驥崎ｦ・ｼ・- 險倅ｺ区悽譁・・HTML蠖｢蠑上〒蜃ｺ蜉帙＠縺ｦ縺上□縺輔＞縲りｦ句・縺励・h2縲”3縲”4縲”5繧ｿ繧ｰ縲∵ｮｵ關ｽ縺ｯp繧ｿ繧ｰ繧剃ｽｿ逕ｨ縺励※縺上□縺輔＞縲・- 繧ｿ繧､繝医Ν縺ｨ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｯ蠢・★蜈・・繧ゅ・縺九ｉ螟画峩縺励※縺上□縺輔＞縲ゅ・繝ｬ繝ｼ繧ｹ繝帙Ν繝繝ｼ縺ｮ縺ｾ縺ｾ縺ｫ縺励↑縺・〒縺上□縺輔＞縲・- 繝槭・繧ｯ繝繧ｦ繝ｳ蠖｢蠑上・菴ｿ逕ｨ縺励↑縺・〒縺上□縺輔＞縲・- 蜀・ｮｹ縺ｯ螟ｧ蟷・↓諡｡蜈・＠縺ｦ縲∬ｩｳ邏ｰ縺ｪ諠・ｱ繧呈署萓帙＠縺ｦ縺上□縺輔＞縲・;
        
        $userContent = "蜈・・險倅ｺ・\n繧ｿ繧､繝医Ν: {$cleanTitle}\n繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ: {$cleanDescription}\n譛ｬ譁・ {$cleanContent}\n\n{$issuesList}\n\n謾ｹ蝟・＠縺溯ｨ倅ｺ九ｒ莉･荳九・繝輔か繝ｼ繝槭ャ繝医〒蜃ｺ蜉帙＠縺ｦ縺上□縺輔＞・喀n繧ｿ繧､繝医Ν: [謾ｹ蝟・＆繧後◆繧ｿ繧､繝医Ν]\n繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ: [謾ｹ蝟・＆繧後◆繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ]\n譛ｬ譁・ [謾ｹ蝟・＆繧後◆譛ｬ譁Ⅹ";
        
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
            'temperature' => 0.8, // 蜑ｵ騾諤ｧ繧帝ｫ倥ａ繧・            'max_tokens' => 8000 // 繝医・繧ｯ繝ｳ謨ｰ繧貞｢励ｄ縺励※繧医ｊ髟ｷ譁・↓蟇ｾ蠢・        ];
        
        // JSON繧ｨ繝ｳ繧ｳ繝ｼ繝峨ｒ繝・ヰ繝・げ
        $jsonData = json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON繧ｨ繝ｳ繧ｳ繝ｼ繝峨お繝ｩ繝ｼ: " . json_last_error_msg());
            throw new Exception("JSON繧ｨ繝ｳ繧ｳ繝ｼ繝峨お繝ｩ繝ｼ: " . json_last_error_msg());
        }
        
        // API繝ｪ繧ｯ繧ｨ繧ｹ繝医ｒ螳溯｡・        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 繧ｿ繧､繝繧｢繧ｦ繝医ｒ120遘偵↓險ｭ螳・        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL險ｼ譏取嶌縺ｮ讀懆ｨｼ繧偵せ繧ｭ繝・・
        
        error_log("Sending request to OpenAI API");
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            error_log("cURL error: " . $curlError);
            curl_close($ch);
            throw new Exception("API繝ｪ繧ｯ繧ｨ繧ｹ繝医お繝ｩ繝ｼ: " . $curlError);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("OpenAI API response code: " . $httpCode);
        
        if ($httpCode != 200) {
            error_log("HTTP error in OpenAI API call: " . $httpCode);
            error_log("Response: " . substr($response, 0, 1000));
            throw new Exception("API繝ｬ繧ｹ繝昴Φ繧ｹ繧ｨ繝ｩ繝ｼ (HTTP {$httpCode})");
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            error_log("Failed to parse OpenAI response: " . substr($response, 0, 1000));
            throw new Exception("API繝ｬ繧ｹ繝昴Φ繧ｹ縺ｮ隗｣譫舌↓螟ｱ謨励＠縺ｾ縺励◆");
        }
        
        $improvedContent = $result['choices'][0]['message']['content'];
        error_log("Successfully received improved content from OpenAI");
        
        // 謾ｹ蝟・＆繧後◆蜀・ｮｹ繧定ｧ｣譫・        $improvedTitle = $title; // 繝・ヵ繧ｩ繝ｫ繝医・蜈・・繧ｿ繧､繝医Ν
        $improvedDescription = $description; // 繝・ヵ繧ｩ繝ｫ繝医・蜈・・隱ｬ譏・        $improvedBody = $content; // 繝・ヵ繧ｩ繝ｫ繝医・蜈・・譛ｬ譁・        
        // 繧ｿ繧､繝医Ν縺ｮ謚ｽ蜃ｺ
        if (preg_match('/繧ｿ繧､繝医Ν:\s*(.+?)(?=\n繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:|$)/s', $improvedContent, $titleMatches)) {
            $extractedTitle = trim($titleMatches[1]);
            if (!empty($extractedTitle) && $extractedTitle !== '[謾ｹ蝟・＆繧後◆繧ｿ繧､繝医Ν]') {
                // 蜈・・繧ｿ繧､繝医Ν縺ｨ蜷後§蝣ｴ蜷医・縲∝､画峩縺輔ｌ縺ｦ縺・↑縺・→蛻､譁ｭ
                if ($extractedTitle === $title) {
                    // 蜈・・繧ｿ繧､繝医Ν縺ｫ繧ｭ繝ｼ繝ｯ繝ｼ繝峨ｒ霑ｽ蜉縺励※螟画峩縺吶ｋ
                    $keywords = ['諢丞袖', '隗｣驥・, '螟｢蜊縺・, '螟｢險ｺ譁ｭ', '繧ｷ繝ｳ繝懊Ν', '證ｦ遉ｾ', '螟｢蜊陦・];
                    $randomKeyword = $keywords[array_rand($keywords)];
                    $improvedTitle = $title . ' | ' . $randomKeyword;
                    error_log("Title was not changed, adding keyword: " . $randomKeyword);
                } else {
                    $improvedTitle = $extractedTitle;
                    error_log("Extracted improved title: " . substr($improvedTitle, 0, 50));
                }
            } else {
                // 繝励Ξ繝ｼ繧ｹ繝帙Ν繝繝ｼ縺ｮ縺ｾ縺ｾ縺ｮ蝣ｴ蜷医∝・縺ｮ繧ｿ繧､繝医Ν繧呈隼蝟・                $keywords = ['諢丞袖縺ｨ隗｣驥・, '螟｢蜊縺・・譫・, '螟｢險ｺ譁ｭ繧ｬ繧､繝・, '繧ｷ繝ｳ繝懊Ν縺ｮ諢丞袖', '螟｢蜊陦灘ｮ悟・隗｣隱ｬ'];
                $randomKeyword = $keywords[array_rand($keywords)];
                $improvedTitle = $title . ' | ' . $randomKeyword;
                error_log("Title placeholder detected, creating new title: " . $improvedTitle);
            }
        } else {
            // 繧ｿ繧､繝医Ν縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医∝・縺ｮ繧ｿ繧､繝医Ν繧呈隼蝟・            $keywords = ['諢丞袖縺ｨ隗｣驥・, '螟｢蜊縺・・譫・, '螟｢險ｺ譁ｭ繧ｬ繧､繝・, '繧ｷ繝ｳ繝懊Ν縺ｮ諢丞袖', '螟｢蜊陦灘ｮ悟・隗｣隱ｬ'];
            $randomKeyword = $keywords[array_rand($keywords)];
            $improvedTitle = $title . ' | ' . $randomKeyword;
            error_log("No title found in response, creating new title: " . $improvedTitle);
        }
        
        // 繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｮ謚ｽ蜃ｺ
        if (preg_match('/繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:\s*(.+?)(?=\n譛ｬ譁・|$)/s', $improvedContent, $descMatches)) {
            $extractedDesc = trim($descMatches[1]);
            if (!empty($extractedDesc) && $extractedDesc !== '[謾ｹ蝟・＆繧後◆繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ]') {
                // 蜈・・繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｨ蜷後§蝣ｴ蜷医・縲∝､画峩縺輔ｌ縺ｦ縺・↑縺・→蛻､譁ｭ
                if ($extractedDesc === $description) {
                    // 鬮伜刀雉ｪ縺ｪ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ繝・Φ繝励Ξ繝ｼ繝医°繧蛾∈謚・                    $descriptionTemplates = [
                        "縺ゅ↑縺溘・隕九◆縲鶏$title}縲阪↓髢｢縺吶ｋ螟｢縺ｫ縺ｯ縲∵э螟悶↑蠢・炊逧・э蜻ｳ縺後≠繧九°繧ゅ＠繧後∪縺帙ｓ縲らｲｾ逾槫・譫仙ｭｦ縺ｫ蝓ｺ縺･縺・縺､縺ｮ隗｣驥医→縲√≠縺ｪ縺溘・螟｢縺檎､ｺ縺吝ｰ・擂縺ｮ證ｦ遉ｾ繧呈少遉ｺ縺励∪縺吶・,
                        "縲鶏$title}縲阪・螟｢縺ｯ縲√≠縺ｪ縺溘・貎懷惠諢剰ｭ倥°繧峨・驥崎ｦ√↑繝｡繝・そ繝ｼ繧ｸ縺九ｂ縺励ｌ縺ｾ縺帙ｓ縲ょ､｢蜊縺・・蟆る摩螳ｶ縺梧・縺九☆縲√％縺ｮ螟｢縺ｫ遘倥ａ繧峨ｌ縺・縺､縺ｮ逵溷ｮ溘→縲√≠縺ｪ縺溘・莠ｺ逕溘∈縺ｮ蠖ｱ髻ｿ繧呈爾繧翫∪縺吶・,
                        "螟懊↓隕九◆縲鶏$title}縲阪・螟｢縺ｯ蜊倥↑繧句・辟ｶ縺ｧ縺ｯ縺ゅｊ縺ｾ縺帙ｓ縲ょ商莉｣縺九ｉ莨昴ｏ繧句､｢蜊縺・・遏･諱ｵ縺ｨ譛譁ｰ縺ｮ蠢・炊蟄ｦ遐皮ｩｶ縺九ｉ縲√≠縺ｪ縺溘・螟｢縺梧囓遉ｺ縺吶ｋ鬩壹￥縺ｹ縺咲悄螳溘ｒ隗｣隱ｬ縺励∪縺吶・,
                        "縲鶏$title}縲阪・螟｢繧定ｦ九◆縺薙→縺後≠繧翫∪縺吶°・溘％縺ｮ螟｢縺ｫ髫縺輔ｌ縺・0縺ｮ繧ｷ繝ｳ繝懊Ν縺ｨ縺昴・諢丞袖繧堤衍繧後・縲√≠縺ｪ縺溘・莠ｺ逕溘↓螟ｧ縺阪↑螟牙喧繧偵ｂ縺溘ｉ縺吶°繧ゅ＠繧後∪縺帙ｓ縲・,
                        "螟｢縺ｫ蜃ｺ縺ｦ縺上ｋ縲鶏$title}縲阪↓縺ｯ縲√≠縺ｪ縺溘′遏･繧峨↑縺・ｩ壹￥縺ｹ縺肴э蜻ｳ縺後≠繧翫∪縺吶ょ､｢蜊縺・・蟆る摩螳ｶ縺梧蕗縺医ｋ縲√％縺ｮ螟｢縺檎､ｺ縺呎ｽ懷惠諢剰ｭ倥°繧峨・繝｡繝・そ繝ｼ繧ｸ縺ｨ縲√≠縺ｪ縺溘・莠ｺ逕溘∈縺ｮ蠖ｱ髻ｿ繧呈爾繧翫∪縺吶・
                    ];
                    
                    $improvedDescription = $descriptionTemplates[array_rand($descriptionTemplates)];
                    
                    // 140譁・ｭ励↓蛻ｶ髯・                    if (mb_strlen($improvedDescription) > 140) {
                        $improvedDescription = mb_substr($improvedDescription, 0, 137) . '...';
                    }
                    error_log("Description was not changed, creating high-quality alternative");
                } else {
                    $improvedDescription = $extractedDesc;
                    error_log("Extracted improved description: " . substr($improvedDescription, 0, 50));
                }
            } else {
                // 繝励Ξ繝ｼ繧ｹ繝帙Ν繝繝ｼ縺ｮ縺ｾ縺ｾ縺ｮ蝣ｴ蜷医・ｫ伜刀雉ｪ縺ｪ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ繧堤函謌・                // 隍・焚縺ｮ繝舌Μ繧ｨ繝ｼ繧ｷ繝ｧ繝ｳ縺九ｉ繝ｩ繝ｳ繝繝縺ｫ驕ｸ謚・                $descriptionTemplates = [
                    "縺ゅ↑縺溘・隕九◆縲鶏$title}縲阪↓髢｢縺吶ｋ螟｢縺ｫ縺ｯ縲∵э螟悶↑蠢・炊逧・э蜻ｳ縺後≠繧九°繧ゅ＠繧後∪縺帙ｓ縲らｲｾ逾槫・譫仙ｭｦ縺ｫ蝓ｺ縺･縺・縺､縺ｮ隗｣驥医→縲√≠縺ｪ縺溘・螟｢縺檎､ｺ縺吝ｰ・擂縺ｮ證ｦ遉ｾ繧呈少遉ｺ縺励∪縺吶・,
                    "縲鶏$title}縲阪・螟｢縺ｯ縲√≠縺ｪ縺溘・貎懷惠諢剰ｭ倥°繧峨・驥崎ｦ√↑繝｡繝・そ繝ｼ繧ｸ縺九ｂ縺励ｌ縺ｾ縺帙ｓ縲ょ､｢蜊縺・・蟆る摩螳ｶ縺梧・縺九☆縲√％縺ｮ螟｢縺ｫ遘倥ａ繧峨ｌ縺・縺､縺ｮ逵溷ｮ溘→縲√≠縺ｪ縺溘・莠ｺ逕溘∈縺ｮ蠖ｱ髻ｿ繧呈爾繧翫∪縺吶・,
                    "螟懊↓隕九◆縲鶏$title}縲阪・螟｢縺ｯ蜊倥↑繧句・辟ｶ縺ｧ縺ｯ縺ゅｊ縺ｾ縺帙ｓ縲ょ商莉｣縺九ｉ莨昴ｏ繧句､｢蜊縺・・遏･諱ｵ縺ｨ譛譁ｰ縺ｮ蠢・炊蟄ｦ遐皮ｩｶ縺九ｉ縲√≠縺ｪ縺溘・螟｢縺梧囓遉ｺ縺吶ｋ鬩壹￥縺ｹ縺咲悄螳溘ｒ隗｣隱ｬ縺励∪縺吶・,
                    "縲鶏$title}縲阪・螟｢繧定ｦ九◆縺薙→縺後≠繧翫∪縺吶°・溘％縺ｮ螟｢縺ｫ髫縺輔ｌ縺・0縺ｮ繧ｷ繝ｳ繝懊Ν縺ｨ縺昴・諢丞袖繧堤衍繧後・縲√≠縺ｪ縺溘・莠ｺ逕溘↓螟ｧ縺阪↑螟牙喧繧偵ｂ縺溘ｉ縺吶°繧ゅ＠繧後∪縺帙ｓ縲ょ､｢險ｺ譁ｭ縺ｮ蟆る摩螳ｶ縺悟､｢縺ｮ逵溽嶌繧定ｧ｣隱ｬ縺励∪縺吶・,
                    "螟｢縺ｫ蜃ｺ縺ｦ縺上ｋ縲鶏$title}縲阪↓縺ｯ縲√≠縺ｪ縺溘′遏･繧峨↑縺・ｩ壹￥縺ｹ縺肴э蜻ｳ縺後≠繧翫∪縺吶ょ､｢蜊縺・・蟆る摩螳ｶ縺梧蕗縺医ｋ縲√％縺ｮ螟｢縺檎､ｺ縺呎ｽ懷惠諢剰ｭ倥°繧峨・繝｡繝・そ繝ｼ繧ｸ縺ｨ縲√≠縺ｪ縺溘・莠ｺ逕溘∈縺ｮ蠖ｱ髻ｿ繧呈爾繧翫∪縺吶・
                ];
                
                $improvedDescription = $descriptionTemplates[array_rand($descriptionTemplates)];
                
                // 140譁・ｭ励↓蛻ｶ髯・                if (mb_strlen($improvedDescription) > 140) {
                    $improvedDescription = mb_substr($improvedDescription, 0, 137) . '...';
                }
                error_log("Description placeholder detected, creating high-quality description");
            }
        } else {
            // 繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医・ｫ伜刀雉ｪ縺ｪ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ繧堤函謌・            // 隍・焚縺ｮ繝舌Μ繧ｨ繝ｼ繧ｷ繝ｧ繝ｳ縺九ｉ繝ｩ繝ｳ繝繝縺ｫ驕ｸ謚・            $descriptionTemplates = [
                "縺ゅ↑縺溘・隕九◆縲鶏$title}縲阪↓髢｢縺吶ｋ螟｢縺ｫ縺ｯ縲∵э螟悶↑蠢・炊逧・э蜻ｳ縺後≠繧九°繧ゅ＠繧後∪縺帙ｓ縲らｲｾ逾槫・譫仙ｭｦ縺ｫ蝓ｺ縺･縺・縺､縺ｮ隗｣驥医→縲√≠縺ｪ縺溘・螟｢縺檎､ｺ縺吝ｰ・擂縺ｮ證ｦ遉ｾ繧呈少遉ｺ縺励∪縺吶・,
                "縲鶏$title}縲阪・螟｢縺ｯ縲√≠縺ｪ縺溘・貎懷惠諢剰ｭ倥°繧峨・驥崎ｦ√↑繝｡繝・そ繝ｼ繧ｸ縺九ｂ縺励ｌ縺ｾ縺帙ｓ縲ょ､｢蜊縺・・蟆る摩螳ｶ縺梧・縺九☆縲√％縺ｮ螟｢縺ｫ遘倥ａ繧峨ｌ縺・縺､縺ｮ逵溷ｮ溘→縲√≠縺ｪ縺溘・莠ｺ逕溘∈縺ｮ蠖ｱ髻ｿ繧呈爾繧翫∪縺吶・,
                "螟懊↓隕九◆縲鶏$title}縲阪・螟｢縺ｯ蜊倥↑繧句・辟ｶ縺ｧ縺ｯ縺ゅｊ縺ｾ縺帙ｓ縲ょ商莉｣縺九ｉ莨昴ｏ繧句､｢蜊縺・・遏･諱ｵ縺ｨ譛譁ｰ縺ｮ蠢・炊蟄ｦ遐皮ｩｶ縺九ｉ縲√≠縺ｪ縺溘・螟｢縺梧囓遉ｺ縺吶ｋ鬩壹￥縺ｹ縺咲悄螳溘ｒ隗｣隱ｬ縺励∪縺吶・,
                "縲鶏$title}縲阪・螟｢繧定ｦ九◆縺薙→縺後≠繧翫∪縺吶°・溘％縺ｮ螟｢縺ｫ髫縺輔ｌ縺・0縺ｮ繧ｷ繝ｳ繝懊Ν縺ｨ縺昴・諢丞袖繧堤衍繧後・縲√≠縺ｪ縺溘・莠ｺ逕溘↓螟ｧ縺阪↑螟牙喧繧偵ｂ縺溘ｉ縺吶°繧ゅ＠繧後∪縺帙ｓ縲ょ､｢險ｺ譁ｭ縺ｮ蟆る摩螳ｶ縺悟､｢縺ｮ逵溽嶌繧定ｧ｣隱ｬ縺励∪縺吶・,
                "螟｢縺ｫ蜃ｺ縺ｦ縺上ｋ縲鶏$title}縲阪↓縺ｯ縲√≠縺ｪ縺溘′遏･繧峨↑縺・ｩ壹￥縺ｹ縺肴э蜻ｳ縺後≠繧翫∪縺吶ょ､｢蜊縺・・蟆る摩螳ｶ縺梧蕗縺医ｋ縲√％縺ｮ螟｢縺檎､ｺ縺呎ｽ懷惠諢剰ｭ倥°繧峨・繝｡繝・そ繝ｼ繧ｸ縺ｨ縲√≠縺ｪ縺溘・莠ｺ逕溘∈縺ｮ蠖ｱ髻ｿ繧呈爾繧翫∪縺吶・
            ];
            
            $improvedDescription = $descriptionTemplates[array_rand($descriptionTemplates)];
            
            // 140譁・ｭ励↓蛻ｶ髯・            if (mb_strlen($improvedDescription) > 140) {
                $improvedDescription = mb_substr($improvedDescription, 0, 137) . '...';
            }
            error_log("No description found in response, creating high-quality description");
        }
        
        // 譛ｬ譁・・謚ｽ蜃ｺ
        if (preg_match('/譛ｬ譁・\s*(.+?)$/s', $improvedContent, $bodyMatches)) {
            $extractedBody = trim($bodyMatches[1]);
            if (!empty($extractedBody)) {
                // 繝槭・繧ｯ繝繧ｦ繝ｳ縺九ｉHTML縺ｸ縺ｮ螟画鋤蜃ｦ逅・                $improvedBody = $extractedBody;
                
                // HTML繧ｿ繧ｰ繧・ｸ崎ｦ√↑隕∫ｴ繧帝勁蜴ｻ縺吶ｋ
                // <title>縲・meta>繧ｿ繧ｰ繧帝勁蜴ｻ
                $improvedBody = preg_replace('/<title>.*?<\/title>/is', '', $improvedBody);
                $improvedBody = preg_replace('/<meta[^>]*>/is', '', $improvedBody);
                
                // <body>繧ｿ繧ｰ縺ｮ荳ｭ霄ｫ縺縺代ｒ謚ｽ蜃ｺ
                if (preg_match('/<body>(.*?)<\/body>/is', $improvedBody, $bodyContentMatches)) {
                    $improvedBody = trim($bodyContentMatches[1]);
                } else {
                    // <body>繧ｿ繧ｰ縺後↑縺・ｴ蜷医・縲√ち繧､繝医Ν縺ｨ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｮ陦後ｒ髯､蜴ｻ
                    $improvedBody = preg_replace('/^繧ｿ繧､繝医Ν:.+?\n/m', '', $improvedBody);
                    $improvedBody = preg_replace('/^繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:.+?\n/m', '', $improvedBody);
                }
                
                // 髢｢騾｣繝壹・繧ｸ縺ｮ邏ｹ莉九ｒ蜑企勁
                $improvedBody = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣縺吶ｋ莉悶・螟｢\s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $improvedBody);
                $improvedBody = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣繝壹・繧ｸ\s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $improvedBody);
                $improvedBody = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣險倅ｺ欺s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $improvedBody);
                $improvedBody = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣繝ｪ繝ｳ繧ｯ\s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $improvedBody);
                
                // 繝ｪ繝ｳ繧ｯ莉倥″縺ｮ繝ｪ繧ｹ繝医ｒ蜑企勁
                $improvedBody = preg_replace('/<ul>\s*<li>\s*<a href=[^>]*>[^<]*<\/a>\s*<\/li>\s*<\/ul>/is', '', $improvedBody);
                
                // div.article_element繧貞炎髯､
                $improvedBody = preg_replace('/<div class="article_element[^"]*".*?<\/div>\s*<\/div>/is', '', $improvedBody);
                
                // 繝槭・繧ｯ繝繧ｦ繝ｳ蠖｢蠑上・隕句・縺励ｒHTML縺ｫ螟画鋤
                $improvedBody = convertMarkdownToHtml($improvedBody);
                
                // 菫晏ｭ倥＠縺ｦ縺翫＞縺・p>髢｢騾｣縺ｮ螟｢</p>莉･髯阪・繧ｳ繝ｳ繝・Φ繝・ｒ蜈・↓謌ｻ縺・                if (!empty($preservedContent)) {
                    $improvedBody .= $preservedContent;
                    error_log("Appended preserved content back to the improved body");
                }
                
                error_log("Extracted and cleaned improved body: length=" . strlen($improvedBody));
            }
        } else {
            // 譛ｬ譁・ち繧ｰ縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医・縲√ヵ繧ｩ繝ｼ繝槭ャ繝医′逡ｰ縺ｪ繧句庄閭ｽ諤ｧ縺後≠繧九・縺ｧ蜈ｨ菴薙ｒ譛ｬ譁・→縺励※謇ｱ縺・            error_log("Body tag not found, using entire content as body");
            
            // HTML繧ｿ繧ｰ繧・ｸ崎ｦ√↑隕∫ｴ繧帝勁蜴ｻ
            $cleanedContent = $improvedContent;
            
            // <title>縲・meta>繧ｿ繧ｰ繧帝勁蜴ｻ
            $cleanedContent = preg_replace('/<title>.*?<\/title>/is', '', $cleanedContent);
            $cleanedContent = preg_replace('/<meta[^>]*>/is', '', $cleanedContent);
            
            // <body>繧ｿ繧ｰ縺ｮ荳ｭ霄ｫ縺縺代ｒ謚ｽ蜃ｺ
            if (preg_match('/<body>(.*?)<\/body>/is', $cleanedContent, $bodyContentMatches)) {
                $cleanedContent = trim($bodyContentMatches[1]);
            } else {
                // <body>繧ｿ繧ｰ縺後↑縺・ｴ蜷医・縲√ち繧､繝医Ν縺ｨ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｮ陦後ｒ髯､蜴ｻ
                $cleanedContent = preg_replace('/^繧ｿ繧､繝医Ν:.+?\n/m', '', $cleanedContent);
                $cleanedContent = preg_replace('/^繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:.+?\n/m', '', $cleanedContent);
            }
            
            // 髢｢騾｣繝壹・繧ｸ縺ｮ邏ｹ莉九ｒ蜑企勁
            $cleanedContent = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣縺吶ｋ莉悶・螟｢\s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $cleanedContent);
            $cleanedContent = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣繝壹・繧ｸ\s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $cleanedContent);
            $cleanedContent = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣險倅ｺ欺s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $cleanedContent);
            $cleanedContent = preg_replace('/<h[2-3][^>]*>\s*髢｢騾｣繝ｪ繝ｳ繧ｯ\s*<\/h[2-3]>.*?(<h[2-3]|$)/is', '$1', $cleanedContent);
            
            // 繝ｪ繝ｳ繧ｯ莉倥″縺ｮ繝ｪ繧ｹ繝医ｒ蜑企勁
            $cleanedContent = preg_replace('/<ul>\s*<li>\s*<a href=[^>]*>[^<]*<\/a>\s*<\/li>\s*<\/ul>/is', '', $cleanedContent);
            
            // div.article_element繧貞炎髯､
            $cleanedContent = preg_replace('/<div class="article_element[^"]*".*?<\/div>\s*<\/div>/is', '', $cleanedContent);
            
            $improvedBody = convertMarkdownToHtml($cleanedContent);
            
            // 菫晏ｭ倥＠縺ｦ縺翫＞縺・p>髢｢騾｣縺ｮ螟｢</p>莉･髯阪・繧ｳ繝ｳ繝・Φ繝・ｒ蜈・↓謌ｻ縺・            if (!empty($preservedContent)) {
                $improvedBody .= $preservedContent;
                error_log("Appended preserved content back to the improved body");
            }
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
            'content' => "<h2>繧ｨ繝ｩ繝ｼ</h2><p>險倅ｺ九・謾ｹ蝟・ｸｭ縺ｫ繧ｨ繝ｩ繝ｼ縺檎匱逕溘＠縺ｾ縺励◆: " . $e->getMessage() . "</p>",
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 蜈・・險倅ｺ九ョ繝ｼ繧ｿ繧貞叙蠕励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @param string $url 蟇ｾ雎｡縺ｮURL
 * @return array|null 蜈・・險倅ｺ九ョ繝ｼ繧ｿ・医ち繧､繝医Ν縲√ョ繧｣繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縲∵悽譁・ｼ峨∪縺溘・null
 */
function getOriginalArticle($sheetData, $url) {
    error_log("Searching for original article data for URL: " . $url);
    
    // 蜃ｦ逅・凾髢薙・蛻ｶ髯舌ｒ險ｭ螳夲ｼ域怙螟ｧ螳溯｡梧凾髢薙ｒ180遘偵↓險ｭ螳夲ｼ・    set_time_limit(180);
    
    foreach ($sheetData as $rowIndex => $row) {
        if ($row[0] === $url) {
            error_log("Found URL match at row index: " . $rowIndex);
            
            // 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・蛻玲ｧ矩
            // A蛻暦ｼ・row[0]・会ｼ啅RL
            // B蛻暦ｼ・row[1]・会ｼ壹け繝ｪ繝・け謨ｰ
            // C蛻暦ｼ・row[2]・会ｼ夊｡ｨ遉ｺ蝗樊焚
            // D蛻暦ｼ・row[3]・会ｼ咾TR
            // E蛻暦ｼ・row[4]・会ｼ壽軸霈蛾・ｽ肴焚
            // F蛻暦ｼ・row[5]・会ｼ夂ｩｺ谺・            
            // 蜈・ｨ倅ｺ九√ち繧､繝医Ν縲√ョ繧｣繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｯ縺吶∋縺ｦURL縺九ｉ蜿門ｾ励☆繧句ｿ・ｦ√′縺ゅｋ
            error_log("Fetching article content from URL: " . $url);
            
            $title = '';
            $description = '';
            $content = '';
            
            try {
                // cURL繧剃ｽｿ逕ｨ縺励※URL縺九ｉ繧ｳ繝ｳ繝・Φ繝・ｒ蜿門ｾ・                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 繝ｪ繝繧､繝ｬ繧ｯ繝医ｒ霑ｽ霍｡
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL險ｼ譏取嶌縺ｮ讀懆ｨｼ繧偵せ繧ｭ繝・・
                curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 繧ｿ繧､繝繧｢繧ｦ繝郁ｨｭ螳壹ｒ遏ｭ縺・                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'); // User-Agent繧定ｨｭ螳・                $originalContent = curl_exec($ch);
                
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
                
                // DOM繧剃ｽｿ逕ｨ縺励※迚ｹ螳壹・隕∫ｴ繧呈歓蜃ｺ
                $dom = new DOMDocument();
                @$dom->loadHTML(mb_convert_encoding($originalContent, 'HTML-ENTITIES', 'UTF-8'));
                $xpath = new DOMXPath($dom);
                
                // 險倅ｺ九ち繧､繝医Ν繧檀1.article_title縺九ｉ蜿門ｾ・                $titleNodes = $xpath->query('//h1[contains(@class, "article_title")]');
                if ($titleNodes->length > 0) {
                    $title = trim($titleNodes->item(0)->textContent);
                    error_log("Extracted title from h1.article_title: " . $title);
                }
                
                // 險倅ｺ区悽譁・ｒ.article_body縺九ｉ蜿門ｾ励＠縲∵悽譁・・縺ｿ繧呈歓蜃ｺ
                $bodyNodes = $xpath->query('//*[contains(@class, "article_body")]');
                if ($bodyNodes->length > 0) {
                    $bodyNode = $bodyNodes->item(0);
                    // 險倅ｺ区悽譁・・縺ｿ繧呈歓蜃ｺ縺吶ｋ縺溘ａ縺ｫ縲∝・驛ｨHTML縺縺代ｒ蜿門ｾ・                    $innerContent = '';
                    foreach ($bodyNode->childNodes as $childNode) {
                        $innerContent .= $dom->saveHTML($childNode);
                    }
                    $content = $innerContent;
                    error_log("Extracted content from .article_body (inner content only), length: " . strlen($content));
                }
                
                // 繧ｿ繧､繝医Ν縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医・title繧ｿ繧ｰ縺九ｉ蜿門ｾ・                if (empty($title)) {
                    $titleTags = $xpath->query('//title');
                    if ($titleTags->length > 0) {
                        $title = trim($titleTags->item(0)->textContent);
                        error_log("Falling back to title tag: " . $title);
                    }
                }
                
                // 譛ｬ譁・′隕九▽縺九ｉ縺ｪ縺・ｴ蜷医・body繧ｿ繧ｰ蜈ｨ菴薙ｒ菴ｿ逕ｨ
                if (empty($content)) {
                    $bodyTags = $xpath->query('//body');
                    if ($bodyTags->length > 0) {
                        $content = $dom->saveHTML($bodyTags->item(0));
                        error_log("Falling back to body tag content, length: " . strlen($content));
                    } else {
                        $content = $originalContent;
                        error_log("Using full HTML as content");
                    }
                }
                
                // 繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ繧貞叙蠕励☆繧区婿豕輔ｒ謾ｹ蝟・                
                // 譁ｹ豕・・嗄eta[name="description"]縺九ｉ蜿門ｾ・                $metaNodes = $xpath->query('//meta[@name="description"]');
                if ($metaNodes->length > 0) {
                    $metaNode = $metaNodes->item(0);
                    if ($metaNode instanceof DOMElement) {
                        $description = $metaNode->getAttribute('content');
                        error_log("Extracted meta description from meta tag: " . $description);
                    }
                }
                
                // 譁ｹ豕・・嗄eta[property="og:description"]縺九ｉ蜿門ｾ・                if (empty($description)) {
                    $ogDescNodes = $xpath->query('//meta[@property="og:description"]');
                    if ($ogDescNodes->length > 0) {
                        $ogDescNode = $ogDescNodes->item(0);
                        if ($ogDescNode instanceof DOMElement) {
                            $description = $ogDescNode->getAttribute('content');
                            error_log("Extracted meta description from og:description: " . $description);
                        }
                    }
                }
                
                // 譁ｹ豕・・夊ｨ倅ｺ九・譛蛻昴・谿ｵ關ｽ縺九ｉ逕滓・
                if (empty($description) || $description === $title) {
                    $paragraphs = $xpath->query('//div[contains(@class, "article_body")]//p');
                    if ($paragraphs->length > 0) {
                        $firstPara = $paragraphs->item(0)->textContent;
                        if (strlen($firstPara) > 10) { // 譛菴朱剞縺ｮ髟ｷ縺輔メ繧ｧ繝・け
                            $description = mb_substr(trim($firstPara), 0, 120, 'UTF-8');
                            if (strlen($firstPara) > 120) {
                                $description .= '...';
                            }
                            error_log("Generated description from first paragraph: " . $description);
                        }
                    }
                }
                
            } catch (Exception $e) {
                error_log("Error fetching article: " . $e->getMessage());
                // 繧ｨ繝ｩ繝ｼ縺ｮ蝣ｴ蜷医・繝繝溘・繧ｳ繝ｳ繝・Φ繝・ｒ菴ｿ逕ｨ
                $title = "險倅ｺ句叙蠕励お繝ｩ繝ｼ";
                $description = "URL: {$url} 縺九ｉ縺ｮ險倅ｺ句叙蠕励↓螟ｱ謨励＠縺ｾ縺励◆縲・;
                $content = "<h1>險倅ｺ句叙蠕励お繝ｩ繝ｼ</h1><p>URL: {$url}</p>";
            }
            
            // 繧ｿ繧､繝医Ν縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医・URL縺九ｉ逕滓・
            if (empty($title)) {
                $pathParts = explode('/', parse_url($url, PHP_URL_PATH));
                $lastPart = end($pathParts);
                $title = ucfirst(str_replace(['-', '_'], ' ', $lastPart));
                error_log("Generated title from URL: " . $title);
            }
            
            // 繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医・繧ｿ繧､繝医Ν縺ｨURL縺九ｉ逕滓・
            if (empty($description) || $description === $title) {
                $urlPath = parse_url($url, PHP_URL_PATH);
                $description = $title . ' - ' . str_replace(['-', '_'], ' ', $urlPath);
                $description = mb_substr($description, 0, 120, 'UTF-8');
                error_log("Generated unique description from title and URL: " . $description);
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
 * 譛譁ｰ縺ｮ繝ｪ繝ｩ繧､繝郁ｨ倅ｺ九ョ繝ｼ繧ｿ繧貞叙蠕励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @param string $url 蟇ｾ雎｡縺ｮURL
 * @return array|null 譛譁ｰ縺ｮ繝ｪ繝ｩ繧､繝郁ｨ倅ｺ九ョ繝ｼ繧ｿ・医ち繧､繝医Ν縲√ョ繧｣繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縲∵悽譁・∝撫鬘檎せ縲∵律譎ゑｼ峨∪縺溘・null
 */
function getLatestRewriteData($sheetData, $url) {
    foreach ($sheetData as $row) {
        if ($row[0] === $url) {
            // F蛻嶺ｻ･髯阪・繝・・繧ｿ繧堤｢ｺ隱・            $columnIndex = 5; // F蛻励°繧蛾幕蟋具ｼ・繝吶・繧ｹ縺ｪ縺ｮ縺ｧ5・・            $latestRewriteData = null;
            
            // 繝・・繧ｿ蠖｢蠑上ｒ遒ｺ隱阪＠縺ｦ蜃ｦ逅・ｒ蛻・ｲ・            // 譁ｰ蠖｢蠑擾ｼ壼・縺ｮ險倅ｺ九∝撫鬘檎せ縲∵律譎ゅ√ち繧､繝医Ν縲√ョ繧｣繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縲∵悽譁・・6蛻励そ繝・ヨ
            // 譌ｧ蠖｢蠑擾ｼ壼・縺ｮ險倅ｺ九∝撫鬘檎せ縲∵律譎ゅ∵隼蝟・＆繧後◆險倅ｺ具ｼ・SON・峨・4蛻励そ繝・ヨ
            
            // 譁ｰ蠖｢蠑上・繝・・繧ｿ繧堤｢ｺ隱・            while (isset($row[$columnIndex + 5])) { // 6蛻励そ繝・ヨ縺ｧ遒ｺ隱・                if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                    !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3]) &&
                    !empty($row[$columnIndex + 4]) && !empty($row[$columnIndex + 5])) {
                    
                    // 譛譁ｰ縺ｮ繝ｪ繝ｩ繧､繝医ョ繝ｼ繧ｿ繧呈峩譁ｰ・亥句挨縺ｮ蛟､縺ｨ縺励※蜿門ｾ暦ｼ・                    $latestRewriteData = [
                        'original' => $row[$columnIndex],
                        'issues' => $row[$columnIndex + 1],
                        'datetime' => $row[$columnIndex + 2],
                        'title' => $row[$columnIndex + 3],
                        'description' => $row[$columnIndex + 4],
                        'content' => $row[$columnIndex + 5]
                    ];
                    
                    // 莠呈鋤諤ｧ縺ｮ縺溘ａ縺ｫ譌ｧ蠖｢蠑上・繧ｭ繝ｼ繧りｨｭ螳・                    $latestRewriteData['improved'] = json_encode([
                        'title' => $row[$columnIndex + 3],
                        'description' => $row[$columnIndex + 4],
                        'content' => $row[$columnIndex + 5]
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    
                    $columnIndex += 6; // 谺｡縺ｮ6蛻励そ繝・ヨ縺ｸ
                    continue;
                }
                $columnIndex += 6;
            }
            
            // 譁ｰ蠖｢蠑上・繝・・繧ｿ縺瑚ｦ九▽縺九ｉ縺ｪ縺九▲縺溷ｴ蜷医・縲∵立蠖｢蠑上ｒ遒ｺ隱・            if ($latestRewriteData === null) {
                $columnIndex = 5; // 蜀榊ｺｦF蛻励°繧蛾幕蟋・                
                while (isset($row[$columnIndex + 3])) { // 4蛻励そ繝・ヨ縺ｧ遒ｺ隱・                    if (!empty($row[$columnIndex]) && !empty($row[$columnIndex + 1]) && 
                        !empty($row[$columnIndex + 2]) && !empty($row[$columnIndex + 3])) {
                        
                        // 譛譁ｰ縺ｮ繝ｪ繝ｩ繧､繝医ョ繝ｼ繧ｿ繧呈峩譁ｰ
                        $latestRewriteData = [
                            'original' => $row[$columnIndex],
                            'issues' => $row[$columnIndex + 1],
                            'datetime' => $row[$columnIndex + 2],
                            'improved' => $row[$columnIndex + 3]
                        ];
                        
                        // JSON蠖｢蠑上・謾ｹ蝟・ョ繝ｼ繧ｿ繧定ｧ｣譫舌＠縺ｦ蛟句挨縺ｮ蛟､縺ｨ縺励※險ｭ螳・                        $improvedData = parseImprovedArticleData($row[$columnIndex + 3]);
                        $latestRewriteData['title'] = $improvedData['title'];
                        $latestRewriteData['description'] = $improvedData['description'];
                        $latestRewriteData['content'] = $improvedData['content'];
                    }
                    $columnIndex += 4; // 谺｡縺ｮ4蛻励そ繝・ヨ縺ｸ
                }
            }
            
            return $latestRewriteData;
        }
    }
    
    return null;
}

/**
 * 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・谺｡縺ｮ遨ｺ縺榊・繧貞叙蠕励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @param string $url 蟇ｾ雎｡縺ｮURL
 * @return int 谺｡縺ｮ遨ｺ縺榊・縺ｮ繧､繝ｳ繝・ャ繧ｯ繧ｹ
 */
function getNextEmptyColumn($sheetData, $url) {
    foreach ($sheetData as $rowIndex => $row) {
        if ($row[0] === $url) {
            $columnIndex = 5; // F蛻励°繧蛾幕蟋具ｼ・繝吶・繧ｹ縺ｪ縺ｮ縺ｧ5・・            
            // 譛蠕後・蛻励ｒ謗｢縺・            while (isset($row[$columnIndex])) {
                $columnIndex++;
            }
            
            return $columnIndex;
        }
    }
    
    // URL縺瑚ｦ九▽縺九ｉ縺ｪ縺・ｴ蜷医・F蛻暦ｼ医う繝ｳ繝・ャ繧ｯ繧ｹ5・峨ｒ霑斐☆
    return 5;
}

/**
 * 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・陦後う繝ｳ繝・ャ繧ｯ繧ｹ繧貞叙蠕励☆繧矩未謨ｰ
 * 
 * @param array $sheetData 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・繝・・繧ｿ
 * @param string $url 蟇ｾ雎｡縺ｮURL
 * @return int|null 陦後う繝ｳ繝・ャ繧ｯ繧ｹ縺ｾ縺溘・null
 */
function getRowIndex($sheetData, $url) {
    foreach ($sheetData as $index => $row) {
        if ($row[0] === $url) {
            return $index + 2; // 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医・1繝吶・繧ｹ縲√・繝・ム繝ｼ陦後′縺ゅｋ縺溘ａ+2
        }
    }
    
    return null;
}

/**
 * 繝ｪ繝ｩ繧､繝育ｵ先棡繧偵せ繝励Ξ繝・ラ繧ｷ繝ｼ繝医↓譖ｸ縺崎ｾｼ繧髢｢謨ｰ
 * 
 * @param string $url 蟇ｾ雎｡縺ｮURL
 * @param string $originalContent 蜈・・險倅ｺ句・螳ｹ
 * @param string $issues 蝠城｡檎せ
 * @param array $improvedData 謾ｹ蝟・＆繧後◆險倅ｺ九ョ繝ｼ繧ｿ
 * @return bool 謌仙粥縺励◆縺九←縺・°
 */
function writeRewriteResult($url, $originalContent, $issues, $improvedData) {
    $sheetData = getSheetData();
    $rowIndex = getRowIndex($sheetData, $url);
    
    if ($rowIndex === null) {
        error_log("URL not found in spreadsheet: " . $url);
        return false;
    }
    
    $nextColumn = getNextEmptyColumn($sheetData, $url);
    $columnLetter = chr(65 + $nextColumn); // 蛻礼分蜿ｷ繧偵い繝ｫ繝輔ぃ繝吶ャ繝医↓螟画鋤・・=0, B=1, ...・・    
    // 譖ｸ縺崎ｾｼ繧繝・・繧ｿ
    $datetime = date('Y-m-d H:i:s');
    
    // 繝・・繧ｿ繧ｵ繧､繧ｺ縺ｮ蛻ｶ髯・    // 蜈・・險倅ｺ句・螳ｹ繧貞宛髯・    if (strlen($originalContent) > 10000) {
        error_log("Original content too long, truncating to 10000 characters");
        $originalContent = mb_substr($originalContent, 0, 10000, 'UTF-8') . "...(truncated)";
    }
    
    // 蝠城｡檎せ繧貞宛髯・    if (strlen($issues) > 5000) {
        error_log("Issues content too long, truncating to 5000 characters");
        $issues = mb_substr($issues, 0, 5000, 'UTF-8') . "...(truncated)";
    }
    
    // 謾ｹ蝟・＆繧後◆繝・・繧ｿ縺ｮ蜃ｦ逅・    try {
        // 繧ｿ繧､繝医Ν縲√Γ繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縲∵悽譁・ｒ蛟句挨縺ｫ蜃ｦ逅・        $improvedTitle = isset($improvedData['title']) ? $improvedData['title'] : '';
        $improvedDescription = isset($improvedData['description']) ? $improvedData['description'] : '';
        $improvedContent = isset($improvedData['content']) ? $improvedData['content'] : '';
        
        // 繝・・繧ｿ繧ｵ繧､繧ｺ縺ｮ蛻ｶ髯・        if (strlen($improvedTitle) > 500) {
            $improvedTitle = mb_substr($improvedTitle, 0, 500, 'UTF-8');
            error_log("Improved title truncated to 500 characters");
        }
        
        if (strlen($improvedDescription) > 1000) {
            $improvedDescription = mb_substr($improvedDescription, 0, 1000, 'UTF-8');
            error_log("Improved description truncated to 1000 characters");
        }
        
        if (strlen($improvedContent) > 20000) {
            $improvedContent = mb_substr($improvedContent, 0, 20000, 'UTF-8') . "...(truncated)";
            error_log("Improved content truncated to 20000 characters");
        }
        
        error_log("Prepared improved data for writing to spreadsheet");
        error_log("Title length: " . strlen($improvedTitle));
        error_log("Description length: " . strlen($improvedDescription));
        error_log("Content length: " . strlen($improvedContent));
        
    } catch (Exception $e) {
        error_log('Error preparing improved content: ' . $e->getMessage());
        $improvedTitle = "Error: Failed to process title";
        $improvedDescription = "Error: Failed to process description";
        $improvedContent = "Error: Failed to process content";
    }
    
    // 譖ｸ縺崎ｾｼ繧遽・峇繧定ｨ育ｮ・- 蛟句挨縺ｮ繧ｻ繝ｫ縺ｫ蛻・￠繧九◆繧√↓蛻玲焚繧貞｢励ｄ縺・    $range = $columnLetter . $rowIndex . ':' . chr(65 + $nextColumn + 5) . $rowIndex;
    error_log("Writing to range: " . $range);
    
    // 譖ｸ縺崎ｾｼ繧繝・・繧ｿ - 蛟句挨縺ｮ繧ｻ繝ｫ縺ｫ蛻・￠繧・    $values = [[
        $originalContent,   // 蜈・・險倅ｺ句・螳ｹ
        $issues,           // 蝠城｡檎せ
        $datetime,         // 譌･譎・        $improvedTitle,    // 謾ｹ蝟・＆繧後◆繧ｿ繧､繝医Ν
        $improvedDescription, // 謾ｹ蝟・＆繧後◆繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ
        $improvedContent   // 謾ｹ蝟・＆繧後◆譛ｬ譁・    ]];
    
    // 繧ｹ繝励Ξ繝・ラ繧ｷ繝ｼ繝医↓譖ｸ縺崎ｾｼ縺ｿ
    $result = writeToSheet($range, $values);
    if (!$result) {
        error_log("Failed to write rewrite result to spreadsheet for URL: " . $url);
    } else {
        error_log("Successfully wrote rewrite result to spreadsheet for URL: " . $url);
    }
    
    return $result;
}

/**
 * 繝槭・繧ｯ繝繧ｦ繝ｳ蠖｢蠑上・繝・く繧ｹ繝医ｒHTML縺ｫ螟画鋤縺吶ｋ髢｢謨ｰ
 * 
 * @param string $markdown 繝槭・繧ｯ繝繧ｦ繝ｳ蠖｢蠑上・繝・く繧ｹ繝・ * @return string HTML蠖｢蠑上・繝・く繧ｹ繝・ */
function convertMarkdownToHtml($markdown) {
    // 譌｢縺ｫHTML蠖｢蠑上・蝣ｴ蜷医・縺昴・縺ｾ縺ｾ霑斐☆
    if (strpos($markdown, '<h2>') !== false || strpos($markdown, '<p>') !== false) {
        return $markdown;
    }
    
    // 謾ｹ陦後ｒ豁｣隕丞喧
    $markdown = str_replace("\r\n", "\n", $markdown);
    
    // 隕句・縺励・螟画鋤
    $html = preg_replace('/^## (.+?)$/m', '<h2>$1</h2>', $markdown);
    $html = preg_replace('/^### (.+?)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^#### (.+?)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^##### (.+?)$/m', '<h5>$1</h5>', $html);
    
    // 谿ｵ關ｽ縺ｮ螟画鋤
    // 遨ｺ陦後〒蛹ｺ蛻・ｉ繧後◆繝悶Ο繝・け繧呈ｮｵ關ｽ縺ｨ縺励※謇ｱ縺・    $paragraphs = preg_split('/\n\s*\n/', $html);
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (!empty($paragraph)) {
            // 譌｢縺ｫHTML繧ｿ繧ｰ縺ｧ蟋九∪繧句ｴ蜷医・縺昴・縺ｾ縺ｾ菴ｿ逕ｨ
            if (preg_match('/^<(h[2-5]|p|ul|ol|li|blockquote|pre|div)/i', $paragraph)) {
                $html .= $paragraph . "\n\n";
            } else {
                $html .= '<p>' . $paragraph . '</p>' . "\n\n";
            }
        }
    }
    
    // 繝ｪ繧ｹ繝医・螟画鋤
    $html = preg_replace('/^- (.+?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.+?<\/li>\s*)+/s', '<ul>$0</ul>', $html);
    
    // 逡ｪ蜿ｷ莉倥″繝ｪ繧ｹ繝医・螟画鋤
    $html = preg_replace('/^\d+\. (.+?)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.+?<\/li>\s*)+/s', '<ol>$0</ol>', $html);
    
    // 莠碁㍾縺ｫ繝阪せ繝医＆繧後◆繝ｪ繧ｹ繝医ｒ菫ｮ豁｣
    $html = str_replace("<ul><ol>", "<ol>", $html);
    $html = str_replace("</ol></ul>", "</ol>", $html);
    
    // 蠑ｷ隱ｿ縺ｮ螟画鋤
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    
    // 繝ｪ繝ｳ繧ｯ縺ｮ螟画鋤
    $html = preg_replace('/\[(.+?)\]\((.+?)\)/s', '<a href="$2">$1</a>', $html);
    
    // 荳崎ｦ√↑繧ｿ繧ｰ縺ｮ菫ｮ豁｣
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
 * 謾ｹ蝟・＆繧後◆險倅ｺ九ョ繝ｼ繧ｿ繧谷SON縺九ｉ隗｣譫舌☆繧矩未謨ｰ
 * 
 * @param string $jsonData JSON蠖｢蠑上・險倅ｺ九ョ繝ｼ繧ｿ
 * @return array 隗｣譫舌＆繧後◆險倅ｺ九ョ繝ｼ繧ｿ
 */
function parseImprovedArticleData($jsonData) {
    // 繝・ヰ繝・げ諠・ｱ繧貞・蜉・    error_log("Parsing improved article data, first 100 chars: " . substr($jsonData, 0, 100));
    
    $data = json_decode($jsonData, true);
    
    if (is_array($data) && isset($data['title']) && isset($data['description']) && isset($data['content'])) {
        error_log("JSON data successfully parsed with title, description, and content");
        // 譛ｬ譁・′繝槭・繧ｯ繝繧ｦ繝ｳ蠖｢蠑上・蝣ｴ蜷医・HTML縺ｫ螟画鋤
        if (isset($data['content'])) {
            $data['content'] = convertMarkdownToHtml($data['content']);
            // ```html ```繧ｿ繧ｰ繧貞炎髯､縺吶ｋ
            $data['content'] = preg_replace('/^```html\s*|\s*```$/s', '', $data['content']);
        }
        return $data;
    }
    
    // JSON繝・さ繝ｼ繝峨↓螟ｱ謨励＠縺溷ｴ蜷医・繝・ヰ繝・げ諠・ｱ
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
    }
    
    // JSON縺ｧ縺ｪ縺・ｴ蜷医・譁・ｭ怜・縺九ｉ隗｣譫舌ｒ隧ｦ縺ｿ繧・    $title = '';
    $description = '';
    $content = $jsonData;
    
    if (preg_match('/繧ｿ繧､繝医Ν:\s*(.+?)(?=\n繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:|$)/s', $jsonData, $titleMatches)) {
        $title = trim($titleMatches[1]);
        // 繧ｯ繝ｪ繝・け邇・↑縺ｩ縺ｮ菴吝・縺ｪ諠・ｱ繧帝勁蜴ｻ
        // 諡ｬ蠑ｧ蜀・・諠・ｱ繧貞炎髯､
        $title = preg_replace('/\s*\([^)]*\)\s*$/', '', $title);
        // 縲後阪ｄ縲弱上・螟門・縺ｫ縺ゅｋ菴吝・縺ｪ諠・ｱ繧貞炎髯､
        $title = preg_replace('/^.*?[縲後讃([^縲阪従*)[縲阪従.*$/', '\1', $title);
        // 縲後阪ｄ縲弱上′縺ｪ縺・ｴ蜷医・縺昴・縺ｾ縺ｾ菴ｿ逕ｨ
        if (!preg_match('/[縲後弱阪従/', $title)) {
            $title = preg_replace('/\s*[:|\-]\s*.*$/', '', $title);
        }
    }
    
    if (preg_match('/繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:\s*(.+?)(?=\n譛ｬ譁・|$)/s', $jsonData, $descMatches)) {
        $description = trim($descMatches[1]);
        // 諡ｬ蠑ｧ蜀・・菴吝・縺ｪ諠・ｱ繧貞炎髯､
        $description = preg_replace('/\s*\([^)]*\)\s*$/', '', $description);
        // 繧ｳ繝ｭ繝ｳ繧・ム繝・す繝･莉･髯阪・菴吝・縺ｪ諠・ｱ繧貞炎髯､
        $description = preg_replace('/\s*[:|\-]\s*.*$/', '', $description);
    }
    
    if (preg_match('/譛ｬ譁・\s*(.+?)$/s', $jsonData, $bodyMatches)) {
        $content = trim($bodyMatches[1]);
        
        // HTML繧ｿ繧ｰ繧・ｸ崎ｦ√↑隕∫ｴ繧帝勁蜴ｻ
        // <title>縲・meta>繧ｿ繧ｰ繧帝勁蜴ｻ
        $content = preg_replace('/<title>.*?<\/title>/is', '', $content);
        $content = preg_replace('/<meta[^>]*>/is', '', $content);
        
        // <body>繧ｿ繧ｰ縺ｮ荳ｭ霄ｫ縺縺代ｒ謚ｽ蜃ｺ
        if (preg_match('/<body>(.*?)<\/body>/is', $content, $bodyContentMatches)) {
            $content = trim($bodyContentMatches[1]);
        } else {
            // <body>繧ｿ繧ｰ縺後↑縺・ｴ蜷医・縲√ち繧､繝医Ν縺ｨ繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ縺ｮ陦後ｒ髯､蜴ｻ
            $content = preg_replace('/^繧ｿ繧､繝医Ν:.+?\n/m', '', $content);
            $content = preg_replace('/^繝｡繧ｿ繝・ぅ繧ｹ繧ｯ繝ｪ繝励す繝ｧ繝ｳ:.+?\n/m', '', $content);
        }
        
        // 繝槭・繧ｯ繝繧ｦ繝ｳ縺九ｉHTML縺ｫ螟画鋤
        $content = convertMarkdownToHtml($content);
        
        // ```html ```繧ｿ繧ｰ繧貞炎髯､縺吶ｋ
        $content = preg_replace('/^```html\s*|\s*```$/s', '', $content);
    }
    
    return [
        'title' => $title,
        'description' => $description,
        'content' => $content
    ];
}

