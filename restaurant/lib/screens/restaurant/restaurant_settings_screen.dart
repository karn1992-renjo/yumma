// lib/screens/restaurant/restaurant_settings_screen.dart
import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../providers/auth_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../utils/currency_utils.dart';
import '../../utils/payout_gateway_utils.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';
import '../../widgets/common/network_image_loader.dart';

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
    _stripeAccountController.text =
        _settings['stripe_account_id'] ?? _settings['gateway_account_id'] ?? '';
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
        const SnackBar(
            content: Text('Enter lat/long and attach FSSAI license')),
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
          const SnackBar(
              content: Text('Location request sent for admin approval')),
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
    final authUser =
        Provider.of<AuthProvider>(context, listen: false).currentUser;
    final payoutProfile = resolvePayoutGatewayProfile(
      provider: _settings['payout_gateway_provider']?.toString() ??
          _settings['payment_gateway_provider']?.toString() ??
          authUser?.payoutGatewayProvider ??
          authUser?.paymentGatewayProvider,
      countryCode:
          _settings['country_code']?.toString() ?? authUser?.countryCode,
    );

    if (_isLoading) {
      return Scaffold(
        backgroundColor: foodflow.canvas,
        body: Stack(children: [
          ...AuroraTheme.auroraBlobs(),
          const Center(child: CircularProgressIndicator()),
        ]),
      );
    }

    final topPad = MediaQuery.of(context).padding.top + 64;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text('Settings',
            style: TextStyle(
                color: foodflow.ink,
                fontSize: 17,
                fontWeight: FontWeight.w900)),
      ),
      bottomNavigationBar: ClipRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            decoration: BoxDecoration(
              color: foodflow.canvas.withOpacity(0.82),
              border: Border(top: BorderSide(color: foodflow.glassBorder)),
            ),
            child: SafeArea(
              top: false,
              child: SizedBox(
                height: 50,
                child: ElevatedButton.icon(
                  onPressed: _isSaving ? null : _saveSettings,
                  icon: _isSaving
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.check_rounded),
                  label: Text(_isSaving ? 'Saving…' : 'Save changes'),
                  style: FoodFlowTheme.zomatoPrimaryButton(),
                ),
              ),
            ),
          ),
        ),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(0, topPad, 0, 32),
          child: Form(
            key: _formKey,
            child: Column(
              children: [
                _IdentityHeader(
                  logoUrl: _settings['logo_image']?.toString(),
                  name: _nameController.text.isEmpty
                      ? 'Your restaurant'
                      : _nameController.text,
                  onEditLogo: _uploadLogo,
                ),
                const SizedBox(height: 20),

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
                        Text(
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
                        Text(
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
                        Text(
                          'Payout Details',
                          style: TextStyle(
                            fontSize: 16,
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Payouts are managed by the platform for region ${payoutProfile.countryCode}. Manual withdrawal is disabled.',
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w600,
                          ),
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
                          decoration: const InputDecoration(
                            labelText: 'Payout account ID',
                            hintText:
                                'Only if the platform gave you a payout reference',
                            border: OutlineInputBorder(),
                          ),
                          validator: payoutProfile.requiresAccountId
                              ? (value) {
                                  if (value?.trim().isEmpty ?? true) {
                                    return 'Please enter your payout account ID';
                                  }
                                  return null;
                                }
                              : null,
                        ),
                        const SizedBox(height: 12),
                        Text(
                          'Payout details are used only for platform settlements.',
                          style: TextStyle(
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
                        Text(
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
                          style: TextStyle(
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
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true, signed: true),
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
                                keyboardType:
                                    const TextInputType.numberWithOptions(
                                        decimal: true, signed: true),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed:
                              _settings['pending_location_request'] != null
                                  ? null
                                  : _pickFssaiLicense,
                          icon: const Icon(Icons.attach_file),
                          label: Text(_fssaiLicenseFile == null
                              ? 'Attach FSSAI license'
                              : _fssaiLicenseFile!.name),
                        ),
                        const SizedBox(height: 12),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton.icon(
                            onPressed:
                                _settings['pending_location_request'] != null ||
                                        _isSaving
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
                          final navigator =
                              Navigator.of(context, rootNavigator: true);
                          await Provider.of<AuthProvider>(context,
                                  listen: false)
                              .logout();
                          navigator.pushNamedAndRemoveUntil(
                              '/login', (route) => false);
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
      ]),
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

class _IdentityHeader extends StatelessWidget {
  const _IdentityHeader({
    required this.logoUrl,
    required this.name,
    required this.onEditLogo,
  });

  final String? logoUrl;
  final String name;
  final VoidCallback onEditLogo;

  @override
  Widget build(BuildContext context) {
    final hasLogo = logoUrl != null && logoUrl!.trim().isNotEmpty;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: foodflow.brandGradient,
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: foodflow.orange.withOpacity(0.26),
              blurRadius: 22,
              offset: const Offset(0, 12),
            ),
          ],
        ),
        child: Row(
          children: [
            GestureDetector(
              onTap: onEditLogo,
              child: Stack(
                children: [
                  Container(
                    width: 60,
                    height: 60,
                    clipBehavior: Clip.antiAlias,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.18),
                      shape: BoxShape.circle,
                    ),
                    child: hasLogo
                        ? NetworkImageLoader(
                            imageUrl: logoUrl!,
                            width: 60,
                            height: 60,
                            fit: BoxFit.cover,
                            errorWidget: const Icon(Icons.restaurant,
                                color: Colors.white),
                          )
                        : const Icon(Icons.restaurant, color: Colors.white),
                  ),
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(Icons.camera_alt_rounded,
                          size: 12, color: foodflow.orange),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  GestureDetector(
                    onTap: onEditLogo,
                    child: Text(
                      'Tap the logo to change your photo',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.85),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
