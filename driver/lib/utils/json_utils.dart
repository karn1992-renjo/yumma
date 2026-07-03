// lib/utils/json_utils.dart

double? parseNullableDouble(dynamic value) {
  if (value == null) return null;
  if (value is double) return value;
  if (value is int) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}

double parseDoubleValue(dynamic value, [double fallback = 0.0]) {
  return parseNullableDouble(value) ?? fallback;
}

int? parseNullableInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is double) return value.toInt();
  if (value is String) {
    final intValue = int.tryParse(value);
    if (intValue != null) return intValue;
    final doubleValue = double.tryParse(value);
    return doubleValue?.toInt();
  }
  return null;
}

int parseIntValue(dynamic value, [int fallback = 0]) {
  return parseNullableInt(value) ?? fallback;
}

bool? parseNullableBool(dynamic value) {
  if (value == null) return null;
  if (value is bool) return value;
  if (value is int) return value != 0;
  if (value is String) {
    final normalized = value.toLowerCase().trim();
    if (normalized == 'true' || normalized == '1' || normalized == 'yes') return true;
    if (normalized == 'false' || normalized == '0' || normalized == 'no') return false;
  }
  return null;
}

bool parseBoolValue(dynamic value, [bool fallback = false]) {
  return parseNullableBool(value) ?? fallback;
}
