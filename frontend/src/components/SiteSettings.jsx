import React, { useState, useEffect } from 'react';
import API_BASE_URL from '../config';

export default function SiteSettings() {
  const [settings, setSettings] = useState({
    site_name: '',
    site_description: '',
    hero_content: '',
    site_logo: ''
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
          site_name: data.site_name || 'CodexaaPos++',
          site_description: data.site_description || 'Default Description',
          hero_content: data.hero_content || 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.',
          site_logo: data.site_logo || ''
        });
      } else {
        console.error('Site settings response not OK:', response.status);
        // Fallback for now
        setSettings({ site_name: 'CodexaaPos++', site_description: 'Modern Point of Sale For Your Business', hero_content: 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.', site_logo: '' });
      }
    } catch (err) {
      console.error("Could not fetch site settings, using fallback.", err);
      setSettings({ site_name: 'CodexaaPos++', site_description: 'Modern Point of Sale For Your Business', hero_content: 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.', site_logo: '' });
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

  const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Accept larger source files, but we will compress them
    if (file.size > 15 * 1024 * 1024) {
      triggerAlert('error', 'Source image must be less than 15MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        const MAX_WIDTH = 300;
        const MAX_HEIGHT = 300;
        let width = img.width;
        let height = img.height;

        if (width > height) {
          if (width > MAX_WIDTH) {
            height *= MAX_WIDTH / width;
            width = MAX_WIDTH;
          }
        } else {
          if (height > MAX_HEIGHT) {
            width *= MAX_HEIGHT / height;
            height = MAX_HEIGHT;
          }
        }

        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);

        // Get highly compressed PNG/JPEG DataURL
        const compressedBase64 = canvas.toDataURL('image/png');
        setSettings(prev => ({ ...prev, site_logo: compressedBase64 }));
      };
      img.src = event.target.result;
    };
    reader.readAsDataURL(file);
  };

  const handleRemoveLogo = () => {
    setSettings(prev => ({ ...prev, site_logo: '' }));
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
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Hero Content (Home Page)</label>
            <textarea name="hero_content" rows="4" value={settings.hero_content} onChange={handleInputChange} className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-1 focus:ring-indigo-500" />
          </div>
          <div className="border-t border-slate-100 pt-4">
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Site Logo</label>
            <div className="mt-2 flex items-center space-x-5">
              {settings.site_logo ? (
                <img
                  src={settings.site_logo}
                  alt="Site Logo Preview"
                  className="w-16 h-16 rounded-xl object-contain bg-slate-900 border border-slate-200"
                />
              ) : (
                <div className="w-16 h-16 rounded-xl bg-slate-100 border border-dashed border-slate-300 flex items-center justify-center text-slate-400 font-bold text-xs uppercase shrink-0">
                  No Logo
                </div>
              )}
              <div className="flex flex-col space-y-2">
                <input
                  type="file"
                  accept="image/*"
                  id="site-logo-upload-input"
                  onChange={handleLogoChange}
                  className="hidden"
                />
                <div className="flex space-x-2">
                  <label
                    htmlFor="site-logo-upload-input"
                    className="cursor-pointer bg-white hover:bg-slate-50 text-slate-700 font-semibold py-2 px-4 border border-slate-200 rounded-xl text-xs shadow-xs transition-colors"
                  >
                    Choose Image
                  </label>
                  {settings.site_logo && (
                    <button
                      type="button"
                      onClick={handleRemoveLogo}
                      className="bg-rose-50 hover:bg-rose-100 text-rose-600 font-semibold py-2 px-4 border border-rose-200 rounded-xl text-xs transition-colors"
                    >
                      Remove Logo
                    </button>
                  )}
                </div>
                <span className="text-[10px] text-slate-450">PNG, JPG, or SVG. Max size 15MB. Will be compressed to 300x300px.</span>
              </div>
            </div>
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