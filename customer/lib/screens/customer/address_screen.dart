// lib/screens/customer/address_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

import '../../config/api_constants.dart';
import '../../models/address.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/common/app_skeleton.dart';
import '../../widgets/customer/profile_screen_chrome.dart';

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

  Future<void> _loadAddresses({bool forceRefresh = false}) async {
    if (mounted) setState(() => _isLoading = _addresses.isEmpty);

    try {
      final response = await _api.get(
        ApiConstants.addresses,
        cachePolicy: ApiCachePolicy.screen,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: _applyAddresses,
      );
      _applyAddresses(response);
    } catch (e) {
      debugPrint('Load addresses error: $e');
    }

    if (mounted) setState(() => _isLoading = false);
  }

  void _applyAddresses(dynamic response) {
    if (!mounted || response is! Map || response['success'] != true) return;
    final data = response['data'];
    if (data is! List) return;
    setState(() {
      _addresses = data
          .whereType<Map>()
          .map((json) => Address.fromJson(Map<String, dynamic>.from(json)))
          .toList();
    });
  }

  Future<void> _openAddAddress() async {
    await Navigator.pushNamed(context, '/addresses/add');
    if (mounted) await _loadAddresses(forceRefresh: true);
  }

  Future<void> _openEditAddress(Address address) async {
    await Navigator.pushNamed(
      context,
      '/addresses/edit',
      arguments: address,
    );
    if (mounted) await _loadAddresses(forceRefresh: true);
  }

  Future<void> _setDefaultAddress(int addressId) async {
    try {
      final response =
          await _api.post('${ApiConstants.setDefaultAddress}/$addressId');
      if (response['success'] == true) {
        await _loadAddresses(forceRefresh: true);
        if (!mounted) return;
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
        title: Text('Delete address'),
        content: Text('Are you sure you want to delete this address?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            style: TextButton.styleFrom(
              foregroundColor: profileButtonColor(context),
              textStyle: const TextStyle(
                fontSize: 13,
                height: 1.05,
                fontWeight: FontWeight.w800,
              ),
            ),
            child: Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(
              backgroundColor: profileButtonColor(context),
              foregroundColor: profileOnButtonColor(context),
              textStyle: const TextStyle(
                fontSize: 14,
                height: 1.05,
                fontWeight: FontWeight.w900,
              ),
            ),
            child: Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      final response = await _api.post(ApiConstants.deleteAddress(addressId));
      if (response['success'] == true) {
        await _loadAddresses(forceRefresh: true);
        if (!mounted) return;
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
      backgroundColor: profileCanvasColor(context),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => _loadAddresses(forceRefresh: true),
          color: profileAccentColor(context),
          child: Stack(
            children: [
              ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 116),
                children: [
                  ProfilePageTopBar(
                    title: 'Saved Addresses',
                    subtitle: _addresses.isEmpty
                        ? 'Manage your delivery places'
                        : '${_addresses.length} saved delivery places',
                    actions: [
                      const SizedBox(width: 12),
                      ProfileRoundButton(
                        icon: LucideIcons.plus,
                        color: profileAccentColor(context),
                        onTap: _openAddAddress,
                      ),
                    ],
                  ),
                  const SizedBox(height: 22),
                  ProfileSurfaceCard(
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
                    child: Row(
                      children: [
                        ProfileAccentIcon(
                          icon: LucideIcons.map_pin,
                          size: 58,
                          iconSize: 26,
                          radius: 20,
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Your delivery places',
                                style: TextStyle(
                                  color: profileTextColor(context),
                                  fontSize: 16,
                                  fontWeight: FontWeight.w800,
                                  height: 1.1,
                                ),
                              ),
                              SizedBox(height: 6),
                              Text(
                                'Save home, work, and go-to places for faster checkout.',
                                style: TextStyle(
                                  color: profileMutedColor(context),
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  height: 1.35,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),
                  ProfileSectionLabel(
                    title:
                        _addresses.isEmpty ? 'Get started' : 'Saved locations',
                  ),
                  const SizedBox(height: 10),
                  if (_isLoading && _addresses.isEmpty)
                    const AppSkeletonColumn(itemCount: 4, itemHeight: 104)
                  else if (_addresses.isEmpty)
                    _EmptyAddresses(onAdd: _openAddAddress)
                  else
                    ..._addresses.map(
                      (address) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _AddressCard(
                          address: address,
                          onSetDefault: () => _setDefaultAddress(address.id),
                          onEdit: () => _openEditAddress(address),
                          onDelete: () => _deleteAddress(address.id),
                        ),
                      ),
                    ),
                ],
              ),
              if (_isLoading && _addresses.isNotEmpty)
                const Positioned(
                  left: 0,
                  right: 0,
                  top: 0,
                  child: LinearProgressIndicator(minHeight: 2),
                ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(18, 8, 18, 14),
        child: SizedBox(
          height: 54,
          child: ElevatedButton.icon(
            onPressed: _openAddAddress,
            style: FoodFlowTheme.zomatoPrimaryButton(
                color: profileButtonColor(context),
                foregroundColor: profileOnButtonColor(context),
                radius: 18),
            icon: const Icon(LucideIcons.plus, size: 18),
            label: Text('Add New Address'),
          ),
        ),
      ),
    );
  }
}

class _AddressCard extends StatelessWidget {
  const _AddressCard({
    required this.address,
    required this.onSetDefault,
    required this.onEdit,
    required this.onDelete,
  });

  final Address address;
  final VoidCallback onSetDefault;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ProfileAccentIcon(
                icon: LucideIcons.house,
                size: 48,
                iconSize: 22,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      address.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: profileTextColor(context),
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Phone: ${address.phone}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: profileMutedColor(context),
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
              if (address.isDefault)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: profileSoftColor(context),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                        color: profileAccentColor(context).withOpacity(0.24)),
                  ),
                  child: Text(
                    'Default',
                    style: TextStyle(
                      color: profileAccentColor(context),
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            address.fullAddress,
            style: TextStyle(
              color: profileMutedColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              if (!address.isDefault) ...[
                Expanded(
                  child: OutlinedButton(
                    onPressed: onSetDefault,
                    style: FoodFlowTheme.zomatoOutlineButton(
                      color: profileButtonColor(context),
                      radius: 14,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                    child: Text('Set as Default'),
                  ),
                ),
                const SizedBox(width: 10),
              ],
              IconButton(
                style: FoodFlowTheme.softIconButton(
                  backgroundColor: profileButtonSoftColor(context),
                  foregroundColor: profileButtonColor(context),
                ),
                icon: const Icon(LucideIcons.pencil, size: 18),
                onPressed: onEdit,
              ),
              const SizedBox(width: 8),
              IconButton(
                style: FoodFlowTheme.softIconButton(
                  backgroundColor: profileButtonSoftColor(context),
                  foregroundColor: profileButtonColor(context),
                ),
                icon: const Icon(LucideIcons.trash_2, size: 18),
                onPressed: onDelete,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _EmptyAddresses extends StatelessWidget {
  const _EmptyAddresses({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return ProfileSurfaceCard(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          ProfileAccentIcon(
            icon: LucideIcons.map_pin_plus,
            size: 66,
            iconSize: 30,
            radius: 20,
          ),
          const SizedBox(height: 16),
          Text(
            'No saved addresses yet',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileTextColor(context),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Add home, work or your go-to spots for faster checkout.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: profileMutedColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: onAdd,
              style: FoodFlowTheme.zomatoPrimaryButton(
                  color: profileButtonColor(context),
                  foregroundColor: profileOnButtonColor(context),
                  radius: 14),
              child: Text('Add New Address'),
            ),
          ),
        ],
      ),
    );
  }
}
