// Internal navigation for the V2 experience. All customer-facing screens are
// V2-native; only map-heavy address add/edit and the payment step of checkout
// fall through to the production screens by name.

import 'package:flutter/material.dart';

import 'screens/addresses_v2.dart';
import 'screens/cart_v2.dart';
import 'screens/checkout_v2.dart';
import 'screens/edit_profile_v2.dart';
import 'screens/notification_prefs_v2.dart';
import 'screens/notifications_v2.dart';
import 'screens/offers_v2.dart';
import 'screens/order_detail_v2.dart';
import 'screens/order_tracking_v2.dart';
import 'screens/orders_v2.dart';
import 'screens/privacy_legal_v2.dart';
import 'screens/referrals_v2.dart';
import 'screens/restaurant_detail_v2.dart';
import 'screens/saved_restaurants_v2.dart';
import 'screens/scratch_cards_v2.dart';
import 'screens/search_v2.dart';
import 'screens/support_v2.dart';
import 'screens/taxonomy_results_v2.dart';
import 'screens/wallet_v2.dart';
import 'widgets/v2_anim.dart';

void _push(BuildContext context, Widget page) {
  Navigator.of(context).push(V2PageRoute(builder: (_) => page));
}

void v2OpenRestaurant(BuildContext context, int id, {int? menuItemId}) {
  if (id <= 0) return;
  _push(context,
      RestaurantDetailV2(restaurantId: id, initialMenuItemId: menuItemId));
}

void v2OpenSearch(BuildContext context) => _push(context, const SearchV2());
void v2OpenCart(BuildContext context) => _push(context, const CartV2());
void v2OpenCheckout(BuildContext context) => _push(context, const CheckoutV2());

void v2OpenTaxonomy(
  BuildContext context, {
  required String title,
  required String filterType,
  int? filterId,
  String? label,
  double? priceMax,
}) {
  _push(
    context,
    TaxonomyResultsV2(
      title: title,
      filterType: filterType,
      filterId: filterId,
      label: label,
      priceMax: priceMax,
    ),
  );
}

void v2OpenOrder(BuildContext context, int orderId) {
  if (orderId <= 0) return;
  _push(context, OrderDetailV2(orderId: orderId));
}

void v2OpenTracking(BuildContext context, int orderId) {
  if (orderId <= 0) return;
  _push(context, OrderTrackingV2(orderId: orderId));
}

void v2OpenOrders(BuildContext context) =>
    _push(context, const OrdersV2());

void v2OpenWallet(BuildContext context) => _push(context, const WalletV2());
void v2OpenOffers(BuildContext context) => _push(context, const OffersV2());
void v2OpenNotifications(BuildContext context) =>
    _push(context, const NotificationsV2());
void v2OpenNotificationPrefs(BuildContext context) =>
    _push(context, const NotificationPrefsV2());
void v2OpenSupport(BuildContext context) => _push(context, const SupportV2());
void v2OpenPrivacy(BuildContext context) =>
    _push(context, const PrivacyLegalV2());
void v2OpenReferrals(BuildContext context) =>
    _push(context, const ReferralsV2());
void v2OpenScratchCards(BuildContext context) =>
    _push(context, const ScratchCardsV2());
void v2OpenSavedRestaurants(BuildContext context) =>
    _push(context, const SavedRestaurantsV2());
void v2OpenAddresses(BuildContext context) =>
    _push(context, const AddressesV2());
void v2OpenEditProfile(BuildContext context) =>
    _push(context, const EditProfileV2());

/// Fallback to a production named route (map-heavy address add/edit, etc.).
void v2OpenNamed(BuildContext context, String route) {
  Navigator.of(context).pushNamed(route);
}
