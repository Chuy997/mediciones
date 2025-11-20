<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Usar conexión central
require_once __DIR__.'/../../calibraciones/config.php';
$pdo = pdo();

try {
    // Últimos 7 días
    $stmt = $pdo->query("
        SELECT 
            UNIX_TIMESTAMP(fecha) * 1000 AS ts,
            posicion,
            particulas_0_5_um,
            particulas_5_0_um
        FROM registros
        WHERE fecha >= CURDATE() - INTERVAL 7 DAY
        ORDER BY fecha ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por día (timestamp de medianoche en ms)
    $data = [];
    foreach ($rows as $r) {
        $day = (int)($r['ts'] - ($r['ts'] % 86400000)); // redondear a día
        if (!isset($data[$day])) {
            $data[$day] = [
                '0_5' => ['Corner1'=>0,'Corner2'=>0,'Corner3'=>0,'Corner4'=>0,'Middle'=>0,'count'=>0],
                '5_0' => ['Corner1'=>0,'Corner2'=>0,'Corner3'=>0,'Corner4'=>0,'Middle'=>0,'count'=>0]
            ];
        }
        $pos = $r['posicion'];
        $data[$day]['0_5'][$pos] += (int)$r['particulas_0_5_um'];
        $data[$day]['5_0'][$pos] += (int)$r['particulas_5_0_um'];
        $data[$day]['0_5']['count']++;
        $data[$day]['5_0']['count']++;
    }

    // Convertir a promedios y estructura final
    ksort($data);
    $labels = [];
    $series05 = ['Corner1'=>[],'Corner2'=>[],'Corner3'=>[],'Corner4'=>[],'Middle'=>[]];
    $series50 = ['Corner1'=>[],'Corner2'=>[],'Corner3'=>[],'Corner4'=>[],'Middle'=>[]];

    foreach ($data as $ts => $day) {
        $labels[] = $ts;
        foreach (['Corner1','Corner2','Corner3','Corner4','Middle'] as $p) {
            $avg05 = $day['0_5']['count'] ? $day['0_5'][$p] / ($day['0_5']['count']/5) : 0;
            $avg50 = $day['5_0']['count'] ? $day['5_0'][$p] / ($day['5_0']['count']/5) : 0;
            $series05[$p][] = (int)$avg05;
            $series50[$p][] = (int)$avg50;
        }
    }

    echo json_encode([
        'status' => 'success',
        'labels' => $labels,
        'series05' => $series05,
        'series50' => $series50,
        'limit05' => 10500000,
        'limit50' => 87900
    ], JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
