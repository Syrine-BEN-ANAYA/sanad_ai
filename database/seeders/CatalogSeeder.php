<?php

declare(strict_types=1);

namespace SanadAi\Database\Seeders;

use PDO;
use SanadAi\Support\Uuid;

final class CatalogSeeder
{
    /**
     * @return list<string>
     */
    public static function run(PDO $pdo): array
    {
        $rows = [
            ['name_ar' => 'وزارة الاقتصاد', 'name_en' => 'Ministry of Economy', 'code' => 'ECON'],
            ['name_ar' => 'وزارة الصحة', 'name_en' => 'Ministry of Health', 'code' => 'HEALTH'],
            ['name_ar' => 'وزارة النقل', 'name_en' => 'Ministry of Transport', 'code' => 'TRANS'],
            ['name_ar' => 'وزارة التجارة', 'name_en' => 'Ministry of Commerce', 'code' => 'TRADE'],
            ['name_ar' => 'وزارة الإسكان', 'name_en' => 'Ministry of Housing', 'code' => 'HOUSING'],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO ministries (public_id, name_ar, name_en, code, status)
            VALUES (:public_id, :name_ar, :name_en, :code, "active")
            ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), name_en = VALUES(name_en), code = VALUES(code), status = VALUES(status)'
        );

        $ids = [];
        foreach ($rows as $row) {
            $stmt->execute([
                'public_id' => Uuid::v4(),
                'name_ar' => $row['name_ar'],
                'name_en' => $row['name_en'],
                'code' => $row['code'],
            ]);
            $ids[] = $row['code'];
        }

        return $ids;
    }
}
