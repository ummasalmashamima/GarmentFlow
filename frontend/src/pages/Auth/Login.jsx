import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import useAuth from '../../hooks/useAuth'

function Login() {
  const { isAuthenticated, loading, login } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (loading) {
    return (
      <div className="auth-page route-loading" role="status">
        <div className="loading-spinner" />
        <span>Restoring secure session…</span>
      </div>
    )
  }

  if (isAuthenticated) {
    return <Navigate replace to="/" />
  }

  const handleChange = (event) => {
    setForm((current) => ({ ...current, [event.target.name]: event.target.value }))
    setError('')
  }

  const handleDemoFill = (email) => {
    setForm({ email, password: 'password' })
    setError('')
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    const submittedForm = {
      email: (event.target.email?.value ?? form.email).trim(),
      password: event.target.password?.value ?? form.password,
    }

    try {
      await login(submittedForm)
      const destination = location.state?.from?.pathname || '/'
      navigate(destination, { replace: true })
    } catch (requestError) {
      setError(
        requestError.response?.data?.message || 'Unable to sign in. Check your details and try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  const demoAccounts = [
    { label: 'Executive / CEO', email: 'ceo@garmentflow.com', role: 'Enterprise Overview', badge: 'CEO' },
    { label: 'Supply Chain Mgr', email: 'supplychain@garmentflow.com', role: 'Demand & MRP', badge: 'SCM' },
    { label: 'Production Mgr', email: 'production@garmentflow.com', role: 'Shop Floor & WIP', badge: 'PROD' },
    { label: 'Procurement Mgr', email: 'procurement@garmentflow.com', role: 'Suppliers & POs', badge: 'PROC' },
    { label: 'Warehouse Mgr', email: 'warehouse@garmentflow.com', role: 'Stock & Balances', badge: 'WH' },
    { label: 'Administrator', email: 'admin@garmentflow.com', role: 'System Admin', badge: 'ADMIN' },
  ]

  return (
    <main className="auth-page">
      {/* Left Panel: Form & Demo Switcher */}
      <section className="auth-panel" aria-labelledby="login-title">
        <div className="auth-brand">
          <div className="brand-mark">GF</div>
          <span className="brand-text">GarmentFlow</span>
        </div>

        <div className="auth-header-block">
          <p className="eyebrow">Enterprise Control Center</p>
          <h1 id="login-title">Welcome back.</h1>
          <p className="auth-copy">Sign in to your garments supply chain decision support platform.</p>
        </div>

        {error && (
          <div className="auth-error-banner" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="8" x2="12" y2="12" />
              <line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
            <span>{error}</span>
          </div>
        )}

        <form className="auth-form" onSubmit={handleSubmit}>
          {/* Work Email Input Field */}
          <div className="auth-input-group">
            <label className="auth-field-label" htmlFor="email">
              Work Email Address
            </label>
            <div className="auth-input-wrapper">
              <span className="input-prefix-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect width="20" height="16" x="2" y="4" rx="2" />
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                </svg>
              </span>
              <input
                autoComplete="email"
                className="auth-input"
                id="email"
                name="email"
                onChange={handleChange}
                placeholder="name@garmentflow.com"
                required
                type="email"
                value={form.email}
              />
            </div>
          </div>

          {/* Password Input Field */}
          <div className="auth-input-group">
            <div className="auth-label-row">
              <label className="auth-field-label" htmlFor="password">
                Password
              </label>
            </div>
            <div className="auth-input-wrapper">
              <span className="input-prefix-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </span>
              <input
                autoComplete="current-password"
                className="auth-input"
                id="password"
                name="password"
                onChange={handleChange}
                placeholder="Enter your password"
                required
                type={showPassword ? 'text' : 'password'}
                value={form.password}
              />
              <button
                className="password-toggle-btn"
                onClick={() => setShowPassword(!showPassword)}
                type="button"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
              >
                {showPassword ? (
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                    <line x1="2" y1="2" x2="22" y2="22" />
                  </svg>
                ) : (
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                )}
              </button>
            </div>
          </div>

          <button
            className="auth-submit-button"
            disabled={submitting}
            type="submit"
          >
            {submitting ? (
              <span className="btn-loading-state">
                <span className="spinner-dot" /> Authenticating…
              </span>
            ) : (
              'Sign in to Workspace →'
            )}
          </button>
        </form>

        {/* 1-Click Role Login Demo Bar */}
        <div className="demo-account-section">
          <div className="demo-account-header">
            <span className="demo-badge">DEMO</span>
            <p className="demo-account-title">Instant 1-Click Role Switcher</p>
          </div>
          <div className="demo-btn-grid">
            {demoAccounts.map((acc) => (
              <button
                className="demo-role-card"
                key={acc.email}
                onClick={() => handleDemoFill(acc.email)}
                type="button"
              >
                <div className="demo-role-header">
                  <span className="demo-role-name">{acc.label}</span>
                  <span className="demo-role-tag">{acc.badge}</span>
                </div>
                <span className="demo-role-desc">{acc.role}</span>
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* Right Hero Showcase */}
      <aside className="auth-aside" aria-label="GarmentFlow platform description">
        <div className="hero-pill-badge">
          <span className="hero-pulse-dot" />
          Connected ERP & Supply Chain Intelligence
        </div>
        <h2>Real-Time Intelligence from Yarn to Final Payment.</h2>
        <p className="hero-desc">
          Seamlessly connecting Buyer Orders, Demand Forecasting, Supply Planning, MRP, Material Availability,
          Procurement, Production Floor WIP, Finished Goods, Sales, and Invoicing in a unified single pane of glass.
        </p>

        <div className="hero-kpi-grid">
          <div className="hero-kpi-card">
            <strong className="hero-kpi-val val-emerald">11 Steps</strong>
            <span className="hero-kpi-lbl">End-to-End Workflow</span>
          </div>
          <div className="hero-kpi-card">
            <strong className="hero-kpi-val val-blue">5 Roles</strong>
            <span className="hero-kpi-lbl">Dedicated Dashboards</span>
          </div>
          <div className="hero-kpi-card">
            <strong className="hero-kpi-val val-indigo">10 Reports</strong>
            <span className="hero-kpi-lbl">Real-Time Financials</span>
          </div>
        </div>
      </aside>
    </main>
  )
}

export default Login
