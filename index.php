<?php
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
$range = $_GET['range'] ?? '1d'; // デフォルトは1日

// --- API設定 ---
$interval_map = [
    '1d' => '5min',    // 5分間隔
    '1w' => '30min',   // 30分間隔
    '1m' => '1h',      // 1時間間隔
];

$outputsize_map = [
    '1d' => 288,   // 5分間隔×24時間 = 288本
    '1w' => 336,   // 30分×7日間×24時間÷30分 = 約336本
    '1m' => 720,   // 1時間×30日 = 720本
];

$interval = $interval_map[$range] ?? '1h';
$outputsize = $outputsize_map[$range] ?? 24;

$url = "https://api.twelvedata.com/time_series?symbol=AAPL&interval=$interval&outputsize=$outputsize&apikey=$api_key";

$response = @file_get_contents($url);
$data = json_decode($response, true);

$labels = [];
$prices = [];

if (isset($data['values'])) {
    foreach (array_reverse($data['values']) as $point) {
        $labels[] = $point['datetime'];
        $prices[] = $point['close'];
    }
}

if (!$data || isset($data['code'])) {
    echo "<p>データの取得に失敗しました（API制限など）。</p>";
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Apple株価チャート</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Apple 株価チャート</h1>
    <form method="get" style="margin-bottom: 20px;">
        <label for="range">表示期間：</label>
        <select name="range" id="range" onchange="this.form.submit()">
            <option value="1d" <?= $range === '1d' ? 'selected' : '' ?>>1日</option>
            <option value="1w" <?= $range === '1w' ? 'selected' : '' ?>>1週間</option>
            <option value="1m" <?= $range === '1m' ? 'selected' : '' ?>>1か月</option>
        </select>
    </form>

    <canvas id="stockChart" width="800" height="400"></canvas>

    <script>
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
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 10
                    }
                },
                y: {
                    beginAtZero: false
                }
            }
        }
    });
    </script>
</body>
</html>
