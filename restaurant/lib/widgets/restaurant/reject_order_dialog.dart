import 'package:flutter/material.dart';

import '../../theme/foodflow_theme.dart';

const _rejectPresets = [
  'Kitchen at capacity right now',
  'Item(s) out of stock',
  'Closing soon',
  'Address outside delivery range',
];

Future<String?> showRestaurantRejectOrderDialog(BuildContext context) async {
  final controller = TextEditingController();

  try {
    return await showDialog<String>(
      context: context,
      barrierDismissible: true,
      builder: (dialogContext) {
        return _RejectDialogBody(controller: controller);
      },
    );
  } finally {
    controller.dispose();
  }
}

class _RejectDialogBody extends StatefulWidget {
  const _RejectDialogBody({required this.controller});
  final TextEditingController controller;

  @override
  State<_RejectDialogBody> createState() => _RejectDialogBodyState();
}

class _RejectDialogBodyState extends State<_RejectDialogBody> {
  String? _selectedPreset;

  @override
  Widget build(BuildContext dialogContext) {
    final controller = widget.controller;
    {
      return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          child: Container(
            decoration: BoxDecoration(
              color: foodflow.isDark ? foodflow.elevatedSurface : Colors.white,
              borderRadius: BorderRadius.circular(28),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.12),
                  blurRadius: 28,
                  offset: const Offset(0, 16),
                ),
              ],
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(22),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: foodflow.danger.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Icon(
                      Icons.close_rounded,
                      color: foodflow.danger,
                      size: 28,
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    'Reject order',
                    style: TextStyle(
                      color: foodflow.ink,
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Let the customer know why this order cannot be prepared right now.',
                    style: TextStyle(
                      color: foodflow.muted,
                      fontSize: 14,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _rejectPresets.map((preset) {
                      final selected = _selectedPreset == preset;
                      return GestureDetector(
                        onTap: () => setState(() {
                          _selectedPreset = selected ? null : preset;
                          if (!selected) controller.clear();
                        }),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: selected
                                ? foodflow.danger.withOpacity(0.12)
                                : (foodflow.isDark
                                    ? foodflow.surfaceColor
                                    : foodflow.canvas),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                              color: selected
                                  ? foodflow.danger
                                  : foodflow.line,
                            ),
                          ),
                          child: Text(
                            preset,
                            style: TextStyle(
                              color: selected
                                  ? foodflow.danger
                                  : foodflow.ink,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: foodflow.isDark ? foodflow.surfaceColor : foodflow.canvas,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: foodflow.line),
                    ),
                    child: TextField(
                      controller: controller,
                      minLines: 2,
                      maxLines: 4,
                      textCapitalization: TextCapitalization.sentences,
                      onChanged: (_) {
                        if (_selectedPreset != null) {
                          setState(() => _selectedPreset = null);
                        }
                      },
                      decoration: const InputDecoration(
                        hintText: 'Or type a custom reason…',
                        border: InputBorder.none,
                        isCollapsed: true,
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(dialogContext).pop(),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(52),
                            side: BorderSide(color: foodflow.line),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                          child: const Text('Keep order'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () {
                            final typed = controller.text.trim();
                            final value = _selectedPreset ??
                                (typed.isEmpty
                                    ? 'Rejected by restaurant'
                                    : typed);
                            Navigator.of(dialogContext).pop(value);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: foodflow.danger,
                            foregroundColor: Colors.white,
                            minimumSize: const Size.fromHeight(52),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                          child: const Text(
                            'Reject order',
                            style: TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
    }
  }
}
