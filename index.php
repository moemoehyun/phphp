<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>PHP サンプルサイト - Apple株価</title>
</head>
<body>
    <h1>ようこそ！</h1>

    <p>現在の日時は: <?php echo date("Y年m月d日 H:i:s"); ?></p>

    <?php
    // .env を読み込む関数（簡易）
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            putenv("$name=$value");
        }
    }
    
    loadEnv(__DIR__ . '/.env'); // .envファイルを読み込み
    
    $api_key = getenv('API_KEY'); // 環境変数から読み込み
    // Twelve Data APIキー（https://twelvedata.com/ で無料登録）

    // Apple (AAPL) のリアルタイム株価を取得
    $url = "https://api.twelvedata.com/price?symbol=AAPL&apikey=$api_key";

    // APIレスポンス取得
    $json = @file_get_contents($url);
    if ($json === FALSE) {
        echo "<p>株価の取得に失敗しました。</p>";
    } else {
        $data = json_decode($json, true);
        if (isset($data['price'])) {
            echo "<p>Appleの現在の株価は <strong>{$data['price']}</strong> USD です。</p>";
        } else {
            echo "<p>株価情報の取得に失敗しました（APIの制限またはエラー）。</p>";
        }
    }
    ?>
</body>
</html>


