<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'POST required'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = sanitize($data['email'] ?? '');
$report_type = sanitize($data['type'] ?? 'bookings');
$date_from = sanitize($data['from'] ?? date('Y-m-01'));
$date_to = sanitize($data['to'] ?? date('Y-m-d'));
$bus_code = sanitize($data['bus_code'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['status' => 'error', 'message' => 'Valid email required'], 400);
}

try {
    $db = getDb();

    // Generate PDF in memory
    $where = "WHERE DATE(bk.created_at) BETWEEN ? AND ?";
    $params = [$date_from, $date_to];
    if ($bus_code) {
        $where .= " AND b.bus_code = ?";
        $params[] = $bus_code;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    class EmailReportPDF extends TCPDF {
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

    $pdf = new EmailReportPDF('P', 'mm', 'A4', true, true, 'UTF-8');
    $pdf->SetCreator('SmartBus Tracker');
    $pdf->SetTitle(ucfirst($report_type) . ' Report');
    $pdf->setPrintHeader(false);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, ucfirst($report_type) . ' Report', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, "Period: {$date_from} to {$date_to}" . ($bus_code ? " | Bus: {$bus_code}" : ""), 0, 1);
    $pdf->Ln(3);

    if ($report_type === 'bookings') {
        $stmt = $db->prepare("
            SELECT bk.id, bk.booking_date, bk.status, bk.payment_method, bk.amount,
                   b.bus_code, b.bus_name, s.seat_number, u.full_name, u.phone
            FROM bookings bk JOIN buses b ON bk.bus_id = b.id JOIN seats s ON bk.seat_id = s.id JOIN users u ON bk.user_id = u.id
            {$where} ORDER BY bk.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $header = ['ID', 'Date', 'Passenger', 'Phone', 'Bus', 'Seat', 'Amount', 'Status'];
        $widths = [12, 22, 40, 30, 25, 15, 25, 25];

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(26, 115, 232);
        $pdf->SetTextColor(255);
        foreach ($header as $i => $h) $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
        $pdf->Ln();
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 8);
        $total = 0;
        foreach ($rows as $row) {
            $fill = $row['id'] % 2 === 0;
            $pdf->SetFillColor(245, 245, 245);
            $vals = ['#'.$row['id'], $row['booking_date'], substr($row['full_name'],0,20), $row['phone'], $row['bus_code'], $row['seat_number'], 'RWF '.number_format($row['amount']??500), strtoupper($row['status'])];
            foreach ($vals as $i => $v) $pdf->Cell($widths[$i], 6, $v, 1, 0, 'C', $fill);
            $pdf->Ln();
            $total += floatval($row['amount'] ?? 500);
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(array_sum($widths), 8, "Total: RWF ".number_format($total)." | Bookings: ".count($rows), 1, 1, 'R');

    } elseif ($report_type === 'payments') {
        $stmt = $db->prepare("
            SELECT p.*, b.bus_code, u.full_name
            FROM payments p JOIN bookings bk ON p.booking_id = bk.id JOIN buses b ON bk.bus_id = b.id JOIN users u ON p.user_id = u.id
            {$where} ORDER BY p.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $header = ['ID', 'Booking', 'Passenger', 'Bus', 'Amount', 'Method', 'Status'];
        $widths = [12, 18, 40, 25, 25, 30, 25];
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(26, 115, 232);
        $pdf->SetTextColor(255);
        foreach ($header as $i => $h) $pdf->Cell($widths[$i], 7, $h, 1, 0, 'C', true);
        $pdf->Ln();
        $pdf->SetTextColor(0);
        $pdf->SetFont('helvetica', '', 8);
        $total = 0;
        foreach ($rows as $row) {
            $fill = $row['id'] % 2 === 0;
            $pdf->SetFillColor(245, 245, 245);
            $vals = ['#'.$row['id'], '#'.$row['booking_id'], substr($row['full_name'],0,20), $row['bus_code'], 'RWF '.number_format($row['amount']), $row['payment_method'], strtoupper($row['status'])];
            foreach ($vals as $i => $v) $pdf->Cell($widths[$i], 6, $v, 1, 0, 'C', $fill);
            $pdf->Ln();
            $total += $row['amount'];
        }
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(array_sum($widths), 8, "Total: RWF ".number_format($total)." | Payments: ".count($rows), 1, 1, 'R');
    }

    $pdf_content = $pdf->Output('', 'S');
    $pdf_path = sys_get_temp_dir() . "/report_email_" . date('Ymd_His') . ".pdf";
    file_put_contents($pdf_path, $pdf_content);

    // Send email with PDF attachment using PHP mail()
    $boundary = md5(time());
    $subject = "SmartBus Tracker - " . ucfirst($report_type) . " Report ({$date_from} to {$date_to})";
    $from_email = 'noreply@bustracking.rw';
    $from_name = 'SmartBus Tracker';

    $headers = "From: {$from_name} <{$from_email}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= "Dear Admin,\r\n\r\n";
    $body .= "Please find attached the " . ucfirst($report_type) . " Report for the period {$date_from} to {$date_to}.\r\n\r\n";
    $body .= "Generated by SmartBus Tracker System.\r\n\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"{$report_type}_report_{$date_to}.pdf\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$report_type}_report_{$date_to}.pdf\"\r\n\r\n";
    $body .= chunk_split(base64_encode($pdf_content));
    $body .= "--{$boundary}--";

    $sent = @mail($email, $subject, $body, $headers);
    @unlink($pdf_path);

    if ($sent) {
        jsonResponse(['status' => 'success', 'message' => "Report emailed to {$email}"]);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Email sending failed. Check server mail configuration.']);
    }
} catch (Exception $e) {
    errorResponse('Report generation failed');
}
