// lib/screens/restaurant/profile/restaurant_profile_edit_screen.dart
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../services/api_service.dart';
import '../../../config/api_constants.dart';
import '../../../config/app_config.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../utils/currency_utils.dart';

class RestaurantProfileEditScreen extends StatefulWidget {
  const RestaurantProfileEditScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantProfileEditScreen> createState() =>
      _RestaurantProfileEditScreenState();
}

class _RestaurantProfileEditScreenState
    extends State<RestaurantProfileEditScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _isSaving = false;
  bool _isLoading = true;

  late TextEditingController _nameController;
  late TextEditingController _descriptionController;
  late TextEditingController _emailController;
  late TextEditingController _phoneController;
  late TextEditingController _minOrderController;

  XFile? _logoFile;
  XFile? _bannerFile;
  String? _logoUrl;
  String? _bannerUrl;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController();
    _descriptionController = TextEditingController();
    _emailController = TextEditingController();
    _phoneController = TextEditingController();
    _minOrderController = TextEditingController();
    _loadRestaurantInfo();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _minOrderController.dispose();
    super.dispose();
  }

  Future<void> _loadRestaurantInfo() async {
    try {
      final response = await _api.get(ApiConstants.restaurantSettings);
      if (response['success'] == true) {
        final data = Map<String, dynamic>.from(response['data'] ?? {});
        if (!mounted) return;
        setState(() {
          _nameController.text = data['name'] ?? '';
          _descriptionController.text = data['description'] ?? '';
          _emailController.text = data['email'] ?? '';
          _phoneController.text = data['phone'] ?? '';
          _minOrderController.text = (data['min_order_amount'] ?? 0).toString();
          _logoUrl = _resolveImageUrl(data['logo_image'] ?? data['image_url']);
          _bannerUrl =
              _resolveImageUrl(data['banner_image'] ?? data['banner_url']);
        });
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading profile: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _pickLogo() async {
    await _pickImage(
      onSelected: (image) => setState(() => _logoFile = image),
    );
  }

  Future<void> _pickBanner() async {
    await _pickImage(
      onSelected: (image) => setState(() => _bannerFile = image),
    );
  }

  Future<void> _pickImage({
    required ValueChanged<XFile> onSelected,
  }) async {
    try {
      final ImagePicker picker = ImagePicker();
      final XFile? image =
          await picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
      if (image != null) onSelected(image);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error picking image: $e')),
      );
    }
  }

  Future<void> _saveProfile() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);

    try {
      final data = {
        'name': _nameController.text.trim(),
        'description': _descriptionController.text.trim(),
        'email': _emailController.text.trim(),
        'phone': _phoneController.text.trim(),
      };
      final files = <String, String>{
        if (_logoFile != null) 'logo_image': _logoFile!.path,
        if (_bannerFile != null) 'banner_image': _bannerFile!.path,
      };

      final response = files.isEmpty
          ? await _api.post(ApiConstants.restaurantSettings, data: data)
          : await _api.postMultipart(
              ApiConstants.restaurantSettings,
              fields: data.map((key, value) => MapEntry(key, value.toString())),
              files: files,
            );

      if (response['success'] == true) {
        if (mounted) {
          setState(() {
            _logoUrl = _logoFile != null ? _logoFile!.path : _logoUrl;
            _bannerUrl = _bannerFile != null ? _bannerFile!.path : _bannerUrl;
            _logoFile = null;
            _bannerFile = null;
          });
        }
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Profile updated successfully'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      } else {
        throw Exception(response['message'] ?? 'Failed to update profile');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    }

    if (mounted) {
      setState(() => _isSaving = false);
    }
  }

  String? _resolveImageUrl(dynamic value) {
    final raw = value?.toString().trim();
    if (raw == null || raw.isEmpty) return null;
    if (raw.startsWith('http://') || raw.startsWith('https://')) return raw;
    return '${AppConfig.apiBaseUrl}/storage/$raw';
  }

  Widget _buildPreviewImage({
    required XFile? file,
    required String? imageUrl,
    required Widget placeholder,
    BoxFit fit = BoxFit.cover,
  }) {
    if (file != null) {
      return Image.file(File(file.path), fit: fit);
    }
    if (imageUrl != null && imageUrl.isNotEmpty) {
      return Image.network(
        imageUrl,
        fit: fit,
        errorBuilder: (_, __, ___) => placeholder,
      );
    }
    return placeholder;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Edit Profile'),
        elevation: 0,
        backgroundColor: FoodFlowTheme.orange,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              GestureDetector(
                onTap: _pickBanner,
                child: Container(
                  height: 176,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    color: Colors.grey.shade100,
                    border: Border.all(color: FoodFlowTheme.orange, width: 1.2),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      _buildPreviewImage(
                        file: _bannerFile,
                        imageUrl: _bannerUrl,
                        placeholder: Container(
                          color: const Color(0xFFF1F8F5),
                          alignment: Alignment.center,
                          child: Icon(
                            Icons.add_photo_alternate_outlined,
                            size: 42,
                            color: FoodFlowTheme.orange,
                          ),
                        ),
                      ),
                      Positioned(
                        left: 14,
                        right: 14,
                        bottom: 14,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.black.withOpacity(0.55),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Text(
                            'Tap to update banner preview',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Center(
                child: Column(
                  children: [
                    GestureDetector(
                      onTap: _pickLogo,
                      child: Container(
                        width: 124,
                        height: 124,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.grey.shade100,
                          border: Border.all(
                            color: FoodFlowTheme.orange,
                            width: 2,
                          ),
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: _buildPreviewImage(
                          file: _logoFile,
                          imageUrl: _logoUrl,
                          placeholder: Icon(
                            Icons.camera_alt,
                            size: 48,
                            color: FoodFlowTheme.orange,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Tap to upload logo',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey.shade600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              // Restaurant Name
              const Text(
                'Restaurant Name',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _nameController,
                decoration: InputDecoration(
                  hintText: 'Enter restaurant name',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                validator: (value) {
                  if (value?.isEmpty ?? true) {
                    return 'Please enter restaurant name';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              // Description
              const Text(
                'Description',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _descriptionController,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Enter restaurant description',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              // Email
              const Text(
                'Email',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _emailController,
                decoration: InputDecoration(
                  hintText: 'Enter email',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                validator: (value) {
                  if (value?.isEmpty ?? true) {
                    return 'Please enter email';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              // Phone
              const Text(
                'Phone Number',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _phoneController,
                decoration: InputDecoration(
                  hintText: 'Enter phone number',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                validator: (value) {
                  if (value?.isEmpty ?? true) {
                    return 'Please enter phone number';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              // Minimum Order Amount
              const Text(
                'Minimum Order Amount',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _minOrderController,
                enabled: false,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  hintText: 'Minimum order amount',
                  helperText: 'Set by admin',
                  prefixText: currencyInputPrefix(context),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 32),
              // Save Button
              SizedBox(
                width: double.infinity,
                height: 54,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _saveProfile,
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
                            valueColor:
                                AlwaysStoppedAnimation<Color>(Colors.white),
                            strokeWidth: 2,
                          ),
                        )
                      : const Text(
                          'Save Changes',
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
      ),
    );
  }
}
