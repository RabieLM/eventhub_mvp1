<?php
/**
 * EventHub Pro — Ticket PDF d'inscription.
 *
 * Choix de bibliothèque :
 * J’ai choisi TCPDF car il permet de créer des PDF structurés multi-pages,
 * d’ajouter facilement des images, tableaux, QR codes, en-têtes/pieds de page,
 * et surtout de dessiner un graphique en barres directement en PHP avec les
 * primitives Rect(), Line(), Cell(), SetFillColor(), sans générer d’image externe.
 *
 * QR code :
 * Le QR code est généré directement par TCPDF avec write2DBarcode(), sans image
 * temporaire ni librairie externe. Il encode eventId|userId|token si user_id
 * existe, sinon eventId|email|token.
 *
 * Modes :
 * - mode I : afficher dans le navigateur ;
 * - mode D : forcer le téléchargement ;
 * - mode F : sauvegarder dans pdf/samples/ticket_example.pdf ou dans le chemin
 *   fourni à la fonction.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Génère le ticket PDF pour une inscription.
 *
 * @param PDO    $pdo
 * @param int    $registrationId
 * @param string $mode 'I' navigateur, 'D' téléchargement, 'F' fichier
 * @param string $filePath Chemin de sortie si mode F
 * @return string|bool
 */
function generateTicketPdf(PDO $pdo, int $registrationId, string $mode = 'I', string $filePath = ''): string|bool
{
    $mode = strtoupper($mode);
    if (!in_array($mode, ['I', 'D', 'F'], true)) {
        $mode = 'I';
    }

    $data = loadTicketData($pdo, $registrationId);
    $category = eventhubCategoryMeta($pdo, $data);
    [$r, $g, $b] = eventhubHexToRgb($category['primary']);

    $pdf = new EventHubPdf('L', 'A5');
    $pdf->SetTitle('Ticket d’inscription — EventHub Pro');
    $pdf->SetSubject('Ticket EventHub Pro');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);

    drawTicketWatermark($pdf);

    $pageW = $pdf->getPageWidth();
    $pageH = $pdf->getPageHeight();

    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect(0, 22, $pageW, 18, 'F');

    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('dejavusans', 'B', 18);
    $pdf->SetXY(14, 25);
    $pdf->Cell(0, 8, 'Ticket d’inscription — EventHub Pro', 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetXY(14, 34);
    $pdf->Cell(0, 5, 'Statut du ticket : valide', 0, 1, 'L');

    $logo = eventhubLogoPath();
    if ($logo !== '') {
        $pdf->Image($logo, $pageW - 35, 25, 18, 0, '', '', '', false, 300);
    }

    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetFont('dejavusans', 'B', 15);
    $pdf->SetXY(14, 48);
    $pdf->MultiCell(122, 8, (string)$data['event_title'], 0, 'L', false, 1);

    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->SetX(14);
    $pdf->MultiCell(122, 5, eventhubShortText($data['event_description'] ?? '', 160), 0, 'L', false, 1);

    drawTicketInfoBox($pdf, 14, 78, 78, 'Événement', [
        'Date'      => eventhubFormatDate((string)$data['event_date']),
        'Lieu'      => (string)$data['location'],
        'Catégorie' => (string)$category['label'],
    ], [$r, $g, $b]);

    drawTicketInfoBox($pdf, 96, 78, 70, 'Participant', [
        'Nom'          => (string)$data['participant_name'],
        'Email'        => (string)$data['participant_email'],
        'Inscrit le'   => eventhubFormatDate((string)$data['registered_at']),
        'Inscription'  => '#' . str_pad((string)$data['registration_id'], 5, '0', STR_PAD_LEFT),
    ], [$r, $g, $b]);

    $qrPayload = buildTicketQrPayload($data);
    $style = [
        'border'        => 0,
        'vpadding'      => 'auto',
        'hpadding'      => 'auto',
        'fgcolor'       => [15, 31, 61],
        'bgcolor'       => false,
        'module_width'  => 1,
        'module_height' => 1,
    ];

    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFillColor(248, 250, 252);
    $pdf->RoundedRect(171, 50, 30, 40, 2, '1111', 'DF');
    $pdf->write2DBarcode($qrPayload, 'QRCODE,H', 174, 53, 24, 24, $style, 'N');
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetXY(173, 79);
    $pdf->MultiCell(26, 4, 'QR code unique', 0, 'C', false, 1);

    $pdf->SetFillColor(240, 253, 244);
    $pdf->SetDrawColor(34, 197, 94);
    $pdf->RoundedRect(171, 94, 30, 14, 2, '1111', 'DF');
    $pdf->SetTextColor(22, 101, 52);
    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetXY(172, 98);
    $pdf->Cell(28, 5, 'VALIDE', 0, 1, 'C');

    $pdf->SetTextColor(100, 116, 139);
    $pdf->SetFont('dejavusans', '', 7);
    $pdf->SetXY(14, $pageH - 24);
    $pdf->MultiCell(
        150,
        4,
        'Ce ticket est personnel. Présentez-le à l’entrée de l’événement. '
        . 'Généré le ' . date('d/m/Y à H:i') . '.',
        0,
        'L',
        false,
        1
    );

    $filename = 'ticket_' . $registrationId . '.pdf';

    if ($mode === 'F') {
        if ($filePath === '') {
            $filePath = eventhubEnsureSamplesDir() . '/ticket_example.pdf';
        }

        eventhubEnsureFileDirectory($filePath);
        $pdf->Output($filePath, 'F');
        updateTicketPathIfAvailable($pdo, $registrationId, $filePath);
        return $filePath;
    }

    $pdf->Output($filename, $mode);
    return true;
}

