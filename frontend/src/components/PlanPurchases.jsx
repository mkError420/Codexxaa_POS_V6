import React, { useState, useEffect } from 'react';
import API_BASE_URL from '../config';

export default function PlanPurchases() {
  const [purchases, setPurchases] = useState([]);
  const [shops, setShops] = useState([]);
  const [loading, setLoading] = useState(true);
  const [alert, setAlert] = useState(null);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showShopModal, setShowShopModal] = useState(false);
  const [showImageModal, setShowImageModal] = useState(false);
  const [selectedPurchase, setSelectedPurchase] = useState(null);
  const [selectedImage, setSelectedImage] = useState(null);
  const [editForm, setEditForm] = useState({ status: '', notes: '', shop_id: '' });
  const [shopForm, setShopForm] = useState({
    shop_name: '',
    shop_email: '',
    shop_phone: '',
    shop_address: '',
    admin_name: '',
    admin_email: '',
    admin_password: ''
  });

  const token = () => localStorage.getItem('token');

  const triggerAlert = (type, message) => {
    setAlert({ type, message });
    setTimeout(() => setAlert(null), 4000);
  };

  const fetchPurchases = async () => {
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/plan-purchases`, {
        headers: { Authorization: `Bearer ${token()}` }
      });
      if (response.ok) {
        const data = await response.json();
        setPurchases(data);
      }
    } catch (err) {
      console.error('Failed to fetch plan purchases:', err);
      triggerAlert('error', 'Failed to load plan purchases');
    } finally {
      setLoading(false);
    }
  };

  const fetchShops = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/shops`, {
        headers: { Authorization: `Bearer ${token()}` }
      });
      if (response.ok) {
        const data = await response.json();
        setShops(data);
      }
    } catch (err) {
      console.error('Failed to fetch shops:', err);
    }
  };

  useEffect(() => {
    fetchPurchases();
    fetchShops();
  }, []);

  const getStatusBadge = (status) => {
    const styles = {
      pending: 'bg-amber-50 text-amber-700 border-amber-200',
      approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      rejected: 'bg-rose-50 text-rose-700 border-rose-200'
    };
    return (
      <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border ${styles[status] || styles.pending}`}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </span>
    );
  };

  const handleEdit = (purchase) => {
    setSelectedPurchase(purchase);
    setEditForm({ status: purchase.status, notes: purchase.notes || '', shop_id: purchase.shop_id || '' });
    setShowEditModal(true);
  };

  const handleCreateShop = (purchase) => {
    setSelectedPurchase(purchase);
    setShopForm({
      shop_name: '',
      shop_email: purchase.user_email,
      shop_phone: purchase.user_phone || '',
      shop_address: '',
      admin_name: purchase.user_name,
      admin_email: purchase.user_email,
      admin_password: ''
    });
    setShowShopModal(true);
  };

  const handleImagePreview = (proofPath) => {
    setSelectedImage(getPaymentProofUrl(proofPath));
    setShowImageModal(true);
  };

  const getPaymentProofUrl = (proofPath) => {
    if (!proofPath) return '';
    
    // If it's already a full URL, return as-is
    if (proofPath.startsWith('http://') || proofPath.startsWith('https://')) {
      return proofPath;
    }
    
    // Remove /api from API_BASE_URL to get the base URL
    const baseUrl = API_BASE_URL.replace('/api', '');
    
    // Remove leading slash from proofPath if present to avoid double slashes
    const cleanPath = proofPath.startsWith('/') ? proofPath : `/${proofPath}`;
    
    return `${baseUrl}${cleanPath}`;
  };

  const handleShopSubmit = async (e) => {
    e.preventDefault();
    try {
      const response = await fetch(`${API_BASE_URL}/auth/register-shop`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token()}`
        },
        body: JSON.stringify(shopForm)
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to create shop');
      }

      const result = await response.json();
      
      // Link the purchase to the newly created shop
      await fetch(`${API_BASE_URL}/plan-purchases/${selectedPurchase.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token()}`
        },
        body: JSON.stringify({ 
          status: 'approved',
          shop_id: result.shop_id,
          notes: `Shop created and linked to purchase. Shop ID: ${result.shop_id}`
        })
      });

      triggerAlert('success', 'Shop created successfully and linked to purchase!');
      setShowShopModal(false);
      fetchShops();
      fetchPurchases();
    } catch (err) {
      triggerAlert('error', err.message);
    }
  };

  const handleQuickStatusChange = async (purchaseId, newStatus) => {
    try {
      const response = await fetch(`${API_BASE_URL}/plan-purchases/${purchaseId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token()}`
        },
        body: JSON.stringify({ status: newStatus })
      });

      if (!response.ok) {
        throw new Error('Failed to update status');
      }

      triggerAlert('success', 'Status updated successfully!');
      fetchPurchases();
    } catch (err) {
      triggerAlert('error', err.message);
    }
  };

  const handleUpdate = async (e) => {
    e.preventDefault();
    try {
      const response = await fetch(`${API_BASE_URL}/plan-purchases/${selectedPurchase.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token()}`
        },
        body: JSON.stringify(editForm)
      });

      if (!response.ok) {
        throw new Error('Failed to update purchase');
      }

      triggerAlert('success', 'Plan purchase updated successfully!');
      setShowEditModal(false);
      fetchPurchases();
    } catch (err) {
      triggerAlert('error', err.message);
    }
  };

  const handleDelete = async (purchaseId) => {
    if (!confirm('Are you sure you want to delete this plan purchase?')) return;

    try {
      const response = await fetch(`${API_BASE_URL}/plan-purchases/${purchaseId}`, {
        method: 'DELETE',
        headers: { Authorization: `Bearer ${token()}` }
      });

      if (!response.ok) {
        throw new Error('Failed to delete purchase');
      }

      triggerAlert('success', 'Plan purchase deleted successfully!');
      fetchPurchases();
    } catch (err) {
      triggerAlert('error', err.message);
    }
  };

  const getPaymentMethodBadge = (method) => {
    if (!method) return '—';
    const labels = {
      cash: 'Cash',
      card: 'Card',
      mobile_pay: 'Mobile Pay',
      bank_transfer: 'Bank Transfer',
      other: 'Other'
    };
    // If it's already a descriptive name from payment_methods table, return it
    if (!labels[method] && method.length > 10) {
      return method;
    }
    return labels[method] || method;
  };

  // Calculate stats
  const totalPurchases = purchases.length;
  const approvedPurchases = purchases.filter(p => p.status === 'approved').length;
  const pendingPurchases = purchases.filter(p => p.status === 'pending').length;
  const totalRevenue = purchases
    .filter(p => p.status === 'approved')
    .reduce((sum, p) => sum + (p.plan_price || 0), 0);

  return (
    <div className="space-y-6">
      {alert && (
        <div className={`fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg flex items-center transition-all ${alert.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'}`}>
          <span className="text-sm font-semibold">{alert.message}</span>
        </div>
      )}

      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold text-slate-800">Plan Purchases</h2>
          <p className="text-sm text-slate-500">Manage pricing plan purchases from the home page</p>
        </div>
        <button
          onClick={fetchPurchases}
          className="inline-flex items-center gap-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-semibold px-4 py-2.5 rounded-xl text-sm transition-colors"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Refresh
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-2xl font-bold text-slate-800">{totalPurchases}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Total Purchases</div>
        </div>
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-2xl font-bold text-emerald-600">{approvedPurchases}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Approved</div>
        </div>
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-2xl font-bold text-amber-600">{pendingPurchases}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Pending</div>
        </div>
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-2xl font-bold text-indigo-600">৳{totalRevenue.toFixed(2)}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Total Revenue</div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-100 text-xs font-bold text-slate-400 uppercase tracking-wider bg-slate-50">
                <th className="px-4 py-3">ID</th>
                <th className="px-4 py-3">Plan</th>
                <th className="px-4 py-3">Customer</th>
                <th className="px-4 py-3">Shop</th>
                <th className="px-4 py-3">Amount</th>
                <th className="px-4 py-3">Transaction ID</th>
                <th className="px-4 py-3">Payment</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Proof</th>
                <th className="px-4 py-3">Date</th>
                <th className="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-sm">
              {loading ? (
                <tr>
                  <td colSpan="11" className="p-12 text-center">
                    <div className="flex flex-col items-center gap-3 text-slate-400">
                      <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-600" />
                      <span className="text-sm">Loading purchases…</span>
                    </div>
                  </td>
                </tr>
              ) : purchases.length === 0 ? (
                <tr>
                  <td colSpan="11" className="p-12 text-center">
                    <div className="flex flex-col items-center gap-2 text-slate-400">
                      <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                      </svg>
                      <p className="font-medium">No plan purchases yet</p>
                    </div>
                  </td>
                </tr>
              ) : (
                purchases.map((purchase) => (
                  <tr key={purchase.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-4 py-3.5 font-mono text-xs font-bold text-slate-400">#{purchase.id}</td>
                    <td className="px-4 py-3.5">
                      <div>
                        <p className="font-semibold text-slate-800">{purchase.plan_name || 'Unknown Plan'}</p>
                        <p className="text-xs text-slate-500">{purchase.plan_period || 'month'}</p>
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      <div>
                        <p className="font-medium text-slate-800">{purchase.user_name}</p>
                        <p className="text-xs text-slate-500">{purchase.user_email}</p>
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      {purchase.shop_id ? (
                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">
                          <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                          </svg>
                          Shop #{purchase.shop_id}
                        </span>
                      ) : (
                        <span className="text-slate-400 text-xs">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3.5 font-bold text-indigo-600">৳{parseFloat(purchase.plan_price || 0).toFixed(2)}</td>
                    <td className="px-4 py-3.5">
                      <div>
                        <p className="font-mono text-xs text-slate-600">{purchase.transaction_id || '—'}</p>
                        {(purchase.bank_name || purchase.account_number) && (
                          <p className="text-xs text-slate-400">{purchase.bank_name} - {purchase.account_number}</p>
                        )}
                        {purchase.card_last_four && (
                          <p className="text-xs text-slate-400">Card ****{purchase.card_last_four}</p>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="text-xs text-slate-600 bg-slate-100 px-2 py-1 rounded">
                        {getPaymentMethodBadge(purchase.payment_method)}
                      </span>
                    </td>
                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-2">
                        {getStatusBadge(purchase.status)}
                        <select
                          value={purchase.status}
                          onChange={(e) => handleQuickStatusChange(purchase.id, e.target.value)}
                          className="text-xs border border-slate-200 rounded px-2 py-1 bg-white hover:bg-slate-50 cursor-pointer outline-none focus:ring-2 focus:ring-indigo-500"
                          title="Quick status change"
                        >
                          <option value="pending">Pending</option>
                          <option value="approved">Approved</option>
                          <option value="rejected">Rejected</option>
                        </select>
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      {purchase.payment_proof ? (
                        <button
                          onClick={() => handleImagePreview(purchase.payment_proof)}
                          title="View payment proof"
                          className="block group"
                        >
                          <div className="relative w-12 h-10 rounded-lg overflow-hidden border border-slate-200 bg-slate-100">
                            <img
                              src={getPaymentProofUrl(purchase.payment_proof)}
                              alt="Proof"
                              className="w-full h-full object-cover group-hover:opacity-80 transition-opacity"
                              onError={(e) => { e.target.style.display='none'; e.target.nextSibling.style.display='flex'; }}
                            />
                            <div style={{display:'none'}} className="absolute inset-0 flex items-center justify-center bg-slate-100">
                              <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01" />
                              </svg>
                            </div>
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors rounded-lg" />
                          </div>
                        </button>
                      ) : (
                        <span className="text-slate-300 text-xs">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3.5 text-slate-500">
                      {new Date(purchase.created_at).toLocaleDateString()}
                    </td>
                    <td className="px-4 py-3.5" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-center gap-1">
                        <button
                          onClick={() => handleEdit(purchase)}
                          title="Edit purchase"
                          className="p-2 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        {!purchase.shop_id && purchase.payment_proof && (
                          <button
                            onClick={() => handleCreateShop(purchase)}
                            title="Create shop from this purchase"
                            className="p-2 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                          >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                          </button>
                        )}
                        <button
                          onClick={() => handleDelete(purchase.id)}
                          title="Delete purchase"
                          className="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Edit Modal */}
      {showEditModal && selectedPurchase && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl">
            <div className="flex justify-between items-center px-6 py-4 border-b border-slate-100">
              <h3 className="text-lg font-bold text-slate-800">Update Plan Purchase</h3>
              <button
                onClick={() => setShowEditModal(false)}
                className="text-slate-400 hover:text-slate-600 transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form onSubmit={handleUpdate} className="p-6 space-y-4">
              <div className="bg-slate-50 rounded-xl p-4 mb-4">
                <p className="font-semibold text-slate-800">{selectedPurchase.plan_name}</p>
                <p className="text-sm text-slate-600">{selectedPurchase.user_name} - ৳{parseFloat(selectedPurchase.plan_price || 0).toFixed(2)}</p>
              </div>

              {/* Payment Details */}
              <div className="bg-indigo-50 rounded-xl p-4 mb-4">
                <h4 className="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-3">Payment Details</h4>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-slate-600">Transaction ID:</span>
                    <span className="font-mono text-slate-800">{selectedPurchase.transaction_id || '—'}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-600">Payment Method:</span>
                    <span className="text-slate-800">{getPaymentMethodBadge(selectedPurchase.payment_method)}</span>
                  </div>
                  {selectedPurchase.bank_name && (
                    <div className="flex justify-between">
                      <span className="text-slate-600">Bank:</span>
                      <span className="text-slate-800">{selectedPurchase.bank_name}</span>
                    </div>
                  )}
                  {selectedPurchase.account_number && (
                    <div className="flex justify-between">
                      <span className="text-slate-600">Account:</span>
                      <span className="font-mono text-slate-800">{selectedPurchase.account_number}</span>
                    </div>
                  )}
                  {selectedPurchase.card_last_four && (
                    <div className="flex justify-between">
                      <span className="text-slate-600">Card:</span>
                      <span className="font-mono text-slate-800">****{selectedPurchase.card_last_four}</span>
                    </div>
                  )}
                  {selectedPurchase.payment_proof && (
                    <div className="pt-2">
                      <span className="text-slate-600 block mb-2 text-xs font-semibold uppercase tracking-wider">Payment Proof:</span>
                      <a
                        href={getPaymentProofUrl(selectedPurchase.payment_proof)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block group"
                        title="Click to view full size"
                      >
                        <div className="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                          <img
                            src={getPaymentProofUrl(selectedPurchase.payment_proof)}
                            alt="Payment proof"
                            className="w-full max-h-48 object-contain group-hover:opacity-90 transition-opacity"
                            onError={(e) => {
                              e.target.style.display = 'none';
                              e.target.nextSibling.style.display = 'flex';
                            }}
                          />
                          <div
                            style={{ display: 'none' }}
                            className="flex-col items-center justify-center p-4 text-slate-400 gap-2"
                          >
                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <a href={getPaymentProofUrl(selectedPurchase.payment_proof)} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800 text-xs underline">
                              View Proof File
                            </a>
                          </div>
                          <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/30 rounded-xl">
                            <span className="bg-white/90 text-slate-800 text-xs font-semibold px-3 py-1 rounded-full shadow">
                              View Full Size ↗
                            </span>
                          </div>
                        </div>
                      </a>
                    </div>
                  )}
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status *</label>
                <select
                  value={editForm.status}
                  onChange={(e) => setEditForm({ ...editForm, status: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                  required
                >
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Link to Shop</label>
                <select
                  value={editForm.shop_id}
                  onChange={(e) => setEditForm({ ...editForm, shop_id: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="">No Shop</option>
                  {shops.map(shop => (
                    <option key={shop.id} value={shop.id}>{shop.name} (#{shop.id})</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Notes</label>
                <textarea
                  value={editForm.notes}
                  onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                  rows="3"
                  placeholder="Add notes about this purchase..."
                />
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  type="submit"
                  className="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors"
                >
                  Update Purchase
                </button>
                <button
                  type="button"
                  onClick={() => setShowEditModal(false)}
                  className="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 px-6 rounded-xl text-sm transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Image Preview Modal */}
      {showImageModal && selectedImage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/90 backdrop-blur-sm" onClick={() => setShowImageModal(false)}>
          <div className="relative max-w-4xl max-h-[90vh] w-full" onClick={(e) => e.stopPropagation()}>
            <button
              onClick={() => setShowImageModal(false)}
              className="absolute -top-12 right-0 text-white hover:text-slate-300 transition-colors"
            >
              <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
            <img
              src={selectedImage}
              alt="Payment proof"
              className="w-full h-full object-contain rounded-lg shadow-2xl"
              onError={(e) => {
                e.target.style.display = 'none';
                e.target.nextSibling.style.display = 'flex';
              }}
            />
            <div
              style={{ display: 'none' }}
              className="flex flex-col items-center justify-center p-8 text-white gap-4"
            >
              <svg className="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
              </svg>
              <p className="text-lg font-semibold">Failed to load image</p>
              <a
                href={selectedImage}
                target="_blank"
                rel="noopener noreferrer"
                className="text-indigo-400 hover:text-indigo-300 underline"
              >
                Open in new tab
              </a>
            </div>
          </div>
        </div>
      )}
      {showShopModal && selectedPurchase && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="bg-white rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
            <div className="flex justify-between items-center px-6 py-4 border-b border-slate-100 sticky top-0 bg-white">
              <h3 className="text-lg font-bold text-slate-800">Create Shop from Purchase</h3>
              <button
                onClick={() => setShowShopModal(false)}
                className="text-slate-400 hover:text-slate-600 transition-colors"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <form onSubmit={handleShopSubmit} className="p-6 space-y-4">
              <div className="bg-indigo-50 rounded-xl p-4 mb-4">
                <h4 className="text-xs font-bold text-indigo-700 uppercase tracking-wider mb-2">Purchase Details</h4>
                <div className="space-y-1 text-sm">
                  <div className="flex justify-between">
                    <span className="text-slate-600">Plan:</span>
                    <span className="font-semibold text-slate-800">{selectedPurchase.plan_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-600">Customer:</span>
                    <span className="font-semibold text-slate-800">{selectedPurchase.user_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-600">Email:</span>
                    <span className="font-semibold text-slate-800">{selectedPurchase.user_email}</span>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Shop Name *</label>
                  <input
                    type="text"
                    value={shopForm.shop_name}
                    onChange={(e) => setShopForm({ ...shopForm, shop_name: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    required
                    placeholder="Enter shop name"
                  />
                </div>
                <div className="col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Shop Email *</label>
                  <input
                    type="email"
                    value={shopForm.shop_email}
                    onChange={(e) => setShopForm({ ...shopForm, shop_email: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    required
                    placeholder="shop@example.com"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Shop Phone</label>
                  <input
                    type="text"
                    value={shopForm.shop_phone}
                    onChange={(e) => setShopForm({ ...shopForm, shop_phone: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="+8801..."
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Shop Address</label>
                  <input
                    type="text"
                    value={shopForm.shop_address}
                    onChange={(e) => setShopForm({ ...shopForm, shop_address: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Enter address"
                  />
                </div>
              </div>

              <div className="border-t border-slate-200 pt-4 mt-4">
                <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Shop Admin Account</h4>
                <div className="grid grid-cols-2 gap-4">
                  <div className="col-span-2">
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Admin Name *</label>
                    <input
                      type="text"
                      value={shopForm.admin_name}
                      onChange={(e) => setShopForm({ ...shopForm, admin_name: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                      required
                      placeholder="Admin full name"
                    />
                  </div>
                  <div className="col-span-2">
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Admin Email *</label>
                    <input
                      type="email"
                      value={shopForm.admin_email}
                      onChange={(e) => setShopForm({ ...shopForm, admin_email: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                      required
                      placeholder="admin@example.com"
                    />
                  </div>
                  <div className="col-span-2">
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Admin Password *</label>
                    <input
                      type="password"
                      value={shopForm.admin_password}
                      onChange={(e) => setShopForm({ ...shopForm, admin_password: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                      required
                      minLength="6"
                      placeholder="Min 6 characters"
                    />
                  </div>
                </div>
              </div>

              <div className="flex gap-3 pt-4">
                <button
                  type="submit"
                  className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors"
                >
                  Create Shop & Approve Purchase
                </button>
                <button
                  type="button"
                  onClick={() => setShowShopModal(false)}
                  className="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold py-2.5 px-6 rounded-xl text-sm transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
