import 'package:flutter/foundation.dart';

import '../models/user.dart';

class PhoneNumberUtils {
  static const String invalidMobileMessage =
      'Enter a valid mobile number for the selected country code.';

  static PhoneNormalizationResult normalizeMobile(
    String input, {
    String? countryCode,
    bool log = false,
  }) {
    final selectedCountryCode = User.normalizeMobileCountryCode(
      countryCode ?? User.defaultMobileCountryCodeFallback,
    );
    final raw = input.trim();
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    final sanitized = digits;
    final countryDigits = selectedCountryCode.replaceAll(RegExp(r'\D'), '');

    if (RegExp(r'[A-Za-z]').hasMatch(raw)) {
      throw const FormatException(invalidMobileMessage);
    }

    if (digits.isEmpty) {
      throw const FormatException(invalidMobileMessage);
    }

    final hasCountryCode =
        countryDigits.isNotEmpty && digits.startsWith(countryDigits);
    var localDigits = hasCountryCode
        ? digits.substring(countryDigits.length)
        : digits.replaceFirst(RegExp(r'^0+'), '');

    if (localDigits.startsWith('0')) {
      localDigits = localDigits.replaceFirst(RegExp(r'^0+'), '');
    }

    if (selectedCountryCode == '+91' &&
        !RegExp(r'^[6-9]\d{9}$').hasMatch(localDigits)) {
      throw const FormatException(invalidMobileMessage);
    }

    if (localDigits.isEmpty) {
      throw const FormatException(invalidMobileMessage);
    }

    final normalized = '$selectedCountryCode$localDigits';

    final result = PhoneNormalizationResult(
      selectedCountryCode: selectedCountryCode,
      rawInput: raw,
      sanitizedNumber: sanitized,
      localNumber: localDigits,
      normalizedNumber: normalized,
    );
    if (log) result.log();
    return result;
  }

  static String? validateIndianMobile(String? value, {String? countryCode}) {
    try {
      normalizeMobile(value ?? '', countryCode: countryCode);
      return null;
    } on FormatException catch (error) {
      return error.message;
    }
  }

  static String localMobile(
    String input, {
    String? countryCode,
  }) {
    return normalizeMobile(input, countryCode: countryCode).localNumber;
  }

  static String sanitizedDigits(String input) {
    return input.replaceAll(RegExp(r'\D'), '');
  }
}

class PhoneNormalizationResult {
  const PhoneNormalizationResult({
    required this.selectedCountryCode,
    required this.rawInput,
    required this.sanitizedNumber,
    required this.localNumber,
    required this.normalizedNumber,
  });

  final String selectedCountryCode;
  final String rawInput;
  final String sanitizedNumber;
  final String localNumber;
  final String normalizedNumber;

  void log() {
    debugPrint(
      'Phone normalization -> selected_country_code: $selectedCountryCode, '
      'raw_input: $rawInput, sanitized_number: $sanitizedNumber, '
      'final_normalized_number: $normalizedNumber',
    );
  }
}
