import 'api_service.dart';

/// Restaurant-facing AI growth assistant.
///
/// Contract (see backend spec):
///   GET  /api/ai/status                       -> { data: { enabled, service_enabled } }
///   POST /api/restaurant/assistant/message    body { message, conversation_id? }
///        -> { data: { conversation_id, reply, plan?: [{title, steps:[]}], suggestions?: [] } }
///   GET  /api/restaurant/assistant/history    -> { data: { conversation_id, messages: [{role, text, plan?}] } }
class AiAssistantService {
  AiAssistantService._();
  static final AiAssistantService instance = AiAssistantService._();

  final ApiService _api = ApiService();

  /// Whether the restaurant growth assistant is available.
  ///
  /// Reads `GET /api/restaurant/assistant/status` (contract: reflects the admin
  /// AI Control Center — OpenAI/Gemini key configured + kill-switch off). If that
  /// endpoint isn't deployed yet we stay optimistic and let [send] surface the
  /// real error, rather than falsely showing "offline".
  Future<bool> isEnabled() async {
    try {
      final res = await _api.get('/restaurant/assistant/status');
      final data = res is Map ? res['data'] : null;
      if (data is Map && data.containsKey('enabled')) {
        return data['enabled'] == true;
      }
    } catch (_) {}
    return true;
  }

  Future<AiAssistantReply> send(String message, {String? conversationId}) async {
    final res = await _api.post(
      '/restaurant/assistant/message',
      data: {
        'message': message,
        if (conversationId != null) 'conversation_id': conversationId,
      },
    );
    final data = (res is Map ? res['data'] : null);
    if (data is! Map) {
      throw Exception('Unexpected assistant response.');
    }
    return AiAssistantReply.fromJson(Map<String, dynamic>.from(data));
  }

  Future<List<AiAssistantMessage>> history() async {
    try {
      final res = await _api.get('/restaurant/assistant/history');
      final data = res is Map ? res['data'] : null;
      final raw = data is Map ? data['messages'] : null;
      if (raw is List) {
        return raw
            .whereType<Map>()
            .map((m) => AiAssistantMessage.fromJson(
                Map<String, dynamic>.from(m)))
            .toList();
      }
    } catch (_) {}
    return const [];
  }
}

class AiPlan {
  AiPlan({required this.title, required this.steps});
  final String title;
  final List<String> steps;

  factory AiPlan.fromJson(Map<String, dynamic> j) => AiPlan(
        title: j['title']?.toString() ?? 'Plan',
        steps: (j['steps'] as List? ?? const [])
            .map((s) => s.toString())
            .toList(),
      );
}

class AiAssistantReply {
  AiAssistantReply({
    required this.conversationId,
    required this.reply,
    this.plans = const [],
    this.suggestions = const [],
  });

  final String? conversationId;
  final String reply;
  final List<AiPlan> plans;
  final List<String> suggestions;

  factory AiAssistantReply.fromJson(Map<String, dynamic> j) {
    final rawPlan = j['plan'] ?? j['plans'];
    return AiAssistantReply(
      conversationId: j['conversation_id']?.toString(),
      reply: j['reply']?.toString() ?? j['message']?.toString() ?? '',
      plans: rawPlan is List
          ? rawPlan
              .whereType<Map>()
              .map((p) => AiPlan.fromJson(Map<String, dynamic>.from(p)))
              .toList()
          : const [],
      suggestions: (j['suggestions'] as List? ?? const [])
          .map((s) => s.toString())
          .toList(),
    );
  }
}

class AiAssistantMessage {
  AiAssistantMessage({
    required this.fromUser,
    required this.text,
    this.plans = const [],
  });

  final bool fromUser;
  final String text;
  final List<AiPlan> plans;

  factory AiAssistantMessage.fromJson(Map<String, dynamic> j) {
    final role = j['role']?.toString() ?? (j['from_user'] == true ? 'user' : 'assistant');
    final rawPlan = j['plan'] ?? j['plans'];
    return AiAssistantMessage(
      fromUser: role == 'user',
      text: j['text']?.toString() ?? j['message']?.toString() ?? '',
      plans: rawPlan is List
          ? rawPlan
              .whereType<Map>()
              .map((p) => AiPlan.fromJson(Map<String, dynamic>.from(p)))
              .toList()
          : const [],
    );
  }
}
