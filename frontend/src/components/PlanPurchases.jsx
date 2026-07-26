import React, { useState, useEffect } from 'react';
import API_BASE_URL from '../config';

export default function PlanPurchases() {
  const [purchases, setPurchases] = useState([]);
  const [loading, setLoading] = useState(true);
  const [alert, setAlert] = useState(null);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedPurchase, setSelectedPurchase] = useState(null);
  const [editForm, setEditForm] = useState({ status: '', notes: '' });

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

  useEffect(() => {
    fetchPurchases();
  }, []);

  const getStatusBadge = (status) => {
    const styles = {
      pending: 'bg-amber-50 text-amber-700 border-amber-200',
      completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      failed: 'bg-rose-50 text-rose-700 border-rose-200',
      cancelled: 'bg-slate-50 text-slate-700 border-slate-200'
    };
    return (
      <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border ${styles[status] || styles.pending}`}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </span>
    );
  };

  const handleEdit = (purchase) => {
    setSelectedPurchase(purchase);
    setEditForm({ status: purchase.status, notes: purchase.notes || '' });
    setShowEditModal(true);
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
    const labels = {
      cash: 'Cash',
      card: 'Card',
      mobile_pay: 'Mobile Pay',
      bank_transfer: 'Bank Transfer',
      other: 'Other'
    };
    return labels[method] || method;
  };

  // Calculate stats
  const totalPurchases = purchases.length;
  const completedPurchases = purchases.filter(p => p.status === 'completed').length;
  const pendingPurchases = purchases.filter(p => p.status === 'pending').length;
  const totalRevenue = purchases
    .filter(p => p.status === 'completed')
    .reduce((sum, p) => sum + (p.amount_paid || 0), 0);

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
          <div className="text-2xl font-bold text-emerald-600">{completedPurchases}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Completed</div>
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
                  <td colSpan="10" className="p-12 text-center">
                    <div className="flex flex-col items-center gap-3 text-slate-400">
                      <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-600" />
                      <span className="text-sm">Loading purchases…</span>
                    </div>
                  </td>
                </tr>
              ) : purchases.length === 0 ? (
                <tr>
                  <td colSpan="10" className="p-12 text-center">
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
                    <td className="px-4 py-3.5 font-bold text-indigo-600">৳{parseFloat(purchase.amount_paid || 0).toFixed(2)}</td>
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
                    <td className="px-4 py-3.5">{getStatusBadge(purchase.status)}</td>
                    <td className="px-4 py-3.5">
                      {purchase.payment_proof ? (
                        <a
                          href={purchase.payment_proof}
                          target="_blank"
                          rel="noopener noreferrer"
                          title="View payment proof"
                          className="block group"
                        >
                          <div className="relative w-12 h-10 rounded-lg overflow-hidden border border-slate-200 bg-slate-100">
                            <img
                              src={purchase.payment_proof}
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
                        </a>
                      ) : (
                        <span className="text-slate-300 text-xs">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3.5 text-slate-500">
                      {new Date(purchase.purchase_date).toLocaleDateString()}
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
                <p className="text-sm text-slate-600">{selectedPurchase.user_name} - ৳{parseFloat(selectedPurchase.amount_paid || 0).toFixed(2)}</p>
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
                        href={selectedPurchase.payment_proof}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block group"
                        title="Click to view full size"
                      >
                        <div className="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                          <img
                            src={selectedPurchase.payment_proof}
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
                            <a href={selectedPurchase.payment_proof} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:text-indigo-800 text-xs underline">
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
                  <option value="completed">Completed</option>
                  <option value="failed">Failed</option>
                  <option value="cancelled">Cancelled</option>
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
    </div>
  );
}
