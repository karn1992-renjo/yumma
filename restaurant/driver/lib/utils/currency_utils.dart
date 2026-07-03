import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';

const String _defaultCurrencyCode = 'INR';
const String _defaultCurrencySymbol = 'Rs';
String _globalCurrencySymbol = _defaultCurrencySymbol;
int _globalCurrencyDecimals = 2;

void setGlobalCurrencySymbol(String? value) {
  final normalized = value?.trim();
  _globalCurrencySymbol =
      normalized != null && normalized.isNotEmpty ? normalized : _defaultCurrencySymbol;
}

void setGlobalCurrencyDecimals(dynamic value) {
  _globalCurrencyDecimals = _normalizeCurrencyDecimals(value);
}

String getGlobalCurrencySymbol() => _globalCurrencySymbol;

int getGlobalCurrencyDecimals() => _globalCurrencyDecimals;

String getCurrencySymbol(BuildContext context) {
  try {
    final symbol =
        Provider.of<AuthProvider>(context, listen: false).currentUser?.currencySymbol;
    final normalized = symbol?.trim();
    return normalized != null && normalized.isNotEmpty
        ? normalized
        : _defaultCurrencySymbol;
  } catch (_) {
    return _globalCurrencySymbol;
  }
}

String getCurrencyCode(BuildContext context) {
  try {
    final code =
        Provider.of<AuthProvider>(context, listen: false).currentUser?.currencyCode;
    final normalized = code?.trim().toUpperCase();
    return normalized != null && normalized.isNotEmpty
        ? normalized
        : _defaultCurrencyCode;
  } catch (_) {
    return _defaultCurrencyCode;
  }
}

String formatCurrency(BuildContext context, num amount) {
  final symbol = getCurrencySymbol(context);
  return '$symbol${amount.toStringAsFixed(getCurrencyDecimals(context))}';
}

String formatCurrencyWithDecimals(BuildContext context, num amount,
    {int? decimals}) {
  final symbol = getCurrencySymbol(context);
  return '$symbol${amount.toStringAsFixed(_normalizeCurrencyDecimals(decimals ?? getCurrencyDecimals(context)))}';
}

String formatGlobalCurrency(num amount, {int? decimals}) {
  return '$_globalCurrencySymbol${amount.toStringAsFixed(_normalizeCurrencyDecimals(decimals ?? _globalCurrencyDecimals))}';
}

String formatCurrencyValue(BuildContext context, dynamic amount,
    {int? decimals}) {
  final value = _toNum(amount);
  return formatCurrencyWithDecimals(context, value, decimals: decimals);
}

String formatCompactCurrency(BuildContext context, num amount) {
  final symbol = getCurrencySymbol(context);
  if (amount >= 100000) return '$symbol${(amount / 100000).toStringAsFixed(1)}L';
  if (amount >= 1000) return '$symbol${(amount / 1000).toStringAsFixed(0)}k';
  return '$symbol${amount.toInt()}';
}

String currencyInputPrefix(BuildContext context) {
  final symbol = getCurrencySymbol(context);
  return symbol.endsWith(' ') ? symbol : '$symbol ';
}

num _toNum(dynamic value) {
  if (value is num) return value;
  return num.tryParse(value?.toString() ?? '') ?? 0;
}

int getCurrencyDecimals(BuildContext context) {
  try {
    final decimals =
        Provider.of<AuthProvider>(context, listen: false).currentUser?.currencyDecimals;
    return _normalizeCurrencyDecimals(decimals);
  } catch (_) {
    return _globalCurrencyDecimals;
  }
}

int _normalizeCurrencyDecimals(dynamic value) {
  final decimals = value is int ? value : int.tryParse(value?.toString() ?? '');
  return (decimals ?? 2).clamp(2, 5).toInt();
}
