import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/phone_number_utils.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key});

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  final ImagePicker _imagePicker = ImagePicker();
  bool _isSaving = false;
  String? _selectedProfileImagePath;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().currentUser;
    _nameController = TextEditingController(text: user?.name ?? '');
    _phoneController = TextEditingController(text: user?.phone ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _saveProfile() async {
    FocusScope.of(context).unfocus();
    if (_isSaving || !_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    final authProvider = context.read<AuthProvider>();
    final normalizedPhone = PhoneNumberUtils.normalizeMobile(
      _phoneController.text,
      log: true,
    ).normalizedNumber;
    final ok = await authProvider.updateProfile(
      name: _nameController.text.trim(),
      phone: normalizedPhone,
      profileImagePath: _selectedProfileImagePath,
    );

    if (!mounted) return;
    setState(() => _isSaving = false);

    final scheme = Theme.of(context).colorScheme;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok
              ? 'Profile updated successfully'
              : (authProvider.error ?? 'Failed to update profile'),
        ),
        backgroundColor: ok ? profileAccentColor(context) : scheme.error,
      ),
    );

    if (ok) {
      Navigator.of(context).pop(true);
    }
  }

  Future<void> _pickProfileImage(ImageSource source) async {
    try {
      final file = await _imagePicker.pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 1400,
      );
      if (file == null || !mounted) return;
      setState(() => _selectedProfileImagePath = file.path);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Unable to pick image: $e'),
          backgroundColor: Theme.of(context).colorScheme.error,
        ),
      );
    }
  }

  Future<void> _showPhotoOptions() async {
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Theme.of(context).colorScheme.scrim.withOpacity(0),
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: profileLineColor(context),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Update profile photo',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: profileTextColor(context),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Choose a profile photo that looks clear and friendly.',
                style: TextStyle(
                  color: profileMutedColor(context),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 20),
              Row(
                children: [
                  Expanded(
                    child: _PhotoActionButton(
                      icon: Icons.photo_camera_outlined,
                      label: 'Camera',
                      color: profileButtonColor(context),
                      onTap: () {
                        Navigator.pop(sheetContext);
                        _pickProfileImage(ImageSource.camera);
                      },
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _PhotoActionButton(
                      icon: Icons.photo_library_outlined,
                      label: 'Gallery',
                      color: profileButtonColor(context),
                      onTap: () {
                        Navigator.pop(sheetContext);
                        _pickProfileImage(ImageSource.gallery);
                      },
                    ),
                  ),
                ],
              ),
              if (_selectedProfileImagePath != null) ...[
                const SizedBox(height: 12),
                TextButton(
                  onPressed: () {
                    Navigator.pop(sheetContext);
                    setState(() => _selectedProfileImagePath = null);
                  },
                  style: TextButton.styleFrom(
                    foregroundColor: profileButtonColor(context),
                    textStyle: const TextStyle(
                      fontSize: 13,
                      height: 1.05,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  child: Text('Remove selected photo'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().currentUser;
    return Scaffold(
      backgroundColor: profileCanvasColor(context),
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(20, 8, 20, 14),
        child: SizedBox(
          height: 54,
          child: ElevatedButton(
            onPressed: _isSaving ? null : _saveProfile,
            style: FoodFlowTheme.zomatoPrimaryButton(
              color: profileButtonColor(context),
              foregroundColor: profileOnButtonColor(context),
              radius: 14,
            ),
            child: _isSaving
                ? SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.4,
                      color: Theme.of(context).colorScheme.surface,
                    ),
                  )
                : Text('Save Changes'),
          ),
        ),
      ),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(18, 14, 18, 120),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            children: [
              const ProfilePageTopBar(
                title: 'Edit Profile',
                subtitle: 'Update your account details',
              ),
              const SizedBox(height: 16),
              _EditProfileHero(
                user: user,
                selectedProfileImagePath: _selectedProfileImagePath,
                onAvatarTap: _showPhotoOptions,
              ),
              const SizedBox(height: 16),
              _EditProfileCard(
                title: 'Personal details',
                subtitle:
                    'These details are visible across your customer account.',
                child: Column(
                  children: [
                    _ProfileFieldLabel(label: 'Full name'),
                    TextFormField(
                      controller: _nameController,
                      textCapitalization: TextCapitalization.words,
                      decoration: _inputDecoration(
                        context,
                        hint: 'Enter your full name',
                        icon: Icons.person_outline,
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Please enter your name';
                        }
                        if (value.trim().length < 2) {
                          return 'Name is too short';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 18),
                    _ProfileFieldLabel(label: 'Phone number'),
                    TextFormField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: _inputDecoration(
                        context,
                        hint: 'Enter your phone number',
                        icon: Icons.phone_outlined,
                      ),
                      validator: (value) {
                        return PhoneNumberUtils.validateIndianMobile(value);
                      },
                    ),
                    const SizedBox(height: 18),
                    _ProfileFieldLabel(label: 'Email address'),
                    TextFormField(
                      initialValue: user?.email ?? '',
                      enabled: false,
                      decoration: _inputDecoration(
                        context,
                        hint: 'Email address',
                        icon: Icons.mail_outline,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              _EditProfileCard(
                title: 'Quick tips',
                subtitle:
                    'A complete profile helps riders, restaurants and support teams.',
                child: const Column(
                  children: [
                    _TipRow(
                      icon: Icons.call_outlined,
                      title: 'Keep your phone active',
                      subtitle: 'Delivery riders may call you for directions.',
                    ),
                    SizedBox(height: 14),
                    _TipRow(
                      icon: Icons.verified_user_outlined,
                      title: 'Use your real name',
                      subtitle:
                          'It makes order verification and support easier.',
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(
    BuildContext context, {
    required String hint,
    required IconData icon,
  }) {
    return InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, color: profileAccentColor(context)),
      filled: true,
      fillColor: Theme.of(context).colorScheme.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: profileLineColor(context)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: profileLineColor(context)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: profileButtonColor(context), width: 1.4),
      ),
      disabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: profileLineColor(context)),
      ),
    );
  }
}

class _EditProfileHero extends StatelessWidget {
  const _EditProfileHero({
    required this.user,
    required this.selectedProfileImagePath,
    required this.onAvatarTap,
  });

  final User? user;
  final String? selectedProfileImagePath;
  final VoidCallback onAvatarTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onAvatarTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
        decoration: profileSurfaceDecoration(context, radius: 20),
        child: Row(
          children: [
            _EditAvatar(
              user: user,
              selectedProfileImagePath: selectedProfileImagePath,
              onTap: onAvatarTap,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    user?.name.isNotEmpty == true ? user!.name : 'Your profile',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: profileTextColor(context),
                      fontSize: 16,
                      height: 1.1,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Tap your photo to update your profile image.',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: profileMutedColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: profileSoftColor(context),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                    color: profileAccentColor(context).withOpacity(0.24)),
              ),
              child: Icon(
                LucideIcons.pencil,
                color: profileAccentColor(context),
                size: 17,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EditAvatar extends StatelessWidget {
  const _EditAvatar({
    required this.user,
    required this.selectedProfileImagePath,
    required this.onTap,
  });

  final User? user;
  final String? selectedProfileImagePath;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    Widget avatarChild;
    if (selectedProfileImagePath != null) {
      avatarChild = Image.file(
        File(selectedProfileImagePath!),
        fit: BoxFit.cover,
      );
    } else if (user?.profileImage?.isNotEmpty == true) {
      avatarChild = AppCachedImage(
        imageUrl: user!.profileImage!,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => _AvatarFallback(name: user?.name ?? 'U'),
      );
    } else {
      avatarChild = _AvatarFallback(name: user?.name ?? 'U');
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 66,
            height: 66,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  profileSoftColor(context),
                  profileAccentColor(context).withOpacity(0.35)
                ],
              ),
            ),
            clipBehavior: Clip.antiAlias,
            child: avatarChild,
          ),
          Positioned(
            right: 0,
            bottom: 0,
            child: Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                color: profileAccentColor(context),
                shape: BoxShape.circle,
                border: Border.all(
                    color: Theme.of(context).colorScheme.surface, width: 2),
              ),
              child: Icon(
                LucideIcons.pencil,
                size: 12,
                color: Theme.of(context).colorScheme.surface,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AvatarFallback extends StatelessWidget {
  const _AvatarFallback({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? 'U' : name.trim()[0].toUpperCase();
    return Container(
      color: profileSoftColor(context),
      alignment: Alignment.center,
      child: Text(
        initial,
        style: TextStyle(
          color: profileAccentColor(context),
          fontSize: 22,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _EditProfileCard extends StatelessWidget {
  const _EditProfileCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: profileSurfaceDecoration(context, radius: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: profileTextColor(context),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: TextStyle(
              fontSize: 12,
              height: 1.35,
              color: profileMutedColor(context),
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }
}

class _ProfileFieldLabel extends StatelessWidget {
  const _ProfileFieldLabel({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Text(
          label,
          style: TextStyle(
            color: profileTextColor(context),
            fontSize: 12,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _PhotoActionButton extends StatelessWidget {
  const _PhotoActionButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: color.withOpacity(0.16)),
        ),
        child: Column(
          children: [
            Icon(icon, color: color, size: 26),
            const SizedBox(height: 8),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 13,
                height: 1.05,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TipRow extends StatelessWidget {
  const _TipRow({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: profileSoftColor(context),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: profileButtonColor(context), size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  color: profileTextColor(context),
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: TextStyle(
                  color: profileMutedColor(context),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  height: 1.3,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
