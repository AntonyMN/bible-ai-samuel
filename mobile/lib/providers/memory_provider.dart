import 'package:flutter/material.dart';
import '../services/api_service.dart';

class MemoryProvider with ChangeNotifier {
  final ApiService _apiService;
  List<dynamic> _memories = [];
  bool _isLoading = false;

  MemoryProvider(this._apiService);

  List<dynamic> get memories => _memories;
  bool get isLoading => _isLoading;

  Future<void> loadMemories() async {
    _isLoading = true;
    notifyListeners();

    try {
      _memories = await _apiService.getMemories();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> deleteMemory(String id) async {
    final success = await _apiService.deleteMemory(id);
    if (success) {
      _memories.removeWhere((m) => m['_id'] == id);
      notifyListeners();
    }
    return success;
  }
}
