import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../theme/aurora_theme.dart';
import '../../widgets/aurora/aurora.dart';
import '../../widgets/aurora/aurora_dialogs.dart';
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
      if (response['success'] == true) {
        await _loadStaff();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ?? 'Could not update staff status.',
            ),
          ),
        );
      }
    } catch (e) {
      debugPrint('Toggle staff error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to update status: ${_apiMessage(e)}')),
        );
      }
    }
  }

  Future<void> _deleteStaff(int id) async {
    final confirmed = await showAuroraConfirm(
      context,
      icon: Icons.person_remove_rounded,
      title: 'Remove staff member?',
      message:
          'Their login is revoked immediately. Order and menu history stays.',
      confirmLabel: 'Remove',
      destructive: true,
    );
    if (!confirmed) return;

    try {
      final response = await _api.delete(
        '${ApiConstants.restaurantStaff}/$id',
      );
      if (response['success'] == true) {
        await _loadStaff();
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ?? 'Could not remove staff member.',
            ),
          ),
        );
      }
    } catch (e) {
      debugPrint('Delete staff error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to remove staff: ${_apiMessage(e)}')),
        );
      }
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
          } else if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Staff account created.')),
            );
          }
        } else if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Staff details updated.')),
          );
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response['message']?.toString() ?? 'Could not save staff member.',
            ),
          ),
        );
      }
    } catch (e) {
      debugPrint('Save staff error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save staff: ${_apiMessage(e)}')),
        );
      }
    }
  }

  String _apiMessage(Object error) {
    if (error is ApiException) return error.message;
    final text = error.toString();
    return text.startsWith('Exception: ') ? text.substring(11) : text;
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
            onPressed: () {
              Clipboard.setData(
                ClipboardData(
                  text: 'Email: $email\nPhone: $phone\nPassword: $password',
                ),
              );
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Login details copied')),
              );
            },
            child: const Text('Copy'),
          ),
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
    final topPad = MediaQuery.of(context).padding.top + 60;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Staff',
                style: TextStyle(
                    color: foodflow.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w900)),
            Text('$activeCount of ${_staff.length} active',
                style: TextStyle(
                    color: foodflow.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700)),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openStaffEditor(),
        backgroundColor: foodflow.orange,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('Add staff',
            style: TextStyle(fontWeight: FontWeight.w800)),
      ),
      body: Stack(
        children: [
          ...AuroraTheme.auroraBlobs(),
          _isLoading
              ? const Center(child: CircularProgressIndicator())
              : RefreshIndicator(
                  onRefresh: _loadStaff,
                  child: ListView(
                    padding: EdgeInsets.fromLTRB(16, topPad, 16, 96),
                    children: [
                      _TeamHero(
                        active: activeCount,
                        total: _staff.length,
                        onAdd: () => _openStaffEditor(),
                      ),
                      const SizedBox(height: 14),
                      if (_staff.isEmpty)
                        _StaffEmpty(onAdd: () => _openStaffEditor())
                      else
                        ..._staff.map((item) {
                          final staff = Map<String, dynamic>.from(item as Map);
                          return Padding(
                            padding: const EdgeInsets.only(top: 10),
                            child: _StaffRosterCard(
                              staff: staff,
                              onEdit: () => _openStaffEditor(staff: staff),
                              onToggle: () => _toggleStaff(staff['id'] as int),
                              onDelete: () => _deleteStaff(staff['id'] as int),
                            ),
                          );
                        }),
                    ],
                  ),
                ),
        ],
      ),
    );
  }
}

class _TeamHero extends StatelessWidget {
  const _TeamHero(
      {required this.active, required this.total, required this.onAdd});

  final int active;
  final int total;
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: foodflow.brandGradient,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: foodflow.orange.withOpacity(0.26),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.16),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(Icons.groups_2_rounded, color: Colors.white),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text.rich(
                  TextSpan(
                    text: '$active',
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 30,
                        height: 1,
                        fontWeight: FontWeight.w900),
                    children: [
                      TextSpan(
                        text: '  / $total staff on shift access',
                        style: TextStyle(
                          color: Colors.white.withOpacity(0.85),
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Each login sees only what you grant below',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.8),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onAdd,
            icon: const Icon(Icons.add_rounded, color: Colors.white),
            style: IconButton.styleFrom(
              backgroundColor: Colors.white.withOpacity(0.16),
            ),
          ),
        ],
      ),
    );
  }
}

