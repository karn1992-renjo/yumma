import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../../providers/auth_provider.dart';
import '../../../../widgets/common/app_cached_image.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

class EditProfileV2 extends StatefulWidget {
  const EditProfileV2({super.key});

  @override
  State<EditProfileV2> createState() => _EditProfileV2State();
}

class _EditProfileV2State extends State<EditProfileV2> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  String? _pickedImagePath;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final u = context.read<AuthProvider>().currentUser;
    _name.text = u?.name ?? '';
    _phone.text = u?.phone ?? '';
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pick() async {
    try {
      final x = await ImagePicker()
          .pickImage(source: ImageSource.gallery, imageQuality: 80);
      if (x != null) setState(() => _pickedImagePath = x.path);
    } catch (_) {}
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      _toast('Name cannot be empty');
      return;
    }
    setState(() => _saving = true);
    final ok = await context.read<AuthProvider>().updateProfile(
          name: _name.text.trim(),
          phone: _phone.text.trim(),
          profileImagePath: _pickedImagePath,
        );
    if (!mounted) return;
    setState(() => _saving = false);
    if (ok) {
      _toast('Profile updated');
      Navigator.of(context).maybePop();
    } else {
      _toast('Could not update profile');
    }
  }

  void _toast(String m) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(m)));
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final u = context.watch<AuthProvider>().currentUser;
    final img = u?.profileImage?.trim() ?? '';
    return V2Scaffold(
      title: 'Edit Profile',
      showBack: true,
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 34),
        children: [
          Center(
            child: V2Tappable(
              onTap: _pick,
              child: Stack(
                children: [
                  Container(
                    width: 96,
                    height: 96,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: p.accent.withOpacity(0.18),
                      border: Border.all(color: p.accent.withOpacity(0.4)),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: _pickedImagePath != null
                        ? Image.file(
                            File(_pickedImagePath!),
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _initial(u?.name),
                          )
                        : (img.isNotEmpty
                            ? AppCachedImage(imageUrl: img, fit: BoxFit.cover)
                            : _initial(u?.name)),
                  ),
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      padding: const EdgeInsets.all(7),
                      decoration: BoxDecoration(
                        color: p.accent,
                        shape: BoxShape.circle,
                        border: Border.all(color: p.bgTop, width: 2),
                      ),
                      child: const Icon(Icons.camera_alt_rounded,
                          size: 14, color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          _field(context, 'Full name', _name, TextInputType.name),
          const SizedBox(height: 14),
          _field(context, 'Phone number', _phone, TextInputType.phone),
          const SizedBox(height: 10),
          Text(
            'Email: ${u?.email ?? '—'}',
            style: TextStyle(color: p.inkFaint, fontSize: 12),
          ),
          const SizedBox(height: 26),
          V2Tappable(
            onTap: _saving ? null : _save,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 15),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: p.accent,
                borderRadius: BorderRadius.circular(15),
              ),
              child: _saving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Text('Save changes',
                      style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 15)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _initial(String? name) {
    final p = V2Theme.of(context);
    final ch = (name ?? '').trim().isNotEmpty ? name!.trim()[0].toUpperCase() : 'S';
    return Center(
      child: Text(ch,
          style: TextStyle(
              color: p.ink, fontSize: 34, fontWeight: FontWeight.w900)),
    );
  }

  Widget _field(BuildContext context, String label, TextEditingController c,
      TextInputType type) {
    final p = V2Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: TextStyle(
                color: p.inkFaint,
                fontSize: 11.5,
                fontWeight: FontWeight.w700)),
        const SizedBox(height: 6),
        TextField(
          controller: c,
          keyboardType: type,
          keyboardAppearance: p.isDark ? Brightness.dark : Brightness.light,
          style: TextStyle(
              color: p.ink, fontSize: 15, fontWeight: FontWeight.w700),
          cursorColor: p.accent,
          decoration: InputDecoration(
            filled: true,
            fillColor:
                p.isDark ? const Color(0xFF1B2233) : const Color(0xFFF3F4F6),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(13),
              borderSide: BorderSide.none,
            ),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          ),
        ),
      ],
    );
  }
}
