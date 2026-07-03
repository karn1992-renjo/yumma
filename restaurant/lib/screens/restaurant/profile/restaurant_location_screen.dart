import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:http/http.dart' as http;

import '../../../config/api_constants.dart';
import '../../../services/api_service.dart';
import '../../../theme/foodflow_theme.dart';

class RestaurantLocationScreen extends StatefulWidget {
  const RestaurantLocationScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantLocationScreen> createState() =>
      _RestaurantLocationScreenState();
}

class _RestaurantLocationScreenState extends State<RestaurantLocationScreen> {
  final ApiService _api = ApiService();
  GoogleMapController? _mapController;
  bool _isLoading = false;
  bool _isSaving = false;

  late final TextEditingController _addressController;
  late final TextEditingController _cityController;
  late final TextEditingController _pincodeController;
  late final TextEditingController _latitudeController;
  late final TextEditingController _longitudeController;

  LatLng? _selectedLocation;
  Set<Marker> _markers = {};
  PlatformFile? _fssaiFile;
  bool _hasApprovedFssaiLicense = false;

  @override
  void initState() {
    super.initState();
    _addressController = TextEditingController();
    _cityController = TextEditingController();
    _pincodeController = TextEditingController();
    _latitudeController = TextEditingController();
    _longitudeController = TextEditingController();
    _loadLocationData();
  }

  @override
  void dispose() {
    _addressController.dispose();
    _cityController.dispose();
    _pincodeController.dispose();
    _latitudeController.dispose();
    _longitudeController.dispose();
    _mapController?.dispose();
    super.dispose();
  }