class _StaffEmpty extends StatelessWidget {
  const _StaffEmpty({required this.onAdd});
  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 20),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: foodflow.line),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: foodflow.orange.withOpacity(0.10),
              shape: BoxShape.circle,
            ),
            child:
                Icon(Icons.people_outline, color: foodflow.orange, size: 30),
          ),
          const SizedBox(height: 14),
          Text('No staff logins yet',
              style: TextStyle(
                  color: foodflow.ink,
                  fontSize: 16,
                  fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          Text(
            'Give managers, chefs and cashiers their own login with scoped access.',
            textAlign: TextAlign.center,
            style: TextStyle(color: foodflow.muted, fontSize: 13),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: onAdd,
              icon: const Icon(Icons.person_add_alt_1),
              label: const Text('Add first staff'),
              style: FoodFlowTheme.zomatoPrimaryButton(),
            ),
          ),
        ],
      ),
    );
  }
}

/// Roster row rebuilt around scoped-access visibility: avatar, name/role, a live
/// permission chip strip, and an inline active switch.
class _StaffRosterCard extends StatelessWidget {
  const _StaffRosterCard({
    required this.staff,
    required this.onEdit,
    required this.onToggle,
    required this.onDelete,
  });

  final Map<String, dynamic> staff;
  final VoidCallback onEdit;
  final VoidCallback onToggle;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final isActive = staff['is_active'] == true;
    final name = staff['name']?.toString() ?? 'Staff';
    final initial = name.isNotEmpty ? name[0].toUpperCase() : 'S';
    final perms = staff['permissions'];
    final permList = perms is List ? perms.map((e) => '$e').toList() : <String>[];
    const permMeta = {
      'orders': ('Orders', Icons.receipt_long_rounded),
      'menu': ('Menu', Icons.restaurant_menu_rounded),
      'reports': ('Reports', Icons.insights_rounded),
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: foodflow.surfaceColor,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isActive ? foodflow.line : foodflow.line.withOpacity(0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: isActive
                      ? foodflow.orange
                      : foodflow.muted.withOpacity(0.4),
                  shape: BoxShape.circle,
                ),
                child: Text(initial,
                    style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                        fontSize: 16)),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: foodflow.ink,
                            fontWeight: FontWeight.w900,
                            fontSize: 14)),
                    const SizedBox(height: 1),
                    Text(
                      '${staff['role'] ?? 'Staff'} · ${staff['shift']?.toString().isNotEmpty == true ? staff['shift'] : 'No shift'}',
                      style: TextStyle(
                          color: foodflow.muted,
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                icon: Icon(Icons.more_vert_rounded, color: foodflow.muted),
                onSelected: (value) {
                  if (value == 'edit') onEdit();
                  if (value == 'toggle') onToggle();
                  if (value == 'delete') onDelete();
                },
                itemBuilder: (context) => [
                  const PopupMenuItem(value: 'edit', child: Text('Edit')),
                  PopupMenuItem(
                    value: 'toggle',
                    child: Text(isActive ? 'Deactivate' : 'Activate'),
                  ),
                  const PopupMenuItem(value: 'delete', child: Text('Remove')),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    if (permList.isEmpty)
                      _PermChip(
                          label: 'View only',
                          icon: Icons.visibility_outlined,
                          on: false)
                    else
                      ...permMeta.entries.map((e) => _PermChip(
                            label: e.value.$1,
                            icon: e.value.$2,
                            on: permList.contains(e.key),
                          )),
                  ],
                ),
              ),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(isActive ? 'Active' : 'Off',
                      style: TextStyle(
                          color: foodflow.muted,
                          fontSize: 11,
                          fontWeight: FontWeight.w800)),
                  Transform.scale(
                    scale: 0.8,
                    child: Switch.adaptive(
                      value: isActive,
                      onChanged: (_) => onToggle(),
                      activeColor: foodflow.orange,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PermChip extends StatelessWidget {
  const _PermChip(
      {required this.label, required this.icon, required this.on});

  final String label;
  final IconData icon;
  final bool on;

  @override
  Widget build(BuildContext context) {
    final color = on ? foodflow.orange : foodflow.muted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: on ? foodflow.orange.withOpacity(0.10) : foodflow.canvas,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
            color: on ? foodflow.orange.withOpacity(0.3) : foodflow.line),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: color),
          const SizedBox(width: 4),
          Text(label,
              style: TextStyle(
                  color: color,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800)),
        ],
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
      backgroundColor: foodflow.canvas,
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
                title:
                    _isCreating ? 'Create Staff Login' : 'Update Staff Access',
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
                value:
                    widget.roles.contains(_role) ? _role : widget.roles.first,
                decoration: const InputDecoration(
                  labelText: 'Role',
                  prefixIcon: Icon(Icons.badge_outlined),
                ),
                items: widget.roles
                    .map((item) =>
                        DropdownMenuItem(value: item, child: Text(item)))
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
                  if (passwordText != confirmText)
                    return 'Passwords do not match';
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
              Text(
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
                subtitle:
                    const Text('Manage dishes, categories, and availability'),
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
                  label: Text(
                      _isCreating ? 'Create Staff Account' : 'Save Changes'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
