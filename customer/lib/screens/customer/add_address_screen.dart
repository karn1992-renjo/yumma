// lib/screens/customer/add_address_screen.dart
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../services/api_service.dart';
import '../../services/location_service.dart';
import '../../config/api_constants.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/phone_number_utils.dart';
import '../../widgets/customer/account_chrome.dart';

class AddAddressScreen extends StatefulWidget {
  final dynamic address;
  
  const AddAddressScreen({Key? key, this.address}) : super(key: key);

  @override
  State<AddAddressScreen> createState() => _AddAddressScreenState();
}

class _AddAddressScreenState extends State<AddAddressScreen> {
  final ApiService _api = ApiService();
  final LocationService _locationService = LocationService();
  
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _manualSearchController = TextEditingController();
  final _addressController = TextEditingController();
  final _cityController = TextEditingController();
  final _stateController = TextEditingController();
  final _pincodeController = TextEditingController();
  final _phoneController = TextEditingController();
  
  double? _latitude;
  double? _longitude;
  GoogleMapController? _mapController;
  bool _isLoading = false;
  bool _isFetchingLocation = false;
  bool _isSearchingAddress = false;
  bool _isResolvingPin = false;
  List<Map<String, dynamic>> _locationSuggestions = [];
  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    if (widget.address != null) {
      _nameController.text = widget.address.name;
      _addressController.text = widget.address.address;
      _cityController.text = widget.address.city;
      _stateController.text = widget.address.state;
      _pincodeController.text = widget.address.pincode;
      _phoneController.text = widget.address.phone;
      _latitude = widget.address.latitude;
      _longitude = widget.address.longitude;
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _manualSearchController.dispose();
    _addressController.dispose();
    _cityController.dispose();
    _stateController.dispose();
    _pincodeController.dispose();
    _phoneController.dispose();
    _searchDebounce?.cancel();
    _mapController = null;
    super.dispose();
  }

  Future<void> _animateMap(double latitude, double longitude) async {
    final controller = _mapController;
    if (!mounted || controller == null) return;
    try {
      await controller.animateCamera(
        CameraUpdate.newLatLngZoom(LatLng(latitude, longitude), 17),
      );
    } catch (error) {
      // The platform map can be replaced before this async camera update runs.
      debugPrint('Skipped camera update for a disposed map: $error');
      if (identical(_mapController, controller)) {
        _mapController = null;
      }
    }
  }

  Future<void> _applyResolvedLocation(
    double latitude,
    double longitude, {
    String? fallbackAddress,
    String? fallbackCity,
  }) async {
    if (!mounted) return;
    setState(() {
      _latitude = latitude;
      _longitude = longitude;
      _isResolvingPin = true;
    });

    final locationData = await _locationService.getAddressFromLatLng(
      latitude,
      longitude,
    );

    if (!mounted) return;

    setState(() {
      if (fallbackAddress != null && fallbackAddress.trim().isNotEmpty) {
        _addressController.text = fallbackAddress.trim();
      }
      if (fallbackCity != null && fallbackCity.trim().isNotEmpty) {
        _cityController.text = fallbackCity.trim();
      }

      if (locationData != null) {
        if (locationData['address']?.toString().trim().isNotEmpty == true) {
          _addressController.text = locationData['address'].toString().trim();
        }
        if (locationData['city']?.toString().trim().isNotEmpty == true) {
          _cityController.text = locationData['city'].toString().trim();
        }
        if (locationData['state']?.toString().trim().isNotEmpty == true) {
          _stateController.text = locationData['state'].toString().trim();
        }
        if (locationData['pincode']?.toString().trim().isNotEmpty == true) {
          _pincodeController.text = locationData['pincode'].toString().trim();
        }
      }
      _isResolvingPin = false;
    });

    await _animateMap(latitude, longitude);
  }