  Future<void> _loadLocationData() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.restaurantInfo);
      if (response['success'] == true) {
        final data = Map<String, dynamic>.from(response['data'] ?? {});
        final latitude = _toDouble(data['latitude']);
        final longitude = _toDouble(data['longitude']);
        if (!mounted) return;

        setState(() {
          _addressController.text = data['address']?.toString() ?? '';
          _cityController.text = data['city']?.toString() ?? '';
          _pincodeController.text = data['pincode']?.toString() ?? '';
          _hasApprovedFssaiLicense =
              data['has_approved_fssai_license'] == true ||
                  (data['fssai_license_number']?.toString().isNotEmpty ??
                      false);

          if (latitude != null && longitude != null) {
            _selectedLocation = LatLng(latitude, longitude);
            _latitudeController.text = latitude.toString();
            _longitudeController.text = longitude.toString();
            _updateMarker(_selectedLocation!);
            _zoomToLocation(_selectedLocation!);
          } else {
            _selectedLocation = const LatLng(28.6139, 77.2090);
          }
        });
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading location: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  double? _toDouble(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  Future<void> _pickFssaiFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png'],
      withData: false,
    );
    if (result == null || result.files.isEmpty || !mounted) return;
    setState(() => _fssaiFile = result.files.single);
  }

  Future<void> _getCurrentLocation() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Location permission is required'),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      _updateLocationFromLatLng(
        LatLng(position.latitude, position.longitude),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error getting location: $e')),
      );
    }
  }

  void _updateLocationFromLatLng(LatLng location) {
    setState(() {
      _selectedLocation = location;
      _latitudeController.text = location.latitude.toString();
      _longitudeController.text = location.longitude.toString();
      _updateMarker(location);
    });

    _mapController?.animateCamera(
      CameraUpdate.newLatLngZoom(location, 17),
    );
  }

  void _zoomToLocation(LatLng location) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _mapController?.animateCamera(
        CameraUpdate.newLatLngZoom(location, 17),
      );
    });
  }

  void _updateMarker(LatLng location) {
    _markers = {
      Marker(
        markerId: const MarkerId('restaurant_location'),
        position: location,
        infoWindow: const InfoWindow(
          title: 'Requested location',
          snippet: 'This will be sent for admin approval',
        ),
      ),
    };
  }

  void _onMapTap(LatLng location) {
    _updateLocationFromLatLng(location);
  }

  Future<void> _saveLocation() async {
    if (_selectedLocation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please select a location on the map'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    if (!_hasApprovedFssaiLicense && _fssaiFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please attach your FSSAI proof before applying'),
          backgroundColor: Colors.orange,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final token = await _api.getToken();
      final request = http.MultipartRequest(
        'POST',
        Uri.parse(
          '${ApiConstants.baseUrl}${ApiConstants.restaurantLocationChangeRequest}',
        ),
      );

      request.headers['Authorization'] = 'Bearer $token';
      request.fields['latitude'] = _selectedLocation!.latitude.toString();
      request.fields['longitude'] = _selectedLocation!.longitude.toString();
      if (_fssaiFile != null) {
        if (_fssaiFile?.path == null || _fssaiFile!.path!.isEmpty) {
          throw Exception('Selected FSSAI file is not accessible.');
        }
        request.files.add(
          await http.MultipartFile.fromPath(
            'fssai_license',
            _fssaiFile!.path!,
          ),
        );
      }

      final streamedResponse = await request.send();
      final body = await streamedResponse.stream.bytesToString();
      final response = jsonDecode(body) as Map<String, dynamic>;

      if (streamedResponse.statusCode >= 200 &&
          streamedResponse.statusCode < 300 &&
          response['success'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Location request submitted. A support ticket has been created automatically.',
            ),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      } else {
        throw Exception(
          response['message'] ?? 'Failed to submit location request',
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) {
        setState(() => _isSaving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final mapLocation = _selectedLocation ?? const LatLng(28.6139, 77.2090);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Apply For Location Update'),
        elevation: 0,
        backgroundColor: FoodFlowTheme.orange,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              child: Column(
                children: [
                  Container(
                    height: 300,
                    margin: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.grey.shade300),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: GoogleMap(
                      onMapCreated: (controller) {
                        _mapController = controller;
                        if (_selectedLocation != null) {
                          _zoomToLocation(_selectedLocation!);
                        }
                      },
                      initialCameraPosition: CameraPosition(
                        target: mapLocation,
                        zoom: 17,
                      ),
                      markers: _markers,
                      onTap: _onMapTap,
                      myLocationButtonEnabled: true,
                      zoomControlsEnabled: true,
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: OutlinedButton.icon(
                        onPressed: _getCurrentLocation,
                        icon: const Icon(Icons.my_location),
                        label: const Text('Use Current Location'),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Current restaurant address',
                          style: TextStyle(fontWeight: FontWeight.w600),
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          controller: _addressController,
                          readOnly: true,
                          maxLines: 2,
                          decoration: InputDecoration(
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _cityController,
                                readOnly: true,
                                decoration: InputDecoration(
                                  labelText: 'City',
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextField(
                                controller: _pincodeController,
                                readOnly: true,
                                decoration: InputDecoration(
                                  labelText: 'Pincode',
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _latitudeController,
                                readOnly: true,
                                decoration: InputDecoration(
                                  labelText: 'Requested latitude',
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextField(
                                controller: _longitudeController,
                                readOnly: true,
                                decoration: InputDecoration(
                                  labelText: 'Requested longitude',
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.orange.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.orange.shade200),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'FSSAI proof',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                _hasApprovedFssaiLicense && _fssaiFile == null
                                    ? 'Approved license already on file'
                                    : (_fssaiFile?.name ??
                                        'No file selected yet'),
                                style: TextStyle(color: Colors.grey.shade700),
                              ),
                              const SizedBox(height: 10),
                              OutlinedButton.icon(
                                onPressed: _pickFssaiFile,
                                icon: const Icon(Icons.upload_file),
                                label: Text(
                                  _fssaiFile == null
                                      ? (_hasApprovedFssaiLicense
                                          ? 'Replace FSSAI proof'
                                          : 'Attach FSSAI proof')
                                      : 'Change attachment',
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: Colors.blue.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.blue.shade100),
                          ),
                          child: const Text(
                            'This does not directly overwrite your restaurant location. It sends the selected latitude and longitude for admin approval and automatically opens a support ticket.',
                          ),
                        ),
                        const SizedBox(height: 24),
                        SizedBox(
                          width: double.infinity,
                          height: 54,
                          child: ElevatedButton(
                            onPressed: _isSaving ? null : _saveLocation,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: FoodFlowTheme.orange,
                              disabledBackgroundColor: FoodFlowTheme.faint,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: _isSaving
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      valueColor: AlwaysStoppedAnimation<Color>(
                                        Colors.white,
                                      ),
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text(
                                    'Apply For Location Update',
                                    style: TextStyle(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w600,
                                      color: Colors.white,
                                    ),
                                  ),
                          ),
                        ),
                        const SizedBox(height: 24),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
