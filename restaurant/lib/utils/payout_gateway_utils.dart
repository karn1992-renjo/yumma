class PayoutGatewayProfile {
  const PayoutGatewayProfile({
    required this.provider,
    required this.displayName,
    required this.countryCode,
    required this.routingCodeLabel,
    required this.routingCodeHint,
    required this.accountIdLabel,
    required this.accountIdHint,
    required this.helperText,
    required this.showBankDetails,
    required this.bankDetailsRequired,
    this.supportsUpi = false,
    this.requiresAccountId = false,
  });

  final String provider;
  final String displayName;
  final String countryCode;
  final String routingCodeLabel;
  final String routingCodeHint;
  final String accountIdLabel;
  final String accountIdHint;
  final String helperText;
  final bool supportsUpi;
  final bool requiresAccountId;
  final bool showBankDetails;
  final bool bankDetailsRequired;
}

PayoutGatewayProfile resolvePayoutGatewayProfile({
  String? provider,
  String? countryCode,
}) {
  final normalizedCountry = (countryCode ?? '').trim().toUpperCase();
  final normalizedProvider =
      _normalizeProvider(provider, fallbackCountryCode: normalizedCountry);

  switch (normalizedProvider) {
    case 'razorpay':
      return const PayoutGatewayProfile(
        provider: 'razorpay',
        displayName: 'Razorpay',
        countryCode: 'IN',
        routingCodeLabel: 'IFSC Code',
        routingCodeHint: 'e.g., HDFC0001234',
        accountIdLabel: 'Razorpay Route Account ID',
        accountIdHint: 'Optional for marketplace payouts',
        helperText:
            'Indian payouts use bank account details. Add UPI only if your ops team uses it for fallback settlement.',
        showBankDetails: true,
        bankDetailsRequired: true,
        supportsUpi: true,
      );
    case 'stripe':
      final routingLabel = _stripeRoutingLabel(normalizedCountry);
      return PayoutGatewayProfile(
        provider: 'stripe',
        displayName: 'Stripe',
        countryCode: normalizedCountry.isEmpty ? 'US' : normalizedCountry,
        routingCodeLabel: routingLabel,
        routingCodeHint: _routingHint(routingLabel),
        accountIdLabel: 'Stripe Connected Account ID',
        accountIdHint: 'e.g., acct_1234',
        helperText:
            'Stripe payouts depend on your country. Use the connected account ID and the banking code used in your region.',
        showBankDetails: false,
        bankDetailsRequired: false,
        requiresAccountId: true,
      );
    case 'paypal':
      return const PayoutGatewayProfile(
        provider: 'paypal',
        displayName: 'PayPal',
        countryCode: 'GLOBAL',
        routingCodeLabel: 'Payout Reference',
        routingCodeHint: 'Optional settlement reference',
        accountIdLabel: 'PayPal Email',
        accountIdHint: 'merchant@example.com',
        helperText:
            'PayPal payouts are usually linked to the merchant email instead of local bank routing details.',
        showBankDetails: false,
        bankDetailsRequired: false,
        requiresAccountId: true,
      );
    case 'paystack':
      return const PayoutGatewayProfile(
        provider: 'paystack',
        displayName: 'Paystack',
        countryCode: 'NG',
        routingCodeLabel: 'Bank Code',
        routingCodeHint: 'Enter settlement bank code',
        accountIdLabel: 'Paystack Recipient / Subaccount Code',
        accountIdHint: 'Optional transfer recipient or subaccount code',
        helperText:
            'Paystack payouts usually need the recipient bank code plus account details for your settlement country.',
        showBankDetails: true,
        bankDetailsRequired: true,
      );
    case 'sslcommerz':
      return const PayoutGatewayProfile(
        provider: 'sslcommerz',
        displayName: 'SSLCommerz',
        countryCode: 'BD',
        routingCodeLabel: 'Bank Routing Number',
        routingCodeHint: 'Enter the Bangladeshi bank routing number',
        accountIdLabel: 'Merchant / Settlement ID',
        accountIdHint: 'Optional SSLCommerz settlement ID',
        helperText:
            'SSLCommerz settlements in Bangladesh usually rely on bank account routing details tied to your merchant profile.',
        showBankDetails: true,
        bankDetailsRequired: true,
      );
    case 'mollie':
      return const PayoutGatewayProfile(
        provider: 'mollie',
        displayName: 'Mollie',
        countryCode: 'NL',
        routingCodeLabel: 'IBAN / SWIFT Code',
        routingCodeHint: 'Enter IBAN routing or SWIFT code',
        accountIdLabel: 'Mollie Organization / Profile ID',
        accountIdHint: 'Optional connected payout profile',
        helperText:
            'Mollie payouts are usually managed through your organization profile with EU bank settlement details.',
        showBankDetails: true,
        bankDetailsRequired: true,
      );
    case 'senangpay':
      return const PayoutGatewayProfile(
        provider: 'senangpay',
        displayName: 'SenangPay',
        countryCode: 'MY',
        routingCodeLabel: 'Bank Code',
        routingCodeHint: 'Enter Malaysian bank code',
        accountIdLabel: 'SenangPay Merchant ID',
        accountIdHint: 'Merchant or settlement account ID',
        helperText:
            'SenangPay payouts typically use a Malaysian bank account attached to your merchant ID.',
        showBankDetails: true,
        bankDetailsRequired: true,
      );
    case 'bkash':
      return const PayoutGatewayProfile(
        provider: 'bkash',
        displayName: 'bKash',
        countryCode: 'BD',
        routingCodeLabel: 'Wallet / Branch Code',
        routingCodeHint: 'Optional branch or wallet routing reference',
        accountIdLabel: 'bKash Wallet Number',
        accountIdHint: 'Enter the settlement wallet number',
        helperText:
            'bKash settlements often route to a verified wallet number instead of a traditional payout account.',
        showBankDetails: false,
        bankDetailsRequired: false,
        requiresAccountId: true,
      );
    case 'mercadopago':
      return const PayoutGatewayProfile(
        provider: 'mercadopago',
        displayName: 'Mercado Pago',
        countryCode: 'LATAM',
        routingCodeLabel: 'CBU / PIX / Bank Routing Code',
        routingCodeHint: 'Enter the settlement routing code used in your country',
        accountIdLabel: 'Mercado Pago Collector ID',
        accountIdHint: 'Collector or marketplace account ID',
        helperText:
            'Mercado Pago payout requirements vary by country, so use the local settlement routing format configured by admin.',
        showBankDetails: true,
        bankDetailsRequired: false,
      );
    case 'skrill':
      return const PayoutGatewayProfile(
        provider: 'skrill',
        displayName: 'Skrill',
        countryCode: 'GLOBAL',
        routingCodeLabel: 'Bank / SWIFT Code',
        routingCodeHint: 'Optional if you settle to bank instead of wallet',
        accountIdLabel: 'Skrill Email / Wallet ID',
        accountIdHint: 'merchant@example.com',
        helperText:
            'Skrill payouts are commonly linked to a wallet email or wallet ID, with bank routing only when required.',
        showBankDetails: false,
        bankDetailsRequired: false,
        requiresAccountId: true,
      );
    case 'easypaisa':
      return const PayoutGatewayProfile(
        provider: 'easypaisa',
        displayName: 'EasyPaisa',
        countryCode: 'PK',
        routingCodeLabel: 'Branch / Bank Code',
        routingCodeHint: 'Enter the branch or bank code for settlement',
        accountIdLabel: 'EasyPaisa Wallet Number',
        accountIdHint: 'Enter the EasyPaisa wallet or merchant account number',
        helperText:
            'EasyPaisa payouts usually settle to a verified wallet number or linked Pakistani bank account.',
        showBankDetails: false,
        bankDetailsRequired: false,
        requiresAccountId: true,
      );
    case 'flutterwave':
      return const PayoutGatewayProfile(
        provider: 'flutterwave',
        displayName: 'Flutterwave',
        countryCode: 'AFRICA',
        routingCodeLabel: 'Bank Code',
        routingCodeHint: 'Enter settlement bank code',
        accountIdLabel: 'Flutterwave Subaccount ID',
        accountIdHint: 'Optional connected payout account',
        helperText:
            'Flutterwave settlements vary by country, so use the bank code your local payout partner expects.',
        showBankDetails: true,
        bankDetailsRequired: false,
      );
    default:
      final label = _countryRoutingLabel(normalizedCountry);
      return PayoutGatewayProfile(
        provider: normalizedProvider,
        displayName: _displayName(normalizedProvider),
        countryCode: normalizedCountry.isEmpty ? 'GLOBAL' : normalizedCountry,
        routingCodeLabel: label,
        routingCodeHint: _routingHint(label),
        accountIdLabel: 'Gateway Merchant Account ID',
        accountIdHint: 'Optional connected payout account',
        helperText:
            'Payout details adapt to the selected gateway and country so restaurants do not have to enter India-only banking fields.',
        showBankDetails: true,
        bankDetailsRequired: false,
      );
  }
}

