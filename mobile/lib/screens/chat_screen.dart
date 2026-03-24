import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:speech_to_text/speech_to_text.dart';
import 'package:speech_to_text/speech_recognition_result.dart';
import 'package:permission_handler/permission_handler.dart';
import '../providers/chat_provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final TextEditingController _controller = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final AudioPlayer _audioPlayer = AudioPlayer();
  final SpeechToText _speechToText = SpeechToText();
  bool _speechEnabled = false;
  bool _isListening = false;
  int? _speakingIndex;
  bool _isSpeaking = false;

  @override
  void initState() {
    super.initState();
    _initSpeech();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ChatProvider>().loadConversations();
    });
  }

  /// This has to happen only once per app
  void _initSpeech() async {
    _speechEnabled = await _speechToText.initialize();
    
    // Configure AudioPlayer for Android
    await _audioPlayer.setAudioContext(AudioContext(
      android: AudioContextAndroid(
        usageType: AndroidUsageType.assistanceSonification,
        audioFocus: AndroidAudioFocus.gain,
      ),
      iOS: AudioContextIOS(
        category: AVAudioSessionCategory.playback,
      ),
    ));

    _audioPlayer.onLog.listen((log) {
      print('DEBUG: AudioPlayer Log: $log');
    });

    setState(() {});
  }

  void _startListening() async {
    var status = await Permission.microphone.status;
    if (status.isDenied) {
      await Permission.microphone.request();
    }
    
    await _speechToText.listen(
      onResult: _onSpeechResult,
      listenMode: ListenMode.dictation,
    );
    setState(() {
      _isListening = true;
    });
  }

  void _stopListening() async {
    await _speechToText.stop();
    setState(() {
      _isListening = false;
    });
  }

  void _onSpeechResult(SpeechRecognitionResult result) {
    setState(() {
      _controller.text = result.recognizedWords;
    });
  }

  @override
  void dispose() {
    _audioPlayer.dispose();
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final chatProvider = context.watch<ChatProvider>();
    final authProvider = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Image.asset('assets/logo.png', height: 32),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Samuel', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
                Text('Your faithful brother', style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.purple[800], fontStyle: FontStyle.italic)),
              ],
            ),
          ],
        ),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert),
            onSelected: (value) async {
              if (value == 'keep_online') {
                final url = Uri.parse('https://ko-fi.com/Y8Y21W7RKD');
                if (await canLaunchUrl(url)) {
                  await launchUrl(url, mode: LaunchMode.externalApplication);
                }
              } else if (value == 'login') {
                Navigator.pushNamed(context, '/auth');
              } else if (value == 'logout') {
                authProvider.logout();
              }
            },
            itemBuilder: (BuildContext context) => [
              const PopupMenuItem<String>(
                value: 'keep_online',
                child: ListTile(
                  leading: Icon(Icons.favorite, color: Colors.pink),
                  title: Text('Keep Samuel Online'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              const PopupMenuDivider(),
              if (!authProvider.isAuthenticated)
                const PopupMenuItem<String>(
                  value: 'login',
                  child: ListTile(
                    leading: Icon(Icons.login),
                    title: Text('Login'),
                    contentPadding: EdgeInsets.zero,
                  ),
                )
              else
                const PopupMenuItem<String>(
                  value: 'logout',
                  child: ListTile(
                    leading: Icon(Icons.logout),
                    title: Text('Logout'),
                    contentPadding: EdgeInsets.zero,
                  ),
                ),
            ],
          ),
        ],
        backgroundColor: Colors.purple[50],
        elevation: 0,
      ),
      drawer: _buildDrawer(chatProvider),
      body: Container(
        decoration: const BoxDecoration(
          color: Color(0xFFFAFAF9),
        ),
        child: Column(
          children: [
            _buildSettingsBar(chatProvider),
            Expanded(
              child: chatProvider.messages.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.volunteer_activism, size: 64, color: Colors.grey[300]),
                          const SizedBox(height: 16),
                          Text('Peace be with you.', 
                            style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontStyle: FontStyle.italic, color: Colors.grey[600])),
                        ],
                      ),
                    )
                  : ListView.builder(
                      controller: _scrollController,
                      padding: const EdgeInsets.all(16),
                      itemCount: chatProvider.messages.length + (chatProvider.isTyping ? 1 : 0),
                      itemBuilder: (context, index) {
                        if (index == chatProvider.messages.length) {
                          return _buildTypingIndicator();
                        }
                        final message = chatProvider.messages[index];
                        return _buildMessageBubble(message, index);
                      },
                    ),
            ),
            _buildInputArea(chatProvider),
          ],
        ),
      ),
    );
  }

  Widget _buildMessageBubble(dynamic message, int index) {
    bool isUser = message.role == 'user';
    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Column(
        crossAxisAlignment: isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.symmetric(vertical: 4),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.8),
            decoration: BoxDecoration(
              color: isUser ? const Color(0xFF7E22CE) : Colors.white,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(20),
                topRight: const Radius.circular(20),
                bottomLeft: isUser ? const Radius.circular(20) : Radius.zero,
                bottomRight: isUser ? Radius.zero : const Radius.circular(20),
              ),
              boxShadow: [
                BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 4, offset: const Offset(0, 2)),
              ],
            ),
            child: isUser 
              ? Text(message.content, style: const TextStyle(color: Colors.white, fontSize: 16))
              : MarkdownBody(
                  data: message.content,
                  styleSheet: MarkdownStyleSheet(
                    p: TextStyle(color: Colors.grey[800], fontSize: 16),
                    listBullet: TextStyle(color: Colors.purple[700], fontWeight: FontWeight.bold),
                    listIndent: 24.0,
                    blockSpacing: 12.0,
                    strong: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
          ),
          if (!isUser && message.citations != null && message.citations!.isNotEmpty)
            _buildCitations(message.citations!),
          if (!isUser)
            Padding(
              padding: const EdgeInsets.only(left: 4, bottom: 8),
              child: Row(
                children: [
                  IconButton(
                    iconSize: 20,
                    constraints: const BoxConstraints(),
                    padding: const EdgeInsets.all(4),
                    icon: Icon(
                      (_isSpeaking && _speakingIndex == index) ? Icons.stop_circle : Icons.volume_up,
                      color: Colors.purple[300],
                    ),
                    onPressed: () => _playTts(message.content, index),
                  ),
                  if (_isSpeaking && _speakingIndex == index)
                    const SizedBox(
                      width: 12,
                      height: 12,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.purple),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildCitations(List<dynamic> citations) {
    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 4),
      padding: const EdgeInsets.all(12),
      constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.8),
      decoration: BoxDecoration(
        color: Colors.purple[50]?.withOpacity(0.5),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.purple[100]!),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.menu_book, size: 14, color: Colors.purple[700]),
              const SizedBox(width: 8),
              Text('Scripture References', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.purple[900])),
            ],
          ),
          const SizedBox(height: 8),
          ...citations.map((cite) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${cite['reference']} (${cite['version']})',
                  style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.black87),
                ),
                const SizedBox(height: 2),
                Text(
                  cite['text'],
                  style: TextStyle(fontSize: 12, color: Colors.grey[700], fontStyle: FontStyle.italic),
                ),
              ],
            ),
          )).toList(),
        ],
      ),
    );
  }

  Future<void> _playTts(String text, int index) async {
    if (_isSpeaking && _speakingIndex == index) {
      await _audioPlayer.stop();
      setState(() {
        _isSpeaking = false;
        _speakingIndex = null;
      });
      return;
    }

    await _audioPlayer.stop();
    setState(() {
      _isSpeaking = true;
      _speakingIndex = index;
    });

    try {
      final apiService = Provider.of<ApiService>(context, listen: false);
      print('DEBUG: Requesting TTS URL for text: ${text.substring(0, text.length > 20 ? 20 : text.length)}...');
      final url = await apiService.getTtsUrl(text);
      if (url != null) {
        print('DEBUG: Received TTS URL: $url');
        await _audioPlayer.play(UrlSource(url));
        print('DEBUG: AudioPlayer called play');
        _audioPlayer.onPlayerComplete.listen((event) {
          print('DEBUG: AudioPlayer completed');
          if (mounted) {
            setState(() {
              _isSpeaking = false;
              _speakingIndex = null;
            });
          }
        });
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Failed to generate speech')),
          );
          setState(() {
            _isSpeaking = false;
            _speakingIndex = null;
          });
        }
      }
    } catch (e, stack) {
      print('DEBUG: Critical Error in _playTts: $e');
      print('DEBUG: Stack: $stack');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Heart of Harmony: Samuel encountered trouble speaking ($e)')),
        );
        setState(() {
          _isSpeaking = false;
          _speakingIndex = null;
        });
      }
    }
  }

  Widget _buildTypingIndicator() {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.purple)),
            const SizedBox(width: 8),
            Text('Samuel is searching the scriptures...', style: TextStyle(color: Colors.grey[600], fontStyle: FontStyle.italic)),
          ],
        ),
      ),
    );
  }

  Widget _buildInputArea(ChatProvider provider) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE7E5E4))),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _controller,
              decoration: InputDecoration(
                hintText: 'Ask a biblical question...',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(30), borderSide: BorderSide.none),
                filled: true,
                fillColor: const Color(0xFFF5F5F4),
                contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                suffixIcon: _speechEnabled 
                  ? IconButton(
                      icon: Icon(_isListening ? Icons.mic : Icons.mic_none),
                      color: _isListening ? Colors.red : Colors.purple,
                      onPressed: _isListening ? _stopListening : _startListening,
                    )
                  : null,
              ),
              onSubmitted: (val) => _send(provider),
            ),
          ),
          const SizedBox(width: 8),
          CircleAvatar(
            backgroundColor: const Color(0xFF7E22CE),
            child: IconButton(
              icon: const Icon(Icons.send, color: Colors.white),
              onPressed: () => _send(provider),
            ),
          ),
        ],
      ),
    );
  }

  void _send(ChatProvider provider) {
    if (_controller.text.trim().isEmpty) return;
    if (_isListening) _stopListening();
    provider.sendMessage(_controller.text);
    _controller.clear();
    _scrollToBottom();
  }

  Widget _buildSettingsBar(ChatProvider provider) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: Colors.white,
      child: Row(
        children: [
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey[300]!),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: provider.selectedMode,
                  isExpanded: true,
                  icon: const Icon(Icons.arrow_drop_down, size: 20),
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black87),
                  onChanged: (String? newValue) {
                    if (newValue != null) provider.selectedMode = newValue;
                  },
                  items: [
                    {'value': 'fast', 'label': 'Fast', 'icon': Icons.bolt},
                    {'value': 'deep', 'label': 'Deep', 'icon': Icons.auto_awesome},
                    {'value': 'research', 'label': 'Research', 'icon': Icons.search},
                  ].map<DropdownMenuItem<String>>((Map<String, dynamic> mode) {
                    return DropdownMenuItem<String>(
                      value: mode['value'] as String,
                      child: Row(
                        children: [
                          Icon(mode['icon'] as IconData, size: 14, color: Colors.purple),
                          const SizedBox(width: 8),
                          Expanded(child: Text(mode['label'] as String, overflow: TextOverflow.ellipsis)),
                        ],
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: Colors.grey[300]!),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: provider.selectedBibleVersion,
                  isExpanded: true,
                  icon: const Icon(Icons.arrow_drop_down, size: 20),
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black87),
                  onChanged: (String? newValue) {
                    if (newValue != null) provider.selectedBibleVersion = newValue;
                  },
                  items: <String>['BSB', 'KJV', 'ASV', 'WEB']
                      .map<DropdownMenuItem<String>>((String value) {
                    return DropdownMenuItem<String>(
                      value: value,
                      child: Row(
                        children: [
                          const Icon(Icons.menu_book_outlined, size: 14, color: Colors.purple),
                          const SizedBox(width: 8),
                          Expanded(child: Text(value, overflow: TextOverflow.ellipsis)),
                        ],
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDrawer(ChatProvider provider) {
    return Drawer(
      child: Column(
        children: [
          DrawerHeader(
            decoration: BoxDecoration(color: Colors.purple[50]),
            child: const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.history, size: 48, color: Colors.purple),
                  SizedBox(height: 8),
                  Text('Conversations', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                ],
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.add_circle_outline, color: Colors.purple),
            title: const Text('New Conversation', style: TextStyle(fontWeight: FontWeight.bold)),
            onTap: () {
              provider.startNewChat();
              Navigator.pop(context);
            },
          ),
          const Divider(),
          Expanded(
            child: provider.conversations.isEmpty
                ? const Center(child: Text('No previous conversations', style: TextStyle(color: Colors.grey)))
                : ListView.builder(
                    itemCount: provider.conversations.length,
                    itemBuilder: (context, index) {
                      final conv = provider.conversations[index];
                      return ListTile(
                        title: Text(conv['title'] ?? 'Chat', maxLines: 1, overflow: TextOverflow.ellipsis),
                        subtitle: Text(conv['updated_at'] != null ? DateTime.parse(conv['updated_at']).toLocal().toString().split(' ')[0] : 'Today'),
                        selected: provider.activeConversationId == conv['id'].toString(),
                        onTap: () {
                          provider.selectConversation(conv['id'].toString());
                          Navigator.pop(context);
                        },
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
