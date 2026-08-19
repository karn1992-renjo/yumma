import 'dart:io';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../config/app_config.dart';
import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../services/location_service.dart';
import '../../services/partner_application_service.dart';
import 'otp_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({
    super.key,
    this.initialPhone,
  });

  final String? initialPhone;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _text = Color(0xFF111827);
  static const _subtext = Color(0xFF6B7280);
  static const _line = Color(0xFFE5E7EB);

  final _formKey = GlobalKey<FormState>();
  final _picker = ImagePicker();
  final _locationService = LocationService();
  final _applicationService = PartnerApplicationService.instance;
  final _firebasePhoneAuthService = FirebasePhoneAuthService();

  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _dobController = TextEditingController();
  final _cityController = TextEditingController();
  final _addressController = TextEditingController();
  final _landmarkController = TextEditingController();
  final _locationSearchController = TextEditingController();
  final _vehicleNumberController = TextEditingController();
  final _vehicleModelController = TextEditingController();
  final _licenseNumberController = TextEditingController();
  final _bankHolderController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _ifscController = TextEditingController();
  final _accountNumberController = TextEditingController();
  final _upiController = TextEditingController();

  AppBranding _branding = AppBranding.fallback();
  List<Map<String, dynamic>> _deliveryAreas = const [];

  String _gender = 'Male';
  String _vehicleType = 'bike';
  String _fuelType = 'petrol';
  String? _verifiedPhoneToken;
  String? _verifiedPhoneNumber;
  int? _selectedAreaId;
  double? _latitude;
  double? _longitude;
  bool _agreeTerms = true;
  bool _backgroundLocationEnabled = true;
  bool _notificationPermissionEnabled = true;
  bool _isLoadingBranding = true;
  bool _isLoadingAreas = true;
  bool _isLocating = false;
  bool _isSubmitting = false;
  bool _isSendingOtp = false;

  File? _profilePhoto;
  File? _vehicleImage;
  File? _aadhaarFile;
  File? _panFile;
  File? _licenseFile;
  File? _rcFile;
  File? _insuranceFile;

  Color get _primary => AppConfig.primaryColor;
  Color get _secondary => AppConfig.secondaryColor;
  bool get _isPhoneVerified =>
      _verifiedPhoneToken != null && _verifiedPhoneNumber != null;

  @override
  void initState() {
    super.initState();
    _phoneController.text = _stripCountryCode(widget.initialPhone?.trim() ?? '');
    _loadBootstrap();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _dobController.dispose();
    _cityController.dispose();
    _addressController.dispose();
    _landmarkController.dispose();
    _locationSearchController.dispose();
    _vehicleNumberController.dispose();
    _vehicleModelController.dispose();
    _licenseNumberController.dispose();
    _bankHolderController.dispose();
    _bankNameController.dispose();
    _ifscController.dispose();
    _accountNumberController.dispose();
    _upiController.dispose();
    super.dispose();
  }

  Future<void> _loadBootstrap() async {
    await Future.wait([
      _loadBranding(),
      _loadDeliveryAreas(),
    ]);
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
      _isLoadingBranding = false;
      if ((widget.initialPhone ?? '').isNotEmpty) {
        _phoneController.text = _stripCountryCode(widget.initialPhone!.trim());
      }
    });
  }

  Future<void> _loadDeliveryAreas() async {
    try {
      final areas = await _applicationService.fetchDeliveryAreas();
      if (!mounted) return;
      setState(() {
        _deliveryAreas = areas;
      });
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Could not load delivery areas right now.'),
        ),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoadingAreas = false);
      }
    }
  }

  String _normalizedPhone() {
    final raw = _phoneController.text.trim();
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    final dialCode = _branding.defaultMobileCountryCode;
    final dialDigits = dialCode.replaceAll(RegExp(r'\D'), '');

    if (raw.startsWith('+')) return '+$digits';
    if (digits.startsWith(dialDigits)) return '+$digits';
    return '$dialCode$digits';
  }

  String _stripCountryCode(String phone) {
    if (phone.isEmpty) return '';
    final digits = phone.replaceAll(RegExp(r'\D'), '');
    final dialDigits =
        _branding.defaultMobileCountryCode.replaceAll(RegExp(r'\D'), '');
    if (dialDigits.isNotEmpty && digits.startsWith(dialDigits)) {
      return digits.substring(dialDigits.length);
    }
    return digits;
  }

  Future<void> _pickFile(ValueChanged<File> onPicked) async {
    final file = await _picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 82,
      maxWidth: 1800,
    );
    if (file == null || !mounted) return;
    onPicked(File(file.path));
  }

  Future<void> _useCurrentLocation() async {
    setState(() => _isLocating = true);
    try {
      final position = await _locationService.getCurrentLocation();
      if (position == null) {
        _showMessage('Location unavailable. Please enter address manually.',
            isError: true);
        return;
      }

      _latitude = position.latitude;
      _longitude = position.longitude;

      final data = await _locationService.getAddressFromLatLng(
        position.latitude,
        position.longitude,
      );
      if (!mounted) return;

      if (data != null) {
        setState(() {
          if ((data['city'] ?? '').isNotEmpty) {
            _cityController.text = data['city']!;
          }
          if ((data['address'] ?? '').isNotEmpty) {
            _addressController.text = data['address']!;
            _locationSearchController.text = data['address']!;
          }
        });
      }

      _autoAssignAreaFromCurrentLocation(showFeedback: true);
    } finally {
      if (mounted) {
        setState(() => _isLocating = false);
      }
    }
  }

  Future<void> _searchLocationManually() async {
    final query = _locationSearchController.text.trim().isNotEmpty
        ? _locationSearchController.text.trim()
        : _addressController.text.trim();
    if (query.isEmpty) {
      _showMessage('Enter an area, landmark, or pincode to search.', isError: true);
      return;
    }

    setState(() => _isLocating = true);
    try {
      final location = await _locationService.getLocationFromAddress(query);
      if (location == null) {
        _showMessage('No location found for that search.', isError: true);
        return;
      }

      _latitude = location['lat'] as double?;
      _longitude = location['lng'] as double?;

      if (_latitude == null || _longitude == null) {
        _showMessage('Location coordinates could not be resolved.', isError: true);
        return;
      }

      final data = await _locationService.getAddressFromLatLng(
        _latitude!,
        _longitude!,
      );
      if (!mounted) return;

      setState(() {
        if ((data?['city'] ?? '').isNotEmpty) {
          _cityController.text = data!['city']!;
        } else if ((location['city']?.toString() ?? '').isNotEmpty) {
          _cityController.text = location['city'].toString();
        }
        if ((data?['address'] ?? '').isNotEmpty) {
          _addressController.text = data!['address']!;
          _locationSearchController.text = data['address']!;
        }
      });

      _autoAssignAreaFromCurrentLocation(showFeedback: true);
    } finally {
      if (mounted) {
        setState(() => _isLocating = false);
      }
    }
  }

  Future<void> _verifyMobile() async {
    if (_isSendingOtp) return;
    if (_phoneController.text.trim().isEmpty) {
      _showMessage('Enter your mobile number first.', isError: true);
      return;
    }

    setState(() => _isSendingOtp = true);
    try {
      final authProvider = context.read<AuthProvider>();
      final phone = _normalizedPhone();
      final status = await authProvider.getPhoneStatus(
        phone: phone,
        role: 'driver',
      );

      if (!mounted) return;

      if (status == null) {
        _showMessage(
          authProvider.error ?? 'Unable to validate your mobile number.',
          isError: true,
        );
        return;
      }

      if (status['exists'] == true) {
        _showMessage(
          'A driver account already exists with this mobile number. Please sign in.',
          isError: true,
        );
        return;
      }

      final pendingApplication = status['pending_application'];
      if (pendingApplication is Map &&
          (pendingApplication['application_number']?.toString().isNotEmpty ??
              false)) {
        Navigator.pushReplacementNamed(
          context,
          '/application-status',
          arguments: pendingApplication['application_number']?.toString(),
        );
        return;
      }

      String? firebaseVerificationId;
      if (_branding.usesFirebasePhoneAuth) {
        try {
          firebaseVerificationId = await _firebasePhoneAuthService.sendOtp(
            phone: phone,
            countryCode: _branding.defaultMobileCountryCode,
          );
        } catch (error) {
          if (!mounted) return;
          _showMessage(
            error.toString().replaceFirst('Exception: ', ''),
            isError: true,
          );
          return;
        }
      } else {
        final sent = await authProvider.sendLoginOtp(
          phone: phone,
          flow: 'signup',
          role: 'driver',
        );

        if (!mounted) return;

        if (!sent) {
          _showMessage(authProvider.error ?? 'Failed to send OTP', isError: true);
          return;
        }
      }

      final result = await Navigator.of(context).push<Map<String, dynamic>>(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: phone,
            countryCode: _branding.defaultMobileCountryCode,
            appName: _branding.displayName,
            role: 'driver',
            flow: 'signup',
            useFirebasePhoneAuth: _branding.usesFirebasePhoneAuth,
            initialFirebaseVerificationId: firebaseVerificationId,
          ),
        ),
      );

      if (!mounted || result == null) return;

      setState(() {
        _verifiedPhoneToken = result['verified_phone_token']?.toString();
        _verifiedPhoneNumber = result['phone']?.toString() ?? phone;
        _phoneController.text = _stripCountryCode(_verifiedPhoneNumber!);
      });

      _showMessage('Mobile number verified successfully.');
    } finally {
      if (mounted) {
        setState(() => _isSendingOtp = false);
      }
    }
  }

  Future<void> _submitApplication() async {
    if (!_formKey.currentState!.validate()) return;

    if (!_agreeTerms) {
      _showMessage('Please accept the terms and conditions.', isError: true);
      return;
    }
    if (!_isPhoneVerified) {
      await _verifyMobile();
      if (!_isPhoneVerified) return;
    }
    if (_latitude == null || _longitude == null) {
      _showMessage(
        'Use current location first so we can auto-assign your delivery zone.',
        isError: true,
      );
      return;
    }
    if (_selectedAreaId == null) {
      _autoAssignAreaFromCurrentLocation();
    }
    if (_selectedAreaId == null) {
      _showMessage(
        'No delivery zone matched your current location.',
        isError: true,
      );
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      final response = await _applicationService.submitApplication(
        fields: {
          'partner_type': 'driver',
          'verified_phone_token': _verifiedPhoneToken!,
          'full_name': _nameController.text.trim(),
          'email': _emailController.text.trim(),
          'phone': _verifiedPhoneNumber!,
          'date_of_birth': _dobController.text.trim(),
          'gender': _gender,
          'city': _cityController.text.trim(),
          'address': _addressController.text.trim(),
          'landmark': _landmarkController.text.trim(),
          'vehicle_type': _vehicleType,
          'vehicle_number': _vehicleNumberController.text.trim(),
          'vehicle_model': _vehicleModelController.text.trim(),
          'fuel_type': _fuelType,
          'license_number': _licenseNumberController.text.trim(),
          'area_id': '$_selectedAreaId',
          'latitude': _latitude?.toString() ?? '',
          'longitude': _longitude?.toString() ?? '',
          'bank_holder_name': _bankHolderController.text.trim(),
          'bank_name': _bankNameController.text.trim(),
          'bank_ifsc': _ifscController.text.trim(),
          'bank_account_number': _accountNumberController.text.trim(),
          'upi_id': _upiController.text.trim(),
          'background_location_enabled':
              _backgroundLocationEnabled ? '1' : '0',
          'notification_permission_enabled':
              _notificationPermissionEnabled ? '1' : '0',
          'terms': '1',
        },
        files: {
          if (_profilePhoto != null) 'profile_photo': _profilePhoto!.path,
          if (_vehicleImage != null) 'vehicle_image': _vehicleImage!.path,
          if (_licenseFile != null) 'license_document': _licenseFile!.path,
          if (_aadhaarFile != null) 'aadhar_card': _aadhaarFile!.path,
          if (_panFile != null) 'pan_card': _panFile!.path,
          if (_rcFile != null) 'vehicle_rc': _rcFile!.path,
          if (_insuranceFile != null) 'insurance_document': _insuranceFile!.path,
        },
      );

      if (!mounted) return;

      Navigator.pushReplacementNamed(
        context,
        '/application-status',
        arguments: response['data']?['application_number']?.toString(),
      );
    } catch (e) {
      if (!mounted) return;
      _showMessage(e.toString().replaceFirst('Exception: ', ''), isError: true);
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  InputDecoration _fieldDecoration({
    required String hint,
    Widget? prefixIcon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        color: Color(0xFF9CA3AF),
      ),
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: _line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: _primary, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: Colors.red),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: Colors.red, width: 1.4),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        return Scaffold(
          backgroundColor: const Color(0xFFF8FAFC),
          body: SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          style: IconButton.styleFrom(
                            backgroundColor: Colors.white,
                          ),
                          icon: const Icon(Icons.arrow_back_rounded),
                        ),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Text(
                            'Driver Registration',
                            style: TextStyle(
                              color: _text,
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Login'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    _hero(),
                    const SizedBox(height: 18),
                    _sectionCard(
                      title: '1. Verify mobile number',
                      subtitle:
                          'Use the same real OTP flow as customer login before submitting your partner application.',
                      child: Column(
                        children: [
                          TextFormField(
                            controller: _phoneController,
                            readOnly: _isPhoneVerified,
                            keyboardType: TextInputType.phone,
                            onChanged: (_) {
                              if (_isPhoneVerified) return;
                              if (_verifiedPhoneToken != null) {
                                setState(() {
                                  _verifiedPhoneToken = null;
                                  _verifiedPhoneNumber = null;
                                });
                              }
                            },
                            decoration: _fieldDecoration(
                              hint: 'Mobile number',
                              prefixIcon: const Icon(Icons.phone_android_rounded),
                              suffixIcon: TextButton(
                                onPressed: auth.isLoading ||
                                        _isSendingOtp ||
                                        _isPhoneVerified
                                    ? null
                                    : _verifyMobile,
                                child: Text(
                                  _isPhoneVerified
                                      ? 'Verified'
                                      : _isSendingOtp
                                          ? 'Sending...'
                                          : 'Verify',
                                ),
                              ),
                            ),
                            validator: (value) {
                              final digits =
                                  (value ?? '').replaceAll(RegExp(r'\D'), '');
                              if (digits.length < 8) {
                                return 'Enter a valid mobile number';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 12),
                          _infoBanner(
                            icon: _isPhoneVerified
                                ? Icons.verified_rounded
                                : Icons.sms_outlined,
                            text: _isPhoneVerified
                                ? 'Mobile verified: $_verifiedPhoneNumber'
                                : 'Verify your mobile first. Admin approval will create your driver account on this number.',
                            accent: _isPhoneVerified ? _primary : _secondary,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    _sectionCard(
                      title: '2. Personal details',
                      subtitle: 'These details go into the admin verification queue.',
                      child: Column(
                        children: [
                          TextFormField(
                            controller: _nameController,
                            decoration: _fieldDecoration(
                              hint: 'Full name',
                              prefixIcon: const Icon(Icons.person_outline_rounded),
                            ),
                            validator: (value) =>
                                (value == null || value.trim().isEmpty)
                                    ? 'Full name is required'
                                    : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            decoration: _fieldDecoration(
                              hint: 'Email address',
                              prefixIcon: const Icon(Icons.email_outlined),
                            ),
                            validator: (value) {
                              if (value == null || value.trim().isEmpty) {
                                return 'Email is required';
                              }
                              if (!value.contains('@')) {
                                return 'Enter a valid email';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _dobController,
                            decoration: _fieldDecoration(
                              hint: 'Date of birth (YYYY-MM-DD)',
                              prefixIcon: const Icon(Icons.cake_outlined),
                            ),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            value: _gender,
                            decoration: _fieldDecoration(
                              hint: 'Gender',
                              prefixIcon: const Icon(Icons.wc_rounded),
                            ),
                            items: const [
                              DropdownMenuItem(value: 'Male', child: Text('Male')),
                              DropdownMenuItem(
                                  value: 'Female', child: Text('Female')),
                              DropdownMenuItem(value: 'Other', child: Text('Other')),
                            ],
                            onChanged: (value) {
                              if (value == null) return;
                              setState(() => _gender = value);
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    _sectionCard(
                      title: '3. Address and zone',
                      subtitle:
                          'Use current location to prefill address and auto-assign the correct delivery dispatch zone.',
                      child: Column(
                        children: [
                          Align(
                            alignment: Alignment.centerLeft,
                            child: OutlinedButton.icon(
                              onPressed: _isLocating ? null : _useCurrentLocation,
                              icon: const Icon(Icons.my_location_rounded),
                              label:
                                  Text(_isLocating ? 'Locating...' : 'Use Current Location'),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: TextFormField(
                                  controller: _locationSearchController,
                                  textInputAction: TextInputAction.search,
                                  onFieldSubmitted: (_) => _searchLocationManually(),
                                  decoration: _fieldDecoration(
                                    hint: 'Search by area, landmark, or pincode',
                                    prefixIcon: const Icon(Icons.search_rounded),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              FilledButton.tonal(
                                onPressed: _isLocating ? null : _searchLocationManually,
                                style: FilledButton.styleFrom(
                                  minimumSize: const Size(92, 56),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(16),
                                  ),
                                ),
                                child: const Text('Search'),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _cityController,
                            decoration: _fieldDecoration(
                              hint: 'City',
                              prefixIcon:
                                  const Icon(Icons.location_city_outlined),
                            ),
                            validator: (value) =>
                                (value == null || value.trim().isEmpty)
                                    ? 'City is required'
                                    : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _addressController,
                            minLines: 2,
                            maxLines: 3,
                            decoration: _fieldDecoration(
                              hint: 'Full address',
                              prefixIcon: const Icon(Icons.home_work_outlined),
                            ),
                            validator: (value) =>
                                (value == null || value.trim().isEmpty)
                                    ? 'Address is required'
                                    : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _landmarkController,
                            decoration: _fieldDecoration(
                              hint: 'Landmark (optional)',
                              prefixIcon: const Icon(Icons.place_outlined),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: _line),
                            ),
                            child: Row(
                              children: [
                                Icon(
                                  Icons.map_outlined,
                                  color: _selectedAreaId == null
                                      ? _subtext
                                      : _primary,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      const Text(
                                        'Auto-assigned delivery zone',
                                        style: TextStyle(
                                          color: _subtext,
                                          fontSize: 12,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        _selectedAreaName(),
                                        style: const TextStyle(
                                          color: _text,
                                          fontSize: 16,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    _sectionCard(
                      title: '4. Vehicle and documents',
                      subtitle:
                          'Vehicle details and files are uploaded directly into the partner application reviewed by admin.',
                      child: Column(
                        children: [
                          DropdownButtonFormField<String>(
                            value: _vehicleType,
                            decoration: _fieldDecoration(
                              hint: 'Vehicle type',
                              prefixIcon:
                                  const Icon(Icons.two_wheeler_rounded),
                            ),
                            items: const [
                              DropdownMenuItem(value: 'bike', child: Text('Bike')),
                              DropdownMenuItem(
                                  value: 'ev_scooter', child: Text('EV Scooter')),
                              DropdownMenuItem(
                                  value: 'bicycle', child: Text('Bicycle')),
                            ],
                            onChanged: (value) {
                              if (value == null) return;
                              setState(() => _vehicleType = value);
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _vehicleNumberController,
                            decoration: _fieldDecoration(
                              hint: 'Vehicle number',
                              prefixIcon: const Icon(Icons.pin_outlined),
                            ),
                            validator: (value) =>
                                (value == null || value.trim().isEmpty)
                                    ? 'Vehicle number is required'
                                    : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _vehicleModelController,
                            decoration: _fieldDecoration(
                              hint: 'Vehicle model',
                              prefixIcon:
                                  const Icon(Icons.directions_bike_outlined),
                            ),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            value: _fuelType,
                            decoration: _fieldDecoration(
                              hint: 'Fuel type',
                              prefixIcon:
                                  const Icon(Icons.local_gas_station_outlined),
                            ),
                            items: const [
                              DropdownMenuItem(value: 'petrol', child: Text('Petrol')),
                              DropdownMenuItem(
                                  value: 'electric', child: Text('Electric')),
                              DropdownMenuItem(value: 'manual', child: Text('Manual')),
                            ],
                            onChanged: (value) {
                              if (value == null) return;
                              setState(() => _fuelType = value);
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _licenseNumberController,
                            decoration: _fieldDecoration(
                              hint: 'Driving licence number',
                              prefixIcon:
                                  const Icon(Icons.badge_outlined),
                            ),
                            validator: (value) =>
                                (value == null || value.trim().isEmpty)
                                    ? 'Licence number is required'
                                    : null,
                          ),
                          const SizedBox(height: 14),
                          _uploadTile(
                            title: 'Profile photo',
                            file: _profilePhoto,
                            onTap: () => _pickFile(
                              (file) => setState(() => _profilePhoto = file),
                            ),
                          ),
                          _uploadTile(
                            title: 'Vehicle image',
                            file: _vehicleImage,
                            onTap: () => _pickFile(
                              (file) => setState(() => _vehicleImage = file),
                            ),
                          ),
                          _uploadTile(
                            title: 'Aadhaar card',
                            file: _aadhaarFile,
                            onTap: () => _pickFile(
                              (file) => setState(() => _aadhaarFile = file),
                            ),
                          ),
                          _uploadTile(
                            title: 'PAN card',
                            file: _panFile,
                            onTap: () => _pickFile(
                              (file) => setState(() => _panFile = file),
                            ),
                          ),
                          _uploadTile(
                            title: 'Driving licence',
                            file: _licenseFile,
                            onTap: () => _pickFile(
                              (file) => setState(() => _licenseFile = file),
                            ),
                          ),
                          _uploadTile(
                            title: 'Vehicle RC',
                            file: _rcFile,
                            onTap: () => _pickFile(
                              (file) => setState(() => _rcFile = file),
                            ),
                          ),
                          _uploadTile(
                            title: 'Insurance',
                            file: _insuranceFile,
                            onTap: () => _pickFile(
                              (file) => setState(() => _insuranceFile = file),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    _sectionCard(
                      title: '5. Payout setup and permissions',
                      subtitle:
                          'Bank details are stored with the application and copied into the driver account when approved.',
                      child: Column(
                        children: [
                          TextFormField(
                            controller: _bankHolderController,
                            decoration: _fieldDecoration(
                              hint: 'Account holder name',
                              prefixIcon: const Icon(Icons.person_outline_rounded),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _bankNameController,
                            decoration: _fieldDecoration(
                              hint: 'Bank name',
                              prefixIcon:
                                  const Icon(Icons.account_balance_outlined),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _ifscController,
                            decoration: _fieldDecoration(
                              hint: 'IFSC code',
                              prefixIcon: const Icon(Icons.code_rounded),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _accountNumberController,
                            keyboardType: TextInputType.number,
                            decoration: _fieldDecoration(
                              hint: 'Account number',
                              prefixIcon:
                                  const Icon(Icons.credit_card_outlined),
                            ),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _upiController,
                            decoration: _fieldDecoration(
                              hint: 'UPI ID',
                              prefixIcon: const Icon(Icons.payments_outlined),
                            ),
                          ),
                          const SizedBox(height: 16),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            activeColor: _primary,
                            title: const Text(
                              'Background location enabled',
                              style: TextStyle(
                                color: _text,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: const Text(
                              'Required for live delivery assignment after approval.',
                              style: TextStyle(color: _subtext),
                            ),
                            value: _backgroundLocationEnabled,
                            onChanged: (value) {
                              setState(() => _backgroundLocationEnabled = value);
                            },
                          ),
                          SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            activeColor: _primary,
                            title: const Text(
                              'Notifications enabled',
                              style: TextStyle(
                                color: _text,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: const Text(
                              'Used for new order alerts and approval updates.',
                              style: TextStyle(color: _subtext),
                            ),
                            value: _notificationPermissionEnabled,
                            onChanged: (value) {
                              setState(
                                  () => _notificationPermissionEnabled = value);
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    CheckboxListTile(
                      value: _agreeTerms,
                      onChanged: (value) {
                        setState(() => _agreeTerms = value ?? false);
                      },
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      title: const Text(
                        'I agree to the Terms, Privacy Policy and driver verification process',
                        style: TextStyle(
                          color: _subtext,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    SizedBox(
                      width: double.infinity,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              _primary,
                              Color.lerp(_primary, _secondary, 0.24) ?? _primary,
                            ],
                          ),
                          borderRadius: BorderRadius.circular(18),
                          boxShadow: [
                            BoxShadow(
                              color: _primary.withOpacity(0.2),
                              blurRadius: 18,
                              offset: const Offset(0, 10),
                            ),
                          ],
                        ),
                        child: ElevatedButton(
                          onPressed: auth.isLoading ||
                                  _isLoadingBranding ||
                                  _isSubmitting ||
                                  _isLoadingAreas
                              ? null
                              : _submitApplication,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.transparent,
                            shadowColor: Colors.transparent,
                            minimumSize: const Size.fromHeight(58),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(18),
                            ),
                          ),
                          child: Text(
                            _isSubmitting
                                ? 'Submitting application...'
                                : _isPhoneVerified
                                    ? 'Submit for Admin Approval'
                                    : 'Verify Mobile & Submit',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w600,
                              color: Colors.black,
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    Center(
                      child: TextButton(
                        onPressed: () =>
                            Navigator.pushNamed(context, '/application-status'),
                        child: const Text('Track existing application'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _hero() {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Color.lerp(_primary, Colors.white, 0.22) ?? _primary,
            _primary,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
      ),
      child: const Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Apply as a delivery partner',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
                SizedBox(height: 10),
                Text(
                  'Real OTP verification, real document upload and direct admin approval flow.',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 12),
          Icon(
            Icons.delivery_dining_rounded,
            color: Colors.white,
            size: 72,
          ),
        ],
      ),
    );
  }

  Widget _sectionCard({
    required String title,
    required String subtitle,
    required Widget child,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _line),
        boxShadow: const [
          BoxShadow(
            color: Color(0x12000000),
            blurRadius: 18,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: _text,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(
              color: _subtext,
              fontSize: 14,
              fontWeight: FontWeight.w500,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }

  Widget _infoBanner({
    required IconData icon,
    required String text,
    required Color accent,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: accent.withOpacity(0.08),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(icon, color: accent),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                color: _text,
                fontWeight: FontWeight.w500,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _uploadTile({
    required String title,
    required File? file,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: _line),
          ),
          child: Row(
            children: [
              Icon(
                file == null ? Icons.upload_file_rounded : Icons.check_circle_rounded,
                color: file == null ? _subtext : _primary,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: _text,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Text(
                file == null ? 'Upload' : 'Ready',
                style: TextStyle(
                  color: file == null ? _subtext : _primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : _primary,
      ),
    );
  }

  void _autoAssignAreaFromCurrentLocation({bool showFeedback = false}) {
    final area = _resolveAreaFromCoordinates(_latitude, _longitude);
    if (!mounted) return;
    setState(() {
      _selectedAreaId = area?['id'] as int?;
    });
    if (showFeedback) {
      _showMessage(
        area == null
            ? 'No delivery zone matched your current location yet.'
            : 'Delivery zone auto-assigned: ${area['name']}',
        isError: area == null,
      );
    }
  }

  Map<String, dynamic>? _resolveAreaFromCoordinates(
    double? latitude,
    double? longitude,
  ) {
    if (latitude == null || longitude == null) {
      return null;
    }

    final containing = _deliveryAreas.where(
      (area) => _areaContainsPoint(area, latitude, longitude),
    ).toList()
      ..sort(
        (left, right) => _areaFootprint(left).compareTo(_areaFootprint(right)),
      );

    if (containing.isNotEmpty) {
      return containing.first;
    }

    final centered = _deliveryAreas
        .where((area) => area['latitude'] != null && area['longitude'] != null)
        .toList()
      ..sort(
        (left, right) => _distanceKm(
          latitude,
          longitude,
          (left['latitude'] as num).toDouble(),
          (left['longitude'] as num).toDouble(),
        ).compareTo(
          _distanceKm(
            latitude,
            longitude,
            (right['latitude'] as num).toDouble(),
            (right['longitude'] as num).toDouble(),
          ),
        ),
      );

    return centered.isEmpty ? null : centered.first;
  }

  bool _areaContainsPoint(
    Map<String, dynamic> area,
    double latitude,
    double longitude,
  ) {
    if ((area['area_type']?.toString() ?? 'circle') == 'polygon') {
      final polygon = area['polygon_coordinates'];
      if (polygon is! List || polygon.length < 3) {
        return false;
      }
      return _pointInPolygon(polygon, latitude, longitude);
    }

    final areaLatitude = area['latitude'];
    final areaLongitude = area['longitude'];
    final radius = (area['radius_km'] as num?)?.toDouble();
    if (areaLatitude == null || areaLongitude == null || radius == null) {
      return false;
    }

    return _distanceKm(
          latitude,
          longitude,
          (areaLatitude as num).toDouble(),
          (areaLongitude as num).toDouble(),
        ) <=
        radius;
  }

  bool _pointInPolygon(List<dynamic> polygon, double latitude, double longitude) {
    var intersections = 0;
    for (var i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
      final current = polygon[i] as Map;
      final previous = polygon[j] as Map;
      final currentLat = (current['lat'] as num?)?.toDouble();
      final currentLng = (current['lng'] as num?)?.toDouble();
      final previousLat = (previous['lat'] as num?)?.toDouble();
      final previousLng = (previous['lng'] as num?)?.toDouble();
      if (currentLat == null ||
          currentLng == null ||
          previousLat == null ||
          previousLng == null) {
        continue;
      }
      final intersects =
          (currentLat > latitude) != (previousLat > latitude) &&
              longitude <
                  (previousLng - currentLng) *
                          (latitude - currentLat) /
                          (previousLat - currentLat) +
                      currentLng;
      if (intersects) {
        intersections++;
      }
    }
    return intersections.isOdd;
  }

  double _areaFootprint(Map<String, dynamic> area) {
    if ((area['area_type']?.toString() ?? 'circle') == 'polygon') {
      final polygon = area['polygon_coordinates'];
      if (polygon is! List || polygon.length < 3) {
        return double.infinity;
      }
      var total = 0.0;
      for (var i = 0; i < polygon.length; i++) {
        final current = polygon[i] as Map;
        final next = polygon[(i + 1) % polygon.length] as Map;
        final currentLat = (current['lat'] as num?)?.toDouble() ?? 0;
        final currentLng = (current['lng'] as num?)?.toDouble() ?? 0;
        final nextLat = (next['lat'] as num?)?.toDouble() ?? 0;
        final nextLng = (next['lng'] as num?)?.toDouble() ?? 0;
        total += currentLat * nextLng;
        total -= nextLat * currentLng;
      }
      final polygonArea = total.abs() / 2;
      return polygonArea > 0 ? polygonArea : double.infinity;
    }

    final radius = (area['radius_km'] as num?)?.toDouble();
    return radius == null || radius <= 0 ? double.infinity : radius;
  }

  double _distanceKm(double lat1, double lon1, double lat2, double lon2) {
    const earthRadius = 6371.0;
    final dLat = _degreesToRadians(lat2 - lat1);
    final dLon = _degreesToRadians(lon2 - lon1);
    final a =
        (sin(dLat / 2) * sin(dLat / 2)) +
            cos(_degreesToRadians(lat1)) *
                cos(_degreesToRadians(lat2)) *
                (sin(dLon / 2) * sin(dLon / 2));
    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadius * c;
  }

  double _degreesToRadians(double degrees) => degrees * 3.1415926535897932 / 180;

  String _selectedAreaName() {
    for (final area in _deliveryAreas) {
      if (area['id'] == _selectedAreaId) {
        return area['name']?.toString() ?? '-';
      }
    }
    return _isLoadingAreas
        ? 'Loading delivery zones...'
        : 'Use current location to detect zone';
  }
}
