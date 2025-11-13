<?php
declare(strict_types=1);

/**
 * Backfill de registros (polvo) por día y posición.
 * - Omite domingos.
 * - Omite festivos definidos en $FESTIVOS.
 * - Evita duplicados.
 * - Usa el último valor real por posición y simula mediciones humanas:
 *     • Variación relativa suave (±2%)
 *     • Ruido absoluto adicional (±0.5% del valor base)
 *     • Límites suaves para evitar valores absurdos
 */

if (php_sapi_name() !== 'cli') { fwrite(STDERR, "Ejecuta desde CLI\n"); exit(1); }

require_once __DIR__ . '/../conexion.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
  fwrite(STDERR, "No se pudo conectar (conexion.php)\n"); exit(1);
}
$conn->set_charset('utf8mb4');

// ====== CONFIGURACIÓN ======
$DEFAULT_POSITIONS = ['Corner1','Corner2','Corner3','Corner4','Middle'];
$DEFAULT_SHIFTS    = [1];
$SKIP_SUNDAY       = true;

$FESTIVOS = [
  '2025-01-01','2025-02-03','2025-03-17',
  '2025-04-17','2025-04-18','2025-05-01',
  '2025-09-16','2025-11-17','2025-12-25',
];

$DAYS_DEFAULT = 7;

// Valores por defecto si no hay historial
$FALLBACK = [
  'particulas_0_5_um' => 3400000,
  'particulas_5_0_um' =>   55000,
];

// Parámetros de realismo
$RELATIVE_VARIATION = 0.02;   // ±2% de variación relativa
$ABSOLUTE_NOISE_PCT = 0.005;  // ±0.5% de ruido absoluto adicional
$HARD_MIN_05 = 2000000;       // Límite físico razonable
$HARD_MAX_05 = 12000000;
$HARD_MIN_50 = 8000;
$HARD_MAX_50 = 90000;

// ====== PARSE ARGS ======
$args = [];
foreach ($argv as $a) {
  if (preg_match('/^--([^=]+)=(.*)$/', $a, $m)) { $args[$m[1]] = $m[2]; }
  elseif ($a === '--dry-run') { $args['dry-run'] = '1'; }
}

$today = new DateTimeImmutable('today');
if (!empty($args['start']) && !empty($args['end'])) {
  $start = new DateTimeImmutable($args['start']);
  $end   = new DateTimeImmutable($args['end']);
} else {
  $days  = isset($args['days']) ? max(1, min(180, (int)$args['days'])) : $DAYS_DEFAULT;
  $start = $today->sub(new DateInterval('P'.($days-1).'D'));
  $end   = $today;
}
if ($end < $start) { [$start,$end] = [$end,$start]; }

$positions = $DEFAULT_POSITIONS;
if (!empty($args['positions'])) {
  $positions = array_values(array_filter(array_map('trim', explode(',', $args['positions']))));
}

$shifts = $DEFAULT_SHIFTS;
if (!empty($args['shifts'])) {
  $shifts = array_values(array_filter(array_map('intval', explode(',', $args['shifts']))));
  if (!$shifts) $shifts = [1];
}

if (isset($args['skip-sunday'])) { $SKIP_SUNDAY = (bool)(int)$args['skip-sunday']; }

$DRY = !empty($args['dry-run']);

// ====== FUNCIONES ======
function isHoliday(DateTimeImmutable $d, array $festivos): bool {
  return in_array($d->format('Y-m-d'), $festivos, true);
}

function isSunday(DateTimeImmutable $d): bool {
  return $d->format('N') === '7';
}

function applyRealisticVariation(int $base, float $relPct, float $absPct, int $hardMin, int $hardMax): int {
  // Variación relativa (±2%)
  $relDelta = $base * $relPct;
  $val = $base + random_int(-(int)$relDelta, (int)$relDelta);
  
  // Ruido absoluto adicional (±0.5% del valor base)
  $absNoise = (int)($base * $absPct);
  $val += random_int(-$absNoise, $absNoise);
  
  // Aplicar límites duros
  $val = max($hardMin, min($hardMax, $val));
  
  return (int)round($val);
}

