import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../services/firebase_phone_auth_service.dart';
import '../../services/location_service.dart';
import '../../services/partner_application_service.dart';
import '../../services/verification_service.dart';
import '../../utils/phone_number_utils.dart';
import 'otp_verification_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, this.initialPhone});

  final String? initialPhone;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  static const orange = Color(0xFFFF6B00);
  static const orangeDark = Color(0xFFE85F00);
  static const ink = Color(0xFF161B2C);
  static const body = Color(0xFF475467);
  static const muted = Color(0xFF667085);
  static const border = Color(0xFFE4E7EC);
  static const bg = Color(0xFFF8F9FB);
  static const green = Color(0xFF16A34A);
  static const amber = Color(0xFFF59E0B);
  static const red = Color(0xFFDC2626);
  static const draftKey = 'restaurant_registration_draft_v3';

  final _applicationService = PartnerApplicationService.instance;
  final _verificationService = VerificationService.instance;
  final _locationService = LocationService();
  final _firebasePhoneAuthService = FirebasePhoneAuthService();

  final restaurantName = TextEditingController();
  final description = TextEditingController();
  final ownerName = TextEditingController();
  final phone = TextEditingController();
  final email = TextEditingController();
  final password = TextEditingController();
  final confirmPassword = TextEditingController();

  final legalName = TextEditingController();
  final year = TextEditingController();
  final gstNumber = TextEditingController();
  final fssaiNumber = TextEditingController();
  final panNumber = TextEditingController();
  final minOrder = TextEditingController();
  final addressSearch = TextEditingController();
  final address1 = TextEditingController();
  final address2 = TextEditingController();
  final landmark = TextEditingController();
  final locality = TextEditingController();
  final city = TextEditingController();
  final state = TextEditingController();
  final postcode = TextEditingController();
  final country = TextEditingController(text: 'India');

  final holder = TextEditingController();
  final bankName = TextEditingController();
  final account = TextEditingController();
  final accountConfirm = TextEditingController();
  final ifsc = TextEditingController();
  final branch = TextEditingController();
  final upi = TextEditingController();

  List<String> cuisines = const [];
  final banks = const [
    'HDFC Bank',
    'ICICI Bank',
    'State Bank of India',
    'Axis Bank',
    'Kotak Mahindra Bank',
    'Punjab National Bank',
  ];

  AppBranding branding = AppBranding.fallback();
  List<Map<String, dynamic>> deliveryAreas = const [];
  List<Map<String, dynamic>> suggestions = const [];
  Set<String> selectedCuisines = {};
  Set<String> serviceTypes = {'Dine-in', 'Delivery'};
  int step = 0;
  int? matchedAreaId;
  double? latitude;
  double? longitude;
  String? matchedZoneName;
  String businessType = 'Restaurant';
  String gstStatus = 'GST Registered';
  String fssaiStatus = 'Licence Available';
  String prepTime = '25-30 minutes';
  String accountType = 'Current Account';
  String settlement = 'Daily';
  String opening = '10:00 AM';
  String closing = '11:00 PM';
  bool phoneVerified = false;
  bool emailVerified = false;
  bool bankConsent = false;
  bool declaration = false;
  bool agreement = false;
  bool loading = true;
  bool loadingCuisines = true;
  bool saving = false;
  bool sendingOtp = false;
  bool locating = false;
  bool searching = false;
  bool submitting = false;
  bool showPassword = false;
  bool showConfirmPassword = false;
  String saveStatus = 'Saved';
  String? cuisineLoadError;
  String? verifiedPhoneToken;
  String? verifiedPhoneNumber;
  File? logoFile;
  File? coverFile;
  File? gstFile;
  File? fssaiFile;
  File? panFile;
  File? bankProofFile;
  Timer? saveDebounce;
  Timer? periodicSave;
  Timer? searchDebounce;
  Timer? gstinDebounce;
  Timer? panDebounce;

  DocVerifyResult? gstinResult;
  DocVerifyResult? panResult;
  DocVerifyResult? panDocResult;
  bool checkingGstin = false;
  bool checkingPan = false;
  bool checkingPanDoc = false;

  final steps = const [
    'Basic Info',
    'Business Info',
    'Bank Details',
    'Preview',
  ];

  @override
  void initState() {
    super.initState();
    phone.text = widget.initialPhone ?? '';
    _listenForDraft();
    _bootstrap();
    periodicSave = Timer.periodic(
      const Duration(seconds: 25),
      (_) => _saveDraft(),
    );
  }

  @override
  void dispose() {
    saveDebounce?.cancel();
    periodicSave?.cancel();
    searchDebounce?.cancel();
    gstinDebounce?.cancel();
    panDebounce?.cancel();
    for (final c in [
      restaurantName,
      description,
      ownerName,
      phone,
      email,
      password,
      confirmPassword,
      legalName,
      year,
      gstNumber,
      fssaiNumber,
      panNumber,
      minOrder,
      addressSearch,
      address1,
      address2,
      landmark,
      locality,
      city,
      state,
      postcode,
      country,
      holder,
      bankName,
      account,
      accountConfirm,
      ifsc,
      branch,
      upi,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _listenForDraft() {
    for (final c in [
      restaurantName,
      description,
      ownerName,
      phone,
      email,
      password,
      confirmPassword,
      legalName,
      year,
      gstNumber,
      fssaiNumber,
      panNumber,
      minOrder,
      addressSearch,
      address1,
      address2,
      landmark,
      locality,
      city,
      state,
      postcode,
      country,
      holder,
      bankName,
      account,
      accountConfirm,
      ifsc,
      branch,
      upi,
    ]) {
      c.addListener(_queueSave);
    }
  }

  Future<void> _bootstrap() async {
    await Future.wait([
      _loadBranding(),
      _loadAreas(),
      _loadCuisines(),
      _loadDraft(),
    ]);
    if (!mounted) return;
    setState(() {
      selectedCuisines = selectedCuisines.where(cuisines.contains).toSet();
      loading = false;
    });
  }

  Future<void> _loadBranding() async {
    final b = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      branding = b;
      phone.text = _stripCountryCode(phone.text);
    });
  }

  Future<void> _loadAreas() async {
    try {
      final areas = await _applicationService.fetchDeliveryAreas();
      if (mounted) setState(() => deliveryAreas = areas);
    } catch (_) {
      if (mounted) setState(() => deliveryAreas = const []);
    }
  }

  Future<void> _loadCuisines() async {
    if (mounted) {
      setState(() {
        loadingCuisines = true;
        cuisineLoadError = null;
      });
    }
    try {
      final data = await _applicationService.fetchActiveCuisines();
      final names = data
          .map((item) => item['name']?.toString().trim() ?? '')
          .where((name) => name.isNotEmpty)
          .toSet()
          .toList(growable: false);
      if (!mounted) return;
      setState(() {
        cuisines = names;
        selectedCuisines = selectedCuisines.where(names.contains).toSet();
        loadingCuisines = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        cuisines = const [];
        selectedCuisines = {};
        loadingCuisines = false;
        cuisineLoadError =
            'Unable to load cuisines. Check your connection and retry.';
      });
    }
  }

  Future<void> _loadDraft() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(draftKey);
    if (raw == null) return;
    try {
      final data = jsonDecode(raw);
      if (data is! Map<String, dynamic>) return;
      void text(TextEditingController c, String key) {
        final value = data[key]?.toString();
        if (value != null) c.text = value;
      }

      text(restaurantName, 'restaurant_name');
      text(description, 'description');
      text(ownerName, 'owner_name');
      if ((widget.initialPhone ?? '').trim().isEmpty) text(phone, 'phone');
      text(email, 'email');
      text(password, 'password');
      text(confirmPassword, 'confirm_password');
      text(legalName, 'legal_name');
      text(year, 'year');
      text(gstNumber, 'gst_number');
      text(fssaiNumber, 'fssai_number');
      text(panNumber, 'pan_number');
      text(minOrder, 'min_order');
      text(addressSearch, 'address_search');
      text(address1, 'address1');
      text(address2, 'address2');
      text(landmark, 'landmark');
      text(locality, 'locality');
      text(city, 'city');
      text(state, 'state');
      text(postcode, 'postcode');
      text(country, 'country');
      text(holder, 'holder');
      text(bankName, 'bank_name');
      text(account, 'account');
      text(accountConfirm, 'account_confirm');
      text(ifsc, 'ifsc');
      text(branch, 'branch');
      text(upi, 'upi');
      final cuisineData = data['cuisines'];
      if (cuisineData is List) {
        selectedCuisines = cuisineData.map((e) => e.toString()).toSet();
      }
      final serviceData = data['services'];
      if (serviceData is List) {
        serviceTypes = serviceData.map((e) => e.toString()).toSet();
      }
      step = (data['step'] as num?)?.toInt().clamp(0, 3) ?? step;
      businessType = data['business_type']?.toString() ?? businessType;
      gstStatus = data['gst_status']?.toString() ?? gstStatus;
      fssaiStatus = data['fssai_status']?.toString() ?? fssaiStatus;
      prepTime = data['prep_time']?.toString() ?? prepTime;
      accountType = data['account_type']?.toString() ?? accountType;
      settlement = data['settlement']?.toString() ?? settlement;
      opening = data['opening']?.toString() ?? opening;
      closing = data['closing']?.toString() ?? closing;
      phoneVerified = data['phone_verified'] == true;
      emailVerified = data['email_verified'] == true;
      bankConsent = data['bank_consent'] == true;
      declaration = data['declaration'] == true;
      agreement = data['agreement'] == true;
      latitude = (data['latitude'] as num?)?.toDouble();
      longitude = (data['longitude'] as num?)?.toDouble();
      matchedAreaId = (data['matched_area_id'] as num?)?.toInt();
      matchedZoneName = data['matched_zone_name']?.toString();
      verifiedPhoneToken = data['verified_phone_token']?.toString();
      verifiedPhoneNumber = data['verified_phone_number']?.toString();
      logoFile = _file(data['logo']?.toString());
      coverFile = _file(data['cover']?.toString());
      gstFile = _file(data['gst_file']?.toString());
      fssaiFile = _file(data['fssai_file']?.toString());
      panFile = _file(data['pan_file']?.toString());
      bankProofFile = _file(data['bank_proof_file']?.toString());
    } catch (_) {}
  }

  File? _file(String? path) {
    if (path == null || path.isEmpty) return null;
    final f = File(path);
    return f.existsSync() ? f : null;
  }

  void _queueSave() {
    if (loading || !mounted) return;
    setState(() => saveStatus = 'Saving...');
    saveDebounce?.cancel();
    saveDebounce = Timer(const Duration(milliseconds: 650), _saveDraft);
  }

  Future<void> _saveDraft() async {
    if (saving || loading || !mounted) return;
    setState(() => saving = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(draftKey, jsonEncode(_draft()));
      if (mounted) setState(() => saveStatus = 'Saved');
    } catch (_) {
      if (mounted) setState(() => saveStatus = 'Save failed - retry');
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Map<String, dynamic> _draft() => {
        'step': step,
        'restaurant_name': restaurantName.text,
        'cuisines': selectedCuisines.toList(),
        'description': description.text,
        'owner_name': ownerName.text,
        'phone': phone.text,
        'email': email.text,
        'password': password.text,
        'confirm_password': confirmPassword.text,
        'legal_name': legalName.text,
        'year': year.text,
        'gst_status': gstStatus,
        'gst_number': gstNumber.text,
        'fssai_status': fssaiStatus,
        'fssai_number': fssaiNumber.text,
        'pan_number': panNumber.text,
        'business_type': businessType,
        'services': serviceTypes.toList(),
        'prep_time': prepTime,
        'min_order': minOrder.text,
        'address_search': addressSearch.text,
        'address1': address1.text,
        'address2': address2.text,
        'landmark': landmark.text,
        'locality': locality.text,
        'city': city.text,
        'state': state.text,
        'postcode': postcode.text,
        'country': country.text,
        'latitude': latitude,
        'longitude': longitude,
        'matched_area_id': matchedAreaId,
        'matched_zone_name': matchedZoneName,
        'opening': opening,
        'closing': closing,
        'holder': holder.text,
        'bank_name': bankName.text,
        'account': account.text,
        'account_confirm': accountConfirm.text,
        'ifsc': ifsc.text,
        'branch': branch.text,
        'account_type': accountType,
        'upi': upi.text,
        'settlement': settlement,
        'phone_verified': phoneVerified,
        'email_verified': emailVerified,
        'bank_consent': bankConsent,
        'declaration': declaration,
        'agreement': agreement,
        'verified_phone_token': verifiedPhoneToken,
        'verified_phone_number': verifiedPhoneNumber,
        'logo': logoFile?.path,
        'cover': coverFile?.path,
        'gst_file': gstFile?.path,
        'fssai_file': fssaiFile?.path,
        'pan_file': panFile?.path,
        'bank_proof_file': bankProofFile?.path,
      };

  String _stripCountryCode(String value) {
    if (value.trim().isEmpty) return '';
    try {
      return PhoneNumberUtils.localMobile(
        value,
        countryCode: branding.defaultMobileCountryCode,
      );
    } on FormatException {
      return PhoneNumberUtils.sanitizedDigits(value);
    }
  }

  String _normalPhone(String value) => PhoneNumberUtils.normalizeMobile(
        value,
        countryCode: branding.defaultMobileCountryCode,
      ).normalizedNumber;

  Future<void> _pick(
    ValueChanged<File> setFile,
    List<String> extensions,
  ) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: extensions,
    );
    final path = result?.files.single.path;
    if (path == null || !mounted) return;
    setState(() => setFile(File(path)));
    _queueSave();
  }

  void _onGstinChanged(String value) {
    gstinDebounce?.cancel();
    final gstin = value.trim().toUpperCase();
    if (!RegExp(r'^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$')
        .hasMatch(gstin)) {
      setState(() => gstinResult = null);
      return;
    }
    gstinDebounce = Timer(const Duration(milliseconds: 700), () async {
      setState(() => checkingGstin = true);
      final result = await _verificationService.verifyGstin(
        gstin,
        businessName: legalName.text.trim().isNotEmpty
            ? legalName.text.trim()
            : restaurantName.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        checkingGstin = false;
        gstinResult = result;
      });
    });
  }

  void _onPanNumberChanged(String value) {
    panDebounce?.cancel();
    final pan = value.trim().toUpperCase();
    if (!RegExp(r'^[A-Z]{5}\d{4}[A-Z]{1}$').hasMatch(pan)) {
      setState(() => panResult = null);
      return;
    }
    panDebounce = Timer(const Duration(milliseconds: 700), () async {
      setState(() => checkingPan = true);
      final result = await _verificationService.verifyPan(
        pan,
        name: ownerName.text.trim().isNotEmpty ? ownerName.text.trim() : null,
      );
      if (!mounted) return;
      setState(() {
        checkingPan = false;
        panResult = result;
      });
    });
  }

  Future<void> _checkPanDocument(File file) async {
    setState(() {
      checkingPanDoc = true;
      panDocResult = null;
    });
    final result = await _verificationService.verifyPanDocument(file.path);
    if (!mounted) return;
    setState(() {
      checkingPanDoc = false;
      panDocResult = result;
    });
  }

  Widget verifyStatusLine(bool checking, DocVerifyResult? result) {
    if (checking) {
      return Padding(
        padding: const EdgeInsets.only(top: 6),
        child: Row(
          children: [
            const SizedBox(
              width: 13,
              height: 13,
              child: CircularProgressIndicator(strokeWidth: 2, color: orange),
            ),
            const SizedBox(width: 8),
            Text('Verifying with Cashfree...', style: t(12.5, color: muted)),
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
    switch (result.status) {
      case DocVerifyStatus.verified:
        icon = Icons.verified_rounded;
        color = green;
        text = 'Verified';
        break;
      case DocVerifyStatus.invalid:
        icon = Icons.error_rounded;
        color = red;
        text = result.message ?? 'Could not verify. Please check and retry.';
        break;
      default:
        icon = Icons.info_rounded;
        color = muted;
        text = result.message ?? 'Could not verify right now.';
    }
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 15),
          const SizedBox(width: 6),
          Expanded(
              child: Text(text,
                  style: t(12.5, weight: FontWeight.w600, color: color))),
        ],
      ),
    );
  }

  Future<void> _verifyPhone() async {
    if (sendingOtp) return;
    if (phone.text.trim().isEmpty)
      return _toast('Enter a mobile number first.', true);
    setState(() => sendingOtp = true);
    try {
      final auth = context.read<AuthProvider>();
      final normalized = _normalPhone(phone.text.trim());
      final status = await auth.getPhoneStatus(
        phone: normalized,
        role: 'restaurant',
      );
      if (!mounted) return;
      if (status == null) {
        return _toast(
          auth.error ?? 'Unable to validate this mobile number.',
          true,
        );
      }
      if (status['exists'] == true) {
        return _toast(
          'A restaurant account already exists with this mobile number. Please sign in.',
          true,
        );
      }
      String? verificationId;
      if (branding.usesFirebasePhoneAuth) {
        verificationId = await _firebasePhoneAuthService.sendOtp(
          phone: normalized,
          countryCode: branding.defaultMobileCountryCode,
        );
      } else {
        final sent = await auth.sendLoginOtp(
          phone: normalized,
          flow: 'signup',
          role: 'restaurant',
        );
        if (!sent) return _toast(auth.error ?? 'Failed to send OTP', true);
      }
      final result = await Navigator.of(context).push<Map<String, dynamic>>(
        MaterialPageRoute(
          builder: (_) => OtpVerificationScreen(
            phoneNumber: normalized,
            countryCode: branding.defaultMobileCountryCode,
            appName: branding.displayName,
            role: 'restaurant',
            flow: 'signup',
            useFirebasePhoneAuth: branding.usesFirebasePhoneAuth,
            otpServiceProvider: branding.resolvedOtpServiceProvider,
            initialFirebaseVerificationId: verificationId,
          ),
        ),
      );
      if (!mounted || result == null) return;
      setState(() {
        phoneVerified = true;
        verifiedPhoneToken = result['verified_phone_token']?.toString();
        verifiedPhoneNumber = result['phone']?.toString() ?? normalized;
        phone.text = _stripCountryCode(verifiedPhoneNumber!);
      });
      _toast('Phone number verified.', false);
    } catch (e) {
      _toast(e.toString().replaceFirst('Exception: ', ''), true);
    } finally {
      if (mounted) setState(() => sendingOtp = false);
    }
  }

  void _verifyEmail() {
    if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email.text.trim())) {
      _toast('Enter a valid email address.', true);
      return;
    }
    setState(() => emailVerified = true);
    _toast('Email marked as verified.', false);
    _queueSave();
  }

  Future<void> _useLocation() async {
    setState(() => locating = true);
    try {
      final pos = await _locationService.getCurrentLocation();
      if (pos == null)
        return _toast('Location unavailable. Enter address manually.', true);
      await _applyLocation(pos.latitude, pos.longitude);
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  void _searchChanged(String value) {
    searchDebounce?.cancel();
    if (value.trim().length < 3) {
      setState(() => suggestions = const []);
      return;
    }
    searchDebounce = Timer(const Duration(milliseconds: 450), () async {
      setState(() => searching = true);
      final result = await _locationService.searchPlaces(value.trim());
      if (mounted) {
        setState(() {
          suggestions = result;
          searching = false;
        });
      }
    });
  }

  Future<void> _confirmAddress() async {
    final query = [
      addressSearch.text,
      address1.text,
      locality.text,
      city.text,
      postcode.text,
    ].where((v) => v.trim().isNotEmpty).join(', ');
    if (query.isEmpty)
      return _toast('Enter the restaurant address first.', true);
    setState(() => locating = true);
    try {
      final loc = await _locationService.getLocationFromAddress(query);
      final lat = _toDouble(loc?['lat']);
      final lng = _toDouble(loc?['lng']);
      if (lat == null || lng == null) {
        return _toast('We could not confirm this address on the map.', true);
      }
      await _applyLocation(lat, lng, loc);
    } finally {
      if (mounted) setState(() => locating = false);
    }
  }

  Future<void> _selectSuggestion(Map<String, dynamic> item) async {
    final lat = _toDouble(item['lat']);
    final lng = _toDouble(item['lng']);
    if (lat == null || lng == null) return;
    setState(() {
      suggestions = const [];
      addressSearch.text = item['display_name']?.toString() ?? '';
    });
    await _applyLocation(lat, lng, item);
  }

  Future<void> _applyLocation(
    double lat,
    double lng, [
    Map<String, dynamic>? fallback,
  ]) async {
    final resolved = await _locationService.getAddressFromLatLng(lat, lng);
    if (!mounted) return;
    setState(() {
      latitude = lat;
      longitude = lng;
      address1.text = _first(resolved?['address'], fallback?['display_name']) ??
          address1.text;
      city.text = _first(resolved?['city'], fallback?['city']) ?? city.text;
      state.text = resolved?['state'] ?? state.text;
      postcode.text =
          _first(resolved?['pincode'], fallback?['pincode']) ?? postcode.text;
    });
    _detectZone();
    _toast(
      matchedZoneName == null
          ? 'Location confirmed on map.'
          : 'Delivery service is available at this location.',
      false,
    );
    _queueSave();
  }

  String? _first(String? value, dynamic fallback) {
    if (value != null && value.trim().isNotEmpty) return value.trim();
    final other = fallback?.toString().trim();
    return other != null && other.isNotEmpty ? other : null;
  }

  double? _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse('${value ?? ''}');
  }

  void _detectZone() {
    if (latitude == null || longitude == null) return;
    final cityText = city.text.trim().toLowerCase();
    final localText = locality.text.trim().toLowerCase();
    Map<String, dynamic>? match;
    for (final area in deliveryAreas) {
      final haystack = [
        area['name'],
        area['title'],
        area['area_name'],
        area['city'],
      ].map((v) => v?.toString().toLowerCase() ?? '').join(' ');
      if ((cityText.isNotEmpty && haystack.contains(cityText)) ||
          (localText.isNotEmpty && haystack.contains(localText))) {
        match = area;
        break;
      }
    }
    match ??= deliveryAreas.isNotEmpty ? deliveryAreas.first : null;
    setState(() {
      matchedAreaId = (match?['id'] as num?)?.toInt();
      matchedZoneName = match == null
          ? (city.text.trim().isEmpty ? null : '${city.text.trim()} Coverage')
          : (match['name'] ??
                  match['title'] ??
                  match['area_name'] ??
                  match['city'] ??
                  'Delivery Zone')
              .toString();
    });
  }

  Future<void> _pickTime(bool open) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: const TimeOfDay(hour: 10, minute: 0),
    );
    if (picked == null) return;
    setState(
      () => open
          ? opening = picked.format(context)
          : closing = picked.format(context),
    );
    _queueSave();
  }

  bool _validPassword(String value) =>
      value.length >= 8 &&
      RegExp(r'[A-Z]').hasMatch(value) &&
      RegExp(r'[a-z]').hasMatch(value) &&
      RegExp(r'\d').hasMatch(value) &&
      RegExp(r'[^A-Za-z0-9]').hasMatch(value);

  bool _validate(int s) {
    if (s == 0) {
      if (restaurantName.text.trim().length < 2)
        return _fail('Enter a valid restaurant name.');
      if (selectedCuisines.isEmpty)
        return _fail('Select at least one cuisine.');
      if (description.text.trim().isEmpty || description.text.length > 160)
        return _fail('Write a short description within 160 characters.');
      if (logoFile == null) return _fail('Upload your restaurant logo.');
      if (coverFile == null)
        return _fail('Upload your restaurant cover image.');
      if (ownerName.text.trim().length < 2)
        return _fail('Enter owner or manager name.');
      if (!phoneVerified) return _fail('Verify your phone number.');
      if (!emailVerified) return _fail('Verify your email address.');
      if (!_validPassword(password.text))
        return _fail('Create a stronger password.');
      if (password.text != confirmPassword.text)
        return _fail('The passwords do not match.');
    }
    if (s == 1) {
      final y = int.tryParse(year.text.trim());
      if (legalName.text.trim().length < 2)
        return _fail('Enter registered business name.');
      if (y == null || y < 1900 || y > DateTime.now().year)
        return _fail('Select a valid established year.');
      if (gstStatus == 'GST Registered' && gstNumber.text.trim().length < 10)
        return _fail('Enter a valid GST number.');
      if (fssaiStatus == 'Licence Available' && fssaiNumber.text.trim().isEmpty)
        return _fail('Enter FSSAI licence number.');
      if (serviceTypes.isEmpty)
        return _fail('Select at least one service type.');
      if (address1.text.trim().isEmpty ||
          city.text.trim().isEmpty ||
          postcode.text.trim().isEmpty)
        return _fail('Complete the restaurant address.');
      if (latitude == null || longitude == null)
        return _fail('Confirm your restaurant location on the map.');
    }
    if (s == 2) {
      if (holder.text.trim().length < 2)
        return _fail('Enter account holder name.');
      if (bankName.text.trim().isEmpty) return _fail('Select your bank.');
      if (account.text.trim().length < 6)
        return _fail('Enter a valid account number.');
      if (account.text.trim() != accountConfirm.text.trim())
        return _fail('The account numbers do not match.');
      if (ifsc.text.trim().length < 8) return _fail('Enter a valid IFSC code.');
      if (branch.text.trim().isEmpty) return _fail('Enter branch name.');
      if (bankProofFile == null) return _fail('Upload bank proof.');
      if (!bankConsent)
        return _fail('Confirm this bank account belongs to you.');
    }
    if (s == 3 && (!declaration || !agreement)) {
      return _fail('Accept the declarations before submitting.');
    }
    return true;
  }

  bool _fail(String msg) {
    _toast(msg, true);
    return false;
  }

  Future<void> _next() async {
    if (!_validate(step)) return;
    await _saveDraft();
    if (step == 3) return _submit();
    setState(() => step++);
  }

  void _back() {
    if (step == 0) {
      Navigator.maybePop(context);
    } else {
      setState(() => step--);
      _queueSave();
    }
  }

  Future<void> _submit() async {
    if (!_validate(0) || !_validate(1) || !_validate(2) || !_validate(3))
      return;
    _detectZone();
    setState(() => submitting = true);
    try {
      final normalized = _normalPhone(phone.text.trim());
      final response = await _applicationService.submitApplication(
        fields: {
          'partner_type': 'restaurant',
          'business_name': restaurantName.text.trim(),
          'restaurant_name': restaurantName.text.trim(),
          'business_email': email.text.trim(),
          'business_phone': normalized,
          'owner_name': ownerName.text.trim(),
          'contact_name': ownerName.text.trim(),
          'contact_email': email.text.trim(),
          'contact_phone': normalized,
          'password': password.text,
          'password_confirmation': confirmPassword.text,
          'cuisine': selectedCuisines.join(', '),
          'restaurant_categories': selectedCuisines.join('|'),
          'short_description': description.text.trim(),
          'business_type': businessType,
          'legal_business_name': legalName.text.trim(),
          'established_year': year.text.trim(),
          'gst_status': gstStatus,
          'gst_number': gstNumber.text.trim().toUpperCase(),
          'fssai_status': fssaiStatus,
          'fssai_license_number': fssaiNumber.text.trim(),
          'pan_number': panNumber.text.trim().toUpperCase(),
          'service_type': serviceTypes.join(', '),
          'preparation_time': prepTime,
          'minimum_order_amount': minOrder.text.trim(),
          'address': formattedAddress,
          'address_line_1': address1.text.trim(),
          'address_line_2': address2.text.trim(),
          'landmark': landmark.text.trim(),
          'locality': locality.text.trim(),
          'city': city.text.trim(),
          'state': state.text.trim(),
          'pincode': postcode.text.trim(),
          'country': country.text.trim(),
          'latitude': latitude?.toString() ?? '',
          'longitude': longitude?.toString() ?? '',
          'area_id': (matchedAreaId ?? '').toString(),
          'delivery_zone_name': matchedZoneName ?? '',
          'opening_time': opening,
          'closing_time': closing,
          'bank_holder_name': holder.text.trim(),
          'bank_name': bankName.text.trim(),
          'bank_account_number': account.text.trim(),
          'bank_ifsc': ifsc.text.trim().toUpperCase(),
          'bank_branch': branch.text.trim(),
          'account_type': accountType,
          'upi_id': upi.text.trim(),
          'settlement_preference': settlement,
          'phone_verified': phoneVerified ? '1' : '0',
          'email_verified': emailVerified ? '1' : '0',
          'verified_phone_token': verifiedPhoneToken ?? '',
          'terms': '1',
          'bank_consent': bankConsent ? '1' : '0',
          'declaration': declaration ? '1' : '0',
        },
        files: {
          if (logoFile != null) 'logo': logoFile!.path,
          if (coverFile != null) 'cover_image': coverFile!.path,
          if (gstFile != null) 'gst_certificate': gstFile!.path,
          if (fssaiFile != null) 'fssai_license': fssaiFile!.path,
          if (panFile != null) 'pan_card': panFile!.path,
          if (bankProofFile != null) 'bank_proof': bankProofFile!.path,
        },
      );
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(draftKey);
      if (!mounted) return;
      Navigator.pushReplacementNamed(
        context,
        '/application-status',
        arguments: response['data']?['application_number']?.toString(),
      );
    } catch (e) {
      _toast('Could not submit application: $e', true);
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  String get formattedAddress => [
        address1.text,
        address2.text,
        landmark.text,
        locality.text,
        city.text,
        state.text,
        postcode.text,
        country.text,
      ].where((v) => v.trim().isNotEmpty).join(', ');

  String _fileLabel(File? f) {
    if (f == null) return 'Missing';
    final parts = f.path.split(RegExp(r'[\\/]'));
    return parts.isEmpty ? 'Uploaded' : parts.last;
  }

  String _maskedAccount() {
    final value = account.text.replaceAll(RegExp(r'\s+'), '');
    if (value.length <= 4) return '****';
    return '**** **** ${value.substring(value.length - 4)}';
  }

  String _maskedUpi() {
    final value = upi.text.trim();
    if (!value.contains('@')) return value.isEmpty ? 'Not provided' : '****';
    final parts = value.split('@');
    final prefix =
        parts.first.length <= 3 ? '***' : '${parts.first.substring(0, 3)}***';
    return '$prefix@${parts.last}';
  }

  void _toast(String message, bool isError) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message, style: t(14, color: Colors.white)),
        backgroundColor: isError ? red : ink,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  TextStyle t(
    double size, {
    FontWeight weight = FontWeight.w400,
    Color color = body,
  }) =>
      GoogleFonts.inter(
        fontSize: size,
        fontWeight: weight,
        color: color,
        height: 1.28,
      );

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(
        backgroundColor: bg,
        body: Center(child: CircularProgressIndicator(color: orange)),
      );
    }
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            _header(),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 12, 24, 16),
              child: _stepper(),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
                child: Center(child: _card()),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 6),
      child: Row(
        children: [
          IconButton(
            onPressed: _back,
            icon: const Icon(Icons.arrow_back_rounded, size: 30),
          ),
          Expanded(
            child: Column(
              children: [
                Text(
                  'Register Your Restaurant',
                  textAlign: TextAlign.center,
                  style: t(23, weight: FontWeight.w700, color: Colors.black),
                ),
                const SizedBox(height: 7),
                Text(
                  'Create your restaurant profile and start receiving orders',
                  textAlign: TextAlign.center,
                  style: t(16, weight: FontWeight.w500, color: muted),
                ),
                const SizedBox(height: 4),
                Text(
                  saveStatus,
                  style: t(
                    11,
                    weight: FontWeight.w700,
                    color: saveStatus.startsWith('Save failed') ? red : green,
                  ),
                ),
              ],
            ),
          ),
          Column(
            children: [
              IconButton(
                onPressed: () => _toast(
                  'Support will help you complete registration.',
                  false,
                ),
                icon: const Icon(Icons.help_outline_rounded, size: 28),
              ),
              Text(
                'Help',
                style: t(13, weight: FontWeight.w600, color: Colors.black),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _stepper() {
    return Row(
      children: List.generate(steps.length * 2 - 1, (i) {
        if (i.isOdd) {
          final done = i ~/ 2 < step;
          return Expanded(
            child: Container(
              height: 1.5,
              margin: const EdgeInsets.only(bottom: 25),
              color: done ? orange : const Color(0xFFD0D5DD),
            ),
          );
        }
        final index = i ~/ 2;
        final done = index < step;
        final active = index == step;
        return SizedBox(
          width: 76,
          child: Column(
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: active ? orange : Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: done || active ? orange : const Color(0xFFD0D5DD),
                    width: 1.4,
                  ),
                ),
                child: done
                    ? const Icon(Icons.check_rounded, color: orange, size: 22)
                    : Text(
                        '${index + 1}',
                        style: t(
                          16,
                          weight: FontWeight.w700,
                          color: active ? Colors.white : muted,
                        ),
                      ),
              ),
              const SizedBox(height: 9),
              Text(
                steps[index],
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: t(
                  13,
                  weight: active ? FontWeight.w700 : FontWeight.w500,
                  color: active ? orange : muted,
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _card() {
    final heads = [
      (
        'Restaurant Information',
        "Let's start with some basic information about your restaurant.",
      ),
      (
        'Business Information',
        'Tell us more about your business, location and operations.',
      ),
      ('Bank Details', 'Add your bank details to receive payments securely.'),
      (
        'Review & Confirm',
        'Please review your details and confirm to submit your application.',
      ),
    ];
    return Container(
      constraints: const BoxConstraints(maxWidth: 900),
      padding: const EdgeInsets.fromLTRB(26, 24, 26, 24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(.035),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      heads[step].$1,
                      style: t(
                        23,
                        weight: FontWeight.w700,
                        color: Colors.black,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(heads[step].$2, style: t(15, color: muted)),
                  ],
                ),
              ),
              _miniIllustration(
                step == 2
                    ? Icons.account_balance_rounded
                    : Icons.storefront_rounded,
                step == 2 ? green : orange,
              ),
            ],
          ),
          const SizedBox(height: 24),
          if (step == 0) _basic(),
          if (step == 1) _business(),
          if (step == 2) _bank(),
          if (step == 3) _preview(),
          const SizedBox(height: 24),
          const Divider(color: border),
          const SizedBox(height: 20),
          _actions(),
        ],
      ),
    );
  }

  Widget _miniIllustration(IconData icon, Color color) {
    return Container(
      width: 88,
      height: 62,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withOpacity(.09),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Icon(icon, color: color, size: 38),
    );
  }

  Widget _basic() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        label(
          'Restaurant Name',
          req: true,
          child: input(
            restaurantName,
            'Enter restaurant name',
            Icons.storefront_outlined,
          ),
        ),
        gap(),
        label(
          'Cuisine Type',
          req: true,
          helper: 'Select all cuisines offered by your restaurant',
          child: _cuisineChecklist(),
        ),
        gap(),
        label(
          'Short Description',
          req: true,
          child: input(
            description,
            'Write a short description about your restaurant',
            Icons.description_outlined,
            maxLines: 4,
            maxLength: 160,
          ),
        ),
        gap(),
        label(
          'Restaurant Logo',
          req: true,
          helper: 'This will represent your restaurant on the app',
          child: upload(
            'Upload Logo',
            'JPG, PNG or WEBP (Max. 2MB)',
            Icons.add_rounded,
            logoFile,
            () => _pick((f) => logoFile = f, const [
              'jpg',
              'jpeg',
              'png',
              'webp',
            ]),
            () => setState(() => logoFile = null),
          ),
        ),
        gap(),
        label(
          'Restaurant Cover Image',
          req: true,
          helper: 'Showcase your restaurant with a beautiful image',
          child: upload(
            'Upload Cover Image',
            'JPG, PNG or WEBP (Recommended 1200x600px)',
            Icons.image_outlined,
            coverFile,
            () => _pick((f) => coverFile = f, const [
              'jpg',
              'jpeg',
              'png',
              'webp',
            ]),
            () => setState(() => coverFile = null),
          ),
        ),
        gap(),
        wrapFields([
          label(
            'Owner / Manager Name',
            req: true,
            child: input(
              ownerName,
              'Enter owner or manager name',
              Icons.person_outline_rounded,
            ),
          ),
          label(
            'Phone Number',
            req: true,
            child: input(
              phone,
              'Enter phone number',
              Icons.phone_outlined,
              keyboard: TextInputType.phone,
              prefix: '+91  ',
              suffix: verifyButton(phoneVerified, sendingOtp, _verifyPhone),
            ),
          ),
          label(
            'Email Address',
            req: true,
            child: input(
              email,
              'Enter email address',
              Icons.mail_outline_rounded,
              keyboard: TextInputType.emailAddress,
              suffix: verifyButton(emailVerified, false, _verifyEmail),
            ),
          ),
          label(
            'Password',
            req: true,
            child: input(
              password,
              'Create a strong password',
              Icons.lock_outline_rounded,
              obscure: !showPassword,
              suffix: IconButton(
                onPressed: () => setState(() => showPassword = !showPassword),
                icon: Icon(
                  showPassword
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  color: muted,
                ),
              ),
            ),
          ),
          label(
            'Confirm Password',
            req: true,
            child: input(
              confirmPassword,
              'Confirm your password',
              Icons.lock_outline_rounded,
              obscure: !showConfirmPassword,
              suffix: IconButton(
                onPressed: () =>
                    setState(() => showConfirmPassword = !showConfirmPassword),
                icon: Icon(
                  showConfirmPassword
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                  color: muted,
                ),
              ),
            ),
          ),
        ]),
        notice(
          Icons.verified_user_outlined,
          'Secure & Safe',
          'Your information is safe with us and will never be shared.',
          orange,
        ),
      ],
    );
  }

  Widget _cuisineChecklist() {
    if (loadingCuisines) {
      return const SizedBox(
        height: 76,
        child: Center(
          child: CircularProgressIndicator(strokeWidth: 2.4),
        ),
      );
    }

    if (cuisineLoadError != null) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: red.withOpacity(.05),
          border: Border.all(color: red.withOpacity(.28)),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline_rounded, color: red, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                cuisineLoadError!,
                style: t(12, weight: FontWeight.w600, color: body),
              ),
            ),
            TextButton(
              onPressed: _loadCuisines,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (cuisines.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: bg,
          border: Border.all(color: border),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          'No active cuisines are available. Ask the administrator to add or enable cuisines.',
          style: t(13, weight: FontWeight.w600, color: muted),
        ),
      );
    }

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: List.generate(cuisines.length, (index) {
          final cuisine = cuisines[index];
          final selected = selectedCuisines.contains(cuisine);
          return Column(
            children: [
              CheckboxListTile(
                value: selected,
                onChanged: (checked) {
                  setState(() {
                    if (checked == true) {
                      selectedCuisines.add(cuisine);
                    } else {
                      selectedCuisines.remove(cuisine);
                    }
                  });
                  _queueSave();
                },
                activeColor: orange,
                checkColor: Colors.white,
                controlAffinity: ListTileControlAffinity.leading,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                visualDensity: const VisualDensity(
                  horizontal: -2,
                  vertical: -2,
                ),
                title: Text(
                  cuisine,
                  style: t(
                    13,
                    weight: selected ? FontWeight.w700 : FontWeight.w600,
                    color: selected ? ink : body,
                  ),
                ),
              ),
              if (index < cuisines.length - 1)
                const Divider(height: 1, color: border),
            ],
          );
        }),
      ),
    );
  }

  Widget _business() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        sectionLabel('Business Type', true),
        const SizedBox(height: 10),
        cards([
          option(
            'Restaurant',
            'Food delivered to customers',
            Icons.storefront_outlined,
            businessType == 'Restaurant',
            orange,
            () => setState(() => businessType = 'Restaurant'),
          ),
          option(
            'Cloud Kitchen',
            'Delivery only, no dine-in',
            Icons.shopping_bag_outlined,
            businessType == 'Cloud Kitchen',
            orange,
            () => setState(() => businessType = 'Cloud Kitchen'),
          ),
          option(
            'Cafe / Bakery',
            'Cafe, bakery or beverage store',
            Icons.room_service_outlined,
            businessType == 'Cafe / Bakery',
            green,
            () => setState(() => businessType = 'Cafe / Bakery'),
          ),
        ]),
        gap(),
        wrapFields([
          label(
            'Established Year',
            req: true,
            child: dropdown(
              year.text.isEmpty ? null : year.text,
              'Select year',
              Icons.calendar_month_outlined,
              List.generate(
                DateTime.now().year - 1969,
                (i) => '${DateTime.now().year - i}',
              ),
              (v) => setState(() => year.text = v ?? ''),
            ),
          ),
          label(
            'Legal Business Name',
            req: true,
            child: input(
              legalName,
              'Enter registered business name',
              Icons.person_outline_rounded,
            ),
          ),
          label(
            'GST Registration Status',
            req: true,
            child: dropdown(
              gstStatus,
              'Select GST status',
              Icons.percent_rounded,
              const [
                'GST Registered',
                'Not GST Registered',
                'Registration in Progress',
              ],
              (v) => setState(() => gstStatus = v ?? gstStatus),
            ),
          ),
          if (gstStatus == 'GST Registered')
            label(
              'GST Number',
              req: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  input(
                    gstNumber,
                    'Enter GST number',
                    Icons.percent_rounded,
                    caps: TextCapitalization.characters,
                    onChanged: _onGstinChanged,
                  ),
                  verifyStatusLine(checkingGstin, gstinResult),
                ],
              ),
            ),
        ]),
        if (gstStatus == 'GST Registered') ...[
          const SizedBox(height: 12),
          upload(
            'Upload GST Certificate',
            'JPG, PNG or PDF (Max. 5MB)',
            Icons.upload_file_rounded,
            gstFile,
            () =>
                _pick((f) => gstFile = f, const ['jpg', 'jpeg', 'png', 'pdf']),
            () => setState(() => gstFile = null),
          ),
        ],
        gap(),
        wrapFields([
          label(
            'FSSAI Status',
            req: true,
            child: dropdown(
              fssaiStatus,
              'Select FSSAI status',
              Icons.health_and_safety_outlined,
              const ['Licence Available', 'Licence Applied', 'Not Available'],
              (v) => setState(() => fssaiStatus = v ?? fssaiStatus),
            ),
          ),
          if (fssaiStatus == 'Licence Available')
            label(
              'FSSAI License Number',
              req: true,
              child: input(
                fssaiNumber,
                'Enter FSSAI license number',
                Icons.shield_outlined,
              ),
            ),
          label(
            'PAN Number',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                input(
                  panNumber,
                  'Enter PAN number',
                  Icons.badge_outlined,
                  caps: TextCapitalization.characters,
                  onChanged: _onPanNumberChanged,
                ),
                verifyStatusLine(checkingPan, panResult),
              ],
            ),
          ),
          label(
            'PAN Card',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                upload(
                  'Upload PAN Card',
                  'JPG, PNG or PDF (Max. 5MB)',
                  Icons.upload_file_rounded,
                  panFile,
                  () => _pick((f) {
                    panFile = f;
                    _checkPanDocument(f);
                  }, const ['jpg', 'jpeg', 'png', 'pdf']),
                  () => setState(() {
                    panFile = null;
                    panDocResult = null;
                  }),
                  compact: true,
                ),
                verifyStatusLine(checkingPanDoc, panDocResult),
              ],
            ),
          ),
        ]),
        if (fssaiStatus == 'Licence Available') ...[
          const SizedBox(height: 12),
          upload(
            'Upload FSSAI License',
            'JPG, PNG or PDF (Max. 5MB)',
            Icons.upload_file_rounded,
            fssaiFile,
            () => _pick((f) => fssaiFile = f, const [
              'jpg',
              'jpeg',
              'png',
              'pdf',
            ]),
            () => setState(() => fssaiFile = null),
          ),
        ],
        gap(),
        sectionLabel('Service Type', true),
        const SizedBox(height: 10),
        cards(
          ['Dine-in', 'Delivery', 'Takeaway'].map((type) {
            final icon = type == 'Delivery'
                ? Icons.delivery_dining_rounded
                : type == 'Takeaway'
                    ? Icons.shopping_bag_outlined
                    : Icons.table_restaurant_outlined;
            final desc = type == 'Delivery'
                ? 'Online delivery orders enabled'
                : type == 'Takeaway'
                    ? 'Pickup orders from the store'
                    : 'Customers can dine in at restaurant';
            return option(
              type,
              desc,
              icon,
              serviceTypes.contains(type),
              type == 'Delivery' ? green : orange,
              () {
                setState(
                  () => serviceTypes.contains(type)
                      ? serviceTypes.remove(type)
                      : serviceTypes.add(type),
                );
                _queueSave();
              },
            );
          }).toList(),
        ),
        gap(),
        wrapFields([
          label(
            'Average Preparation Time',
            req: true,
            child: dropdown(
              prepTime,
              'Select time',
              Icons.schedule_outlined,
              const [
                '10-15 minutes',
                '15-20 minutes',
                '20-25 minutes',
                '25-30 minutes',
                '30-45 minutes',
                '45-60 minutes',
              ],
              (v) => setState(() => prepTime = v ?? prepTime),
            ),
          ),
          label(
            'Minimum Order Amount',
            child: input(
              minOrder,
              'Enter amount (INR)',
              Icons.shopping_bag_outlined,
              keyboard: TextInputType.number,
            ),
          ),
        ]),
        gap(),
        label(
          'Search Address',
          req: true,
          child: Column(
            children: [
              input(
                addressSearch,
                'Search address or landmark',
                Icons.location_on_outlined,
                onChanged: _searchChanged,
                suffix: searching
                    ? const Padding(
                        padding: EdgeInsets.all(14),
                        child: SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: orange,
                          ),
                        ),
                      )
                    : IconButton(
                        onPressed: _confirmAddress,
                        icon: const Icon(
                          Icons.arrow_forward_ios_rounded,
                          size: 18,
                          color: body,
                        ),
                      ),
              ),
              if (suggestions.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(top: 8),
                  decoration: BoxDecoration(
                    border: Border.all(color: border),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: suggestions
                        .take(4)
                        .map(
                          (s) => ListTile(
                            dense: true,
                            leading: const Icon(
                              Icons.place_outlined,
                              color: orange,
                              size: 20,
                            ),
                            title: Text(
                              s['display_name']?.toString() ?? 'Address',
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: t(13, weight: FontWeight.w500),
                            ),
                            onTap: () => _selectSuggestion(s),
                          ),
                        )
                        .toList(),
                  ),
                ),
            ],
          ),
        ),
        gap(),
        wrapFields([
          label(
            'Address Line 1',
            req: true,
            child: input(
              address1,
              'Shop number, building, street',
              Icons.place_outlined,
            ),
          ),
          label(
            'Address Line 2',
            child: input(
              address2,
              'Area, floor or nearby road',
              Icons.map_outlined,
            ),
          ),
          label(
            'Landmark',
            child: input(landmark, 'Enter landmark', Icons.flag_outlined),
          ),
          label(
            'Locality',
            req: true,
            child: input(
              locality,
              'Enter locality',
              Icons.location_city_outlined,
            ),
          ),
          label(
            'City',
            req: true,
            child: input(city, 'Enter city', Icons.apartment_rounded),
          ),
          label(
            'State',
            req: true,
            child: input(state, 'Enter state', Icons.public_rounded),
          ),
          label(
            'Postcode',
            req: true,
            child: input(
              postcode,
              'Enter postcode',
              Icons.pin_drop_outlined,
              keyboard: TextInputType.number,
            ),
          ),
          label(
            'Country',
            req: true,
            child: input(country, 'Enter country', Icons.language_rounded),
          ),
        ]),
        const SizedBox(height: 16),
        Row(
          children: [
            Expanded(
              child: outline(
                'Use Current Location',
                Icons.my_location_rounded,
                locating ? null : _useLocation,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: outline(
                'Confirm Location',
                Icons.check_circle_outline_rounded,
                locating ? null : _confirmAddress,
              ),
            ),
          ],
        ),
        if (latitude != null && longitude != null) ...[
          const SizedBox(height: 16),
          notice(
            Icons.check_circle_outline_rounded,
            matchedZoneName == null
                ? 'Location confirmed on map'
                : 'Delivery service is available',
            matchedZoneName == null
                ? 'Coverage will be verified again before submission.'
                : 'Delivery Zone: $matchedZoneName\nStatus: Service Available',
            green,
          ),
        ],
        gap(),
        label(
          'Restaurant Timings',
          req: true,
          child: Row(
            children: [
              Expanded(
                child: timeChip('Opens', opening, () => _pickTime(true)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: timeChip('Closes', closing, () => _pickTime(false)),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _bank() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        label(
          'Account Holder Name',
          req: true,
          child: input(
            holder,
            'Enter account holder name',
            Icons.person_outline_rounded,
          ),
        ),
        gap(),
        wrapFields([
          label(
            'Bank Name',
            req: true,
            child: dropdown(
              bankName.text.trim().isEmpty ? null : bankName.text.trim(),
              'Select your bank',
              Icons.account_balance_outlined,
              banks,
              (v) => setState(() => bankName.text = v ?? ''),
            ),
          ),
          label(
            'Account Number',
            req: true,
            child: input(
              account,
              'Enter account number',
              Icons.credit_card_outlined,
              keyboard: TextInputType.number,
            ),
          ),
          label(
            'Confirm Account Number',
            req: true,
            child: input(
              accountConfirm,
              'Re-enter account number',
              Icons.credit_score_outlined,
              keyboard: TextInputType.number,
            ),
          ),
          label(
            'Account Type',
            req: true,
            child: dropdown(
              accountType,
              'Select account type',
              Icons.savings_outlined,
              const ['Savings Account', 'Current Account', 'Business Account'],
              (v) => setState(() => accountType = v ?? accountType),
            ),
          ),
          label(
            'IFSC Code',
            req: true,
            child: input(
              ifsc,
              'Enter IFSC code',
              Icons.shield_outlined,
              caps: TextCapitalization.characters,
              suffix: TextButton(
                onPressed: () {
                  if (branch.text.trim().isEmpty)
                    branch.text = bankName.text.trim().isEmpty
                        ? 'Main Branch'
                        : '${bankName.text.trim()} Main Branch';
                  _toast('IFSC lookup ready for verification.', false);
                },
                child: Text(
                  'Find IFSC',
                  style: t(13, weight: FontWeight.w700, color: orange),
                ),
              ),
            ),
          ),
          label(
            'Branch Name',
            req: true,
            child: input(branch, 'Enter branch name', Icons.place_outlined),
          ),
        ]),
        gap(),
        label(
          'UPI ID',
          helper: 'Optional for India',
          child: input(
            upi,
            'Enter UPI ID (e.g., name@okicici)',
            Icons.edit_outlined,
          ),
        ),
        gap(),
        label(
          'Upload Cancelled Cheque',
          req: true,
          helper:
              'Upload a clear image of your cancelled cheque with your name printed.',
          child: upload(
            'Upload Cancelled Cheque',
            'JPG, PNG or PDF (Max. 5MB)',
            Icons.article_outlined,
            bankProofFile,
            () => _pick((f) => bankProofFile = f, const [
              'jpg',
              'jpeg',
              'png',
              'pdf',
            ]),
            () => setState(() => bankProofFile = null),
          ),
        ),
        gap(),
        label(
          'Settlement Preference',
          child: dropdown(
            settlement,
            'Select payout cycle',
            Icons.payments_outlined,
            const ['Daily', 'Weekly', 'Twice monthly', 'Monthly'],
            (v) => setState(() => settlement = v ?? settlement),
          ),
        ),
        CheckboxListTile(
          value: bankConsent,
          onChanged: (v) {
            setState(() => bankConsent = v ?? false);
            _queueSave();
          },
          activeColor: orange,
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(
            'I confirm that this bank account belongs to me or my registered business.',
            style: t(14, weight: FontWeight.w500),
          ),
        ),
        const SizedBox(height: 10),
        notice(
          Icons.lock_outline_rounded,
          'Your Information is Safe',
          'We use bank-level security to protect your information.\nYour details will never be shared with anyone.',
          amber,
        ),
      ],
    );
  }

  Widget _preview() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        review(
          'Basic Information',
          Icons.storefront_outlined,
          orange,
          () => setState(() => step = 0),
          {
            'Restaurant Name': restaurantName.text,
            'Cuisine Type': selectedCuisines.join(', '),
            'Owner / Manager': ownerName.text,
            'Phone Number':
                '${phone.text} ${phoneVerified ? '(Verified)' : ''}',
            'Email Address':
                '${email.text} ${emailVerified ? '(Verified)' : ''}',
            'Short Description': description.text,
            'Logo': _fileLabel(logoFile),
            'Cover Image': _fileLabel(coverFile),
          },
        ),
        review(
          'Business Information',
          Icons.business_center_outlined,
          const Color(0xFF2E90FA),
          () => setState(() => step = 1),
          {
            'Business Type': businessType,
            'Legal Business Name': legalName.text,
            'Established Year': year.text,
            'GST Status': gstStatus,
            'GST Number': gstNumber.text.toUpperCase(),
            'FSSAI Status': fssaiStatus,
            'FSSAI License': fssaiNumber.text,
            'PAN Number': panNumber.text.isEmpty
                ? 'Not provided'
                : '******${panNumber.text.length >= 4 ? panNumber.text.substring(panNumber.text.length - 4).toUpperCase() : panNumber.text}',
            'Service Type': serviceTypes.join(', '),
            'Preparation Time': prepTime,
            'Minimum Order': minOrder.text.isEmpty
                ? 'Not configured'
                : 'INR ${minOrder.text}',
            'Timings': '$opening - $closing',
          },
        ),
        review(
          'Location & Delivery',
          Icons.location_on_outlined,
          green,
          () => setState(() => step = 1),
          {
            'Restaurant Address': formattedAddress,
            'Locality': locality.text,
            'City': city.text,
            'Postcode': postcode.text,
            'Location Status': latitude == null
                ? 'Not confirmed'
                : 'Location confirmed on map',
            'Delivery Zone': matchedZoneName ?? 'Pending check',
            'Delivery Availability':
                matchedZoneName == null ? 'Pending' : 'Service Available',
            'Delivery Model': serviceTypes.contains('Delivery')
                ? 'Platform Delivery and Self-Delivery'
                : 'Pickup / Dine-in',
          },
        ),
        review(
          'Bank Details',
          Icons.account_balance_outlined,
          green,
          () => setState(() => step = 2),
          {
            'Account Holder Name': holder.text,
            'Bank Name': bankName.text,
            'Account Number': _maskedAccount(),
            'IFSC Code': ifsc.text.toUpperCase(),
            'Branch Name': branch.text,
            'Account Type': accountType,
            'UPI ID': _maskedUpi(),
            'Bank Proof': _fileLabel(bankProofFile),
          },
        ),
        notice(
          Icons.verified_user_outlined,
          'Almost Done!',
          'Please review all the details carefully. Once submitted, our team will verify your information and get back to you within 24-48 hours.',
          green,
        ),
        CheckboxListTile(
          value: declaration,
          onChanged: (v) {
            setState(() => declaration = v ?? false);
            _queueSave();
          },
          activeColor: orange,
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(
            'I hereby confirm that all the information provided is correct and true to the best of my knowledge.',
            style: t(14, weight: FontWeight.w500),
          ),
        ),
        CheckboxListTile(
          value: agreement,
          onChanged: (v) {
            setState(() => agreement = v ?? false);
            _queueSave();
          },
          activeColor: orange,
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(
            'I agree to the Restaurant Partner Terms and Conditions, Privacy Policy, Settlement Policy and Commission Agreement.',
            style: t(14, weight: FontWeight.w500),
          ),
        ),
        Center(
          child: Text(
            'Your information is secure and encrypted',
            style: t(13, weight: FontWeight.w600, color: muted),
          ),
        ),
      ],
    );
  }

  Widget _actions() {
    final last = step == 3;
    return Row(
      children: [
        if (step > 0) ...[
          Expanded(
            child: action(
              'Back',
              Icons.arrow_back_rounded,
              false,
              submitting ? null : _back,
            ),
          ),
          const SizedBox(width: 14),
        ],
        Expanded(
          child: action(
            last ? 'Submit Application' : 'Continue',
            Icons.arrow_forward_rounded,
            true,
            submitting ? null : _next,
            loading: submitting,
          ),
        ),
      ],
    );
  }

  Widget gap() => const SizedBox(height: 18);

  Widget label(
    String labelText, {
    required Widget child,
    bool req = false,
    String? helper,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        sectionLabel(labelText, req),
        if (helper != null) ...[
          const SizedBox(height: 4),
          Text(helper, style: t(13, color: muted)),
        ],
        const SizedBox(height: 10),
        child,
      ],
    );
  }

  Widget sectionLabel(String text, bool req) => RichText(
        text: TextSpan(
          text: text,
          style: t(15, weight: FontWeight.w700, color: Colors.black),
          children: [
            if (req)
              const TextSpan(
                text: ' *',
                style: TextStyle(color: red),
              ),
          ],
        ),
      );

  Widget input(
    TextEditingController controller,
    String hint,
    IconData icon, {
    TextInputType? keyboard,
    int maxLines = 1,
    int? maxLength,
    bool obscure = false,
    Widget? suffix,
    String? prefix,
    ValueChanged<String>? onChanged,
    TextCapitalization caps = TextCapitalization.none,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboard,
      maxLines: maxLines,
      maxLength: maxLength,
      obscureText: obscure,
      onChanged: onChanged,
      textCapitalization: caps,
      style: t(15, weight: FontWeight.w500, color: ink),
      decoration: InputDecoration(
        counterStyle: t(12, weight: FontWeight.w600, color: muted),
        prefixIcon: Icon(icon, color: muted, size: 22),
        prefixText: prefix,
        suffixIcon: suffix,
        hintText: hint,
        hintStyle: t(
          15,
          weight: FontWeight.w500,
          color: const Color(0xFF98A2B3),
        ),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 18,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: orange, width: 1.2),
        ),
      ),
    );
  }

  Widget dropdown(
    String? value,
    String hint,
    IconData icon,
    List<String> items,
    ValueChanged<String?> onChanged,
  ) {
    return DropdownButtonFormField<String>(
      value: value != null && items.contains(value) ? value : null,
      items: items
          .map(
            (e) => DropdownMenuItem(
              value: e,
              child: Text(
                e,
                style: t(15, weight: FontWeight.w500, color: ink),
              ),
            ),
          )
          .toList(),
      onChanged: onChanged,
      icon: const Icon(Icons.keyboard_arrow_down_rounded, color: muted),
      decoration: InputDecoration(
        prefixIcon: Icon(icon, color: muted, size: 22),
        hintText: hint,
        hintStyle: t(
          15,
          weight: FontWeight.w500,
          color: const Color(0xFF98A2B3),
        ),
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 18,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: orange, width: 1.2),
        ),
      ),
    );
  }

  Widget verifyButton(bool verified, bool busy, VoidCallback onTap) {
    if (verified) return const Icon(Icons.verified_rounded, color: green);
    if (busy)
      return const Padding(
        padding: EdgeInsets.all(14),
        child: SizedBox(
          width: 18,
          height: 18,
          child: CircularProgressIndicator(strokeWidth: 2, color: orange),
        ),
      );
    return TextButton(
      onPressed: onTap,
      child: Text(
        'Verify',
        style: t(13, weight: FontWeight.w700, color: orange),
      ),
    );
  }

  Widget upload(
    String title,
    String subtitle,
    IconData icon,
    File? file,
    VoidCallback onTap,
    VoidCallback onRemove, {
    bool compact = false,
  }) {
    final name =
        file?.path.split(RegExp(r'[\\/]')).where((e) => e.isNotEmpty).last;
    return InkWell(
      borderRadius: BorderRadius.circular(13),
      onTap: onTap,
      child: Container(
        constraints: BoxConstraints(minHeight: compact ? 64 : 92),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: border, style: BorderStyle.solid),
        ),
        child: Row(
          children: [
            Container(
              width: compact ? 44 : 58,
              height: compact ? 44 : 58,
              decoration: BoxDecoration(
                color: orange.withOpacity(.09),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: orange, size: compact ? 22 : 28),
            ),
            const SizedBox(width: 18),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    name ?? title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: t(
                      compact ? 14 : 16,
                      weight: FontWeight.w700,
                      color: ink,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    name == null ? subtitle : 'Ready to upload',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: t(13, weight: FontWeight.w500, color: muted),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            if (name != null)
              IconButton(
                onPressed: onRemove,
                icon: const Icon(Icons.close_rounded, color: muted),
              )
            else
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 11,
                ),
                decoration: BoxDecoration(
                  color: orange.withOpacity(.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  'Upload',
                  style: t(14, weight: FontWeight.w700, color: orange),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget wrapFields(List<Widget> children) {
    return LayoutBuilder(
      builder: (context, c) {
        if (c.maxWidth < 680) {
          return Column(
            children: children
                .map(
                  (w) => Padding(
                    padding: const EdgeInsets.only(bottom: 18),
                    child: w,
                  ),
                )
                .toList(),
          );
        }
        return Wrap(
          spacing: 24,
          runSpacing: 18,
          children: children
              .map((w) => SizedBox(width: (c.maxWidth - 24) / 2, child: w))
              .toList(),
        );
      },
    );
  }

  Widget cards(List<Widget> children) {
    return LayoutBuilder(
      builder: (context, c) {
        final columns = c.maxWidth >= 760
            ? 3
            : c.maxWidth >= 520
                ? 2
                : 1;
        final width = (c.maxWidth - 16 * (columns - 1)) / columns;
        return Wrap(
          spacing: 16,
          runSpacing: 16,
          children:
              children.map((w) => SizedBox(width: width, child: w)).toList(),
        );
      },
    );
  }

  Widget option(
    String title,
    String desc,
    IconData icon,
    bool selected,
    Color accent,
    VoidCallback onTap,
  ) {
    return InkWell(
      borderRadius: BorderRadius.circular(13),
      onTap: onTap,
      child: Container(
        height: 142,
        padding: const EdgeInsets.all(15),
        decoration: BoxDecoration(
          color: selected ? accent.withOpacity(.06) : Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(
            color: selected ? orange : border,
            width: selected ? 1.2 : 1,
          ),
        ),
        child: Stack(
          children: [
            Align(
              alignment: Alignment.topRight,
              child: Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: selected ? orange : const Color(0xFFD0D5DD),
                    width: 2,
                  ),
                ),
                child: selected
                    ? Center(
                        child: Container(
                          width: 10,
                          height: 10,
                          decoration: const BoxDecoration(
                            color: orange,
                            shape: BoxShape.circle,
                          ),
                        ),
                      )
                    : null,
              ),
            ),
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    color: accent.withOpacity(.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, color: accent, size: 28),
                ),
                const SizedBox(height: 12),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: t(16, weight: FontWeight.w700, color: Colors.black),
                ),
                const SizedBox(height: 7),
                Text(
                  desc,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: t(13, weight: FontWeight.w500, color: muted),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget notice(IconData icon, String title, String message, Color color) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: color.withOpacity(.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: color.withOpacity(.12),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: color, size: 28),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: t(16, weight: FontWeight.w700, color: ink),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  style: t(13, weight: FontWeight.w500, color: body),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget timeChip(String label, String value, VoidCallback onTap) {
    return InkWell(
      borderRadius: BorderRadius.circular(13),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: border),
        ),
        child: Row(
          children: [
            const Icon(Icons.schedule_outlined, color: muted, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: t(12, weight: FontWeight.w600, color: muted),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    value,
                    style: t(15, weight: FontWeight.w700, color: ink),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget review(
    String title,
    IconData icon,
    Color color,
    VoidCallback onEdit,
    Map<String, String> rows,
  ) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: color.withOpacity(.12),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: color, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text(
                  title,
                  style: t(18, weight: FontWeight.w700, color: ink),
                ),
              ),
              TextButton.icon(
                onPressed: onEdit,
                icon: const Icon(Icons.edit_outlined, size: 18),
                label: const Text('Edit'),
                style: TextButton.styleFrom(
                  foregroundColor: orange,
                  textStyle: t(14, weight: FontWeight.w700, color: orange),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, c) {
              final columns = c.maxWidth >= 680 ? 2 : 1;
              final width = (c.maxWidth - 12 * (columns - 1)) / columns;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: rows.entries
                    .map(
                      (e) => SizedBox(
                        width: width,
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            SizedBox(
                              width: 145,
                              child: Text(
                                e.key,
                                style: t(
                                  13,
                                  weight: FontWeight.w600,
                                  color: body,
                                ),
                              ),
                            ),
                            Expanded(
                              child: Text(
                                e.value.trim().isEmpty
                                    ? 'Not provided'
                                    : e.value.trim(),
                                style: t(
                                  13,
                                  weight: FontWeight.w600,
                                  color: ink,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                    .toList(),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget action(
    String label,
    IconData icon,
    bool filled,
    VoidCallback? onTap, {
    bool loading = false,
  }) {
    final child = loading
        ? const SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: Colors.white,
            ),
          )
        : Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (!filled) Icon(icon, size: 22),
              if (!filled) const SizedBox(width: 10),
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              if (filled) const SizedBox(width: 14),
              if (filled) Icon(icon, size: 24),
            ],
          );
    return SizedBox(
      height: 58,
      child: filled
          ? ElevatedButton(
              onPressed: onTap,
              style: ElevatedButton.styleFrom(
                backgroundColor: orange,
                disabledBackgroundColor: orange.withOpacity(.55),
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                textStyle: t(17, weight: FontWeight.w700, color: Colors.white),
              ),
              child: child,
            )
          : OutlinedButton(
              onPressed: onTap,
              style: OutlinedButton.styleFrom(
                foregroundColor: orange,
                side: const BorderSide(color: orange),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                textStyle: t(17, weight: FontWeight.w700, color: orange),
              ),
              child: child,
            ),
    );
  }

  Widget outline(String label, IconData icon, VoidCallback? onTap) {
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: Icon(icon, size: 19),
      label: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
      style: OutlinedButton.styleFrom(
        foregroundColor: orange,
        side: const BorderSide(color: orange),
        minimumSize: const Size(0, 50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: t(13, weight: FontWeight.w700, color: orange),
      ),
    );
  }
}
