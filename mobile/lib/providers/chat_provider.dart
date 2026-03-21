import 'package:flutter/material.dart';
import '../models/message.dart';
import '../services/api_service.dart';

class ChatProvider extends ChangeNotifier {
  final ApiService _apiService;
  List<Message> _messages = [];
  String? _activeConversationId;
  bool _isTyping = false;
  
  String _selectedModel = 'llama3.2:3b';
  String _selectedBibleVersion = 'BSB';
  List<dynamic> _conversations = [];

  ChatProvider(this._apiService);

  List<Message> get messages => _messages;
  String? get activeConversationId => _activeConversationId;
  bool get isTyping => _isTyping;
  String get selectedModel => _selectedModel;
  String get selectedBibleVersion => _selectedBibleVersion;
  List<dynamic> get conversations => _conversations;

  set selectedModel(String value) {
    _selectedModel = value;
    notifyListeners();
  }

  set selectedBibleVersion(String value) {
    _selectedBibleVersion = value;
    notifyListeners();
  }

  Future<void> loadConversations() async {
    _conversations = await _apiService.getConversations();
    notifyListeners();
  }

  Future<void> selectConversation(String id) async {
    _activeConversationId = id;
    _messages = [];
    notifyListeners();
    
    final details = await _apiService.getConversationDetails(id);
    if (details != null && details['messages'] != null) {
      _messages = (details['messages'] as List)
          .map((m) => Message.fromJson(m))
          .toList();
    }
    notifyListeners();
  }

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
      model: _selectedModel,
      bibleVersion: _selectedBibleVersion,
    );

    _isTyping = false;

    if (result != null && result['message'] != null) {
      final aiMsg = Message(
        role: result['message']['role'],
        content: result['message']['content'],
        citations: result['citations'],
      );
      _messages.add(aiMsg);
      if (result['conversation_id'] != null && _activeConversationId == null) {
        _activeConversationId = result['conversation_id'].toString();
        loadConversations(); // Refresh list on new conversation
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
