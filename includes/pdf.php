<?php
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

/**
 * FPDF (core fonts) usa Windows-1252, non UTF-8: ogni stringa passata a
 * Cell/MultiCell va convertita, altrimenti lettere accentate e simbolo
 * euro escono corrotti nel PDF.
 */
function pdf_text(?string $value): string
{
    $value = $value ?? '';
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
    return $converted !== false ? $converted : $value;
}

/**
 * Genera il PDF del preventivo e lo salva in ORDERS_DIR.
 *
 * @param array $order {id, access_token, customer_first_name, customer_last_name,
 *                       customer_email, subtotal, discount_total, total,
 *                       has_price_on_request, created_at}
 * @param array $items  righe {brand, name, variant, quantity, unit_price,
 *                       discount_percent, line_total}
 * @return string percorso del file (relativo, per il DB/URL)
 */
function generate_order_pdf(array $order, array $items): string
{
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(16, 16, 16);
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    $logoPath = __DIR__ . '/../assets/images/beautydrops-logo.png';
    if (is_file($logoPath)) {
        try {
            $pdf->Image($logoPath, 16, 14, 32);
        } catch (Throwable $e) {
            // se il logo non è leggibile da FPDF, si prosegue senza bloccare il PDF
        }
    }

    $pdf->SetXY(16, 34);
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 8, pdf_text('Preventivo ordine'), 0, 1);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(110, 100, 100);
    $pdf->Cell(0, 6, pdf_text('BeautyDrops — Ordine #' . $order['id'] . ' · ' . date('d/m/Y', strtotime($order['created_at']))), 0, 1);
    $pdf->Ln(6);

    $pdf->SetTextColor(30, 24, 24);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 6, pdf_text('Cliente'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, pdf_text($order['customer_first_name'] . ' ' . $order['customer_last_name']), 0, 1);
    $pdf->Cell(0, 6, pdf_text($order['customer_email']), 0, 1);
    $pdf->Ln(6);

    // Intestazione tabella
    $pdf->SetFillColor(245, 238, 230);
    $pdf->SetFont('Helvetica', 'B', 9);
    $colProduct = 76;
    $colQty = 16;
    $colPrice = 26;
    $colDiscount = 22;
    $colTotal = 30;
    $pdf->Cell($colProduct, 8, pdf_text('Prodotto'), 0, 0, 'L', true);
    $pdf->Cell($colQty, 8, pdf_text('Qta'), 0, 0, 'C', true);
    $pdf->Cell($colPrice, 8, pdf_text('Prezzo'), 0, 0, 'R', true);
    $pdf->Cell($colDiscount, 8, pdf_text('Sconto'), 0, 0, 'C', true);
    $pdf->Cell($colTotal, 8, pdf_text('Totale'), 0, 1, 'R', true);

    $pdf->SetFont('Helvetica', '', 9);
    foreach ($items as $item) {
        $label = $item['brand'] . ' — ' . $item['name'];
        if (!empty($item['variant'])) {
            $label .= ' (' . $item['variant'] . ')';
        }
        $lineHeight = 6;
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->MultiCell($colProduct, $lineHeight, pdf_text($label), 0, 'L');
        $usedHeight = $pdf->GetY() - $y;
        $rowHeight = max($usedHeight, $lineHeight);

        $pdf->SetXY($x + $colProduct, $y);
        $pdf->Cell($colQty, $rowHeight, (string) $item['quantity'], 0, 0, 'C');

        if ($item['unit_price'] === null) {
            $pdf->Cell($colPrice, $rowHeight, pdf_text('su richiesta'), 0, 0, 'R');
            $pdf->Cell($colDiscount, $rowHeight, '-', 0, 0, 'C');
            $pdf->Cell($colTotal, $rowHeight, '-', 0, 0, 'R');
        } else {
            $pdf->Cell($colPrice, $rowHeight, pdf_text(format_price_pdf((float) $item['unit_price'])), 0, 0, 'R');
            $pdf->Cell($colDiscount, $rowHeight, $item['discount_percent'] > 0 ? '-' . $item['discount_percent'] . '%' : '-', 0, 0, 'C');
            $pdf->Cell($colTotal, $rowHeight, pdf_text(format_price_pdf((float) $item['line_total'])), 0, 0, 'R');
        }

        $pdf->SetXY($x, $y + $rowHeight);
        $pdf->SetDrawColor(230, 222, 214);
        $pdf->Line($x, $pdf->GetY(), $x + $colProduct + $colQty + $colPrice + $colDiscount + $colTotal, $pdf->GetY());
        $pdf->Ln(1);
    }

    $pdf->Ln(4);
    $totalsX = $colProduct + $colQty;

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell($totalsX, 6, '', 0, 0);
    $pdf->Cell($colPrice + $colDiscount, 6, pdf_text('Subtotale'), 0, 0, 'R');
    $pdf->Cell($colTotal, 6, pdf_text(format_price_pdf((float) $order['subtotal'])), 0, 1, 'R');

    $pdf->Cell($totalsX, 6, '', 0, 0);
    $pdf->Cell($colPrice + $colDiscount, 6, pdf_text('Sconto quantità'), 0, 0, 'R');
    $pdf->Cell($colTotal, 6, pdf_text('-' . format_price_pdf((float) $order['discount_total'])), 0, 1, 'R');

    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell($totalsX, 9, '', 0, 0);
    $pdf->Cell($colPrice + $colDiscount, 9, pdf_text('TOTALE'), 0, 0, 'R');
    $pdf->Cell($colTotal, 9, pdf_text(format_price_pdf((float) $order['total'])), 0, 1, 'R');

    if (!empty($order['has_price_on_request'])) {
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->SetTextColor(150, 90, 40);
        $pdf->MultiCell(0, 5, pdf_text('Alcuni articoli hanno prezzo "su richiesta": non sono inclusi nel totale sopra e verranno confermati direttamente da BeautyDrops.'));
    }

    $pdf->Ln(10);
    $pdf->SetTextColor(120, 110, 110);
    $pdf->SetFont('Helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, pdf_text('Questo documento è un preventivo non vincolante, non una fattura: non è previsto alcun pagamento online. Per confermare l\'ordine, invia questo PDF a BeautyDrops (' . SHOP_CONTACT_EMAIL . '): pagamento e consegna verranno concordati privatamente.'));

    if (!is_dir(ORDERS_DIR)) {
        mkdir(ORDERS_DIR, 0755, true);
    }
    $filename = 'ordine-' . $order['id'] . '-' . substr($order['access_token'], 0, 16) . '.pdf';
    $fullPath = ORDERS_DIR . $filename;
    $pdf->Output('F', $fullPath);

    return ORDERS_URL_PATH . $filename;
}

function format_price_pdf(float $value): string
{
    return 'EUR ' . number_format($value, 2, ',', '.');
}
