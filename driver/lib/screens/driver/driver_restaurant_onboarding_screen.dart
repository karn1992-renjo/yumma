import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:image_picker/image_picker.dart';

import '../../services/driver_restaurant_onboarding_service.dart';
import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class DriverRestaurantOnboardingScreen extends StatefulWidget {
  const DriverRestaurantOnboardingScreen({super.key});

  @override
  State<DriverRestaurantOnboardingScreen> createState() =>
      _DriverRestaurantOnboardingScreenState();
}

class _DriverRestaurantOnboardingScreenState
    extends State<DriverRestaurantOnboardingScreen> {
  final _service = DriverRestaurantOnboardingService();
  String _status = 'all';
  bool _loading = true;
  bool _starting = false;
  String? _error;
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _items = [];

  static const _statuses = [
    'all',
    'draft',
    'submitted',
    'correction_required',
    'approved',
    'activated',
    'rejected',
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        _service.summary(),
        _service.list(status: _status),
      ]);
      if (!mounted) return;
      setState(() {
        _summary = results[0] as Map<String, dynamic>;
        _items = List<Map<String, dynamic>>.from(results[1] as List);
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _startDraft() async {
    setState(() => _starting = true);
    try {
      final onboarding = await _service.createDraft();
      if (!mounted) return;
      final changed = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) =>
              DriverRestaurantOnboardingFormScreen(onboarding: onboarding),
        ),
      );
      if (changed == true || mounted) await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    } finally {
      if (mounted) setState(() => _starting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: foodflow.canvas,
      appBar: AppBar(
        title: const Text('Restaurant Onboarding'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _loading ? null : _load,
            icon: Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _starting ? null : _startDraft,
        icon: _starting
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Icon(Icons.add_business_outlined),
        label: const Text('Onboard'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading && _items.isEmpty
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                children: [
                  if (_error != null)
                    _ErrorPanel(message: _error!, onRetry: _load),
                  _SummaryPanel(summary: _summary),
                  const SizedBox(height: 14),
                  _StatusFilters(
                    selected: _status,
                    statuses: _statuses,
                    onChanged: (status) {
                      setState(() => _status = status);
                      _load();
                    },
                  ),
                  const SizedBox(height: 12),
                  if (_items.isEmpty)
                    const _EmptyPanel()
                  else
                    ..._items.map(
                      (item) => _OnboardingCard(
                        item: item,
                        onOpen: () => _openDetail(item),
                        onEdit: _canEdit(item) ? () => _openForm(item) : null,
                      ),
                    ),
                ],
              ),
      ),
    );
  }

  bool _canEdit(Map<String, dynamic> item) {
    final status = item['status']?.toString();
    return status == 'draft' || status == 'correction_required';
  }

  Future<void> _openForm(Map<String, dynamic> item) async {
    final id = _intValue(item['id']);
    if (id == null) return;
    final detail = await _service.show(id);
    if (!mounted) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) =>
            DriverRestaurantOnboardingFormScreen(onboarding: detail),
      ),
    );
    if (changed == true) await _load();
  }

  Future<void> _openDetail(Map<String, dynamic> item) async {
    final id = _intValue(item['id']);
    if (id == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => DriverRestaurantOnboardingDetailScreen(id: id),
      ),
    );
    if (changed == true) await _load();
  }
}

class DriverRestaurantOnboardingDetailScreen extends StatefulWidget {
  const DriverRestaurantOnboardingDetailScreen({super.key, required this.id});

  final int id;

  @override
  State<DriverRestaurantOnboardingDetailScreen> createState() =>
      _DriverRestaurantOnboardingDetailScreenState();
}

class _DriverRestaurantOnboardingDetailScreenState
    extends State<DriverRestaurantOnboardingDetailScreen> {
  final _service = DriverRestaurantOnboardingService();
  bool _loading = true;
  Map<String, dynamic>? _item;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final item = await _service.show(widget.id);
      if (!mounted) return;
      setState(() => _item = item);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = _item;
    return Scaffold(
      backgroundColor: foodflow.canvas,
      appBar: AppBar(title: const Text('Onboarding Detail')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : item == null
              ? _ErrorPanel(
                  message: _error ?? 'Unable to load onboarding.',
                  onRetry: _load)
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    _OnboardingCard(
                      item: item,
                      onOpen: () {},
                      onEdit: _canEdit(item) ? _edit : null,
                    ),
                    if ((item['correction_notes'] ?? '').toString().isNotEmpty)
                      _NoticePanel(
                        title: 'Correction Required',
                        body: item['correction_notes'].toString(),
                        color: Colors.orange,
                      ),
                    if ((item['rejection_reason'] ?? '').toString().isNotEmpty)
                      _NoticePanel(
                        title: 'Rejected',
                        body: item['rejection_reason'].toString(),
                        color: Colors.red,
                      ),
                    _VerificationPanel(verification: item['verification']),
                    const SizedBox(height: 16),
                    Text('Timeline', style: _sectionTitle),
                    const SizedBox(height: 8),
                    ..._timeline(item).map(_TimelineTile.new),
                  ],
                ),
    );
  }

  bool _canEdit(Map<String, dynamic> item) {
    final status = item['status']?.toString();
    return status == 'draft' || status == 'correction_required';
  }

  List<Map<String, dynamic>> _timeline(Map<String, dynamic> item) {
    final timeline = item['timeline'];
    if (timeline is! List) return const [];
    return timeline
        .whereType<Map>()
        .map((entry) => Map<String, dynamic>.from(entry))
        .toList();
  }

  Future<void> _edit() async {
    final item = _item;
    if (item == null) return;
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => DriverRestaurantOnboardingFormScreen(onboarding: item),
      ),
    );
    if (changed == true) {
      await _load();
      if (mounted) Navigator.of(context).pop(true);
    }
  }
}

