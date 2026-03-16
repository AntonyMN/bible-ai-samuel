import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/message.dart';
import '../models/user.dart';

class ApiService {
  static const String baseUrl = 'https://chat.chatwithsamuel.org/api';
  
  String? _token;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('auth_token');
  }

  Map<String, String> get _headers {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };
    if (_token != null) {
      headers['Authorization'] = 'Bearer $_token';
    }
    return headers;
  }

  Future<bool> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/login'),
        headers: _headers,
        body: jsonEncode({
          'email': email,
          'password': password,
          'device_name': 'mobile_app',
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _token = data['token'];
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _token!);
        return true;
      }
      return false;
    } catch (e) {
      return false;
    }
  }

  Future<bool> register(String name, String email, String password, String passwordConfirmation) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/register'),
        headers: _headers,
        body: jsonEncode({
          'name': name,
          'email': email,
          'password': password,
          'password_confirmation': passwordConfirmation,
          'device_name': 'mobile_app',
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _token = data['token'];
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _token!);
        return true;
      }
      return false;
    } catch (e) {
      return false;
    }
  }

  Future<void> logout() async {
    if (_token == null) return;
    try {
      await http.post(Uri.parse('$baseUrl/logout'), headers: _headers);
    } catch (e) {
      // Ignore
    }
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }

  Future<Map<String, dynamic>?> sendMessage(String message, {String? conversationId, List<Message>? history}) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/chat/send'),
        headers: _headers,
        body: jsonEncode({
          'message': message,
          'conversation_id': conversationId,
          'history': history?.map((m) => m.toJson()).toList(),
        }),
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  Future<List<dynamic>> getConversations() async {
    if (_token == null) return [];
    try {
      final response = await http.get(Uri.parse('$baseUrl/conversations'), headers: _headers);
      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      }
    } catch (e) {
      // Ignore
    }
    return [];
  }

  bool get isAuthenticated => _token != null;
}
