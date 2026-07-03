// lib/config/api_constants.dart

class ApiConstants {
  // Base URL - Update this with your actual API URL
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://yumma.in/api',
  );

  // Auth endpoints
  static const String login = '/login';
  static const String socialLogin = '/auth/social-login';
  static const String loginWithPhone = '/auth/login-with-phone';
  static const String register = '/register';
  static const String phoneStatus = '/auth/phone/status';
  static const String verifyFirebasePhone = '/auth/phone/verify-firebase';
  static const String sendLoginOtp = '/auth/otp/send';
  static const String verifyLoginOtp = '/auth/otp/verify';
  static const String forgotPassword = '/forgot-password';
  static const String resetPasswordByPhone = '/forgot-password/reset-by-phone';
  static const String logout = '/logout';
  static const String user = '/user';
  static const String appBranding = '/app/branding';
  static const String updateProfile = '/user/profile';
  static const String registerFcmToken = '/user/fcm-token';
  static const String notifications = '/notifications';
  static const String markNotificationsRead = '/notifications/read';
  static String markNotificationRead(String id) => '/notifications/$id/read';
  static const String changePassword = '/user/change-password';
  static const String updatePassword = changePassword;
  static const String wallet = '/wallet';
  static const String walletTopUp = '/wallet/top-up';
  static const String walletTopUpVerify = '/wallet/top-up/verify';
  static const String walletGiftCardRedeem = '/wallet/gift-card/redeem';
  static const String supportTickets = '/support/tickets';
  static String supportTicketReply(int ticketId) =>
      '/support/tickets/$ticketId/reply';
  static String orderChat(int orderId) => '/orders/$orderId/chat';
  static String restaurantOrderChat(int orderId) => '/restaurant/orders/$orderId/chat';
  static String driverOrderChat(int orderId) => '/driver/orders/$orderId/chat';

  // Restaurant Customer endpoints
  static const String nearbyRestaurants = '/restaurants/nearby';
  static const String searchRestaurants = '/restaurants/search';
  static const String advancedSearch = '/v1/search';
  static const String searchSuggestions = '/v1/search/suggestions';
  static const String searchTrending = '/v1/search/trending';
  static const String searchHistory = '/v1/search/history';
  static const String searchClick = '/v1/search/click';
  static const String restaurantDetails = '/restaurants';
  static const String restaurantMenu = '/restaurants/menu';
  static const String popularCuisines = '/cuisines/popular';
  static const String savedRestaurants = '/favorites/restaurants';
  static String favoriteRestaurant(int restaurantId) =>
      '/favorites/restaurants/$restaurantId';
  static String removeFavoriteRestaurant(int restaurantId) =>
      '/favorites/restaurants/$restaurantId/remove';

  // Content endpoints
  static const String homeSections = '/home/sections';
  static const String banners = '/banners';
  static const String bannersByType = '/banners';
  static const String activeOffers = '/offers/active';
  static const String legalContent = '/content/legal';

  // Order Customer endpoints
  static const String createOrder = '/orders';
  static const String orderSummary = '/orders/summary';
  static const String myOrders = '/orders';
  static const String orderDetails = '/orders';
  static String cancelOrder(int orderId) => '/orders/$orderId/cancel';
  static const String trackOrder = '/orders/track';
  static String requestRefund(int orderId) => '/orders/$orderId/refund-request';
  static String orderFeedback(int orderId) => '/orders/$orderId/feedback';
  static const String refundPolicy = '/refund-policy';

  // Address endpoints
  static const String addresses = '/addresses';
  static const String setDefaultAddress = '/addresses/default';
  static String deleteAddress(int id) => '/addresses/$id/delete';

  // Coupon endpoints
  static const String validateCoupon = '/coupons/validate';
  static const String offers = '/offers';

  // Payment endpoints
  static const String createPayment = '/payments/create';
  static const String verifyPayment = '/payments/verify';

  // Restaurant Owner Dashboard endpoints
  static const String restaurantDashboard = '/restaurant/dashboard';
  static const String restaurantStats = '/restaurant/stats';
  static const String restaurantToggleStatus = '/restaurant/toggle-status';
  static const String restaurantInfo = '/restaurant/info';
  static const String restaurantStaff = '/restaurant/staff';
  static const String restaurantOrders = '/restaurant/orders';
  static const String restaurantCategories = '/restaurant/categories';
  static const String restaurantMenuItems = '/restaurant/menu';
  static const String restaurantSettings = '/restaurant/settings';
  static const String restaurantAnalytics = '/restaurant/analytics';
  static const String restaurantPromos = '/restaurant/promos';
  static String customerRestaurantPromos(int restaurantId) =>
      '/restaurants/$restaurantId/promos';
  static const String restaurantPrinters = '/restaurant/printers';
  static String restaurantOrderDetails(int orderId) =>
      '$restaurantOrders/$orderId';
  static String restaurantAcceptOrder(int orderId) =>
      '$restaurantOrders/$orderId/accept';
  static String restaurantRejectOrder(int orderId) =>
      '$restaurantOrders/$orderId/reject';
  static String restaurantOrderStatus(int orderId) =>
      '$restaurantOrders/$orderId/status';
  static String restaurantOrderReady(int orderId) =>
      '$restaurantOrders/$orderId/ready';

  // Dining endpoints
  static const String diningCelebrationTypes = '/dining/celebration-types';
  static const String diningRestaurants = '/dining/restaurants';
  static const String diningBook = '/dining/book';
  static String diningBookingCreatePayment(int bookingId) =>
      '/dining/bookings/$bookingId/payment/create';
  static String diningBookingVerifyPayment(int bookingId) =>
      '/dining/bookings/$bookingId/payment/verify';
  static const String diningMyBookings = '/dining/my-bookings';
  static const String diningCancel = '/dining/cancel';
  static const String diningBookings = '/dining/bookings';
  static String diningBookingDetail(int bookingId) =>
      '/dining/bookings/$bookingId';
  static String diningBookingReview(int bookingId) =>
      '/dining/bookings/$bookingId/review';

  // Delivery endpoints
  static const String verifyDeliveryOtp = '/delivery/verify-otp';
  static const String resendDeliveryOtp = '/delivery/resend-otp';

  // Return endpoints
  static const String requestReturn = '/returns/request';
  static const String returnStatus = '/returns/status';

  // Driver endpoints
  static const String driverLocation = '/driver/location';
  static const String driverOrders = '/driver/orders';
  static const String driverOrderStatus = '/driver/orders/status';
  static const String driverGigs = '/driver/gigs';
  static const String driverBookGig = '/driver/gigs/book';
  static const String driverEarnings = '/driver/earnings';
  static const String driverStatus = '/driver/status';
  static const String driverToggleStatus = '/driver/toggle-status';
  static String driverAcceptOrder(int orderId) =>
      '/driver/orders/$orderId/accept';
  static String driverRejectOrder(int orderId) =>
      '/driver/orders/$orderId/reject';
  static String updateOrderStatus(int orderId) =>
      '/driver/orders/$orderId/status';

  // Campaign endpoints
  static const String campaigns = '/campaigns';
  static String campaignTrackClick(int campaignId) =>
      '/campaigns/$campaignId/track-click';
  static String campaignTrackImpression(int campaignId) =>
      '/campaigns/$campaignId/track-impression';
}