String _normalizeProvider(String? provider, {required String fallbackCountryCode}) {
  final normalized = (provider ?? '').trim().toLowerCase();
  if (normalized.isNotEmpty) return normalized;

  switch (fallbackCountryCode) {
    case 'IN':
      return 'razorpay';
    case 'NG':
    case 'GH':
      return 'paystack';
    case 'BD':
      return 'sslcommerz';
    case 'MY':
      return 'senangpay';
    case 'PK':
      return 'easypaisa';
    case 'KE':
    case 'UG':
    case 'TZ':
      return 'flutterwave';
    case 'US':
    case 'CA':
    case 'GB':
    case 'UK':
    case 'AU':
    case 'NZ':
    case 'SG':
    case 'AE':
    case 'DE':
    case 'FR':
    case 'ES':
    case 'IT':
    case 'NL':
      return 'stripe';
    default:
      return 'stripe';
  }
}

String _displayName(String provider) {
  if (provider.isEmpty) return 'Payment Gateway';
  return provider
      .split(RegExp(r'[_\s-]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _stripeRoutingLabel(String countryCode) {
  switch (countryCode) {
    case 'IN':
      return 'IFSC Code';
    case 'US':
      return 'Routing Number';
    case 'CA':
      return 'Transit Number';
    case 'GB':
    case 'UK':
      return 'Sort Code';
    case 'AU':
      return 'BSB Code';
    case 'BD':
      return 'Bank Routing Number';
    case 'MY':
      return 'Bank Code';
    case 'PK':
      return 'Branch / Bank Code';
    case 'NZ':
      return 'Bank / Branch Code';
    default:
      return 'SWIFT / Routing Code';
  }
}

String _countryRoutingLabel(String countryCode) {
  switch (countryCode) {
    case 'IN':
      return 'IFSC Code';
    case 'US':
      return 'Routing Number';
    case 'CA':
      return 'Transit Number';
    case 'GB':
    case 'UK':
      return 'Sort Code';
    case 'AU':
      return 'BSB Code';
    case 'BD':
      return 'Bank Routing Number';
    case 'MY':
      return 'Bank Code';
    case 'PK':
      return 'Branch / Bank Code';
    default:
      return 'Bank Routing Code';
  }
}

String _routingHint(String label) {
  final normalized = label.toLowerCase();
  if (normalized.contains('ifsc')) return 'e.g., HDFC0001234';
  if (normalized.contains('routing')) return 'Enter bank routing number';
  if (normalized.contains('sort')) return 'e.g., 12-34-56';
  if (normalized.contains('bsb')) return 'e.g., 062-000';
  if (normalized.contains('swift')) return 'e.g., CHASUS33';
  if (normalized.contains('transit')) return 'Enter transit / institution code';
  if (normalized.contains('branch')) return 'Enter branch or bank code';
  return 'Enter the code used for payouts in your country';
}