class DriverRestaurantOnboardingFormScreen extends StatefulWidget {
  const DriverRestaurantOnboardingFormScreen({
    super.key,
    required this.onboarding,
  });

  final Map<String, dynamic> onboarding;

  @override
  State<DriverRestaurantOnboardingFormScreen> createState() =>
      _DriverRestaurantOnboardingFormScreenState();
}

class _DriverRestaurantOnboardingFormScreenState
    extends State<DriverRestaurantOnboardingFormScreen> {
  final _service = DriverRestaurantOnboardingService();
  final _locationService = LocationService();
  final _picker = ImagePicker();
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  int _step = 0;
  bool _saving = false;
  bool _submitting = false;
  bool _otpSending = false;
  bool _otpVerifying = false;
  bool _locating = false;
  bool _ownerVerified = false;
  LatLng? _restaurantLocation;
  LatLng? _driverLocation;
  List<Map<String, dynamic>> _cuisines = const [];
  bool _loadingCuisines = false;
  final Set<String> _selectedCuisineNames = <String>{};
  final Set<String> _selectedWeeklyOff = <String>{};
  final Map<String, String> _files = <String, String>{};

  static const _stepCount = 5;
  static const _weekDays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];
  static const _fields = [
    'business_name',
    'business_email',
    'business_phone',
    'contact_name',
    'contact_email',
    'contact_phone',
    'city',
    'address',
    'pincode',
    'latitude',
    'longitude',
    'cuisine',
    'gstin_number',
    'pan_number',
    'bank_holder_name',
    'bank_name',
    'bank_account_number',
    'bank_ifsc',
    'upi_id',
    'opening_time',
    'closing_time',
    'weekly_off',
    'driver_latitude',
    'driver_longitude',
    'owner_otp',
  ];

  @override
  void initState() {
    super.initState();
    _ownerVerified = widget.onboarding['owner_mobile_verified'] == true;
    final draft = widget.onboarding['draft_payload'];
    final restaurant = widget.onboarding['restaurant'];
    final payload = draft is Map ? Map<String, dynamic>.from(draft) : {};
    if (restaurant is Map) {
      payload.putIfAbsent('business_name', () => restaurant['name']);
      payload.putIfAbsent('business_phone', () => restaurant['phone']);
    }
    payload.putIfAbsent('bank_account_number', () => payload['account_number']);
    payload.putIfAbsent('bank_ifsc', () => payload['ifsc_code']);
    _selectedCuisineNames.addAll(_splitCsv(payload['cuisine']));
    _selectedWeeklyOff.addAll(_splitCsv(payload['weekly_off']));
    for (final field in _fields) {
      _controllers[field] =
          TextEditingController(text: (payload[field] ?? '').toString());
    }
    _loadCuisines();
    _restaurantLocation = _latLngFromPayload(
      payload['latitude'],
      payload['longitude'],
    );
    _driverLocation = _latLngFromPayload(
      payload['driver_latitude'],
      payload['driver_longitude'],
    );
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final busy =
        _saving || _submitting || _otpSending || _otpVerifying || _locating;
    return Scaffold(
      backgroundColor: foodflow.canvas,
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              _wizardTopBar(busy),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  children: [
                    _wizardHeader(),
                    const SizedBox(height: 14),
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 180),
                      child: KeyedSubtree(
                        key: ValueKey(_step),
                        child: _stepContent(),
                      ),
                    ),
                  ],
                ),
              ),
              _bottomBar(busy),
            ],
          ),
        ),
      ),
    );
  }

  Widget _wizardTopBar(bool busy) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 8, 16, 8),
      child: Row(
        children: [
          IconButton(
            onPressed: busy ? null : _back,
            icon: Icon(
                _step == 0 ? Icons.close_rounded : Icons.arrow_back_rounded),
          ),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                minHeight: 8,
                value: (_step + 1) / _stepCount,
                backgroundColor: foodflow.line,
                valueColor: AlwaysStoppedAnimation(foodflow.orange),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Text(
            'Step ${_step + 1} of $_stepCount',
            style: TextStyle(
                color: foodflow.inkSoft, fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }

  Widget _wizardHeader() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: foodflow.surface(radius: 12),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: foodflow.orange.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(_stepIcon(_step), color: foodflow.orange),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_stepTitle(_step), style: _sectionTitle),
                const SizedBox(height: 4),
                Text(
                  _stepSubtitle(_step),
                  style: TextStyle(color: foodflow.muted, height: 1.35),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _stepContent() {
    switch (_step) {
      case 0:
        return _restaurantStep();
      case 1:
        return _ownerStep();
      case 2:
        return _locationStep();
      case 3:
        return _payoutStep();
      default:
        return _hoursReviewStep();
    }
  }

  Widget _restaurantStep() {
    return _SectionCard(
      title: 'Restaurant details',
      children: [
        _text('business_name', 'Restaurant name', required: true),
        _text('business_email', 'Business email', required: true),
        _text('business_phone', 'Business phone', required: true),
        _cuisinePicker(),
        _text('gstin_number', 'GSTIN'),
        _text('pan_number', 'PAN'),
      ],
    );
  }

  Widget _cuisinePicker() {
    if (_loadingCuisines && _cuisines.isEmpty) {
      return const Padding(
        padding: EdgeInsets.only(bottom: 12),
        child: LinearProgressIndicator(minHeight: 3),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Cuisine',
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          if (_cuisines.isEmpty)
            OutlinedButton.icon(
              onPressed: _loadingCuisines ? null : _loadCuisines,
              icon: Icon(Icons.refresh),
              label: const Text('Load cuisines'),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _cuisines.map((cuisine) {
                final name = cuisine['name']?.toString() ?? '';
                final selected = _selectedCuisineNames.contains(name);
                return FilterChip(
                  label: Text(name),
                  selected: selected,
                  onSelected: name.isEmpty
                      ? null
                      : (value) {
                          setState(() {
                            if (value) {
                              _selectedCuisineNames.add(name);
                            } else {
                              _selectedCuisineNames.remove(name);
                            }
                            _controllers['cuisine']?.text =
                                _selectedCuisineNames.join(', ');
                          });
                        },
                );
              }).toList(),
            ),
        ],
      ),
    );
  }

  Widget _ownerStep() {
    return Column(
      children: [
        _NoticePanel(
          title: 'Owner verification',
          body: _ownerVerified
              ? 'Owner mobile has been verified.'
              : 'Send OTP to the restaurant owner before final submission when required.',
          color: _ownerVerified ? Colors.green : Colors.blue,
        ),
        _SectionCard(
          title: 'Owner details',
          children: [
            _text('contact_name', 'Owner name', required: true),
            _text('contact_email', 'Owner email', required: true),
            _text('contact_phone', 'Owner phone', required: true),
            Row(
              children: [
                Expanded(child: _text('owner_otp', 'OTP')),
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  onPressed: _otpSending ? null : _sendOtp,
                  icon: _otpSending
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Icon(Icons.sms_outlined),
                  label: const Text('Send'),
                ),
                const SizedBox(width: 8),
                FilledButton.icon(
                  onPressed: _otpVerifying ? null : _verifyOtp,
                  icon: _otpVerifying
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Icon(Icons.verified_outlined),
                  label: const Text('Verify'),
                ),
              ],
            ),
          ],
        ),
      ],
    );
  }

  Widget _locationStep() {
    return _SectionCard(
      title: 'Location and address',
      children: [
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: _locating ? null : _useCurrentLocation,
                icon: _locating
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(Icons.my_location_rounded),
                label: Text(_locating ? 'Detecting...' : 'Auto Detect'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: FilledButton.icon(
                onPressed: _openMapPicker,
                icon: Icon(Icons.add_location_alt_outlined),
                label: const Text('Pin on Map'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        _LocationPreview(location: _restaurantLocation),
        const SizedBox(height: 12),
        _text('address', 'Restaurant address', required: true, maxLines: 3),
        _text('city', 'City', required: true),
        _text('pincode', 'Pincode'),
      ],
    );
  }

  Widget _payoutStep() {
    return _SectionCard(
      title: 'Bank details',
      children: [
        _text('bank_holder_name', 'Account holder'),
        _text('bank_name', 'Bank name'),
        Row(
          children: [
            Expanded(child: _text('bank_account_number', 'Account number')),
            const SizedBox(width: 10),
            Expanded(child: _text('bank_ifsc', 'IFSC')),
          ],
        ),
        _text('upi_id', 'UPI ID'),
      ],
    );
  }

  Widget _hoursReviewStep() {
    return Column(
      children: [
        _SectionCard(
          title: 'Hours and documents',
          children: [
            Row(
              children: [
                Expanded(child: _timeField('opening_time', 'Opening time')),
                const SizedBox(width: 10),
                Expanded(child: _timeField('closing_time', 'Closing time')),
              ],
            ),
            _weekdaySelector(),
            const SizedBox(height: 4),
            _UploadTile(
              label: 'Restaurant logo',
              path: _files['logo_image'],
              icon: Icons.storefront_outlined,
              onTap: () => _pickUpload('logo_image', ImageSource.gallery),
            ),
            _UploadTile(
              label: 'Restaurant banner',
              path: _files['banner_image'],
              icon: Icons.photo_size_select_actual_outlined,
              onTap: () => _pickUpload('banner_image', ImageSource.gallery),
            ),
            _UploadTile(
              label: 'Menu photo',
              path: _files['menu_photo'],
              icon: Icons.camera_alt_outlined,
              onTap: () => _pickUpload('menu_photo', ImageSource.camera),
            ),
            _UploadTile(
              label: 'FSSAI license',
              path: _files['fssai_license'],
              icon: Icons.verified_outlined,
              onTap: () => _pickUpload('fssai_license', ImageSource.camera),
            ),
          ],
        ),
        _NoticePanel(
          title: 'Ready for review',
          body:
              'Owner password setup is skipped for driver onboarding. The backend will create credentials after approval.',
          color: Colors.green,
        ),
      ],
    );
  }

  Widget _weekdaySelector() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Weekly off days',
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _weekDays.map((day) {
              final selected = _selectedWeeklyOff.contains(day);
              return FilterChip(
                label: Text(day.substring(0, 3)),
                selected: selected,
                onSelected: (value) {
                  setState(() {
                    if (value) {
                      _selectedWeeklyOff.add(day);
                    } else {
                      _selectedWeeklyOff.remove(day);
                    }
                    _controllers['weekly_off']?.text =
                        _selectedWeeklyOff.join(', ');
                  });
                },
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _bottomBar(bool busy) {
    final isLast = _step == _stepCount - 1;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        border: Border(top: BorderSide(color: foodflow.line)),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            if (_step > 0) ...[
              SizedBox(
                height: 52,
                width: 52,
                child: OutlinedButton(
                  onPressed: busy ? null : () => setState(() => _step -= 1),
                  child: Icon(Icons.arrow_back),
                ),
              ),
              const SizedBox(width: 10),
            ],
            Expanded(
              child: OutlinedButton.icon(
                onPressed: busy ? null : _saveDraft,
                icon: _saving
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(Icons.save_outlined),
                label: const Text('Save Draft'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              flex: 2,
              child: FilledButton.icon(
                onPressed: busy ? null : _continueOrSubmit,
                icon: _submitting
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(isLast ? Icons.send_outlined : Icons.arrow_forward),
                label: Text(isLast ? 'Submit' : 'Continue'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _text(
    String key,
    String label, {
    bool required = false,
    int maxLines = 1,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: _controllers[key],
        maxLines: maxLines,
        decoration: InputDecoration(labelText: label),
        validator: required
            ? (value) => value == null || value.trim().isEmpty
                ? '$label is required'
                : null
            : null,
      ),
    );
  }

  Widget _timeField(String key, String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: _controllers[key],
        readOnly: true,
        decoration: InputDecoration(
          labelText: label,
          suffixIcon: Icon(Icons.schedule_outlined),
        ),
        onTap: () => _pickTime(key),
      ),
    );
  }

  Future<void> _pickTime(String key) async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _parseTime(_controllers[key]?.text) ??
          const TimeOfDay(hour: 10, minute: 0),
    );
    if (picked == null) return;
    _controllers[key]?.text = _formatTimeOfDay(picked);
    setState(() {});
  }

  Future<void> _loadCuisines() async {
    if (_loadingCuisines) return;
    setState(() => _loadingCuisines = true);
    try {
      final cuisines = await _service.cuisines();
      if (!mounted) return;
      setState(() {
        _cuisines = cuisines;
        for (final name in _selectedCuisineNames.toList()) {
          if (name.trim().isEmpty) _selectedCuisineNames.remove(name);
        }
      });
    } catch (e) {
      debugPrint('Cuisine load error: $e');
    } finally {
      if (mounted) setState(() => _loadingCuisines = false);
    }
  }

  Future<void> _pickUpload(String field, ImageSource source) async {
    try {
      final file = await _picker.pickImage(source: source, imageQuality: 86);
      if (file == null || !mounted) return;
      setState(() => _files[field] = file.path);
    } catch (e) {
      _showError(
          'Unable to open ${source == ImageSource.camera ? 'camera' : 'gallery'}: $e');
    }
  }

  bool _hasDocument(String field) {
    final path = _files[field];
    if (path != null && path.isNotEmpty) return true;
    final documents = widget.onboarding['documents'];
    return documents is Map && documents[field] == true;
  }

  Future<void> _useCurrentLocation() async {
    setState(() => _locating = true);
    try {
      final pos = await _locationService.getCurrentLocation();
      if (pos == null) {
        _showError('Location unavailable. Pin the restaurant manually on map.');
        return;
      }
      final location = LatLng(pos.latitude, pos.longitude);
      _driverLocation = location;
      await _applyRestaurantLocation(location);
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _openMapPicker() async {
    final picked = await Navigator.of(context).push<LatLng>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => _RestaurantMapPicker(
          initialLocation: _restaurantLocation ?? _driverLocation,
        ),
      ),
    );
    if (picked == null) return;
    await _captureDriverLocation();
    await _applyRestaurantLocation(picked);
  }

  Future<void> _captureDriverLocation() async {
    try {
      final pos = await _locationService.getCurrentLocation();
      if (pos != null) {
        _driverLocation = LatLng(pos.latitude, pos.longitude);
        _controllers['driver_latitude']?.text = pos.latitude.toString();
        _controllers['driver_longitude']?.text = pos.longitude.toString();
      }
    } catch (_) {}
  }

  Future<void> _applyRestaurantLocation(LatLng location) async {
    _restaurantLocation = location;
    _controllers['latitude']?.text = location.latitude.toString();
    _controllers['longitude']?.text = location.longitude.toString();
    if (_driverLocation != null) {
      _controllers['driver_latitude']?.text =
          _driverLocation!.latitude.toString();
      _controllers['driver_longitude']?.text =
          _driverLocation!.longitude.toString();
    }

    final resolved = await _locationService.getAddressFromLatLng(
      location.latitude,
      location.longitude,
    );
    if (!mounted) return;
    setState(() {
      if ((resolved?['address'] ?? '').isNotEmpty) {
        _controllers['address']?.text = resolved!['address']!;
      }
      if ((resolved?['city'] ?? '').isNotEmpty) {
        _controllers['city']?.text = resolved!['city']!;
      }
      if ((resolved?['pincode'] ?? '').isNotEmpty) {
        _controllers['pincode']?.text = resolved!['pincode']!;
      }
    });
  }

  void _continueOrSubmit() {
    if (!_validateStep(_step)) return;
    if (_step < _stepCount - 1) {
      setState(() => _step += 1);
      return;
    }
    _submit();
  }

  bool _validateStep(int step) {
    String? message;
    final emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
    final phonePattern = RegExp(r'\d{8,}');
    final ifscPattern = RegExp(r'^[A-Z]{4}0[A-Z0-9]{6}$');
    final gstPattern = RegExp(
      r'^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$',
      caseSensitive: false,
    );
    final panPattern = RegExp(r'^[A-Z]{5}\d{4}[A-Z]{1}$', caseSensitive: false);

    String value(String key) => _controllers[key]?.text.trim() ?? '';

    if (step == 0) {
      for (final key in ['business_name', 'business_email', 'business_phone']) {
        if (value(key).isEmpty) message = 'Complete restaurant details.';
      }
      if (_selectedCuisineNames.isEmpty)
        message = 'Select at least one cuisine.';
      if (value('business_email').isNotEmpty &&
          !emailPattern.hasMatch(value('business_email'))) {
        message = 'Enter a valid business email.';
      }
      if (value('business_phone').isNotEmpty &&
          !phonePattern.hasMatch(
              value('business_phone').replaceAll(RegExp(r'\D'), ''))) {
        message = 'Enter a valid business phone.';
      }
      if (value('gstin_number').isNotEmpty &&
          !gstPattern.hasMatch(value('gstin_number'))) {
        message = 'Enter a valid GSTIN.';
      }
      if (value('pan_number').isNotEmpty &&
          !panPattern.hasMatch(value('pan_number'))) {
        message = 'Enter a valid PAN.';
      }
    } else if (step == 1) {
      for (final key in ['contact_name', 'contact_email', 'contact_phone']) {
        if (value(key).isEmpty) message = 'Complete owner details.';
      }
      if (value('contact_email').isNotEmpty &&
          !emailPattern.hasMatch(value('contact_email'))) {
        message = 'Enter a valid owner email.';
      }
      if (value('contact_phone').isNotEmpty &&
          !phonePattern
              .hasMatch(value('contact_phone').replaceAll(RegExp(r'\D'), ''))) {
        message = 'Enter a valid owner phone.';
      }
    } else if (step == 2) {
      if (_restaurantLocation == null) {
        message = 'Confirm restaurant location on the map.';
      }
      if (value('address').isEmpty || value('city').isEmpty) {
        message = 'Complete restaurant address.';
      }
    } else if (step == 3) {
      final bankFields = [
        value('bank_holder_name'),
        value('bank_account_number'),
        value('bank_ifsc'),
      ];
      final hasAnyBankField = bankFields.any((item) => item.isNotEmpty) ||
          value('upi_id').isNotEmpty;
      if (hasAnyBankField && bankFields.any((item) => item.isEmpty)) {
        message = 'Enter account holder, account number, and IFSC.';
      }
      if (value('bank_ifsc').isNotEmpty &&
          !ifscPattern.hasMatch(value('bank_ifsc').toUpperCase())) {
        message = 'Enter a valid IFSC code.';
      }
    } else if (step == 4) {
      if (value('opening_time').isEmpty || value('closing_time').isEmpty) {
        message = 'Select opening and closing time.';
      }
      for (final entry in const {
        'logo_image': 'restaurant logo',
        'banner_image': 'restaurant banner',
        'menu_photo': 'menu photo',
        'fssai_license': 'FSSAI license',
      }.entries) {
        if (!_hasDocument(entry.key)) {
          message = 'Upload ${entry.value}.';
          break;
        }
      }
    }
    if (message != null) {
      _showError(message);
      return false;
    }
    return true;
  }

  Map<String, dynamic> _payload() {
    final data = <String, dynamic>{};
    for (final entry in _controllers.entries) {
      if (entry.key == 'owner_otp') continue;
      final value = entry.value.text.trim();
      if (value.isNotEmpty) data[entry.key] = value;
    }
    if (_selectedCuisineNames.isNotEmpty) {
      data['cuisine'] = _selectedCuisineNames.join(', ');
    }
    if (_selectedWeeklyOff.isNotEmpty) {
      data['weekly_off'] = _selectedWeeklyOff.join(', ');
    }
    data['terms'] = true;
    data['declaration'] = true;
    data['is_pure_veg'] = false;
    if (_restaurantLocation != null) {
      data['latitude'] = _restaurantLocation!.latitude.toString();
      data['longitude'] = _restaurantLocation!.longitude.toString();
    }
    if (_driverLocation != null) {
      data['driver_latitude'] = _driverLocation!.latitude.toString();
      data['driver_longitude'] = _driverLocation!.longitude.toString();
    } else {
      data.putIfAbsent('driver_latitude', () => data['latitude']);
      data.putIfAbsent('driver_longitude', () => data['longitude']);
    }
    return data;
  }

  Future<void> _saveDraft() async {
    setState(() => _saving = true);
    try {
      await _service.saveDraft(_id, _payload());
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Draft saved')),
      );
      Navigator.of(context).pop(true);
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _submit() async {
    for (var index = 0; index < _stepCount; index++) {
      if (!_validateStep(index)) {
        setState(() => _step = index);
        return;
      }
    }
    setState(() => _submitting = true);
    try {
      await _service.submit(_id, _payload(),
          files: Map<String, String>.from(_files));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Submitted for review')),
      );
      Navigator.of(context).pop(true);
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _sendOtp() async {
    final phone = _controllers['contact_phone']!.text.trim();
    if (phone.isEmpty) {
      _showError('Enter owner phone first.');
      return;
    }
    setState(() => _otpSending = true);
    try {
      await _service.sendOwnerOtp(_id, phone);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('OTP sent')),
      );
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _otpSending = false);
    }
  }

  Future<void> _verifyOtp() async {
    final phone = _controllers['contact_phone']!.text.trim();
    final otp = _controllers['owner_otp']!.text.trim();
    if (phone.isEmpty || otp.isEmpty) {
      _showError('Enter owner phone and OTP.');
      return;
    }
    setState(() => _otpVerifying = true);
    try {
      await _service.verifyOwnerOtp(_id, phone, otp);
      if (!mounted) return;
      setState(() => _ownerVerified = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Owner mobile verified')),
      );
    } catch (e) {
      _showError(e);
    } finally {
      if (mounted) setState(() => _otpVerifying = false);
    }
  }

  void _back() {
    if (_step == 0) {
      Navigator.of(context).pop();
    } else {
      setState(() => _step -= 1);
    }
  }

  void _showError(Object error) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error.toString())),
    );
  }

  IconData _stepIcon(int step) {
    return [
      Icons.storefront_outlined,
      Icons.verified_user_outlined,
      Icons.add_location_alt_outlined,
      Icons.account_balance_outlined,
      Icons.schedule_outlined,
    ][step];
  }

  String _stepTitle(int step) {
    return [
      'Restaurant Information',
      'Owner Verification',
      'Location',
      'Bank Details',
      'Hours and Review',
    ][step];
  }

  String _stepSubtitle(int step) {
    return [
      'Capture the same basic restaurant details used in public signup.',
      'Verify the owner mobile while keeping driver attribution server-side.',
      'Auto detect location or pin the restaurant manually on the map.',
      'Add payout details for the restaurant owner account.',
      'Select opening and closing time before final submission.',
    ][step];
  }

  int get _id => _intValue(widget.onboarding['id']) ?? 0;
}

class _LocationPreview extends StatelessWidget {
  const _LocationPreview({required this.location});

  final LatLng? location;

  @override
  Widget build(BuildContext context) {
    if (location == null) {
      return Container(
        height: 150,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: foodflow.canvas,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: foodflow.line),
        ),
        child:  Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.map_outlined, color: foodflow.muted),
            SizedBox(height: 6),
            Text('No location selected'),
          ],
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        height: 170,
        child: GoogleMap(
          initialCameraPosition: CameraPosition(target: location!, zoom: 16),
          markers: {
            Marker(markerId: const MarkerId('restaurant'), position: location!),
          },
          liteModeEnabled: true,
          zoomControlsEnabled: false,
          myLocationButtonEnabled: false,
          mapToolbarEnabled: false,
        ),
      ),
    );
  }
}

