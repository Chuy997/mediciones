<?php
// /mediciones/api/summary7d.php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

try {
  require_once __DIR__ . '/../conexion.php';   // crea $conn (mysqli) a BD mediciones_particulas
  if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new RuntimeException('No se pudo obtener conexión mysqli desde conexion.php');
  }
  $conn->set_charset('utf8mb4');

  // --- Config ---
  // Días a consultar (por defecto 7). Permite overwrite con ?days=30 (1..180)
  $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
  if ($days < 1)   $days = 1;
  if ($days > 180) $days = 180;

  // Límite visual (ajústalos si tu umbral oficial es distinto)
  $LIMIT_05 = 11000000; // partículas 0.5 µm
  $LIMIT_50 = 90000;    // partículas 5.0 µm

  // Posiciones esperadas (si aparece alguna extra en DB, también la incluimos dinámicamente)
  $EXPECTED_POS = ['Corner1','Corner2','Corner3','Corner4','Middle'];

  // Rango de fechas: hoy incluido, hacia atrás (days-1)
  $today = new DateTimeImmutable('today'); // sin tiempo
  $start = $today->sub(new DateInterval('P'.($days-1).'D'))->format('Y-m-d');
  $end   = $today->format('Y-m-d');

  // Query: promedio por día y posición (si hay varios turnos en un día, promediamos)
  $sql = "
    SELECT
      fecha,
      posicion,
      AVG(particulas_0_5_um) AS avg05,
      AVG(particulas_5_0_um) AS avg50
    FROM registros
    WHERE fecha BETWEEN ? AND ?
    GROUP BY fecha, posicion
    ORDER BY fecha ASC
  ";
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new RuntimeException('Prepare error: '.$conn->error);
  $stmt->bind_param('ss', $start, $end);
  if (!$stmt->execute()) throw new RuntimeException('Execute error: '.$stmt->error);
  $res = $stmt->get_result();

  // Construir eje X (todas las fechas del rango)
  $labels = [];
  $idxByDate = [];
  for ($d = new DateTimeImmutable($start), $i=0; $d <= $today; $d = $d->add(new DateInterval('P1D')), $i++) {
    // epoch ms a medianoche local
    $ts = $d->getTimestamp() * 1000;
    $labels[] = $ts;
    $idxByDate[$d->format('Y-m-d')] = $i;
  }

  // Detectar posiciones presentes en DB además de las esperadas
  $positions = $EXPECTED_POS;

  // Inicializar matrices (series por posición)
  $series05 = [];
  $series50 = [];
  foreach ($positions as $p) {
    $series05[$p] = array_fill(0, count($labels), null);
    $series50[$p] = array_fill(0, count($labels), null);
  }

  // Volcar resultados
  while ($row = $res->fetch_assoc()) {
    $fecha    = (string)$row['fecha'];
    $pos      = (string)$row['posicion'];
    $avg05    = isset($row['avg05']) ? (float)$row['avg05'] : null;
    $avg50    = isset($row['avg50']) ? (float)$row['avg50'] : null;

    // Si aparece una posición no prevista, la añadimos y prellenamos
    if (!isset($series05[$pos])) {
      $series05[$pos] = array_fill(0, count($labels), null);
      $series50[$pos] = array_fill(0, count($labels), null);
      $positions[] = $pos;
    }

    if (isset($idxByDate[$fecha])) {
      $i = $idxByDate[$fecha];
      $series05[$pos][$i] = $avg05;
      $series50[$pos][$i] = $avg50;
    }
  }

  echo json_encode([
    'status'   => 'success',
    'labels'   => $labels,     // epoch ms por día
    'series05' => $series05,   // por posición
    'series50' => $series50,   // por posición
    'limit05'  => $LIMIT_05,
    'limit50'  => $LIMIT_50,
    'updated_at' => date('c'),
    'range'    => ['start'=>$start, 'end'=>$end, 'days'=>$days],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
