import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';

class OrderCardWidget extends StatelessWidget {
  final dynamic order;
  final VoidCallback onTap;
  final VoidCallback? onAccept;
  final bool isPending;

  const OrderCardWidget({
    Key? key,
    required this.order,
    required this.onTap,
    this.onAccept,
    this.isPending = false,
  }) : super(key: key);

  String _getStatusText(String status) {
    switch (status) {
      case 'pending':
        return '🕐 Pending';
      case 'confirmed':
        return '✅ Confirmed';
      case 'preparing':
        return '👨‍🍳 Preparing';
      case 'ready_for_pickup':
        return '📦 Ready';
      case 'picked_up':
        return '🛵 Picked Up';
      case 'on_the_way':
        return '🚚 On The Way';
      case 'delivered':
        return '🎉 Delivered';
      case 'cancelled':
        return '❌ Cancelled';
      default:
        return status;
    }
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'pending':
        return FoodFlowTheme.crimson;
      case 'confirmed':
        return Colors.blue;
      case 'preparing':
        return Colors.purple;
      case 'ready_for_pickup':
        return Colors.teal;
      case 'picked_up':
        return Colors.indigo;
      case 'on_the_way':
        return Colors.cyan;
      case 'delivered':
        return FoodFlowTheme.success;
      case 'cancelled':
        return FoodFlowTheme.danger;
      default:
        return FoodFlowTheme.muted;
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = order['items'] != null && order['items'] is List
        ? order['items'] as List
        : (order['items'] is String ? jsonDecode(order['items']) : []);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 40,
                    decoration: BoxDecoration(
                      color: _getStatusColor(order['status']),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Text(
                              '#${order['order_number']}',
                              style: const TextStyle(
                                color: FoodFlowTheme.ink,
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: _getStatusColor(order['status'])
                                    .withOpacity(0.1),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                _getStatusText(order['status']),
                                style: TextStyle(
                                  fontSize: 10,
                                  color: _getStatusColor(order['status']),
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${formatCurrencyValue(context, order['total'])} • ${items.length} items',
                          style: TextStyle(
                            fontSize: 14,
                            color: FoodFlowTheme.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    DateFormat('HH:mm').format(
                      DateTime.parse(order['created_at']),
                    ),
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey.shade500,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              // Items preview
              Wrap(
                spacing: 8,
                runSpacing: 4,
                children: items.take(3).map<Widget>((item) {
                  return Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: FoodFlowTheme.canvas,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      '${item['quantity']}x ${item['name']}',
                      style:
                          const TextStyle(
                              fontSize: 12, color: FoodFlowTheme.inkSoft),
                    ),
                  );
                }).toList(),
              ),
              if (items.length > 3)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    '+${items.length - 3} more',
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                  ),
                ),
              const SizedBox(height: 12),
              // Customer info
              Row(
                children: [
                  const Icon(Icons.person_outline,
                      size: 14, color: FoodFlowTheme.muted),
                  const SizedBox(width: 4),
                  Text(
                    order['customer_name'] ?? 'Guest',
                    style:
                        const TextStyle(fontSize: 13, color: FoodFlowTheme.muted),
                  ),
                  const SizedBox(width: 12),
                  const Icon(Icons.phone_outlined,
                      size: 14, color: FoodFlowTheme.muted),
                  const SizedBox(width: 4),
                  Text(
                    order['customer_phone'] ?? 'N/A',
                    style:
                        const TextStyle(fontSize: 13, color: FoodFlowTheme.muted),
                  ),
                ],
              ),
              // Accept button for pending orders
              if (isPending && onAccept != null)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: ElevatedButton(
                    onPressed: onAccept,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: FoodFlowTheme.orange,
                      minimumSize: const Size(double.infinity, 45),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.check_circle, color: Colors.white, size: 20),
                        SizedBox(width: 8),
                        Text('Accept Order', style: TextStyle(fontSize: 14)),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
