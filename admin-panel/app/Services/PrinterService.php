<?php

namespace App\Services;

use App\Models\PrinterSetting;
use App\Models\Order;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class PrinterService
{
    protected $printer;
    protected $connector;
    
    /**
     * Connect to printer based on settings
     */
    public function connect(PrinterSetting $setting): bool
    {
        try {
            switch ($setting->printer_type) {
                case 'network':
                    $this->connector = new NetworkPrintConnector($setting->ip_address, $setting->port);
                    break;
                    
                case 'usb':
                    if (PHP_OS_FAMILY === 'Windows') {
                        $this->connector = new WindowsPrintConnector($setting->usb_path ?? 'USB001');
                    } else {
                        $this->connector = new FilePrintConnector($setting->usb_path ?? '/dev/usb/lp0');
                    }
                    break;
                    
                case 'bluetooth':
                    if (PHP_OS_FAMILY === 'Linux') {
                        $this->connector = new FilePrintConnector('/dev/rfcomm0');
                    } elseif (PHP_OS_FAMILY === 'Windows') {
                        $this->connector = new WindowsPrintConnector('COM1');
                    } else {
                        $this->connector = new NetworkPrintConnector($setting->bluetooth_mac, 9100);
                    }
                    break;
                    
                default:
                    return false;
            }
            
            $this->printer = new Printer($this->connector);
            return true;
            
        } catch (\Exception $e) {
            Log::error("Printer connection failed for {$setting->printer_name}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get printer instance
     */
    public function getPrinter()
    {
        return $this->printer;
    }

    public function autoPrintNewOrder(Order $order): bool
    {
        $order->loadMissing('restaurant');
        $restaurant = $order->restaurant;

        if (!$restaurant || !$restaurant->auto_print_new_orders) {
            return false;
        }

        $printer = $restaurant->printerSettings()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (!$printer) {
            Log::info("Auto-print skipped for order {$order->id}: no active default printer.");
            return false;
        }

        try {
            return $this->printKOT($order, $printer);
        } catch (\Throwable $exception) {
            Log::error("Auto-print failed for order {$order->id}: " . $exception->getMessage());
            return false;
        }
    }
    
    /**
     * Print KOT (Kitchen Order Ticket)
     */
    public function printKOT(Order $order, PrinterSetting $printer): bool
    {
        if (!$this->connect($printer)) {
            return false;
        }
        
        try {
            $restaurant = $order->restaurant;
            $items = $this->parseOrderItems($order->items);
            
            // Initialize printer
            $this->printer->initialize();
            
            // Set paper size
            $this->setPaperSize($printer->paper_size);
            
            // Header
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text($restaurant->name . "\n");
            $this->printer->selectPrintMode();
            $this->printer->text($restaurant->address . "\n");
            $this->printer->text("Phone: " . ($restaurant->phone ?? 'N/A') . "\n");
            $this->printer->feed();
            
            // KOT Title
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("======== KITCHEN ORDER TICKET ========\n");
            $this->printer->feed();
            
            // Order Details
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text("Order #: " . $order->order_number . "\n");
            $this->printer->text("Date: " . $order->created_at->format('d/m/Y H:i:s') . "\n");
            $this->printer->text("Customer: " . ($order->customer_name ?? 'Guest') . "\n");
            $this->printer->text("Phone: " . ($order->customer_phone ?? 'N/A') . "\n");
            $this->printer->feed();
            
            // Items
            $this->printer->text("--------------------------------\n");
            $this->printer->setEmphasis(true);
            $this->printer->text("ITEMS:\n");
            $this->printer->setEmphasis(false);
            $this->printer->text("--------------------------------\n");
            
            foreach ($items as $item) {
                $name = substr($item['name'], 0, 25);
                $qty = $item['quantity'];
                $price = number_format($item['price'], AppSetting::currencyDecimals());
                $total = number_format($item['total'], AppSetting::currencyDecimals());
                
                $this->printer->text($name . "\n");
                $this->printer->text("  x" . $qty . " @ ₹" . $price . " = ₹" . $total . "\n");
                if (!empty($item['special_instructions'])) {
                    $this->printer->text("  [Note: " . substr($item['special_instructions'], 0, 30) . "]\n");
                }
            }
            
            $this->printer->text("--------------------------------\n");
            
            // Total
            $this->printer->setEmphasis(true);
            $this->printer->text("TOTAL: ₹" . number_format($order->total, AppSetting::currencyDecimals()) . "\n");
            $this->printer->setEmphasis(false);
            
            $this->printer->feed();
            
            // Special Instructions
            if ($order->special_instructions) {
                $this->printer->text("Special Instructions:\n");
                $this->printer->text(substr($order->special_instructions, 0, 40) . "\n");
                $this->printer->feed();
            }
            
            // Footer
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Thank you for ordering!\n");
            $this->printer->feed(2);
            
            // Cut paper
            $this->printer->cut();
            
            // Close connection
            $this->printer->close();
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("KOT printing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Print Invoice
     */
    public function printInvoice(Order $order, PrinterSetting $printer): bool
    {
        if (!$this->connect($printer)) {
            return false;
        }
        
        try {
            $restaurant = $order->restaurant;
            $items = $this->parseOrderItems($order->items);
            
            $this->printer->initialize();
            $this->setPaperSize($printer->paper_size);
            
            // Restaurant Header
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text($restaurant->name . "\n");
            $this->printer->selectPrintMode();
            $this->printer->text($restaurant->address . "\n");
            $this->printer->text("GST: " . ($restaurant->gst_number ?? 'N/A') . "\n");
            $this->printer->feed();
            
            // Invoice Title
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("============= INVOICE =============\n");
            $this->printer->feed();
            
            // Order Details
            $this->printer->setJustification(Printer::JUSTIFY_LEFT);
            $this->printer->text("Invoice #: " . $order->order_number . "\n");
            $this->printer->text("Date: " . $order->created_at->format('d/m/Y H:i:s') . "\n");
            $this->printer->text("Customer: " . ($order->customer_name ?? 'Guest') . "\n");
            $this->printer->text("Phone: " . ($order->customer_phone ?? 'N/A') . "\n");
            $this->printer->feed();
            
            // Items Table
            $this->printer->text("----------------------------------------\n");
            $this->printer->setEmphasis(true);
            $this->printer->text(sprintf("%-20s %5s %8s %8s\n", "Item", "Qty", "Price", "Total"));
            $this->printer->setEmphasis(false);
            $this->printer->text("----------------------------------------\n");
            
            foreach ($items as $item) {
                $name = substr($item['name'], 0, 18);
                $qty = $item['quantity'];
                $price = number_format($item['price'], AppSetting::currencyDecimals());
                $total = number_format($item['total'], AppSetting::currencyDecimals());
                
                $this->printer->text(sprintf("%-20s %5d %8s %8s\n", $name, $qty, $price, $total));
            }
            
            $this->printer->text("----------------------------------------\n");
            
            // Totals
            $this->printer->text(sprintf("%-33s %8s\n", "Subtotal:", number_format($order->subtotal, AppSetting::currencyDecimals())));
            if ($order->delivery_fee > 0) {
                $this->printer->text(sprintf("%-33s %8s\n", "Delivery Fee:", number_format($order->delivery_fee, AppSetting::currencyDecimals())));
            }
            if ((float) ($order->platform_fee ?? 0) > 0) {
                $this->printer->text(sprintf("%-33s %8s\n", "Platform Fee:", number_format($order->platform_fee, AppSetting::currencyDecimals())));
            }
            if ($order->tax > 0) {
                $this->printer->text(sprintf("%-33s %8s\n", "Taxes/Charges:", number_format($order->tax, AppSetting::currencyDecimals())));
            }
            if ($order->discount > 0) {
                $this->printer->text(sprintf("%-33s %8s\n", "Coupon Discount:", '-' . number_format($order->discount, AppSetting::currencyDecimals())));
            }
            $this->printer->text("----------------------------------------\n");
            
            $this->printer->setEmphasis(true);
            $this->printer->text(sprintf("%-33s %8s\n", "TOTAL:", number_format($order->total, AppSetting::currencyDecimals())));
            $this->printer->setEmphasis(false);
            
            $this->printer->feed();
            
            // Payment Details
            $this->printer->text("Payment Method: " . strtoupper($order->payment_method) . "\n");
            $this->printer->text("Payment Status: " . strtoupper($order->payment_status) . "\n");
            
            $this->printer->feed();
            
            // Footer
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text("Thank you for dining with us!\n");
            $this->printer->text("Visit again!\n");
            $this->printer->feed(2);
            
            $this->printer->cut();
            $this->printer->close();
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Invoice printing failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Print test page
     */
    public function printTest(PrinterSetting $printer): bool
    {
        if (!$this->connect($printer)) {
            return false;
        }
        
        try {
            $this->printer->initialize();
            $this->setPaperSize($printer->paper_size);
            
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text("TEST PRINT\n");
            $this->printer->selectPrintMode();
            $this->printer->text("================\n");
            $this->printer->text("Printer: " . $printer->printer_name . "\n");
            $this->printer->text("Type: " . ucfirst($printer->printer_type) . "\n");
            $this->printer->text("Date: " . now()->format('Y-m-d H:i:s') . "\n");
            $this->printer->text("Status: CONNECTED\n");
            $this->printer->text("================\n");
            $this->printer->feed();
            $this->printer->text("If you can read this, your printer is working correctly!\n");
            $this->printer->feed(2);
            $this->printer->cut();
            $this->printer->close();
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Test print failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set paper size
     */
    protected function setPaperSize($size)
    {
        if ($size == 58) {
            // 58mm paper settings
            $this->printer->setPrintWidth(32);
        } else {
            // 80mm paper settings
            $this->printer->setPrintWidth(48);
        }
    }
    
    /**
     * Parse order items
     */
    protected function parseOrderItems($items)
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
                if (!is_array($item)) {
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