// ====== CARGAR ÚLTIMOS VALORES POR POSICIÓN ======
$lastValues = [];
$placeholders = str_repeat('?,', count($positions) - 1) . '?';
$stmtLast = $conn->prepare("
  SELECT posicion, particulas_0_5_um, particulas_5_0_um
  FROM registros
  WHERE (posicion, fecha) IN (
    SELECT posicion, MAX(fecha)
    FROM registros
    WHERE posicion IN ($placeholders)
    GROUP BY posicion
  )
");
if (!$stmtLast) {
  fwrite(STDERR, "Error preparing last-values query: " . $conn->error . "\n");
  exit(1);
}

$stmtLast->bind_param(str_repeat('s', count($positions)), ...$positions);
$stmtLast->execute();
$result = $stmtLast->get_result();
while ($row = $result->fetch_assoc()) {
  $lastValues[$row['posicion']] = [
    'particulas_0_5_um' => (int)$row['particulas_0_5_um'],
    'particulas_5_0_um' => (int)$row['particulas_5_0_um'],
  ];
}
$stmtLast->close();

foreach ($positions as $pos) {
  if (!isset($lastValues[$pos])) {
    $lastValues[$pos] = $FALLBACK;
    echo "WARN: sin historial para '$pos', usando valores por defecto.\n";
  }
}

// ====== PREPARED STATEMENTS ======
$check = $conn->prepare("SELECT COUNT(*) FROM registros WHERE fecha=? AND posicion=?");
$ins   = $conn->prepare("INSERT INTO registros (fecha, turno, posicion, particulas_0_5_um, particulas_5_0_um)
                         VALUES (?,?,?,?,?)");
if (!$check || !$ins) {
  fwrite(STDERR, "Prepare error: " . $conn->error . "\n");
  exit(1);
}

$inserted = 0; $skipped = 0;

for ($d = $start; $d <= $end; $d = $d->add(new DateInterval('P1D'))) {
  $ymd = $d->format('Y-m-d');

  if ($SKIP_SUNDAY && isSunday($d)) { echo "SKIP domingo $ymd\n"; continue; }
  if (isHoliday($d, $FESTIVOS))     { echo "SKIP festivo $ymd\n"; continue; }

  foreach ($positions as $pos) {
    $check->bind_param('ss', $ymd, $pos);
    if (!$check->execute()) { fwrite(STDERR, "Check error: " . $check->error . "\n"); exit(1); }
    $count = 0;
    $check->bind_result($count);
    $check->fetch();
    $check->free_result();

    if ($count > 0) { $skipped++; continue; }

    $turno = $shifts[0];
    $base05 = $lastValues[$pos]['particulas_0_5_um'];
    $base50 = $lastValues[$pos]['particulas_5_0_um'];

    // Generar valores con variación realista
    $v05 = applyRealisticVariation($base05, $RELATIVE_VARIATION, $ABSOLUTE_NOISE_PCT, $HARD_MIN_05, $HARD_MAX_05);
    $v50 = applyRealisticVariation($base50, $RELATIVE_VARIATION, $ABSOLUTE_NOISE_PCT, $HARD_MIN_50, $HARD_MAX_50);

    // Actualizar el valor base para el siguiente día (simulación secuencial)
    $lastValues[$pos]['particulas_0_5_um'] = $v05;
    $lastValues[$pos]['particulas_5_0_um'] = $v50;

    if ($DRY) {
      echo "DRY  insert: fecha=$ymd turno=$turno pos=$pos  0.5um=$v05  5.0um=$v50\n";
    } else {
      $ins->bind_param('sissi', $ymd, $turno, $pos, $v05, $v50);
      if (!$ins->execute()) { fwrite(STDERR, "Insert error: " . $ins->error . "\n"); exit(1); }
      $inserted++;
      echo "OK   insert: $ymd $pos (t$turno) -> 0.5=$v05  5.0=$v50\n";
    }
  }
}

echo "\nResumen: inserted=$inserted skipped_existing=$skipped\n";

// php /var/www/html/mediciones/tools/backfill.php --days=5 