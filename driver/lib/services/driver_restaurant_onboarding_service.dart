import '../config/api_constants.dart';
import 'api_service.dart';

class DriverRestaurantOnboardingService {
  DriverRestaurantOnboardingService({ApiService? api})
      : _api = api ?? ApiService();

  final ApiService _api;

  Future<Map<String, dynamic>> summary() async {
    final response = await _api.get('/driver/restaurant-onboardings/summary');
    return _dataMap(response);
  }

  Future<List<Map<String, dynamic>>> list({String? status}) async {
    final response = await _api.get(
      '/driver/restaurant-onboardings',
      queryParams: {
        if (status != null && status != 'all') 'status': status,
      },
    );
    final data = _dataMap(response);
    final items = data['data'] is List ? data['data'] as List : data['items'];
    if (items is! List) return const [];
    return items
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<Map<String, dynamic>> createDraft() async {
    final response = await _api.post('/driver/restaurant-onboardings');
    return _dataMap(response);
  }

  Future<Map<String, dynamic>> show(int id) async {
    final response = await _api.get('/driver/restaurant-onboardings/$id');
    return _dataMap(response);
  }

  Future<Map<String, dynamic>> saveDraft(
    int id,
    Map<String, dynamic> payload,
  ) async {
    final response = await _api.post(
      '/driver/restaurant-onboardings/$id/draft',
      data: payload,
    );
    return _dataMap(response);
  }

  Future<Map<String, dynamic>> submit(
    int id,
    Map<String, dynamic> payload, {
    Map<String, String>? files,
  }) async {
    final endpoint = '/driver/restaurant-onboardings/$id/submit';
    final response = files == null || files.isEmpty
        ? await _api.post(endpoint, data: payload)
        : await _api.postMultipart(
            endpoint,
            fields: payload.map(
              (key, value) => MapEntry(
                key,
                value is bool ? (value ? '1' : '0') : value?.toString() ?? '',
              ),
            ),
            files: files,
          );
    return _dataMap(response);
  }

  Future<List<Map<String, dynamic>>> cuisines() async {
    final response = await _api.get(ApiConstants.popularCuisines);
    final data = response is Map ? response['data'] : null;
    if (data is! List) return const [];
    return data
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Future<Map<String, dynamic>> sendOwnerOtp(int id, String phone) async {
    final response = await _api.post(
      '/driver/restaurant-onboardings/$id/owner-otp/send',
      data: {'phone': phone},
    );
    return _dataMap(response);
  }

  Future<Map<String, dynamic>> verifyOwnerOtp(
    int id,
    String phone,
    String otp,
  ) async {
    final response = await _api.post(
      '/driver/restaurant-onboardings/$id/owner-otp/verify',
      data: {'phone': phone, 'otp': otp},
    );
    return _dataMap(response);
  }

  Map<String, dynamic> _dataMap(dynamic response) {
    if (response is Map) {
      final data = response['data'];
      if (data is Map) return Map<String, dynamic>.from(data);
      return Map<String, dynamic>.from(response);
    }
    return <String, dynamic>{};
  }
}
