import 'package:flutter/material.dart';

import '../../../config/api_constants.dart';
import '../../../services/api_service.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../theme/aurora_theme.dart';
import '../../../widgets/aurora/aurora.dart';

/// One full-screen legal / policy document.
///
/// Content is admin-managed: it is read from `GET /content/legal`
/// (`data[contentKey]`). [fallbackBody] is shown only until the admin sets that
/// field. All four legal documents (terms, privacy, refund, account deletion)
/// use this same screen.
class LegalDocumentScreen extends StatefulWidget {
  const LegalDocumentScreen({
    super.key,
    required this.title,
    required this.contentKey,
    required this.fallbackBody,
    this.intro,
  });

  final String title;
  final String contentKey;
  final String fallbackBody;
  final String? intro;

  @override
  State<LegalDocumentScreen> createState() => _LegalDocumentScreenState();
}

class _LegalDocumentScreenState extends State<LegalDocumentScreen> {
  final ApiService _api = ApiService();
  String? _body;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _apply(dynamic response) {
    if (response is! Map || response['success'] != true) return;
    final data = response['data'];
    final value = data is Map ? data[widget.contentKey]?.toString().trim() : null;
    if (mounted) {
      setState(() {
        _body = (value != null && value.isNotEmpty) ? value : null;
        _loading = false;
      });
    }
  }

  Future<void> _load() async {
    try {
      _apply(await _api.getWithCache(
        ApiConstants.legalContent,
        onCache: _apply,
      ));
    } catch (_) {
      // keep fallback
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 64;
    final body = _body ?? widget.fallbackBody;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text(
          widget.title,
          style: TextStyle(
            color: foodflow.ink,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        ListView(
          padding: EdgeInsets.fromLTRB(16, topPad, 16, 32),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: foodflow.brandGradient,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: foodflow.orange.withOpacity(0.24),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.description_outlined,
                      color: Colors.white, size: 22),
                  const SizedBox(height: 10),
                  Text(
                    widget.title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      height: 1.15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  if (widget.intro != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      widget.intro!,
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.85),
                        fontSize: 12.5,
                        height: 1.35,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_loading && _body == null)
              const Padding(
                padding: EdgeInsets.only(top: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: foodflow.surfaceColor,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: foodflow.line),
                ),
                child: Text(
                  body,
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 13,
                    height: 1.55,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
          ],
        ),
      ]),
    );
  }
}
