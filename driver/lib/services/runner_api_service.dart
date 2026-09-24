import 'api_service.dart';

/// swaad-only: API surface for a movie-theatre runner claiming and
/// completing in-seat delivery tickets. Status-advance and OTP-completion
/// reuse the same generic, role-agnostic driver endpoints a road driver
/// uses (both scope purely by `driver_id = auth()->id()`); only the
/// claim-from-a-shared-queue step is genuinely new, since runners are never
/// candidates for the GPS-radius auto-assign a road driver goes through.
class RunnerApiService {
  RunnerApiService._();
  static final RunnerApiService instance = RunnerApiService._();

  final ApiService _api = ApiService();

  Future<Map<String, dynamic>> fetchQueue() async {
    final result = await _api.get('/theatre-runner/queue');
    final data = Map<String, dynamic>.from(result['data'] as Map? ?? {});
    return {
      'claimable': (data['claimable'] as List? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
      'mine': (data['mine'] as List? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
    };
  }

  Future<void> claim(int orderId) async {
    await _api.post('/theatre-runner/orders/$orderId/claim');
  }

  Future<void> advanceStatus(int orderId, String status) async {
    await _api.post('/driver/orders/$orderId/status', data: {'status': status});
  }

  Future<void> completeDelivery({
    required int orderId,
    required String otp,
    required String paymentMode,
    bool cashCollected = false,
  }) async {
    await _api.post('/delivery/verify-otp/$orderId', data: {
      'otp': otp,
      'payment_mode': paymentMode,
      if (paymentMode == 'cash') 'cash_collected': cashCollected,
    });
  }
}
