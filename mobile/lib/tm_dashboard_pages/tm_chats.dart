import 'dart:async';
import 'dart:convert';
import 'package:remoco_app/services/api_constants.dart';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:firebase_database/firebase_database.dart';
import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:file_picker/file_picker.dart';
import 'package:remoco_app/services/api_http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'package:intl/intl.dart';

class TmChatsPage extends StatefulWidget {
  final int userId;
  final String userName;

  const TmChatsPage({super.key, required this.userId, required this.userName});

  @override
  State<TmChatsPage> createState() => _TmChatsPageState();
}

class _TmChatsPageState extends State<TmChatsPage> {
  final String agoraAppId = ''; // supplied by the API at call time

  List<Map<String, dynamic>> chats = [];
  String? currentRoom;
  int? currentChatId;
  String? currentChatTitle;
  DatabaseReference? messagesRef;
  StreamSubscription<DatabaseEvent>? messagesSubscription;
  List<Map<String, dynamic>> messages = [];
  List<Map<String, dynamic>> sharedFiles = [];

  final messageController = TextEditingController();
  late RtcEngine agoraEngine;
  bool inCall = false;

  @override
  void initState() {
    super.initState();
    fetchChats();
  }

  @override
  void dispose() {
    messageController.dispose();
    messagesSubscription?.cancel();
    super.dispose();
  }

  Future<void> fetchChats() async {
    final url = Uri.parse('http://$apiHost/remoco_app/api/get_tm_chats.php?tm_id=${widget.userId}');
    final response = await http.get(url);
    if (response.statusCode == 200) {
      try {
        final data = json.decode(response.body);
        print("DEBUG: fetchChats response: $data");
        if (data['status'] == 'success') {
          setState(() {
            chats = List<Map<String, dynamic>>.from(data['chats']);
          });
        } else {
          showError("API error: ${data['message']}");
        }
      } catch (e) {
        showError("Invalid JSON: ${e.toString()}");
      }
    } else {
      showError("Failed to fetch chats: ${response.statusCode}");
    }
  }

  void selectChat(Map<String, dynamic> chat) {
    print("DEBUG: Selected chat: $chat");
    setState(() {
      currentRoom = (chat['firebase_room_id'] ?? '').toString().trim();
      currentChatId = chat['chat_id'];
      currentChatTitle = chat['chat_title'];
      messages.clear();
      sharedFiles.clear();
    });
    print("DEBUG: currentRoom set to [$currentRoom]");
    setupMessagesListener();
    fetchFiles();
  }

  void setupMessagesListener() {
    messagesSubscription?.cancel();
    messagesRef = FirebaseDatabase.instance.ref('chats/$currentRoom/messages');
    print("DEBUG: Listening to Firebase path chats/$currentRoom/messages");

    messagesSubscription = messagesRef!
        .orderByChild('timestamp')
        .onChildAdded
        .listen((event) {
      final data = event.snapshot.value;
      if (data != null) {
        final msg = Map<String, dynamic>.from(data as Map);
        print("DEBUG: onChildAdded: ${event.snapshot.key} -> $msg");
        setState(() {
          messages.add({
            'text': msg['text'],
            'sender_id': msg['sender_id'],
            'sender_name': msg['sender_name'],
            'timestamp': msg['timestamp'],
            'type': msg['type'],
          });
        });
      } else {
        print("DEBUG: onChildAdded received null data.");
      }
    }, onError: (error) {
      print("ERROR: Firebase onChildAdded error: $error");
    });
  }

  Future<void> sendMessage() async {
    final text = messageController.text.trim();
    if (text.isEmpty || messagesRef == null) return;

    final newMsg = {
      'text': text,
      'sender_id': widget.userId,
      'sender_name': widget.userName,
      'timestamp': ServerValue.timestamp,
      'type': 'text'
    };

    try {
      await messagesRef!.push().set(newMsg);
      messageController.clear();
    } catch (e) {
      showError("Failed to send message: $e");
    }
  }

  Future<void> pickAndUploadFile() async {
    if (currentChatId == null) return;

    final result = await FilePicker.platform.pickFiles();
    if (result == null || result.files.isEmpty) return;

    final file = File(result.files.first.path!);
    final request = http.authMultipartRequest(
      'POST',
      Uri.parse('http://$apiHost/remoco_app/api/file_upload.php'),
    );
    request.fields['chat_id'] = currentChatId.toString();
    request.fields['uploaded_by'] = widget.userId.toString();
    request.files.add(await http.MultipartFile.fromPath('file', file.path));

    final response = await request.send();
    if (response.statusCode == 200) {
      final respStr = await response.stream.bytesToString();
      try {
        final data = json.decode(respStr);
        print("DEBUG: File upload response: $data");
        if (data['status'] == 'success') {
          fetchFiles();
        } else {
          showError("Upload failed: ${data['message']}");
        }
      } catch (e) {
        showError("Invalid JSON from upload: ${e.toString()}");
      }
    } else {
      showError("Upload HTTP error: ${response.statusCode}");
    }
  }