class _RestaurantMapPicker extends StatefulWidget {
  const _RestaurantMapPicker({this.initialLocation});

  final LatLng? initialLocation;

  @override
  State<_RestaurantMapPicker> createState() => _RestaurantMapPickerState();
}

class _RestaurantMapPickerState extends State<_RestaurantMapPicker> {
  LatLng? _selected;

  @override
  void initState() {
    super.initState();
    _selected = widget.initialLocation ?? const LatLng(28.6139, 77.2090);
  }

  @override
  Widget build(BuildContext context) {
    final selected = _selected!;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Pin Restaurant Location'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(selected),
            child: const Text('Use'),
          ),
        ],
      ),
      body: Stack(
        children: [
          GoogleMap(
            initialCameraPosition: CameraPosition(target: selected, zoom: 16),
            onTap: (point) => setState(() => _selected = point),
            markers: {
              Marker(
                markerId: const MarkerId('restaurant_pin'),
                position: selected,
                draggable: true,
                onDragEnd: (point) => setState(() => _selected = point),
              ),
            },
            myLocationEnabled: true,
            myLocationButtonEnabled: true,
            zoomControlsEnabled: false,
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 16,
            child: FilledButton.icon(
              onPressed: () => Navigator.of(context).pop(selected),
              icon: Icon(Icons.check),
              label: const Text('Confirm Location'),
            ),
          ),
        ],
      ),
    );
  }
}

