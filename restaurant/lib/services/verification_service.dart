import '../config/api_constants.dart';
import 'api_service.dart';

enum DocVerifyStatus { checking, verified, invalid, unknown, unconfigured }

class DocVerifyResult {
  const DocVerifyResult(this.status, [this.message]);

  final DocVerifyStatus status;
  final String? message;

  bool get isVerified => status == DocVerifyStatus.verified;
  bool get isInvalid => status == DocVerifyStatus.invalid;
}

/// Realtime, pre-submission document verification against Cashfree's
/// Verification Suite - used while the restaurant owner is still filling
/// the registration form so obviously wrong GSTIN/PAN numbers are caught
/// before the application is even submitted for admin review.
class VerificationService {
  VerificationService._();

  static final VerificationService instance = VerificationService._();
  final ApiService _api = ApiService();

  String _friendlyMessage(String? raw) {
    if (raw == null || raw.trim().isEmpty) {
      return 'Not verified. It will be verified manually.';
    }
    final lower = raw.toLowerCase();
    if (lower.contains('ip') ||
        lower.contains('whitelist') ||
        lower.contains('cashfree') ||
        lower.contains('http') ||
        lower.contains('exception') ||
        lower.contains('socket') ||
        lower.contains('server') ||
        lower.contains('gateway') ||
        lower.contains('timeout') ||
        lower.contains('unauthorized') ||
        lower.contains('forbidden') ||
        lower.contains('failed') ||
        lower.contains('connection') ||
        lower.contains('credentials') ||
        lower.contains('vrs')) {
      return 'Not verified. It will be verified manually.';
    }
    return raw;
  }

  DocVerifyResult _fromResponse(dynamic response) {
    final status = response is Map ? response['status']?.toString() : null;
    final message = response is Map ? response['message']?.toString() : null;
    switch (status) {
      case 'verified':
      case 'checked':
        return DocVerifyResult(DocVerifyStatus.verified, message ?? 'Verified');
      case 'invalid':
        return DocVerifyResult(DocVerifyStatus.invalid, _friendlyMessage(message));
      case 'not_configured':
        return DocVerifyResult(DocVerifyStatus.unconfigured, message);
      default:
        return DocVerifyResult(DocVerifyStatus.unknown, _friendlyMessage(message));
    }
  }

  DocVerifyResult _fromError(Object error) {
    return const DocVerifyResult(
      DocVerifyStatus.unknown,
      'Not verified. It will be verified manually.',
    );
  }

  Future<DocVerifyResult> verifyGstin(String gstin, {String? businessName}) async {
    try {
      final response = await _api.post(
        ApiConstants.verifyGstin,
        data: {'gstin': gstin, if (businessName != null) 'business_name': businessName},
      );
      return _fromResponse(response);
    } catch (e) {
      return _fromError(e);
    }
  }

  Future<DocVerifyResult> verifyPan(String pan, {String? name}) async {
    try {
      final response = await _api.post(
        ApiConstants.verifyPan,
        data: {'pan': pan, if (name != null) 'name': name},
      );
      return _fromResponse(response);
    } catch (e) {
      return _fromError(e);
    }
  }

  Future<DocVerifyResult> verifyPanDocument(String filePath) async {
    try {
      final response = await _api.postMultipart(
        ApiConstants.verifyPanDocument,
        files: {'front_image': filePath},
      );
      return _fromResponse(response);
    } catch (e) {
      return _fromError(e);
    }
  }
}
