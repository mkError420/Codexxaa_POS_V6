import React, { useState, useEffect } from 'react';
import API_BASE_URL from '../config';

export default function Home({ onLoginSuccess }) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [activeSection, setActiveSection] = useState(null);
  const [siteSettings, setSiteSettings] = useState({
    site_name: 'CodexaaPos++',
    site_description: 'Modern Point of Sale For Your Business',
    hero_content: 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.',
    site_logo: '',
    active_shop_count: 0
  });
  const [pricingPlans, setPricingPlans] = useState([]);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [showPurchaseModal, setShowPurchaseModal] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState(null);
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState(null);
  const [purchaseForm, setPurchaseForm] = useState({
    user_name: '',
    user_email: '',
    user_phone: '',
    payment_method: 'other',
    notes: ''
  });
  const [purchaseLoading, setPurchaseLoading] = useState(false);
  const [purchaseError, setPurchaseError] = useState('');
  const [purchaseSuccess, setPurchaseSuccess] = useState(false);
  const [showPaymentStep, setShowPaymentStep] = useState(false);
  const [paymentDetails, setPaymentDetails] = useState({
    transaction_id: '',
    bank_name: '',
    account_number: '',
    card_last_four: '',
    payment_proof: ''
  });

  const scrollToSection = (sectionId) => {
    setActiveSection(sectionId);
    const element = document.getElementById(sectionId);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth' });
    }
  };

  useEffect(() => {
    const fetchSiteSettings = async () => {
      try {
        console.log('Fetching site settings from:', `${API_BASE_URL}/settings/site`);
        const response = await fetch(`${API_BASE_URL}/settings/site`);
        console.log('Site settings response status:', response.status);
        if (response.ok) {
          const data = await response.json();
          console.log('Site settings data received:', data);
          setSiteSettings({
            site_name: data.site_name || 'CodexaaPos++',
            site_description: data.site_description || 'Modern Point of Sale For Your Business',
            hero_content: data.hero_content || 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.',
            site_logo: data.site_logo || '',
            active_shop_count: data.active_shop_count || 0
          });
        } else {
          console.error('Site settings response not OK:', response.status);
          setSiteSettings({ site_name: 'CodexaaPos++', site_description: 'Modern Point of Sale For Your Business', hero_content: 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.', site_logo: '', active_shop_count: 0 });
        }
      } catch (err) {
        console.error("Could not fetch site settings, using defaults.", err);
        setSiteSettings({ site_name: 'CodexaaPos++', site_description: 'Modern Point of Sale For Your Business', hero_content: 'Streamline your retail operations with our powerful, cloud-based POS solution. Manage inventory, sales, customers, and more from anywhere.', site_logo: '', active_shop_count: 0 });
      }
    };

    const fetchPricingPlans = async () => {
      try {
        console.log('Fetching pricing plans from:', `${API_BASE_URL}/pricing-plans`);
        const response = await fetch(`${API_BASE_URL}/pricing-plans`);
        console.log('Pricing plans response status:', response.status);
        if (response.ok) {
          const data = await response.json();
          console.log('Pricing plans data received:', data);
          setPricingPlans(data);
        } else {
          console.error('Pricing plans response not OK:', response.status);
          setPricingPlans([]);
        }
      } catch (err) {
        console.error("Could not fetch pricing plans, using empty array.", err);
        setPricingPlans([]);
      }
    };

    const fetchPaymentMethods = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/payment-methods/active`);
        if (response.ok) {
          const data = await response.json();
          setPaymentMethods(data);
        }
      } catch (err) {
        console.error("Could not fetch payment methods.", err);
        setPaymentMethods([]);
      }
    };

    fetchSiteSettings();
    fetchPricingPlans();
    fetchPaymentMethods();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const res = await fetch(`${API_BASE_URL}/auth/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();

      if (!res.ok) {
        setError(data.error || 'Login failed. Please check your credentials.');
        setLoading(false);
        return;
      }

      localStorage.setItem('token', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));
      onLoginSuccess(data.user);
    } catch (err) {
      setError('Cannot connect to server. Make sure the backend is running.');
      setLoading(false);
    }
  };

  const handlePurchasePlan = (plan) => {
    setSelectedPlan(plan);
    setSelectedPaymentMethod(null);
    setShowPurchaseModal(true);
    setShowPaymentStep(false);
    setPurchaseError('');
    setPurchaseSuccess(false);
    setPurchaseForm({
      user_name: '',
      user_email: '',
      user_phone: '',
      payment_method: 'other',
      notes: ''
    });
    setPaymentDetails({
      transaction_id: '',
      bank_name: '',
      account_number: '',
      card_last_four: '',
      payment_proof: ''
    });
  };

  const handleProceedToPayment = (e) => {
    e.preventDefault();
    if (!purchaseForm.user_name || !purchaseForm.user_email) {
      setPurchaseError('Please fill in required fields');
      return;
    }
    if (!purchaseForm.payment_method || purchaseForm.payment_method === 'other') {
      setPurchaseError('Please select a payment method');
      return;
    }
    // Find the selected payment method object
    const method = paymentMethods.find(m => m.id === parseInt(purchaseForm.payment_method));
    setSelectedPaymentMethod(method);
    setShowPaymentStep(true);
    setPurchaseError('');
  };

  const handlePurchaseSubmit = async (e) => {
    e.preventDefault();
    setPurchaseError('');
    setPurchaseLoading(true);

    try {
      const res = await fetch(`${API_BASE_URL}/plan-purchases`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          plan_id: selectedPlan.id,
          user_name: purchaseForm.user_name,
          user_email: purchaseForm.user_email,
          user_phone: purchaseForm.user_phone,
          payment_method: selectedPaymentMethod?.type || purchaseForm.payment_method,
          payment_method_id: selectedPaymentMethod?.id || null,
          notes: purchaseForm.notes,
          transaction_id: paymentDetails.transaction_id,
          bank_name: paymentDetails.bank_name,
          account_number: paymentDetails.account_number,
          card_last_four: paymentDetails.card_last_four,
          payment_proof: paymentDetails.payment_proof
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        setPurchaseError(data.error || 'Purchase failed. Please try again.');
        setPurchaseLoading(false);
        return;
      }

      setPurchaseSuccess(true);
      setPurchaseLoading(false);
      
      // Close modal after 2 seconds
      setTimeout(() => {
        setShowPurchaseModal(false);
        setPurchaseSuccess(false);
      }, 2000);
    } catch (err) {
      setPurchaseError('Cannot connect to server. Make sure the backend is running.');
      setPurchaseLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 relative overflow-hidden">
      
      {/* Animated background blobs */}
      <div className="absolute top-[-10%] left-[-5%] w-[500px] h-[500px] bg-slate-600/20 rounded-full blur-3xl animate-pulse" />
      <div className="absolute bottom-[-10%] right-[-5%] w-[500px] h-[500px] bg-violet-600/20 rounded-full blur-3xl animate-pulse" style={{ animationDelay: '1s' }} />
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] bg-indigo-900/10 rounded-full blur-3xl" />

      {/* Navigation */}
      <nav className="relative z-10 flex items-center justify-between px-6 py-4 lg:px-12">
        <div className="flex items-center gap-3">
          {siteSettings.site_logo ? (
            <img
              src={siteSettings.site_logo}
              alt="Site Logo"
              className="w-10 h-10 rounded-xl object-contain bg-slate-600 shadow-xl shadow-indigo-600/40"
            />
          ) : (
            <div className="w-10 h-10 rounded-xl bg-slate-600 shadow-xl shadow-indigo-600/40 flex items-center justify-center">
              <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
          )}
          <span className="text-white font-bold text-xl tracking-tight">{siteSettings.site_name}</span>
        </div>
      </nav>

      {/* Hero Section */}
      <div className="relative z-10 max-w-7xl mx-auto px-6 lg:px-12 pt-20 pb-32">
        <div className="grid lg:grid-cols-2 gap-12 items-center">
          
          {/* Left Content */}
          <div className="space-y-8">
            <div className="space-y-4">
              <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-500/10 border border-indigo-500/20 rounded-full">
                <span className="w-2 h-2 bg-indigo-400 rounded-full animate-pulse" />
                <span className="text-indigo-300 text-xs font-medium">Multi-Tenant POS System</span>
              </div>
              <h1 className="text-4xl lg:text-6xl font-bold text-white leading-tight">
                {siteSettings.site_description}
              </h1>
              <p className="text-slate-400 text-lg max-w-lg leading-relaxed">
                {siteSettings.hero_content}
              </p>
            </div>

            {/* CTA Buttons */}
            <div className="flex flex-wrap gap-4">
              <button className="px-8 py-4 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-xl shadow-indigo-600/30 hover:shadow-indigo-500/40 hover:-translate-y-0.5 active:translate-y-0 flex items-center gap-2">
                Learn More
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
              </button>
              <button className="px-8 py-4 bg-slate-800/60 hover:bg-slate-800/80 border border-slate-700 text-white font-semibold rounded-xl transition-all duration-200 flex items-center gap-2">
                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Watch Details
              </button>
            </div>

            {/* Stats */}
            <div className="flex gap-8 pt-8 border-t border-slate-800">
              <div>
                <div className="text-3xl font-bold text-white">{siteSettings.active_shop_count}+</div>
                <div className="text-slate-500 text-sm">Active Shops</div>
              </div>
              <div>
                <div className="text-3xl font-bold text-white">10K+</div>
                <div className="text-slate-500 text-sm">Daily Transactions</div>
              </div>
              <div>
                <div className="text-3xl font-bold text-white">99.9%</div>
                <div className="text-slate-500 text-sm">Uptime</div>
              </div>
            </div>
          </div>

          {/* Right Content - Login Form */}
          <div className="relative">
            <div className="bg-slate-900/80 backdrop-blur-xl border border-slate-700/60 rounded-2xl p-8 shadow-2xl">
              {/* Brand */}
              <div className="text-center mb-6">
                {siteSettings.site_logo ? (
                  <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-600 shadow-2xl shadow-indigo-600/40 mb-3">
                    <img
                      src={siteSettings.site_logo}
                      alt="Site Logo"
                      className="w-10 h-10 rounded-xl object-contain"
                    />
                  </div>
                ) : (
                  <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-600 shadow-2xl shadow-indigo-600/40 mb-3">
                    <svg className="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                  </div>
                )}
                <h2 className="text-2xl font-bold text-white tracking-tight">Sign In</h2>
                <p className="text-slate-400 mt-1 text-sm">Access your dashboard</p>
              </div>

              <form onSubmit={handleSubmit} className="space-y-5">
                {/* Error Message */}
                {error && (
                  <div className="flex items-start gap-3 bg-rose-500/10 border border-rose-500/30 rounded-xl p-4">
                    <svg className="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p className="text-rose-300 text-sm">{error}</p>
                  </div>
                )}

                {/* Email */}
                <div>
                  <label htmlFor="email" className="block text-sm font-medium text-slate-300 mb-2">
                    Email address
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg className="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                      </svg>
                    </div>
                    <input
                      id="email"
                      type="email"
                      autoComplete="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="admin@example.com"
                      className="w-full bg-slate-800/60 border border-slate-600/60 text-white placeholder-slate-500 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    />
                  </div>
                </div>

                {/* Password */}
                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-slate-300 mb-2">
                    Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <svg className="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                      </svg>
                    </div>
                    <input
                      id="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="current-password"
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="••••••••"
                      className="w-full bg-slate-800/60 border border-slate-600/60 text-white placeholder-slate-500 rounded-xl pl-10 pr-12 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 transition-colors"
                    >
                      {showPassword ? (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        </svg>
                      ) : (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      )}
                    </button>
                  </div>
                </div>

                {/* Submit Button */}
                <button
                  type="submit"
                  disabled={loading}
                  className="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-600/50 disabled:cursor-not-allowed text-white font-semibold rounded-xl py-3 text-sm transition-all duration-200 shadow-lg shadow-indigo-600/30 hover:shadow-indigo-500/40 hover:-translate-y-0.5 active:translate-y-0"
                >
                  {loading ? (
                    <>
                      <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                      </svg>
                      Signing in...
                    </>
                  ) : (
                    <>
                      Sign In
                      <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                      </svg>
                    </>
                  )}
                </button>
              </form>

              {/* Role Hint Cards */}
              <div className="mt-6 pt-6 border-t border-slate-700/60">
                <p className="text-xs text-slate-500 text-center mb-3 font-medium uppercase tracking-wider">Demo Credentials</p>
                <div className="grid grid-cols-1 gap-2">
                  <button
                type="button"
                onClick={() => { setEmail('mk.rabbani.cse@gmail.com'); setPassword('*********'); }}
                className="flex items-center gap-3 w-full text-left bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 rounded-xl px-3 py-2.5 transition-colors group"
              >
                <span className="text-xs font-bold bg-rose-500/20 text-rose-400 px-2 py-0.5 rounded-full shrink-0">SUPER ADMIN</span>
                <span className="text-xs text-slate-400 group-hover:text-slate-300 transition-colors truncate">Restricted!!!</span>
              </button>
                  <button
                    type="button"
                    onClick={() => { setEmail('alice@boutique.com'); setPassword('alice123'); }}
                    className="flex items-center gap-3 w-full text-left bg-slate-500/10 hover:bg-slate-500/20 border border-slate-500/20 rounded-xl px-3 py-2.5 transition-colors group"
                  >
                    <span className="text-xs font-bold bg-slate-500/20 text-slate-400 px-2 py-0.5 rounded-full shrink-0">SHOP ADMIN</span>
                    <span className="text-xs text-slate-400 group-hover:text-slate-300 transition-colors truncate">alice@boutique.com · alice123</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => { setEmail('admin@lakeside.com'); setPassword('lakeside123'); }}
                    className="flex items-center gap-3 w-full text-left bg-slate-500/10 hover:bg-slate-500/20 border border-slate-500/20 rounded-xl px-3 py-2.5 transition-colors group"
                  >
                    <span className="text-xs font-bold bg-slate-500/20 text-slate-400 px-2 py-0.5 rounded-full shrink-0">SHOP ADMIN</span>
                    <span className="text-xs text-slate-400 group-hover:text-slate-300 transition-colors truncate">admin@lakeside.com · lakeside123</span>
                  </button>
                  <button
                    type="button"
                    onClick={() => { setEmail('staff1@boutique.com'); setPassword('staff123'); }}
                    className="flex items-center gap-3 w-full text-left bg-slate-500/10 hover:bg-slate-500/20 border border-slate-500/20 rounded-xl px-3 py-2.5 transition-colors group"
                  >
                    <span className="text-xs font-bold bg-slate-500/20 text-slate-400 px-2 py-0.5 rounded-full shrink-0">SHOP STAFF</span>
                    <span className="text-xs text-slate-400 group-hover:text-slate-300 transition-colors truncate">staff1@boutique.com · staff123</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <section className="relative z-10 py-20 lg:py-32 bg-slate-950/50">
        <div className="max-w-7xl mx-auto px-6 lg:px-12">
          {/* Section Header */}
          <div className="text-center mb-16">
            <h2 className="text-4xl lg:text-5xl font-bold text-white">A Feature for Every Need</h2>
            <p className="text-slate-400 mt-4 max-w-3xl mx-auto text-lg">
              From a simple checkout to complex multi-store management, CodexaaPOS++ is equipped with tools to grow your business.
            </p>
          </div>

          {/* Features Grid */}
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            {[
              {                
                title: 'Multi-Tenant Architecture',
                description: 'Manage multiple shops from a single, powerful super-admin dashboard. Perfect for franchises and chains.'
              },
              {
                title: 'Advanced POS & Checkout',
                description: 'Fast barcode scanning, multiple payment methods, held bills, and an integrated loyalty program to keep customers coming back.'
              },
              {
                title: 'Comprehensive Inventory',
                description: 'Real-time stock tracking, low-stock alerts, expiry date management, purchase orders, and supplier management.'
              },
              {
                title: 'In-depth Analytics',
                description: 'A powerful dashboard with sales trends, revenue breakdowns, top-selling products, and exportable reports.'
              },
              {
                title: 'Customer Management',
                description: 'Maintain a complete customer directory with purchase history, due balance tracking, and loyalty points.'
              },
              {
                title: 'Role-Based Access Control',
                description: 'Assign granular permissions for Shop Admins and Staff, ensuring users only access the sections they need.'
              }
            ].map((feature, index) => (
              <div key={index} className="bg-slate-800/50 backdrop-blur-lg border border-slate-700/50 rounded-2xl p-6 transform hover:-translate-y-2 transition-transform duration-300">
                <h3 className="text-lg font-bold text-white mb-2">{feature.title}</h3>
                <p className="text-slate-400 text-sm">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing Section */}
      <section className="relative z-10 py-20 lg:py-32 bg-gradient-to-b from-slate-950/50 to-indigo-950/30">
        <div className="max-w-7xl mx-auto px-6 lg:px-12">
          <div className="text-center mb-16">
            <h2 className="text-4xl lg:text-5xl font-bold text-white mb-4">Choose Your Plan</h2>
            <p className="text-slate-400 text-lg max-w-3xl mx-auto">
              Flexible pricing plans to fit your business needs. Start with a free trial or choose a plan that works for you.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            {pricingPlans.map((plan) => (
              <div key={plan.id} className={`${plan.is_popular ? 'bg-gradient-to-b from-indigo-900/50 to-violet-900/50 backdrop-blur-xl border-2 border-indigo-500 rounded-3xl p-8 relative transform scale-105 shadow-2xl shadow-indigo-600/30' : 'bg-slate-800/50 backdrop-blur-xl border border-slate-700/50 rounded-3xl p-8 hover:border-indigo-500/50 transition-all duration-300'}`}>
                {plan.is_popular && (
                  <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-xs font-bold px-4 py-1 rounded-full">
                    MOST POPULAR
                  </div>
                )}
                <div className="mb-6">
                  <h3 className="text-2xl font-bold text-white mb-2">{plan.name}</h3>
                  <p className="text-slate-400 text-sm">{plan.description}</p>
                </div>
                <div className="mb-6">
                  <span className="text-4xl font-bold text-white">BDT {plan.price}</span>
                  <span className="text-slate-400 text-sm">/{plan.period}</span>
                </div>
                <ul className="space-y-3 mb-8">
                  {plan.features.map((feature, index) => (
                    <li key={index} className="flex items-center gap-3 text-slate-300 text-sm">
                      <svg className="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                      </svg>
                      {feature}
                    </li>
                  ))}
                </ul>
                <button 
                  onClick={() => handlePurchasePlan(plan)}
                  className="w-full py-3 ${plan.is_popular ? 'bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700' : 'bg-slate-700 hover:bg-slate-600'} text-white font-semibold rounded-xl transition-all duration-200 shadow-lg"
                >
                  {plan.button_text}
                </button>
              </div>
            ))}
          </div>

          <div className="text-center mt-12">
            <p className="text-yellow-400 text-xl">
              All plans include a 14-day free trial.
            </p>
          </div>
        </div>
      </section>

      {/* Privacy & Terms Section */}
      <section id="privacy" className="relative z-10 py-20 lg:py-32 bg-slate-950/50">
        <div className="max-w-7xl mx-auto px-6 lg:px-12">
          <div className="text-center mb-12">
            <h2 className="text-4xl lg:text-5xl font-bold text-white mb-4">Privacy Policy & Terms of Service</h2>
            <p className="text-slate-400 text-lg">Your privacy is important to us. Learn how we protect your data and understand our terms.</p>
          </div>
          <div className="grid lg:grid-cols-2 gap-8">
            {/* Privacy Policy */}
            <div className="bg-slate-800/50 backdrop-blur-lg border border-slate-700/50 rounded-2xl p-8 space-y-6">
              <h3 className="text-2xl font-bold text-white mb-4">Privacy Policy</h3>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Data Collection</h4>
                <p className="text-slate-400 text-sm leading-relaxed">We collect only the necessary data to provide our POS services, including your business information, transaction records, and customer data. All data is encrypted and stored securely.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Data Usage</h4>
                <p className="text-slate-400 text-sm leading-relaxed">Your data is used solely to operate and improve our services. We never sell or share your data with third parties without your explicit consent.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Security Measures</h4>
                <p className="text-slate-400 text-sm leading-relaxed">We implement industry-standard security protocols including SSL encryption, regular security audits, and access controls to protect your information.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Your Rights</h4>
                <p className="text-slate-400 text-sm leading-relaxed">You have the right to access, modify, or delete your data at any time. Contact our support team for any privacy-related requests.</p>
              </div>
            </div>
            {/* Terms of Service */}
            <div id="terms" className="bg-slate-800/50 backdrop-blur-lg border border-slate-700/50 rounded-2xl p-8 space-y-6">
              <h3 className="text-2xl font-bold text-white mb-4">Terms of Service</h3>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Acceptance of Terms</h4>
                <p className="text-slate-400 text-sm leading-relaxed">By using CodexaaPOS++, you agree to these terms of service. If you do not agree, please do not use our services.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Service Description</h4>
                <p className="text-slate-400 text-sm leading-relaxed">CodexaaPOS++ provides a multi-tenant point of sale system for businesses to manage inventory, sales, customers, and operations.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">User Responsibilities</h4>
                <p className="text-slate-400 text-sm leading-relaxed">Users are responsible for maintaining account security, providing accurate information, and complying with all applicable laws and regulations.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Limitation of Liability</h4>
                <p className="text-slate-400 text-sm leading-relaxed">CodexaaPOS++ shall not be liable for any indirect, incidental, or consequential damages arising from the use of our services.</p>
              </div>
              <div>
                <h4 className="text-lg font-bold text-white mb-2">Termination</h4>
                <p className="text-slate-400 text-sm leading-relaxed">We reserve the right to terminate or suspend access to our services at any time, with or without cause, with or without notice.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Support Section */}
      <section id="support" className="relative z-10 py-20 lg:py-32 bg-slate-950/50">
        <div className="max-w-4xl mx-auto px-6 lg:px-12">
          <div className="text-center mb-12">
            <h2 className="text-4xl lg:text-5xl font-bold text-white mb-4">Support</h2>
            <p className="text-slate-400 text-lg">Get help with CodexaaPOS++ services.</p>
          </div>
          <div className="bg-slate-800/50 backdrop-blur-lg border border-slate-700/50 rounded-2xl p-8 space-y-6">
            <div>
              <h3 className="text-xl font-bold text-white mb-3">Contact Us</h3>
              <p className="text-slate-400 text-sm leading-relaxed">For technical support, billing inquiries, or general questions, please reach out to our support team.</p>
              <div className="mt-4 space-y-2">
                <p className="text-slate-300 text-sm"><span className="font-semibold">Email:</span> mk.rabbani.cse@gmail.com</p>
                <p className="text-slate-300 text-sm"><span className="font-semibold">Phone:</span> +880 1854-718767</p>
              </div>
            </div>
            <div>
              <h3 className="text-xl font-bold text-white mb-3">Documentation</h3>
              <p className="text-slate-400 text-sm leading-relaxed">Access our comprehensive documentation for guides, tutorials, and FAQs to help you make the most of CodexaaPOS++.</p>
            </div>
            <div>
              <h3 className="text-xl font-bold text-white mb-3">Response Time</h3>
              <p className="text-slate-400 text-sm leading-relaxed">Our support team typically responds within 24-48 hours. For urgent issues, please indicate the priority level in your message.</p>
            </div>
            <div>
              <h3 className="text-xl font-bold text-white mb-3">Community</h3>
              <p className="text-slate-400 text-sm leading-relaxed">Join our community forums to connect with other users, share tips, and get help from experienced CodexaaPOS++ users.</p>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="relative z-10 border-t border-slate-800 px-6 py-8 lg:px-12">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-center gap-4">
          <p className="text-slate-500 text-sm">
            CodexaaPOS++ &copy; {new Date().getFullYear()} developed by{' '}
            <a href="https://its-mk.netlify.app/" target="_blank" rel="noopener noreferrer" className="text-indigo-400 hover:text-indigo-300 transition-colors">
              MK
            </a>
          </p>
        </div>
      </footer>

      {/* Purchase Modal */}
      {showPurchaseModal && selectedPlan && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4">
          <div className="bg-slate-800 border border-slate-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-xl font-bold text-white">Purchase Plan</h3>
              <button
                onClick={() => setShowPurchaseModal(false)}
                className="text-slate-400 hover:text-white transition-colors"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            {purchaseSuccess ? (
              <div className="text-center py-8">
                <div className="w-16 h-16 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                  <svg className="w-8 h-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <h4 className="text-lg font-semibold text-white mb-2">Purchase Successful!</h4>
                <p className="text-slate-400 text-sm">Thank you for purchasing {selectedPlan.name} plan.</p>
              </div>
            ) : showPaymentStep ? (
              <form onSubmit={handlePurchaseSubmit} className="space-y-4">
                <div className="bg-slate-700/50 rounded-xl p-4 mb-4">
                  <h4 className="font-semibold text-white mb-1">{selectedPlan.name}</h4>
                  <p className="text-2xl font-bold text-indigo-400">BDT {selectedPlan.price}<span className="text-sm text-slate-400">/{selectedPlan.period}</span></p>
                  <p className="text-slate-400 text-sm mt-1">Payment Method: {selectedPaymentMethod?.name || 'Other'}</p>
                </div>

                {/* Payment Details Display */}
                {selectedPaymentMethod && (
                  <div className="bg-indigo-900/30 border border-indigo-700/50 rounded-xl p-4 mb-4">
                    <h4 className="text-sm font-semibold text-indigo-300 mb-3">Send payment to:</h4>
                    <div className="space-y-2 text-sm">
                      <div className="flex justify-between">
                        <span className="text-slate-400">Method:</span>
                        <span className="text-white font-medium">{selectedPaymentMethod.name}</span>
                      </div>
                      {selectedPaymentMethod.phone_number && (
                        <div className="flex justify-between">
                          <span className="text-slate-400">Number:</span>
                          <span className="text-white font-mono">{selectedPaymentMethod.phone_number}</span>
                        </div>
                      )}
                      {selectedPaymentMethod.account_number && (
                        <div className="flex justify-between">
                          <span className="text-slate-400">Account:</span>
                          <span className="text-white font-mono">{selectedPaymentMethod.account_number}</span>
                        </div>
                      )}
                      {selectedPaymentMethod.account_holder && (
                        <div className="flex justify-between">
                          <span className="text-slate-400">Holder:</span>
                          <span className="text-white">{selectedPaymentMethod.account_holder}</span>
                        </div>
                      )}
                      {selectedPaymentMethod.branch_name && (
                        <div className="flex justify-between">
                          <span className="text-slate-400">Branch:</span>
                          <span className="text-white">{selectedPaymentMethod.branch_name}</span>
                        </div>
                      )}
                    </div>
                  </div>
                )}

                {purchaseError && (
                  <div className="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3 text-rose-300 text-sm">
                    {purchaseError}
                  </div>
                )}

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Transaction ID *</label>
                  <input
                    type="text"
                    required
                    value={paymentDetails.transaction_id}
                    onChange={(e) => setPaymentDetails({...paymentDetails, transaction_id: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Enter transaction ID or reference number"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Payment Proof (Screenshot URL)</label>
                  <input
                    type="text"
                    value={paymentDetails.payment_proof}
                    onChange={(e) => setPaymentDetails({...paymentDetails, payment_proof: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Paste screenshot URL (optional)"
                  />
                </div>

                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={() => setShowPaymentStep(false)}
                    className="flex-1 bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-xl py-3 text-sm transition-colors"
                  >
                    Back
                  </button>
                  <button
                    type="submit"
                    disabled={purchaseLoading}
                    className="flex-1 bg-gradient-to-r from-gray-600 to-gray-600 hover:from-gray-700 hover:to-gray-700 text-white font-semibold rounded-xl py-3 text-sm transition-all duration-200 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {purchaseLoading ? (
                      <span className="flex items-center justify-center gap-2">
                        <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Processing...
                      </span>
                    ) : (
                      `Confirm Payment BDT ${selectedPlan.price}`
                    )}
                  </button>
                </div>
              </form>
            ) : (
              <form onSubmit={handleProceedToPayment} className="space-y-4">
                <div className="bg-slate-700/50 rounded-xl p-4 mb-4">
                  <h4 className="font-semibold text-white mb-1">{selectedPlan.name}</h4>
                  <p className="text-2xl font-bold text-indigo-400">BDT {selectedPlan.price}<span className="text-sm text-slate-400">/{selectedPlan.period}</span></p>
                </div>

                {purchaseError && (
                  <div className="bg-rose-500/10 border border-rose-500/30 rounded-xl p-3 text-rose-300 text-sm">
                    {purchaseError}
                  </div>
                )}

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Full Name *</label>
                  <input
                    type="text"
                    required
                    value={purchaseForm.user_name}
                    onChange={(e) => setPurchaseForm({...purchaseForm, user_name: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Enter your full name"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Email Address *</label>
                  <input
                    type="email"
                    required
                    value={purchaseForm.user_email}
                    onChange={(e) => setPurchaseForm({...purchaseForm, user_email: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Enter your email"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Phone Number</label>
                  <input
                    type="tel"
                    value={purchaseForm.user_phone}
                    onChange={(e) => setPurchaseForm({...purchaseForm, user_phone: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="Enter your phone number"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Payment Method *</label>
                  <select
                    required
                    value={purchaseForm.payment_method}
                    onChange={(e) => setPurchaseForm({...purchaseForm, payment_method: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                  >
                    <option value="">Select a payment method</option>
                    {paymentMethods.length === 0 ? (
                      <option value="cash">Cash</option>
                    ) : (
                      paymentMethods.map((method) => (
                        <option key={method.id} value={method.id}>
                          {method.name} ({method.type === 'mobile_payment' ? 'Mobile' : method.type === 'bank_transfer' ? 'Bank' : 'Card'})
                        </option>
                      ))
                    )}
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-2">Notes (Optional)</label>
                  <textarea
                    value={purchaseForm.notes}
                    onChange={(e) => setPurchaseForm({...purchaseForm, notes: e.target.value})}
                    className="w-full bg-slate-700/50 border border-slate-600 text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    placeholder="Any additional notes..."
                    rows="3"
                  />
                </div>

                <button
                  type="submit"
                  className="w-full bg-gradient-to-r from-gray-600 to-gray-600 hover:from-gray-700 hover:to-gray-700 text-white font-semibold rounded-xl py-3 text-sm transition-all duration-200 shadow-lg"
                >
                  Proceed to Payment
                </button>
              </form>
            )}
          </div>
        </div>
      )}
    </div>
  );
};
