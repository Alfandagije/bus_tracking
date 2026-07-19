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
$mode = $_GET['mode'] ?? 'download';

if (!in_array($report_type, ['bookings', 'payments', 'drivers', 'summary'])) {
    $report_type = 'bookings';
}

require_once __DIR__ . '/../vendor/autoload.php';

class BusReportPDF extends TCPDF {
    public $reportTitle = 'Report';
    public $dateFrom = '';
    public $dateTo = '';
    public $busCode = '';

    public function Header() {
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(26, 115, 232);
        $this->Cell(0, 12, 'SmartBus Tracker', 0, 1, 'C');
        $this->SetTextColor(0);
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, $this->reportTitle, 0, 1, 'C');
        $this->SetFont('helvetica', '', 10);
        $period = "Period: {$this->dateFrom} to {$this->dateTo}";
        if ($this->busCode) $period .= " | Bus: {$this->busCode}";
        $this->Cell(0, 6, $period, 0, 1, 'C');
        $this->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $this->Ln(3);
        $this->SetDrawColor(26, 115, 232);
        $this->SetLineWidth(0.5);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'SmartBus Tracker Report | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }

    public function TableHeader($headers, $widths) {
        $this->SetFont('helvetica', 'B', 8);
        $this->SetFillColor(26, 115, 232);
        $this->SetTextColor(255);
        $this->SetDrawColor(26, 115, 232);
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 8, $h, 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0);
        $this->SetDrawColor(200, 200, 200);
    }

    public function TableRow($vals, $widths, $fill) {
        $this->SetFont('helvetica', '', 8);
        if ($fill) {
            $this->SetFillColor(240, 245, 255);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        foreach ($vals as $i => $v) {
            $this->Cell($widths[$i], 7, $v, 1, 0, 'C', $fill);
        }
        $this->Ln();
    }

    public function TableFooter($text) {
        $this->SetFont('helvetica', 'B', 9);
        $this->SetFillColor(230, 240, 255);
        $this->SetDrawColor(26, 115, 232);
        $w = array_sum($this->lastWidths ?? [187]);
        $this->Cell($w, 9, $text, 1, 1, 'R', true);
    }
}

$pdf = new BusReportPDF('P', 'mm', 'A4', true, true, 'UTF-8');
$pdf->SetCreator('SmartBus Tracker');
$pdf->SetTitle(ucfirst($report_type) . ' Report - SmartBus Tracker');
$pdf->SetAuthor('SmartBus Tracker Admin');
$pdf->setPrintHeader(false);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->SetFont('helvetica', '', 10);
$pdf->reportTitle = ucfirst($report_type) . ' Report';
$pdf->dateFrom = $date_from;
$pdf->dateTo = $date_to;
$pdf->busCode = $bus_code;

$pdf->AddPage();

$where = "WHERE DATE(bk.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($bus_code) {
    $where .= " AND b.bus_code = ?";
    $params[] = $bus_code;
}

if ($report_type === 'bookings') {
    $stmt = $db->prepare("
        SELECT bk.id, bk.booking_date, bk.status, bk.payment_method, bk.amount,
               b.bus_code, b.bus_name, s.seat_number, u.full_name, u.phone
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
    $widths = [12, 22, 35, 28, 22, 15, 22, 18, 33];
    $pdf->lastWidths = $widths;
    $pdf->TableHeader($header, $widths);

    $total_amount = 0;
    foreach ($rows as $row) {
        $fill = ($row['id'] % 2 === 0);
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
        $pdf->TableRow($vals, $widths, $fill);
        $total_amount += floatval($row['amount'] ?? 500);
    }

    $pdf->TableFooter("Total: RWF " . number_format($total_amount) . " | Bookings: " . count($rows));

} elseif ($report_type === 'payments') {
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
    $widths = [12, 18, 35, 22, 15, 22, 25, 20, 18];
    $pdf->lastWidths = $widths;
    $pdf->TableHeader($header, $widths);

    $total_amount = 0;
    $successful = 0;
    foreach ($rows as $row) {
        $fill = ($row['id'] % 2 === 0);
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
        $pdf->TableRow($vals, $widths, $fill);
        $total_amount += $row['amount'];
        if ($row['status'] === 'successful') $successful++;
    }

    $pdf->TableFooter("Total: RWF " . number_format($total_amount) . " | Successful: {$successful} | Total Records: " . count($rows));

} elseif ($report_type === 'drivers') {
    $stmt = $db->query("SELECT d.*, b.bus_code, b.bus_name FROM drivers d LEFT JOIN buses b ON d.assigned_bus_id = b.id ORDER BY d.full_name");
    $rows = $stmt->fetchAll();

    $header = ['ID', 'Name', 'Phone', 'License', 'Assigned Bus', 'Status'];
    $widths = [12, 45, 30, 35, 35, 30];
    $pdf->lastWidths = $widths;
    $pdf->TableHeader($header, $widths);

    foreach ($rows as $row) {
        $fill = ($row['id'] % 2 === 0);
        $vals = [
            '#' . $row['id'],
            $row['full_name'],
            $row['phone'],
            $row['license_number'],
            $row['bus_code'] ?? 'Unassigned',
            strtoupper($row['status'])
        ];
        $pdf->TableRow($vals, $widths, $fill);
    }

    $pdf->TableFooter("Total Drivers: " . count($rows));

} elseif ($report_type === 'summary') {
    $stats = [];
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where}");
    $stmt->execute($params);
    $stats['total_bookings'] = $stmt->fetch()['c'];

    $where_paid = str_replace('bk.created_at', 'bk.created_at', $where) . " AND bk.status = 'paid'";
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(bk.amount),0) as total FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where_paid}");
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

    $where_revenue = "WHERE status = 'successful' AND DATE(created_at) BETWEEN ? AND ?";
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) as total FROM payments {$where_revenue}");
    $stmt->execute([$date_from, $date_to]);
    $stats['total_payment_revenue'] = $stmt->fetch()['total'];

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetFillColor(26, 115, 232);
    $pdf->SetTextColor(255);
    $pdf->Cell(170, 10, 'Key Metrics', 1, 1, 'C', true);
    $pdf->SetTextColor(0);

    $summary_data = [
        ['Total Bookings', number_format($stats['total_bookings'])],
        ['Paid Bookings', number_format($stats['paid_bookings'])],
        ['Pending Bookings', number_format($stats['pending_bookings'])],
        ['Successful Payments', number_format($stats['successful_payments'])],
        ['Total Revenue (Bookings)', 'RWF ' . number_format($stats['total_revenue'])],
        ['Total Revenue (Payments)', 'RWF ' . number_format($stats['total_payment_revenue'])],
    ];

    foreach ($summary_data as $i => $item) {
        $fill = ($i % 2 === 0);
        $pdf->SetFillColor(240, 245, 255);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(95, 12, $item[0], 1, 0, 'L', $fill);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(75, 12, $item[1], 1, 1, 'C', $fill);
    }

    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(128);
    $pdf->Cell(0, 6, 'This report was auto-generated by SmartBus Tracker System.', 0, 1, 'C');
}

$pdf_content = $pdf->Output('', 'S');
$pdf_path = sys_get_temp_dir() . "/report_{$report_type}_" . date('Ymd_His') . ".pdf";
file_put_contents($pdf_path, $pdf_content);

if ($mode === 'view') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $report_type . '_report_' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . filesize($pdf_path));
    readfile($pdf_path);
} else {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . filesize($pdf_path));
    readfile($pdf_path);
}

unlink($pdf_path);
exit;
