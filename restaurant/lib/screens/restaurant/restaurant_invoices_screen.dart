import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/api_service.dart';
import '../../theme/aurora_theme.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/aurora/aurora.dart';

/// Downloads & invoices.
///
/// Real data: GET /api/restaurant/statements
///   data = { period{from,to,fy}, gst_active, summary{gross,commission,commission_gst,
///            tds_194o,tcs,net,...}, fy_tds_194o{fy,gross_ytd,tds_ytd,form_16a_url}, cycles[] }
/// Contract (new): GET /api/restaurant/invoices?type=ordering|ads&month=YYYY-MM
///   data.invoices[] = { id, label, period, amount, download_url }
class RestaurantInvoicesScreen extends StatefulWidget {
  const RestaurantInvoicesScreen({super.key});

  @override
  State<RestaurantInvoicesScreen> createState() =>
      _RestaurantInvoicesScreenState();
}

class _RestaurantInvoicesScreenState extends State<RestaurantInvoicesScreen> {
  final ApiService _api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _statements = {};
  List<Map<String, dynamic>> _orderingInvoices = [];
  List<Map<String, dynamic>> _adsInvoices = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await _api.get('/restaurant/statements');
      final data = res is Map ? res['data'] : null;
      if (data is Map) _statements = Map<String, dynamic>.from(data);
    } catch (_) {}
    _orderingInvoices = await _fetchInvoices('ordering');
    _adsInvoices = await _fetchInvoices('ads');
    if (mounted) setState(() => _loading = false);
  }

  Future<List<Map<String, dynamic>>> _fetchInvoices(String type) async {
    try {
      final res =
          await _api.get('/restaurant/invoices', queryParams: {'type': type});
      final data = res is Map ? res['data'] : null;
      final raw = data is Map ? data['invoices'] : null;
      if (raw is List) {
        return raw
            .whereType<Map>()
            .map((m) => Map<String, dynamic>.from(m))
            .toList();
      }
    } catch (_) {}
    return const [];
  }

  Future<void> _open(String? url) async {
    if (url == null || url.trim().isEmpty) {
      _snack('Download link not available yet.');
      return;
    }
    final uri = Uri.tryParse(url);
    if (uri == null || !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      _snack('Could not open the file.');
    }
  }

  void _snack(String m) => ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(m)));

  double _num(dynamic v) => double.tryParse('${v ?? 0}') ?? 0;

  @override
  Widget build(BuildContext context) {
    final topPad = MediaQuery.of(context).padding.top + 60;
    final summary = _statements['summary'] is Map
        ? Map<String, dynamic>.from(_statements['summary'])
        : {};
    final period = _statements['period'] is Map
        ? Map<String, dynamic>.from(_statements['period'])
        : {};
    final tds = _statements['fy_tds_194o'] is Map
        ? Map<String, dynamic>.from(_statements['fy_tds_194o'])
        : {};
    final cycles = (_statements['cycles'] as List? ?? const [])
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .toList();

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Invoices & downloads',
                style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900)),
            Text('FY ${period['fy'] ?? '—'}',
                style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700)),
          ],
        ),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        _loading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: EdgeInsets.fromLTRB(16, topPad, 16, 28),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        gradient: foodflow.brandGradient,
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: [
                          BoxShadow(
                            color: foodflow.orange.withOpacity(0.26),
                            blurRadius: 22,
                            offset: const Offset(0, 12),
                          ),
                        ],
                      ),
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('NET EARNINGS THIS PERIOD',
                                style: TextStyle(
                                    color: Colors.white.withOpacity(0.75),
                                    fontSize: 11,
                                    letterSpacing: 0.6,
                                    fontWeight: FontWeight.w800)),
                            const SizedBox(height: 4),
                            Text(formatCurrency(context, _num(summary['net'])),
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 32,
                                    fontWeight: FontWeight.w900)),
                            const SizedBox(height: 6),
                            Text(
                              '${period['from'] ?? ''} → ${period['to'] ?? ''}',
                              style: TextStyle(
                                  color: Colors.white.withOpacity(0.82),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700),
                            ),
                          ]),
                    ),
                    const SizedBox(height: 14),
                    _section('This period', Icons.summarize_rounded, [
                      _row('Gross sales', formatCurrency(context, _num(summary['gross']))),
                      _row('Platform commission',
                          '- ${formatCurrency(context, _num(summary['commission']))}'),
                      _row('Commission GST',
                          '- ${formatCurrency(context, _num(summary['commission_gst']))}'),
                      _row('TDS u/s 194-O',
                          '- ${formatCurrency(context, _num(summary['tds_194o']))}'),
                      _row('TCS',
                          '- ${formatCurrency(context, _num(summary['tcs']))}'),
                    ]),
                    _downloadSection(
                      'Payout statements',
                      Icons.account_balance_wallet_outlined,
                      cycles.isEmpty
                          ? const []
                          : cycles
                              .map((c) => _DownloadRow(
                                    label: c['label']?.toString() ??
                                        'Cycle ${c['id'] ?? ''}',
                                    sub: c['period']?.toString() ??
                                        '${c['from'] ?? ''} → ${c['to'] ?? ''}',
                                    amount: formatCurrency(
                                        context, _num(c['net'] ?? c['amount'])),
                                    onTap: () => _open(
                                        c['download_url']?.toString() ??
                                            c['statement_url']?.toString()),
                                  ))
                              .toList(),
                      emptyText: 'No settled payout cycles yet.',
                    ),
                    _downloadSection(
                      'TDS certificate (Form 16A)',
                      Icons.verified_outlined,
                      [
                        _DownloadRow(
                          label: 'Form 16A · FY ${tds['fy'] ?? period['fy'] ?? ''}',
                          sub:
                              'TDS deposited ${formatCurrency(context, _num(tds['tds_ytd']))} on ${formatCurrency(context, _num(tds['gross_ytd']))}',
                          amount: '',
                          onTap: () => _open(tds['form_16a_url']?.toString()),
                        ),
                      ],
                    ),
                    _downloadSection(
                      'Online ordering invoices',
                      Icons.receipt_long_outlined,
                      _orderingInvoices
                          .map((i) => _DownloadRow(
                                label: i['label']?.toString() ?? 'Invoice',
                                sub: i['period']?.toString() ?? '',
                                amount: formatCurrency(
                                    context, _num(i['amount'])),
                                onTap: () =>
                                    _open(i['download_url']?.toString()),
                              ))
                          .toList(),
                      emptyText:
                          'Month-wise ordering invoices will appear here.',
                    ),
                    _downloadSection(
                      'Ads invoices',
                      Icons.campaign_outlined,
                      _adsInvoices
                          .map((i) => _DownloadRow(
                                label: i['label']?.toString() ?? 'Ad invoice',
                                sub: i['period']?.toString() ?? '',
                                amount: formatCurrency(
                                    context, _num(i['amount'])),
                                onTap: () =>
                                    _open(i['download_url']?.toString()),
                              ))
                          .toList(),
                      emptyText: 'Ad spend invoices will appear here.',
                    ),
                  ],
                ),
              ),
      ]),
    );
  }

  Widget _section(String title, IconData icon, List<Widget> rows) => Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: foodflow.surfaceColor,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: foodflow.line),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(icon, size: 16, color: foodflow.orange),
            const SizedBox(width: 8),
            Text(title.toUpperCase(),
                style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 12,
                    letterSpacing: 0.6,
                    fontWeight: FontWeight.w900)),
          ]),
          Divider(height: 22, color: foodflow.line),
          ...rows,
        ]),
      );

  Widget _downloadSection(
    String title,
    IconData icon,
    List<Widget> rows, {
    String emptyText = 'Nothing here yet.',
  }) =>
      _section(
        title,
        icon,
        rows.isEmpty
            ? [
                Text(emptyText,
                    style: TextStyle(color: foodflow.muted, fontSize: 12.5)),
              ]
            : rows,
      );

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          Expanded(
              child: Text(label,
                  style: TextStyle(
                      color: foodflow.muted, fontWeight: FontWeight.w700))),
          Text(value,
              style: TextStyle(
                  color: foodflow.ink, fontWeight: FontWeight.w800)),
        ]),
      );
}

class _DownloadRow extends StatelessWidget {
  const _DownloadRow({
    required this.label,
    required this.sub,
    required this.amount,
    required this.onTap,
  });

  final String label;
  final String sub;
  final String amount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: TextStyle(
                        color: foodflow.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w800)),
                if (sub.isNotEmpty)
                  Text(sub,
                      style:
                          TextStyle(color: foodflow.muted, fontSize: 11)),
              ],
            ),
          ),
          if (amount.isNotEmpty) ...[
            Text(amount,
                style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w900)),
            const SizedBox(width: 8),
          ],
          IconButton(
            onPressed: onTap,
            icon: Icon(Icons.download_rounded, color: foodflow.orange),
          ),
        ],
      ),
    );
  }
}
