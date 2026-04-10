import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/memory_provider.dart';

class MemoriesScreen extends StatefulWidget {
  const MemoriesScreen({super.key});

  @override
  State<MemoriesScreen> createState() => _MemoriesScreenState();
}

class _MemoriesScreenState extends State<MemoriesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemoryProvider>().loadMemories();
    });
  }

  IconData _getIconForCategory(String category) {
    switch (category.toLowerCase()) {
      case 'events': return Icons.event;
      case 'struggles': return Icons.psychology;
      case 'victories': return Icons.emoji_events;
      case 'prayer points': return Icons.volunteer_activism;
      case 'knowledge base': return Icons.menu_book;
      case 'plans': return Icons.assignment;
      default: return Icons.info;
    }
  }

  Color _getImportanceColor(int? importance) {
    switch (importance) {
      case 5: return Colors.red[700]!;
      case 4: return Colors.orange[700]!;
      case 3: return Colors.blue[700]!;
      default: return Colors.grey[600]!;
    }
  }

  @override
  Widget build(BuildContext context) {
    final memoryProvider = context.watch<MemoryProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Life (Memories)'),
        backgroundColor: Colors.purple[50],
      ),
      body: memoryProvider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : memoryProvider.memories.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.history_edu, size: 64, color: Colors.grey[300]),
                      const SizedBox(height: 16),
                      const Text('Samuel has not remembered any facts yet.', style: TextStyle(color: Colors.grey)),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: memoryProvider.memories.length,
                  itemBuilder: (context, index) {
                    final memory = memoryProvider.memories[index];
                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: Colors.purple[50],
                          child: Icon(_getIconForCategory(memory['category'] ?? ''), color: Colors.purple),
                        ),
                        title: Text(memory['content'] ?? ''),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Text(
                                  (memory['category'] ?? 'other').toUpperCase(),
                                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                                ),
                                const SizedBox(width: 8),
                                Container(
                                  width: 8,
                                  height: 8,
                                  decoration: BoxDecoration(
                                    color: _getImportanceColor(memory['importance']),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete_outline, color: Colors.grey),
                          onPressed: () => _confirmDelete(memory['id']?.toString() ?? ''),
                        ),
                      ),
                    );
                  },
                ),
    );
  }

  void _confirmDelete(String id) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm'),
        content: const Text('Do you want Samuel to forget this?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          TextButton(
            onPressed: () {
              context.read<MemoryProvider>().deleteMemory(id);
              Navigator.pop(context);
            },
            child: const Text('Delete', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }
}
