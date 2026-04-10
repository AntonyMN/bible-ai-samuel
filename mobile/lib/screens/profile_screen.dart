import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/chat_provider.dart';
import '../services/api_service.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _nameController;
  late TextEditingController _emailController;
  String? _selectedBibleVersion;
  String? _selectedMode;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    final chatProvider = context.read<ChatProvider>();
    
    _nameController = TextEditingController(text: user?.name ?? '');
    _emailController = TextEditingController(text: user?.email ?? '');
    _selectedBibleVersion = chatProvider.selectedBibleVersion;
    _selectedMode = chatProvider.selectedMode;
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);
    
    final apiService = context.read<ApiService>();
    final success = await apiService.updateProfile({
      'name': _nameController.text,
      'email': _emailController.text,
      'bible_version': _selectedBibleVersion,
      'mode': _selectedMode,
    });

    if (mounted) {
      setState(() => _isLoading = false);
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile updated successfully')),
        );
        // Refresh local providers
        context.read<ChatProvider>().selectedBibleVersion = _selectedBibleVersion!;
        context.read<ChatProvider>().selectedMode = _selectedMode!;
        // AuthProvider might need an update to user object if we want it reflected elsewhere
        Navigator.pop(context);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to update profile')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Profile'),
        backgroundColor: Colors.purple[50],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(labelText: 'Name', border: OutlineInputBorder()),
                validator: (val) => val == null || val.isEmpty ? 'Enter your name' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _emailController,
                decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder()),
                validator: (val) => val == null || val.isEmpty ? 'Enter your email' : null,
              ),
              const SizedBox(height: 24),
              const Text('Preferences', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                value: _selectedBibleVersion,
                decoration: const InputDecoration(labelText: 'Default Bible Version', border: OutlineInputBorder()),
                items: ['BSB', 'KJV', 'ASV', 'WEB'].map((v) => DropdownMenuItem(value: v, child: Text(v))).toList(),
                onChanged: (val) => setState(() => _selectedBibleVersion = val),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                value: _selectedMode,
                decoration: const InputDecoration(labelText: 'Default Chat Mode', border: OutlineInputBorder()),
                items: [
                  {'value': 'fast', 'label': 'Short and Sweet'},
                  {'value': 'deep', 'label': 'Deep Reflection'},
                  {'value': 'research', 'label': 'Research Mode'},
                ].map((m) => DropdownMenuItem(value: m['value'], child: Text(m['label']!))).toList(),
                onChanged: (val) => setState(() => _selectedMode = val),
              ),
              const SizedBox(height: 32),
              ElevatedButton(
                onPressed: _isLoading ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.purple,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: _isLoading 
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Save Changes'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
