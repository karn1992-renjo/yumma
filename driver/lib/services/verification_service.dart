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
/// Verification Suite - used while the driver is still filling the
/// registration form so obviously wrong numbers/documents are caught before
/// the application is even submitted for admin review.
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

  Future<DocVerifyResult> verifyVehicleRc(String vehicleNumber) async {
    try {
      final response = await _api.post(
        ApiConstants.verifyVehicleRc,
        data: {'vehicle_number': vehicleNumber},
      );
      return _fromResponse(response);
    } catch (e) {
      return _fromError(e);
    }
  }

  Future<DocVerifyResult> verifyDrivingLicense({
    required String licenseNumber,
    required String dob,
  }) async {
    try {
      final response = await _api.post(
        ApiConstants.verifyDrivingLicense,
        data: {'license_number': licenseNumber, 'dob': dob},
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

  Future<DocVerifyResult> verifyAadhaarDocument(
    String frontPath, {
    String? backPath,
  }) async {
    try {
      final response = await _api.postMultipart(
        ApiConstants.verifyAadhaarDocument,
        files: {
          'front_image': frontPath,
          if (backPath != null) 'back_image': backPath,
        },
      );
      return _fromResponse(response);
    } catch (e) {
      return _fromError(e);
    }
  }
}
