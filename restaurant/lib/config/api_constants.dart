// lib/config/api_constants.dart

class ApiConstants {
  // Base URL - Update this with your actual API URL
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://yumma.in/api',
  );

  // Auth endpoints
  static const String login = '/login';
  static const String loginWithPhone = '/auth/login-with-phone';
  static const String verifyFirebasePhone = '/auth/phone/verify-firebase';
  static const String register = '/register';
  static const String phoneStatus = '/auth/phone/status';
  static const String sendLoginOtp = '/auth/otp/send';
  static const String verifyLoginOtp = '/auth/otp/verify';
  static const String appBranding = '/app/branding';
  static const String logout = '/logout';
  static const String user = '/user';
  static const String updateProfile = '/user/profile';
  static const String registerFcmToken = '/user/fcm-token';
  static const String changePassword = '/user/change-password';
  static const String updatePassword = changePassword;
  static const String wallet = '/wallet';
  static const String walletWithdraw = '/wallet/withdraw';
  static const String notifications = '/notifications';
  static const String notificationsRead = '/notifications/read';
  static const String walletTopUp = '/wallet/top-up';
  static const String walletTopUpVerify = '/wallet/top-up/verify';
  static const String supportTickets = '/support/tickets';
  static String supportTicketReply(int ticketId) =>
      '/support/tickets/$ticketId/reply';
  static String orderChat(int orderId) => '/orders/$orderId/chat';
  static String restaurantOrderChat(int orderId) =>
      '/restaurant/orders/$orderId/chat';
  static String driverOrderChat(int orderId) => '/driver/orders/$orderId/chat';

  // Restaurant Customer endpoints
  static const String nearbyRestaurants = '/restaurants/nearby';
  static const String searchRestaurants = '/restaurants/search';
  static const String restaurantDetails = '/restaurants';
  static const String restaurantMenu = '/restaurants/menu';
  static const String popularCuisines = '/cuisines/popular';

  // Content endpoints
  static const String banners = '/banners';
  static const String bannersByType = '/banners';
  static const String activeOffers = '/offers/active';
  static const String legalContent = '/content/legal';
  static const String partnerApplications = '/partner-applications';

  // Order Customer endpoints
  static const String createOrder = '/orders';
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

  // Coupon endpoints
  static const String validateCoupon = '/coupons/validate';
  static const String offers = '/offers';

  // Payment endpoints
  static const String createPayment = '/payments/create';
  static const String verifyPayment = '/payments/verify';
  static const String activeDeliveryAreas = '/delivery-areas/active';

  // Restaurant Owner Dashboard endpoints
  static const String restaurantStores = '/restaurant/restaurants';
  static const String restaurantDashboard = '/restaurant/dashboard';
  static const String restaurantStats = '/restaurant/stats';
  static const String restaurantToggleStatus = '/restaurant/toggle-status';
  static const String restaurantInfo = '/restaurant/info';
  static const String restaurantProfile = '/restaurant/profile';
  static const String restaurantBankDetails = '/restaurant/bank-details';
  static const String restaurantLocation = '/restaurant/location';
  static const String updateBankDetails = '/restaurant/bank-details';
  static const String updateLocation = '/restaurant/location';
  static const String updateRestaurantProfile = '/restaurant/profile';
  static const String restaurantStaff = '/restaurant/staff';
  static const String restaurantOrders = '/restaurant/orders';
  static const String restaurantCategories = '/restaurant/categories';
  static const String restaurantMenuItems = '/restaurant/menu';
  static const String restaurantGlobalMenu = '/restaurant/global-menu';
  static const String restaurantGlobalCategories = '/restaurant/global-categories';
  static const String restaurantSettings = '/restaurant/settings';
  static const String restaurantLocationChangeRequest = '/restaurant/location-change-request';
  static const String restaurantAnalytics = '/restaurant/analytics';
  static const String restaurantPromos = '/restaurant/promos';
  static const String restaurantPrinters = '/restaurant/printers';
  static const String restaurantPrinterSettings = '/restaurant/printers/settings';
  static const String restaurantDiningBookings = '/restaurant/dining/bookings';
  static const String restaurantDiningStats = '/restaurant/dining/stats';
  static const String restaurantDiningUpcoming = '/restaurant/dining/upcoming';
  static const String restaurantDiningSettings = '/restaurant/dining/settings';
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
  static String restaurantVerifyTakeawayOtp(int orderId) =>
      '$restaurantOrders/$orderId/takeaway/verify-otp';
  static String restaurantDiningBookingDetails(int bookingId) =>
      '$restaurantDiningBookings/$bookingId';
  static String restaurantConfirmDiningBooking(int bookingId) =>
      '$restaurantDiningBookings/$bookingId/confirm';
  static String restaurantRejectDiningBooking(int bookingId) =>
      '$restaurantDiningBookings/$bookingId/reject';
  static String restaurantCompleteDiningBooking(int bookingId) =>
      '$restaurantDiningBookings/$bookingId/complete';

  // Dining endpoints
  static const String diningCelebrationTypes = '/dining/celebration-types';
  static const String diningRestaurants = '/dining/restaurants';
  static const String diningBook = '/dining/book';
  static const String diningMyBookings = '/dining/my-bookings';
  static const String diningCancel = '/dining/cancel';

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
  static const String campaignTrackClick = '/campaigns/track-click';
  static const String campaignTrackImpression = '/campaigns/track-impression';
}
