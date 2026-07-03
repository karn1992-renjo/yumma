import 'package:flutter/material.dart';

import '../../theme/foodflow_theme.dart';

Future<String?> showRestaurantRejectOrderDialog(BuildContext context) async {
  final controller = TextEditingController();

  try {
    return await showDialog<String>(
      context: context,
      barrierDismissible: true,
      builder: (dialogContext) {
        return Dialog(
          backgroundColor: Colors.transparent,
          insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
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
                      color: const Color(0xFFFFF1F0),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: const Icon(
                      Icons.close_rounded,
                      color: Color(0xFFD92D20),
                      size: 28,
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Reject order',
                    style: TextStyle(
                      color: FoodFlowTheme.ink,
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Let the customer know why this order cannot be prepared right now.',
                    style: TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 14,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 18),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF8FAFC),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: FoodFlowTheme.line),
                    ),
                    child: TextField(
                      controller: controller,
                      autofocus: true,
                      minLines: 3,
                      maxLines: 5,
                      textCapitalization: TextCapitalization.sentences,
                      decoration: const InputDecoration(
                        hintText: 'Example: Kitchen is at capacity for the next 30 minutes.',
                        border: InputBorder.none,
                        isCollapsed: true,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Leave it blank to send the default rejection message.',
                    style: TextStyle(
                      color: FoodFlowTheme.muted,
                      fontSize: 12,
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
                            side: const BorderSide(color: FoodFlowTheme.line),
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
                            final value = controller.text.trim();
                            Navigator.of(dialogContext).pop(
                              value.isEmpty ? 'Rejected by restaurant' : value,
                            );
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFD92D20),
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
      },
    );
  } finally {
    controller.dispose();
  }
}
