<?php
/**
 * EventHub Pro — Rapport PDF organisateur.
 *
 * Choix de bibliothèque :
 * J’ai choisi TCPDF car il permet de créer des PDF structurés multi-pages,
 * d’ajouter facilement des images, tableaux, QR codes, en-têtes/pieds de page,
 * et surtout de dessiner un graphique en barres directement en PHP avec les
 * primitives Rect(), Line(), Cell(), SetFillColor(), sans générer d’image externe.
 *
 * QR code :
 * Le rapport n’a pas besoin de QR code. Le QR code du ticket est généré dans
 * pdf/ticket.php avec write2DBarcode(), directement par TCPDF.
 *
 * Graphique :
 * Le graphique en barres est dessiné sans image externe avec Rect(), Line(),
 * Cell(), SetFillColor(), SetDrawColor() et SetXY().
 *
 * Modes :
 * - mode I : afficher dans le navigateur ;
 * - mode D : forcer le téléchargement ;
 * - mode F : sauvegarder dans pdf/samples/report_example.pdf ou dans le chemin
 *   fourni à la fonction.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Génère le rapport PDF d'un événement.
 *
 * @param PDO    $pdo
 * @param int    $eventId
 * @param string $mode 'I' navigateur, 'D' téléchargement, 'F' fichier
 * @param string $filePath Chemin de sortie si mode F
 * @return string|bool
 */
function generateEventReportPdf(PDO $pdo, int $eventId, string $mode = 'I', string $filePath = ''): string|bool
{
    $mode = strtoupper($mode);
    if (!in_array($mode, ['I', 'D', 'F'], true)) {
        $mode = 'I';
    }

    $report = loadReportData($pdo, $eventId);
    $event = $report['event'];
    $category = $report['category'];
    [$r, $g, $b] = eventhubHexToRgb($category['primary']);

    $pdf = new EventHubPdf('P', 'A4');
    $pdf->SetTitle('Rapport de gestion — EventHub Pro');
    $pdf->SetSubject('Rapport organisateur');

    drawReportSummaryPage($pdf, $event, $category, $report, [$r, $g, $b]);
    drawRegistrationsPage($pdf, $report['registrations'], $report['has_status'], [$r, $g, $b]);
    drawStatisticsPage($pdf, $report['stats_by_day'], [$r, $g, $b]);

    $filename = 'rapport_event_' . $eventId . '.pdf';

    if ($mode === 'F') {
        if ($filePath === '') {
            $filePath = eventhubEnsureSamplesDir() . '/report_example.pdf';
        }

        eventhubEnsureFileDirectory($filePath);
        $pdf->Output($filePath, 'F');
        return $filePath;
    }

    $pdf->Output($filename, $mode);
    return true;
}

/**
 * Compatibilité avec le code existant de la Partie 2.
 */
function generateReportPDF(PDO $pdo, int $eventId, string $output = 'D', string $filePath = ''): string|bool
{
    return generateEventReportPdf($pdo, $eventId, $output, $filePath);
}