LatLng? _latLngFromPayload(dynamic latValue, dynamic lngValue) {
  final lat = double.tryParse(latValue?.toString() ?? '');
  final lng = double.tryParse(lngValue?.toString() ?? '');
  if (lat == null || lng == null) return null;
  if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
  return LatLng(lat, lng);
}

TimeOfDay? _parseTime(String? value) {
  final text = (value ?? '').trim();
  final match = RegExp(r'^(\d{1,2}):(\d{2})').firstMatch(text);
  if (match == null) return null;
  final hour = int.tryParse(match.group(1) ?? '');
  final minute = int.tryParse(match.group(2) ?? '');
  if (hour == null || minute == null || hour > 23 || minute > 59) return null;
  return TimeOfDay(hour: hour, minute: minute);
}

String _formatTimeOfDay(TimeOfDay value) {
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

class _SummaryPanel extends StatelessWidget {
  const _SummaryPanel({required this.summary});

  final Map<String, dynamic> summary;

  @override
  Widget build(BuildContext context) {
    final totals = summary['metrics'] is Map
        ? Map<String, dynamic>.from(summary['metrics'])
        : summary['totals'] is Map
            ? Map<String, dynamic>.from(summary['totals'])
            : summary;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: foodflow.surface(radius: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Onboarding Incentives', style: _sectionTitle),
          const SizedBox(height: 12),
          Row(
            children: [
              _Metric(
                'Submitted',
                '${totals['submitted'] ?? totals['total_submitted'] ?? 0}',
              ),
              _Metric('Earned',
                  formatCurrencyValue(context, totals['earned_amount'] ?? 0)),
              _Metric('Paid',
                  formatCurrencyValue(context, totals['paid_amount'] ?? 0)),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatusFilters extends StatelessWidget {
  const _StatusFilters({
    required this.selected,
    required this.statuses,
    required this.onChanged,
  });

  final String selected;
  final List<String> statuses;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: statuses
            .map(
              (status) => Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(_label(status)),
                  selected: selected == status,
                  onSelected: (_) => onChanged(status),
                ),
              ),
            )
            .toList(),
      ),
    );
  }
}

class _OnboardingCard extends StatelessWidget {
  const _OnboardingCard({
    required this.item,
    required this.onOpen,
    this.onEdit,
  });

  final Map<String, dynamic> item;
  final VoidCallback onOpen;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final restaurant = item['restaurant'] is Map
        ? Map<String, dynamic>.from(item['restaurant'])
        : const <String, dynamic>{};
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: foodflow.surface(radius: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: foodflow.orange.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(Icons.storefront, color: foodflow.orange),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      (restaurant['name'] ?? 'Restaurant draft').toString(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    Text(
                      (item['application_number'] ?? '').toString(),
                      style: TextStyle(color: foodflow.muted),
                    ),
                  ],
                ),
              ),
              _StatusPill(item['status_label'] ?? _label('${item['status']}')),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Incentive ${formatCurrencyValue(context, item['incentive_amount'] ?? 0)}',
                  style:
                      GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700),
                ),
              ),
              Text(
                _label('${item['incentive_status'] ?? 'pending'}'),
                style: TextStyle(color: foodflow.inkSoft),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              OutlinedButton.icon(
                onPressed: onOpen,
                icon: Icon(Icons.timeline_outlined),
                label: const Text('Detail'),
              ),
              const SizedBox(width: 8),
              if (onEdit != null)
                FilledButton.icon(
                  onPressed: onEdit,
                  icon: Icon(Icons.edit_outlined),
                  label: const Text('Edit'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _UploadTile extends StatelessWidget {
  const _UploadTile({
    required this.label,
    required this.path,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final String? path;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final uploaded = path != null && path!.isNotEmpty;
    final name = uploaded ? _fileName(path!) : 'Tap to upload';

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: OutlinedButton.icon(
        onPressed: onTap,
        icon: Icon(uploaded ? Icons.check_circle_outline : icon),
        label: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(label),
                  Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 12, color: foodflow.muted),
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

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 2),
      decoration: foodflow.surface(radius: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: _sectionTitle),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }
}

class _NoticePanel extends StatelessWidget {
  const _NoticePanel({
    required this.title,
    required this.body,
    required this.color,
  });

  final String title;
  final String body;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                Text(body),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _VerificationPanel extends StatelessWidget {
  const _VerificationPanel({required this.verification});

  final Object? verification;

  @override
  Widget build(BuildContext context) {
    final data = verification is Map
        ? Map<String, dynamic>.from(verification as Map)
        : <String, dynamic>{};
    final statusMap = data['document_statuses'] is Map
        ? Map<String, dynamic>.from(data['document_statuses'] as Map)
        : <String, dynamic>{};

    if (data.isEmpty && statusMap.isEmpty) {
      return const SizedBox.shrink();
    }

    final overall = (data['overall_status'] ?? 'pending').toString();
    final bankStatus = data['bank_account_status']?.toString();
    final documentEntries = statusMap.entries
        .where((entry) => entry.key != 'bank_account')
        .toList();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: foodflow.surface(radius: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.verified_user_outlined, color: foodflow.orange),
              const SizedBox(width: 8),
              Expanded(child: Text('Verification', style: _sectionTitle)),
              _VerificationBadge(status: overall),
            ],
          ),
          const SizedBox(height: 10),
          if (bankStatus != null && bankStatus.isNotEmpty)
            _VerificationRow(label: 'Bank account', status: bankStatus),
          ...documentEntries.map((entry) => _VerificationRow(
                label: _label(entry.key),
                status: entry.value?.toString() ?? 'checked',
              )),
          if ((bankStatus == null || bankStatus.isEmpty) &&
              documentEntries.isEmpty)
            Text(
              'Verification pending.',
              style: TextStyle(color: foodflow.muted),
            ),
        ],
      ),
    );
  }
}

