import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../services/printer_discovery_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

class RestaurantPrintersScreen extends StatefulWidget {
  const RestaurantPrintersScreen({super.key});

  @override
  State<RestaurantPrintersScreen> createState() =>
      _RestaurantPrintersScreenState();
}

class _RestaurantPrintersScreenState extends State<RestaurantPrintersScreen> {
  final ApiService _api = ApiService();

  List<dynamic> _printers = [];
  bool _isLoading = true;
  bool _autoPrintNewOrders = false;
  bool _isUpdatingAutoPrint = false;

  @override
  void initState() {
    super.initState();
    _loadPrinters();
  }

  Future<void> _loadPrinters() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.restaurantPrinters);
      if (response['success'] == true && mounted) {
        setState(() {
          _printers = response['data'] ?? [];
          _autoPrintNewOrders =
              response['settings']?['auto_print_new_orders'] == true;
        });
      }
    } catch (e) {
      debugPrint('Load printers error: $e');
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _openAddPrinterScreen() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => const _AddPrinterScreen(),
      ),
    );

    if (created == true) {
      await _loadPrinters();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Printer added successfully')),
        );
      }
    }
  }

  Future<void> _testPrinter(int printerId) async {
    try {
      final response =
          await _api.post('${ApiConstants.restaurantPrinters}/$printerId/test');
      if (response['success'] == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Test print sent successfully')),
        );
      }
    } catch (e) {
      debugPrint('Test printer error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to test printer: $e')),
        );
      }
    }
  }

  Future<void> _setDefaultPrinter(int printerId) async {
    try {
      final response = await _api
          .post('${ApiConstants.restaurantPrinters}/$printerId/default');
      if (response['success'] == true) {
        await _loadPrinters();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Default printer updated')),
          );
        }
      }
    } catch (e) {
      debugPrint('Set default printer error: $e');
    }
  }

  Future<void> _deletePrinter(int printerId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Printer'),
        content: const Text('Are you sure you want to delete this printer?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      final response =
          await _api.delete('${ApiConstants.restaurantPrinters}/$printerId');
      if (response['success'] == true) {
        await _loadPrinters();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Printer deleted successfully')),
          );
        }
      }
    } catch (e) {
      debugPrint('Delete printer error: $e');
    }
  }

  Future<void> _updateAutoPrint(bool value) async {
    final previousValue = _autoPrintNewOrders;
    setState(() {
      _autoPrintNewOrders = value;
      _isUpdatingAutoPrint = true;
    });

    try {
      final response = await _api.post(
        ApiConstants.restaurantPrinterSettings,
        data: {
          'auto_print_new_orders': value,
        },
      );

      if (!mounted) return;

      if (response['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              value
                  ? 'Auto print enabled for new orders'
                  : 'Auto print disabled for new orders',
            ),
          ),
        );
      } else {
        throw Exception(response['message'] ?? 'Could not update setting');
      }
    } catch (e) {
      if (!mounted) return;
      setState(() => _autoPrintNewOrders = previousValue);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to update auto print: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _isUpdatingAutoPrint = false);
      }
    }
  }

  String _connectionLabel(Map<String, dynamic> printer) {
    final type = '${printer['printer_type'] ?? 'network'}'.toLowerCase();
    if (type == 'bluetooth') {
      final mac = '${printer['bluetooth_mac'] ?? ''}'.trim();
      return mac.isEmpty ? 'Bluetooth printer' : 'Bluetooth • $mac';
    }
    if (type == 'usb') {
      final path = '${printer['usb_path'] ?? ''}'.trim();
      return path.isEmpty ? 'USB printer' : 'USB • $path';
    }

    final ip = '${printer['ip_address'] ?? ''}'.trim();
    final port = printer['port'];
    if (ip.isEmpty) return 'Network printer';
    return 'Wi-Fi • $ip${port != null ? ':$port' : ''}';
  }

  IconData _printerIcon(String type) {
    switch (type.toLowerCase()) {
      case 'bluetooth':
        return Icons.bluetooth_rounded;
      case 'usb':
        return Icons.usb_rounded;
      default:
        return Icons.wifi_tethering_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Printers'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: _openAddPrinterScreen,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _printers.isEmpty
              ? Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    FoodFlowTheme.emptyState(
                      icon: Icons.print_outlined,
                      title: 'No printers configured',
                      subtitle:
                          'Search nearby Bluetooth or Wi-Fi printers and save them for KOT printing.',
                    ),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 28),
                      child: ElevatedButton.icon(
                        onPressed: _openAddPrinterScreen,
                        icon: const Icon(Icons.radar_rounded),
                        label: const Text('Search Printers'),
                      ),
                    ),
                  ],
                )
              : ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  itemCount: _printers.length + 1,
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return Column(
                        children: [
                          PremiumRestaurantHeader(
                            title: 'Kitchen Print Hub',
                            subtitle:
                                '${_printers.length} printer${_printers.length == 1 ? '' : 's'} ready for live tickets.',
                            icon: Icons.print,
                            trailing: IconButton(
                              onPressed: _openAddPrinterScreen,
                              icon: const Icon(Icons.add),
                              color: Colors.white,
                              style: IconButton.styleFrom(
                                backgroundColor: Colors.white.withOpacity(0.14),
                              ),
                            ),
                          ),
                          Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            decoration: RestaurantPremium.panel(radius: 16),
                            child: SwitchListTile(
                              value: _autoPrintNewOrders,
                              onChanged: _isUpdatingAutoPrint
                                  ? null
                                  : _updateAutoPrint,
                              title: const Text(
                                'Auto print on new order',
                                style: TextStyle(fontWeight: FontWeight.w700),
                              ),
                              subtitle: Text(
                                _autoPrintNewOrders
                                    ? 'New orders will automatically print on the active default printer.'
                                    : 'Keep this off if you want to manually print KOT after review.',
                              ),
                              secondary: _isUpdatingAutoPrint
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.print_rounded),
                            ),
                          ),
                        ],
                      );
                    }

                    final printer = Map<String, dynamic>.from(_printers[index - 1]);
                    final type = '${printer['printer_type'] ?? 'network'}';

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: RestaurantPremium.panel(radius: 16),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: FoodFlowTheme.orange
                                        .withOpacity(0.10),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Icon(
                                    _printerIcon(type),
                                    color: FoodFlowTheme.orange,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        '${printer['printer_name']}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          fontSize: 16,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        _connectionLabel(printer),
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey.shade700,
                                        ),
                                      ),
                                      Text(
                                        '${type.toUpperCase()} • ${printer['paper_size']}mm',
                                        style: TextStyle(
                                          fontSize: 12,
                                          color: Colors.grey.shade500,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                if (printer['is_default'] == true)
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.green.shade100,
                                      borderRadius: BorderRadius.circular(999),
                                    ),
                                    child: const Text(
                                      'Default',
                                      style: TextStyle(
                                        fontSize: 10,
                                        color: Colors.green,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 14),
                            Row(
                              children: [
                                if (printer['is_default'] != true)
                                  TextButton(
                                    onPressed: () =>
                                        _setDefaultPrinter(printer['id'] as int),
                                    child: const Text('Set as Default'),
                                  ),
                                const Spacer(),
                                if (printer['is_active'] == true)
                                  TextButton(
                                    onPressed: () =>
                                        _testPrinter(printer['id'] as int),
                                    child: const Text('Test Print'),
                                  ),
                                IconButton(
                                  icon: const Icon(
                                    Icons.delete_outline,
                                    color: Colors.red,
                                  ),
                                  onPressed: () =>
                                      _deletePrinter(printer['id'] as int),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}

class _AddPrinterScreen extends StatefulWidget {
  const _AddPrinterScreen();

  @override
  State<_AddPrinterScreen> createState() => _AddPrinterScreenState();
}

class _AddPrinterScreenState extends State<_AddPrinterScreen> {
  final ApiService _api = ApiService();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _ipController = TextEditingController();
  final TextEditingController _portController =
      TextEditingController(text: '9100');
  final TextEditingController _bluetoothMacController = TextEditingController();

  String _printerType = 'network';
  int _paperSize = 80;
  bool _isScanning = false;
  bool _isSaving = false;
  bool _makeDefault = false;
  List<Map<String, dynamic>> _networkPrinters = [];
  List<Map<String, dynamic>> _bluetoothPrinters = [];

  @override
  void dispose() {
    _searchController.dispose();
    _nameController.dispose();
    _ipController.dispose();
    _portController.dispose();
    _bluetoothMacController.dispose();
    super.dispose();
  }

  Future<void> _scanNetworkPrinters() async {
    setState(() => _isScanning = true);
    try {
      final printers = await PrinterDiscoveryService.discoverNetworkPrinters();
      if (!mounted) return;
      setState(() => _networkPrinters = printers);
    } catch (e) {
      _showSnack('Wi-Fi scan failed: $e');
    } finally {
      if (mounted) {
        setState(() => _isScanning = false);
      }
    }
  }

  Future<void> _scanBluetoothPrinters() async {
    setState(() => _isScanning = true);
    try {
      final granted =
          await PrinterDiscoveryService.requestBluetoothPermissions();
      if (!granted) {
        _showSnack(
          'Bluetooth permission is required to search paired printers.',
        );
        return;
      }
      final printers = await PrinterDiscoveryService.discoverBluetoothPrinters();
      if (!mounted) return;
      setState(() => _bluetoothPrinters = printers);
    } catch (e) {
      _showSnack('Bluetooth scan failed: $e');
    } finally {
      if (mounted) {
        setState(() => _isScanning = false);
      }
    }
  }

  void _applyDiscoveredPrinter(Map<String, dynamic> printer) {
    final type = '${printer['type'] ?? 'network'}';
    setState(() {
      _printerType = type;
      _nameController.text = '${printer['name'] ?? ''}';
      if (type == 'bluetooth') {
        _bluetoothMacController.text = '${printer['mac'] ?? ''}';
      } else {
        _ipController.text = '${printer['ip'] ?? ''}';
        _portController.text = '${printer['port'] ?? 9100}';
      }
    });
  }

  Future<void> _savePrinter() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    try {
      final response = await _api.post(
        ApiConstants.restaurantPrinters,
        data: {
          'printer_name': _nameController.text.trim(),
          'printer_type': _printerType,
          'ip_address':
              _printerType == 'network' ? _ipController.text.trim() : null,
          'port': _printerType == 'network'
              ? int.tryParse(_portController.text.trim()) ?? 9100
              : null,
          'bluetooth_mac': _printerType == 'bluetooth'
              ? _bluetoothMacController.text.trim()
              : null,
          'paper_size': _paperSize,
          'is_default': _makeDefault,
          'is_active': true,
        },
      );

      if (!mounted) return;
      if (response['success'] == true) {
        Navigator.of(context).pop(true);
      }
    } catch (e) {
      _showSnack('Could not save printer: $e');
    } finally {
      if (mounted) {
        setState(() => _isSaving = false);
      }
    }
  }

  void _showSnack(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }

  List<Map<String, dynamic>> _filteredResults(List<Map<String, dynamic>> source) {
    final query = _searchController.text.trim().toLowerCase();
    if (query.isEmpty) return source;
    return source.where((printer) {
      final name = '${printer['name'] ?? ''}'.toLowerCase();
      final ip = '${printer['ip'] ?? ''}'.toLowerCase();
      final mac = '${printer['mac'] ?? ''}'.toLowerCase();
      return name.contains(query) || ip.contains(query) || mac.contains(query);
    }).toList();
  }

  Widget _buildDiscoverySection({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color accent,
    required VoidCallback onScan,
    required List<Map<String, dynamic>> printers,
  }) {
    final results = _filteredResults(printers);
    return Container(
      decoration: RestaurantPremium.panel(radius: 20),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: accent.withOpacity(0.10),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: accent),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                        ),
                      ),
                      Text(
                        subtitle,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade600,
                        ),
                      ),
                    ],
                  ),
                ),
                FilledButton.tonalIcon(
                  onPressed: _isScanning ? null : onScan,
                  icon: _isScanning
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.radar_rounded),
                  label: Text(_isScanning ? 'Scanning' : 'Scan'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (results.isEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.grey.shade50,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.grey.shade200),
                ),
                child: Text(
                  'No printers found yet. Run a scan or use the manual fields below.',
                  style: TextStyle(
                    color: Colors.grey.shade700,
                    fontSize: 13,
                  ),
                ),
              )
            else
              ...results.map(
                (printer) => InkWell(
                  onTap: () => _applyDiscoveredPrinter(printer),
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: Colors.grey.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(icon, color: accent),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                '${printer['name'] ?? 'Printer'}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                printer['type'] == 'bluetooth'
                                    ? '${printer['mac'] ?? 'Paired Bluetooth printer'}'
                                    : '${printer['ip'] ?? ''}:${printer['port'] ?? 9100}',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: Colors.grey.shade600,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 14),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Add Printer'),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: SizedBox(
            height: 54,
            child: ElevatedButton.icon(
              onPressed: _isSaving ? null : _savePrinter,
              icon: _isSaving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.save_rounded),
              label: Text(_isSaving ? 'Saving...' : 'Save Printer'),
            ),
          ),
        ),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 120),
          children: [
            PremiumRestaurantHeader(
              title: 'Search Nearby Printers',
              subtitle:
                  'Find paired Bluetooth printers or scan your local Wi-Fi for thermal printers.',
              icon: Icons.print_rounded,
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _searchController,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search_rounded),
                hintText: 'Search by printer name, IP, or MAC',
                filled: true,
                fillColor: Colors.white,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(18),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
            const SizedBox(height: 16),
            _buildDiscoverySection(
              title: 'Wi-Fi Printers',
              subtitle: 'Search thermal printers on the same local network.',
              icon: Icons.wifi_tethering_rounded,
              accent: const Color(0xFF2563EB),
              onScan: _scanNetworkPrinters,
              printers: _networkPrinters,
            ),
            const SizedBox(height: 14),
            _buildDiscoverySection(
              title: 'Bluetooth Printers',
              subtitle: 'Search paired Bluetooth printers on this device.',
              icon: Icons.bluetooth_rounded,
              accent: const Color(0xFF0EA5E9),
              onScan: _scanBluetoothPrinters,
              printers: _bluetoothPrinters,
            ),
            const SizedBox(height: 16),
            Container(
              decoration: RestaurantPremium.panel(radius: 20),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Printer Details',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        ChoiceChip(
                          label: const Text('Wi-Fi'),
                          selected: _printerType == 'network',
                          onSelected: (_) =>
                              setState(() => _printerType = 'network'),
                        ),
                        ChoiceChip(
                          label: const Text('Bluetooth'),
                          selected: _printerType == 'bluetooth',
                          onSelected: (_) =>
                              setState(() => _printerType = 'bluetooth'),
                        ),
                        ChoiceChip(
                          label: const Text('USB'),
                          selected: _printerType == 'usb',
                          onSelected: (_) =>
                              setState(() => _printerType = 'usb'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _nameController,
                      decoration: const InputDecoration(
                        labelText: 'Printer Name',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Printer name is required';
                        }
                        return null;
                      },
                    ),
                    if (_printerType == 'network') ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _ipController,
                        decoration: const InputDecoration(
                          labelText: 'IP Address',
                          hintText: '192.168.1.100',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) {
                          if (_printerType == 'network' &&
                              (value == null || value.trim().isEmpty)) {
                            return 'IP address is required';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _portController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Port',
                          hintText: '9100',
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                    if (_printerType == 'bluetooth') ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _bluetoothMacController,
                        decoration: const InputDecoration(
                          labelText: 'Bluetooth MAC',
                          hintText: 'XX:XX:XX:XX:XX:XX',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) {
                          if (_printerType == 'bluetooth' &&
                              (value == null || value.trim().isEmpty)) {
                            return 'Bluetooth MAC is required';
                          }
                          return null;
                        },
                      ),
                    ],
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      value: _paperSize,
                      decoration: const InputDecoration(
                        labelText: 'Paper Size',
                        border: OutlineInputBorder(),
                      ),
                      items: const [
                        DropdownMenuItem(value: 58, child: Text('58mm')),
                        DropdownMenuItem(value: 80, child: Text('80mm')),
                      ],
                      onChanged: (value) {
                        if (value == null) return;
                        setState(() => _paperSize = value);
                      },
                    ),
                    const SizedBox(height: 12),
                    SwitchListTile(
                      value: _makeDefault,
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Set as default printer'),
                      subtitle: const Text(
                        'Use this printer first for KOT and invoice printing.',
                      ),
                      onChanged: (value) =>
                          setState(() => _makeDefault = value),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