function loadReportData(PDO $pdo, int $eventId): array
{
    if ($eventId <= 0) {
        throw new InvalidArgumentException('Identifiant d’événement invalide.');
    }

    $hasDescription = eventhubColumnExists($pdo, 'events', 'description');
    $hasCategory = eventhubColumnExists($pdo, 'events', 'category');
    $hasCategoryId = eventhubColumnExists($pdo, 'events', 'category_id');
    $hasPrice = eventhubColumnExists($pdo, 'events', 'price');
    $hasOrganizerId = eventhubColumnExists($pdo, 'events', 'organizer_id');
    $hasStatus = eventhubColumnExists($pdo, 'registrations', 'status');

    $select = [
        'e.id',
        'e.title',
        $hasDescription ? 'e.description' : "'' AS description",
        'e.event_date',
        'e.location',
        'e.capacity',
        'e.organizer_email',
        $hasCategory ? 'e.category' : "'' AS category",
        $hasCategoryId ? 'e.category_id' : 'NULL AS category_id',
        $hasPrice ? 'e.price' : 'NULL AS price',
        $hasOrganizerId ? 'e.organizer_id' : 'NULL AS organizer_id',
    ];

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM events e WHERE e.id = :id LIMIT 1');
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        throw new RuntimeException('Événement introuvable.');
    }

    $activeSql = $hasStatus ? " AND status = 'active'" : '';

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE event_id = :id' . $activeSql);
    $stmt->execute([':id' => $eventId]);
    $registeredCount = (int)$stmt->fetchColumn();

    $registrations = loadReportRegistrations($pdo, $eventId, $hasStatus);
    $statsByDay = loadReportStatsByDay($pdo, $eventId, $hasStatus);
    $capacity = max(1, (int)$event['capacity']);
    $available = max(0, $capacity - $registeredCount);
    $fillPct = (int)round(($registeredCount / $capacity) * 100);
    $revenue = 'Non applicable';

    if ($hasPrice && $event['price'] !== null && is_numeric($event['price'])) {
        $revenue = number_format((float)$event['price'] * $registeredCount, 2, ',', ' ') . ' MAD';
    }

    return [
        'event'            => $event,
        'category'         => eventhubCategoryMeta($pdo, $event),
        'registrations'    => $registrations,
        'stats_by_day'     => $statsByDay,
        'registered_count' => $registeredCount,
        'available_places' => $available,
        'fill_pct'         => $fillPct,
        'revenue'          => $revenue,
        'has_status'       => $hasStatus,
    ];
}

function loadReportRegistrations(PDO $pdo, int $eventId, bool $hasStatus): array
{
    $select = [
        'id',
        'name',
        'email',
        'registered_at',
        $hasStatus ? 'status' : "'active' AS status",
    ];

    $stmt = $pdo->prepare(
        'SELECT ' . implode(', ', $select) . '
         FROM registrations
         WHERE event_id = :id
         ORDER BY name ASC, registered_at ASC'
    );
    $stmt->execute([':id' => $eventId]);
    return $stmt->fetchAll();
}

function loadReportStatsByDay(PDO $pdo, int $eventId, bool $hasStatus): array
{
    $activeSql = $hasStatus ? " AND status = 'active'" : '';
    $stmt = $pdo->prepare(
        'SELECT DATE(registered_at) AS day,
                COUNT(*) AS count
         FROM registrations
         WHERE event_id = :id' . $activeSql . '
         GROUP BY DATE(registered_at)
         ORDER BY day ASC'
    );
    $stmt->execute([':id' => $eventId]);
    return $stmt->fetchAll();
}

function drawReportSummaryPage(EventHubPdf $pdf, array $event, array $category, array $report, array $rgb): void
{
    [$r, $g, $b] = $rgb;
    $pdf->AddPage();

    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetFont('dejavusans', 'B', 20);
    $pdf->Cell(0, 10, 'Rapport de gestion — EventHub Pro', 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->MultiCell(0, 6, eventhubShortText($event['description'] ?? '', 220), 0, 'L', false, 1);

    $pdf->Ln(4);
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 13);
    $pdf->Cell(0, 10, (string)$event['title'], 0, 1, 'L', true);

    $rows = [
        'Date'                  => eventhubFormatDate((string)$event['event_date']),
        'Lieu'                  => (string)$event['location'],
        'Catégorie'             => (string)$category['label'],
        'Capacité'              => (string)$event['capacity'],
        'Nombre d’inscrits'     => (string)$report['registered_count'],
        'Places restantes'      => (string)$report['available_places'],
        'Taux de remplissage'   => $report['fill_pct'] . ' %',
        'Revenu estimé'         => (string)$report['revenue'],
        'Date de génération'    => date('d/m/Y à H:i'),
    ];

    $pdf->Ln(6);
    foreach ($rows as $label => $value) {
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetTextColor(15, 31, 61);
        $pdf->Cell(55, 8, $label, 1, 0, 'L', false);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(51, 65, 85);
        $pdf->Cell(0, 8, $value, 1, 1, 'L', false);
    }

    $pdf->Ln(10);
    drawReportKpiCards($pdf, [
        ['Capacité', (string)$event['capacity']],
        ['Inscrits', (string)$report['registered_count']],
        ['Restantes', (string)$report['available_places']],
        ['Remplissage', $report['fill_pct'] . '%'],
    ], $rgb);
}

