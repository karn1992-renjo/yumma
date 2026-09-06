<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\PrinterSetting;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrinterService
{
    protected $printer;

    protected $connector;

    public function __construct(protected InvoiceService $invoiceService)
    {
    }

    /**
     * Connect to printer based on settings.
     */
    public function connect(PrinterSetting $setting): bool
    {
        try {
            switch ($setting->printer_type) {
                case 'network':
                    $this->connector = new NetworkPrintConnector($setting->ip_address, $setting->port ?: 9100);
                    break;

                case 'usb':
                    if (PHP_OS_FAMILY === 'Windows') {
                        $this->connector = new WindowsPrintConnector($setting->usb_path ?: 'USB001');
                    } else {
                        $this->connector = new FilePrintConnector($setting->usb_path ?: '/dev/usb/lp0');
                    }
                    break;

                case 'bluetooth':
                    // Server-side Bluetooth only works when the host has a bound
                    // rfcomm device / COM port. Phone-paired printers must print
                    // from the device instead.
                    if (PHP_OS_FAMILY === 'Windows') {
                        $this->connector = new WindowsPrintConnector($setting->usb_path ?: 'COM1');
                    } elseif ($setting->ip_address) {
                        $this->connector = new NetworkPrintConnector($setting->ip_address, $setting->port ?: 9100);
                    } else {
                        $this->connector = new FilePrintConnector('/dev/rfcomm0');
                    }
                    break;

                case 'sunmi':
                    // Sunmi built-in printer is driven on-device; nothing to do server-side.
                    Log::info("Sunmi printer '{$setting->printer_name}' is handled on-device, skipping server print.");

                    return false;

                default:
                    return false;
            }

            $this->printer = new Printer($this->connector);

            return true;
        } catch (\Throwable $e) {
            Log::error("Printer connection failed for {$setting->printer_name}: ".$e->getMessage());

            return false;
        }
    }

    public function getPrinter()
    {
        return $this->printer;
    }

    /**
     * Auto-print the KOT for a new order on the restaurant's KOT printer.
     */
    public function autoPrintNewOrder(Order $order): bool
    {
        $order->loadMissing('restaurant');
        $restaurant = $order->restaurant;

        if (! $restaurant || ! $restaurant->auto_print_new_orders) {
            return false;
        }

        $printer = $this->resolvePrinter($restaurant, 'kot');

        if (! $printer) {
            Log::info("Auto-print skipped for order {$order->id}: no active KOT printer.");

            return false;
        }

        try {
            return $this->printKOT($order, $printer);
        } catch (\Throwable $exception) {
            Log::error("Auto-print failed for order {$order->id}: ".$exception->getMessage());

            return false;
        }
    }

    /**
     * Pick the printer that should handle a document role ('kot' | 'invoice').
     * Prefers the default printer when it can handle the role, else the newest
     * active printer that can.
     */
    public function resolvePrinter($restaurant, string $role): ?PrinterSetting
    {
        $printers = $restaurant->printerSettings()
            ->where('is_active', true)
            ->handlesRole($role)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $printers->firstWhere('is_default', true) ?: $printers->first();
    }

    /**
     * Print KOT (Kitchen Order Ticket) — no prices, big quantities, notes kept.
     */
    public function printKOT(Order $order, PrinterSetting $printer): bool
    {
        if (! $this->connect($printer)) {
            return false;
        }

        $width = $this->printWidth($printer);

        try {
            $order->loadMissing('restaurant');
            $restaurant = $order->restaurant;
            $items = $this->parseOrderItems($order->items);

            $this->printer->initialize();
            $this->setPaperSize($printer->paper_size);

            // Header
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
            $this->printer->text($this->clip($restaurant->name ?? 'Kitchen', $width).PHP_EOL);
            $this->printer->selectPrintMode();
            $this->printer->text('KITCHEN ORDER TICKET'.PHP_EOL);
            $this->printer->text($this->divider($width).PHP_EOL);

            // Order meta
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $orderType = strtoupper((string) ($order->order_type ?? 'delivery'));
            $this->printer->setEmphasis(true);
            $this->printer->text($this->twoCol('Order #'.$order->order_number, $orderType, $width).PHP_EOL);
            $this->printer->setEmphasis(false);
            $this->printer->text('Time : '.optional($order->created_at)->format('d/m/Y H:i').PHP_EOL);
            if ($order->customer_name) {
                $this->printer->text('Cust : '.$this->clip($order->customer_name, $width - 7).PHP_EOL);
            }
            if (($order->table_number ?? null)) {
                $this->printer->text('Table: '.$order->table_number.PHP_EOL);
            }
            $this->printer->text($this->divider($width).PHP_EOL);

            // Items — "2 x Paneer Tikka"
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT);
            foreach ($items as $item) {
                $line = $item['quantity'].' x '.$item['name'];
                foreach ($this->wrap($line, $width) as $row) {
                    $this->printer->text($row.PHP_EOL);
                }
                if (! empty($item['special_instructions'])) {
                    foreach ($this->wrap('  * '.$item['special_instructions'], $width) as $row) {
                        $this->printer->text($row.PHP_EOL);
                    }
                }
            }
            $this->printer->selectPrintMode();

            if ($order->special_instructions) {
                $this->printer->text($this->divider($width).PHP_EOL);
                $this->printer->setEmphasis(true);
                $this->printer->text('ORDER NOTE'.PHP_EOL);
                $this->printer->setEmphasis(false);
                foreach ($this->wrap((string) $order->special_instructions, $width) as $row) {
                    $this->printer->text($row.PHP_EOL);
                }
            }

            $this->printer->text($this->divider($width).PHP_EOL);
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text('Items: '.count($items).PHP_EOL);
            $this->printer->feed(2);
            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (\Throwable $e) {
            Log::error('KOT printing failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Print the customer bill / invoice. Totals and tax come from
     * {@see InvoiceService::data()} so the ticket matches the PDF exactly.
     */
    public function printInvoice(Order $order, PrinterSetting $printer): bool
    {
        if (! $this->connect($printer)) {
            return false;
        }

        $width = $this->printWidth($printer);

        try {
            $data = $this->invoiceService->data($order);
            $decimals = $data['decimals'];
            $num = static fn ($v) => number_format((float) $v, $decimals);

            $this->printer->initialize();
            $this->setPaperSize($printer->paper_size);

            // Supplier header
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text($this->clip($data['supplier']['name'] ?: 'Invoice', $width).PHP_EOL);
            $this->printer->selectPrintMode();
            foreach ($this->wrap($data['supplier']['address'] ?? '', $width) as $row) {
                $this->printer->text($row.PHP_EOL);
            }
            if (! empty($data['supplier']['gstin'])) {
                $this->printer->text('GSTIN: '.$data['supplier']['gstin'].PHP_EOL);
            }
            if (! empty($data['supplier']['fssai'])) {
                $this->printer->text('FSSAI: '.$data['supplier']['fssai'].PHP_EOL);
            }
            $this->printer->feed();
            $this->printer->setEmphasis(true);
            $this->printer->text(($data['meta']['title'] ?? 'INVOICE').PHP_EOL);
            $this->printer->setEmphasis(false);
            $this->printer->text($this->divider($width).PHP_EOL);

            // Invoice meta
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text('No  : '.($data['meta']['invoice_no'] ?? $order->order_number).PHP_EOL);
            $this->printer->text('Date: '.($data['meta']['date'] ?? optional($order->created_at)->format('d M Y, h:i A')).PHP_EOL);
            if (! empty($data['customer']['name'])) {
                $this->printer->text('Bill: '.$this->clip($data['customer']['name'], $width - 6).PHP_EOL);
            }
            if (! empty($data['customer']['phone'])) {
                $this->printer->text('Ph  : '.$data['customer']['phone'].PHP_EOL);
            }
            $this->printer->text($this->divider($width).PHP_EOL);

            // Item table: name on its own line, "qty x unit = total" below.
            $this->printer->setEmphasis(true);
            $this->printer->text($this->threeCol('Item', 'Qty', 'Amount', $width).PHP_EOL);
            $this->printer->setEmphasis(false);
            $this->printer->text($this->divider($width).PHP_EOL);
            foreach ($data['items'] as $item) {
                foreach ($this->wrap((string) $item['name'], $width) as $row) {
                    $this->printer->text($row.PHP_EOL);
                }
                $qtyUnit = $item['qty'].' x '.$num($item['unit']);
                $this->printer->text($this->twoCol('  '.$qtyUnit, $num($item['total']), $width).PHP_EOL);
            }
            $this->printer->text($this->divider($width).PHP_EOL);

            // Fee rows from the invoice service (subtotal, delivery, packaging,
            // platform fee, GST, tip, discount…).
            foreach ($data['feeRows'] as $row) {
                $value = ($row['negative'] ? '-' : '').$num($row['value']);
                $this->printer->text($this->twoCol($row['label'], $value, $width).PHP_EOL);
            }

            // GST split when available.
            $gst = $data['gst'] ?? null;
            $cgst = (float) ($gst['cgst_total'] ?? $order->cgst_amount ?? 0);
            $sgst = (float) ($gst['sgst_total'] ?? $order->sgst_amount ?? 0);
            if ($cgst > 0 || $sgst > 0) {
                $this->printer->text($this->twoCol('  CGST', $num($cgst), $width).PHP_EOL);
                $this->printer->text($this->twoCol('  SGST', $num($sgst), $width).PHP_EOL);
            }

            $this->printer->text($this->divider($width, '=').PHP_EOL);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text($this->twoCol('TOTAL', $num($order->total), (int) floor($width / 2)).PHP_EOL);
            $this->printer->selectPrintMode();
            $this->printer->text($this->divider($width, '=').PHP_EOL);

            // Payment
            $this->printer->text('Payment: '.($data['meta']['payment_method'] ?? strtoupper((string) $order->payment_method)).PHP_EOL);
            $this->printer->text('Status : '.($data['meta']['payment_status'] ?? strtoupper((string) $order->payment_status)).PHP_EOL);
            if (! empty($data['meta']['paid_label'])) {
                $this->printer->text($data['meta']['paid_label'].PHP_EOL);
            }

            $this->printer->feed();
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            if (! empty($data['amountInWords'])) {
                foreach ($this->wrap($data['amountInWords'], $width) as $row) {
                    $this->printer->text($row.PHP_EOL);
                }
            }
            $this->printer->text('Thank you! Visit again'.PHP_EOL);
            $this->printer->feed(2);
            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (\Throwable $e) {
            Log::error('Invoice printing failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Print a test page.
     */
    public function printTest(PrinterSetting $printer): bool
    {
        if (! $this->connect($printer)) {
            return false;
        }

        $width = $this->printWidth($printer);

        try {
            $this->printer->initialize();
            $this->setPaperSize($printer->paper_size);

            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text('TEST PRINT'.PHP_EOL);
            $this->printer->selectPrintMode();
            $this->printer->text($this->divider($width).PHP_EOL);
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text($this->twoCol('Printer', $this->clip($printer->printer_name, 18), $width).PHP_EOL);
            $this->printer->text($this->twoCol('Type', ucfirst((string) $printer->printer_type), $width).PHP_EOL);
            $this->printer->text($this->twoCol('Role', ucfirst((string) ($printer->printer_role ?: 'both')), $width).PHP_EOL);
            $this->printer->text($this->twoCol('Paper', $printer->paper_size.'mm ('.$width.' col)', $width).PHP_EOL);
            $this->printer->text($this->twoCol('Time', now()->format('Y-m-d H:i:s'), $width).PHP_EOL);
            $this->printer->text($this->divider($width).PHP_EOL);
            $this->printer->text('0123456789'.str_repeat('.', max(0, $width - 10)).PHP_EOL);
            $this->printer->text('If the ruler above fits on one line the column'.PHP_EOL);
            $this->printer->text('width is correct.'.PHP_EOL);
            $this->printer->feed(2);
            $this->printer->cut();
            $this->printer->close();

            return true;
        } catch (\Throwable $e) {
            Log::error('Test print failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Column width in characters for the printer's paper size.
     */
    protected function printWidth(PrinterSetting $printer): int
    {
        return ((int) $printer->paper_size) === 58 ? 32 : 48;
    }

    protected function setPaperSize($size): void
    {
        $this->printer->setPrintWidth($size == 58 ? 32 : 48);
    }

    protected function divider(int $width, string $char = '-'): string
    {
        return str_repeat($char, $width);
    }

    protected function clip(string $text, int $width): string
    {
        $text = trim($text);

        return mb_strlen($text) > $width ? mb_substr($text, 0, $width) : $text;
    }

    /**
     * Left label + right value on one line, padded to $width.
     */
    protected function twoCol(string $left, string $right, int $width): string
    {
        $right = trim($right);
        $maxLeft = max(1, $width - mb_strlen($right) - 1);
        if (mb_strlen($left) > $maxLeft) {
            $left = mb_substr($left, 0, $maxLeft);
        }
        $gap = max(1, $width - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $gap).$right;
    }

    protected function threeCol(string $a, string $b, string $c, int $width): string
    {
        // "Item ......... Qty .... Amount" header.
        $cWidth = 8;
        $bWidth = 5;
        $aWidth = max(1, $width - $cWidth - $bWidth);

        return $this->pad($a, $aWidth).$this->pad($b, $bWidth, STR_PAD_LEFT).$this->pad($c, $cWidth, STR_PAD_LEFT);
    }

    /**
     * Multibyte-safe pad/truncate to an exact width.
     */
    protected function pad(string $input, int $length, int $type = STR_PAD_RIGHT): string
    {
        $diff = $length - mb_strlen($input);
        if ($diff <= 0) {
            return mb_substr($input, 0, $length);
        }

        return $type === STR_PAD_LEFT
            ? str_repeat(' ', $diff).$input
            : $input.str_repeat(' ', $diff);
    }

    /**
     * Word-wrap to $width, hard-splitting overly long tokens.
     *
     * @return array<int,string>
     */
    protected function wrap(string $text, int $width): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return [];
        }

        $lines = [];
        $current = '';
        foreach (explode(' ', $text) as $word) {
            while (mb_strlen($word) > $width) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }
            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $width) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Parse order items JSON into a normalised list.
     */
    protected function parseOrderItems($items): array
    {
        if (is_null($items)) {
            return [];
        }

        if (is_array($items)) {
            $decoded = $items;
        } elseif (is_string($items)) {
            $decoded = json_decode($items, true);
            $decoded = is_array($decoded) ? $decoded : [];
        } else {
            $decoded = [];
        }

        return collect($decoded)
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $quantity = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
                $total = (float) ($item['total_price'] ?? $item['total'] ?? 0);
                $price = (float) ($item['unit_price'] ?? $item['price'] ?? 0);

                if ($price <= 0 && $quantity > 0 && $total > 0) {
                    $price = $total / $quantity;
                }

                if ($total <= 0) {
                    $total = $price * $quantity;
                }

                return [
                    'name' => (string) ($item['name'] ?? $item['item_name'] ?? data_get($item, 'menu_item.name') ?? $item['title'] ?? 'Item'),
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                    'special_instructions' => $item['special_instructions'] ?? $item['note'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
