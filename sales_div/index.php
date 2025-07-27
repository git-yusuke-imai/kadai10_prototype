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
$compare_fy = isset($_GET['compare_fy']) ? $_GET['compare_fy'] : '';


// 集計SQL実行
$sql = "
SELECT
    media,
    fy,
    SUM(totalarticles) AS total_articles,
    SUM(newlisting) AS new_listing,
    SUM(number_sold) AS number_sold,
    SUM(sales) AS sales,
    CASE WHEN SUM(totalarticles) > 0 THEN ROUND(SUM(number_sold) / SUM(totalarticles) * 100, 1) ELSE 0 END AS sell_rate_total,
    CASE WHEN SUM(newlisting) > 0 THEN ROUND(SUM(number_sold) / SUM(newlisting) * 100, 1) ELSE 0 END AS sell_rate_new,
    CASE WHEN SUM(number_sold) > 0 THEN FLOOR(SUM(sales) / SUM(number_sold)) ELSE 0 END AS unit_price
FROM dpt_sales
";

// WHERE句構築
$where = [];
$params = [];

if ($fy !== '') {
    $where[] = "fy = :fy";
    $params[':fy'] = $fy;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY media, fy ORDER BY sales DESC";

// SQL実行
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 総計集計用
$total_articles_sum = 0;
$new_listing_sum = 0;
$number_sold_sum = 0;
$sales_sum = 0;


// 総計算出
foreach ($results as $row) {
    $total_articles_sum += $row['total_articles'];
    $new_listing_sum += $row['new_listing'];
    $number_sold_sum += $row['number_sold'];
    $sales_sum += $row['sales'];
}

// 平均・率再計算
$sell_rate_total = ($total_articles_sum > 0) ? round($number_sold_sum / $total_articles_sum * 100, 1) : 0;
$sell_rate_new = ($new_listing_sum > 0) ? round($number_sold_sum / $new_listing_sum * 100, 1) : 0;
$unit_price_total = ($number_sold_sum > 0) ? floor($sales_sum / $number_sold_sum) : 0;

// === 対比FYの総計取得 ===
$compare_data = [];
$compare_results_exist = false;

if (!empty($compare_fy)) {
    $compare_sql = "
        SELECT
            SUM(totalarticles) AS total_articles,
            SUM(newlisting) AS new_listing,
            SUM(number_sold) AS number_sold,
            SUM(sales) AS sales
        FROM dpt_sales
        WHERE fy = :compare_fy
    ";
    $stmt2 = $pdo->prepare($compare_sql);
    $stmt2->execute([':compare_fy' => $compare_fy]);
    $compare_data = $stmt2->fetch(PDO::FETCH_ASSOC);

    if ($compare_data && $compare_data['sales'] !== null) {
        $compare_results_exist = true;
        $compare_data['sell_rate_total'] = $compare_data['total_articles'] > 0 ? round($compare_data['number_sold'] / $compare_data['total_articles'] * 100, 1) : 0;
        $compare_data['sell_rate_new'] = $compare_data['new_listing'] > 0 ? round($compare_data['number_sold'] / $compare_data['new_listing'] * 100, 1) : 0;
        $compare_data['unit_price'] = $compare_data['number_sold'] > 0 ? floor($compare_data['sales'] / $compare_data['number_sold']) : 0;
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
            <!-- <li><a href="/results_bot/personal_results/index.php">Personal Results</a></li> -->
            <li><a href="#">Budget Management</a></li>
          </ul>
    </aside>
    
<main>
  <div class="toolbar">

        <form action="../upload.php" method="post" enctype="multipart/form-data" class="upload-form">
  <label for="csv-file" class="custom-file-label">Select CSV</label>
  <input type="file" name="csv_file" id="csv-file" accept=".csv" style="display: none;" required>

  <label for="department" class="custom-file-label">Select Dpt</label>
  <select name="department" id="department" required>
    <option value="">dpt</option>
    <option value="1st">1stDpt</option>
  <option value="2nd">2ndDpt</option>
  <option value="3rd">3rdDpt</option>
  <option value="sales">Sales</option>
  </select>

  <button type="submit">CSV Upload</button>
</form>

            
                <form action="" method="get" style="display:inline;">
                <select name="fy">
        <option value="">FY</option>
        <option value="2025"<?= ($fy === '2025') ? 'selected' : '' ?>>FY2025</option>
        <option value="2024"<?= ($fy === '2024') ? 'selected' : '' ?>>FY2024</option>
        <!-- 必要に応じて追加 -->
        </select>
        <select name="branch_office">
    <option value="">Branch</option>
    <option value="名古屋" <?= ($branch_office === '名古屋') ? 'selected' : '' ?>>名古屋</option>
    <option value="千葉" <?= ($branch_office === '千葉') ? 'selected' : '' ?>>千葉</option>
    <option value="大宮" <?= ($branch_office === '大宮') ? 'selected' : '' ?>>大宮</option>
    <option value="横浜" <?= ($branch_office === '横浜') ? 'selected' : '' ?>>横浜</option>
    <option value="池袋" <?= ($branch_office === '池袋') ? 'selected' : '' ?>>池袋</option>
    <option value="長野" <?= ($branch_office === '長野') ? 'selected' : '' ?>>長野</option>
</select>

    </select>  
    <select name="status">
    <option value="">Status (ALL)</option>
    <option value="holding"<?= ($status === 'holding') ? 'selected' : '' ?>>在職 (holding)</option>
    <option value="resigning"<?= ($status === 'resigning') ? 'selected' : '' ?>>退職 (resigning)</option>
</select>          
                
                
                
                <select name="year">
                    <option value="">Year</option>
                    <option value="2025"<?= ($year === '2025') ? 'selected' : '' ?>>2025年</option>
                    <option value="2024"<?= ($year === '2024') ? 'selected' : '' ?>>2024年</option>
                    <!-- 必要に応じて追加 -->
                  </select>
                  <select name="month">
                    <option value="">Month</option>
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

    <h2>4_販売事業部</h2>
    <table>
        <thead>
            <tr>
                <th>媒体</th>
                <th>FY</th>
                <th>売上合計</th>
                <th>販売点数</th>
                <th>売上単価</th>
                <th>総出品</th>
                <th>新規出品</th>
                <th>総出品販売率</th>
                <th>新規出品販売率</th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($results as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['media']) ?></td>
        <td><?= htmlspecialchars($row['fy']) ?></td>
        <td><?= number_format($row['sales']) ?>円</td>
        <td><?= (int)$row['number_sold'] ?>点</td>
        <td><?= $row['unit_price'] !== null ? number_format($row['unit_price']) . '円' : '-' ?></td>
        <td><?= (int)$row['total_articles'] ?>点</td>
        <td><?= (int)$row['new_listing'] ?>点</td>
        <td><?= $row['sell_rate_total'] !== null ? $row['sell_rate_total'] . '%' : '0%' ?></td>
        <td><?= $row['sell_rate_new'] !== null ? $row['sell_rate_new'] . '%' : '0%' ?></td>
    </tr>
    <?php endforeach; ?>
</tbody>
<tfoot>
    <tr style="font-weight:bold; background:#f0f0f0;">
        <td>TOTAL</td>
        <td>-</td>
        <td><?= number_format($sales_sum) ?>円</td>
        <td><?= $number_sold_sum ?>点</td>
        <td><?= $unit_price_total !== null ? number_format($unit_price_total) . '円' : '-' ?></td>
        <td><?= $total_articles_sum ?>点</td>
        <td><?= $new_listing_sum ?>点</td>
        <td><?= $sell_rate_total ?>%</td>
        <td><?= $sell_rate_new ?>%</td>
    </tr>
</tfoot>

    </table>
 </div>



<!-- 対比FYセレクトフォーム -->
<div class="toolbar">
    <form method="get" action="">
        <input type="hidden" name="fy" value="<?= htmlspecialchars($fy) ?>">
        <input type="hidden" name="branch_office" value="<?= htmlspecialchars($branch_office) ?>">

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

<?php if (!empty($compare_data) && $compare_data['sales'] !== null): ?>
<!-- 比較年テーブル -->
<div class="dashboard-content">
    <h2>比較年：FY<?= htmlspecialchars($compare_fy) ?></h2>
    <table>
        <thead>
            <tr>
                <th>媒体</th>
                <th>FY</th>
                <th>売上合計</th>
                <th>販売点数</th>
                <th>売上単価</th>
                <th>総出品</th>
                <th>新規出品</th>
                <th>総出品販売率</th>
                <th>新規出品販売率</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>TOTAL</td>
                <td><?= htmlspecialchars($compare_fy) ?></td>
                <td><?= number_format($compare_data['sales']) ?>円</td>
                <td><?= $compare_data['number_sold'] ?>点</td>
                <td><?= number_format($compare_data['unit_price']) ?>円</td>
                <td><?= $compare_data['total_articles'] ?>件</td>
                <td><?= $compare_data['new_listing'] ?>件</td>
                <td><?= $compare_data['sell_rate_total'] ?>%</td>
                <td><?= $compare_data['sell_rate_new'] ?>%</td>
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
            <th>媒体</th>
            <th>FY</th>
                <th>売上</th>
                <th>販売点数</th>
                <th>売上単価</th>
                <th>総出品</th>
                <th>新規出品</th>
                <th>総出品販売率</th>
                <th>新規出品販売率</th>
            </tr>
        </thead>
        <tbody>
            <tr>
            <td>TOTAL</td>
            <td><?= htmlspecialchars($compare_fy) ?></td>
                <?php
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
                <?= coloredRate($sales_sum, $compare_data['sales']) ?>
<?= coloredRate($number_sold_sum, $compare_data['number_sold']) ?>
<?= coloredRate($unit_price_total, $compare_data['unit_price']) ?>
<?= coloredRate($total_articles_sum, $compare_data['total_articles']) ?>
<?= coloredRate($new_listing_sum, $compare_data['new_listing']) ?>
<?= coloredRate($sell_rate_total, $compare_data['sell_rate_total']) ?>
<?= coloredRate($sell_rate_new, $compare_data['sell_rate_new']) ?>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>    
</body>
</html>