function drawReportKpiCards(EventHubPdf $pdf, array $cards, array $rgb): void
{
    [$r, $g, $b] = $rgb;
    $x = 15;
    $y = $pdf->GetY();
    $w = 42;

    foreach ($cards as $card) {
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->RoundedRect($x, $y, $w, 24, 2, '1111', 'DF');
        $pdf->SetXY($x + 3, $y + 4);
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell($w - 6, 5, $card[0], 0, 1, 'C');
        $pdf->SetXY($x + 3, $y + 11);
        $pdf->SetFont('dejavusans', 'B', 15);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->Cell($w - 6, 8, $card[1], 0, 1, 'C');
        $x += $w + 5;
    }
}

function drawRegistrationsPage(EventHubPdf $pdf, array $registrations, bool $hasStatus, array $rgb): void
{
    $pdf->AddPage();
    drawRegistrationsTitle($pdf, count($registrations), $hasStatus);
    drawRegistrationsHeader($pdf, $hasStatus, $rgb);

    if (empty($registrations)) {
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 10, 'Aucun inscrit pour cet événement.', 0, 1, 'L');
        return;
    }

    $i = 1;
    foreach ($registrations as $row) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
            drawRegistrationsTitle($pdf, count($registrations), $hasStatus);
            drawRegistrationsHeader($pdf, $hasStatus, $rgb);
        }

        drawRegistrationRow($pdf, $i, $row, $hasStatus);
        $i++;
    }
}

function drawRegistrationsTitle(EventHubPdf $pdf, int $count, bool $hasStatus): void
{
    $pdf->SetFont('dejavusans', 'B', 17);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->Cell(0, 10, 'Liste des inscrits', 0, 1, 'L');
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $suffix = $hasStatus ? ' · colonne statut détectée' : '';
    $pdf->Cell(0, 6, $count . ' inscription(s)' . $suffix, 0, 1, 'L');
    $pdf->Ln(3);
}

function drawRegistrationsHeader(EventHubPdf $pdf, bool $hasStatus, array $rgb): void
{
    [$r, $g, $b] = $rgb;
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->Cell(12, 8, 'N°', 1, 0, 'C', true);
    $pdf->Cell(44, 8, 'Nom', 1, 0, 'L', true);
    $pdf->Cell(62, 8, 'Email', 1, 0, 'L', true);
    $pdf->Cell(36, 8, 'Date inscription', 1, 0, 'L', true);
    if ($hasStatus) {
        $pdf->Cell(26, 8, 'Statut', 1, 1, 'L', true);
    } else {
        $pdf->Cell(26, 8, 'Statut', 1, 1, 'L', true);
    }
}

function drawRegistrationRow(EventHubPdf $pdf, int $i, array $row, bool $hasStatus): void
{
    $fill = $i % 2 === 0;
    $pdf->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetFont('dejavusans', '', 8);
    $pdf->Cell(12, 7, (string)$i, 1, 0, 'C', $fill);
    $pdf->Cell(44, 7, eventhubShortText((string)$row['name'], 28), 1, 0, 'L', $fill);
    $pdf->Cell(62, 7, eventhubShortText((string)$row['email'], 42), 1, 0, 'L', $fill);
    $pdf->Cell(36, 7, eventhubFormatDate((string)$row['registered_at']), 1, 0, 'L', $fill);
    $pdf->Cell(26, 7, $hasStatus ? (string)$row['status'] : 'active', 1, 1, 'L', $fill);
}

