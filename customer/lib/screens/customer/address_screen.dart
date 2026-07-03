// lib/screens/customer/address_screen.dart
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../config/api_constants.dart';
import '../../models/address.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/common/lucide_icon.dart';
import '../../widgets/customer/account_chrome.dart';

class AddressScreen extends StatefulWidget {
  const AddressScreen({Key? key}) : super(key: key);

  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  final ApiService _api = ApiService();
  List<Address> _addresses = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  Future<void> _loadAddresses() async {
    setState(() => _isLoading = true);

    try {
      final response = await _api.get(ApiConstants.addresses);
      if (response['success'] == true) {
        final List<dynamic> addressesData = response['data'];
        setState(() {
          _addresses =
              addressesData.map((json) => Address.fromJson(json)).toList();
        });
      }
    } catch (e) {
      debugPrint('Load addresses error: $e');
    }

    setState(() => _isLoading = false);
  }

  Future<void> _setDefaultAddress(int addressId) async {
    try {
      final response =
          await _api.post('${ApiConstants.setDefaultAddress}/$addressId');
      if (response['success'] == true) {
        await _loadAddresses();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Default address updated')),
        );
      }
    } catch (e) {
      debugPrint('Set default error: $e');
    }
  }

  Future<void> _deleteAddress(int addressId) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Address'),
        content: const Text('Are you sure you want to delete this address?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      final response = await _api.post(ApiConstants.deleteAddress(addressId));
      if (response['success'] == true) {
        await _loadAddresses();
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Address deleted successfully')),
        );
      }
    } catch (e) {
      debugPrint('Delete address error: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      appBar: AppBar(
        title: const Text(
          'Saved Addresses',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        backgroundColor: accountCanvas,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: ElevatedButton.icon(
              onPressed: () {
                Navigator.pushNamed(context, '/addresses/add');
              },
              icon: const AppIcon(AppIcons.add, size: 16),
              label: const Text('Add New'),
              style: FoodFlowTheme.zomatoPrimaryButton(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                radius: 14,
              ),
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              padding: EdgeInsets.zero,
              itemCount: (_addresses.isEmpty ? 1 : _addresses.length) + 2,
              itemBuilder: (context, index) {
                if (index == 0) {
                  return const AccountHeroCard(
                    title: 'Your delivery places',
                    subtitle:
                        'Save home, work, and your go-to places for faster checkout and smoother ordering.',
                    icon: Icons.location_on_outlined,
                    badge: 'PROFILE SPACE',
                  );
                }
                if (index == 1) {
                  return Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                    child: AccountSectionTitle(
                      title: _addresses.isEmpty
                          ? 'GET STARTED'
                          : 'SAVED LOCATIONS',
                    ),
                  );
                }
                if (_addresses.isEmpty) {
                  return AccountSurfaceCard(
                    margin: const EdgeInsets.fromLTRB(20, 0, 20, 24),
                    padding: const EdgeInsets.all(24),
                    radius: 28,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 76,
                          height: 76,
                          decoration: BoxDecoration(
                            color: const Color(0xFFFFF1EE),
                            borderRadius: BorderRadius.circular(24),
                          ),
                          child: const Center(
                            child: AppIcon(AppIcons.locationPin, size: 40),
                          ),
                        ),
                        const SizedBox(height: 18),
                        const Text(
                          'No saved addresses yet',
                          style: TextStyle(
                            fontSize: 20,
                            color: FoodFlowTheme.ink,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Add home, work or your go-to spots for faster checkout.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: 13,
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w500,
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 18),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: () {
                              Navigator.pushNamed(context, '/addresses/add');
                            },
                            child: const Text('Add New Address'),
                          ),
                        ),
                      ],
                    ),
                  );
                }
                final address = _addresses[index - 2];
                return AccountSurfaceCard(
                  margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                  radius: 24,
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const AppIcon(AppIcons.home, size: 20),
                            const SizedBox(width: 8),
                            Text(
                              address.name,
                              style: const TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w900,
                                color: FoodFlowTheme.ink,
                              ),
                            ),
                            const Spacer(),
                            if (address.isDefault)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF1EE),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: const Text(
                                  'Default',
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: FoodFlowTheme.crimson,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          address.fullAddress,
                          style: const TextStyle(
                            fontSize: 14,
                            color: FoodFlowTheme.inkSoft,
                            fontWeight: FontWeight.w500,
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Phone: ${address.phone}',
                          style: const TextStyle(
                            fontSize: 13,
                            color: FoodFlowTheme.muted,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            if (!address.isDefault)
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () =>
                                      _setDefaultAddress(address.id),
                                  style: FoodFlowTheme.zomatoOutlineButton(
                                    radius: 16,
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 12,
                                    ),
                                  ),
                                  child: const Text('Set as Default'),
                                ),
                              ),
                            if (!address.isDefault) const SizedBox(width: 10),
                            IconButton(
                              style: FoodFlowTheme.softIconButton(
                                backgroundColor: const Color(0xFFFFF8F4),
                                foregroundColor: FoodFlowTheme.crimson,
                              ),
                              icon: const AppIcon(AppIcons.edit, size: 20),
                              onPressed: () {
                                Navigator.pushNamed(
                                  context,
                                  '/addresses/edit',
                                  arguments: address,
                                );
                              },
                            ),
                            const SizedBox(width: 8),
                            IconButton(
                              style: FoodFlowTheme.softIconButton(
                                backgroundColor: const Color(0xFFFFF2F2),
                                foregroundColor: const Color(0xFFDC2626),
                              ),
                              icon: const AppIcon(AppIcons.delete, size: 20),
                              onPressed: () => _deleteAddress(address.id),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}
