<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\PrinterSetting;
use App\Models\Order;
use App\Services\PrinterService;
use App\Services\PrinterDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrinterController extends Controller
{
    protected $printerService;
    protected $discoveryService;
    
    public function __construct(PrinterService $printerService, PrinterDiscoveryService $discoveryService)
    {
        $this->printerService = $printerService;
        $this->discoveryService = $discoveryService;
    }
    
    public function index()
    {
        $restaurant = $this->getCurrentRestaurant();
        $printers = $restaurant->printerSettings()->orderBy('is_default', 'desc')->orderBy('created_at', 'desc')->get();
        
        return view('restaurant.printers.index', compact('printers'));
    }
    
    public function create()
    {
        return view('restaurant.printers.create');
    }
    
    public function store(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        $validated = $request->validate([
            'printer_name' => 'required|string|max:255',
            'printer_type' => 'required|in:network,usb,bluetooth',
            'ip_address' => 'required_if:printer_type,network|nullable|ip',
            'port' => 'required_if:printer_type,network|nullable|integer|min:1|max:65535',
            'usb_path' => 'nullable|string|max:255',
            'bluetooth_mac' => 'nullable|string|max:17',
            'paper_size' => 'required|integer|in:58,80',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);
        
        // Clear other default if this is set as default
        if ($request->has('is_default') && $request->is_default) {
            $restaurant->printerSettings()->update(['is_default' => false]);
        }
        
        $printer = $restaurant->printerSettings()->create([
            'printer_name' => $request->printer_name,
            'printer_type' => $request->printer_type,
            'ip_address' => $request->ip_address,
            'port' => $request->port ?? 9100,
            'usb_path' => $request->usb_path,
            'bluetooth_mac' => $request->bluetooth_mac,
            'paper_size' => $request->paper_size,
            'is_default' => $request->has('is_default'),
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);
        
        return redirect()->route('restaurant.printers.index')
            ->with('success', 'Printer "' . $printer->printer_name . '" added successfully!');
    }
    
    public function edit($id)
    {
        $restaurant = $this->getCurrentRestaurant();
        $printer = $restaurant->printerSettings()->findOrFail($id);
        
        return view('restaurant.printers.edit', compact('printer'));
    }
    
    public function update(Request $request, $id)
    {
        $restaurant = $this->getCurrentRestaurant();
        $printer = $restaurant->printerSettings()->findOrFail($id);
        
        $validated = $request->validate([
            'printer_name' => 'required|string|max:255',
            'paper_size' => 'required|integer|in:58,80',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);
        
        // Clear other default if this is set as default
        if ($request->has('is_default') && $request->is_default) {
            $restaurant->printerSettings()->where('id', '!=', $printer->id)->update(['is_default' => false]);
        }
        
        $printer->update([
            'printer_name' => $request->printer_name,
            'paper_size' => $request->paper_size,
            'is_active' => $request->has('is_active'),
            'is_default' => $request->has('is_default'),
        ]);
        
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Printer updated successfully!']);
        }
        
        return redirect()->route('restaurant.printers.index')
            ->with('success', 'Printer updated successfully!');
    }
    
    public function destroy($id)
    {
        $restaurant = $this->getCurrentRestaurant();
        $printer = $restaurant->printerSettings()->findOrFail($id);
        $printerName = $printer->printer_name;
        $printer->delete();
        
        return redirect()->route('restaurant.printers.index')
            ->with('success', 'Printer "' . $printerName . '" deleted successfully!');
    }
    
    public function discover(Request $request)
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            $type = $request->get('type', 'all');
            
            $allPrinters = $this->discoveryService->discoverAll($restaurant->id);
            $printers = [
                'network' => [],
                'bluetooth' => [],
                'usb' => []
            ];
            
            if ($type === 'all') {
                $printers = $allPrinters;
            } elseif (isset($allPrinters[$type])) {
                $printers[$type] = $allPrinters[$type];
            }
            
            return response()->json([
                'success' => true,
                'printers' => $printers
            ]);
        } catch (\Exception $e) {
            Log::error('Printer discovery error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to discover printers: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function pairBluetooth(Request $request)
    {
        $request->validate([
            'mac' => 'required|string'
        ]);
        
        $result = $this->discoveryService->pairBluetoothPrinter($request->mac);
        
        return response()->json($result);
    }
    
    public function test($id)
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            $printer = $restaurant->printerSettings()->findOrFail($id);
            
            $result = $this->printerService->printTest($printer);
            
            if ($result) {
                return response()->json(['success' => true, 'message' => 'Test print sent successfully!']);
            }
            
            return response()->json(['success' => false, 'message' => 'Failed to connect to printer'], 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function setDefault($id)
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            $printer = $restaurant->printerSettings()->findOrFail($id);
            
            // Clear all default printers
            $restaurant->printerSettings()->update(['is_default' => false]);
            
            // Set this as default
            $printer->update(['is_default' => true]);
            
            return response()->json(['success' => true, 'message' => 'Default printer updated!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function getEditData($id)
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            $printer = $restaurant->printerSettings()->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $printer->id,
                    'printer_name' => $printer->printer_name,
                    'paper_size' => $printer->paper_size,
                    'is_active' => (bool) $printer->is_active,
                    'is_default' => (bool) $printer->is_default
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function printKOT($orderId, $printerId = null)
    {
        $restaurant = $this->getCurrentRestaurant();
        $order = Order::where('restaurant_id', $restaurant->id)->findOrFail($orderId);
        
        if ($printerId) {
            $printer = $restaurant->printerSettings()->findOrFail($printerId);
        } else {
            $printer = $restaurant->printerSettings()->where('is_default', true)->first();
        }
        
        if (!$printer) {
            return redirect()->back()->with('error', 'No printer configured. Please add a printer first.');
        }
        
        $result = $this->printerService->printKOT($order, $printer);
        
        if ($result) {
            return redirect()->back()->with('success', 'KOT sent to printer!');
        }
        
        return redirect()->back()->with('error', 'Failed to print KOT. Please check printer connection.');
    }
    
    public function printInvoice($orderId, $printerId = null)
    {
        $restaurant = $this->getCurrentRestaurant();
        $order = Order::where('restaurant_id', $restaurant->id)->findOrFail($orderId);
        
        if ($printerId) {
            $printer = $restaurant->printerSettings()->findOrFail($printerId);
        } else {
            $printer = $restaurant->printerSettings()->where('is_default', true)->first();
        }
        
        if (!$printer) {
            return redirect()->back()->with('error', 'No printer configured. Please add a printer first.');
        }
        
        $result = $this->printerService->printInvoice($order, $printer);
        
        if ($result) {
            return redirect()->back()->with('success', 'Invoice sent to printer!');
        }
        
        return redirect()->back()->with('error', 'Failed to print invoice. Please check printer connection.');
    }
    
    protected function getCurrentRestaurant()
    {
        $user = Auth::user();
        
        if (session()->has('current_restaurant_id')) {
            $restaurant = $user->restaurants()->find(session('current_restaurant_id'));
            if ($restaurant) {
                return $restaurant;
            }
        }
        
        if ($user->current_restaurant_id) {
            $restaurant = $user->restaurants()->find($user->current_restaurant_id);
            if ($restaurant) {
                return $restaurant;
            }
        }
        
        return $user->restaurants()->firstOrFail();
    }
}