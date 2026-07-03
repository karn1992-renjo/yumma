import 'dart:io';
import 'dart:math';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../services/location_service.dart';
import '../../services/partner_application_service.dart';
import '../../theme/foodflow_theme.dart';
import 'otp_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, this.initialPhone});

  final String? initialPhone;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const _red = Color(0xFFEF4F5F);
  static const _orange = Color(0xFFFF7A00);
  static const _green = Color(0xFF22C55E);
  static const _ink = Color(0xFF111827);
  static const _muted = Color(0xFF6B7280);
  static const _canvas = Color(0xFFF8F9FB);
  static const _line = Color(0xFFE5E7EB);

  final _pageController = PageController();
  final _formKey = GlobalKey<FormState>();
  final _applicationService = PartnerApplicationService.instance;
  final _locationService = LocationService();
  final _firebasePhoneAuthService = FirebasePhoneAuthService();

  final _ownerNameController = TextEditingController();
  final _businessNameController = TextEditingController();
  final _businessEmailController = TextEditingController();
  final _businessPhoneController = TextEditingController();
  final _contactNameController = TextEditingController();
  final _contactDesignationController = TextEditingController();
  final _contactEmailController = TextEditingController();
  final _contactPhoneController = TextEditingController();
  final _cityController = TextEditingController();
  final _addressController = TextEditingController();
  final _landmarkController = TextEditingController();
  final _pincodeController = TextEditingController();
  final _locationSearchController = TextEditingController();
  final _bankHolderController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _ifscController = TextEditingController();
  final _accountNumberController = TextEditingController();
  final _upiController = TextEditingController();
  final _minimumOrderController = TextEditingController(text: '199');
  final _freeDeliveryController = TextEditingController(text: '399');
  final _deliveryChargeController = TextEditingController(text: '40');
  final _packagingChargeController = TextEditingController(text: '12');
  final _gstController = TextEditingController(text: '5');
  final _handlingFeeController = TextEditingController(text: '8');
  final _menuSummaryController = TextEditingController(
    text: 'Starters, Biryani, Main Course, Desserts',
  );

  final List<String> _allCategories = const [
    'Fast Food',
    'Biryani',
    'Pizza',
    'Chinese',
    'South Indian',
    'Bakery',
    'Cafe',
    'Pure Veg',
  ];
  final List<String> _weeklyDays = const [
    'Mon',
    'Tue',
    'Wed',
    'Thu',
    'Fri',
    'Sat',
    'Sun',
  ];

  AppBranding _branding = AppBranding.fallback();
  List<Map<String, dynamic>> _deliveryAreas = const [];
  Set<String> _selectedCategories = {'Fast Food', 'Cafe'};
  Set<String> _weeklyOff = {'Mon'};
  int _pageIndex = 0;
  int? _selectedAreaId;
  String _openingTime = '09:00 AM';
  String _closingTime = '11:00 PM';
  String _secondaryOpeningTime = '01:00 PM';
  String _secondaryClosingTime = '03:00 PM';
  String _commissionPreview = 'Commission configured by admin';
  String _payoutCycle = 'Daily settlement';
  bool _isPureVeg = false;
  bool _runs24x7 = false;
  bool _secondShiftEnabled = true;
  bool _backgroundLocationEnabled = true;
  bool _notificationPermissionEnabled = true;
  bool _aiVerificationEnabled = true;
  bool _agreeTerms = true;
  bool _isLoadingBootstrap = true;
  bool _isLoadingAreas = true;
  bool _isLocating = false;
  bool _isSubmitting = false;
  bool _isSendingOtp = false;
  String? _verifiedPhoneToken;
  String? _verifiedPhoneNumber;
  double? _latitude;
  double? _longitude;

  File? _logoFile;
  File? _bannerFile;
  File? _interiorFile;
  File? _foodFile;
  File? _kitchenFile;
  File? _gstFile;
  File? _fssaiFile;
  File? _panFile;
  File? _bankProofFile;
  File? _shopLicenseFile;

  final List<_OnboardingStep> _steps = const [
    _OnboardingStep(
      title: 'Welcome',
      subtitle: 'Premium merchant intro',
      icon: Icons.rocket_launch_rounded,
    ),
    _OnboardingStep(
      title: 'Mobile',
      subtitle: 'Verify owner mobile',
      icon: Icons.phone_android_rounded,
    ),
    _OnboardingStep(
      title: 'Basics',
      subtitle: 'Restaurant identity',
      icon: Icons.storefront_rounded,
    ),
    _OnboardingStep(
      title: 'Location',
      subtitle: 'Map and address',
      icon: Icons.location_on_rounded,
    ),
    _OnboardingStep(
      title: 'Category',
      subtitle: 'Cuisine and timing',
      icon: Icons.restaurant_menu_rounded,
    ),
    _OnboardingStep(
      title: 'Media',
      subtitle: 'Photos and docs',
      icon: Icons.photo_camera_back_rounded,
    ),
    _OnboardingStep(
      title: 'Banking',
      subtitle: 'Payout setup',
      icon: Icons.account_balance_wallet_rounded,
    ),
    _OnboardingStep(
      title: 'Zone',
      subtitle: 'Delivery coverage',
      icon: Icons.map_rounded,
    ),
    _OnboardingStep(
      title: 'Menu',
      subtitle: 'Menu and charges',
      icon: Icons.menu_book_rounded,
    ),
    _OnboardingStep(
      title: 'Review',
      subtitle: 'Submit for approval',
      icon: Icons.verified_rounded,
    ),
  ];

  bool get _isPhoneVerified =>
      _verifiedPhoneToken != null && _verifiedPhoneNumber != null;

  @override
  void initState() {
    super.initState();
    _businessPhoneController.text =
        _stripCountryCode(widget.initialPhone?.trim() ?? '');
    _contactPhoneController.text =
        _stripCountryCode(widget.initialPhone?.trim() ?? '');
    _bootstrap();
  }

  @override
  void dispose() {
    _pageController.dispose();
    for (final controller in [
      _ownerNameController,
      _businessNameController,
      _businessEmailController,
      _businessPhoneController,
      _contactNameController,
      _contactDesignationController,
      _contactEmailController,
      _contactPhoneController,
      _cityController,
      _addressController,
      _landmarkController,
      _pincodeController,
      _locationSearchController,
      _bankHolderController,
      _bankNameController,
      _ifscController,
      _accountNumberController,
      _upiController,
      _minimumOrderController,
      _freeDeliveryController,
      _deliveryChargeController,
      _packagingChargeController,
      _gstController,
      _handlingFeeController,
      _menuSummaryController,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _bootstrap() async {
    await Future.wait([
      _loadBranding(),
      _loadDeliveryAreas(),
    ]);
    if (!mounted) return;
    setState(() => _isLoadingBootstrap = false);
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
      if ((widget.initialPhone ?? '').isNotEmpty) {
        final stripped = _stripCountryCode(widget.initialPhone!.trim());
        _businessPhoneController.text = stripped;
        _contactPhoneController.text = stripped;
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
      _showMessage('Could not load delivery zones right now.', isError: true);
    } finally {
      if (mounted) {
        setState(() => _isLoadingAreas = false);
      }
    }
  }

  String _normalizedPhone(String raw) {
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
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'pdf'],
    );
    final path = result?.files.single.path;
    if (path == null || !mounted) return;
    onPicked(File(path));
  }

  Future<void> _useCurrentLocation() async {
    setState(() => _isLocating = true);
    try {
      final position = await _locationService.getCurrentLocation();
      if (position == null) {
        _showMessage('Location unavailable. Please enter manually.', isError: true);
        return;
      }
      _latitude = position.latitude;
      _longitude = position.longitude;
      final address = await _locationService.getAddressFromLatLng(
        position.latitude,
        position.longitude,
      );
      if (!mounted) return;
      if (address != null) {
        setState(() {
          _cityController.text = address['city'] ?? _cityController.text;
          _addressController.text =
              address['address'] ?? _addressController.text;
          _locationSearchController.text =
              address['address'] ?? _locationSearchController.text;
          _pincodeController.text =
              address['pincode'] ?? _pincodeController.text;
        });
      }
      _autoAssignAreaFromCurrentLocation(showFeedback: true);
    } finally {
      if (mounted) setState(() => _isLocating = false);
    }
  }

  Future<void> _searchLocationManually() async {
    final query = _locationSearchController.text.trim().isNotEmpty
        ? _locationSearchController.text.trim()
        : _addressController.text.trim();
    if (query.isEmpty) {
      _showMessage('Enter a store area, landmark, or pincode first.', isError: true);
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

      final address = await _locationService.getAddressFromLatLng(
        _latitude!,
        _longitude!,
      );
      if (!mounted) return;

      setState(() {
        if ((address?['city'] ?? '').isNotEmpty) {
          _cityController.text = address!['city']!;
        } else if ((location['city']?.toString() ?? '').isNotEmpty) {
          _cityController.text = location['city'].toString();
        }
        if ((address?['address'] ?? '').isNotEmpty) {
          _addressController.text = address!['address']!;
          _locationSearchController.text = address['address']!;
        }
        if ((address?['pincode'] ?? '').isNotEmpty) {
          _pincodeController.text = address!['pincode']!;
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
    final rawPhone = _contactPhoneController.text.trim().isNotEmpty
        ? _contactPhoneController.text.trim()
        : _businessPhoneController.text.trim();
    if (rawPhone.isEmpty) {
      _showMessage('Enter a mobile number first.', isError: true);
      return;
    }

    setState(() => _isSendingOtp = true);
    try {
      final authProvider = context.read<AuthProvider>();
      final phone = _normalizedPhone(rawPhone);
      final status = await authProvider.getPhoneStatus(
        phone: phone,
        role: 'restaurant',
      );

      if (!mounted) return;

      if (status == null) {
        _showMessage(
          authProvider.error ?? 'Unable to validate this mobile number.',
          isError: true,
        );
        return;
      }

      if (status['exists'] == true) {
        _showMessage(
          'A restaurant account already exists with this mobile number. Please sign in.',
          isError: true,
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
          role: 'restaurant',
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
            role: 'restaurant',
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
        final stripped = _stripCountryCode(_verifiedPhoneNumber!);
        _contactPhoneController.text = stripped;
        if (_businessPhoneController.text.trim().isEmpty) {
          _businessPhoneController.text = stripped;
        }
      });

      _showMessage('Mobile verified successfully.');
    } finally {
      if (mounted) {
        setState(() => _isSendingOtp = false);
      }
    }
  }

  Future<void> _pickTime({
    required String currentValue,
    required ValueChanged<String> onSelected,
  }) async {
    final parsed = TimeOfDay(
      hour: int.tryParse(currentValue.split(':').first) ?? 9,
      minute: 0,
    );
    final picked = await showTimePicker(context: context, initialTime: parsed);
    if (picked == null) return;
    onSelected(picked.format(context));
  }

  bool _validateCurrentPage() {
    switch (_pageIndex) {
      case 0:
        return true;
      case 1:
        if (!_isPhoneVerified) {
          _showMessage('Verify your mobile number to continue.', isError: true);
          return false;
        }
        return true;
      case 2:
        if (_businessNameController.text.trim().isEmpty ||
            _businessEmailController.text.trim().isEmpty ||
            _contactNameController.text.trim().isEmpty ||
            _contactEmailController.text.trim().isEmpty) {
          _showMessage('Complete the restaurant basics first.', isError: true);
          return false;
        }
        return true;
      case 3:
        if (_cityController.text.trim().isEmpty ||
            _addressController.text.trim().isEmpty) {
          _showMessage('Add your restaurant location.', isError: true);
          return false;
        }
        return true;
      case 4:
        if (_selectedCategories.isEmpty) {
          _showMessage('Choose at least one category.', isError: true);
          return false;
        }
        return true;
      case 5:
        if (_fssaiFile == null && _gstFile == null) {
          _showMessage('Upload at least one compliance document.', isError: true);
          return false;
        }
        return true;
      case 6:
        if (_bankHolderController.text.trim().isEmpty ||
            _accountNumberController.text.trim().isEmpty ||
            _ifscController.text.trim().isEmpty) {
          _showMessage('Complete bank setup first.', isError: true);
          return false;
        }
        return true;
      case 7:
        if (_latitude == null || _longitude == null) {
          _showMessage(
            'Use current location so we can auto-assign the right delivery zone.',
            isError: true,
          );
          return false;
        }
        if (_selectedAreaId == null) {
          _autoAssignAreaFromCurrentLocation();
        }
        if (_selectedAreaId == null) {
          _showMessage('No delivery zone matched your current location.', isError: true);
          return false;
        }
        return true;
      case 8:
        if (_menuSummaryController.text.trim().isEmpty) {
          _showMessage('Add a menu summary to continue.', isError: true);
          return false;
        }
        return true;
      case 9:
        if (!_agreeTerms) {
          _showMessage('Accept the terms to submit.', isError: true);
          return false;
        }
        return true;
      default:
        return true;
    }
  }

  Future<void> _goNext() async {
    if (!_validateCurrentPage()) return;
    if (_pageIndex == _steps.length - 1) {
      await _submit();
      return;
    }
    final next = _pageIndex + 1;
    setState(() => _pageIndex = next);
    await _pageController.animateToPage(
      next,
      duration: const Duration(milliseconds: 260),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _goBack() async {
    if (_pageIndex == 0) {
      Navigator.pop(context);
      return;
    }
    final previous = _pageIndex - 1;
    setState(() => _pageIndex = previous);
    await _pageController.animateToPage(
      previous,
      duration: const Duration(milliseconds: 240),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (!_isPhoneVerified) {
      _showMessage('Verify the mobile number before submitting.', isError: true);
      return;
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
      _showMessage('No delivery zone matched your current location.', isError: true);
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      final response = await _applicationService.submitApplication(
        fields: {
          'partner_type': 'restaurant',
          'business_name': _businessNameController.text.trim(),
          'business_email': _businessEmailController.text.trim(),
          'business_phone': _normalizedPhone(_businessPhoneController.text.trim()),
          'city': _cityController.text.trim(),
          'address': _addressController.text.trim(),
          'pincode': _pincodeController.text.trim(),
          'cuisine': _selectedCategories.join(', '),
          'is_pure_veg': _isPureVeg ? '1' : '0',
          'contact_name': _contactNameController.text.trim(),
          'contact_designation': _contactDesignationController.text.trim(),
          'contact_email': _contactEmailController.text.trim(),
          'contact_phone': _normalizedPhone(_contactPhoneController.text.trim()),
          'bank_holder_name': _bankHolderController.text.trim(),
          'bank_name': _bankNameController.text.trim(),
          'bank_account_number': _accountNumberController.text.trim(),
          'bank_ifsc': _ifscController.text.trim(),
          'upi_id': _upiController.text.trim(),
          'owner_name': _ownerNameController.text.trim(),
          'restaurant_phone': _normalizedPhone(_businessPhoneController.text.trim()),
          'landmark': _landmarkController.text.trim(),
          'area_id': (_selectedAreaId ?? '').toString(),
          'latitude': _latitude?.toString() ?? '',
          'longitude': _longitude?.toString() ?? '',
          'weekly_off': _weeklyOff.join(','),
          'opening_time': _openingTime,
          'closing_time': _closingTime,
          'secondary_opening_time': _secondShiftEnabled ? _secondaryOpeningTime : '',
          'secondary_closing_time': _secondShiftEnabled ? _secondaryClosingTime : '',
          'restaurant_categories': _selectedCategories.join('|'),
          'photo_status': _photoChecklist(),
          'document_status': _documentChecklist(),
          'minimum_order_value': _minimumOrderController.text.trim(),
          'free_delivery_threshold': _freeDeliveryController.text.trim(),
          'delivery_charges': _deliveryChargeController.text.trim(),
          'packaging_charge': _packagingChargeController.text.trim(),
          'gst_percentage': _gstController.text.trim(),
          'handling_fee': _handlingFeeController.text.trim(),
          'commission_preview': _commissionPreview,
          'payout_cycle': _payoutCycle,
          'menu_summary': _menuSummaryController.text.trim(),
          'ai_verification_enabled': _aiVerificationEnabled ? '1' : '0',
          'background_location_enabled': _backgroundLocationEnabled ? '1' : '0',
          'notification_permission_enabled': _notificationPermissionEnabled ? '1' : '0',
          'terms': '1',
        },
        files: {
          if (_gstFile != null) 'gst_certificate': _gstFile!.path,
          if (_fssaiFile != null) 'fssai_license': _fssaiFile!.path,
        },
      );

      if (!mounted) return;
      Navigator.pushReplacementNamed(
        context,
        '/application-status',
        arguments: response['data']?['application_number']?.toString(),
      );
    } catch (error) {
      _showMessage('Could not submit application: $error', isError: true);
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  String _photoChecklist() {
    return [
      if (_logoFile != null) 'logo',
      if (_bannerFile != null) 'banner',
      if (_interiorFile != null) 'interior',
      if (_foodFile != null) 'food',
      if (_kitchenFile != null) 'kitchen',
    ].join(',');
  }

  String _documentChecklist() {
    return [
      if (_fssaiFile != null) 'fssai',
      if (_gstFile != null) 'gst',
      if (_panFile != null) 'pan',
      if (_bankProofFile != null) 'bank_proof',
      if (_shopLicenseFile != null) 'shop_license',
    ].join(',');
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoadingBootstrap) {
      return const Scaffold(
        backgroundColor: _canvas,
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      backgroundColor: _canvas,
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 18, 20, 12),
                child: _buildTopBar(),
              ),
              Expanded(
                child: PageView(
                  controller: _pageController,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    _buildWelcomeStep(),
                    _buildMobileStep(),
                    _buildBasicInfoStep(),
                    _buildLocationStep(),
                    _buildCategoryTimingStep(),
                    _buildMediaDocsStep(),
                    _buildBankStep(),
                    _buildDeliveryZoneStep(),
                    _buildMenuStep(),
                    _buildReviewStep(),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 18),
                child: _buildBottomActions(),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTopBar() {
    final step = _steps[_pageIndex];
    return Column(
      children: [
        Row(
          children: [
            IconButton(
              onPressed: _goBack,
              icon: const Icon(Icons.arrow_back_ios_new_rounded),
              style: IconButton.styleFrom(backgroundColor: Colors.white),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Restaurant Partner Onboarding',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      color: _ink,
                    ),
                  ),
                  Text(
                    '${_pageIndex + 1}/${_steps.length} · ${step.subtitle}',
                    style: const TextStyle(
                      color: _muted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            TextButton(
              onPressed: () => Navigator.pushReplacementNamed(context, '/login'),
              child: const Text('Sign In'),
            ),
          ],
        ),
        const SizedBox(height: 14),
        SizedBox(
          height: 60,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _steps.length,
            separatorBuilder: (_, __) => const SizedBox(width: 10),
            itemBuilder: (context, index) {
              final item = _steps[index];
              final active = index == _pageIndex;
              final complete = index < _pageIndex;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: active || complete ? Colors.white : const Color(0xFFF1F3F6),
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: active
                        ? _red
                        : complete
                            ? _green
                            : _line,
                  ),
                  boxShadow: active
                      ? const [
                          BoxShadow(
                            color: Color(0x12EF4F5F),
                            blurRadius: 18,
                            offset: Offset(0, 10),
                          ),
                        ]
                      : const [],
                ),
                child: Row(
                  children: [
                    Icon(
                      complete ? Icons.check_circle : item.icon,
                      color: complete
                          ? _green
                          : active
                              ? _red
                              : _muted,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      item.title,
                      style: TextStyle(
                        color: active ? _ink : _muted,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Widget _buildBottomActions() {
    final last = _pageIndex == _steps.length - 1;
    return Row(
      children: [
        if (_pageIndex > 0)
          Expanded(
            child: OutlinedButton(
              onPressed: _goBack,
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(56),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
              child: const Text('Back'),
            ),
          ),
        if (_pageIndex > 0) const SizedBox(width: 12),
        Expanded(
          flex: 2,
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [_red, _orange],
              ),
              borderRadius: BorderRadius.circular(20),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x26EF4F5F),
                  blurRadius: 18,
                  offset: Offset(0, 10),
                ),
              ],
            ),
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _goNext,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.transparent,
                shadowColor: Colors.transparent,
                minimumSize: const Size.fromHeight(58),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
              child: Text(
                _isSubmitting
                    ? 'Submitting...'
                    : last
                        ? 'Submit Application'
                        : _pageIndex == 0
                            ? 'Get Started'
                            : 'Continue',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                  color: Colors.white,
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildWelcomeStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [_red, _orange],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(30),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: const [
                  _GlassBadge(label: 'Fast onboarding'),
                  _GlassBadge(label: 'AI verification'),
                  _GlassBadge(label: 'Live approval tracking'),
                ],
              ),
              const SizedBox(height: 22),
              const Text(
                'Partner With Us',
                style: TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Grow your restaurant business with online food delivery, AI-assisted onboarding, and a premium merchant dashboard.',
                style: TextStyle(
                  fontSize: 16,
                  color: Colors.white,
                  height: 1.5,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 26),
              Row(
                children: const [
                  Expanded(
                    child: _MetricCard(label: 'Avg approvals', value: '24 hrs'),
                  ),
                  SizedBox(width: 12),
                  Expanded(
                    child: _MetricCard(label: 'Launch checklist', value: '20 steps'),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: const [
              Text(
                'What we set up for you',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: _ink,
                ),
              ),
              SizedBox(height: 14),
              _FeatureRow(
                icon: Icons.verified_user_rounded,
                title: 'AI document verification',
                subtitle: 'FSSAI, GST, PAN and bank proof checks',
              ),
              _FeatureRow(
                icon: Icons.location_searching_rounded,
                title: 'Delivery zone mapping',
                subtitle: 'Radius, fees, thresholds and ETA preview',
              ),
              _FeatureRow(
                icon: Icons.auto_awesome_rounded,
                title: 'Menu launch assistance',
                subtitle: 'Categories, menu summary and launch readiness',
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMobileStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Mobile Number Verification',
          subtitle:
              'Verify the owner mobile first. This number will be used for future OTP-based merchant sign in.',
          icon: Icons.phone_iphone_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _labeledTextField(
                controller: _contactPhoneController,
                label: 'Owner mobile number',
                hint: '${_branding.defaultMobileCountryCode}9876543210',
                icon: Icons.call_outlined,
                keyboardType: TextInputType.phone,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _businessPhoneController,
                label: 'Restaurant contact number',
                hint: 'Separate helpline for customers',
                icon: Icons.store_mall_directory_outlined,
                keyboardType: TextInputType.phone,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 18),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: _isPhoneVerified ? const Color(0xFFF1FBF4) : const Color(0xFFFFF7ED),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: _isPhoneVerified ? const Color(0xFFD8F1DE) : const Color(0xFFFED7AA),
                  ),
                ),
                child: Row(
                  children: [
                    Icon(
                      _isPhoneVerified
                          ? Icons.check_circle_rounded
                          : Icons.sms_outlined,
                      color: _isPhoneVerified ? _green : _orange,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        _isPhoneVerified
                            ? 'Verified for ${_verifiedPhoneNumber ?? ''}'
                            : 'OTP verification secures the restaurant onboarding flow.',
                        style: const TextStyle(
                          color: _ink,
                          fontWeight: FontWeight.w700,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _isSendingOtp || _isPhoneVerified
                      ? null
                      : _verifyMobile,
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(56),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(18),
                    ),
                  ),
                  child: Text(
                    _isPhoneVerified
                        ? 'Verified'
                        : _isSendingOtp
                            ? 'Sending OTP...'
                            : 'Send OTP',
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildBasicInfoStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Restaurant Basic Information',
          subtitle:
              'Set up your merchant identity, owner contact, and the details that appear in admin review.',
          icon: Icons.storefront_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            children: [
              _labeledTextField(
                controller: _businessNameController,
                label: 'Restaurant name',
                hint: 'The Bombay Tandoor',
                icon: Icons.restaurant_rounded,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _ownerNameController,
                label: 'Owner name',
                hint: 'Primary business owner',
                icon: Icons.person_outline_rounded,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _businessEmailController,
                label: 'Restaurant email',
                hint: 'merchant@example.com',
                icon: Icons.email_outlined,
                keyboardType: TextInputType.emailAddress,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _contactNameController,
                label: 'Operations contact',
                hint: 'Onboarding SPOC',
                icon: Icons.badge_outlined,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _contactDesignationController,
                label: 'Designation',
                hint: 'Owner / Manager / Admin',
                icon: Icons.work_outline_rounded,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _contactEmailController,
                label: 'Contact email',
                hint: 'ops@example.com',
                icon: Icons.alternate_email_rounded,
                keyboardType: TextInputType.emailAddress,
                validator: _requiredValidator,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildLocationStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Restaurant Location Setup',
          subtitle:
              'Pin your restaurant, verify delivery availability, and build the address used for activation.',
          icon: Icons.pin_drop_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            children: [
              Container(
                height: 220,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  gradient: const LinearGradient(
                    colors: [Color(0xFFFFF3F0), Color(0xFFFFF7ED)],
                  ),
                  border: Border.all(color: _line),
                ),
                child: Stack(
                  children: [
                    Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.location_searching_rounded,
                            size: 48,
                            color: _red,
                          ),
                          const SizedBox(height: 12),
                          Text(
                            _selectedAreaId == null
                                ? 'Merchant map preview'
                                : 'Zone linked to application',
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              color: _ink,
                              fontSize: 18,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _selectedAreaId == null
                                ? 'Use GPS or enter your store address manually'
                                : 'Coordinates and zone are ready for admin review',
                            style: const TextStyle(
                              color: _muted,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Positioned(
                      right: 16,
                      top: 16,
                      child: FilledButton.tonal(
                        onPressed: _isLocating ? null : _useCurrentLocation,
                        child: Text(_isLocating ? 'Locating...' : 'Use GPS'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: _labeledTextField(
                      controller: _locationSearchController,
                      label: 'Find location',
                      hint: 'Search area, landmark, or pincode',
                      icon: Icons.search_rounded,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Padding(
                    padding: const EdgeInsets.only(top: 28),
                    child: FilledButton.tonal(
                      onPressed: _isLocating ? null : _searchLocationManually,
                      style: FilledButton.styleFrom(
                        minimumSize: const Size(96, 56),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(18),
                        ),
                      ),
                      child: const Text('Search'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _addressController,
                label: 'Street address',
                hint: 'Shop number, street, area',
                icon: Icons.home_work_outlined,
                maxLines: 3,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: _labeledTextField(
                      controller: _cityController,
                      label: 'City',
                      hint: 'Mumbai',
                      icon: Icons.location_city_outlined,
                      validator: _requiredValidator,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _labeledTextField(
                      controller: _pincodeController,
                      label: 'Pincode',
                      hint: '400001',
                      icon: Icons.pin_outlined,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _landmarkController,
                label: 'Landmark',
                hint: 'Opposite metro station',
                icon: Icons.place_outlined,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildCategoryTimingStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Categories & Timings',
          subtitle:
              'Select cuisine identity, configure operating hours, and define store availability.',
          icon: Icons.schedule_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Restaurant categories',
                style: TextStyle(
                  fontWeight: FontWeight.w900,
                  color: _ink,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: _allCategories.map((category) {
                  final selected = _selectedCategories.contains(category);
                  return ChoiceChip(
                    label: Text(category),
                    selected: selected,
                    onSelected: (_) {
                      setState(() {
                        if (selected) {
                          _selectedCategories.remove(category);
                        } else {
                          _selectedCategories.add(category);
                        }
                      });
                    },
                  );
                }).toList(),
              ),
              const SizedBox(height: 18),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _isPureVeg,
                title: const Text('Pure veg restaurant'),
                onChanged: (value) => setState(() => _isPureVeg = value),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _runs24x7,
                title: const Text('24x7 operations'),
                onChanged: (value) => setState(() => _runs24x7 = value),
              ),
              const SizedBox(height: 8),
              if (!_runs24x7) ...[
                Row(
                  children: [
                    Expanded(
                      child: _tapField(
                        label: 'Opening time',
                        value: _openingTime,
                        onTap: () => _pickTime(
                          currentValue: _openingTime,
                          onSelected: (value) =>
                              setState(() => _openingTime = value),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _tapField(
                        label: 'Closing time',
                        value: _closingTime,
                        onTap: () => _pickTime(
                          currentValue: _closingTime,
                          onSelected: (value) =>
                              setState(() => _closingTime = value),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _secondShiftEnabled,
                  title: const Text('Enable second shift'),
                  onChanged: (value) =>
                      setState(() => _secondShiftEnabled = value),
                ),
                if (_secondShiftEnabled) ...[
                  Row(
                    children: [
                      Expanded(
                        child: _tapField(
                          label: 'Second shift start',
                          value: _secondaryOpeningTime,
                          onTap: () => _pickTime(
                            currentValue: _secondaryOpeningTime,
                            onSelected: (value) =>
                                setState(() => _secondaryOpeningTime = value),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _tapField(
                          label: 'Second shift end',
                          value: _secondaryClosingTime,
                          onTap: () => _pickTime(
                            currentValue: _secondaryClosingTime,
                            onSelected: (value) =>
                                setState(() => _secondaryClosingTime = value),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
              const SizedBox(height: 16),
              const Text(
                'Weekly off',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: _ink,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 10,
                children: _weeklyDays.map((day) {
                  final selected = _weeklyOff.contains(day);
                  return FilterChip(
                    label: Text(day),
                    selected: selected,
                    onSelected: (_) {
                      setState(() {
                        if (selected) {
                          _weeklyOff.remove(day);
                        } else {
                          _weeklyOff.add(day);
                        }
                      });
                    },
                  );
                }).toList(),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMediaDocsStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Photos & Document Upload',
          subtitle:
              'Prepare launch photography and merchant compliance documents for AI-assisted review.',
          icon: Icons.auto_awesome_mosaic_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Restaurant photos',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: _ink,
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _uploadCard('Logo', _logoFile, () => _pickFile((f) => setState(() => _logoFile = f))),
                  _uploadCard('Banner', _bannerFile, () => _pickFile((f) => setState(() => _bannerFile = f))),
                  _uploadCard('Interior', _interiorFile, () => _pickFile((f) => setState(() => _interiorFile = f))),
                  _uploadCard('Food', _foodFile, () => _pickFile((f) => setState(() => _foodFile = f))),
                  _uploadCard('Kitchen', _kitchenFile, () => _pickFile((f) => setState(() => _kitchenFile = f))),
                ],
              ),
              const SizedBox(height: 18),
              const Text(
                'Verification documents',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: _ink,
                ),
              ),
              const SizedBox(height: 14),
              _documentTile('FSSAI License', _fssaiFile, () => _pickFile((f) => setState(() => _fssaiFile = f))),
              _documentTile('GST Certificate', _gstFile, () => _pickFile((f) => setState(() => _gstFile = f))),
              _documentTile('PAN Card', _panFile, () => _pickFile((f) => setState(() => _panFile = f))),
              _documentTile('Bank Proof', _bankProofFile, () => _pickFile((f) => setState(() => _bankProofFile = f))),
              _documentTile('Shop License', _shopLicenseFile, () => _pickFile((f) => setState(() => _shopLicenseFile = f))),
              const SizedBox(height: 12),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _aiVerificationEnabled,
                title: const Text('Enable AI verification checks'),
                subtitle: const Text('License validation, blur detection, duplicate checks'),
                onChanged: (value) =>
                    setState(() => _aiVerificationEnabled = value),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildBankStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Bank & Payout Setup',
          subtitle:
              'Set up settlement details for payouts, penny-drop readiness, and finance review.',
          icon: Icons.account_balance_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            children: [
              _labeledTextField(
                controller: _bankHolderController,
                label: 'Account holder name',
                hint: 'Legal beneficiary name',
                icon: Icons.person_pin_circle_outlined,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _bankNameController,
                label: 'Bank name',
                hint: 'ICICI / HDFC / SBI',
                icon: Icons.account_balance_outlined,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _ifscController,
                label: 'IFSC code',
                hint: 'SBIN0001234',
                icon: Icons.qr_code_rounded,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _accountNumberController,
                label: 'Account number',
                hint: 'Primary settlement account',
                icon: Icons.numbers_rounded,
                keyboardType: TextInputType.number,
                validator: _requiredValidator,
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _upiController,
                label: 'UPI ID',
                hint: 'merchant@upi',
                icon: Icons.account_balance_wallet_outlined,
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1FBF4),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFD8F1DE)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.check_circle_outline_rounded, color: _green),
                    SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Payout verification preview: penny-drop and IFSC validation will be shown to the admin review team.',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: _ink,
                          height: 1.45,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildDeliveryZoneStep() {
    final selectedArea = _deliveryAreas.cast<Map<String, dynamic>?>().firstWhere(
          (area) => area?['id'] == _selectedAreaId,
          orElse: () => null,
        );
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Delivery Zone Setup',
          subtitle:
              'Your delivery zone is auto-detected from the restaurant location and used for launch coverage and delivery economics.',
          icon: Icons.map_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            children: [
              Container(
                height: 280,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  gradient: const LinearGradient(
                    colors: [Color(0xFFFFF6F3), Color(0xFFFFF8EE)],
                  ),
                  border: Border.all(color: _line),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.near_me_rounded, color: _red),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              selectedArea?['name']?.toString() ?? 'Use current location to detect zone',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                color: _ink,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Text(
                        selectedArea?['description']?.toString() ??
                            'Map-based radius and polygon coverage will be reviewed by the admin team.',
                        style: const TextStyle(
                          color: _muted,
                          fontWeight: FontWeight.w600,
                          height: 1.45,
                        ),
                      ),
                      const Spacer(),
                      Row(
                        children: [
                          Expanded(
                            child: _miniMetric(
                              'Radius',
                              '${selectedArea?['radius_km'] ?? '-'} km',
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _miniMetric(
                              'ETA',
                              '25-35 mins',
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _miniMetric(
                              'Demand',
                              'High',
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: _line),
                ),
                child: Row(
                  children: [
                    Icon(
                      Icons.my_location_rounded,
                      color: _selectedAreaId == null ? _muted : _red,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Auto-assigned delivery zone',
                            style: TextStyle(
                              color: _muted,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _selectedAreaName(),
                            style: const TextStyle(
                              color: _ink,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: _labeledTextField(
                      controller: _minimumOrderController,
                      label: 'Minimum order',
                      hint: '199',
                      icon: Icons.currency_rupee_rounded,
                      keyboardType: TextInputType.number,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _labeledTextField(
                      controller: _freeDeliveryController,
                      label: 'Free delivery at',
                      hint: '399',
                      icon: Icons.local_shipping_outlined,
                      keyboardType: TextInputType.number,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _deliveryChargeController,
                label: 'Delivery charge',
                hint: '40',
                icon: Icons.delivery_dining_rounded,
                keyboardType: TextInputType.number,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMenuStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Menu, Packaging & Commission',
          subtitle:
              'Summarize your menu structure, configure fees, and preview settlement economics before launch.',
          icon: Icons.dinner_dining_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            children: [
              _labeledTextField(
                controller: _menuSummaryController,
                label: 'Menu summary',
                hint: 'Categories, bestsellers, addons, variants',
                icon: Icons.menu_book_rounded,
                maxLines: 3,
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: _labeledTextField(
                      controller: _packagingChargeController,
                      label: 'Packaging charge',
                      hint: '12',
                      icon: Icons.inventory_2_outlined,
                      keyboardType: TextInputType.number,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _labeledTextField(
                      controller: _gstController,
                      label: 'GST %',
                      hint: '5',
                      icon: Icons.percent_rounded,
                      keyboardType: TextInputType.number,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _labeledTextField(
                controller: _handlingFeeController,
                label: 'Handling fee',
                hint: '8',
                icon: Icons.payments_outlined,
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: _analyticsCard(
                      title: 'Commission',
                      value: _commissionPreview,
                      tone: const Color(0xFFFFF4EB),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _analyticsCard(
                      title: 'Payout cycle',
                      value: _payoutCycle,
                      tone: const Color(0xFFF1FBF4),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _backgroundLocationEnabled,
                title: const Text('Background sync permissions explained'),
                subtitle: const Text('Used for live order updates and partner operations'),
                onChanged: (value) =>
                    setState(() => _backgroundLocationEnabled = value),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _notificationPermissionEnabled,
                title: const Text('Notification permissions ready'),
                subtitle: const Text('Order alerts and approval updates'),
                onChanged: (value) =>
                    setState(() => _notificationPermissionEnabled = value),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildReviewStep() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      children: [
        _heroStepCard(
          title: 'Registration Review',
          subtitle:
              'Review merchant details, delivery configuration, documents, and submit for live approval tracking.',
          icon: Icons.fact_check_rounded,
        ),
        const SizedBox(height: 18),
        _surfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _reviewTile('Restaurant', _businessNameController.text.trim()),
              _reviewTile('Owner mobile', _verifiedPhoneNumber ?? 'Not verified'),
              _reviewTile('Address', _addressController.text.trim()),
              _reviewTile('Categories', _selectedCategories.join(', ')),
              _reviewTile('Delivery zone', _selectedAreaName()),
              _reviewTile('Menu setup', _menuSummaryController.text.trim()),
              _reviewTile('Commission preview', _commissionPreview),
              const SizedBox(height: 12),
              CheckboxListTile(
                value: _agreeTerms,
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: const Text(
                  'I confirm the restaurant information, payout details, and compliance documents are accurate.',
                  style: TextStyle(
                    color: _ink,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
                onChanged: (value) =>
                    setState(() => _agreeTerms = value ?? false),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF7ED),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFFED7AA)),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.insights_rounded, color: _orange),
                    SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'After submission you will see live status updates for verification, moderation, corrections, and final approval.',
                        style: TextStyle(
                          color: _ink,
                          fontWeight: FontWeight.w700,
                          height: 1.45,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _heroStepCard({
    required String title,
    required String subtitle,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFFFF2F4), Color(0xFFFFF8EF)],
        ),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: const Color(0xFFFFD5DB)),
      ),
      child: Row(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(22),
              gradient: const LinearGradient(colors: [_red, _orange]),
            ),
            child: Icon(icon, color: Colors.white, size: 30),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    color: _ink,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 15,
                    height: 1.5,
                    fontWeight: FontWeight.w600,
                    color: _muted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _surfaceCard({required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: _line),
        boxShadow: const [
          BoxShadow(
            color: Color(0x12000000),
            blurRadius: 22,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: child,
    );
  }

  InputDecoration _fieldDecoration({
    required String label,
    required IconData icon,
    String? hint,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon),
      filled: true,
      fillColor: const Color(0xFFF8F9FB),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: _line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: _red, width: 1.4),
      ),
    );
  }

  Widget _labeledTextField({
    required TextEditingController controller,
    required String label,
    required String hint,
    required IconData icon,
    TextInputType? keyboardType,
    int maxLines = 1,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      validator: validator,
      decoration: _fieldDecoration(label: label, hint: hint, icon: icon),
    );
  }

  Widget _tapField({
    required String label,
    required String value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: InputDecorator(
        decoration: _fieldDecoration(
          label: label,
          hint: '',
          icon: Icons.access_time_rounded,
        ),
        child: Text(
          value,
          style: const TextStyle(
            fontWeight: FontWeight.w700,
            color: _ink,
          ),
        ),
      ),
    );
  }

  Widget _uploadCard(String label, File? file, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(22),
      child: Container(
        width: 150,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: file != null ? const Color(0xFFF1FBF4) : const Color(0xFFF8F9FB),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: file != null ? const Color(0xFFD8F1DE) : _line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              file != null ? Icons.check_circle_rounded : Icons.add_photo_alternate_outlined,
              color: file != null ? _green : _orange,
            ),
            const SizedBox(height: 16),
            Text(
              label,
              style: const TextStyle(
                fontWeight: FontWeight.w900,
                color: _ink,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              file == null ? 'Upload photo' : file.path.split(Platform.pathSeparator).last,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: _muted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _documentTile(String label, File? file, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: _line),
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: file != null ? const Color(0xFFF1FBF4) : const Color(0xFFFFF7ED),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  file != null ? Icons.verified_rounded : Icons.upload_file_rounded,
                  color: file != null ? _green : _orange,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(
                        color: _ink,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      file == null
                          ? 'Pending upload'
                          : file.path.split(Platform.pathSeparator).last,
                      style: const TextStyle(
                        color: _muted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                file == null ? 'Pending' : 'Uploaded',
                style: TextStyle(
                  color: file == null ? _orange : _green,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _miniMetric(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: _muted, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              color: _ink,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _analyticsCard({
    required String title,
    required String value,
    required Color tone,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: tone,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: _muted, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              color: _ink,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _reviewTile(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(
              label,
              style: const TextStyle(
                color: _muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '-' : value,
              style: const TextStyle(
                color: _ink,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }

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

  String? _requiredValidator(String? value) {
    if (value == null || value.trim().isEmpty) {
      return 'Required';
    }
    return null;
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.redAccent : _green,
      ),
    );
  }
}

class _OnboardingStep {
  const _OnboardingStep({
    required this.title,
    required this.subtitle,
    required this.icon,
  });

  final String title;
  final String subtitle;
  final IconData icon;
}

class _GlassBadge extends StatelessWidget {
  const _GlassBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.16),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withOpacity(0.18)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.14),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withOpacity(0.14)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: Colors.white70,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
              fontSize: 20,
            ),
          ),
        ],
      ),
    );
  }
}

class _FeatureRow extends StatelessWidget {
  const _FeatureRow({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF4EB),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: _RegisterScreenState._orange),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: _RegisterScreenState._ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: _RegisterScreenState._muted,
                    fontWeight: FontWeight.w600,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
