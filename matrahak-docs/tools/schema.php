<?php
// يستخرج مخطط قاعدة البيانات الفعلي (الجداول، الأعمدة، المفاتيح، الفهارس، عدد الصفوف) بصيغة JSON.
// الاستخدام من أي مجلد:
//   php schema.php smart_city_db > schema.json
// (الاتصال: 127.0.0.1:3306 بالمستخدم root بلا كلمة مرور — عدّل السطر التالي إن اختلف عندك)
$db = $argv[1] ?? 'smart_city_db';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=information_schema;charset=utf8mb4', 'root', '');
$q = function (string $sql, array $p = []) use ($pdo) {
    $s = $pdo->prepare($sql);
    $s->execute($p);

    return $s->fetchAll(PDO::FETCH_ASSOC);
};
$out = [];
foreach ($q('SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME', [$db]) as $t) {
    $name = $t['TABLE_NAME'];
    $out[] = [
        'name' => $name,
        'rows' => (int) $pdo->query("SELECT COUNT(*) FROM `$db`.`$name`")->fetchColumn(),
        'engine' => $t['ENGINE'],
        'collation' => $t['TABLE_COLLATION'],
        'columns' => $q('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT FROM COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$db, $name]),
        'foreign_keys' => $q('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE, k.CONSTRAINT_NAME FROM KEY_COLUMN_USAGE k JOIN REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=? AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL', [$db, $name]),
        'indexes' => $q('SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols, INDEX_TYPE FROM STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE', [$db, $name]),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
