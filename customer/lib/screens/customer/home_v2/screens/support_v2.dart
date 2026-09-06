import 'package:flutter/material.dart';

import '../../../../models/order.dart';
import 'support_chat_v2.dart';

/// Kept as a thin alias — Help &amp; Support is now the two-tab
/// [SupportChatV2] screen (Help + Chat).
class SupportV2 extends StatelessWidget {
  const SupportV2({super.key, this.order, this.openChat = false});

  final Order? order;
  final bool openChat;

  @override
  Widget build(BuildContext context) =>
      SupportChatV2(order: order, openChat: openChat);
}
