import 'package:flutter/material.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../widgets/restaurant/premium_restaurant_widgets.dart';

class StaffManagementScreen extends StatefulWidget {
  const StaffManagementScreen({super.key});

  @override
  State<StaffManagementScreen> createState() => _StaffManagementScreenState();
}

class _StaffManagementScreenState extends State<StaffManagementScreen> {
  final ApiService _api = ApiService();
  List<dynamic> _staff = [];
  bool _isLoading = true;

  static const _roles = [
    'Manager',
    'Chef',
    'Kitchen Staff',
    'Cashier',
    'Packing Staff',
    'Support',
  ];

  @override
  void initState() {
    super.initState();
    _loadStaff();
  }

  Future<void> _loadStaff() async {
    setState(() => _isLoading = true);
    try {
      final response = await _api.get(ApiConstants.restaurantStaff);
      if (response['success'] == true && mounted) {
        setState(() => _staff = response['data'] ?? []);
      }
    } catch (e) {
      debugPrint('Load staff error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load staff: $e')),
        );
      }
    }
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _toggleStaff(int id) async {
    try {
      final response =
          await _api.post('${ApiConstants.restaurantStaff}/$id/toggle');
      if (response['success'] == true) await _loadStaff();
    } catch (e) {
      debugPrint('Toggle staff error: $e');
    }
  }

  Future<void> _deleteStaff(int id) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Remove Staff'),
        content: const Text('Remove this staff member from your restaurant?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Remove', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final response = await _api.delete('${ApiConstants.restaurantStaff}/$id');
      if (response['success'] == true) await _loadStaff();
    } catch (e) {
      debugPrint('Delete staff error: $e');
    }
  }

  Future<void> _openStaffEditor({Map<String, dynamic>? staff}) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => _StaffEditorScreen(
          staff: staff,
          roles: _roles,
          onSave: _saveStaff,
        ),
      ),
    );
  }

  Future<void> _saveStaff({
    int? id,
    required String name,
    required String phone,
    required String email,
    required String role,
    required String shift,
    required double? salary,
    required String password,
    required List<String> permissions,
  }) async {
    final data = {
      'name': name,
      'phone': phone,
      'email': email,
      'role': role,
      'shift': shift,
      'salary': salary,
      'permissions': permissions,
      if (password.isNotEmpty) 'password': password,
      if (password.isNotEmpty) 'password_confirmation': password,
    };

    try {
      final response = id == null
          ? await _api.post(ApiConstants.restaurantStaff, data: data)
          : await _api.put('${ApiConstants.restaurantStaff}/$id', data: data);
      if (response['success'] == true) {
        await _loadStaff();
        if (!mounted) return;

        if (id == null) {
          final responseData = response['data'];
          final account = responseData is Map<String, dynamic>
              ? responseData['account']
              : null;
          if (account is Map) {
            await _showAccountCreatedDialog(Map<String, dynamic>.from(account));
          }
        }
      }
    } catch (e) {
      debugPrint('Save staff error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save staff: $e')),
        );
      }
    }
  }

  bool _hasPermission(Map<String, dynamic>? staff, String permission) {
    final permissions = staff?['permissions'];
    return permissions is List && permissions.contains(permission);
  }

  Future<void> _showAccountCreatedDialog(
    Map<String, dynamic> account,
  ) {
    final email = account['email']?.toString() ?? '-';
    final phone = account['phone']?.toString() ?? '-';
    final password = account['temporary_password']?.toString() ?? '-';

    return showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Staff Account Created'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Share these login details with your staff member.',
            ),
            const SizedBox(height: 14),
            SelectableText('Email: $email'),
            const SizedBox(height: 6),
            SelectableText('Phone: $phone'),
            const SizedBox(height: 6),
            SelectableText('Password: $password'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final activeCount =
        _staff.where((item) => item is Map && item['is_active'] == true).length;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Staff Management'),
        actions: [
          IconButton(
            onPressed: () => _openStaffEditor(),
            icon: const Icon(Icons.person_add_alt_1),
            tooltip: 'Add staff',
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadStaff,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                children: [
                  PremiumRestaurantHeader(
                    title: 'Team Control',
                    subtitle:
                        '$activeCount active • ${_staff.length} total staff members',
                    icon: Icons.groups_2_outlined,
                    trailing: IconButton(
                      onPressed: () => _openStaffEditor(),
                      icon: const Icon(Icons.add),
                      color: Colors.white,
                    ),
                  ),
                  if (_staff.isEmpty)
                    FoodFlowTheme.emptyState(
                      icon: Icons.people_outline,
                      title: 'No staff added',
                      subtitle:
                          'Add managers, chefs, cashiers, and packing staff.',
                    )
                  else
                    ..._staff.map((item) {
                      final staff = Map<String, dynamic>.from(item as Map);
                      final isActive = staff['is_active'] == true;
                      final id = staff['id'] as int;
                      return Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        decoration: RestaurantPremium.panel(radius: 16),
                        child: ListTile(
                          contentPadding:
                              const EdgeInsets.fromLTRB(14, 10, 8, 10),
                          leading: CircleAvatar(
                            backgroundColor: isActive
                                ? FoodFlowTheme.orange
                                : FoodFlowTheme.faint,
                            child: Text(
                              (staff['name']?.toString().isNotEmpty == true
                                      ? staff['name'].toString()[0]
                                      : 'S')
                                  .toUpperCase(),
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          title: Text(
                            staff['name']?.toString() ?? 'Staff',
                            style: const TextStyle(
                              color: FoodFlowTheme.ink,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          subtitle: Text(
                            '${staff['role'] ?? 'Staff'} • ${staff['shift'] ?? 'No shift'}\n${staff['phone'] ?? 'No phone'}',
                            style: const TextStyle(
                              color: FoodFlowTheme.muted,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          isThreeLine: true,
                          trailing: PopupMenuButton<String>(
                            onSelected: (value) {
                              if (value == 'edit')
                                _openStaffEditor(staff: staff);
                              if (value == 'toggle') _toggleStaff(id);
                              if (value == 'delete') _deleteStaff(id);
                            },
                            itemBuilder: (context) => [
                              const PopupMenuItem(
                                value: 'edit',
                                child: Text('Edit'),
                              ),
                              PopupMenuItem(
                                value: 'toggle',
                                child:
                                    Text(isActive ? 'Deactivate' : 'Activate'),
                              ),
                              const PopupMenuItem(
                                value: 'delete',
                                child: Text('Remove'),
                              ),
                            ],
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openStaffEditor(),
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('Add Staff'),
      ),
    );
  }
}

class _StaffEditorScreen extends StatefulWidget {
  const _StaffEditorScreen({
    required this.roles,
    required this.onSave,
    this.staff,
  });

  final Map<String, dynamic>? staff;
  final List<String> roles;
  final Future<void> Function({
    int? id,
    required String name,
    required String phone,
    required String email,
    required String role,
    required String shift,
    required double? salary,
    required String password,
    required List<String> permissions,
  }) onSave;

  @override
  State<_StaffEditorScreen> createState() => _StaffEditorScreenState();
}

class _StaffEditorScreenState extends State<_StaffEditorScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _shift;
  late final TextEditingController _salary;
  late final TextEditingController _password;
  late final TextEditingController _confirmPassword;
  late String _role;
  late bool _canOrders;
  late bool _canMenu;
  late bool _canReports;
  bool _isSaving = false;

  bool get _isCreating => widget.staff == null;

  @override
  void initState() {
    super.initState();
    final staff = widget.staff;
    _name = TextEditingController(text: staff?['name']?.toString() ?? '');
    _phone = TextEditingController(text: staff?['phone']?.toString() ?? '');
    _email = TextEditingController(text: staff?['email']?.toString() ?? '');
    _shift = TextEditingController(text: staff?['shift']?.toString() ?? '');
    _salary = TextEditingController(text: staff?['salary']?.toString() ?? '');
    _password = TextEditingController();
    _confirmPassword = TextEditingController();
    _role = staff?['role']?.toString() ?? widget.roles.first;
    final permissions = staff?['permissions'];
    _canOrders = permissions is List && permissions.contains('orders');
    _canMenu = permissions is List && permissions.contains('menu');
    _canReports = permissions is List && permissions.contains('reports');
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _shift.dispose();
    _salary.dispose();
    _password.dispose();
    _confirmPassword.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _isSaving) return;
    setState(() => _isSaving = true);
    try {
      await widget.onSave(
        id: widget.staff?['id'] is int ? widget.staff!['id'] as int : null,
        name: _name.text.trim(),
        phone: _phone.text.trim(),
        email: _email.text.trim(),
        role: _role,
        shift: _shift.text.trim(),
        salary: double.tryParse(_salary.text.trim()),
        password: _password.text.trim(),
        permissions: [
          if (_canOrders) 'orders',
          if (_canMenu) 'menu',
          if (_canReports) 'reports',
        ],
      );
      if (mounted) Navigator.pop(context);
    } finally {
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: Text(_isCreating ? 'Add Staff' : 'Edit Staff'),
      ),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              PremiumRestaurantHeader(
                title: _isCreating ? 'Create Staff Login' : 'Update Staff Access',
                subtitle: _isCreating
                    ? 'Add a staff account without cramped modal forms.'
                    : 'Adjust permissions, contact details, and password safely.',
                icon: Icons.badge_outlined,
              ),
              TextFormField(
                controller: _name,
                decoration: const InputDecoration(
                  labelText: 'Name',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (value) =>
                    value == null || value.trim().isEmpty ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: widget.roles.contains(_role) ? _role : widget.roles.first,
                decoration: const InputDecoration(
                  labelText: 'Role',
                  prefixIcon: Icon(Icons.badge_outlined),
                ),
                items: widget.roles
                    .map((item) => DropdownMenuItem(value: item, child: Text(item)))
                    .toList(),
                onChanged: (value) => setState(() => _role = value!),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Phone',
                  prefixIcon: Icon(Icons.call_outlined),
                ),
                validator: (value) => value == null || value.trim().isEmpty
                    ? 'Phone is required for staff login'
                    : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Email',
                  prefixIcon: Icon(Icons.mail_outline),
                ),
                validator: (value) {
                  final text = value?.trim() ?? '';
                  if (text.isEmpty) return 'Email is required for staff login';
                  final emailRegex = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');
                  if (!emailRegex.hasMatch(text)) return 'Enter a valid email';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _password,
                obscureText: true,
                decoration: InputDecoration(
                  labelText: _isCreating ? 'Password' : 'Reset Password',
                  hintText: _isCreating
                      ? 'Enter login password'
                      : 'Leave blank to keep current password',
                  prefixIcon: const Icon(Icons.lock_outline),
                ),
                validator: (value) {
                  final text = value?.trim() ?? '';
                  if (_isCreating && text.isEmpty) {
                    return 'Password is required for staff login';
                  }
                  if (text.isNotEmpty && text.length < 8) {
                    return 'Use at least 8 characters';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _confirmPassword,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Confirm Password',
                  prefixIcon: Icon(Icons.lock_reset_outlined),
                ),
                validator: (value) {
                  final passwordText = _password.text.trim();
                  final confirmText = value?.trim() ?? '';
                  if (passwordText.isEmpty && !_isCreating) return null;
                  if (confirmText.isEmpty) return 'Confirm the password';
                  if (passwordText != confirmText) return 'Passwords do not match';
                  return null;
                },
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _shift,
                      decoration: const InputDecoration(
                        labelText: 'Shift',
                        hintText: '10 AM - 7 PM',
                        prefixIcon: Icon(Icons.schedule),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _salary,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Salary',
                        prefixIcon: Icon(Icons.payments_outlined),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Access Control',
                style: TextStyle(
                  color: FoodFlowTheme.ink,
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              SwitchListTile(
                value: _canOrders,
                onChanged: (value) => setState(() => _canOrders = value),
                title: const Text('Order handling'),
                subtitle: const Text('Accept, prepare, and manage orders'),
                activeColor: FoodFlowTheme.orange,
                contentPadding: EdgeInsets.zero,
              ),
              SwitchListTile(
                value: _canMenu,
                onChanged: (value) => setState(() => _canMenu = value),
                title: const Text('Menu updates'),
                subtitle: const Text('Manage dishes, categories, and availability'),
                activeColor: FoodFlowTheme.orange,
                contentPadding: EdgeInsets.zero,
              ),
              SwitchListTile(
                value: _canReports,
                onChanged: (value) => setState(() => _canReports = value),
                title: const Text('Reports access'),
                subtitle: const Text('View analytics and business performance'),
                activeColor: FoodFlowTheme.orange,
                contentPadding: EdgeInsets.zero,
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: _isSaving ? null : _submit,
                  icon: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: Text(_isCreating ? 'Create Staff Account' : 'Save Changes'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
