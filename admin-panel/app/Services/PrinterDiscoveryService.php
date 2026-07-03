<?php

namespace App\Services;


use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PrinterDiscoveryService
{
    /**
     * Discover all available printers
     */
    public function discoverAll($restaurantId = null)
    {
        $printers = [
            'bluetooth' => $this->discoverBluetoothPrinters(),
            'network' => $this->discoverNetworkPrinters(),
            'usb' => $this->discoverUsbPrinters(),
        ];
        
        // Cache discovered printers
        if ($restaurantId) {
            Cache::put("discovered_printers_{$restaurantId}", $printers, now()->addMinutes(30));
        }
        
        return $printers;
    }
    
    /**
     * Discover Bluetooth printers
     */
    public function discoverBluetoothPrinters()
    {
        $printers = [];
        
        // Common printer Bluetooth names
        $commonPrinters = [
            'XP-58IIH', 'XP-80C', 'BTP-R880', 'SP-POS88V', 
            'TM-T20', 'TM-T88', 'TM-m30', 'Star TSP650',
            'Epson', 'POS-58', 'Thermal Printer', 'Printer'
        ];
        
        if (PHP_OS_FAMILY === 'Linux') {
            try {
                $output = shell_exec('hcitool scan 2>/dev/null');
                if ($output) {
                    $lines = explode("\n", $output);
                    foreach ($lines as $line) {
                        if (preg_match('/([0-9A-F:]{17})\s+(.+)/', $line, $matches)) {
                            $mac = $matches[1];
                            $name = $matches[2];
                            
                            // Check if it might be a printer
                            $isPrinter = false;
                            foreach ($commonPrinters as $printerName) {
                                if (stripos($name, $printerName) !== false) {
                                    $isPrinter = true;
                                    break;
                                }
                            }
                            
                            if ($isPrinter) {
                                $printers[] = [
                                    'mac' => $mac,
                                    'name' => $name,
                                    'type' => 'bluetooth',
                                    'status' => $this->getBluetoothStatus($mac),
                                    'paired' => $this->isBluetoothPaired($mac)
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Bluetooth scan failed: ' . $e->getMessage());
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            try {
                $output = $this->runWmicCsv('wmic path Win32_PnPEntity where "PNPClass=\'Bluetooth\'" get Name,DeviceID /format:csv');
                foreach ($output as $row) {
                    if (empty($row['Name'])) {
                        continue;
                    }
                    $name = $row['Name'];
                    $isPrinter = false;
                    foreach ($commonPrinters as $printerName) {
                        if (stripos($name, $printerName) !== false) {
                            $isPrinter = true;
                            break;
                        }
                    }
                    if (!$isPrinter) {
                        continue;
                    }
                    $mac = null;
                    if (!empty($row['DeviceID']) && preg_match('/([0-9A-F]{2}[:-]){5}[0-9A-F]{2}/i', $row['DeviceID'], $matches)) {
                        $mac = strtoupper($matches[0]);
                    }
                    $printers[] = [
                        'mac' => $mac,
                        'name' => $name,
                        'type' => 'bluetooth',
                        'status' => 'available',
                        'paired' => false
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Bluetooth discovery failed on Windows: ' . $e->getMessage());
            }
        }
        
        return $printers;
    }
    
    /**
     * Discover network printers
     */
    public function discoverNetworkPrinters()
    {
        $printers = [];
        
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $printers = $this->discoverNetworkPrintersWindows();
            } else {
                $localIp = $this->getLocalIp();
                $subnet = substr($localIp, 0, strrpos($localIp, '.'));
                
                if ($subnet) {
                    $commonIps = [$subnet . '.100', $subnet . '.101', $subnet . '.102', 
                                  $subnet . '.105', $subnet . '.110', $subnet . '.120'];
                    
                    foreach ($commonIps as $ip) {
                        $result = $this->checkNetworkPrinter($ip);
                        if ($result) {
                            $printers[] = $result;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Network printer discovery failed: ' . $e->getMessage());
        }
        
        return $printers;
    }
    
    /**
     * Discover USB printers
     */
    public function discoverUsbPrinters()
    {
        $printers = [];
        
        if (PHP_OS_FAMILY === 'Linux') {
            try {
                $output = shell_exec('lsusb 2>/dev/null');
                if ($output) {
                    $lines = explode("\n", $output);
                    foreach ($lines as $line) {
                        if (preg_match('/ID\s+([0-9a-f]{4}):([0-9a-f]{4})\s+(.+)/i', $line, $matches)) {
                            $description = $matches[3];
                            if (preg_match('/printer|thermal|pos/i', $description)) {
                                $printers[] = [
                                    'name' => $description,
                                    'device_path' => '/dev/usb/lp0',
                                    'type' => 'usb',
                                    'status' => 'connected'
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('USB printer discovery failed: ' . $e->getMessage());
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            try {
                $rows = $this->runWmicCsv('wmic printer get Name,PortName,DriverName,Network /format:csv');
                foreach ($rows as $row) {
                    if (empty($row['Name']) || empty($row['PortName'])) {
                        continue;
                    }
                    $portName = $row['PortName'];
                    $network = strtolower($row['Network'] ?? '') === 'true';
                    if ($network) {
                        continue;
                    }
                    if (preg_match('/USB|LPT/i', $portName)) {
                        $printers[] = [
                            'name' => $row['Name'],
                            'device_path' => $portName,
                            'type' => 'usb',
                            'status' => 'connected'
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('USB printer discovery failed on Windows: ' . $e->getMessage());
            }
        }
        
        return $printers;
    }
    
    protected function discoverNetworkPrintersWindows()
    {
        $printers = [];
        $rows = $this->runWmicCsv('wmic printer get Name,PortName,DriverName,Network /format:csv');
        foreach ($rows as $row) {
            if (empty($row['Name']) || empty($row['PortName'])) {
                continue;
            }
            $portName = $row['PortName'];
            $network = strtolower($row['Network'] ?? '') === 'true';
            if (!$network) {
                if (substr($portName, 0, 7) === '\\\\.\\pipe') {
                    continue;
                }
                $isNetworkLike = preg_match('/IP_|WSD-|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/i', $portName)
                    || (substr($portName, 0, 2) === '\\\\' && substr($portName, 0, 4) !== '\\\\.\\');
                if (!$isNetworkLike) {
                    continue;
                }
            }
            $ip = null;
            $port = 9100;
            if (preg_match('/IP_([0-9.]+)/i', $portName, $matches)) {
                $ip = $matches[1];
            } elseif (preg_match('/([0-9.]+):(\d+)/', $portName, $matches)) {
                $ip = $matches[1];
                $port = (int)$matches[2];
            }
            if (empty($ip)) {
                continue;
            }
            $printers[] = [
                'ip' => $ip,
                'port' => $port,
                'name' => $row['Name'],
                'model' => $row['DriverName'] ?? null,
                'type' => 'network',
                'status' => 'available'
            ];
        }
        return $printers;
    }
    
    protected function runWmicCsv($command)
    {
        $output = shell_exec($command . ' 2>&1');
        if (!$output) {
            return [];
        }
        $lines = array_filter(array_map('trim', explode("\n", $output)));
        if (count($lines) < 2) {
            return [];
        }
        $header = str_getcsv(array_shift($lines));
        $rows = [];
        foreach ($lines as $line) {
            $columns = str_getcsv($line);
            if (count($columns) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $columns);
            $rows[] = $row;
        }
        return $rows;
    }
    
    /**
     * Check network printer
     */
    protected function checkNetworkPrinter($ip)
    {
        $ports = [9100, 515, 631];
        
        foreach ($ports as $port) {
            $socket = @fsockopen($ip, $port, $errno, $errstr, 1);
            if ($socket) {
                fclose($socket);
                return [
                    'ip' => $ip,
                    'port' => $port,
                    'name' => "Printer at $ip",
                    'type' => 'network',
                    'status' => 'available'
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Get local IP address
     */
    protected function getLocalIp()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('ipconfig | findstr "IPv4"');
            preg_match('/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/', $output, $matches);
            return $matches[0] ?? '192.168.1.1';
        } else {
            $output = shell_exec("ip route get 1 2>/dev/null | awk '{print $NF;exit}'");
            return trim($output) ?: '192.168.1.1';
        }
    }
    
    /**
     * Get Bluetooth device status
     */
    protected function getBluetoothStatus($mac)
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $output = shell_exec("bluetoothctl info $mac 2>/dev/null");
            if (strpos($output, 'Connected: yes') !== false) {
                return 'connected';
            }
            if (strpos($output, 'Paired: yes') !== false) {
                return 'paired';
            }
        }
        return 'available';
    }
    
    /**
     * Check if Bluetooth device is paired
     */
    protected function isBluetoothPaired($mac)
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $output = shell_exec("bluetoothctl info $mac 2>/dev/null");
            return strpos($output, 'Paired: yes') !== false;
        }
        return false;
    }
    
    /**
     * Pair Bluetooth printer
     */
    public function pairBluetoothPrinter($mac, $pin = null)
    {
        if (PHP_OS_FAMILY === 'Linux') {
            try {
                shell_exec("bluetoothctl pair $mac 2>&1");
                sleep(2);
                shell_exec("bluetoothctl trust $mac 2>&1");
                
                return ['success' => true, 'message' => 'Printer paired successfully!'];
            } catch (\Exception $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }
        
        return ['success' => false, 'message' => 'Bluetooth pairing not available on this system'];
    }
}