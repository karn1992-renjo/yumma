import 'package:flutter/foundation.dart';

import '../models/user.dart';

class PhoneNumberUtils {
  static const String invalidMobileMessage =
      'Enter a valid mobile number for the selected country code.';

  // Indian mobile numbers are always exactly 10 digits. Used below to tell
  // a number that already carries its country code apart from a bare local
  // number that merely happens to start with the same digits (e.g.
  // 9155308530 starts with "91" but is NOT "+91" + "55308530").
  static const int _localNumberLength = 10;

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

    // A bare prefix check (startsWith) alone is wrong: a 10-digit Indian
    // mobile number can legitimately start with the same digits as the
    // country code (e.g. 9155308530 starts with "91"), which would
    // otherwise be mistaken for an already-prefixed number and lose its
    // real country code (producing "+9155308530" instead of the correct
    // "+919155308530"). Require the digit count to match
    // country-code-length + local-number-length too.
    final hasCountryCode = countryDigits.isNotEmpty &&
        digits.startsWith(countryDigits) &&
        digits.length == countryDigits.length + _localNumberLength;
    final localDigits = hasCountryCode
        ? digits.substring(countryDigits.length)
        : digits;

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

  static String? validateMobile(String? value, {String? countryCode}) {
    try {
      normalizeMobile(value ?? '', countryCode: countryCode);
      return null;
    } on FormatException catch (error) {
      return error.message;
    }
  }

  static String? validateIndianMobile(String? value, {String? countryCode}) {
    return validateMobile(value, countryCode: countryCode);
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
