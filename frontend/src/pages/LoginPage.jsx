import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import Button from '../components/ui/Button.jsx'
import ThemeToggle from '../components/ui/ThemeToggle.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { useI18n } from '../context/I18nContext.jsx'
import { useTheme } from '../context/ThemeContext.jsx'

export default function LoginPage() {
  const { login, isAuthenticated } = useAuth()
  const { locale, setLocale, t } = useI18n()
  const { isDark, toggleTheme } = useTheme()
  const [form, setForm] = useState({
    email: '',
    password: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState('')

  if (isAuthenticated) {
    return <Navigate to="/" replace />
  }

  const handleChange = (event) => {
    const { name, value } = event.target
    setForm((current) => ({
      ...current,
      [name]: value,
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setErrorMessage('')

    try {
      await login(form)
    } catch (error) {
      setErrorMessage(
        error?.response?.data?.message ||
          error?.response?.data?.errors?.email?.[0] ||
          'Login failed. Check your credentials and try again.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="dash-shell page-shift flex min-h-screen items-center justify-center px-4 py-10">
      <div className="rise-in w-full max-w-5xl overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white/90 shadow-[0_24px_55px_rgba(17,24,39,0.16)] backdrop-blur">
        <div className="grid lg:grid-cols-[1.15fr_1fr]">
          <section className="hero-grid bg-[radial-gradient(circle_at_top_right,rgba(47,184,137,0.42),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(255,138,91,0.24),transparent_34%),linear-gradient(135deg,#0a1222_0%,#1a2f49_50%,#2a4d63_100%)] px-8 py-8 text-white">
            <p className="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-100">Company Leave Portal</p>
            <h1 className="mt-3 text-4xl font-extrabold tracking-tight">HR Dashboard</h1>
            <p className="mt-2 text-sm text-emerald-50/90">
              Authenticate with Sanctum and manage company vacation workflows.
            </p>

            <div className="mt-5 grid gap-3 text-sm text-slate-100/90">
              <div className="rounded-xl border border-white/15 bg-white/10 px-3 py-2">Multi-level approvals with audit trail</div>
              <div className="rounded-xl border border-white/15 bg-white/10 px-3 py-2">Policy engine by department and seniority</div>
              <div className="rounded-xl border border-white/15 bg-white/10 px-3 py-2">Real-time dashboard with analytics and team calendar</div>
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-3">
              <label className="text-xs font-medium text-emerald-50" htmlFor="login-language">
                {t.language}
              </label>
              <select
                id="login-language"
                value={locale}
                onChange={(event) => setLocale(event.target.value)}
                className="rounded-lg border border-white/20 bg-white/10 px-2 py-1 text-xs text-white outline-none"
              >
                <option value="it" className="text-slate-900">
                  Italiano
                </option>
                <option value="en" className="text-slate-900">
                  English
                </option>
              </select>
              <ThemeToggle
                isDark={isDark}
                onToggle={toggleTheme}
                className="border-white/30 bg-white/10 px-2 py-1 text-xs text-white hover:bg-white/20"
              />
            </div>
          </section>

          <section className="bg-gradient-to-b from-white to-slate-50/80 px-8 py-8">
            <form className="space-y-5" onSubmit={handleSubmit}>
            <div>
              <label className="mb-2 block text-sm font-medium text-slate-700" htmlFor="email">
                Email
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                value={form.email}
                onChange={handleChange}
                placeholder="admin@company.test"
                className="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:-translate-y-0.5 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              />
            </div>

            <div>
              <label className="mb-2 block text-sm font-medium text-slate-700" htmlFor="password">
                Password
              </label>
              <input
                id="password"
                name="password"
                type="password"
                autoComplete="current-password"
                required
                value={form.password}
                onChange={handleChange}
                placeholder="password123"
                className="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none transition focus:-translate-y-0.5 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
              />
            </div>

            {errorMessage ? (
              <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {errorMessage}
              </div>
            ) : null}

            <Button type="submit" disabled={submitting} className="w-full rounded-2xl py-3">
              {submitting ? 'Signing in...' : 'Sign in'}
            </Button>
            </form>

            <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-600">
              <p>Admin: admin@company.test / password123</p>
              <p>HR: hr@company.test / password123</p>
              <p>Manager: manager@company.test / password123</p>
              <p>Employee: employee@company.test / password123</p>
              <p>Employee 2: employee2@company.test / password123</p>
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}