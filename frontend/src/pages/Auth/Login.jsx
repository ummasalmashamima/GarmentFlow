import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import useAuth from '../../hooks/useAuth'

function Login() {
  const { isAuthenticated, loading, login } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (loading) {
    return (
      <div className="auth-page route-loading" role="status">
        Restoring your session…
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

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    // Read straight from the DOM at submit time instead of trusting React
    // state alone. Some browsers' autofill/password managers write the
    // value into the input without firing a React-visible change event,
    // which leaves `form.email`/`form.password` stale or empty even though
    // the field looks filled in — causing a 401 with "different" credentials
    // than what's visibly typed. This guarantees we send what's actually
    // in the fields at submit time. Email is also trimmed defensively
    // (password is sent as-is since passwords are case/space sensitive).
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

  return (
    <main className="auth-page">
      <section className="auth-panel" aria-labelledby="login-title">
        <div className="auth-brand">
          <div className="brand-mark">GF</div>
          <span>GarmentFlow</span>
        </div>
        <p className="eyebrow">Supply chain intelligence</p>
        <h1 id="login-title">Welcome back.</h1>
        <p className="auth-copy">Sign in to access your connected operations workspace.</p>

        <form className="auth-form" onSubmit={handleSubmit}>
          <label htmlFor="email">Work email</label>
          <input
            autoComplete="email"
            id="email"
            name="email"
            onChange={handleChange}
            placeholder="you@company.com"
            required
            type="email"
            value={form.email}
          />

          <label htmlFor="password">Password</label>
          <input
            autoComplete="current-password"
            id="password"
            minLength="8"
            name="password"
            onChange={handleChange}
            required
            type="password"
            value={form.password}
          />

          {error && <p className="form-error" role="alert">{error}</p>}

          <button className="primary-button" disabled={submitting} type="submit">
            {submitting ? 'Signing in…' : 'Sign in'}
          </button>
        </form>
      </section>
      <aside className="auth-aside" aria-label="GarmentFlow platform description">
        <p className="eyebrow">One operating picture</p>
        <h2>From first thread to final payment.</h2>
        <p>Designed to connect the decisions that keep garment supply chains moving.</p>
        <span className="auth-aside-line" />
      </aside>
    </main>
  )
}

export default Login
