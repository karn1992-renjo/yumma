// lib/screens/auth/modern_login_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter/gestures.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../config/app_config.dart';
import '../../providers/auth_provider.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/common/loading_button.dart';
import 'register_screen.dart';

class ModernLoginScreen extends StatefulWidget {
  const ModernLoginScreen({Key? key}) : super(key: key);

  @override
  State<ModernLoginScreen> createState() => _ModernLoginScreenState();
}

class _ModernLoginScreenState extends State<ModernLoginScreen>
    with TickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _phoneController = TextEditingController();
  final _otpController = TextEditingController();

  bool _isPasswordVisible = false;
  bool _useOtp = true;
  bool _otpSent = false;
  bool _agreedToTerms = false;
  bool _isLoading = false;

  late String _selectedRole = AppConfig.isDriverApp
      ? 'driver'
      : AppConfig.isRestaurantApp
          ? 'restaurant'
          : 'customer';

  late AnimationController _fadeController;
  late AnimationController _slideController;
  late Animation<double> _fadeAnimation;
  late Animation<Offset> _slideAnimation;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );
    _slideController = AnimationController(
      duration: const Duration(milliseconds: 800),
      vsync: this,
    );

    _fadeAnimation = Tween<double>(begin: 0, end: 1).animate(
      CurvedAnimation(parent: _fadeController, curve: Curves.easeIn),
    );
    _slideAnimation = Tween<Offset>(begin: const Offset(0, 0.3), end: Offset.zero)
        .animate(
      CurvedAnimation(parent: _slideController, curve: Curves.easeOut),
    );

    _fadeController.forward();
    _slideController.forward();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _phoneController.dispose();
    _otpController.dispose();
    _fadeController.dispose();
    _slideController.dispose();
    super.dispose();
  }

  Future<void> _requestOtp() async {
    if (_isLoading) return;
    if (_phoneController.text.trim().length < 10) {
      _showError('Please enter a valid mobile number');
      return;
    }

    setState(() => _isLoading = true);
    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final success = await authProvider.sendLoginOtp(
        phone: _phoneController.text.trim(),
        role: _selectedRole,
      );

      if (!mounted) return;

      if (success) {
        setState(() => _otpSent = true);
        _showSuccess('OTP sent to your mobile number');
      } else {
        final error = authProvider.error ?? 'Failed to send OTP';
        if (_isAccountMissingError(error)) {
          _goToRegister();
          return;
        }
        _showError(error);
      }
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _handleLogin() async {
    if (_isLoading) return;
    if (!_formKey.currentState!.validate()) return;

    if (!_agreedToTerms) {
      _showError('Please agree to the terms and conditions');
      return;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    bool success;

    if (_useOtp) {
      if (!_otpSent) {
        await _requestOtp();
        return;
      }
      setState(() => _isLoading = true);
      success = await authProvider.verifyLoginOtp(
        phone: _phoneController.text.trim(),
        otp: _otpController.text.trim(),
        role: _selectedRole,
      );
    } else {
      setState(() => _isLoading = true);
      success = await authProvider.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
        role: _selectedRole,
      );
    }

    if (!mounted) return;
    setState(() => _isLoading = false);

    if (success) {
      _showSuccess('Login successful!');
      if (mounted) {
        Navigator.of(context).pushReplacementNamed('/home');
      }
    } else {
      _showError(authProvider.error ?? 'Login failed');
    }
  }

  void _goToRegister() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const RegisterScreen()),
    );
  }

  bool _isAccountMissingError(String error) {
    return error.toLowerCase().contains('account') ||
        error.toLowerCase().contains('not found') ||
        error.toLowerCase().contains('not registered');
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red.shade600,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.all(16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  void _showSuccess(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green.shade600,
        behavior: SnackBarBehavior.floating,
        margin: const EdgeInsets.all(16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF667EEA).withOpacity(0.95),
              Color(0xFF764BA2).withOpacity(0.95),
            ],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            physics: const BouncingScrollPhysics(),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 30),
                  FadeTransition(
                    opacity: _fadeAnimation,
                    child: SlideTransition(
                      position: _slideAnimation,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Logo and Title
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.2),
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Icon(
                              Icons.restaurant,
                              color: Colors.white,
                              size: 32,
                            ),
                          ),
                          const SizedBox(height: 24),
                          Text(
                            'Welcome Back',
                            style: GoogleFonts.poppins(
                              fontSize: 32,
                              fontWeight: FontWeight.bold,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _getSubtitle(),
                            style: GoogleFonts.poppins(
                              fontSize: 16,
                              color: Colors.white.withOpacity(0.8),
                              fontWeight: FontWeight.w400,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 40),
                  // Login Form
                  Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Toggle between OTP and Password
                        Container(
                          decoration: BoxDecoration(
                            color: Colors.white.withOpacity(0.15),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: Colors.white.withOpacity(0.3),
                              width: 1,
                            ),
                          ),
                          padding: const EdgeInsets.all(4),
                          child: Row(
                            children: [
                              Expanded(
                                child: GestureDetector(
                                  onTap: () {
                                    setState(() {
                                      _useOtp = true;
                                      _otpSent = false;
                                    });
                                  },
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 300),
                                    decoration: BoxDecoration(
                                      color: _useOtp
                                          ? Colors.white
                                          : Colors.transparent,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    padding: const EdgeInsets.symmetric(
                                        vertical: 12),
                                    child: Text(
                                      'OTP',
                                      textAlign: TextAlign.center,
                                      style: GoogleFonts.poppins(
                                        fontWeight: FontWeight.w600,
                                        color: _useOtp
                                            ? Color(0xFF667EEA)
                                            : Colors.white,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                              Expanded(
                                child: GestureDetector(
                                  onTap: () {
                                    setState(() => _useOtp = false);
                                  },
                                  child: AnimatedContainer(
                                    duration: const Duration(milliseconds: 300),
                                    decoration: BoxDecoration(
                                      color: !_useOtp
                                          ? Colors.white
                                          : Colors.transparent,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    padding: const EdgeInsets.symmetric(
                                        vertical: 12),
                                    child: Text(
                                      'Password',
                                      textAlign: TextAlign.center,
                                      style: GoogleFonts.poppins(
                                        fontWeight: FontWeight.w600,
                                        color: !_useOtp
                                            ? Color(0xFF667EEA)
                                            : Colors.white,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 24),

                        // Input Fields
                        if (_useOtp) ...[
                          // Phone Input
                          _buildInputField(
                            controller: _phoneController,
                            label: 'Mobile Number',
                            hint: 'Enter your 10-digit mobile number',
                            keyboardType: TextInputType.phone,
                            prefixIcon: Icons.phone,
                            validator: (value) {
                              if (value?.isEmpty ?? true) {
                                return 'Mobile number is required';
                              }
                              if (value!.length < 10) {
                                return 'Mobile number must be at least 10 digits';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),
                          if (_otpSent) ...[
                            _buildInputField(
                              controller: _otpController,
                              label: 'OTP',
                              hint: 'Enter 6-digit OTP',
                              keyboardType: TextInputType.number,
                              prefixIcon: Icons.security,
                              maxLength: 6,
                              validator: (value) {
                                if (value?.isEmpty ?? true) {
                                  return 'OTP is required';
                                }
                                if (value!.length < 6) {
                                  return 'OTP must be 6 digits';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 12),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton(
                                onPressed: () {
                                  setState(() => _otpSent = false);
                                  _phoneController.clear();
                                },
                                child: Text(
                                  'Change number?',
                                  style: GoogleFonts.poppins(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ] else ...[
                          // Email Input
                          _buildInputField(
                            controller: _emailController,
                            label: 'Email Address',
                            hint: 'your@email.com',
                            keyboardType: TextInputType.emailAddress,
                            prefixIcon: Icons.email_outlined,
                            validator: (value) {
                              if (value?.isEmpty ?? true) {
                                return 'Email is required';
                              }
                              if (!RegExp(r'^[^@]+@[^@]+\.[^@]+')
                                  .hasMatch(value!)) {
                                return 'Enter a valid email';
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),
                          // Password Input
                          _buildInputField(
                            controller: _passwordController,
                            label: 'Password',
                            hint: 'Enter your password',
                            keyboardType: TextInputType.visiblePassword,
                            obscureText: !_isPasswordVisible,
                            prefixIcon: Icons.lock_outline,
                            suffixIcon: _isPasswordVisible
                                ? Icons.visibility
                                : Icons.visibility_off,
                            onSuffixIconPressed: () {
                              setState(
                                () => _isPasswordVisible = !_isPasswordVisible,
                              );
                            },
                            validator: (value) {
                              if (value?.isEmpty ?? true) {
                                return 'Password is required';
                              }
                              if (value!.length < 6) {
                                return 'Password must be at least 6 characters';
                              }
                              return null;
                            },
                          ),
                        ],
                        const SizedBox(height: 20),

                        // Terms and Conditions Checkbox
                        Row(
                          children: [
                            Checkbox(
                              value: _agreedToTerms,
                              onChanged: (value) {
                                setState(() => _agreedToTerms = value ?? false);
                              },
                              fillColor: MaterialStateProperty.all(
                                _agreedToTerms
                                    ? Colors.white
                                    : Colors.white.withOpacity(0.3),
                              ),
                              side: BorderSide(
                                color: Colors.white.withOpacity(0.5),
                                width: 2,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(6),
                              ),
                            ),
                            Expanded(
                              child: RichText(
                                text: TextSpan(
                                  children: [
                                    TextSpan(
                                      text: 'I agree to the ',
                                      style: GoogleFonts.poppins(
                                        color: Colors.white.withOpacity(0.8),
                                        fontSize: 13,
                                      ),
                                    ),
                                    TextSpan(
                                      text: 'Terms & Conditions',
                                      style: GoogleFonts.poppins(
                                        color: Colors.white,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),

                        // Login Button
                        SizedBox(
                          width: double.infinity,
                          height: 56,
                          child: ElevatedButton(
                            onPressed: _isLoading ? null : _handleLogin,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                              elevation: 0,
                            ),
                            child: _isLoading
                                ? SizedBox(
                                    height: 24,
                                    width: 24,
                                    child: CircularProgressIndicator(
                                      valueColor: AlwaysStoppedAnimation(
                                        Color(0xFF667EEA),
                                      ),
                                      strokeWidth: 3,
                                    ),
                                  )
                                : Text(
                                    _otpSent && _useOtp
                                        ? 'Verify OTP'
                                        : _useOtp
                                            ? 'Send OTP'
                                            : 'Continue',
                                    style: GoogleFonts.poppins(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w600,
                                      color: Color(0xFF667EEA),
                                    ),
                                  ),
                          ),
                        ),
                        const SizedBox(height: 16),

                        // Register Link
                        Center(
                          child: RichText(
                            text: TextSpan(
                              children: [
                                TextSpan(
                                  text: "Don't have an account? ",
                                  style: GoogleFonts.poppins(
                                    color: Colors.white.withOpacity(0.8),
                                    fontSize: 14,
                                  ),
                                ),
                                TextSpan(
                                  text: 'Sign Up',
                                  style: GoogleFonts.poppins(
                                    color: Colors.white,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    decoration: TextDecoration.underline,
                                  ),
                                  recognizer: TapGestureRecognizer()
                                    ..onTap = _goToRegister,
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 30),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  String _getSubtitle() {
    if (AppConfig.isDriverApp) {
      return 'Deliver food and earn money';
    } else if (AppConfig.isRestaurantApp) {
      return 'Manage your restaurant';
    } else {
      return 'Order delicious food delivered fast';
    }
  }

  Widget _buildInputField({
    required TextEditingController controller,
    required String label,
    required String hint,
    required TextInputType keyboardType,
    required IconData prefixIcon,
    bool obscureText = false,
    IconData? suffixIcon,
    VoidCallback? onSuffixIconPressed,
    String? Function(String?)? validator,
    int? maxLength,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.poppins(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: Colors.white,
          ),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          obscureText: obscureText,
          maxLength: maxLength,
          validator: validator,
          style: GoogleFonts.poppins(
            color: Colors.white,
            fontSize: 16,
          ),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: GoogleFonts.poppins(
              color: Colors.white.withOpacity(0.5),
              fontSize: 14,
            ),
            prefixIcon: Icon(
              prefixIcon,
              color: Colors.white.withOpacity(0.7),
            ),
            suffixIcon: suffixIcon != null
                ? IconButton(
                    icon: Icon(
                      suffixIcon,
                      color: Colors.white.withOpacity(0.7),
                    ),
                    onPressed: onSuffixIconPressed,
                  )
                : null,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                color: Colors.white.withOpacity(0.3),
                width: 2,
              ),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                color: Colors.white.withOpacity(0.3),
                width: 2,
              ),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(
                color: Colors.white,
                width: 2,
              ),
            ),
            errorBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: BorderSide(
                color: Colors.red.withOpacity(0.7),
                width: 2,
              ),
            ),
            filled: true,
            fillColor: Colors.white.withOpacity(0.1),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 16,
            ),
            counterText: '',
          ),
        ),
      ],
    );
  }
}
