<?php
session_start();

// --- .env 読み込み ---
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv("$name=$value");
    }
}
loadEnv(__DIR__ . '/.env');
$api_key = getenv('API_KEY');

// --- 表示期間の取得 ---
$range = $_GET['range'] ?? '1d';

// --- API設定 ---
$interval_map = ['1d' => '5min', '1w' => '30min', '1m' => '1h'];
$outputsize_map = ['1d' => 288, '1w' => 336, '1m' => 720];
$interval = $interval_map[$range] ?? '1h';
$outputsize = $outputsize_map[$range] ?? 24;

// --- 銘柄の取得 ---
$symbol = $_GET['symbol'] ?? 'AAPL';  // デフォルトはAAPL
$company_names = ['AAPL' => 'Apple', 'GOOGL' => 'Google', 'MSFT' => 'Microsoft'];

$url = "https://api.twelvedata.com/time_series?symbol=$symbol&interval=$interval&outputsize=$outputsize&apikey=$api_key";

$response = @file_get_contents($url);
$data = json_decode($response, true);

$labels = [];
$prices = [];
$current_price = 0;

if (isset($data['values'])) {
    $reversed = array_reverse($data['values']);
    foreach ($reversed as $point) {
        $labels[] = $point['datetime'];
        $prices[] = $point['close'];
    }
    $current_price = floatval(end($prices)); // 最新の株価
    $start_price = isset($prices[0]) ? floatval($prices[0]) : 0;
    $price_diff = $current_price - $start_price;

}

if (!$data || isset($data['code'])) {
    echo "<p>データの取得に失敗しました（API制限など）。</p>";
}

if (!isset($_SESSION['portfolio'])) $_SESSION['portfolio'] = [];

if (!isset($_SESSION['portfolio'][$symbol])) {
    $_SESSION['portfolio'][$symbol] = [
        'cash' => 10000,         // 初期現金
        'stocks' => 0,           // 初期保有株数
        'history' => []          // 初期履歴
    ];
}

// --- 売買処理 ---
$action = $_POST['action'] ?? null;
$quantity = max(1, intval($_POST['quantity'] ?? 1));
$message = '';

$cash = &$_SESSION['portfolio'][$symbol]['cash'];
$stocks = &$_SESSION['portfolio'][$symbol]['stocks'];
$history = &$_SESSION['portfolio'][$symbol]['history'];

if ($action && $current_price > 0 && $quantity > 0) {
    if ($action === 'buy') {
        $cost = $current_price * $quantity;
        if ($cash >= $cost) {
            $cash -= $cost;
            $stocks += $quantity;
            $history[] = "購入: {$quantity}株（" . number_format($cost, 2) . " USD）";
            $message = "{$quantity}株購入しました。";
        } else {
            $message = "資金が不足しています。";
        }
    } elseif ($action === 'sell') {
        if ($stocks >= $quantity) {
            $revenue = $current_price * $quantity;
            $cash += $revenue;
            $stocks -= $quantity;
            $history[] = "売却: {$quantity}株（" . number_format($revenue, 2) . " USD）";
            $message = "{$quantity}株売却しました。";
        } else {
            $message = "保有株が不足しています。";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>株シミュレーター</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
</head>
<body>
    <h1><?= $company_names[$symbol] ?></h1>

    <form method="get" style="margin-bottom: 20px;">
    <label for="symbol">銘柄：</label>
    <select name="symbol" id="symbol" onchange="this.form.submit()">
        <?php foreach ($company_names as $sym => $name): ?>
            <option value="<?= $sym ?>" <?= $symbol === $sym ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
    </select>

    <label for="range">表示期間：</label>
    <select name="range" id="range" onchange="this.form.submit()">
        <option value="1d" <?= $range === '1d' ? 'selected' : '' ?>>1日</option>
        <option value="1w" <?= $range === '1w' ? 'selected' : '' ?>>1週間</option>
        <option value="1m" <?= $range === '1m' ? 'selected' : '' ?>>1か月</option>
    </select>
</form>

    <canvas id="stockChart" width="800" height="400"></canvas>

    <h2>現在の株価: <?= number_format($current_price, 2) ?> USD</h2>
    <p>所持金: <?= number_format($cash, 2) ?> USD</p>
    <p>保有株: <?= $stocks ?> 株</p>

    <p>
        期間内の変動: 
        <span style="color: <?= $price_diff >= 0 ? 'limegreen' : 'orangered' ?>">
            <?= ($price_diff >= 0 ? '+' : '') . number_format($price_diff, 2) ?> USD
        </span>
    </p>


    <form method="post" style="margin: 10px 0;">
        <input type="number" name="quantity" min="1" value="1" required>
        <button type="submit" name="action" value="buy">購入</button>
        <button type="submit" name="action" value="sell">売却</button>
    </form>


    <button onclick="chart.resetZoom()">ズームリセット</button>

    <?php if ($message): ?>
        <p><strong><?= $message ?></strong></p>
    <?php endif; ?>

    <h3>売買履歴（<?= $company_names[$symbol] ?>）</h3>
    <ul>
        <?php foreach (array_reverse($history) as $entry): ?>
            <li><?= htmlspecialchars($entry) ?></li>
        <?php endforeach; ?>
    </ul>

    <h3>全銘柄の資産状況</h3>
    <ul>
    <?php foreach ($_SESSION['portfolio'] as $sym => $info): ?>
        <li>
            <?= $company_names[$sym] ?? $sym ?>：
            所持金 <?= number_format($info['cash'], 2) ?> USD ／
            保有株 <?= $info['stocks'] ?> 株
        </li>
    <?php endforeach; ?>
    </ul>

    <script>
    const prices = <?= json_encode($prices) ?>;
    const trendline = linearRegression(prices);


    const ctx = document.getElementById('stockChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: '株価 (USD)',
                data: <?= json_encode($prices) ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: '#ffffff' } },
            },
            scales: {
                x: {
                    ticks: { color: '#aaa' },
                    grid: { color: '#333' }
                },
                y: {
                    ticks: { color: '#aaa' },
                    grid: { color: '#333' }
                }
            },
            plugins: {
                zoom: {
                    zoom: {
                        wheel: {
                            enabled: true // マウスホイールでズーム
                        },
                        pinch: {
                            enabled: true // ピンチイン・アウト（モバイル）
                        },
                        mode: 'x' // x軸方向にズーム
                    },
                    pan: {
                        enabled: true,
                        mode: 'x' // x軸方向にスクロール
                    }
                }
            }
        }
    });
    </script>
</body>
</html>
