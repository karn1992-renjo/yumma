import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/payout_gateway_utils.dart';

class DriverProfileScreen extends StatefulWidget {
  const DriverProfileScreen({Key? key}) : super(key: key);

  @override
  State<DriverProfileScreen> createState() => _DriverProfileScreenState();
}

class _DriverProfileScreenState extends State<DriverProfileScreen> {
  final ApiService _api = ApiService();
  bool _isLoading = true;
  Map<String, dynamic> _driverData = {};

  @override
  void initState() {
    super.initState();
    _loadDriverData();
  }

  Future<void> _loadDriverData() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get('/driver/profile');
      if (response['success'] == true && mounted) {
        setState(() {
          _driverData = Map<String, dynamic>.from(response['data'] ?? {});
        });
      }
    } catch (e) {
      debugPrint('Load driver data error: $e');
    }
    if (mounted) setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().currentUser;

    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: RefreshIndicator(
        onRefresh: _loadDriverData,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFF161B22), Color(0xFFE64A19)],
                ),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 34,
                    backgroundColor: Colors.white.withOpacity(0.14),
                    child: Text(
                      user?.name.isNotEmpty == true
                          ? user!.name[0].toUpperCase()
                          : 'D',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _driverData['name'] ?? user?.name ?? 'Driver',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _driverData['phone'] ?? user?.phone ?? '',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.82),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _driverRatingLabel(),
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.72),
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            _ProfileMenuItem(
              icon: Icons.person_outline,
              title: 'Personal Details',
              subtitle: 'Name, phone and account identity',
              onTap: () => _openEditor(
                context,
                DriverProfileEditorScreen.personal(initialData: _driverData),
              ),
            ),
            _ProfileMenuItem(
              icon: Icons.two_wheeler,
              title: 'Vehicle Details',
              subtitle: 'Vehicle type, number and driving licence',
              onTap: () => _openEditor(
                context,
                DriverProfileEditorScreen.vehicle(initialData: _driverData),
              ),
            ),
            _ProfileMenuItem(
              icon: Icons.account_balance_wallet_outlined,
              title: 'Payout Details',
              subtitle: 'Bank, wallet or gateway payout account',
              onTap: () => _openEditor(
                context,
                DriverProfileEditorScreen.payout(initialData: _driverData),
              ),
            ),
            _ProfileMenuItem(
              icon: Icons.lock_outline,
              title: 'Security',
              subtitle: 'Change your password',
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const DriverSecurityScreen(),
                ),
              ),
            ),
            _ProfileMenuItem(
              icon: Icons.logout,
              title: 'Logout',
              subtitle: 'Sign out from the driver app',
              iconColor: Colors.red,
              onTap: () async {
                await context.read<AuthProvider>().logout();
                if (mounted) {
                  Navigator.of(context, rootNavigator: true)
                      .pushNamedAndRemoveUntil('/login', (route) => false);
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openEditor(BuildContext context, Widget screen) async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => screen),
    );
    if (saved == true && mounted) {
      await context.read<AuthProvider>().loadUser();
      await _loadDriverData();
    }
  }

  String _driverRatingLabel() {
    final count = int.tryParse('${_driverData['total_ratings'] ?? 0}') ?? 0;
    final rating = double.tryParse('${_driverData['rating'] ?? ''}');
    if (count >= 3 && rating != null && rating > 0) {
      return '${rating.toStringAsFixed(1)} rating';
    }
    return count > 0 ? '$count reviews' : 'New driver';
  }
}

enum DriverProfileSection { personal, vehicle, payout }

class DriverProfileEditorScreen extends StatefulWidget {
  final DriverProfileSection section;
  final Map<String, dynamic> initialData;

  const DriverProfileEditorScreen._({
    super.key,
    required this.section,
    required this.initialData,
  });

  factory DriverProfileEditorScreen.personal({
    Key? key,
    required Map<String, dynamic> initialData,
  }) {
    return DriverProfileEditorScreen._(
      key: key,
      section: DriverProfileSection.personal,
      initialData: initialData,
    );
  }

  factory DriverProfileEditorScreen.vehicle({
    Key? key,
    required Map<String, dynamic> initialData,
  }) {
    return DriverProfileEditorScreen._(
      key: key,
      section: DriverProfileSection.vehicle,
      initialData: initialData,
    );
  }

  factory DriverProfileEditorScreen.payout({
    Key? key,
    required Map<String, dynamic> initialData,
  }) {
    return DriverProfileEditorScreen._(
      key: key,
      section: DriverProfileSection.payout,
      initialData: initialData,
    );
  }

