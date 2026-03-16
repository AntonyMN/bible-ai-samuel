class Message {
  final String role;
  final String content;
  final List<dynamic>? citations;
  final bool failed;

  Message({
    required this.role,
    required this.content,
    this.citations,
    this.failed = false,
  });

  factory Message.fromJson(Map<String, dynamic> json) {
    return Message(
      role: json['role'] as String,
      content: json['content'] as String,
      citations: json['citations'] as List<dynamic>?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'role': role,
      'content': content,
    };
  }
}
