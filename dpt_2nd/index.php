<?php
// DB接続
try {
    $pdo = new PDO(
        'mysql:dbname=gs-y-imai_kadai;charset=utf8;host=mysql3108.db.sakura.ne.jp', 
        'gs-y-imai_kadai', 
        ''
    );
} catch (PDOException $e) {
    exit('DB Connection Error: ' . $e->getMessage());
}

// フィルタ受取
$fy = isset($_GET['fy']) ? $_GET['fy'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$branch_office = isset($_GET['branch_office']) ? $_GET['branch_office'] : '';
$compare_fy = isset($_GET['compare_fy']) ? $_GET['compare_fy'] : '';


// 集計SQL実行
$sql = "
SELECT
    branch_office,
    fy,
    SUM(gross) AS gross_sum,
    COUNT(DISTINCT CONCAT(branch_office, '_', staff_name)) AS staff_count,
    CASE
        WHEN COUNT(DISTINCT CONCAT(branch_office, '_', staff_name)) > 0
        THEN FLOOR(SUM(gross) / COUNT(DISTINCT CONCAT(branch_office, '_', staff_name)))
        ELSE 0
    END AS average_gross_per_staff,
    SUM(appointment) AS appointment_sum,
    SUM(conclusion) AS conclusion_sum,
    CASE WHEN SUM(appointment) > 0 THEN ROUND(SUM(conclusion) / SUM(appointment) * 100, 1) ELSE 0 END AS conclusion_rate,
    CASE WHEN SUM(appointment) > 0 THEN FLOOR(SUM(gross) / SUM(appointment)) ELSE 0 END AS gross_per_appointment,
    CASE WHEN SUM(conclusion) > 0 THEN FLOOR(SUM(gross) / SUM(conclusion)) ELSE 0 END AS gross_per_conclusion
FROM
    dpt_2nd
";

// WHERE句構築
$where = [];
$params = [];

if ($fy !== '') {
    $where[] = "fy = :fy";
    $params[':fy'] = $fy;
}
if ($status !== '') {
    $where[] = "status = :status";
    $params[':status'] = $status;
}
if ($branch_office !== '') {
    $where[] = "branch_office = :branch_office";
    $params[':branch_office'] = $branch_office;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
GROUP BY branch_office, fy
ORDER BY gross_sum DESC
";

// SQL実行
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 総計集計用
$gross_total = 0;
$staff_total = 0;
$appointment_total = 0;
$conclusion_total = 0;

// 総計算出
foreach ($results as $row) {
    $gross_total += $row['gross_sum'];
    $staff_total += $row['staff_count'];
    $appointment_total += $row['appointment_sum'];
    $conclusion_total += $row['conclusion_sum'];
}

// 平均・率再計算
$average_gross_per_staff_total = ($staff_total > 0) ? floor($gross_total / $staff_total) : 0;
$conclusion_rate_total = ($appointment_total > 0) ? round($conclusion_total / $appointment_total * 100, 1) : 0;
$gross_per_appointment_total = ($appointment_total > 0) ? floor($gross_total / $appointment_total) : 0;
$gross_per_conclusion_total = ($conclusion_total > 0) ? floor($gross_total / $conclusion_total) : 0;

// === 対比FYの総計取得 ===
$compare_data = [];
$compare_results_exist = false;

if (!empty($compare_fy)) {
    // 元の条件から fy を除外して compare_fy に置き換える
    $compare_where = array_filter($where, function ($w) {
        return strpos($w, 'fy') === false;
    });

    $compare_where[] = "fy = :compare_fy";

    // パラメータも上書き
    $compare_params = $params;
    foreach ($compare_params as $key => $val) {
        if ($key === ':fy') {
            unset($compare_params[$key]);
        }
    }
    $compare_params[':compare_fy'] = $compare_fy;

    // SQL作成
    $compare_sql = "
        SELECT
            SUM(gross) AS gross_sum,
            COUNT(DISTINCT CONCAT(branch_office, '_', staff_name)) AS staff_count,
            SUM(appointment) AS appointment_sum,
            SUM(conclusion) AS conclusion_sum
        FROM dpt_2nd
    ";

    if (!empty($compare_where)) {
        $compare_sql .= " WHERE " . implode(" AND ", $compare_where);
    }

    $stmt2 = $pdo->prepare($compare_sql);
    $stmt2->execute($compare_params);
    $compare_data = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($compare_data && $compare_data['gross_sum'] !== null) {
        $compare_results_exist = true;

        // 対比FYの指標再計算
        $compare_data['average_gross_per_staff'] = $compare_data['staff_count'] > 0 ? floor($compare_data['gross_sum'] / $compare_data['staff_count']) : 0;
        $compare_data['conclusion_rate'] = $compare_data['appointment_sum'] > 0 ? round($compare_data['conclusion_sum'] / $compare_data['appointment_sum'] * 100, 1) : 0;
        $compare_data['gross_per_appointment'] = $compare_data['appointment_sum'] > 0 ? floor($compare_data['gross_sum'] / $compare_data['appointment_sum']) : 0;
        $compare_data['gross_per_conclusion'] = $compare_data['conclusion_sum'] > 0 ? floor($compare_data['gross_sum'] / $compare_data['conclusion_sum']) : 0;
    }
}




// グラフ用データ取得
$sql_chart = "
    SELECT
        branch_office,
        year,
        month,
        COUNT(DISTINCT staff_name) AS staff_count
    FROM
        dpt_2nd
";

// WHERE 条件は既存の $where, $params をそのまま使う
if (!empty($where)) {
    $sql_chart .= " WHERE " . implode(" AND ", $where);
}

$sql_chart .= "
    GROUP BY branch_office, year, month
    ORDER BY year ASC, month ASC, branch_office ASC
";

$stmt_chart = $pdo->prepare($sql_chart);
$stmt_chart->execute($params);
$chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Bot</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<header>
        <!-- Results Bot -->
        <img src="../img/logo.png" alt="Results Bot ロゴ" class="logo">
        <button id="menu-toggle" class="hamburger">&#9776;</button>
</header>
    
<div class="container">
<aside id="side-menu">
          <h2>MENU</h2>
          <ul>
            <li><a href="/results_bot_4/index.php">Dashboard</a></li>
            <li><a href="#">1st Sales Dept</a></li>
            <li><a href="/results_bot_4/dpt_2nd/index.php">2nd Sales Dept</a></li>
            <li><a href="#">3rd Sales Dept</a></li>
            <li><a href="/results_bot_4/sales_div/index.php">Sales Division</a></li>
            <li><a href="#">Web Division</a></li>
            <!-- <li><a href="/results_bot_4/personal_results/index.php">Personal Results</a></li> -->
            <li><a href="#">Budget Management</a></li>
          </ul>
    </aside>
    
    <main>
        <div class="toolbar">
        <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
        <label for="csv-file" class="custom-file-label">Select CSV</label>
        <input type="file" name="csv_file" id="csv-file" accept=".csv" style="display: none;">
        <button type="submit">CSV Upload</button>
        </form>
            
                <form action="" method="get" style="display:inline;">
                <select name="fy">
        <option value="">Select FY</option>
        <option value="2025"<?= ($fy === '2025') ? 'selected' : '' ?>>FY2025</option>
        <option value="2024"<?= ($fy === '2024') ? 'selected' : '' ?>>FY2024</option>
        <!-- 必要に応じて追加 -->
        </select>
        <select name="branch_office">
    <option value="">Select Branch</option>
    <option value="名古屋" <?= ($branch_office === '名古屋') ? 'selected' : '' ?>>名古屋</option>
    <option value="千葉" <?= ($branch_office === '千葉') ? 'selected' : '' ?>>千葉</option>
    <option value="大宮" <?= ($branch_office === '大宮') ? 'selected' : '' ?>>大宮</option>
    <option value="横浜" <?= ($branch_office === '横浜') ? 'selected' : '' ?>>横浜</option>
    <option value="池袋" <?= ($branch_office === '池袋') ? 'selected' : '' ?>>池袋</option>
    <option value="長野" <?= ($branch_office === '長野') ? 'selected' : '' ?>>長野</option>
</select>

    </select>  
    <select name="status">
    <option value="">Select Status (ALL)</option>
    <option value="holding"<?= ($status === 'holding') ? 'selected' : '' ?>>在職 (holding)</option>
    <option value="resigning"<?= ($status === 'resigning') ? 'selected' : '' ?>>退職 (resigning)</option>
</select>          
                
                
                
                <select name="year">
                    <option value="">Select Year</option>
                    <option value="2025"<?= ($year === '2025') ? 'selected' : '' ?>>2025年</option>
                    <option value="2024"<?= ($year === '2024') ? 'selected' : '' ?>>2024年</option>
                    <!-- 必要に応じて追加 -->
                  </select>
                  <select name="month">
                    <option value="">Select Month</option>
                    <option value="1"<?= ($month === '1') ? 'selected' : '' ?>>1月</option>
                    <option value="2"<?= ($month === '2') ? 'selected' : '' ?>>2月</option>
                    <option value="3"<?= ($month === '3') ? 'selected' : '' ?>>3月</option>
                    <option value="4"<?= ($month === '4') ? 'selected' : '' ?>>4月</option>
                    <option value="5"<?= ($month === '5') ? 'selected' : '' ?>>5月</option>
                    <option value="6"<?= ($month === '6') ? 'selected' : '' ?>>6月</option>
                    <option value="7"<?= ($month === '7') ? 'selected' : '' ?>>7月</option>
                    <option value="8"<?= ($month === '8') ? 'selected' : '' ?>>8月</option>
                    <option value="9"<?= ($month === '9') ? 'selected' : '' ?>>9月</option>
                    <option value="10"<?= ($month === '10') ? 'selected' : '' ?>>10月</option>
                    <option value="11"<?= ($month === '11') ? 'selected' : '' ?>>11月</option>
                    <option value="12"<?= ($month === '12') ? 'selected' : '' ?>>12月</option>
                    <!-- 12月まで -->
                  </select>
                  <button type="submit">Display</button>
                </form>
        </div>




        <div class="dashboard-content">
                <!-- 集計結果のテーブルやグラフ -->
              
                <h2>2_第二営業部</h2>
<table>
    <thead>
        <tr>
            <th>支店</th>
            <th>FY</th>
            <!-- <th>スタッフ名</th> -->
            <!-- <th>年</th> -->
            <!-- <th>月</th> -->
            <th>粗利合計</th>
            <th>車両数</th>
            <th>車両単価</th>
            <th>アポ数</th>
            <th>成約数</th>
            <th>成約率(%)</th>
            <th>アポ単価</th>
            <th>成約単価</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['branch_office']) ?></td>
            <!-- <td><?= htmlspecialchars($row['branch_offices']) ?></td> -->
            <td><?= htmlspecialchars($row['fy']) ?></td>
            <!-- <td><?= htmlspecialchars($row['staff_name']) ?></td> -->
            <!-- <td><?= htmlspecialchars($row['year']) ?></td> -->
            <!-- <td><?= htmlspecialchars($row['month']) ?></td> -->
            <td><?= number_format($row['gross_sum']) ?>円</td>
            <td><?= htmlspecialchars($row['staff_count']) ?>人</td>
            <td style="color: <?= ($row['average_gross_per_staff'] < $average_gross_per_staff_total) ? 'red' : 'blue' ?>"><?= number_format($row['average_gross_per_staff']) ?>円</td>
            <td><?= htmlspecialchars($row['appointment_sum']) ?>件</td>
            <td><?= htmlspecialchars($row['conclusion_sum']) ?>件</td>
            <td style="color: <?= ($row['conclusion_rate'] < $conclusion_rate_total) ? 'red' : 'blue' ?>"><?= htmlspecialchars($row['conclusion_rate']) ?>%</td>
            <td style="color: <?= ($row['gross_per_appointment'] < $gross_per_appointment_total) ? 'red' : 'blue' ?>"><?= number_format($row['gross_per_appointment']) ?>円</td>
            <td style="color: <?= ($row['gross_per_conclusion'] < $gross_per_conclusion_total) ? 'red' : 'blue' ?>"><?= number_format($row['gross_per_conclusion']) ?>円</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
<tr style="font-weight:bold; background:#f0f0f0;">
    <td>TOTAL</td>
    <td>-</td> <!-- fy -->
    <!-- <td>-</td> staff_name -->
    
    <td><?= number_format($gross_total) ?>円</td>
    <td><?= $staff_total ?>人</td>
    <td><?= number_format($average_gross_per_staff_total) ?>円</td>
    <td><?= $appointment_total ?>件</td>
    <td><?= $conclusion_total ?>件</td>
    <td><?= $conclusion_rate_total ?>%</td>
    <td><?= number_format($gross_per_appointment_total) ?>円</td>
    <td><?= number_format($gross_per_conclusion_total) ?>円</td>
</tr>
</tfoot>
</table>

        </div>


<!-- 追加FY比較セレクトフォーム -->
<div class="toolbar">
    <form method="get" action="">
        <input type="hidden" name="fy" value="<?= htmlspecialchars($fy) ?>">
        <input type="hidden" name="branch_office" value="<?= htmlspecialchars($branch_office) ?>">
        <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
        <input type="hidden" name="month" value="<?= htmlspecialchars($month) ?>">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        
        <label for="compare_fy">対比用FY:</label>
        <select name="compare_fy" id="compare_fy">
            <option value="">Select FY</option>
            <option value="2025" <?= ($compare_fy === '2025') ? 'selected' : '' ?>>FY2025</option>
            <option value="2024" <?= ($compare_fy === '2024') ? 'selected' : '' ?>>FY2024</option>
            <option value="2023" <?= ($compare_fy === '2023') ? 'selected' : '' ?>>FY2023</option>
        </select>
        <button type="submit">Compare</button>
    </form>
</div>

<!-- 比較対象FYの総計表示 -->
<?php 
$compare_results_exist = !empty($compare_data) && $compare_data['gross_sum'] !== null;

if ($compare_results_exist): ?>
<div class="dashboard-content">
    <h2>比較年：FY<?= htmlspecialchars($compare_fy) ?> </h2>
    <table>
        <thead>
            <tr>
            <th>支店</th>
            <th>FY</th>
            <!-- <th>スタッフ名</th> -->
            <!-- <th>年</th> -->
            <!-- <th>月</th> -->
            <th>粗利合計</th>
            <th>車両数</th>
            <th>車両単価</th>
            <th>アポ数</th>
            <th>成約数</th>
            <th>成約率(%)</th>
            <th>アポ単価</th>
            <th>成約単価</th>
            </tr>
        </thead>
        <tbody>
        <tr>
                <td>TOTAL</td>
                <td><?= htmlspecialchars($compare_fy) ?></td>
                <td><?= number_format($compare_data['gross_sum']) ?>円</td>
                <td><?= $compare_data['staff_count'] ?>人</td>
                <td><?= number_format($compare_data['average_gross_per_staff']) ?>円</td>
                <td><?= $compare_data['appointment_sum'] ?>件</td>
                <td><?= $compare_data['conclusion_sum'] ?>件</td>
                <td><?= $compare_data['conclusion_rate'] ?>%</td>
                <td><?= number_format($compare_data['gross_per_appointment']) ?>円</td>
                <td><?= number_format($compare_data['gross_per_conclusion']) ?>円</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- 対比表示 -->
<div class="dashboard-content">
    <h2><?= htmlspecialchars($compare_fy) ?>年対比</h2>
    <table>
        <thead>
            <tr>
                <th>支店</th>
                <th>FY</th>
                <th>粗利合計</th>
                <th>車両数</th>
                <th>車両単価</th>
                <th>アポ数</th>
                <th>成約数</th>
                <th>成約率(%)</th>
                <th>アポ単価</th>
                <th>成約単価</th>
            </tr>
        </thead>
        <tbody>
            <tr>
            <td>TOTAL</td>
            <td><?= htmlspecialchars($compare_fy) ?></td>
                <?php
                // 関数で色付け処理
                function coloredRate($current, $compare) {
                    if ($compare > 0) {
                        $rate = round($current / $compare * 100, 1);
                        $color = ($rate >= 100) ? 'blue' : 'red';
                        return "<td style='color: {$color}'>{$rate}%</td>";
                    } else {
                        return "<td style='color: red'>0%</td>";
                    }
                }
                ?>
                <?= coloredRate($gross_total, $compare_data['gross_sum']) ?>
                <?= coloredRate($staff_total, $compare_data['staff_count']) ?>
                <?= coloredRate($average_gross_per_staff_total, $compare_data['average_gross_per_staff']) ?>
                <?= coloredRate($appointment_total, $compare_data['appointment_sum']) ?>
                <?= coloredRate($conclusion_total, $compare_data['conclusion_sum']) ?>
                <?= coloredRate($conclusion_rate_total, $compare_data['conclusion_rate']) ?>
                <?= coloredRate($gross_per_appointment_total, $compare_data['gross_per_appointment']) ?>
                <?= coloredRate($gross_per_conclusion_total, $compare_data['gross_per_conclusion']) ?>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>









        <div class=dashboard-chart>
        <h2>支店別クローザー人数推移</h2>        
        <div class="chart-container">
         <canvas  id="staffChart" height="480"></canvas>
        </div>
        </div>    
    </main>
 </div>    
 <script src="../menu.js"></script>
 <script>
const chartData = <?= json_encode($chart_data); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../staff_chart.js"></script>    
</body>
</html>
