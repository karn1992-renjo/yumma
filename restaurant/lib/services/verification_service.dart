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

  DocVerifyResult _fromResponse(dynamic response) {
    final status = response is Map ? response['status']?.toString() : null;
    final message = response is Map ? response['message']?.toString() : null;
    switch (status) {
      case 'verified':
      case 'checked':
        return DocVerifyResult(DocVerifyStatus.verified, message);
      case 'invalid':
        return DocVerifyResult(DocVerifyStatus.invalid, message);
      case 'not_configured':
        return DocVerifyResult(DocVerifyStatus.unconfigured, message);
      default:
        return DocVerifyResult(DocVerifyStatus.unknown, message);
    }
  }

  DocVerifyResult _fromError(Object error) {
    final message = error.toString().replaceFirst('Exception: ', '');
    return DocVerifyResult(DocVerifyStatus.unknown, message);
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