function loadTicketData(PDO $pdo, int $registrationId): array
{
    if ($registrationId <= 0) {
        throw new InvalidArgumentException('Identifiant d’inscription invalide.');
    }

    $hasUserId = eventhubColumnExists($pdo, 'registrations', 'user_id');
    $hasStatus = eventhubColumnExists($pdo, 'registrations', 'status');
    $hasTicketPath = eventhubColumnExists($pdo, 'registrations', 'ticket_path');
    $hasEventCategoryId = eventhubColumnExists($pdo, 'events', 'category_id');
    $hasEventCategory = eventhubColumnExists($pdo, 'events', 'category');
    $hasDescription = eventhubColumnExists($pdo, 'events', 'description');

    $select = [
        'r.id AS registration_id',
        'r.event_id',
        'r.name AS registration_name',
        'r.email AS registration_email',
        'r.token',
        'r.registered_at',
        'e.id AS event_id',
        'e.title AS event_title',
        $hasDescription ? 'e.description AS event_description' : "'' AS event_description",
        'e.event_date',
        'e.location',
        'e.capacity',
        $hasEventCategory ? 'e.category' : "'' AS category",
        $hasEventCategoryId ? 'e.category_id' : 'NULL AS category_id',
    ];

    if ($hasUserId) {
        $select[] = 'r.user_id';
        $select[] = 'u.name AS user_name';
    } else {
        $select[] = 'NULL AS user_id';
        $select[] = 'NULL AS user_name';
    }

    $select[] = $hasStatus ? 'r.status' : "'active' AS status";
    $select[] = $hasTicketPath ? 'r.ticket_path' : "'' AS ticket_path";

    $sql = 'SELECT ' . implode(', ', $select)
        . ' FROM registrations r'
        . ' JOIN events e ON e.id = r.event_id';

    if ($hasUserId) {
        $sql .= ' LEFT JOIN users u ON u.id = r.user_id';
    }

    $sql .= ' WHERE r.id = :registration_id LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':registration_id' => $registrationId]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new RuntimeException('Inscription introuvable.');
    }

    $row['participant_name'] = $row['user_name'] ?: $row['registration_name'];
    $row['participant_email'] = $row['registration_email'];

    return $row;
}

function buildTicketQrPayload(array $data): string
{
    $identity = !empty($data['user_id']) ? (string)$data['user_id'] : (string)$data['participant_email'];
    return (string)$data['event_id'] . '|' . $identity . '|' . (string)$data['token'];
}

function drawTicketWatermark(EventHubPdf $pdf): void
{
    if (method_exists($pdf, 'SetAlpha')) {
    $pdf->SetAlpha(0.035);
    }

    $pdf->SetFont('dejavusans', 'B', 30);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->StartTransform();
    $pdf->Rotate(18, 105, 74);
    $pdf->Text(52, 78, 'EventHub Pro');
    $pdf->StopTransform();

    if (method_exists($pdf, 'SetAlpha')) {
        $pdf->SetAlpha(1);
    }
}

function drawTicketInfoBox(EventHubPdf $pdf, float $x, float $y, float $w, string $title, array $rows, array $rgb): void
{
    [$r, $g, $b] = $rgb;
    $pdf->SetDrawColor(226, 232, 240);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->RoundedRect($x, $y, $w, 42, 2, '1111', 'DF');

    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect($x, $y, $w, 5, 'F');

    $pdf->SetXY($x + 4, $y + 9);
    $pdf->SetTextColor(15, 31, 61);
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->Cell($w - 8, 5, $title, 0, 1, 'L');

    $pdf->SetFont('dejavusans', '', 7.5);
    $currentY = $y + 17;
    foreach ($rows as $label => $value) {
        $pdf->SetXY($x + 4, $currentY);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(22, 4, $label . ' :', 0, 0, 'L');
        $pdf->SetTextColor(15, 31, 61);
        $pdf->MultiCell($w - 30, 4, (string)$value, 0, 'L', false, 1);
        $currentY = max($currentY + 5, $pdf->GetY());
    }
}

function updateTicketPathIfAvailable(PDO $pdo, int $registrationId, string $path): void
{
    if (!eventhubColumnExists($pdo, 'registrations', 'ticket_path')) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE registrations SET ticket_path = :path WHERE id = :id');
    $stmt->execute([
        ':path' => $path,
        ':id'   => $registrationId,
    ]);
}

if (php_sapi_name() !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    try {
        $registrationId = filter_input(INPUT_GET, 'registration_id', FILTER_VALIDATE_INT);
        if (!$registrationId) {
            throw new InvalidArgumentException('Paramètre registration_id invalide.');
        }

        $pdo = getDB();

        if (isset($_GET['save']) && $_GET['save'] === '1') {
            $path = generateTicketPdf($pdo, (int)$registrationId, 'F');
            eventhubStreamSavedPdf((string)$path, 'ticket_example.pdf');
            exit;
        }

        $mode = (isset($_GET['download']) && $_GET['download'] === '1') ? 'D' : 'I';
        generateTicketPdf($pdo, (int)$registrationId, $mode);
    } catch (Throwable $e) {
        error_log('[EventHub] ticket.php: ' . $e->getMessage());
        eventhubPdfError($e instanceof InvalidArgumentException ? $e->getMessage() : 'Impossible de générer le ticket.', 400);
    }
}

