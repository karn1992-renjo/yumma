// lib/utils/currency_utils.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';

String _globalCurrencySymbol = 'Rs';
int _globalCurrencyDecimals = 2;

String normalizeCurrencySymbol(String? value) {
  final symbol = value?.trim();
  if (symbol == null || symbol.isEmpty || symbol == 'Ã¢â€šÂ¹') {
    return 'Rs';
  }
  return symbol;
}

void setGlobalCurrencySymbol(String? value) {
  _globalCurrencySymbol = normalizeCurrencySymbol(value);
}

String getGlobalCurrencySymbol() => _globalCurrencySymbol;

void setGlobalCurrencyDecimals(dynamic value) {
  _globalCurrencyDecimals = _normalizeCurrencyDecimals(value);
}

int getGlobalCurrencyDecimals() => _globalCurrencyDecimals;

int _normalizeCurrencyDecimals(dynamic value) {
  final decimals = value is int ? value : int.tryParse(value?.toString() ?? '');
  return (decimals ?? 2).clamp(2, 5).toInt();
}

/// Get the currency symbol from the current user settings
String getCurrencySymbol(BuildContext context) {
  try {
    final authProvider = context.read<AuthProvider>();
    return normalizeCurrencySymbol(
      authProvider.currentUser?.currencySymbol ?? _globalCurrencySymbol,
    );
  } catch (_) {
    return _globalCurrencySymbol;
  }
}

/// Get the currency code from the current user settings
String getCurrencyCode(BuildContext context) {
  try {
    final authProvider = context.read<AuthProvider>();
    return authProvider.currentUser?.currencyCode ?? 'INR';
  } catch (_) {
    return 'INR';
  }
}

int getCurrencyDecimals(BuildContext context) {
  try {
    final authProvider = context.read<AuthProvider>();
    return authProvider.currentUser?.currencyDecimals ?? _globalCurrencyDecimals;
  } catch (_) {
    return _globalCurrencyDecimals;
  }
}

/// Format price with dynamic currency symbol
String formatCurrency(BuildContext context, num amount) {
  final symbol = getCurrencySymbol(context);
  return '$symbol${amount.toStringAsFixed(getCurrencyDecimals(context))}';
}

/// Format price with dynamic currency symbol and decimal places
String formatCurrencyWithDecimals(
  BuildContext context,
  num amount, {
  int? decimals,
}) {
  final symbol = getCurrencySymbol(context);
  return '$symbol${amount.toStringAsFixed(_normalizeCurrencyDecimals(decimals ?? getCurrencyDecimals(context)))}';
}

String formatGlobalCurrency(num amount, {int? decimals}) {
  final symbol = getGlobalCurrencySymbol();
  return '$symbol${amount.toStringAsFixed(_normalizeCurrencyDecimals(decimals ?? _globalCurrencyDecimals))}';
}

String currencyInputPrefix(BuildContext context) {
  final symbol = getCurrencySymbol(context);
  return symbol.endsWith(' ') ? symbol : '$symbol ';
}
