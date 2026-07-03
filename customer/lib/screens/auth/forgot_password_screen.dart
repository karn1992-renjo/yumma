import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/app_branding.dart';
import '../../providers/auth_provider.dart';
import '../../services/app_branding_service.dart';
import '../../theme/brand_palette.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/auth/brand_mark.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();
  AppBranding _branding = AppBranding.fallback();

  BrandPalette get _palette => BrandPalette.fromBranding(_branding);

  @override
  void initState() {
    super.initState();
    _loadBranding();
  }

  Future<void> _loadBranding() async {
    final branding = await AppBrandingService.instance.loadBranding();
    if (!mounted) return;
    setState(() {
      _branding = branding;
    });
  }

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    final authProvider = context.read<AuthProvider>();
    final success = await authProvider.requestPasswordReset(
      email: _emailController.text.trim(),
    );

    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          success
              ? 'Password reset link sent to your email.'
              : (authProvider.error ?? 'Unable to send reset link'),
        ),
        backgroundColor: success ? _palette.primary : Colors.red,
        behavior: SnackBarBehavior.floating,
      ),
    );

    if (success) {
      Navigator.pop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Consumer<AuthProvider>(
          builder: (context, auth, _) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(28, 20, 28, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  BrandMark(branding: _branding, size: 30),
                  const SizedBox(height: 30),
                  Expanded(
                    child: SingleChildScrollView(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Forgot\nPassword',
                              style: TextStyle(
                                fontSize: 44,
                                height: 0.98,
                                fontWeight: FontWeight.w900,
                                color: Color(0xFF423E3E),
                              ),
                            ),
                            const SizedBox(height: 18),
                            const Text(
                              'Enter your email address and we will send you a password reset link.',
                              style: TextStyle(
                                color: Color(0xFF8A817C),
                                fontSize: 14,
                                height: 1.55,
                              ),
                            ),
                            const SizedBox(height: 28),
                            TextFormField(
                              controller: _emailController,
                              keyboardType: TextInputType.emailAddress,
                              decoration: InputDecoration(
                                labelText: 'Email',
                                hintText: 'Enter your email address',
                                floatingLabelBehavior:
                                    FloatingLabelBehavior.always,
                                contentPadding: const EdgeInsets.only(
                                  top: 4,
                                  bottom: 12,
                                ),
                                enabledBorder: const UnderlineInputBorder(
                                  borderSide: BorderSide(
                                    color: Color(0xFFB3ACA7),
                                  ),
                                ),
                                focusedBorder: UnderlineInputBorder(
                                  borderSide: BorderSide(
                                    color: _palette.primary,
                                    width: 1.4,
                                  ),
                                ),
                              ),
                              validator: (value) {
                                if (value == null || value.trim().isEmpty) {
                                  return 'Email is required';
                                }
                                if (!value.contains('@')) {
                                  return 'Enter a valid email';
                                }
                                return null;
                              },
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: auth.isLoading ? null : _submit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _palette.primary,
                        foregroundColor: Colors.white,
                        minimumSize: const Size.fromHeight(54),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                      child: auth.isLoading
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Send reset link'),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Center(
                    child: TextButton(
                      onPressed: () => Navigator.pop(context),
                      child: Text(
                        'Back to sign in',
                        style: TextStyle(
                          color: _palette.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