  Future<void> _fetchCurrentLocation() async {
    setState(() => _isFetchingLocation = true);

    final position = await _locationService.getCurrentLocation();
    if (position != null) {
      final locationData = await _locationService.getAddressFromLatLng(
        position.latitude,
        position.longitude,
      );

      setState(() {
        _latitude = position.latitude;
        _longitude = position.longitude;

        if (locationData != null) {
          if (locationData['address']?.isNotEmpty == true) {
            _addressController.text = locationData['address']!;
          }
          if (locationData['city']?.isNotEmpty == true) {
            _cityController.text = locationData['city']!;
          }
          if (locationData['state']?.isNotEmpty == true) {
            _stateController.text = locationData['state']!;
          }
          if (locationData['pincode']?.isNotEmpty == true) {
            _pincodeController.text = locationData['pincode']!;
          }
        }
      });
      await _animateMap(position.latitude, position.longitude);

      if (locationData == null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not resolve full address from current location.')),
        );
      }
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to retrieve current location.')),
      );
    }

    setState(() => _isFetchingLocation = false);
  }

  Future<void> _searchAddressSuggestions(String query) async {
    final trimmed = query.trim();
    if (trimmed.length < 3) {
      if (!mounted) return;
      setState(() {
        _locationSuggestions = [];
        _isSearchingAddress = false;
      });
      return;
    }

    setState(() => _isSearchingAddress = true);
    final suggestions = await _locationService.getLocationSuggestions(trimmed);
    if (!mounted) return;
    setState(() {
      _locationSuggestions = suggestions;
      _isSearchingAddress = false;
    });
  }

  void _onManualSearchChanged(String value) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(
      const Duration(milliseconds: 350),
      () => _searchAddressSuggestions(value),
    );
  }

  Future<void> _selectManualSuggestion(Map<String, dynamic> suggestion) async {
    FocusScope.of(context).unfocus();
    final lat = suggestion['lat'];
    final lng = suggestion['lng'];
    if (lat is! double || lng is! double) return;

    final displayName = suggestion['display_name']?.toString().trim() ?? '';
    final city = suggestion['city']?.toString().trim();

    _manualSearchController.text = displayName;
    setState(() {
      _locationSuggestions = [];
    });

    await _applyResolvedLocation(
      lat,
      lng,
      fallbackAddress: displayName,
      fallbackCity: city,
    );
  }

  Future<void> _saveAddress() async {
    if (!_formKey.currentState!.validate()) return;
    if (_latitude == null || _longitude == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please detect location or pin it on the map.')),
      );
      return;
    }

    late final String normalizedPhone;
    try {
      normalizedPhone = PhoneNumberUtils.normalizeMobile(
        _phoneController.text,
        log: true,
      ).normalizedNumber;
    } on FormatException catch (error) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.message)),
      );
      return;
    }
    
    setState(() => _isLoading = true);
    
    final data = {
      'name': _nameController.text.trim(),
      'address': _addressController.text.trim(),
      'city': _cityController.text.trim(),
      'state': _stateController.text.trim(),
      'pincode': _pincodeController.text.trim(),
      'phone': normalizedPhone,
      'latitude': _latitude,
      'longitude': _longitude,
    };
    
    try {
      late dynamic response;
      if (widget.address != null) {
        response = await _api.put('${ApiConstants.addresses}/${widget.address.id}', data: data);
      } else {
        response = await _api.post(ApiConstants.addresses, data: data);
      }
      
      if (response['success'] == true && mounted) {
        Navigator.pop(context, true);
      }
    } catch (e) {
      debugPrint('Save address error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save address: $e')),
        );
      }
    }
    
    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        title: Text(
          widget.address != null ? 'Edit Address' : 'Add New Address',
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        backgroundColor: accountCanvas,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AccountHeroCard(
                title:
                    widget.address != null ? 'Update your address' : 'Add a delivery place',
                subtitle:
                    'Pin the exact spot and add clear address details so every delivery feels effortless.',
                icon: Icons.location_on_outlined,
                badge: 'HOME STYLE',
                margin: const EdgeInsets.fromLTRB(0, 0, 0, 16),
              ),
              const AccountSectionTitle(title: 'DELIVERY PIN'),
              const SizedBox(height: 10),
              // Current Location Button
              const SizedBox(height: 0),
              AccountSurfaceCard(
                radius: 24,
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Delivery pin',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Use your location or tap the map to place the exact drop point.',
                      style: TextStyle(
                        fontSize: 13,
                        color: FoodFlowTheme.muted,
                        fontWeight: FontWeight.w500,
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _manualSearchController,
                      onChanged: _onManualSearchChanged,
                      decoration: _inputDecoration(
                        label: 'Search Address',
                        hint: 'Search area, landmark or pincode',
                        icon: Icons.search_rounded,
                      ).copyWith(
                        suffixIcon: _isSearchingAddress
                            ? const Padding(
                                padding: EdgeInsets.all(14),
                                child: SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                ),
                              )
                            : null,
                      ),
                    ),
                    if (_locationSuggestions.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: accountBorder),
                        ),
                        child: Column(
                          children: _locationSuggestions
                              .take(5)
                              .map(
                                (suggestion) => ListTile(
                                  dense: true,
                                  leading: Icon(
                                    Icons.location_on_outlined,
                                    color: Theme.of(context).colorScheme.primary,
                                  ),
                                  title: Text(
                                    suggestion['city']?.toString() ??
                                        'Selected location',
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: FoodFlowTheme.ink,
                                    ),
                                  ),
                                  subtitle: Text(
                                    suggestion['display_name']?.toString() ?? '',
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: FoodFlowTheme.muted,
                                      height: 1.25,
                                    ),
                                  ),
                                  onTap: () => _selectManualSuggestion(suggestion),
                                ),
                              )
                              .toList(growable: false),
                        ),
                      ),
                    ],
                    const SizedBox(height: 14),
                    OutlinedButton.icon(
                      onPressed: _isFetchingLocation ? null : _fetchCurrentLocation,
                      icon: _isFetchingLocation
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.my_location_rounded),
                      label: const Text('Use Current Location'),
                      style: FoodFlowTheme.zomatoOutlineButton(),
                    ),
                    const SizedBox(height: 16),
                    Container(
                      height: 220,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: const Color(0xFFF0DADB)),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: GoogleMap(
                        initialCameraPosition: CameraPosition(
                          target: LatLng(_latitude ?? 28.6139, _longitude ?? 77.2090),
                          zoom: _latitude == null ? 11 : 17,
                        ),
                        onMapCreated: (controller) => _mapController = controller,
                        myLocationEnabled: true,
                        myLocationButtonEnabled: false,
                        zoomControlsEnabled: false,
                        onTap: (point) {
                          _applyResolvedLocation(point.latitude, point.longitude);
                        },
                        markers: {
                          if (_latitude != null && _longitude != null)
                            Marker(
                              markerId: const MarkerId('delivery_pin'),
                              position: LatLng(_latitude!, _longitude!),
                              draggable: true,
                              onDragEnd: (point) {
                                _applyResolvedLocation(
                                  point.latitude,
                                  point.longitude,
                                );
                              },
                            ),
                        },
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      _isResolvingPin
                          ? 'Resolving pinned address...'
                          : _latitude == null
                          ? 'Tap the map or use current location to place the delivery pin.'
                          : 'Pinned: ${_latitude!.toStringAsFixed(6)}, ${_longitude!.toStringAsFixed(6)}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: FoodFlowTheme.inkSoft,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const AccountSectionTitle(title: 'ADDRESS DETAILS'),
              const SizedBox(height: 10),
              AccountSurfaceCard(
                padding: const EdgeInsets.all(18),
                radius: 24,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Address details',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Add clear address details so delivery is faster and smoother.',
                      style: TextStyle(
                        fontSize: 13,
                        color: FoodFlowTheme.muted,
                        fontWeight: FontWeight.w500,
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 18),
                    TextFormField(
                      controller: _nameController,
                      decoration: _inputDecoration(
                        label: 'Address Name',
                        hint: 'Home, Office, etc.',
                        icon: Icons.home_outlined,
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter address name';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _addressController,
                      decoration: _inputDecoration(
                        label: 'Address',
                        hint: 'House/Flat No., Street, Area',
                        icon: Icons.location_on_outlined,
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter your address';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _cityController,
                      decoration: _inputDecoration(
                        label: 'City',
                        hint: 'Enter city',
                        icon: Icons.location_city,
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter city';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _stateController,
                      decoration: _inputDecoration(
                        label: 'State',
                        hint: 'Enter state',
                        icon: Icons.map_outlined,
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter state';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _pincodeController,
                      keyboardType: TextInputType.number,
                      decoration: _inputDecoration(
                        label: 'Pincode',
                        hint: 'Enter pincode',
                        icon: Icons.pin_drop_outlined,
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Please enter pincode';
                        }
                        if (value.length < 6) {
                          return 'Please enter valid pincode';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: _inputDecoration(
                        label: 'Phone Number',
                        hint: 'Enter contact number',
                        icon: Icons.phone_outlined,
                      ),
                      validator: (value) {
                        return PhoneNumberUtils.validateIndianMobile(value);
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _saveAddress,
                  style: FoodFlowTheme.zomatoPrimaryButton(),
                  child: _isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          widget.address != null ? 'Update Address' : 'Save Address',
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration({
    required String label,
    required String hint,
    required IconData icon,
  }) {
    final primary = Theme.of(context).colorScheme.primary;
    return InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: Icon(icon, color: primary),
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: accountBorder),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: accountBorder),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: primary, width: 1.4),
      ),
    );
  }
}
