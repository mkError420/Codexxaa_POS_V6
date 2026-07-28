<?php

class ChatController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Create a new chat session
    public function createSession($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_sessions (visitor_name, visitor_email, visitor_phone, status)
                VALUES (:visitor_name, :visitor_email, :visitor_phone, 'active')
            ");
            $stmt->execute([
                ':visitor_name' => $data['visitor_name'],
                ':visitor_email' => $data['visitor_email'] ?? null,
                ':visitor_phone' => $data['visitor_phone'] ?? null
            ]);
            
            return [
                'success' => true,
                'session_id' => $this->pdo->lastInsertId(),
                'message' => 'Chat session created successfully'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to create chat session: ' . $e->getMessage()
            ];
        }
    }

    // Get all chat sessions (for admin)
    public function getAllSessions() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT cs.*, 
                       (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.id) as message_count,
                       (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.id AND sender_type = 'visitor' AND is_read = FALSE) as unread_count,
                       (SELECT message FROM chat_messages WHERE session_id = cs.id ORDER BY created_at DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM chat_messages WHERE session_id = cs.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                FROM chat_sessions cs
                ORDER BY cs.updated_at DESC
            ");
            $stmt->execute();
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'sessions' => $sessions
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch chat sessions: ' . $e->getMessage()
            ];
        }
    }

    // Get a specific chat session
    public function getSession($sessionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM chat_sessions WHERE id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                return [
                    'success' => false,
                    'error' => 'Chat session not found'
                ];
            }
            
            return [
                'success' => true,
                'session' => $session
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch chat session: ' . $e->getMessage()
            ];
        }
    }

    // Get messages for a session
    public function getMessages($sessionId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM chat_messages 
                WHERE session_id = :session_id 
                ORDER BY created_at ASC
            ");
            $stmt->execute([':session_id' => $sessionId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Mark visitor messages as read
            $markStmt = $this->pdo->prepare("
                UPDATE chat_messages 
                SET is_read = TRUE 
                WHERE session_id = :session_id AND sender_type = 'visitor'
            ");
            $markStmt->execute([':session_id' => $sessionId]);
            
            return [
                'success' => true,
                'messages' => $messages
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to fetch messages: ' . $e->getMessage()
            ];
        }
    }

    // Send a message
    public function sendMessage($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO chat_messages (session_id, sender_type, sender_name, message, is_read)
                VALUES (:session_id, :sender_type, :sender_name, :message, FALSE)
            ");
            $stmt->execute([
                ':session_id' => $data['session_id'],
                ':sender_type' => $data['sender_type'],
                ':sender_name' => $data['sender_name'],
                ':message' => $data['message']
            ]);
            
            // Update session timestamp
            $updateStmt = $this->pdo->prepare("
                UPDATE chat_sessions 
                SET updated_at = CURRENT_TIMESTAMP 
                WHERE id = :session_id
            ");
            $updateStmt->execute([':session_id' => $data['session_id']]);
            
            return [
                'success' => true,
                'message_id' => $this->pdo->lastInsertId(),
                'message' => 'Message sent successfully'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to send message: ' . $e->getMessage()
            ];
        }
    }

    // Update session status
    public function updateSessionStatus($sessionId, $status) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE chat_sessions 
                SET status = :status, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :session_id
            ");
            $stmt->execute([
                ':status' => $status,
                ':session_id' => $sessionId
            ]);
            
            return [
                'success' => true,
                'message' => 'Session status updated successfully'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to update session status: ' . $e->getMessage()
            ];
        }
    }

    // Delete a session
    public function deleteSession($sessionId) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM chat_sessions WHERE id = :session_id
            ");
            $stmt->execute([':session_id' => $sessionId]);
            
            return [
                'success' => true,
                'message' => 'Session deleted successfully'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to delete session: ' . $e->getMessage()
            ];
        }
    }
}