function drawStatisticsPage(EventHubPdf $pdf, array $statsByDay, array $rgb): void
{
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', 'B', 17);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->Cell(0, 10, 'Statistiques visuelles', 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->MultiCell(
        0,
        6,
        'Graphique en barres généré côté PHP avec les primitives TCPDF, sans JavaScript ni image externe.',
        0,
        'L',
        false,
        1
    );

    drawRegistrationsBarChart($pdf, $statsByDay, 25, 70, 160, 95, $rgb);
}

function drawRegistrationsBarChart(EventHubPdf $pdf, array $statsByDay, float $x, float $y, float $w, float $h, array $rgb): void
{
    if (empty($statsByDay)) {
        $pdf->SetXY($x, $y + 20);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell($w, 10, 'Aucune inscription récente à afficher.', 0, 1, 'C');
        return;
    }

    $statsByDay = array_slice($statsByDay, -10);
    $max = max(1, ...array_map(static fn($row) => (int)$row['count'], $statsByDay));
    $barCount = count($statsByDay);
    $gap = 4;
    $barW = max(8, ($w - (($barCount - 1) * $gap)) / $barCount);
    $originY = $y + $h;

    [$r, $g, $b] = $rgb;

    $pdf->SetDrawColor(203, 213, 225);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetFont('dejavusans', '', 7);

    for ($step = 0; $step <= 4; $step++) {
        $value = (int)round($max * $step / 4);
        $lineY = $originY - ($h * $step / 4);
        $pdf->Line($x, $lineY, $x + $w, $lineY);
        $pdf->SetXY($x - 12, $lineY - 2);
        $pdf->Cell(10, 4, (string)$value, 0, 0, 'R');
    }

    $pdf->SetDrawColor(15, 31, 61);
    $pdf->Line($x, $y, $x, $originY);
    $pdf->Line($x, $originY, $x + $w, $originY);

    foreach ($statsByDay as $index => $row) {
        $count = (int)$row['count'];
        $barH = ($count / $max) * ($h - 8);
        $barX = $x + ($index * ($barW + $gap));
        $barY = $originY - $barH;

        $pdf->SetFillColor($r, $g, $b);
        $pdf->Rect($barX, $barY, $barW, $barH, 'F');

        $pdf->SetFont('dejavusans', 'B', 7);
        $pdf->SetTextColor(15, 31, 61);
        $pdf->SetXY($barX, $barY - 6);
        $pdf->Cell($barW, 4, (string)$count, 0, 0, 'C');

        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetXY($barX - 2, $originY + 2);
        $pdf->Cell($barW + 4, 4, date('d/m', strtotime((string)$row['day'])), 0, 0, 'C');
    }

    $pdf->SetFont('dejavusans', '', 8);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetXY($x, $originY + 12);
    $pdf->Cell($w, 5, 'Nombre d’inscriptions par jour', 0, 1, 'C');
}

if (php_sapi_name() !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    try {
        $eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
        if (!$eventId) {
            throw new InvalidArgumentException('Paramètre event_id invalide.');
        }

        $pdo = getDB();

        if (isset($_GET['save']) && $_GET['save'] === '1') {
            $path = generateEventReportPdf($pdo, (int)$eventId, 'F');
            eventhubStreamSavedPdf((string)$path, 'report_example.pdf');
            exit;
        }

        $mode = (isset($_GET['download']) && $_GET['download'] === '1') ? 'D' : 'I';
        generateEventReportPdf($pdo, (int)$eventId, $mode);
    } catch (Throwable $e) {
        error_log('[EventHub] report.php: ' . $e->getMessage());
        eventhubPdfError($e instanceof InvalidArgumentException ? $e->getMessage() : 'Impossible de générer le rapport.', 400);
    }
}

