<?php
// db接続
try {
    $pdo = new PDO(
        'mysql:dbname=gs-y-imai_kadai;charset=utf8;host=mysql3108.db.sakura.ne.jp', 
        'gs-y-imai_kadai', 
        ''
    );
} catch (PDOException $e) {
    exit('DB Connection Error: ' . $e->getMessage());
}




// ファイルと部署選択の検証
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    exit('ファイルが正常にアップロードされていません。');
}
if (!isset($_POST['department'])) {
    exit('部署が選択されていません。');
}

// 部署別設定
$departments = [
    '2nd' => [
        'table' => 'dpt_2nd',
        'columns' => ['staff_name', 'branch_office', 'appointment', 'conclusion', 'gross', 'year', 'month', 'fy', 'status'],
        'column_count' => 9
    ],
    '1st' => [
        'table' => 'dpt_1st',
        'columns' => ['staff_name', 'branch_office', 'responses', 'appointment', 'gross', 'laborcost', 'year', 'month', 'fy'],
        'column_count' => 10
    ],
    '3rd' => [
        'table' => 'dpt_3rd',
        'columns' => ['staff_name', 'branch_office', 'receiving', 'reception', 'invalid', 'gross', 'year', 'month', 'fy'],
        'column_count' => 9
    ],
    'sales' => [
        'table' => 'dpt_sales',
        'columns' => ['branch_office','fy' , 'year', 'month', 'media', 'totalarticles', 'newlisting', 'sales', 'number_sold'	],
        'column_count' => 8
    ],
];

$dept_key = $_POST['department'];
if (!isset($departments[$dept_key])) {
    exit('不正な部署が選択されています。');
}

$config = $departments[$dept_key];
$table = $config['table'];
$columns = $config['columns'];
$column_count = $config['column_count'];

$csvFile = $_FILES['csv_file']['tmp_name'];
$handle = fopen($csvFile, 'r');
if ($handle === false) {
    exit('ファイルオープンエラー');
}

$pdo->beginTransaction();

try {
    $header = fgetcsv($handle); // ヘッダー読み飛ばし

    $placeholders = array_map(fn($col) => ":$col", $columns);
    $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) < $column_count) continue; // 欠損行スキップ

        foreach ($columns as $i => $col) {
            $stmt->bindValue(":$col", $data[$i]);
        }
        $stmt->execute();
    }

    $pdo->commit();
    fclose($handle);

    echo 'CSVアップロード＆登録完了しました。';
    echo '<br><a href="index.php">戻る</a>';

} catch (Exception $e) {
    $pdo->rollBack();
    fclose($handle);
    exit('DB登録エラー: ' . $e->getMessage());
}
