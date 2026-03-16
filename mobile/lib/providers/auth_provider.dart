import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../models/user.dart';

class AuthProvider extends ChangeNotifier {
  final ApiService _apiService;
  User? _user;
  bool _isLoading = false;

  AuthProvider(this._apiService);

  User? get user => _user;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _apiService.isAuthenticated;

  Future<String?> login(String email, String password) async {
    _isLoading = true;
    notifyListeners();
    
    final result = await _apiService.login(email, password);
    _isLoading = false;
    notifyListeners();
    return result;
  }

  Future<String?> register(String name, String email, String password, String passwordConfirmation) async {
    _isLoading = true;
    notifyListeners();
    
    final result = await _apiService.register(name, email, password, passwordConfirmation);
    _isLoading = false;
    notifyListeners();
    return result;
  }

  Future<void> logout() async {
    await _apiService.logout();
    _user = null;
    notifyListeners();
  }
}
