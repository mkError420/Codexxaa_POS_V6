import React, { useState, useEffect } from 'react';
import API_BASE_URL from '../config';

export default function SiteSettings() {
  const [settings, setSettings] = useState({
    site_name: '',
    site_description: ''
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [alert, setAlert] = useState(null);

  const fetchSiteSettings = async () => {
    setLoading(true);
    try {
      console.log('Fetching site settings from:', `${API_BASE_URL}/settings/site`);
      const response = await fetch(`${API_BASE_URL}/settings/site`);
      console.log('Site settings response status:', response.status);
      if (response.ok) {
        const data = await response.json();
        console.log('Site settings data received:', data);
        setSettings({
          site_name: data.site_name || 'CodexaaPOS++',
          site_description: data.site_description || 'Default Description'
        });
      } else {
        console.error('Site settings response not OK:', response.status);
        // Fallback for now
        setSettings({ site_name: 'CodexaaPOS++', site_description: 'Modern Point of Sale For Your Business' });
      }
    } catch (err) {
      console.error("Could not fetch site settings, using fallback.", err);
      setSettings({ site_name: 'CodexaaPOS++', site_description: 'Modern Point of Sale For Your Business' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSiteSettings();
  }, []);

  const triggerAlert = (type, message) => {
    setAlert({ type, message });
    setTimeout(() => setAlert(null), 4000);
  };

  const handleInputChange = (e) => {
    setSettings({ ...settings, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const token = localStorage.getItem('token');
      console.log('Saving site settings:', settings);
      console.log('Token exists:', !!token);
      const response = await fetch(`${API_BASE_URL}/settings/site`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(settings)
      });

      console.log('Save response status:', response.status);
      if (!response.ok) {
        const resData = await response.json();
        console.error('Save error response:', resData);
        throw new Error(resData.error || 'Failed to save site settings.');
      }

      const responseData = await response.json();
      console.log('Save success response:', responseData);
      triggerAlert('success', 'Site settings updated successfully!');
    } catch (err) {
      console.error('Save error:', err);
      triggerAlert('error', err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-6 max-w-2xl">
      {alert && (
        <div className={`fixed top-4 right-4 z-50 p-4 rounded-xl shadow-lg flex items-center transition-all ${alert.type === 'success' ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'}`}>
          <span className="text-sm font-semibold">{alert.message}</span>
        </div>
      )}

      <div>
        <h2 className="text-2xl font-bold text-slate-800">Site Settings</h2>
        <p className="text-sm text-slate-500">Manage global site branding and details shown on public pages.</p>
      </div>

      <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs">
        <form onSubmit={handleSubmit} className="space-y-5">
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Site Name *</label>
            <input type="text" name="site_name" value={settings.site_name} onChange={handleInputChange} required className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-1 focus:ring-indigo-500" />
          </div>
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Site Description / Tagline</label>
            <textarea name="site_description" rows="3" value={settings.site_description} onChange={handleInputChange} className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-1 focus:ring-indigo-500" />
          </div>
          <div className="pt-4 border-t border-slate-100 flex justify-end">
            <button type="submit" disabled={saving || loading} className="bg-slate-600 hover:bg-indigo-700 disabled:bg-slate-300 text-white font-bold py-2.5 px-6 rounded-xl text-sm shadow-md transition-colors flex items-center space-x-2">
              {saving ? <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-white"></div> : <span>Save Site Settings</span>}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}