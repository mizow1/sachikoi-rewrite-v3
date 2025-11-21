<?php
/**
 * OPcacheをクリアするスクリプト
 * 本番サーバーにアップロードして、ブラウザからアクセスしてください
 */

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "✓ OPcache cleared successfully\n";
        echo "<br>";
        echo "修正したPHPファイルが反映されました。\n";
    } else {
        echo "✗ Failed to clear OPcache\n";
    }
} else {
    echo "OPcache is not enabled on this server\n";
}

echo "<br><br>";
echo "完了したら、このファイルは削除してください。\n";
?>
