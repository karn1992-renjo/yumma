// lib/screens/restaurant/restaurant_settings_screen.dart
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../utils/payout_gateway_utils.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

class RestaurantSettingsScreen extends StatefulWidget {
  const RestaurantSettingsScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantSettingsScreen> createState() =>
      _RestaurantSettingsScreenState();
}

class _RestaurantSettingsScreenState extends State<RestaurantSettingsScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();

  Map<String, dynamic> _settings = {};
  bool _isLoading = true;
  bool _isSaving = false;

  // Controllers
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _addressController = TextEditingController();
  final _cityController = TextEditingController();
  final _pincodeController = TextEditingController();
  final _minOrderController = TextEditingController();
  final _latitudeController = TextEditingController();
  final _longitudeController = TextEditingController();
  final _accountHolderController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _accountNumberController = TextEditingController();
  final _ifscController = TextEditingController();
  final _upiIdController = TextEditingController();
  final _stripeAccountController = TextEditingController();
  PlatformFile? _fssaiLicenseFile;

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    _cityController.dispose();
    _pincodeController.dispose();
    _minOrderController.dispose();
    _latitudeController.dispose();
    _longitudeController.dispose();
    _accountHolderController.dispose();
    _bankNameController.dispose();
    _accountNumberController.dispose();
    _ifscController.dispose();
    _upiIdController.dispose();
    _stripeAccountController.dispose();
    super.dispose();
  }

  Future<void> _loadSettings() async {
    setState(() => _isLoading = true);

    try {
      final response = await _api.get(ApiConstants.restaurantSettings);
      if (response['success'] == true) {
        setState(() {
          _settings = response['data'];
        });
        _populateControllers();
      }
    } catch (e) {
      debugPrint('Load settings error: $e');
    }

    setState(() => _isLoading = false);
  }

  void _populateControllers() {
    _nameController.text = _settings['name'] ?? '';
    _emailController.text = _settings['email'] ?? '';
    _phoneController.text = _settings['phone'] ?? '';
    _addressController.text = _settings['address'] ?? '';
    _cityController.text = _settings['city'] ?? '';
    _pincodeController.text = _settings['pincode'] ?? '';
    _minOrderController.text =
        (_settings['min_order_amount'] ?? 199).toString();
    _latitudeController.text = (_settings['latitude'] ?? '').toString();
    _longitudeController.text = (_settings['longitude'] ?? '').toString();
    _accountHolderController.text = _settings['account_holder_name'] ?? '';
    _bankNameController.text = _settings['bank_name'] ?? '';
    _accountNumberController.text = _settings['account_number'] ?? '';
    _ifscController.text = _settings['ifsc_code'] ?? '';
    _upiIdController.text = _settings['upi_id'] ?? '';
    _stripeAccountController.text = _settings['stripe_account_id'] ??
        _settings['gateway_account_id'] ?? '';
  }

  Future<void> _saveSettings() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);

    final data = {
      'name': _nameController.text.trim(),
      'email': _emailController.text.trim(),
      'phone': _phoneController.text.trim(),
      'address': _addressController.text.trim(),
      'city': _cityController.text.trim(),
      'pincode': _pincodeController.text.trim(),
      'account_holder_name': _accountHolderController.text.trim(),
      'bank_name': _bankNameController.text.trim(),
      'account_number': _accountNumberController.text.trim(),
      'ifsc_code': _ifscController.text.trim(),
      'upi_id': _upiIdController.text.trim(),
      'stripe_account_id': _stripeAccountController.text.trim(),
      'gateway_account_id': _stripeAccountController.text.trim(),
    };

    try {
      final response =
          await _api.post(ApiConstants.restaurantSettings, data: data);
      if (response['success'] == true && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Settings saved successfully')),
        );
      }
    } catch (e) {
      debugPrint('Save settings error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save settings: $e')),
        );
      }
    }

    setState(() => _isSaving = false);
  }

  Future<void> _uploadLogo() async {
    final picker = ImagePicker();
    final pickedFile = await picker.pickImage(source: ImageSource.gallery);

    if (pickedFile != null) {
      // Upload logo logic
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Logo upload feature coming soon')),
      );
    }
  }

  Future<void> _pickFssaiLicense() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
      withData: false,
    );
    if (result == null || result.files.isEmpty || !mounted) return;
    setState(() => _fssaiLicenseFile = result.files.single);
  }

  Future<void> _submitLocationChangeRequest() async {
    final lat = double.tryParse(_latitudeController.text.trim());
    final lng = double.tryParse(_longitudeController.text.trim());

    if (lat == null || lng == null || _fssaiLicenseFile?.path == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter lat/long and attach FSSAI license')),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final response = await _api.postMultipart(
        ApiConstants.restaurantLocationChangeRequest,
        fields: {
          'latitude': lat.toString(),
          'longitude': lng.toString(),
        },
        files: {'fssai_license': _fssaiLicenseFile!.path!},
      );

      if (response['success'] == true && mounted) {
        setState(() => _fssaiLicenseFile = null);
        await _loadSettings();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Location request sent for admin approval')),
        );
      }
    } catch (e) {
      debugPrint('Location request error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to submit location request: $e')),
        );
      }
    }

    if (mounted) setState(() => _isSaving = false);
  }

  @override
  Widget build(BuildContext context) {
    final authUser = Provider.of<AuthProvider>(context, listen: false).currentUser;
    final payoutProfile = resolvePayoutGatewayProfile(
      provider: _settings['payout_gateway_provider']?.toString() ??
          _settings['payment_gateway_provider']?.toString() ??
          authUser?.payoutGatewayProvider ??
          authUser?.paymentGatewayProvider,
      countryCode: _settings['country_code']?.toString() ?? authUser?.countryCode,
    );

    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          TextButton(
            onPressed: _isSaving ? null : _saveSettings,
            child: Text(
              'Save',
              style: TextStyle(
                color: _isSaving ? Colors.grey : FoodFlowTheme.orange,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.only(bottom: 32),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              PremiumRestaurantHeader(
                title: 'Restaurant Profile',
                subtitle:
                    'Keep store details, delivery promise, and account security polished.',
                icon: Icons.tune,
                trailing: IconButton(
                  onPressed: _isSaving ? null : _saveSettings,
                  icon: const Icon(Icons.save_outlined),
                  color: Colors.white,
                  style: IconButton.styleFrom(
                    backgroundColor: Colors.white.withOpacity(0.14),
                  ),
                ),
              ),
              // Restaurant Logo
              Center(
                child: Stack(
                  children: [
                    CircleAvatar(
                      radius: 50,
                      backgroundColor: Colors.grey.shade200,
                      backgroundImage: _settings['logo_image'] != null
                          ? NetworkImage(_settings['logo_image'])
                          : null,
                      child: _settings['logo_image'] == null
                          ? Icon(
                              Icons.restaurant,
                              size: 50,
                              color: Colors.grey.shade400,
                            )
                          : null,
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Container(
                        decoration: BoxDecoration(
                          color: FoodFlowTheme.orange,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: IconButton(
                          icon: const Icon(Icons.camera_alt,
                              size: 18, color: Colors.white),
                          onPressed: _uploadLogo,
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(
                            minWidth: 32,
                            minHeight: 32,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              // Restaurant Info
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Restaurant Information',
                          style: TextStyle(
                            fontSize: 16,
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _nameController,
                          decoration: const InputDecoration(
                            labelText: 'Restaurant Name',
                            border: OutlineInputBorder(),
                          ),
                          validator: (value) =>
                              value?.isEmpty == true ? 'Required' : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _emailController,
                          decoration: const InputDecoration(
                            labelText: 'Email',
                            border: OutlineInputBorder(),
                          ),
                          keyboardType: TextInputType.emailAddress,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _phoneController,
                          decoration: const InputDecoration(
                            labelText: 'Phone',
                            border: OutlineInputBorder(),
                          ),
                          keyboardType: TextInputType.phone,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _addressController,
                          decoration: const InputDecoration(
                            labelText: 'Address',
                            border: OutlineInputBorder(),
                          ),
                          maxLines: 2,
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: _cityController,
                                decoration: const InputDecoration(
                                  labelText: 'City',
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _pincodeController,
                                decoration: const InputDecoration(
                                  labelText: 'Pincode',
                                  border: OutlineInputBorder(),
                                ),
                                keyboardType: TextInputType.number,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Order Settings
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Order Settings',
                          style: TextStyle(
                            fontSize: 16,
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _minOrderController,
                          enabled: false,
                          decoration: InputDecoration(
                            labelText: 'Minimum Order Amount',
                            helperText:
                                'Set by admin. Delivery fee and timing are also admin controlled.',
                            prefixText: currencyInputPrefix(context),
                            border: const OutlineInputBorder(),
                          ),
                          keyboardType: TextInputType.number,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Payout Details',
                          style: TextStyle(
                            fontSize: 16,
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          '${payoutProfile.displayName} payouts are configured by admin for ${payoutProfile.countryCode}. Manual withdrawal is disabled.',
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            Chip(label: Text('Gateway: ${payoutProfile.displayName}')),
                            Chip(label: Text('Country: ${payoutProfile.countryCode}')),
                          ],
                        ),
                        const SizedBox(height: 16),
                        TextFormField(
                          controller: _accountHolderController,
                          decoration: const InputDecoration(
                            labelText: 'Account Holder Name',
                            border: OutlineInputBorder(),
                          ),
                          validator: (value) {
                            if (value?.trim().isEmpty ?? true) {
                              return 'Please enter account holder name';
                            }
                            return null;
                          },
                        ),
                        const SizedBox(height: 12),
                        if (payoutProfile.showBankDetails) ...[
                          TextFormField(
                            controller: _bankNameController,
                            decoration: const InputDecoration(
                              labelText: 'Bank Name',
                              border: OutlineInputBorder(),
                            ),
                            validator: payoutProfile.bankDetailsRequired
                                ? (value) {
                                    if (value?.trim().isEmpty ?? true) {
                                      return 'Please enter bank name';
                                    }
                                    return null;
                                  }
                                : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _accountNumberController,
                            decoration: const InputDecoration(
                              labelText: 'Account Number',
                              border: OutlineInputBorder(),
                            ),
                            keyboardType: TextInputType.number,
                            validator: payoutProfile.bankDetailsRequired
                                ? (value) {
                                    if (value?.trim().isEmpty ?? true) {
                                      return 'Please enter account number';
                                    }
                                    return null;
                                  }
                                : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _ifscController,
                            decoration: InputDecoration(
                              labelText: payoutProfile.routingCodeLabel,
                              hintText: payoutProfile.routingCodeHint,
                              border: const OutlineInputBorder(),
                            ),
                            validator: payoutProfile.bankDetailsRequired
                                ? (value) {
                                    if (value?.trim().isEmpty ?? true) {
                                      return 'Please enter ${payoutProfile.routingCodeLabel.toLowerCase()}';
                                    }
                                    return null;
                                  }
                                : null,
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (payoutProfile.supportsUpi) ...[
                          TextFormField(
                            controller: _upiIdController,
                            decoration: const InputDecoration(
                              labelText: 'UPI ID (Optional)',
                              hintText: 'e.g., restaurant@upi',
                              border: OutlineInputBorder(),
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        TextFormField(
                          controller: _stripeAccountController,
                          decoration: InputDecoration(
                            labelText: payoutProfile.accountIdLabel,
                            hintText: payoutProfile.accountIdHint,
                            border: const OutlineInputBorder(),
                          ),
                          validator: payoutProfile.requiresAccountId
                              ? (value) {
                                  if (value?.trim().isEmpty ?? true) {
                                    return 'Please enter ${payoutProfile.accountIdLabel.toLowerCase()}';
                                  }
                                  return null;
                                }
                              : null,
                        ),
                        const SizedBox(height: 12),
                        Text(
                          payoutProfile.helperText,
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Location Change Request',
                          style: TextStyle(
                            fontSize: 16,
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _settings['pending_location_request'] != null
                              ? 'Your previous request is waiting for admin approval.'
                              : 'Restaurant coordinates change only after admin approval.',
                          style: const TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: _latitudeController,
                                decoration: const InputDecoration(
                                  labelText: 'Latitude',
                                  border: OutlineInputBorder(),
                                ),
                                keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _longitudeController,
                                decoration: const InputDecoration(
                                  labelText: 'Longitude',
                                  border: OutlineInputBorder(),
                                ),
                                keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _settings['pending_location_request'] != null ? null : _pickFssaiLicense,
                          icon: const Icon(Icons.attach_file),
                          label: Text(_fssaiLicenseFile == null
                              ? 'Attach FSSAI license'
                              : _fssaiLicenseFile!.name),
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed: _settings['pending_location_request'] != null || _isSaving
                                ? null
                                : _submitLocationChangeRequest,
                            icon: const Icon(Icons.approval_outlined),
                            label: const Text('Apply for Location Change'),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Account Actions
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  decoration: RestaurantPremium.panel(radius: 18),
                  child: Column(
                    children: [
                      ListTile(
                        leading: const Icon(Icons.lock_outline),
                        title: const Text('Change Password'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () {
                          _showChangePasswordDialog();
                        },
                      ),
                      const Divider(height: 1),
                      ListTile(
                        leading: const Icon(Icons.logout, color: Colors.red),
                        title: const Text('Logout',
                            style: TextStyle(color: Colors.red)),
                        onTap: () async {
                          await Provider.of<AuthProvider>(context,
                                  listen: false)
                              .logout();
                          if (mounted) {
                            Navigator.pushReplacementNamed(context, '/login');
                          }
                        },
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }

  void _showChangePasswordDialog() {
    final currentPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();
    final formKey = GlobalKey<FormState>();

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Change Password'),
        content: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: currentPasswordController,
                decoration: const InputDecoration(
                  labelText: 'Current Password',
                  border: OutlineInputBorder(),
                ),
                obscureText: true,
                validator: (value) =>
                    value?.isEmpty == true ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: newPasswordController,
                decoration: const InputDecoration(
                  labelText: 'New Password',
                  border: OutlineInputBorder(),
                ),
                obscureText: true,
                validator: (value) {
                  if (value?.isEmpty == true) return 'Required';
                  if (value!.length < 6) return 'Minimum 6 characters';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: confirmPasswordController,
                decoration: const InputDecoration(
                  labelText: 'Confirm New Password',
                  border: OutlineInputBorder(),
                ),
                obscureText: true,
                validator: (value) {
                  if (value != newPasswordController.text) {
                    return 'Passwords do not match';
                  }
                  return null;
                },
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () async {
              if (formKey.currentState!.validate()) {
                Navigator.pop(context);
                final authProvider =
                    Provider.of<AuthProvider>(context, listen: false);
                final success = await authProvider.updatePassword(
                  currentPassword: currentPasswordController.text,
                  newPassword: newPasswordController.text,
                  newPasswordConfirmation: confirmPasswordController.text,
                );

                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(success
                          ? 'Password changed successfully'
                          : authProvider.error ?? 'Failed to change password'),
                      backgroundColor: success ? Colors.green : Colors.red,
                    ),
                  );
                }
              }
            },
            child: const Text('Update'),
          ),
        ],
      ),
    );
  }
}
