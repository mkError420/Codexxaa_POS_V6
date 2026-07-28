import React, { useState, useEffect, useRef } from 'react';
import API_BASE_URL from '../config';

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);
  const [sessionId, setSessionId] = useState(null);
  const [messages, setMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [visitorInfo, setVisitorInfo] = useState({
    name: '',
    phone: ''
  });
  const [showVisitorForm, setShowVisitorForm] = useState(true);
  const [loading, setLoading] = useState(false);
  const [unreadCount, setUnreadCount] = useState(0);
  const [error, setError] = useState('');
  const messagesEndRef = useRef(null);
  const chatWindowRef = useRef(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isOpen]);

  // Close chat when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (chatWindowRef.current && !chatWindowRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  useEffect(() => {
    // Check for existing session in localStorage
    const savedSessionId = localStorage.getItem('chat_session_id');
    if (savedSessionId) {
      setSessionId(savedSessionId);
      setShowVisitorForm(false);
      fetchMessages(savedSessionId);
    }
  }, []);

  const fetchMessages = async (sid) => {
    try {
      const response = await fetch(`${API_BASE_URL}/chat/sessions/${sid}/messages`);
      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setMessages(data.messages);
          // Count unread admin messages
          const unread = data.messages.filter(
            m => m.sender_type === 'admin' && !m.is_read
          ).length;
          setUnreadCount(unread);
        }
      }
    } catch (err) {
      console.error('Failed to fetch messages:', err);
    }
  };

  const createSession = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const response = await fetch(`${API_BASE_URL}/chat/sessions`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          visitor_name: visitorInfo.name,
          visitor_phone: visitorInfo.phone
        })
      });
      const data = await response.json();
      if (response.ok && data.success) {
        setSessionId(data.session_id);
        localStorage.setItem('chat_session_id', data.session_id);
        setShowVisitorForm(false);
      } else {
        setError(data.error || 'Failed to start chat. Please try again.');
      }
    } catch (err) {
      console.error('Failed to create session:', err);
      setError('Network error. Please check your connection.');
    } finally {
      setLoading(false);
    }
  };

  const sendMessage = async (e) => {
    e.preventDefault();
    if (!newMessage.trim() || !sessionId) return;

    const messageToSend = newMessage;
    setNewMessage('');
    setError('');

    // Optimistically add message to UI
    const tempMessage = {
      id: Date.now(),
      sender_type: 'visitor',
      sender_name: visitorInfo.name || 'Visitor',
      message: messageToSend,
      is_read: false,
      created_at: new Date().toISOString()
    };
    setMessages(prev => [...prev, tempMessage]);

    try {
      const response = await fetch(`${API_BASE_URL}/chat/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          sender_type: 'visitor',
          sender_name: visitorInfo.name || 'Visitor',
          message: messageToSend
        })
      });
      const data = await response.json();
      if (response.ok && data.success) {
        // Refresh messages to get the actual server response
        fetchMessages(sessionId);
      } else {
        // If session is invalid, clear it and show form
        if (data.error && data.error.includes('foreign key')) {
          setSessionId(null);
          localStorage.removeItem('chat_session_id');
          setShowVisitorForm(true);
          setMessages([]);
          setError('Session expired. Please start a new chat.');
        } else {
          setError(data.error || 'Failed to send message');
        }
        setMessages(prev => prev.filter(m => m.id !== tempMessage.id));
        setNewMessage(messageToSend);
      }
    } catch (err) {
      console.error('Failed to send message:', err);
      setError('Network error. Please check your connection.');
      setMessages(prev => prev.filter(m => m.id !== tempMessage.id));
      setNewMessage(messageToSend);
    }
  };

  // Poll for new messages every 5 seconds when chat is open
  useEffect(() => {
    if (!isOpen || !sessionId) return;
    
    const interval = setInterval(() => {
      fetchMessages(sessionId);
    }, 5000);

    return () => clearInterval(interval);
  }, [isOpen, sessionId]);

  return (
    <div className="fixed bottom-10 right-6 z-50">
      {/* Chat Button */}
      {!isOpen && (
        <button
          onClick={() => setIsOpen(true)}
          className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-3 rounded-full shadow-lg transition-all duration-300 hover:scale-105"
        >
          <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <span className="font-semibold">Live Chat</span>
          {unreadCount > 0 && (
            <span className="bg-rose-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
              {unreadCount}
            </span>
          )}
        </button>
      )}

      {/* Chat Window */}
      {isOpen && (
        <div ref={chatWindowRef} className="bg-white rounded-2xl shadow-2xl w-96 max-h-[600px] flex flex-col overflow-hidden">
          {/* Header */}
          <div className="bg-indigo-600 text-white px-5 py-4 flex justify-between items-center">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
              </div>
              <div>
                <h3 className="font-bold">Live Support</h3>
                <p className="text-xs text-indigo-200">We typically reply in minutes</p>
              </div>
            </div>
            <button
              onClick={() => setIsOpen(false)}
              className="text-white/80 hover:text-white transition-colors"
            >
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          {/* Content */}
          <div className="flex-1 flex flex-col overflow-hidden">
            {showVisitorForm ? (
              /* Visitor Form */
              <div className="p-5">
                {error && (
                  <div className="mb-4 bg-rose-50 border border-rose-200 text-rose-600 px-4 py-3 rounded-lg text-sm">
                    {error}
                  </div>
                )}
                <form onSubmit={createSession} className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                    <input
                      type="text"
                      required
                      value={visitorInfo.name}
                      onChange={(e) => setVisitorInfo({...visitorInfo, name: e.target.value})}
                      className="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Your name"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                    <input
                      type="tel"
                      required
                      value={visitorInfo.phone}
                      onChange={(e) => setVisitorInfo({...visitorInfo, phone: e.target.value})}
                      className="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="Your phone number"
                    />
                  </div>
                  <button
                    type="submit"
                    disabled={loading}
                    className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {loading ? 'Starting chat...' : 'Start Chat'}
                  </button>
                </form>
              </div>
            ) : (
              <>
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
                      <p className="text-slate-500 text-sm">Start a conversation with our support team</p>
                    </div>
                  ) : (
                    messages.map((msg) => (
                      <div
                        key={msg.id}
                        className={`flex ${msg.sender_type === 'visitor' ? 'justify-end' : 'justify-start'}`}
                      >
                        <div
                          className={`max-w-[80%] rounded-2xl px-4 py-2.5 ${
                            msg.sender_type === 'visitor'
                              ? 'bg-indigo-600 text-white'
                              : 'bg-white text-slate-800 border border-slate-200'
                          }`}
                        >
                          <p className="text-sm">{msg.message}</p>
                          <p className={`text-xs mt-1 ${msg.sender_type === 'visitor' ? 'text-indigo-200' : 'text-slate-400'}`}>
                            {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </p>
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
                    />
                    <button
                      type="submit"
                      disabled={!newMessage.trim()}
                      className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                      </svg>
                    </button>
                  </div>
                </form>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
