import React, { useState, useEffect, useRef } from 'react';
import API_BASE_URL from '../config';

export default function AdminChat({ onUnreadCountChange }) {
  const [sessions, setSessions] = useState([]);
  const [selectedSession, setSelectedSession] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const [notification, setNotification] = useState(null);
  const messagesEndRef = useRef(null);

  const userObj = JSON.parse(localStorage.getItem('user') || '{}');
  const isAdmin = userObj.role === 'super_admin';

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const showNotification = (message, type = 'success') => {
    setNotification({ message, type });
    setTimeout(() => setNotification(null), 3000);
  };

  useEffect(() => {
    if (!isAdmin) return;
    fetchSessions();
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  useEffect(() => {
    if (!selectedSession) return;
    fetchMessages(selectedSession.id);
    
    // Poll for new messages every 5 seconds
    const interval = setInterval(() => {
      fetchMessages(selectedSession.id);
      fetchSessions(); // Refresh sessions to update unread counts
    }, 5000);

    return () => clearInterval(interval);
  }, [selectedSession]);

  const fetchSessions = async () => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_BASE_URL}/admin/chat/sessions`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      const data = await response.json();
      if (response.ok && data.success) {
        setSessions(data.sessions);
        setError('');
        // Update unread count in parent
        if (onUnreadCountChange) {
          const totalUnread = data.sessions.reduce((sum, session) => sum + (session.unread_count || 0), 0);
          onUnreadCountChange(totalUnread);
        }
      } else {
        setError(data.error || 'Failed to fetch chat sessions');
      }
    } catch (err) {
      console.error('Failed to fetch sessions:', err);
      setError('Network error. Please check your connection.');
    } finally {
      setLoading(false);
    }
  };

  const fetchMessages = async (sessionId) => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_BASE_URL}/chat/sessions/${sessionId}/messages`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      const data = await response.json();
      if (response.ok && data.success) {
        setMessages(data.messages);
        setError('');
      } else {
        setError(data.error || 'Failed to fetch messages');
      }
    } catch (err) {
      console.error('Failed to fetch messages:', err);
      setError('Network error. Please check your connection.');
    }
  };

  const sendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim() || !selectedSession) return;

    const messageToSend = newMessage;
    setNewMessage('');
    setSending(true);
    setError('');

    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_BASE_URL}/admin/chat/messages`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          session_id: selectedSession.id,
          sender_type: 'admin',
          sender_name: userObj.name || 'Admin',
          message: messageToSend
        })
      });
      const data = await response.json();
      if (response.ok && data.success) {
        fetchMessages(selectedSession.id);
        showNotification('Message sent successfully');
      } else {
        setError(data.error || 'Failed to send message');
        setNewMessage(messageToSend);
      }
    } catch (err) {
      console.error('Failed to send message:', err);
      setError('Network error. Please check your connection.');
      setNewMessage(messageToSend);
    } finally {
      setSending(false);
    }
  };

  const updateSessionStatus = async (sessionId, status) => {
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_BASE_URL}/admin/chat/sessions/${sessionId}/status`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ status })
      });
      if (response.ok) {
        fetchSessions();
      }
    } catch (err) {
      console.error('Failed to update session status:', err);
    }
  };

  const deleteSession = async (sessionId) => {
    if (!confirm('Are you sure you want to delete this chat session?')) return;
    
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`${API_BASE_URL}/admin/chat/sessions/${sessionId}`, {
        method: 'DELETE',
        headers: { Authorization: `Bearer ${token}` }
      });
      if (response.ok) {
        if (selectedSession?.id === sessionId) {
          setSelectedSession(null);
          setMessages([]);
        }
        fetchSessions();
      }
    } catch (err) {
      console.error('Failed to delete session:', err);
    }
  };

  if (!isAdmin) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="text-center">
          <svg className="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <p className="text-slate-500 font-medium">Access Restricted</p>
          <p className="text-slate-400 text-sm mt-1">Only super admins can access the chat system</p>
        </div>
      </div>
    );
  }

  return (
    <>
      {/* Toast Notification */}
      {notification && (
        <div className={`fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-sm font-medium transition-all duration-300 ${
          notification.type === 'success' 
            ? 'bg-emerald-500 text-white' 
            : notification.type === 'error'
            ? 'bg-rose-500 text-white'
            : 'bg-indigo-500 text-white'
        }`}>
          {notification.message}
        </div>
      )}

      <div className="flex h-[calc(100vh-200px)] gap-4">
        {/* Sessions List */}
        <div className="w-80 bg-white border border-slate-200 rounded-2xl flex flex-col overflow-hidden">
          <div className="p-4 border-b border-slate-100">
            <h2 className="text-lg font-bold text-slate-800">Chat Sessions</h2>
            <p className="text-sm text-slate-500">{sessions.length} active conversations</p>
          </div>
        
          <div className="flex-1 overflow-y-auto">
            {error && (
              <div className="p-4 bg-rose-50 border-b border-rose-200 text-rose-600 text-sm">
                {error}
              </div>
            )}
            {loading ? (
              <div className="flex items-center justify-center h-48">
                <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-600" />
              </div>
            ) : sessions.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-48 text-slate-400">
                <svg className="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p className="text-sm">No chat sessions yet</p>
              </div>
            ) : (
              sessions.map((session) => (
                <div
                  key={session.id}
                  onClick={() => setSelectedSession(session)}
                  className={`p-4 border-b border-slate-100 cursor-pointer transition-colors ${
                    selectedSession?.id === session.id ? 'bg-indigo-50 border-l-4 border-l-indigo-600' : 'hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-start justify-between mb-2">
                    <div className="flex-1">
                      <h3 className="font-semibold text-slate-800">{session.visitor_name}</h3>
                      {session.visitor_email && (
                        <p className="text-xs text-slate-500">{session.visitor_email}</p>
                      )}
                    </div>
                    <span className={`text-xs px-2 py-0.5 rounded-full ${
                      session.status === 'active' ? 'bg-emerald-100 text-emerald-700' :
                      session.status === 'closed' ? 'bg-slate-100 text-slate-600' :
                      'bg-amber-100 text-amber-700'
                    }`}>
                      {session.status}
                    </span>
                  </div>
                  <p className="text-sm text-slate-600 truncate">
                    {session.last_message || 'No messages yet'}
                  </p>
                  <div className="flex items-center justify-between mt-2">
                    <span className="text-xs text-slate-400">
                      {session.last_message_time ? new Date(session.last_message_time).toLocaleString() : new Date(session.created_at).toLocaleString()}
                    </span>
                    {session.unread_count > 0 && (
                      <span className="bg-rose-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                        {session.unread_count}
                      </span>
                    )}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Chat Area */}
        <div className="flex-1 bg-white border border-slate-200 rounded-2xl flex flex-col overflow-hidden">
          {selectedSession ? (
            <>
              {/* Chat Header */}
              <div className="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-slate-800">{selectedSession.visitor_name}</h3>
                  <div className="flex items-center gap-2 text-sm text-slate-500">
                    {selectedSession.visitor_email && <span>{selectedSession.visitor_email}</span>}
                    {selectedSession.visitor_phone && <span>• {selectedSession.visitor_phone}</span>}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <select
                    value={selectedSession.status}
                    onChange={(e) => updateSessionStatus(selectedSession.id, e.target.value)}
                    className="text-sm border border-slate-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="active">Active</option>
                    <option value="closed">Closed</option>
                    <option value="archived">Archived</option>
                  </select>
                  <button
                    onClick={() => deleteSession(selectedSession.id)}
                    className="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                    title="Delete session"
                  >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50">
                {error && (
                  <div className="bg-rose-50 border border-rose-200 text-rose-600 px-4 py-3 rounded-lg text-sm">
                    {error}
                  </div>
                )}
                {messages.length === 0 ? (
                  <div className="text-center py-8">
                    <div className="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                      <svg className="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                      </svg>
                    </div>
                    <p className="text-slate-500 text-sm">No messages in this conversation yet</p>
                  </div>
                ) : (
                  messages.map((msg) => (
                    <div
                      key={msg.id}
                      className={`flex ${msg.sender_type === 'admin' ? 'justify-end' : 'justify-start'}`}
                    >
                      <div className={`max-w-[70%]`}>
                        <div
                          className={`rounded-2xl px-4 py-2.5 ${
                            msg.sender_type === 'admin'
                              ? 'bg-indigo-600 text-white'
                              : 'bg-white text-slate-800 border border-slate-200'
                          }`}
                        >
                          <p className="text-sm">{msg.message}</p>
                        </div>
                        <div className="flex items-center gap-2 mt-1">
                          <span className="text-xs text-slate-400">
                            {msg.sender_name}
                          </span>
                          <span className="text-xs text-slate-400">
                            {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </span>
                        </div>
                      </div>
                    </div>
                  ))
                )}
                <div ref={messagesEndRef} />
              </div>

              {/* Input */}
              <form onSubmit={sendMessage} className="p-4 border-t border-slate-200 bg-white">
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={newMessage}
                    onChange={(e) => setNewMessage(e.target.value)}
                    placeholder="Type your message..."
                    className="flex-1 border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    disabled={sending}
                  />
                  <button
                    type="submit"
                    disabled={!newMessage.trim() || sending}
                    className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {sending ? (
                      <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-white" />
                    ) : (
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                      </svg>
                    )}
                  </button>
                </div>
              </form>
            </>
          ) : (
            <div className="flex items-center justify-center h-full">
              <div className="text-center text-slate-400">
                <svg className="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p className="font-medium">Select a chat session</p>
                <p className="text-sm mt-1">Choose a conversation from the list to start chatting</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
}
