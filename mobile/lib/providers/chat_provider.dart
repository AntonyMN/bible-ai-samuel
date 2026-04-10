import 'package:flutter/material.dart';
import '../models/message.dart';
import '../services/api_service.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'dart:convert';

class ChatProvider extends ChangeNotifier {
  final ApiService _apiService;
  List<Message> _messages = [];
  String? _activeConversationId;
  bool _isTyping = false;
  
  String _selectedMode = 'fast';
  String _selectedBibleVersion = 'BSB';
  String _typingStatus = 'Samuel is searching the scriptures for you...';
  List<dynamic> _conversations = [];
  int _remainingImages = 0;
  final PusherChannelsFlutter _pusher = PusherChannelsFlutter.getInstance();

  ChatProvider(this._apiService);

  List<Message> get messages => _messages;
  String? get activeConversationId => _activeConversationId;
  bool get isTyping => _isTyping;
  String get selectedMode => _selectedMode;
  String get selectedBibleVersion => _selectedBibleVersion;
  String get typingStatus => _typingStatus;
  List<dynamic> get conversations => _conversations;
  int get remainingImages => _remainingImages;

  set selectedMode(String value) {
    _selectedMode = value;
    notifyListeners();
  }

  set selectedBibleVersion(String value) {
    _selectedBibleVersion = value;
    _isTyping = false;
    _typingStatus = 'Samuel is searching the scriptures for you...';
    notifyListeners();
  }

  void connectToPusher(String userId) async {
    if (_activeConversationId != null && _pusher.isActive) return;
    _initPusher(userId);
  }

  void _initPusher(String userId) async {
    try {
      await _pusher.init(
        apiKey: "bibleai_key",
        cluster: "mt1",
        onEvent: (PusherEvent event) {
          if (event.eventName == '.App\\Events\\MessageStatusUpdated') {
            final data = jsonDecode(event.data);
            _typingStatus = data['status'];
            notifyListeners();
          }
        },
      );
      await _pusher.subscribe(channelName: "private-user.$userId");
      await _pusher.connect();
    } catch (e) {
      print("Pusher Error: $e");
    }
  }

  Future<void> loadConversations() async {
    _conversations = await _apiService.getConversations();
    notifyListeners();
  }

  Future<void> loadPreferences() async {
    final prefs = await _apiService.getPreferences();
    if (prefs != null) {
      if (prefs['bible_version'] != null) _selectedBibleVersion = prefs['bible_version'];
      if (prefs['preferred_mode'] != null) _selectedMode = prefs['preferred_mode'];
      _remainingImages = prefs['remaining_images'] ?? 0;
      notifyListeners();
    }
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
    _typingStatus = 'Samuel is reflecting on your heart...';
    notifyListeners();

    final result = await _apiService.sendMessage(
      text, 
      conversationId: _activeConversationId,
      history: _messages.length > 10 ? _messages.sublist(_messages.length - 11, _messages.length - 1) : _messages.sublist(0, _messages.length - 1),
      mode: _selectedMode,
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
      if (result['remaining_images'] != null) {
        _remainingImages = result['remaining_images'];
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
