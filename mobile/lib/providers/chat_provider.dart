import 'package:flutter/material.dart';
import '../models/message.dart';
import '../services/api_service.dart';

class ChatProvider extends ChangeNotifier {
  final ApiService _apiService;
  List<Message> _messages = [];
  String? _activeConversationId;
  bool _isTyping = false;

  ChatProvider(this._apiService);

  List<Message> get messages => _messages;
  String? get activeConversationId => _activeConversationId;
  bool get isTyping => _isTyping;

  Future<void> sendMessage(String text) async {
    if (text.trim().isEmpty) return;

    final userMsg = Message(role: 'user', content: text);
    _messages.add(userMsg);
    _isTyping = true;
    notifyListeners();

    final result = await _apiService.sendMessage(
      text, 
      conversationId: _activeConversationId,
      history: _messages.length > 10 ? _messages.sublist(_messages.length - 11, _messages.length - 1) : _messages.sublist(0, _messages.length - 1),
    );

    _isTyping = false;

    if (result != null && result['message'] != null) {
      final aiMsg = Message.fromJson(result['message']);
      _messages.add(aiMsg);
      if (result['conversation_id'] != null && _activeConversationId == null) {
        _activeConversationId = result['conversation_id'].toString();
      }
    } else {
      // Mark last message as failed
      _messages.last = Message(role: 'user', content: text, failed: true);
    }
    
    notifyListeners();
  }

  void startNewChat() {
    _messages = [];
    _activeConversationId = null;
    notifyListeners();
  }
}
