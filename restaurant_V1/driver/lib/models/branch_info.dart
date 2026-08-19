import '../utils/json_utils.dart';

class BranchInfo {
  final int id;
  final String name;
  final String? code;
  final String? city;
  final String? state;
  final String? status;

  const BranchInfo({
    required this.id,
    required this.name,
    this.code,
    this.city,
    this.state,
    this.status,
  });

  factory BranchInfo.fromJson(Map<String, dynamic> json) {
    return BranchInfo(
      id: parseIntValue(json['id']),
      name: json['name']?.toString() ?? 'Branch',
      code: json['code']?.toString(),
      city: json['city']?.toString(),
      state: json['state']?.toString(),
      status: json['status']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'code': code,
    'city': city,
    'state': state,
    'status': status,
  };

  String get label {
    final parts = [if (code != null && code!.trim().isNotEmpty) code, name];
    return parts.join(' - ');
  }

  String get locationLabel {
    final parts = [
      if (city != null && city!.trim().isNotEmpty) city,
      if (state != null && state!.trim().isNotEmpty) state,
    ];
    return parts.join(', ');
  }
}
