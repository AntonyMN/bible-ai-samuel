import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/message.dart';
import '../models/user.dart';

class ApiService {
  static const String baseUrl = 'https://api.chatwithsamuel.org';
  
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

  Future<String?> login(String email, String password) async {
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

      final data = jsonDecode(response.body);
      if (response.statusCode == 200) {
        _token = data['token'];
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _token!);
        return null; // Success
      }
      return data['message'] ?? 'Login failed';
    } catch (e) {
      return 'Connection error: $e';
    }
  }

  Future<String?> register(String name, String email, String password, String passwordConfirmation) async {
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

      final data = jsonDecode(response.body);
      if (response.statusCode == 200) {
        _token = data['token'];
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _token!);
        return null; // Success
      }
      return data['message'] ?? 'Registration failed';
    } catch (e) {
      return 'Connection error: $e';
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
      final body = jsonEncode({
        'message': message,
        'conversation_id': conversationId,
        'history': history?.map((m) => m.toJson()).toList(),
      });
      print('DEBUG: Sending message to ${Uri.parse('$baseUrl/chat/send')}');
      print('DEBUG: Body: $body');
      
      final response = await http.post(
        Uri.parse('$baseUrl/chat/send'),
        headers: _headers,
        body: body,
      );

      print('DEBUG: Response Status: ${response.statusCode}');
      print('DEBUG: Response Body: ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      }
      return null;
    } catch (e) {
      print('DEBUG: Error sending message: $e');
      return null;
    }
  }

  Future<String?> getTtsUrl(String text) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/tts'),
        headers: _headers,
        body: jsonEncode({'text': text}),
      );
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        return data['url'];
      }
    } catch (e) {
      print('DEBUG: TTS Error: $e');
    }
    return null;
  }

  bool get isAuthenticated => _token != null;
}
