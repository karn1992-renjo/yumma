import 'dart:io';

import 'package:flutter/material.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/phone_number_utils.dart';
import '../../widgets/customer/account_chrome.dart';

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
        backgroundColor: ok ? scheme.primary : FoodFlowTheme.danger,
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
          backgroundColor: FoodFlowTheme.danger,
        ),
      );
    }
  }

  Future<void> _showPhotoOptions() async {
    final scheme = Theme.of(context).colorScheme;
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFD5DCE5),
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Update profile photo',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: FoodFlowTheme.ink,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Choose a profile photo that looks clear and friendly.',
                style: TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 13,
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
                      color: scheme.primary,
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
                      color: scheme.primary,
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
                  child: const Text('Remove selected photo'),
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
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: accountCanvas,
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(20, 8, 20, 14),
        child: SizedBox(
          height: 54,
          child: ElevatedButton(
            onPressed: _isSaving ? null : _saveProfile,
            style: ElevatedButton.styleFrom(
              backgroundColor: scheme.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
            ),
            child: _isSaving
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.4,
                      color: Colors.white,
                    ),
                  )
                : const Text(
                    'Save Changes',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
          ),
        ),
      ),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 120),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            children: [
              Row(
                children: [
                  InkWell(
                    onTap: () => Navigator.of(context).maybePop(),
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      width: 44,
                      height: 44,
                      decoration: FoodFlowTheme.softSurface(radius: 16),
                      child: const Icon(
                        Icons.arrow_back_ios_new_rounded,
                        size: 18,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Edit Profile',
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _EditProfileHero(
                user: user,
                selectedProfileImagePath: _selectedProfileImagePath,
                onAvatarTap: _showPhotoOptions,
              ),
              const SizedBox(height: 16),
              _EditProfileCard(
                title: 'Profile setup',
                subtitle:
                    'Keep your contact details updated for smooth delivery and support.',
                child: _ProfileCompletionSummary(user: user),
              ),
              const SizedBox(height: 14),
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
    final scheme = Theme.of(context).colorScheme;
    return InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, color: scheme.primary),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
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
        borderSide: BorderSide(color: scheme.primary, width: 1.4),
      ),
      disabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: accountBorder),
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
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            scheme.primary,
            Color.lerp(scheme.primary, scheme.secondary, 0.4) ?? scheme.primary,
          ],
        ),
        borderRadius: BorderRadius.circular(30),
        boxShadow: [
          BoxShadow(
            color: scheme.primary.withOpacity(0.24),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        children: [
          _EditAvatar(
            user: user,
            selectedProfileImagePath: selectedProfileImagePath,
            onTap: onAvatarTap,
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.16),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: Colors.white.withOpacity(0.14)),
                  ),
                  child: const Text(
                    'Profile details',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  user?.name.isNotEmpty == true ? user!.name : 'Your profile',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Tap your photo to update how your account appears across customer screens.',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.92),
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    height: 1.3,
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
    final scheme = Theme.of(context).colorScheme;

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
            width: 84,
            height: 84,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 3),
            ),
            clipBehavior: Clip.antiAlias,
            child: avatarChild,
          ),
          Positioned(
            right: 0,
            bottom: 0,
            child: Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: Colors.white,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
              ),
              child: Icon(
                Icons.edit_outlined,
                size: 16,
                color: scheme.primary,
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
    final scheme = Theme.of(context).colorScheme;
    final initial = name.trim().isEmpty ? 'U' : name.trim()[0].toUpperCase();
    return Container(
      color: const Color(0xFFF5F5F5),
      alignment: Alignment.center,
      child: Text(
        initial,
        style: TextStyle(
          color: scheme.primary,
          fontSize: 28,
          fontWeight: FontWeight.w900,
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
      decoration: FoodFlowTheme.elevatedCard(radius: 28),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 13,
              height: 1.35,
              color: FoodFlowTheme.muted,
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

class _ProfileCompletionSummary extends StatelessWidget {
  const _ProfileCompletionSummary({required this.user});

  final User? user;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final completion = _completion(user);

    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Profile completeness helps with smoother order delivery and customer support.',
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  height: 1.3,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: scheme.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              alignment: Alignment.center,
              child: Text(
                '${completion.toStringAsFixed(0)}%',
                style: TextStyle(
                  color: scheme.primary,
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            minHeight: 10,
            value: completion / 100,
            backgroundColor: scheme.primary.withOpacity(0.10),
            valueColor: AlwaysStoppedAnimation<Color>(scheme.primary),
          ),
        ),
      ],
    );
  }

  double _completion(User? user) {
    if (user == null) return 0;
    var completed = 0;
    const total = 3;
    if (user.name.trim().isNotEmpty && user.name != 'Guest User') completed++;
    if (user.email.trim().isNotEmpty) completed++;
    if (user.phone.trim().isNotEmpty) completed++;
    return (completed / total) * 100;
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
          style: const TextStyle(
            color: FoodFlowTheme.ink,
            fontSize: 13,
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
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(20),
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
                fontSize: 14,
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
    final scheme = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: scheme.primary.withOpacity(0.1),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: scheme.primary, size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                subtitle,
                style: const TextStyle(
                  color: FoodFlowTheme.muted,
                  fontSize: 12.5,
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
