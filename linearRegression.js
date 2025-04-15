function linearRegression(yValues) {
    const n = yValues.length;
    const xValues = [...Array(n).keys()]; // [0, 1, 2, ..., n-1]

    const sumX = xValues.reduce((a, b) => a + b, 0);
    const sumY = yValues.reduce((a, b) => a + parseFloat(b), 0);
    const sumXY = xValues.reduce((sum, x, i) => sum + x * parseFloat(yValues[i]), 0);
    const sumX2 = xValues.reduce((sum, x) => sum + x * x, 0);

    const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
    const intercept = (sumY - slope * sumX) / n;

    // 回帰線の y 値を返す
    return xValues.map(x => slope * x + intercept);
}