  Future<void> fetchFiles() async {
    if (currentChatId == null) return;

    final response = await http.get(Uri.parse(
        'http://$apiHost/remoco_app/api/get_files.php?chat_id=$currentChatId'));
    if (response.statusCode == 200) {
      try {
        final data = json.decode(response.body);
        print("DEBUG: fetchFiles response: $data");
        if (data['status'] == 'success' && data['files'] != null) {
          List<Map<String, dynamic>> files = List<Map<String, dynamic>>.from(data['files'] ?? []);

          // 🔥 New: Fetch uploader names for each file
          for (var file in files) {
            final uploaderId = file['uploaded_by'];
            if (uploaderId != null) {
              final nameResponse = await http.get(
                Uri.parse('http://$apiHost/remoco_app/api/get_employee_name.php?employee_id=$uploaderId'),
              );
              if (nameResponse.statusCode == 200) {
                final nameData = json.decode(nameResponse.body);
                if (nameData['status'] == 'success') {
                  file['uploader_name'] = nameData['employee_name'];
                } else {
                  file['uploader_name'] = 'Unknown';
                }
              } else {
                file['uploader_name'] = 'Unknown';
              }
            } else {
              file['uploader_name'] = 'Unknown';
            }
          }

          setState(() {
            sharedFiles = files;
          });
        } else {
          setState(() => sharedFiles = []);
        }
      } catch (e) {
        showError("Failed to parse files: ${e.toString()}");
      }
    } else {
      showError("Fetch files error: ${response.statusCode}");
    }
  }

  Future<void> startCall() async {
    if (currentChatId == null) return;

    final response = await http.post(
      Uri.parse('http://$apiHost/remoco_app/api/start_video_call.php'),
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: {
        'chat_id': currentChatId.toString(),
        'employee_id': widget.userId.toString(),  // ✅ Send user ID explicitly
      },
    );

    if (response.statusCode == 200) {
      try {
        final data = json.decode(response.body);
        if (data['status'] == 'success') {
          await initializeAgora(data['channel'], data['appId']);
        } else {
          showError("Video call error: ${data['message']}");
        }
      } catch (e) {
        showError("Invalid video call response: ${e.toString()}");
      }
    } else {
      showError("Video call request failed: ${response.statusCode}");
    }
  }


  Future<void> initializeAgora(String channelName, String? appId) async {
    agoraEngine = createAgoraRtcEngine();
    await agoraEngine.initialize(RtcEngineContext(appId: (appId != null && appId.isNotEmpty) ? appId : agoraAppId));
    await agoraEngine.joinChannel(
      token: '',
      channelId: channelName,
      uid: widget.userId,
      options: const ChannelMediaOptions(),
    );
    setState(() {
      inCall = true;
    });
  }

  Widget buildMessage(Map<String, dynamic> msg, {Key? key}) {
    final isSelf = msg['sender_id'] == widget.userId;
    final timestamp = msg['timestamp'];
    final timeStr = timestamp != null
        ? DateFormat('HH:mm').format(
        DateTime.fromMillisecondsSinceEpoch(timestamp).toLocal())
        : '??:??';

    return Align(
      key: key,
      alignment: isSelf ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: const BoxConstraints(maxWidth: 280),
        margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelf ? Colors.purple.shade600 : Colors.grey.shade300,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isSelf ? 16 : 0),
            bottomRight: Radius.circular(isSelf ? 0 : 16),
          ),
        ),
        child: Column(
          crossAxisAlignment: isSelf ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            // Show sender's name above every message
            Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Text(
                msg['sender_name'] ?? 'Unknown',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: isSelf ? Colors.white70 : Colors.black87,
                  fontSize: 12,
                ),
              ),
            ),
            Text(
              msg['text'] ?? '',
              style: TextStyle(color: isSelf ? Colors.white : Colors.black),
            ),
            const SizedBox(height: 4),
            Text(
              timeStr,
              style: const TextStyle(fontSize: 10, color: Colors.black54),
            ),
          ],
        ),
      ),
    );
  }

  void showError(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  void showSharedFilesPopup() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => Container(
        height: MediaQuery.of(context).size.height * 0.6,
        padding: const EdgeInsets.all(16),
        child: sharedFiles.isEmpty
            ? const Center(child: Text("No shared files"))
            : ListView.builder(
          itemCount: sharedFiles.length,
          itemBuilder: (_, idx) {
            final file = sharedFiles[idx];
            final filePath = file['file_path'] ?? '';
            return ListTile(
              title: Text(file['file_name'] ?? 'Unnamed File'),
              subtitle: Text('Uploaded by ${file['uploader_name'] ?? 'Unknown'}'),
              onTap: filePath.isNotEmpty
                  ? () => launchUrl(Uri.parse(filePath))
                  : () => showError("File path is invalid."),
            );
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        title: Text(currentChatTitle ?? 'Team Member Chats'),
        leading: currentRoom != null
            ? IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            setState(() {
              currentRoom = null;
              currentChatId = null;
              currentChatTitle = null;
              messages.clear();
              sharedFiles.clear();
            });
          },
        )
            : null,
        actions: [
          if (currentChatId != null && !inCall)
            IconButton(
              icon: const Icon(Icons.video_call),
              onPressed: startCall,
            ),
        ],
      ),
      body: currentRoom == null
          ? chats.isEmpty
          ? const Center(child: Text("No chats found."))
          : ListView.builder(
        itemCount: chats.length,
        itemBuilder: (_, idx) {
          final chat = chats[idx];
          return ListTile(
            title: Text(chat['chat_title'] ?? 'Unnamed'),
            onTap: () => selectChat(chat),
          );
        },
      )
          : Column(
        children: [
          Expanded(
            child: messages.isEmpty
                ? const Center(child: Text("No messages yet."))
                : ListView.builder(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: messages.length,
              itemBuilder: (_, idx) =>
                  buildMessage(messages[idx], key: ValueKey(messages[idx]['timestamp'])),
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: messageController,
                    decoration: const InputDecoration(
                      hintText: 'Type message...',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.send, color: Colors.purple),
                  onPressed: sendMessage,
                ),
                IconButton(
                  icon: const Icon(Icons.attach_file, color: Colors.purple),
                  onPressed: pickAndUploadFile,
                ),
              ],
            ),
          ),
          ElevatedButton.icon(
            onPressed: showSharedFilesPopup,
            icon: const Icon(Icons.folder),
            label: const Text("Shared Files"),
          ),
        ],
      ),
    );
  }
}
