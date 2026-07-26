import React, { useState, useEffect } from 'react';
import API_BASE_URL from '../config';

const PaymentMethods = () => {
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [showAddModal, setShowAddModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [selectedMethod, setSelectedMethod] = useState(null);
  const [modalError, setModalError] = useState('');
  const [formData, setFormData] = useState({
    type: 'mobile_payment',
    name: '',
    phone_number: '',
    account_number: '',
    account_holder: '',
    branch_name: '',
    routing_number: '',
    is_active: true,
    sort_order: 0
  });
  const [alert, setAlert] = useState({ show: false, message: '', type: 'success' });

  const token = localStorage.getItem('token');

  const fetchPaymentMethods = async () => {
    setLoading(true);
    try {
      const res = await fetch(`${API_BASE_URL}/payment-methods`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      if (res.ok) {
        const data = await res.json();
        setPaymentMethods(Array.isArray(data) ? data : []);
      } else {
        const errData = await res.json().catch(() => ({}));
        console.error('Failed to fetch payment methods:', errData);
      }
    } catch (err) {
      console.error('Failed to fetch payment methods:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPaymentMethods();
  }, []);

  const handleAdd = () => {
    setFormData({
      type: 'mobile_payment',
      name: '',
      phone_number: '',
      account_number: '',
      account_holder: '',
      branch_name: '',
      routing_number: '',
      is_active: true,
      sort_order: (paymentMethods.length + 1)
    });
    setModalError('');
    setShowAddModal(true);
  };

  const handleEdit = (method) => {
    setSelectedMethod(method);
    setFormData({
      type: method.type || 'mobile_payment',
      name: method.name || '',
      phone_number: method.phone_number || '',
      account_number: method.account_number || '',
      account_holder: method.account_holder || '',
      branch_name: method.branch_name || '',
      routing_number: method.routing_number || '',
      is_active: Boolean(method.is_active),
      sort_order: method.sort_order ?? 0
    });
    setModalError('');
    setShowEditModal(true);
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this payment method?')) return;

    try {
      const res = await fetch(`${API_BASE_URL}/payment-methods/${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        setAlert({ show: true, message: data.message || 'Payment method deleted successfully', type: 'success' });
        fetchPaymentMethods();
      } else {
        setAlert({ show: true, message: data.error || 'Failed to delete payment method', type: 'error' });
      }
    } catch (err) {
      setAlert({ show: true, message: 'Failed to delete payment method', type: 'error' });
    }
  };

  const handleSubmitAdd = async (e) => {
    e.preventDefault();
    setModalError('');

    if (!formData.name.trim()) {
      setModalError('Payment Method Name is required.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await fetch(`${API_BASE_URL}/payment-methods`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData)
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        setAlert({ show: true, message: data.message || 'Payment method added successfully', type: 'success' });
        setShowAddModal(false);
        fetchPaymentMethods();
      } else {
        setModalError(data.error || 'Failed to add payment method');
      }
    } catch (err) {
      setModalError('Network error while adding payment method.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSubmitEdit = async (e) => {
    e.preventDefault();
    setModalError('');

    if (!formData.name.trim()) {
      setModalError('Payment Method Name is required.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await fetch(`${API_BASE_URL}/payment-methods/${selectedMethod.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(formData)
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        setAlert({ show: true, message: data.message || 'Payment method updated successfully', type: 'success' });
        setShowEditModal(false);
        fetchPaymentMethods();
      } else {
        setModalError(data.error || 'Failed to update payment method');
      }
    } catch (err) {
      setModalError('Network error while updating payment method.');
    } finally {
      setSubmitting(false);
    }
  };

  const getTypeBadge = (type) => {
    const badges = {
      mobile_payment: 'bg-emerald-100 text-emerald-700',
      bank_transfer: 'bg-blue-100 text-blue-700',
      card: 'bg-purple-100 text-purple-700'
    };
    const labels = {
      mobile_payment: 'Mobile Payment',
      bank_transfer: 'Bank Transfer',
      card: 'Card'
    };
    return (
      <span className={`text-xs font-semibold px-2.5 py-1 rounded-lg ${badges[type] || badges.mobile_payment}`}>
        {labels[type] || type}
      </span>
    );
  };

  const activeCount = paymentMethods.filter(m => m.is_active).length;
  const mobileCount = paymentMethods.filter(m => m.type === 'mobile_payment').length;
  const bankCount = paymentMethods.filter(m => m.type === 'bank_transfer').length;
  const cardCount = paymentMethods.filter(m => m.type === 'card').length;

  return (
    <div className="p-6 space-y-6">
      {/* Page Alert */}
      {alert.show && (
        <div className={`p-4 rounded-xl flex justify-between items-center ${alert.type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}`}>
          <span>{alert.message}</span>
          <button onClick={() => setAlert({ show: false, message: '', type: 'success' })} className="text-xs font-bold underline ml-4 hover:opacity-75">
            Dismiss
          </button>
        </div>
      )}

      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-800">Payment Methods</h2>
          <p className="text-sm text-slate-500">Configure accepted payment methods (bKash, Nagad, Bank Transfer, Cards) for subscriptions</p>
        </div>
        <button
          onClick={handleAdd}
          className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-xl text-sm transition-all shadow-sm hover:shadow"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
          </svg>
          Add Payment Method
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-2xl font-bold text-slate-800">{paymentMethods.length}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Total Methods</div>
        </div>
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-2xl font-bold text-emerald-600">{activeCount}</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Active Methods</div>
        </div>
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-xs">
          <div className="text-lg font-bold text-indigo-600">{mobileCount} Mobile / {bankCount} Bank / {cardCount} Card</div>
          <div className="text-xs text-slate-500 font-medium mt-0.5">Methods Breakdown</div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-100 text-xs font-bold text-slate-400 uppercase tracking-wider bg-slate-50">
                <th className="px-4 py-3">ID</th>
                <th className="px-4 py-3">Type</th>
                <th className="px-4 py-3">Name</th>
                <th className="px-4 py-3">Details</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Order</th>
                <th className="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-sm">
              {loading ? (
                <tr>
                  <td colSpan="7" className="p-12 text-center">
                    <div className="flex flex-col items-center gap-3 text-slate-400">
                      <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-600" />
                      <span className="text-sm">Loading payment methods…</span>
                    </div>
                  </td>
                </tr>
              ) : paymentMethods.length === 0 ? (
                <tr>
                  <td colSpan="7" className="p-12 text-center">
                    <div className="flex flex-col items-center gap-2 text-slate-400">
                      <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                      </svg>
                      <p className="font-medium text-slate-600">No payment methods configured</p>
                      <p className="text-xs text-slate-400">Click "Add Payment Method" to create your first payment option.</p>
                    </div>
                  </td>
                </tr>
              ) : (
                paymentMethods.map((method) => (
                  <tr key={method.id} className="hover:bg-slate-50 transition-colors">
                    <td className="px-4 py-3.5 font-mono text-xs font-bold text-slate-400">#{method.id}</td>
                    <td className="px-4 py-3.5">{getTypeBadge(method.type)}</td>
                    <td className="px-4 py-3.5 font-semibold text-slate-800">{method.name}</td>
                    <td className="px-4 py-3.5">
                      <div className="text-xs text-slate-600 space-y-0.5">
                        {method.phone_number && <p><span className="font-medium text-slate-400">Phone:</span> {method.phone_number}</p>}
                        {method.account_number && <p><span className="font-medium text-slate-400">Account:</span> {method.account_number}</p>}
                        {method.account_holder && <p><span className="font-medium text-slate-400">Holder:</span> {method.account_holder}</p>}
                        {method.branch_name && <p><span className="font-medium text-slate-400">Branch:</span> {method.branch_name}</p>}
                        {method.routing_number && <p><span className="font-medium text-slate-400">Routing:</span> {method.routing_number}</p>}
                      </div>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${method.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                        {method.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="px-4 py-3.5 text-slate-500 font-mono text-xs">{method.sort_order}</td>
                    <td className="px-4 py-3.5">
                      <div className="flex items-center justify-center gap-1">
                        <button
                          onClick={() => handleEdit(method)}
                          title="Edit payment method"
                          className="p-2 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                        >
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        <button
                          onClick={() => handleDelete(method.id)}
                          title="Delete payment method"
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

      {/* Add Modal */}
      {showAddModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-fadeIn">
            <div className="flex justify-between items-center px-6 py-4 border-b border-slate-100 bg-slate-50/50">
              <h3 className="text-lg font-bold text-slate-800">Add Payment Method</h3>
              <button
                onClick={() => setShowAddModal(false)}
                className="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {modalError && (
              <div className="mx-6 mt-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
                {modalError}
              </div>
            )}

            <form onSubmit={handleSubmitAdd} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type *</label>
                <select
                  value={formData.type}
                  onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                  required
                >
                  <option value="mobile_payment">Mobile Payment (bKash, Nagad, Rocket)</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="card">Card Payment</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Name *</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                  placeholder={
                    formData.type === 'mobile_payment' ? 'e.g., bKash Personal / Nagad' :
                    formData.type === 'bank_transfer' ? 'e.g., Dutch-Bangla Bank (DBBL)' : 'e.g., Visa / Mastercard'
                  }
                  required
                />
              </div>

              {(formData.type === 'mobile_payment' || formData.type === 'card') && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                    {formData.type === 'mobile_payment' ? 'Phone Number' : 'Contact / Phone Number'}
                  </label>
                  <input
                    type="text"
                    value={formData.phone_number}
                    onChange={(e) => setFormData({ ...formData, phone_number: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="e.g. 01700000000"
                  />
                </div>
              )}

              {(formData.type === 'bank_transfer' || formData.type === 'card' || formData.type === 'mobile_payment') && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Account / Merchant Holder Name</label>
                  <input
                    type="text"
                    value={formData.account_holder}
                    onChange={(e) => setFormData({ ...formData, account_holder: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="e.g. CodexaaPOS++"
                  />
                </div>
              )}

              {(formData.type === 'bank_transfer' || formData.type === 'card') && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                    {formData.type === 'bank_transfer' ? 'Account Number' : 'Terminal / Merchant ID'}
                  </label>
                  <input
                    type="text"
                    value={formData.account_number}
                    onChange={(e) => setFormData({ ...formData, account_number: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="e.g. 1234567890"
                  />
                </div>
              )}

              {formData.type === 'bank_transfer' && (
                <>
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Branch Name</label>
                    <input
                      type="text"
                      value={formData.branch_name}
                      onChange={(e) => setFormData({ ...formData, branch_name: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="e.g. Gulshan Branch"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Routing Number</label>
                    <input
                      type="text"
                      value={formData.routing_number}
                      onChange={(e) => setFormData({ ...formData, routing_number: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                      placeholder="e.g. 125260840"
                    />
                  </div>
                </>
              )}

              <div className="flex items-center justify-between pt-2">
                <div className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    id="is_active_add"
                    checked={formData.is_active}
                    onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                    className="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"
                  />
                  <label htmlFor="is_active_add" className="text-sm font-medium text-slate-700">Active (Visible to users)</label>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sort Order</label>
                <input
                  type="number"
                  value={formData.sort_order}
                  onChange={(e) => setFormData({ ...formData, sort_order: parseInt(e.target.value, 10) || 0 })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex gap-3 pt-4 border-t border-slate-100">
                <button
                  type="submit"
                  disabled={submitting}
                  className="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2"
                >
                  {submitting ? (
                    <>
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <span>Saving…</span>
                    </>
                  ) : (
                    <span>Save Payment Method</span>
                  )}
                </button>
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 px-5 rounded-xl text-sm transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Modal */}
      {showEditModal && selectedMethod && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
          <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-fadeIn">
            <div className="flex justify-between items-center px-6 py-4 border-b border-slate-100 bg-slate-50/50">
              <h3 className="text-lg font-bold text-slate-800">Edit Payment Method</h3>
              <button
                onClick={() => setShowEditModal(false)}
                className="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-lg hover:bg-slate-100"
              >
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {modalError && (
              <div className="mx-6 mt-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl">
                {modalError}
              </div>
            )}

            <form onSubmit={handleSubmitEdit} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type *</label>
                <select
                  value={formData.type}
                  onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
                  required
                >
                  <option value="mobile_payment">Mobile Payment (bKash, Nagad, Rocket)</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="card">Card Payment</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Name *</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                  required
                />
              </div>

              {(formData.type === 'mobile_payment' || formData.type === 'card') && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone Number</label>
                  <input
                    type="text"
                    value={formData.phone_number}
                    onChange={(e) => setFormData({ ...formData, phone_number: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              )}

              {(formData.type === 'bank_transfer' || formData.type === 'card' || formData.type === 'mobile_payment') && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Account / Merchant Holder Name</label>
                  <input
                    type="text"
                    value={formData.account_holder}
                    onChange={(e) => setFormData({ ...formData, account_holder: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              )}

              {(formData.type === 'bank_transfer' || formData.type === 'card') && (
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                    {formData.type === 'bank_transfer' ? 'Account Number' : 'Terminal / Merchant ID'}
                  </label>
                  <input
                    type="text"
                    value={formData.account_number}
                    onChange={(e) => setFormData({ ...formData, account_number: e.target.value })}
                    className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                </div>
              )}

              {formData.type === 'bank_transfer' && (
                <>
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Branch Name</label>
                    <input
                      type="text"
                      value={formData.branch_name}
                      onChange={(e) => setFormData({ ...formData, branch_name: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Routing Number</label>
                    <input
                      type="text"
                      value={formData.routing_number}
                      onChange={(e) => setFormData({ ...formData, routing_number: e.target.value })}
                      className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </>
              )}

              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="is_active_edit"
                  checked={formData.is_active}
                  onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                  className="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500"
                />
                <label htmlFor="is_active_edit" className="text-sm font-medium text-slate-700">Active (Visible to users)</label>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sort Order</label>
                <input
                  type="number"
                  value={formData.sort_order}
                  onChange={(e) => setFormData({ ...formData, sort_order: parseInt(e.target.value, 10) || 0 })}
                  className="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
                />
              </div>

              <div className="flex gap-3 pt-4 border-t border-slate-100">
                <button
                  type="submit"
                  disabled={submitting}
                  className="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2"
                >
                  {submitting ? (
                    <>
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <span>Updating…</span>
                    </>
                  ) : (
                    <span>Update Payment Method</span>
                  )}
                </button>
                <button
                  type="button"
                  onClick={() => setShowEditModal(false)}
                  className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-2.5 px-5 rounded-xl text-sm transition-colors"
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
};

export default PaymentMethods;
