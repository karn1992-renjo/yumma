import 'dart:async';
import 'a1paso_auth_widgets.dart';
import 'dart:io';
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../config/app_config.dart';
import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/appsflyer_deep_link_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../services/location_service.dart';
import '../../services/partner_application_service.dart';
import '../../services/verification_service.dart';
import '../../utils/phone_number_utils.dart';
import '../driver/background_location_disclosure_screen.dart';
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

  TextStyle _font(
    double size, {
    FontWeight weight = FontWeight.w400,
    Color? color,
    double? height,
    double? letterSpacing,
  }) =>
      GoogleFonts.plusJakartaSans(
        fontSize: size,
        fontWeight: weight,
        color: color ?? _text,
        height: height ?? 1.3,
        letterSpacing: letterSpacing,
      );

  Widget _fieldLabel(
    String labelText, {
    bool req = false,
    String? helper,
    required Widget child,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                labelText,
                style: _font(13.5, weight: FontWeight.w600, color: const Color(0xFF374151)),
              ),
              if (req) ...[
                const SizedBox(width: 4),
                Text(
                  '*',
                  style: _font(
                    13.5,
                    weight: FontWeight.w700,
                    color: const Color(0xFFDC2626),
                  ),
                ),
              ],
            ],
          ),
          if (helper != null) ...[
            const SizedBox(height: 2),
            Text(
              helper,
              style: _font(12, color: _subtext),
            ),
          ],
          const SizedBox(height: 4),
          child,
        ],
      ),
    );
  }

  final _formKey = GlobalKey<FormState>();
  final _picker = ImagePicker();
  final _locationService = LocationService();
  final _applicationService = PartnerApplicationService.instance;
  final _verificationService = VerificationService.instance;
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
  List<LocationSuggestion> _locationSuggestions = const [];

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
  bool _isLoadingLocationSuggestions = false;
  bool _isLocating = false;
  bool _isSubmitting = false;
  bool _isSendingOtp = false;
  int _currentStep = 0;
  bool _isForward = true;
  int _locationSearchSerial = 0;

  File? _profilePhoto;
  File? _vehicleImage;
  File? _aadhaarFile;
  File? _panFile;
  File? _licenseFile;
  File? _rcFile;
  File? _insuranceFile;

  DocVerifyResult? _vehicleRcResult;
  DocVerifyResult? _drivingLicenseResult;
  DocVerifyResult? _panDocResult;
  DocVerifyResult? _aadhaarDocResult;
  bool _isCheckingVehicleRc = false;
  bool _isCheckingLicense = false;
  bool _isCheckingPanDoc = false;
  bool _isCheckingAadhaarDoc = false;
  Timer? _vehicleRcDebounce;
  Timer? _licenseDebounce;
  Timer? _locationSearchDebounce;

  Color get _primary => AppConfig.primaryColor;
  Color get _secondary => AppConfig.secondaryColor;
  bool get _isPhoneVerified =>
      _verifiedPhoneToken != null && _verifiedPhoneNumber != null;

  @override
  void initState() {
    super.initState();
    _phoneController.text =
        _stripCountryCode(widget.initialPhone?.trim() ?? '');
    _loadBootstrap();
  }

  @override
  void dispose() {
    _vehicleRcDebounce?.cancel();
    _licenseDebounce?.cancel();
    _locationSearchDebounce?.cancel();
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
    return PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      countryCode: _branding.defaultMobileCountryCode,
      log: true,
    ).normalizedNumber;
  }

  String _stripCountryCode(String phone) {
    if (phone.trim().isEmpty) return '';
    try {
      return PhoneNumberUtils.localMobile(
        phone,
        countryCode: _branding.defaultMobileCountryCode,
      );
    } on FormatException {
      return PhoneNumberUtils.sanitizedDigits(phone);
    }
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

  void _onVehicleNumberChanged(String value) {
    _vehicleRcDebounce?.cancel();
    if (value.trim().length < 8) {
      setState(() => _vehicleRcResult = null);
      return;
    }
    _vehicleRcDebounce = Timer(const Duration(milliseconds: 700), () {
      _checkVehicleRc(value.trim());
    });
  }

  Future<void> _checkVehicleRc(String vehicleNumber) async {
    setState(() => _isCheckingVehicleRc = true);
    final result = await _verificationService.verifyVehicleRc(vehicleNumber);
    if (!mounted) return;
    setState(() {
      _isCheckingVehicleRc = false;
      _vehicleRcResult = result;
    });
  }

  Future<void> _pickDateOfBirth() async {
    final now = DateTime.now();
    DateTime initialDate = DateTime(now.year - 18, now.month, now.day);
    final existing = DateTime.tryParse(_dobController.text.trim());
    if (existing != null) {
      initialDate = existing;
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(now.year - 80),
      lastDate: DateTime(now.year - 18, now.month, now.day),
      helpText: 'Select date of birth',
      initialEntryMode: DatePickerEntryMode.calendarOnly,
    );

    if (picked == null || !mounted) return;

    setState(() {
      _dobController.text = _formatDate(picked);
    });
    _onLicenseFieldsChanged();
  }

  String _formatDate(DateTime date) {
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
  }

  void _onLicenseFieldsChanged() {
    _licenseDebounce?.cancel();
    final licenseNumber = _licenseNumberController.text.trim();
    final dob = _dobController.text.trim();
    if (licenseNumber.length < 6 ||
        !RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(dob)) {
      setState(() => _drivingLicenseResult = null);
      return;
    }
    _licenseDebounce = Timer(const Duration(milliseconds: 700), () {
      _checkDrivingLicense(licenseNumber: licenseNumber, dob: dob);
    });
  }

  Future<void> _checkDrivingLicense({
    required String licenseNumber,
    required String dob,
  }) async {
    setState(() => _isCheckingLicense = true);
    final result = await _verificationService.verifyDrivingLicense(
      licenseNumber: licenseNumber,
      dob: dob,
    );
    if (!mounted) return;
    setState(() {
      _isCheckingLicense = false;
      _drivingLicenseResult = result;
    });
  }

  Future<void> _checkPanDocument(File file) async {
    setState(() {
      _isCheckingPanDoc = true;
      _panDocResult = null;
    });
    final result = await _verificationService.verifyPanDocument(file.path);
    if (!mounted) return;
    setState(() {
      _isCheckingPanDoc = false;
      _panDocResult = result;
    });
  }

  Future<void> _checkAadhaarDocument(File file) async {
    setState(() {
      _isCheckingAadhaarDoc = true;
      _aadhaarDocResult = null;
    });
    final result = await _verificationService.verifyAadhaarDocument(file.path);
    if (!mounted) return;
    setState(() {
      _isCheckingAadhaarDoc = false;
      _aadhaarDocResult = result;
    });
  }

  Widget _verifyStatusLine(bool checking, DocVerifyResult? result) {
    if (checking) {
      return Padding(
        padding: const EdgeInsets.only(top: 6, left: 4),
        child: Row(
          children: [
            SizedBox(
              width: 13,
              height: 13,
              child: CircularProgressIndicator(strokeWidth: 2, color: _primary),
            ),
            const SizedBox(width: 8),
            Text(
              'Verifying document details...',
              style: _font(12, color: _subtext),
            ),
          ],
        ),
      );
    }
    if (result == null || result.status == DocVerifyStatus.unconfigured) {
      return const SizedBox.shrink();
    }
    final IconData icon;
    final Color color;
    final String text;
    if (result.status == DocVerifyStatus.verified) {
      icon = Icons.verified_rounded;
      color = const Color(0xFF16A34A);
      text = 'Verified';
    } else {
      icon = Icons.info_outline_rounded;
      color = const Color(0xFFD97706);
      text = 'Not verified. It will be verified manually.';
    }
    return Padding(
      padding: const EdgeInsets.only(top: 6, left: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 15),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: _font(12, color: color, weight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _useCurrentLocation() async {
    setState(() => _isLocating = true);
    try {
      if (!await _ensureLocationDisclosureBeforeRequest()) {
        _showMessage('Location consent is required before using your location.',
            isError: true);
        return;
      }

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

  Future<bool> _ensureLocationDisclosureBeforeRequest() async {
    final permission = await _locationService.checkLocationPermission();
    if (permission != LocationPermission.denied) return true;
    if (!mounted) return false;

    return BackgroundLocationDisclosureScreen.ensureAccepted(
      context,
      forceDisclosure: true,
    );
  }

  void _onLocationSearchChanged(String value) {
    _locationSearchDebounce?.cancel();
    final query = value.trim();
    _latitude = null;
    _longitude = null;
    _selectedAreaId = null;
    if (query.length < 3) {
      setState(() {
        _locationSuggestions = const [];
        _isLoadingLocationSuggestions = false;
      });
      return;
    }

    _locationSearchDebounce = Timer(const Duration(milliseconds: 450), () {
      _loadLocationSuggestions(query);
    });
  }

  Future<void> _loadLocationSuggestions(String query) async {
    final requestId = ++_locationSearchSerial;
    setState(() => _isLoadingLocationSuggestions = true);

    final suggestions = await _locationService.searchLocationSuggestions(query);
    if (!mounted || requestId != _locationSearchSerial) return;

    setState(() {
      _locationSuggestions = suggestions;
      _isLoadingLocationSuggestions = false;
    });
  }

  Future<void> _selectLocationSuggestion(LocationSuggestion suggestion) async {
    _locationSearchDebounce?.cancel();
    _latitude = suggestion.lat;
    _longitude = suggestion.lng;

    setState(() {
      _locationSearchController.text = suggestion.displayName;
      _addressController.text = suggestion.address.isNotEmpty
          ? suggestion.address
          : suggestion.displayName;
      if (suggestion.city.isNotEmpty) {
        _cityController.text = suggestion.city;
      }
      _locationSuggestions = const [];
      _isLoadingLocationSuggestions = false;
    });

    _autoAssignAreaFromCurrentLocation(showFeedback: true);
  }

  Future<void> _searchLocationManually() async {
    final query = _locationSearchController.text.trim().isNotEmpty
        ? _locationSearchController.text.trim()
        : _addressController.text.trim();
    if (query.isEmpty) {
      _showMessage('Enter an area, landmark, or pincode to search.',
          isError: true);
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
        _showMessage('Location coordinates could not be resolved.',
            isError: true);
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
      late final String phone;
      try {
        phone = _normalizedPhone();
      } on FormatException catch (error) {
        _showMessage(error.message, isError: true);
        return;
      }
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
          _showMessage(authProvider.error ?? 'Failed to send OTP',
              isError: true);
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
            otpServiceProvider: _branding.resolvedOtpServiceProvider,
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
      _showNotServiceableMessage();
    }

    setState(() => _isSubmitting = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      final referralCode =
          prefs.getString(AppsFlyerDeepLinkService.pendingReferralCodeKey);
      final response = await _applicationService.submitApplication(
        fields: {
          'partner_type': 'driver',
          'verified_phone_token': _verifiedPhoneToken!,
          if (referralCode != null && referralCode.trim().isNotEmpty)
            'referral_code': referralCode.trim(),
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
          'area_id': _selectedAreaId?.toString() ?? '',
          'latitude': _latitude?.toString() ?? '',
          'longitude': _longitude?.toString() ?? '',
          'bank_holder_name': _bankHolderController.text.trim(),
          'bank_name': _bankNameController.text.trim(),
          'bank_ifsc': _ifscController.text.trim(),
          'bank_account_number': _accountNumberController.text.trim(),
          'upi_id': _upiController.text.trim(),
          'background_location_enabled': _backgroundLocationEnabled ? '1' : '0',
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
          if (_insuranceFile != null)
            'insurance_document': _insuranceFile!.path,
        },
      );

      if (!mounted) return;

      Navigator.pushReplacementNamed(
        context,
        '/application-status',
        arguments: response['data']?['application_number']?.toString(),
      );
      await prefs.remove(AppsFlyerDeepLinkService.pendingReferralCodeKey);
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
      hintStyle: _font(
        15,
        weight: FontWeight.w400,
        color: const Color(0xFF9CA3AF),
      ),
      prefixIcon: prefixIcon != null
          ? Padding(
              padding: const EdgeInsets.only(right: 12),
              child: prefixIcon,
            )
          : null,
      prefixIconConstraints: const BoxConstraints(minWidth: 32),
      suffixIcon: suffixIcon,
      filled: false,
      contentPadding: const EdgeInsets.symmetric(vertical: 12),
      enabledBorder: const UnderlineInputBorder(
        borderSide: BorderSide(color: Color(0xFFE5E7EB), width: 1.4),
      ),
      focusedBorder: UnderlineInputBorder(
        borderSide: BorderSide(color: _primary, width: 2.0),
      ),
      errorBorder: const UnderlineInputBorder(
        borderSide: BorderSide(color: Color(0xFFDC2626), width: 1.4),
      ),
      focusedErrorBorder: const UnderlineInputBorder(
        borderSide: BorderSide(color: Color(0xFFDC2626), width: 2.0),
      ),
    );
  }

  static const _stepCount = 7;

  final _stepShortNames = const [
    'Mobile',
    'Profile',
    'Selfie',
    'Zone',
    'Vehicle',
    'Documents',
    'Payout',
  ];

  String _stepTitle(int index) {
    switch (index) {
      case 0:
        return 'Verify Mobile Number';
      case 1:
        return 'Personal Details';
      case 2:
        return 'Partner Profile Selfie';
      case 3:
        return 'Operating City & Zone';
      case 4:
        return 'Vehicle & Licence Details';
      case 5:
        return 'Upload KYC Documents';
      default:
        return 'Bank Account & Payouts';
    }
  }

  String _stepSubtitle(int index) {
    switch (index) {
      case 0:
        return 'Enter your mobile number to get started with your application.';
      case 1:
        return 'Enter your personal details to set up your partner account.';
      case 2:
        return 'Take a live photo for your Delivery Partner ID card.';
      case 3:
        return 'Locate your area to match your primary delivery operational hub.';
      case 4:
        return 'Provide your vehicle registration and driving licence details.';
      case 5:
        return 'Upload official document copies for identity & vehicle verification.';
      default:
        return 'Add your bank account for automated, daily delivery earnings.';
    }
  }

  IconData _stepIcon(int index) {
    switch (index) {
      case 0:
        return Icons.phone_android_rounded;
      case 1:
        return Icons.person_outline_rounded;
      case 2:
        return Icons.camera_alt_outlined;
      case 3:
        return Icons.location_on_outlined;
      case 4:
        return Icons.two_wheeler_rounded;
      case 5:
        return Icons.assignment_outlined;
      default:
        return Icons.account_balance_outlined;
    }
  }

  Future<void> _continueStep(AuthProvider auth) async {
    if (_isSubmitting ||
        _isLoadingBranding ||
        _isLoadingAreas ||
        auth.isLoading) return;
    if (!_formKey.currentState!.validate()) return;

    if (_currentStep == 0 && !_isPhoneVerified) {
      await _verifyMobile();
      if (!_isPhoneVerified) return;
    }

    if (_currentStep == 2) {
      if (_profilePhoto == null) {
        _showMessage('Please take a live selfie photo to continue.', isError: true);
        await _takeProfileSelfie();
        return;
      }
    }

    if (_currentStep == 3) {
      if (_latitude == null || _longitude == null) {
        _showMessage(
            'Use current location first so we can auto-assign your delivery zone.',
            isError: true);
        return;
      }
      if (_selectedAreaId == null) {
        _autoAssignAreaFromCurrentLocation();
        _showNotServiceableMessage();
      }
    }

    if (_currentStep < _stepCount - 1) {
      setState(() {
        _isForward = true;
        _currentStep += 1;
      });
      return;
    }

    await _submitApplication();
  }

  void _backStep() {
    if (_currentStep == 0) {
      Navigator.pop(context);
      return;
    }
    setState(() {
      _isForward = false;
      _currentStep -= 1;
    });
  }

  Widget _buildStepTransition({
    required Widget child,
    required int currentStep,
  }) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 320),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      layoutBuilder: (currentChild, previousChildren) {
        return Stack(
          alignment: Alignment.topCenter,
          children: <Widget>[
            ...previousChildren,
            if (currentChild != null) currentChild,
          ],
        );
      },
      transitionBuilder: (Widget child, Animation<double> animation) {
        final isIncoming = (child.key as ValueKey<int>?)?.value == currentStep;
        final beginOffset = _isForward
            ? (isIncoming ? const Offset(0.24, 0.0) : const Offset(-0.24, 0.0))
            : (isIncoming ? const Offset(-0.24, 0.0) : const Offset(0.24, 0.0));
        return SlideTransition(
          position: Tween<Offset>(
            begin: beginOffset,
            end: Offset.zero,
          ).animate(animation),
          child: FadeTransition(
            opacity: animation,
            child: child,
          ),
        );
      },
      child: KeyedSubtree(
        key: ValueKey<int>(currentStep),
        child: child,
      ),
    );
  }

  Widget _animatedProgressBar(double progress) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: progress),
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
      builder: (context, value, _) {
        return Container(
          height: 3.5,
          width: double.infinity,
          color: _line,
          alignment: Alignment.centerLeft,
          child: FractionallySizedBox(
            widthFactor: value.clamp(0.0, 1.0),
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [_primary, _secondary],
                ),
                borderRadius:
                    const BorderRadius.horizontal(right: Radius.circular(3)),
                boxShadow: [
                  BoxShadow(
                    color: _primary.withOpacity(0.35),
                    blurRadius: 4,
                    offset: const Offset(0, 1),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _topNav(bool busy) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 4, 16, 4),
      child: Row(
        children: [
          IconButton(
            onPressed: busy ? null : _backStep,
            icon: Icon(
              _currentStep == 0
                  ? Icons.close_rounded
                  : Icons.arrow_back_rounded,
              color: _text,
              size: 24,
            ),
          ),
          const Spacer(),
          TextButton(
            onPressed: busy
                ? null
                : () => Navigator.pushNamed(context, '/application-status'),
            style: TextButton.styleFrom(
              foregroundColor: _subtext,
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            ),
            child: Text(
              'Track Status',
              style: _font(13, weight: FontWeight.w600, color: _subtext),
            ),
          ),
        ],
      ),
    );
  }

  Widget _thinProgressBar() {
    return Container(
      height: 3,
      margin: const EdgeInsets.symmetric(horizontal: 24),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(2),
        child: LinearProgressIndicator(
          value: (_currentStep + 1) / _stepCount,
          backgroundColor: const Color(0xFFF3F4F6),
          valueColor: AlwaysStoppedAnimation<Color>(_primary),
        ),
      ),
    );
  }

  Widget _buildStepHeader() {
    final (String title, String subtitle) = switch (_currentStep) {
      0 => (
          'Enter your mobile\nnumber',
          '10-Digit mobile number',
        ),
      1 => (
          'Tell us about\nyourself',
          'Enter your personal details as they appear on your government IDs',
        ),
      2 => (
          'Take a live\nprofile photo',
          'This photo will be displayed on your Delivery Partner ID card',
        ),
      3 => (
          'Where will you\ndeliver?',
          'Orders will be dispatched to you within your operational delivery area',
        ),
      4 => (
          'Vehicle &\nlicence details',
          'Provide vehicle registration and licence details for KYC verification',
        ),
      5 => (
          'Upload KYC\ndocuments',
          'Attach clear photos or scanned copies of your official documents',
        ),
      _ => (
          'Bank account\n& payouts',
          'Your delivery earnings and incentives will be deposited into this account',
        ),
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: _font(28, weight: FontWeight.w800, color: _text, height: 1.18, letterSpacing: -0.6),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: _font(13.5, weight: FontWeight.w500, color: _subtext, height: 1.4),
          ),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final busy = auth.isLoading ||
            _isLoadingBranding ||
            _isSubmitting ||
            _isLoadingAreas ||
            _isSendingOtp;

        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: Column(
              children: [
                _topNav(busy),
                _thinProgressBar(),
                Expanded(
                  child: Form(
                    key: _formKey,
                    child: SingleChildScrollView(
                      physics: const BouncingScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(24, 24, 24, 20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildStepHeader(),
                          const SizedBox(height: 28),
                          _buildStepTransition(
                            currentStep: _currentStep,
                            child: _buildCurrentStep(auth),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                _bottomActionArea(busy, auth),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _bottomActionArea(bool busy, AuthProvider auth) {
    return Container(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 16),
      color: Colors.white,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (_currentStep == 0) ...[
            Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: RichText(
                textAlign: TextAlign.center,
                text: TextSpan(
                  style: _font(12, color: _subtext, height: 1.45),
                  children: [
                    TextSpan(
                      text: 'By proceeding, you agree with ${_branding.displayName}\'s ',
                    ),
                    TextSpan(
                      text: 'terms and conditions',
                      style: _font(12, weight: FontWeight.w700, color: _text),
                    ),
                    const TextSpan(text: ' and '),
                    TextSpan(
                      text: 'privacy policy',
                      style: _font(12, weight: FontWeight.w700, color: _text),
                    ),
                    const TextSpan(text: '.'),
                  ],
                ),
              ),
            ),
          ] else if (_currentStep == _stepCount - 1) ...[
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: CheckboxListTile(
                value: _agreeTerms,
                onChanged: (value) =>
                    setState(() => _agreeTerms = value ?? false),
                contentPadding: EdgeInsets.zero,
                activeColor: _primary,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(
                  'I agree to the Delivery Partner Agreement, Code of Conduct, and Privacy Policy.',
                  style: _font(12, color: _subtext, height: 1.35),
                ),
              ),
            ),
          ],
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed: busy ? null : () => _continueStep(auth),
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                disabledBackgroundColor: _primary.withOpacity(0.35),
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(28),
                ),
              ),
              child: busy
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    )
                  : Text(
                      _ctaLabel(),
                      style: _font(16, weight: FontWeight.w700, color: Colors.white),
                    ),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.shield_outlined, size: 17, color: Color(0xFF16A34A)),
              const SizedBox(width: 6),
              Text(
                'Trusted by 10,000+ Delivery Partners',
                style: _font(12.5, weight: FontWeight.w600, color: const Color(0xFF374151)),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _ctaLabel() {
    if (_isSubmitting) return 'Submitting application...';
    if (_isSendingOtp) return 'Sending OTP...';
    if (_currentStep == 0) return _isPhoneVerified ? 'Continue' : 'Get OTP';
    if (_currentStep == 2 && _profilePhoto == null) return 'Take Live Selfie';
    if (_currentStep == _stepCount - 1) return 'Submit Application';
    return 'Continue';
  }

  Widget _buildCurrentStep(AuthProvider auth) {
    switch (_currentStep) {
      case 0:
        return _mobileStep(auth);
      case 1:
        return _personalStep();
      case 2:
        return _profilePhotoStep();
      case 3:
        return _addressStep();
      case 4:
        return _vehicleStep();
      case 5:
        return _documentsStep();
      default:
        return _payoutStep();
    }
  }

  Widget _mobileStep(AuthProvider auth) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(vertical: 6),
          decoration: const BoxDecoration(
            border: Border(
              bottom: BorderSide(color: Color(0xFFE5E7EB), width: 1.5),
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Row(
                children: [
                  Text(
                    '+${_branding.defaultMobileCountryCode.replaceAll('+', '')}',
                    style: _font(20, weight: FontWeight.w700, color: _text),
                  ),
                  const SizedBox(width: 4),
                  const Icon(
                    Icons.arrow_drop_down_rounded,
                    size: 22,
                    color: Color(0xFF6B7280),
                  ),
                ],
              ),
              const SizedBox(width: 14),
              Container(width: 1, height: 26, color: const Color(0xFFE5E7EB)),
              const SizedBox(width: 14),
              Expanded(
                child: TextFormField(
                  controller: _phoneController,
                  readOnly: _isPhoneVerified,
                  keyboardType: TextInputType.phone,
                  style: _font(20, weight: FontWeight.w700, color: _text, letterSpacing: 0.5),
                  onChanged: (_) {
                    if (_isPhoneVerified) return;
                    if (_verifiedPhoneToken != null) {
                      setState(() {
                        _verifiedPhoneToken = null;
                        _verifiedPhoneNumber = null;
                      });
                    }
                  },
                  decoration: InputDecoration(
                    hintText: 'Enter your number',
                    hintStyle: _font(18, weight: FontWeight.w400, color: const Color(0xFF9CA3AF)),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    errorBorder: InputBorder.none,
                    focusedErrorBorder: InputBorder.none,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 6),
                  ),
                  validator: (value) => PhoneNumberUtils.validateMobile(
                    value,
                    countryCode: _branding.defaultMobileCountryCode,
                  ),
                ),
              ),
            ],
          ),
        ),
        if (_isPhoneVerified) ...[
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFF16A34A).withOpacity(0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                const Icon(Icons.verified_rounded, size: 18, color: Color(0xFF16A34A)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Verified: +$_verifiedPhoneNumber',
                    style: _font(13.5, weight: FontWeight.w700, color: const Color(0xFF16A34A)),
                  ),
                ),
                TextButton(
                  onPressed: () {
                    setState(() {
                      _verifiedPhoneToken = null;
                      _verifiedPhoneNumber = null;
                    });
                  },
                  child: Text(
                    'Change',
                    style: _font(13, weight: FontWeight.w700, color: _primary),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _personalStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _fieldLabel(
          'Full Name',
          req: true,
          helper: 'As printed on your Government ID / Driving Licence',
          child: TextFormField(
            controller: _nameController,
            style: _font(16, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. Rahul Sharma',
              prefixIcon: const Icon(Icons.person_outline_rounded),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'Full name is required'
                : null,
          ),
        ),
        _fieldLabel(
          'Email Address',
          req: true,
          helper: 'We will send registration updates and payout receipts here',
          child: TextFormField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            style: _font(16, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. rahul.sharma@example.com',
              prefixIcon: const Icon(Icons.email_outlined),
            ),
            validator: (value) {
              if (value == null || value.trim().isEmpty) {
                return 'Email is required';
              }
              if (!value.contains('@')) return 'Enter a valid email';
              return null;
            },
          ),
        ),
        _fieldLabel(
          'Date of Birth',
          req: true,
          helper: 'Must be at least 18 years old to register as a partner',
          child: TextFormField(
            controller: _dobController,
            readOnly: true,
            onTap: _pickDateOfBirth,
            style: _font(16, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'YYYY-MM-DD',
              prefixIcon: const Icon(Icons.calendar_today_outlined),
              suffixIcon: IconButton(
                icon: const Icon(Icons.edit_calendar_outlined),
                onPressed: _pickDateOfBirth,
              ),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'Date of birth is required'
                : null,
          ),
        ),
        _fieldLabel(
          'Gender',
          req: true,
          child: Row(
            children: ['Male', 'Female', 'Other'].map((g) {
              final isSelected = _gender == g;
              return Padding(
                padding: const EdgeInsets.only(right: 10),
                child: ChoiceChip(
                  label: Text(g),
                  selected: isSelected,
                  selectedColor: _primary.withOpacity(0.12),
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: isSelected ? _primary : const Color(0xFFE5E7EB),
                    width: isSelected ? 1.5 : 1,
                  ),
                  labelStyle: _font(
                    13.5,
                    weight: isSelected ? FontWeight.w700 : FontWeight.w500,
                    color: isSelected ? _primary : _text,
                  ),
                  onSelected: (selected) {
                    if (selected) setState(() => _gender = g);
                  },
                ),
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  Future<void> _takeProfileSelfie() async {
    try {
      final file = await _picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        maxWidth: 1200,
        maxHeight: 1200,
        imageQuality: 88,
      );
      if (file == null || !mounted) return;
      setState(() {
        _profilePhoto = File(file.path);
      });
    } catch (e) {
      if (!mounted) return;
      _showMessage(
        'Camera error: ${e.toString().replaceFirst("Exception: ", "")}',
        isError: true,
      );
    }
  }

  Widget _profilePhotoStep() {
    final hasPhoto = _profilePhoto != null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        const SizedBox(height: 6),
        Center(
          child: Stack(
            alignment: Alignment.center,
            children: [
              Container(
                width: 184,
                height: 184,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: hasPhoto
                      ? const Color(0xFF16A34A).withOpacity(0.06)
                      : const Color(0xFFF9FAFB),
                  border: Border.all(
                    color: hasPhoto
                        ? const Color(0xFF16A34A)
                        : const Color(0xFFD1D5DB),
                    width: hasPhoto ? 2.5 : 1.8,
                  ),
                ),
                child: hasPhoto
                    ? ClipOval(
                        child: Image.file(
                          _profilePhoto!,
                          width: 184,
                          height: 184,
                          fit: BoxFit.cover,
                        ),
                      )
                    : Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 64,
                            height: 64,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: _primary.withOpacity(0.1),
                            ),
                            child: Icon(
                              Icons.camera_alt_rounded,
                              color: _primary,
                              size: 32,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Text(
                            'Live Selfie Only',
                            style: _font(13, weight: FontWeight.w700, color: _text),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Align face inside circle',
                            style: _font(11.5, color: _subtext),
                          ),
                        ],
                      ),
              ),
              if (hasPhoto)
                Positioned(
                  bottom: 6,
                  right: 10,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Color(0x26000000),
                          blurRadius: 6,
                          offset: Offset(0, 2),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.check_circle_rounded,
                      color: Color(0xFF16A34A),
                      size: 28,
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        Center(
          child: hasPhoto
              ? OutlinedButton.icon(
                  onPressed: _takeProfileSelfie,
                  icon: const Icon(Icons.refresh_rounded, size: 18),
                  label: Text(
                    'Retake Selfie',
                    style: _font(13.5, weight: FontWeight.w700, color: _primary),
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _primary,
                    side: BorderSide(color: _primary, width: 1.5),
                    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                  ),
                )
              : ElevatedButton.icon(
                  onPressed: _takeProfileSelfie,
                  icon: const Icon(Icons.camera_alt_rounded, size: 20, color: Colors.white),
                  label: Text(
                    'Open In-App Camera',
                    style: _font(14, weight: FontWeight.w700, color: Colors.white),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _primary,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
                  ),
                ),
        ),
        const SizedBox(height: 26),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          decoration: BoxDecoration(
            color: const Color(0xFFF9FAFB),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE5E7EB)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.verified_user_outlined, size: 18, color: Color(0xFF16A34A)),
                  const SizedBox(width: 8),
                  Text(
                    'Photo Guidelines for ID Verification',
                    style: _font(13.5, weight: FontWeight.w700, color: const Color(0xFF111827)),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _selfieGuideItem(
                icon: Icons.wb_sunny_outlined,
                title: 'Good, natural lighting',
                desc: 'Ensure your face is evenly lit with no backlighting or heavy shadows',
              ),
              const SizedBox(height: 12),
              _selfieGuideItem(
                icon: Icons.face_rounded,
                title: 'Look straight at camera',
                desc: 'Keep your eyes open and hold the camera at eye level',
              ),
              const SizedBox(height: 12),
              _selfieGuideItem(
                icon: Icons.no_accounts_outlined,
                title: 'No accessories or coverings',
                desc: 'Remove sunglasses, caps, hats, or face masks before capture',
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _selfieGuideItem({
    required IconData icon,
    required String title,
    required String desc,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            border: Border.all(color: const Color(0xFFE5E7EB)),
          ),
          child: Icon(icon, size: 17, color: const Color(0xFF4B5563)),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: _font(13, weight: FontWeight.w700, color: const Color(0xFF1F2937)),
              ),
              const SizedBox(height: 2),
              Text(
                desc,
                style: _font(11.5, color: _subtext, height: 1.35),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _addressStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: double.infinity,
          height: 46,
          child: OutlinedButton.icon(
            onPressed: _isLocating ? null : _useCurrentLocation,
            style: OutlinedButton.styleFrom(
              foregroundColor: _primary,
              side: BorderSide(color: _primary.withOpacity(0.4), width: 1.2),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(24),
              ),
            ),
            icon: _isLocating
                ? SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: _primary,
                    ),
                  )
                : const Icon(Icons.my_location_rounded, size: 18),
            label: Text(
              _isLocating ? 'Detecting Location...' : 'Use Current Location',
              style: _font(13.5, weight: FontWeight.w700),
            ),
          ),
        ),
        const SizedBox(height: 18),
        _fieldLabel(
          'Search Hub / Locality',
          helper: 'Search your operational hub, locality, or landmark',
          child: Column(
            children: [
              TextFormField(
                controller: _locationSearchController,
                style: _font(15, weight: FontWeight.w600),
                textInputAction: TextInputAction.search,
                onChanged: _onLocationSearchChanged,
                onFieldSubmitted: (_) => _searchLocationManually(),
                decoration: _fieldDecoration(
                  hint: 'Search area, landmark, or pincode',
                  prefixIcon: const Icon(Icons.search_rounded),
                  suffixIcon: _isLoadingLocationSuggestions
                      ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                            ),
                          ),
                        )
                      : null,
                ),
              ),
              if (_locationSuggestions.isNotEmpty) ...[
                const SizedBox(height: 8),
                _locationSuggestionList(),
              ],
            ],
          ),
        ),
        _fieldLabel(
          'City',
          req: true,
          child: TextFormField(
            controller: _cityController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. Bangalore',
              prefixIcon: const Icon(Icons.location_city_outlined),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'City is required'
                : null,
          ),
        ),
        _fieldLabel(
          'Full Address',
          req: true,
          child: TextFormField(
            controller: _addressController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'House/flat no., building name, street address',
              prefixIcon: const Icon(Icons.home_work_outlined),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'Address is required'
                : null,
          ),
        ),
        _fieldLabel(
          'Landmark (Optional)',
          child: TextFormField(
            controller: _landmarkController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'Nearby landmark or metro station',
              prefixIcon: const Icon(Icons.place_outlined),
            ),
          ),
        ),
        _fieldLabel(
          'Operational Delivery Hub',
          helper: 'Orders will be dispatched within this primary operational zone',
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Row(
              children: [
                Icon(
                  Icons.store_mall_directory_outlined,
                  color: _selectedAreaId == null ? _subtext : _primary,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _selectedAreaId == null
                        ? 'Pending Zone Detection'
                        : _selectedAreaName(),
                    style: _font(15, weight: FontWeight.w700, color: _text),
                  ),
                ),
              ],
            ),
          ),
        ),
        if (_latitude != null &&
            _longitude != null &&
            _selectedAreaId == null) ...[
          const SizedBox(height: 8),
          _infoBanner(
            icon: Icons.info_outline_rounded,
            text:
                'We are expanding fast! We have recorded your location and our onboarding team will notify you as soon as deliveries open in your sector.',
            accent: const Color(0xFFF59E0B),
          ),
        ],
      ],
    );
  }

  Widget _locationSuggestionList() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _line),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0D000000),
            blurRadius: 14,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: ListView.separated(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: _locationSuggestions.length,
        separatorBuilder: (_, __) => const Divider(height: 1, color: _line),
        itemBuilder: (context, index) {
          final suggestion = _locationSuggestions[index];
          final subtitle = [suggestion.city, suggestion.state]
              .where((value) => value.isNotEmpty)
              .join(', ');
          return ListTile(
            dense: true,
            leading: Icon(Icons.location_on_outlined, color: _primary),
            title: Text(
              suggestion.displayName,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: _font(13.5, weight: FontWeight.w600),
            ),
            subtitle: subtitle.isNotEmpty
                ? Text(
                    subtitle,
                    style: _font(12, color: _subtext),
                  )
                : null,
            onTap: () => _selectLocationSuggestion(suggestion),
          );
        },
      ),
    );
  }

  Widget _vehicleStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _fieldLabel(
          'Vehicle Type',
          req: true,
          child: Row(
            children: [
              ('bike', 'Bike', Icons.two_wheeler_rounded),
              ('ev_scooter', 'EV Scooter', Icons.electric_scooter_rounded),
              ('bicycle', 'Bicycle', Icons.pedal_bike_rounded),
            ].map((item) {
              final isSelected = _vehicleType == item.$1;
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  avatar: Icon(
                    item.$3,
                    size: 16,
                    color: isSelected ? _primary : _subtext,
                  ),
                  label: Text(item.$2),
                  selected: isSelected,
                  selectedColor: _primary.withOpacity(0.12),
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: isSelected ? _primary : const Color(0xFFE5E7EB),
                    width: isSelected ? 1.5 : 1,
                  ),
                  labelStyle: _font(
                    13,
                    weight: isSelected ? FontWeight.w700 : FontWeight.w500,
                    color: isSelected ? _primary : _text,
                  ),
                  onSelected: (selected) {
                    if (selected) setState(() => _vehicleType = item.$1);
                  },
                ),
              );
            }).toList(),
          ),
        ),
        _fieldLabel(
          'Vehicle Registration Number',
          req: true,
          helper: 'Enter registration plate number (e.g. KA01AB1234)',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextFormField(
                controller: _vehicleNumberController,
                textCapitalization: TextCapitalization.characters,
                style: _font(15, weight: FontWeight.w700, letterSpacing: 0.5),
                onChanged: _onVehicleNumberChanged,
                decoration: _fieldDecoration(
                  hint: 'e.g. KA01AB1234',
                  prefixIcon: const Icon(Icons.pin_outlined),
                ),
                validator: (value) => (value == null || value.trim().isEmpty)
                    ? 'Vehicle number is required'
                    : null,
              ),
              _verifyStatusLine(_isCheckingVehicleRc, _vehicleRcResult),
            ],
          ),
        ),
        _fieldLabel(
          'Vehicle Make & Model',
          child: TextFormField(
            controller: _vehicleModelController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. Hero Splendor, Honda Activa',
              prefixIcon: const Icon(Icons.directions_bike_outlined),
            ),
          ),
        ),
        _fieldLabel(
          'Fuel / Engine Type',
          req: true,
          child: Row(
            children: ['petrol', 'electric', 'manual'].map((f) {
              final isSelected = _fuelType == f;
              final label = f == 'petrol'
                  ? 'Petrol'
                  : f == 'electric'
                      ? 'Electric'
                      : 'Manual / Pedal';
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(label),
                  selected: isSelected,
                  selectedColor: _primary.withOpacity(0.12),
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: isSelected ? _primary : const Color(0xFFE5E7EB),
                    width: isSelected ? 1.5 : 1,
                  ),
                  labelStyle: _font(
                    13,
                    weight: isSelected ? FontWeight.w700 : FontWeight.w500,
                    color: isSelected ? _primary : _text,
                  ),
                  onSelected: (selected) {
                    if (selected) setState(() => _fuelType = f);
                  },
                ),
              );
            }).toList(),
          ),
        ),
        _fieldLabel(
          'Driving Licence Number',
          req: true,
          helper: 'Licence verification is validated against your date of birth',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextFormField(
                controller: _licenseNumberController,
                textCapitalization: TextCapitalization.characters,
                style: _font(15, weight: FontWeight.w700, letterSpacing: 0.5),
                onChanged: (_) => _onLicenseFieldsChanged(),
                decoration: _fieldDecoration(
                  hint: 'e.g. DL1420110012345',
                  prefixIcon: const Icon(Icons.badge_outlined),
                ),
                validator: (value) => (value == null || value.trim().isEmpty)
                    ? 'Licence number is required'
                    : null,
              ),
              _verifyStatusLine(_isCheckingLicense, _drivingLicenseResult),
            ],
          ),
        ),
      ],
    );
  }

  Widget _documentsStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: Text(
            'Upload clear photos or scanned copies of your KYC documents (JPG, PNG up to 5MB).',
            style: _font(13.5, color: _subtext, height: 1.4),
          ),
        ),
        _uploadTile(
          title: 'Aadhaar Card',
          subtitle: 'Government identity proof document',
          file: _aadhaarFile,
          onTap: () => _pickFile((file) {
            setState(() => _aadhaarFile = file);
            _checkAadhaarDocument(file);
          }),
        ),
        _verifyStatusLine(_isCheckingAadhaarDoc, _aadhaarDocResult),
        const SizedBox(height: 4),
        _uploadTile(
          title: 'PAN Card',
          subtitle: 'Tax and payout identity card',
          file: _panFile,
          onTap: () => _pickFile((file) {
            setState(() => _panFile = file);
            _checkPanDocument(file);
          }),
        ),
        _verifyStatusLine(_isCheckingPanDoc, _panDocResult),
        const SizedBox(height: 4),
        _uploadTile(
          title: 'Driving Licence',
          subtitle: 'Clear photo of valid driving licence',
          file: _licenseFile,
          onTap: () =>
              _pickFile((file) => setState(() => _licenseFile = file)),
        ),
        _uploadTile(
          title: 'Vehicle RC',
          subtitle: 'Registration Certificate document copy',
          file: _rcFile,
          onTap: () =>
              _pickFile((file) => setState(() => _rcFile = file)),
        ),
        _uploadTile(
          title: 'Vehicle Photo',
          subtitle: 'Clear photo showing registration number plate',
          file: _vehicleImage,
          onTap: () =>
              _pickFile((file) => setState(() => _vehicleImage = file)),
        ),
        _uploadTile(
          title: 'Vehicle Insurance',
          subtitle: 'Valid comprehensive or third-party policy copy',
          file: _insuranceFile,
          onTap: () =>
              _pickFile((file) => setState(() => _insuranceFile = file)),
        ),
      ],
    );
  }

  Widget _payoutStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _fieldLabel(
          'Account Holder Name',
          req: true,
          helper: 'Must match the name on your PAN card / Government ID',
          child: TextFormField(
            controller: _bankHolderController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. Rahul Sharma',
              prefixIcon: const Icon(Icons.person_outline_rounded),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'Account holder name is required'
                : null,
          ),
        ),
        _fieldLabel(
          'Bank Name',
          req: true,
          child: TextFormField(
            controller: _bankNameController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. State Bank of India, HDFC Bank',
              prefixIcon: const Icon(Icons.account_balance_outlined),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'Bank name is required'
                : null,
          ),
        ),
        _fieldLabel(
          'IFSC Code',
          req: true,
          helper: '11-character bank branch code (e.g. SBIN0001234)',
          child: TextFormField(
            controller: _ifscController,
            textCapitalization: TextCapitalization.characters,
            style: _font(15, weight: FontWeight.w700, letterSpacing: 0.5),
            decoration: _fieldDecoration(
              hint: 'e.g. SBIN0001234',
              prefixIcon: const Icon(Icons.code_rounded),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'IFSC code is required'
                : null,
          ),
        ),
        _fieldLabel(
          'Bank Account Number',
          req: true,
          child: TextFormField(
            controller: _accountNumberController,
            keyboardType: TextInputType.number,
            style: _font(15, weight: FontWeight.w700, letterSpacing: 0.5),
            decoration: _fieldDecoration(
              hint: 'Enter bank account number',
              prefixIcon: const Icon(Icons.credit_card_outlined),
            ),
            validator: (value) => (value == null || value.trim().isEmpty)
                ? 'Account number is required'
                : null,
          ),
        ),
        _fieldLabel(
          'UPI ID (Optional)',
          helper: 'For instant daily settlements and micro-incentives',
          child: TextFormField(
            controller: _upiController,
            style: _font(15, weight: FontWeight.w600),
            decoration: _fieldDecoration(
              hint: 'e.g. 9876543210@upi',
              prefixIcon: const Icon(Icons.payments_outlined),
            ),
          ),
        ),
        const SizedBox(height: 8),
        _fieldLabel(
          'Operational Permissions',
          child: Column(
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                activeColor: _primary,
                title: Text(
                  'Background Location',
                  style: _font(13.5, weight: FontWeight.w700, color: _text),
                ),
                subtitle: Text(
                  'Enables live order assignments and customer trip tracking while on duty.',
                  style: _font(11.5, color: _subtext, height: 1.35),
                ),
                value: _backgroundLocationEnabled,
                onChanged: (value) =>
                    setState(() => _backgroundLocationEnabled = value),
              ),
              const Divider(height: 1, color: Color(0xFFE5E7EB)),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                activeColor: _primary,
                title: Text(
                  'Push Notifications',
                  style: _font(13.5, weight: FontWeight.w700, color: _text),
                ),
                subtitle: Text(
                  'Used for instant order alerts, earning summaries, and account status updates.',
                  style: _font(11.5, color: _subtext, height: 1.35),
                ),
                value: _notificationPermissionEnabled,
                onChanged: (value) =>
                    setState(() => _notificationPermissionEnabled = value),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _infoBanner({
    required IconData icon,
    required String text,
    required Color accent,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: accent.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, color: accent, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: _font(
                12.5,
                weight: FontWeight.w500,
                color: _text,
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
    String? subtitle,
    required File? file,
    required VoidCallback onTap,
  }) {
    final isUploaded = file != null;
    final fileName =
        file?.path.split(RegExp(r'[\\/]')).where((e) => e.isNotEmpty).last;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: isUploaded
                ? const Color(0xFF16A34A).withOpacity(0.04)
                : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isUploaded
                  ? const Color(0xFF16A34A).withOpacity(0.35)
                  : const Color(0xFFE5E7EB),
              width: 1.2,
            ),
          ),
          child: Row(
            children: [
              Icon(
                isUploaded
                    ? Icons.check_circle_rounded
                    : Icons.file_upload_outlined,
                color: isUploaded ? const Color(0xFF16A34A) : _primary,
                size: 22,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      fileName ?? title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: _font(
                        14,
                        weight: FontWeight.w700,
                        color: _text,
                      ),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        isUploaded
                            ? 'Document attached'
                            : subtitle,
                        style: _font(
                          11.5,
                          color: isUploaded
                              ? const Color(0xFF16A34A)
                              : _subtext,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Text(
                isUploaded ? 'Change' : 'Upload',
                style: _font(
                  13,
                  weight: FontWeight.w700,
                  color: _primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showNotServiceableMessage() {
    _showMessage(
      'This location is not serviceable yet. We have noted your area and will notify you as soon as deliveries open in your sector.',
      isError: true,
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          message,
          style: _font(13.5, color: Colors.white, weight: FontWeight.w500),
        ),
        backgroundColor:
            isError ? const Color(0xFFDC2626) : const Color(0xFF1E293B),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
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

    final containing = _deliveryAreas
        .where(
          (area) => _areaContainsPoint(area, latitude, longitude),
        )
        .toList()
      ..sort(
        (left, right) => _areaFootprint(left).compareTo(_areaFootprint(right)),
      );

    return containing.isEmpty ? null : containing.first;
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

  bool _pointInPolygon(
      List<dynamic> polygon, double latitude, double longitude) {
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
      final intersects = (currentLat > latitude) != (previousLat > latitude) &&
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
    final a = (sin(dLat / 2) * sin(dLat / 2)) +
        cos(_degreesToRadians(lat1)) *
            cos(_degreesToRadians(lat2)) *
            (sin(dLon / 2) * sin(dLon / 2));
    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    return earthRadius * c;
  }

  double _degreesToRadians(double degrees) =>
      degrees * 3.1415926535897932 / 180;

  String _selectedAreaName() {
    for (final area in _deliveryAreas) {
      if (area['id'] == _selectedAreaId) {
        return area['name']?.toString() ?? '-';
      }
    }
    if (_isLoadingAreas) return 'Loading delivery zones...';
    if (_latitude != null && _longitude != null) return 'Not serviceable area';
    return 'Use current location or select an address to detect zone';
  }
}

class _AnimatedPressButton extends StatefulWidget {
  const _AnimatedPressButton({
    required this.child,
    required this.onPressed,
    required this.gradient,
  });

  final Widget child;
  final VoidCallback? onPressed;
  final Gradient gradient;

  @override
  State<_AnimatedPressButton> createState() => _AnimatedPressButtonState();
}

class _AnimatedPressButtonState extends State<_AnimatedPressButton> {
  bool _isPressed = false;

  @override
  Widget build(BuildContext context) {
    final enabled = widget.onPressed != null;
    return GestureDetector(
      onTapDown: enabled ? (_) => setState(() => _isPressed = true) : null,
      onTapUp: enabled
          ? (_) {
              setState(() => _isPressed = false);
              widget.onPressed?.call();
            }
          : null,
      onTapCancel: enabled ? () => setState(() => _isPressed = false) : null,
      child: AnimatedScale(
        scale: _isPressed ? 0.975 : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOutCubic,
        child: Container(
          decoration: BoxDecoration(
            gradient: enabled
                ? widget.gradient
                : LinearGradient(
                    colors: [Colors.grey.shade400, Colors.grey.shade400],
                  ),
            borderRadius: BorderRadius.circular(14),
            boxShadow: enabled
                ? [
                    BoxShadow(
                      color: const Color(0xFF10B981).withOpacity(_isPressed ? 0.16 : 0.28),
                      blurRadius: _isPressed ? 6 : 14,
                      offset: Offset(0, _isPressed ? 2 : 6),
                    ),
                  ]
                : null,
          ),
          child: widget.child,
        ),
      ),
    );
  }
}

