<?php
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$db = getDb();
$report_type = $_GET['type'] ?? 'bookings';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$bus_code = $_GET['bus_code'] ?? '';

require_once __DIR__ . '/../vendor/autoload.php';

class BusReportPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 10, 'SmartBus Tracker - Report', 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new BusReportPDF('P', 'mm', 'A4', true, true, 'UTF-8');
$pdf->SetCreator('SmartBus Tracker');
$pdf->SetTitle(ucfirst($report_type) . ' Report');
$pdf->setPrintHeader(false);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->SetFont('helvetica', '', 10);

$pdf->AddPage();

$where = "WHERE DATE(bk.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($bus_code) {
    $where .= " AND b.bus_code = ?";
    $params[] = $bus_code;
}

if ($report_type === 'bookings') {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Bookings Report', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, "Period: {$date_from} to {$date_to}" . ($bus_code ? " | Bus: {$bus_code}" : ""), 0, 1);
    $pdf->Ln(3);

    $stmt = $db->prepare("
        SELECT bk.id, bk.booking_date, bk.status, bk.payment_method, bk.amount,
               b.bus_code, b.bus_name, s.seat_number, u.full_name, u.phone, u.email
        FROM bookings bk
        JOIN buses b ON bk.bus_id = b.id
        JOIN seats s ON bk.seat_id = s.id
        JOIN users u ON bk.user_id = u.id
        {$where}
        ORDER BY bk.created_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $header = ['ID', 'Date', 'Passenger', 'Phone', 'Bus', 'Seat', 'Amount', 'Status', 'Payment'];
    $widths = [12, 20, 35, 28, 25, 15, 20, 20, 35];

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(26, 115, 232);
    $pdf->SetTextColor(255);
    foreach ($header as $i => $h) {
        $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0);

    $pdf->SetFont('helvetica', '', 8);
    $total_amount = 0;
    foreach ($rows as $row) {
        $fill = $row['id'] % 2 === 0;
        $pdf->SetFillColor(245, 245, 245);
        $vals = [
            '#' . $row['id'],
            $row['booking_date'],
            substr($row['full_name'], 0, 18),
            $row['phone'],
            $row['bus_code'],
            $row['seat_number'],
            'RWF ' . number_format($row['amount'] ?? 500),
            strtoupper($row['status']),
            $row['payment_method'] ?? '-'
        ];
        foreach ($vals as $i => $v) {
            $pdf->Cell($widths[$i], 6, $v, 1, 0, 'C', $fill);
        }
        $pdf->Ln();
        $total_amount += floatval($row['amount'] ?? 500);
    }

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(array_sum($widths), 8, "Total: RWF " . number_format($total_amount) . " | Bookings: " . count($rows), 1, 1, 'R');

} elseif ($report_type === 'payments') {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Payments Report', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, "Period: {$date_from} to {$date_to}" . ($bus_code ? " | Bus: {$bus_code}" : ""), 0, 1);
    $pdf->Ln(3);

    $stmt = $db->prepare("
        SELECT p.*, b.bus_code, b.bus_name, s.seat_number, u.full_name
        FROM payments p
        JOIN bookings bk ON p.booking_id = bk.id
        JOIN buses b ON bk.bus_id = b.id
        JOIN seats s ON bk.seat_id = s.id
        JOIN users u ON p.user_id = u.id
        {$where}
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $header = ['ID', 'Booking', 'Passenger', 'Bus', 'Seat', 'Amount', 'Method', 'Status', 'Date'];
    $widths = [12, 18, 35, 25, 15, 22, 25, 22, 28];

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(26, 115, 232);
    $pdf->SetTextColor(255);
    foreach ($header as $i => $h) {
        $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0);

    $pdf->SetFont('helvetica', '', 8);
    $total_amount = 0;
    $successful = 0;
    foreach ($rows as $row) {
        $fill = $row['id'] % 2 === 0;
        $pdf->SetFillColor(245, 245, 245);
        $vals = [
            '#' . $row['id'],
            '#' . $row['booking_id'],
            substr($row['full_name'], 0, 18),
            $row['bus_code'],
            $row['seat_number'],
            'RWF ' . number_format($row['amount']),
            $row['payment_method'],
            strtoupper($row['status']),
            substr($row['created_at'], 0, 10)
        ];
        foreach ($vals as $i => $v) {
            $pdf->Cell($widths[$i], 6, $v, 1, 0, 'C', $fill);
        }
        $pdf->Ln();
        $total_amount += $row['amount'];
        if ($row['status'] === 'successful') $successful++;
    }

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(array_sum($widths), 8, "Total: RWF " . number_format($total_amount) . " | Successful: {$successful} | Total: " . count($rows), 1, 1, 'R');

} elseif ($report_type === 'drivers') {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Drivers Report', 0, 1, 'L');
    $pdf->Ln(3);

    $stmt = $db->query("SELECT d.*, b.bus_code, b.bus_name FROM drivers d LEFT JOIN buses b ON d.assigned_bus_id = b.id ORDER BY d.full_name");
    $rows = $stmt->fetchAll();

    $header = ['ID', 'Name', 'Phone', 'License', 'Assigned Bus', 'Status'];
    $widths = [12, 45, 30, 35, 35, 30];

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(26, 115, 232);
    $pdf->SetTextColor(255);
    foreach ($header as $i => $h) {
        $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();
    $pdf->SetTextColor(0);

    $pdf->SetFont('helvetica', '', 9);
    foreach ($rows as $row) {
        $fill = $row['id'] % 2 === 0;
        $pdf->SetFillColor(245, 245, 245);
        $vals = [
            '#' . $row['id'],
            $row['full_name'],
            $row['phone'],
            $row['license_number'],
            $row['bus_code'] ?? 'Unassigned',
            strtoupper($row['status'])
        ];
        foreach ($vals as $i => $v) {
            $pdf->Cell($widths[$i], 7, $v, 1, 0, 'C', $fill);
        }
        $pdf->Ln();
    }

} elseif ($report_type === 'summary') {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Summary Report', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, "Period: {$date_from} to {$date_to}", 0, 1);
    $pdf->Ln(5);

    $stats = [];
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where}");
    $stmt->execute($params);
    $stats['total_bookings'] = $stmt->fetch()['c'];

    $where_paid = str_replace('bk.created_at', 'bk.created_at', $where) . " AND bk.status = 'paid'";
    $stmt = $db->prepare("SELECT COUNT(*) as c, SUM(bk.amount) as total FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where_paid}");
    $stmt->execute($params);
    $paid = $stmt->fetch();
    $stats['paid_bookings'] = $paid['c'];
    $stats['total_revenue'] = $paid['total'] ?? 0;

    $where_pending = str_replace('bk.created_at', 'bk.created_at', $where) . " AND bk.status = 'pending'";
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where_pending}");
    $stmt->execute($params);
    $stats['pending_bookings'] = $stmt->fetch()['c'];

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM payments WHERE status = 'successful' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $stats['successful_payments'] = $stmt->fetch()['c'];

    $pdf->SetFont('helvetica', 'B', 12);
    $summary_data = [
        ['Total Bookings', $stats['total_bookings']],
        ['Paid Bookings', $stats['paid_bookings']],
        ['Pending Bookings', $stats['pending_bookings']],
        ['Successful Payments', $stats['successful_payments']],
        ['Total Revenue', 'RWF ' . number_format($stats['total_revenue'])]
    ];

    foreach ($summary_data as $item) {
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell(90, 10, $item[0], 1, 0, 'L', true);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(80, 10, (string) $item[1], 1, 1, 'C', true);
        $pdf->SetFont('helvetica', 'B', 12);
    }
}

$pdf_path = sys_get_temp_dir() . "/report_{$report_type}_" . date('Ymd_His') . ".pdf";
$pdf->Output($pdf_path, 'F');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.pdf"');
header('Content-Length: ' . filesize($pdf_path));
readfile($pdf_path);
unlink($pdf_path);
exit;