  @override
  State<DriverProfileEditorScreen> createState() =>
      _DriverProfileEditorScreenState();
}

class _DriverProfileEditorScreenState extends State<DriverProfileEditorScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _isSaving = false;

  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  late final TextEditingController _vehicleTypeController;
  late final TextEditingController _vehicleNumberController;
  late final TextEditingController _licenseNumberController;
  late final TextEditingController _accountHolderController;
  late final TextEditingController _bankNameController;
  late final TextEditingController _accountNumberController;
  late final TextEditingController _ifscController;
  late final TextEditingController _upiIdController;
  late final TextEditingController _accountIdController;

  @override
  void initState() {
    super.initState();
    final data = widget.initialData;
    _nameController = TextEditingController(text: data['name'] ?? '');
    _phoneController = TextEditingController(text: data['phone'] ?? '');
    _vehicleTypeController =
        TextEditingController(text: data['vehicle_type'] ?? '');
    _vehicleNumberController =
        TextEditingController(text: data['vehicle_number'] ?? '');
    _licenseNumberController =
        TextEditingController(text: data['license_number'] ?? '');
    _accountHolderController =
        TextEditingController(text: data['account_holder_name'] ?? '');
    _bankNameController = TextEditingController(text: data['bank_name'] ?? '');
    _accountNumberController =
        TextEditingController(text: data['account_number'] ?? '');
    _ifscController = TextEditingController(text: data['ifsc_code'] ?? '');
    _upiIdController = TextEditingController(text: data['upi_id'] ?? '');
    _accountIdController = TextEditingController(
      text: data['stripe_account_id'] ?? data['gateway_account_id'] ?? '',
    );
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _vehicleTypeController.dispose();
    _vehicleNumberController.dispose();
    _licenseNumberController.dispose();
    _accountHolderController.dispose();
    _bankNameController.dispose();
    _accountNumberController.dispose();
    _ifscController.dispose();
    _upiIdController.dispose();
    _accountIdController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authUser = context.watch<AuthProvider>().currentUser;
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: Text(_title),
        actions: [
          TextButton(
            onPressed: _isSaving ? null : _save,
            child: Text(
              'Save',
              style: TextStyle(
                color: _isSaving ? Colors.grey : FoodFlowTheme.crimson,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: _fields(authUser),
        ),
      ),
    );
  }

  String get _title {
    switch (widget.section) {
      case DriverProfileSection.personal:
        return 'Personal Details';
      case DriverProfileSection.vehicle:
        return 'Vehicle Details';
      case DriverProfileSection.payout:
        return 'Payout Details';
    }
  }

  List<Widget> _fields(dynamic authUser) {
    final profile = resolvePayoutGatewayProfile(
      provider: widget.initialData['payout_gateway_provider']?.toString() ??
          widget.initialData['payment_gateway_provider']?.toString() ??
          authUser?.payoutGatewayProvider ??
          authUser?.paymentGatewayProvider,
      countryCode: widget.initialData['country_code']?.toString() ??
          authUser?.countryCode,
    );

    switch (widget.section) {
      case DriverProfileSection.personal:
        return [
          _field('Full Name', _nameController),
          const SizedBox(height: 12),
          _field(
            'Phone Number',
            _phoneController,
            keyboardType: TextInputType.phone,
          ),
        ];
      case DriverProfileSection.vehicle:
        return [
          _field('Vehicle Type', _vehicleTypeController),
          const SizedBox(height: 12),
          _field('Vehicle Number', _vehicleNumberController),
          const SizedBox(height: 12),
          _field('License Number', _licenseNumberController),
        ];
      case DriverProfileSection.payout:
        return [
          _field('Account Holder Name', _accountHolderController),
          const SizedBox(height: 12),
          if (profile.showBankDetails) ...[
            _field(
              'Bank Name',
              _bankNameController,
              required: profile.bankDetailsRequired,
            ),
            const SizedBox(height: 12),
            _field(
              'Account Number',
              _accountNumberController,
              keyboardType: TextInputType.number,
              required: profile.bankDetailsRequired,
            ),
            const SizedBox(height: 12),
            _field(
              profile.routingCodeLabel,
              _ifscController,
              helperText: profile.routingCodeHint,
              required: profile.bankDetailsRequired,
            ),
            const SizedBox(height: 12),
          ],
          if (profile.supportsUpi) ...[
            _field('UPI ID', _upiIdController, required: false),
            const SizedBox(height: 12),
          ],
          _field(
            profile.accountIdLabel,
            _accountIdController,
            required: profile.requiresAccountId,
            helperText: profile.accountIdHint,
          ),
          const SizedBox(height: 12),
          Text(
            profile.helperText,
            style: const TextStyle(
              color: FoodFlowTheme.muted,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ];
    }
  }

  Widget _field(
    String label,
    TextEditingController controller, {
    bool required = true,
    TextInputType keyboardType = TextInputType.text,
    String? helperText,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      decoration: InputDecoration(labelText: label, helperText: helperText),
      validator: required
          ? (value) => value == null || value.trim().isEmpty ? 'Required' : null
          : null,
    );
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSaving = true);

    try {
      final base = Map<String, dynamic>.from(widget.initialData);
      final data = {
        'name': widget.section == DriverProfileSection.personal
            ? _nameController.text.trim()
            : (base['name'] ?? ''),
        'phone': widget.section == DriverProfileSection.personal
            ? _phoneController.text.trim()
            : (base['phone'] ?? ''),
        'vehicle_type': widget.section == DriverProfileSection.vehicle
            ? _vehicleTypeController.text.trim()
            : (base['vehicle_type'] ?? ''),
        'vehicle_number': widget.section == DriverProfileSection.vehicle
            ? _vehicleNumberController.text.trim()
            : (base['vehicle_number'] ?? ''),
        'license_number': widget.section == DriverProfileSection.vehicle
            ? _licenseNumberController.text.trim()
            : (base['license_number'] ?? ''),
        'account_holder_name': widget.section == DriverProfileSection.payout
            ? _accountHolderController.text.trim()
            : (base['account_holder_name'] ?? ''),
        'bank_name': widget.section == DriverProfileSection.payout
            ? _bankNameController.text.trim()
            : (base['bank_name'] ?? ''),
        'account_number': widget.section == DriverProfileSection.payout
            ? _accountNumberController.text.trim()
            : (base['account_number'] ?? ''),
        'ifsc_code': widget.section == DriverProfileSection.payout
            ? _ifscController.text.trim()
            : (base['ifsc_code'] ?? ''),
        'upi_id': widget.section == DriverProfileSection.payout
            ? _upiIdController.text.trim()
            : (base['upi_id'] ?? ''),
        'stripe_account_id': widget.section == DriverProfileSection.payout
            ? _accountIdController.text.trim()
            : (base['stripe_account_id'] ?? ''),
        'gateway_account_id': widget.section == DriverProfileSection.payout
            ? _accountIdController.text.trim()
            : (base['gateway_account_id'] ?? ''),
      };

      final response = await _api.post('/driver/profile', data: data);
      if (response['success'] == true && mounted) {
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update profile: $e')),
        );
      }
    }

    if (mounted) setState(() => _isSaving = false);
  }
}

