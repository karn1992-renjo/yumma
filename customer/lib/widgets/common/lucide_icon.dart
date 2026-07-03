import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

class AppIcons {
  static const IconData arrowBack = LucideIcons.arrow_left;
  static const IconData share = LucideIcons.share_2;
  static const IconData favorite = LucideIcons.heart;
  static const IconData favoriteOutline = LucideIcons.heart;
  static const IconData search = LucideIcons.search;
  static const IconData location = LucideIcons.map_pin;
  static const IconData locationPin = LucideIcons.map_pin;
  static const IconData restaurant = LucideIcons.utensils;
  static const IconData user = LucideIcons.circle_user;
  static const IconData star = LucideIcons.star;
  static const IconData offer = LucideIcons.badge_percent;
  static const IconData add = LucideIcons.plus;
  static const IconData check = LucideIcons.check;
  static const IconData remove = LucideIcons.minus;
  static const IconData close = LucideIcons.x;
  static const IconData edit = LucideIcons.pencil;
  static const IconData delete = LucideIcons.trash_2;
  static const IconData schedule = LucideIcons.clock_3;
  static const IconData receipt = LucideIcons.receipt_text;
  static const IconData note = LucideIcons.notebook_pen;
  static const IconData delivery = LucideIcons.bike;
  static const IconData storefront = LucideIcons.store;
  static const IconData wallet = LucideIcons.wallet_cards;
  static const IconData payments = LucideIcons.credit_card;
  static const IconData support = LucideIcons.headset;
  static const IconData phone = LucideIcons.phone;
  static const IconData email = LucideIcons.mail;
  static const IconData home = LucideIcons.house;
  static const IconData chevronRight = LucideIcons.chevron_right;
  static const IconData refresh = LucideIcons.refresh_cw;
  static const IconData bookmark = LucideIcons.bookmark;
  static const IconData bookmarkOutline = LucideIcons.bookmark;
  static const IconData eco = LucideIcons.leaf;
  static const IconData fire = LucideIcons.flame;
  static const IconData filter = LucideIcons.list_filter;
  static const IconData calendar = LucideIcons.calendar_days;
  static const IconData cart = LucideIcons.shopping_cart;
}

class AppIcon extends StatelessWidget {
  const AppIcon(
    this.icon, {
    super.key,
    this.size = 22,
    this.color,
    this.semanticLabel,
  });

  final IconData icon;
  final double size;
  final Color? color;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    return Icon(
      icon,
      size: size,
      color: color,
      semanticLabel: semanticLabel,
    );
  }
}