class _VerificationRow extends StatelessWidget {
  const _VerificationRow({required this.label, required this.status});

  final String label;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700),
            ),
          ),
          _VerificationBadge(status: status),
        ],
      ),
    );
  }
}

class _VerificationBadge extends StatelessWidget {
  const _VerificationBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final color = _verificationColor(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        _label(status),
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _TimelineTile extends StatelessWidget {
  const _TimelineTile(this.event);

  final Map<String, dynamic> event;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: foodflow.surface(radius: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.check_circle_outline, color: foodflow.success),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  (event['label'] ?? event['event'] ?? '').toString(),
                  style:
                      GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
                ),
                if ((event['notes'] ?? '').toString().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(event['notes'].toString()),
                  ),
                if ((event['created_at'] ?? '').toString().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      event['created_at'].toString(),
                      style:
                          TextStyle(color: foodflow.muted, fontSize: 12),
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

class _Metric extends StatelessWidget {
  const _Metric(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: TextStyle(color: foodflow.muted, fontSize: 12)),
          const SizedBox(height: 3),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w900),
          ),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill(this.label);

  final Object? label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: foodflow.orange.withOpacity(0.1),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label.toString(),
        style: TextStyle(
          color: foodflow.orange,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _EmptyPanel extends StatelessWidget {
  const _EmptyPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: foodflow.surface(radius: 12),
      child:  Column(
        children: [
          Icon(Icons.add_business_outlined, size: 48, color: foodflow.muted),
          SizedBox(height: 10),
          Text('No restaurant onboardings yet.'),
        ],
      ),
    );
  }
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: foodflow.surface(radius: 12),
      child: Row(
        children: [
          Icon(Icons.error_outline, color: Colors.red),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
          TextButton(onPressed: onRetry, child: const Text('Retry')),
        ],
      ),
    );
  }
}

TextStyle get _sectionTitle => GoogleFonts.plusJakartaSans(
      fontWeight: FontWeight.w900,
      fontSize: 16,
    );

Color _verificationColor(String status) {
  switch (status) {
    case 'verified':
      return foodflow.success;
    case 'invalid':
    case 'error':
    case 'needs_review':
      return Colors.red.shade700;
    case 'checked':
      return Colors.blueGrey.shade700;
    default:
      return foodflow.muted;
  }
}

List<String> _splitCsv(dynamic value) {
  final text = value?.toString() ?? '';
  return text
      .split(',')
      .map((part) => part.trim())
      .where((part) => part.isNotEmpty)
      .toList();
}

String _fileName(String path) {
  final normalized = path.replaceAll('\\', '/');
  final index = normalized.lastIndexOf('/');
  return index >= 0 ? normalized.substring(index + 1) : normalized;
}

String _label(String value) {
  return value
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1))
      .join(' ');
}

int? _intValue(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}