class DriverSecurityScreen extends StatefulWidget {
  const DriverSecurityScreen({super.key});

  @override
  State<DriverSecurityScreen> createState() => _DriverSecurityScreenState();
}

class _DriverSecurityScreenState extends State<DriverSecurityScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  bool _isSaving = false;

  @override
  void dispose() {
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(title: const Text('Security')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            TextFormField(
              controller: _currentPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Current Password'),
              validator: (value) =>
                  value == null || value.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _newPasswordController,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'New Password'),
              validator: (value) {
                if (value == null || value.isEmpty) return 'Required';
                if (value.length < 6) return 'Minimum 6 characters';
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _confirmPasswordController,
              obscureText: true,
              decoration:
                  const InputDecoration(labelText: 'Confirm New Password'),
              validator: (value) {
                if (value != _newPasswordController.text) {
                  return 'Passwords do not match';
                }
                return null;
              },
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: _isSaving ? null : _savePassword,
              child: const Text('Update Password'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _savePassword() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _isSaving = true);
    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.updatePassword(
      currentPassword: _currentPasswordController.text,
      newPassword: _newPasswordController.text,
      newPasswordConfirmation: _confirmPasswordController.text,
    );

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            success
                ? 'Password changed successfully'
                : authProvider.error ?? 'Failed to change password',
          ),
          backgroundColor: success ? Colors.green : Colors.red,
        ),
      );
      if (success) Navigator.pop(context);
    }
    if (mounted) setState(() => _isSaving = false);
  }
}

class _ProfileMenuItem extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Color? iconColor;

  const _ProfileMenuItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.iconColor,
  });

  @override
  Widget build(BuildContext context) {
    final color = iconColor ?? FoodFlowTheme.crimson;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.10),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: color),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 15,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right, color: FoodFlowTheme.faint),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
