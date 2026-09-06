import 'package:flutter/material.dart';

import 'legal_document_screen.dart';
import 'restaurant_legal_screen.dart' show kAccountDeletionFallback;

/// Kept for the existing `/restaurant/profile/account-deletion-policy` route.
/// Content is admin-managed via `GET /content/legal` (`account_deletion`);
/// [kAccountDeletionFallback] shows only until an admin fills that field.
class AccountDeletionPolicyScreen extends StatelessWidget {
  const AccountDeletionPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const LegalDocumentScreen(
      title: 'Account deletion policy',
      contentKey: 'account_deletion',
      fallbackBody: kAccountDeletionFallback,
      intro:
          'Applies to restaurant partner and staff accounts on this platform.',
    );
  }
}
