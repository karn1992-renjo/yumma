import 'package:shared_preferences/shared_preferences.dart';

import '../config/api_constants.dart';
import 'api_service.dart';

class PartnerApplicationService {
  PartnerApplicationService._();

  static final PartnerApplicationService instance = PartnerApplicationService._();
  final ApiService _api = ApiService();

  static const String _applicationNumberKey =
      'pending_partner_application_number';

  Future<Map<String, dynamic>> submitApplication({
    required Map<String, String> fields,
    Map<String, String>? files,
  }) async {
    final response = await _api.postMultipart(
      ApiConstants.partnerApplications,
      fields: fields,
      files: files,
    );

    final data = Map<String, dynamic>.from(response['data'] ?? const {});
    final applicationNumber = data['application_number']?.toString();
    if (applicationNumber != null && applicationNumber.isNotEmpty) {
      await saveApplicationNumber(applicationNumber);
    }

    return response;
  }

  Future<Map<String, dynamic>> fetchStatus(String applicationNumber) async {
    return await _api.get(
      '${ApiConstants.partnerApplications}/$applicationNumber',
    );
  }

  Future<List<Map<String, dynamic>>> fetchDeliveryAreas() async {
    final response = await _api.get(ApiConstants.activeDeliveryAreas);
    final raw = response['data'];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<void> saveApplicationNumber(String applicationNumber) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_applicationNumberKey, applicationNumber);
  }

  Future<String?> getSavedApplicationNumber() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_applicationNumberKey);
  }

  Future<void> clearSavedApplicationNumber() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_applicationNumberKey);
  }
}
