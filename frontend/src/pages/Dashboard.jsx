import { useEffect, useMemo, useState } from 'react'
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import Badge from '../components/ui/Badge.jsx'
import Button from '../components/ui/Button.jsx'
import Skeleton from '../components/ui/Skeleton.jsx'
import ThemeToggle from '../components/ui/ThemeToggle.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { useI18n } from '../context/I18nContext.jsx'
import { useTheme } from '../context/ThemeContext.jsx'
import api from '../lib/axios.js'

const WEEKDAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const formatCalendarDate = (date) => date.toISOString().slice(0, 10)

const formatDisplayDate = (value, locale) => {
  const date = new Date(value)
  const safeLocale = locale === 'it' ? 'it-IT' : 'en-US'

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat(safeLocale, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

const buildMonthGrid = (monthValue) => {
  const [yearString, monthString] = monthValue.split('-')
  const year = Number(yearString)
  const monthIndex = Number(monthString) - 1

  const firstDay = new Date(year, monthIndex, 1)
  const startOffset = firstDay.getDay()
  const startDate = new Date(year, monthIndex, 1 - startOffset)

  return Array.from({ length: 42 }, (_, index) => {
    const day = new Date(startDate)
    day.setDate(startDate.getDate() + index)

    return {
      date: day,
      key: formatCalendarDate(day),
      inCurrentMonth: day.getMonth() === monthIndex,
    }
  })
}

export default function Dashboard() {
  const { user, logout, refreshUser } = useAuth()
  const { locale, setLocale, t } = useI18n()
  const { isDark, toggleTheme } = useTheme()

  const [leaveRequests, setLeaveRequests] = useState([])
  const [notifications, setNotifications] = useState([])
  const [analytics, setAnalytics] = useState(null)
  const [calendarData, setCalendarData] = useState([])
  const [holidays, setHolidays] = useState([])
  const [policies, setPolicies] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [statusUpdatingId, setStatusUpdatingId] = useState(null)
  const [calendarMonth, setCalendarMonth] = useState(new Date().toISOString().slice(0, 7))
  const [holidayForm, setHolidayForm] = useState({ name: '', date: '' })

  const [requestForm, setRequestForm] = useState({
    start_date: '',
    end_date: '',
    leave_type: 'full_day',
    reason: '',
  })

  const [policyForm, setPolicyForm] = useState({
    department: 'general',
    seniority_min_years: 0,
    max_consecutive_days: 10,
    allow_half_day: true,
    required_approval_level: 1,
    blackout_start_date: '',
    blackout_end_date: '',
  })

  const workflowRole = user?.workflow_role || user?.role || 'employee'
  const approvalLevel = Number(user?.approval_level || 0)
  const canApproveRequests = ['manager', 'hr', 'admin'].includes(workflowRole)
  const canManagePolicies = ['hr', 'admin'].includes(workflowRole)
  const canManageHolidays = ['hr', 'admin'].includes(workflowRole)
  const canViewAnalytics = canApproveRequests
  const canCreateRequests = workflowRole !== 'admin'

  const myRequests = useMemo(
    () => leaveRequests.filter((item) => item.user_id === user?.id || item.user?.id === user?.id),
    [leaveRequests, user?.id],
  )

  const pendingRequests = useMemo(
    () =>
      leaveRequests.filter(
        (item) =>
          item.status === 'pending' &&
          item.user_id !== user?.id &&
          item.user?.id !== user?.id &&
          item.current_approval_level + 1 <= approvalLevel &&
          item.current_approval_level < item.required_approval_level,
      ),
    [approvalLevel, leaveRequests, user?.id],
  )

  const monthlyTrendChart = useMemo(() => {
    if (!analytics?.monthly_trend) {
      return []
    }

    return analytics.monthly_trend.map((item) => ({
      month: item.month?.slice(5) || item.month,
      approved: item.approved_requests,
      total: item.total_requests,
    }))
  }, [analytics])

  const calendarGrid = useMemo(() => buildMonthGrid(calendarMonth), [calendarMonth])

  const leavesByDate = useMemo(() => {
    const map = new Map()

    calendarData.forEach((entry) => {
      const start = new Date(`${entry.start_date}T00:00:00`)
      const end = new Date(`${entry.end_date}T00:00:00`)
      const cursor = new Date(start)

      while (cursor <= end) {
        const key = formatCalendarDate(cursor)
        const current = map.get(key) || []
        current.push(entry.user?.name || 'Employee')
        map.set(key, current)
        cursor.setDate(cursor.getDate() + 1)
      }
    })

    return map
  }, [calendarData])

  const holidaysByDate = useMemo(() => {
    const map = new Map()
    holidays.forEach((holiday) => {
      map.set(holiday.date, holiday)
    })
    return map
  }, [holidays])

  const loadRequests = async () => {
    const response = await api.get('/api/leave-requests', {
      params: canApproveRequests ? { pending_only: false } : {},
    })
    setLeaveRequests(response.data.data)
  }

  const loadAnalytics = async () => {
    if (!canViewAnalytics) {
      return
    }

    const response = await api.get('/api/dashboard/analytics')
    setAnalytics(response.data)
  }

  const loadCalendar = async () => {
    const response = await api.get('/api/dashboard/calendar', {
      params: { month: calendarMonth },
    })
    setCalendarData(response.data.data)
  }

  const loadNotifications = async () => {
    const response = await api.get('/api/notifications')
    setNotifications(response.data.data)
  }

  const loadPolicies = async () => {
    if (!canManagePolicies) {
      return
    }

    const response = await api.get('/api/policies')
    setPolicies(response.data.data)
  }

  const loadHolidays = async () => {
    const response = await api.get('/api/holidays')
    setHolidays(response.data.data)
  }

  useEffect(() => {
    let active = true

    const bootstrap = async () => {
      try {
        const requestsPromise = api.get('/api/leave-requests')
        const calendarPromise = api.get('/api/dashboard/calendar', {
          params: { month: calendarMonth },
        })
        const notificationsPromise = api.get('/api/notifications')
        const holidaysPromise = api.get('/api/holidays')
        const analyticsPromise = canViewAnalytics ? api.get('/api/dashboard/analytics') : Promise.resolve(null)
        const policiesPromise = canManagePolicies ? api.get('/api/policies') : Promise.resolve(null)

        const [requestsResponse, calendarResponse, notificationsResponse, holidaysResponse, analyticsResponse, policiesResponse] =
          await Promise.all([
            requestsPromise,
            calendarPromise,
            notificationsPromise,
            holidaysPromise,
            analyticsPromise,
            policiesPromise,
          ])

        if (!active) {
          return
        }

        setLeaveRequests(requestsResponse.data.data)
        setCalendarData(calendarResponse.data.data)
        setNotifications(notificationsResponse.data.data)
        setHolidays(holidaysResponse.data.data)
        setAnalytics(analyticsResponse?.data ?? null)
        setPolicies(policiesResponse?.data?.data ?? [])
      } catch (error) {
        if (active) {
          setErrorMessage(error?.response?.data?.message || 'Unable to load dashboard data.')
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void bootstrap()

    return () => {
      active = false
    }
  }, [calendarMonth, canViewAnalytics, canManagePolicies, canApproveRequests])

  const handleRequestChange = (event) => {
    const { name, value } = event.target
    setRequestForm((current) => ({
      ...current,
      [name]: value,
    }))
  }

  const handlePolicyChange = (event) => {
    const { name, type, value, checked } = event.target

    setPolicyForm((current) => ({
      ...current,
      [name]: type === 'checkbox' ? checked : value,
    }))
  }

  const handleHolidayChange = (event) => {
    const { name, value } = event.target
    setHolidayForm((current) => ({
      ...current,
      [name]: value,
    }))
  }

  const handleCreateRequest = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setMessage('')
    setErrorMessage('')

    try {
      await api.post('/api/leave-requests', requestForm)
      setRequestForm({
        start_date: '',
        end_date: '',
        leave_type: 'full_day',
        reason: '',
      })
      setMessage('Leave request submitted successfully.')
      await Promise.all([loadRequests(), refreshUser(), loadNotifications()])
    } catch (error) {
      setErrorMessage(
        error?.response?.data?.message ||
          Object.values(error?.response?.data?.errors || {}).flat()[0] ||
          'Unable to create leave request.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  const handleStatusUpdate = async (leaveRequestId, status) => {
    const adminNote = window.prompt(t.adminNote)
    setStatusUpdatingId(leaveRequestId)
    setMessage('')
    setErrorMessage('')

    try {
      await api.patch(`/api/leave-requests/${leaveRequestId}/status`, {
        status,
        admin_note: adminNote || undefined,
      })
      setMessage(`Request ${status} successfully.`)
      await Promise.all([loadRequests(), refreshUser(), loadNotifications(), loadAnalytics(), loadCalendar()])
    } catch (error) {
      setErrorMessage(
        error?.response?.data?.message ||
          Object.values(error?.response?.data?.errors || {}).flat()[0] ||
          'Unable to update leave request.',
      )
    } finally {
      setStatusUpdatingId(null)
    }
  }

  const handleMarkAllRead = async () => {
    try {
      await api.post('/api/notifications/mark-all-read')
      await loadNotifications()
    } catch (error) {
      setErrorMessage(error?.response?.data?.message || 'Unable to mark notifications as read.')
    }
  }

  const handlePolicySubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setErrorMessage('')
    setMessage('')

    try {
      await api.post('/api/policies', {
        ...policyForm,
        seniority_min_years: Number(policyForm.seniority_min_years),
        max_consecutive_days: Number(policyForm.max_consecutive_days),
        required_approval_level: Number(policyForm.required_approval_level),
        blackout_start_date: policyForm.blackout_start_date || null,
        blackout_end_date: policyForm.blackout_end_date || null,
      })

      setMessage('Policy saved successfully.')
      await loadPolicies()
    } catch (error) {
      setErrorMessage(
        error?.response?.data?.message ||
          Object.values(error?.response?.data?.errors || {}).flat()[0] ||
          'Unable to save policy.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  const handleHolidaySubmit = async (event) => {
    event.preventDefault()
    setSubmitting(true)
    setErrorMessage('')
    setMessage('')

    try {
      await api.post('/api/holidays', holidayForm)
      setHolidayForm({ name: '', date: '' })
      setMessage('Holiday saved successfully.')
      await loadHolidays()
    } catch (error) {
      setErrorMessage(
        error?.response?.data?.message ||
          Object.values(error?.response?.data?.errors || {}).flat()[0] ||
          'Unable to save holiday.',
      )
    } finally {
      setSubmitting(false)
    }
  }

  const handleLogout = async () => {
    await logout()
  }

  return (
    <div className="dash-shell page-shift min-h-screen px-4 py-6 sm:px-6 lg:px-8">
      <div className="mx-auto max-w-7xl space-y-6">
        <header className="hero-grid rise-in overflow-hidden rounded-[2rem] bg-slate-950 text-white shadow-[0_30px_60px_rgba(2,6,23,0.35)]">
          <div className="bg-[radial-gradient(circle_at_top_right,rgba(47,184,137,0.45),transparent_34%),radial-gradient(circle_at_bottom_left,rgba(255,138,91,0.28),transparent_33%),linear-gradient(132deg,#0b1223_0%,#13203d_52%,#1d3a53_100%)] px-6 py-8 sm:px-8">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.35em] text-emerald-100/90">{t.dashboard}</p>
                <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-5xl">Welcome, {user?.name}</h1>
                <p className="mt-3 text-sm text-slate-200">
                  Role: <span className="font-semibold capitalize text-white">{workflowRole}</span>
                </p>
              </div>

              <div className="flex flex-wrap items-center gap-3">
                <label className="text-sm font-medium text-slate-200" htmlFor="language-switch">
                  {t.language}
                </label>
                <select
                  id="language-switch"
                  value={locale}
                  onChange={(event) => setLocale(event.target.value)}
                  className="rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-sm text-white outline-none ring-0 transition focus:border-white/40"
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
                  className="border-white/20 bg-white/10 text-white hover:bg-white/20"
                />
                <Button
                  variant="ghost"
                  className="rounded-2xl border-white/20 bg-white/10 text-white hover:bg-white/20"
                  onClick={handleLogout}
                >
                  Logout
                </Button>
              </div>
            </div>
          </div>
        </header>

        <section className="glass-card rise-in rounded-[2rem] p-5" style={{ animationDelay: '50ms' }}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Session status</p>
              <p className="mt-1 text-sm text-slate-700">
                Current role: <span className="font-semibold capitalize text-[var(--ink-900)]">{workflowRole}</span>
              </p>
            </div>
            <Badge tone={canApproveRequests ? 'success' : 'warning'}>
              {canApproveRequests ? 'Approver mode enabled' : 'Approver mode disabled'}
            </Badge>
          </div>
          {!canApproveRequests ? (
            <p className="mt-3 text-sm text-amber-700">
              Approver sections are hidden for this account. Login with <strong>manager@company.test</strong>,
              <strong> hr@company.test</strong> or <strong>admin@company.test</strong> to view approvals and analytics.
            </p>
          ) : null}
        </section>

        {message ? (
          <div className="rise-in rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-700" style={{ animationDelay: '70ms' }}>
            {message}
          </div>
        ) : null}

        {errorMessage ? (
          <div className="rise-in rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-sm text-rose-700" style={{ animationDelay: '70ms' }}>
            {errorMessage}
          </div>
        ) : null}

        <section className="glass-card rise-in rounded-[2rem] p-6" style={{ animationDelay: '90ms' }}>
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-xl font-semibold text-slate-900">{t.notifications}</h2>
            <Button
              variant="ghost"
              onClick={handleMarkAllRead}
              className="bg-white px-3 py-2 text-xs uppercase tracking-wide text-slate-700"
            >
              {t.markAllRead}
            </Button>
          </div>

          <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            {notifications.length === 0 ? (
              <p className="text-sm text-slate-500">No notifications.</p>
            ) : (
              notifications.slice(0, 6).map((notification) => (
                <article key={notification.id} className="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                  <p className="text-xs uppercase tracking-wide text-slate-500">{notification.type.split('\\').pop()}</p>
                  <p className="mt-2 text-sm text-slate-700">{notification.data?.status || 'Update available'}</p>
                </article>
              ))
            )}
          </div>
        </section>

        {canViewAnalytics ? (
          <section className="rise-in grid gap-6 lg:grid-cols-2" style={{ animationDelay: '130ms' }}>
            <article className="glass-card rounded-[2rem] p-6">
              <h2 className="text-xl font-semibold text-slate-900">{t.analytics}</h2>

              {analytics ? (
                <>
                  <div className="mt-4 grid grid-cols-2 gap-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <p className="text-xs uppercase text-slate-500">Total</p>
                      <p className="mt-1 text-2xl font-extrabold text-slate-900">{analytics.kpis.total_requests}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <p className="text-xs uppercase text-slate-500">Pending</p>
                      <p className="mt-1 text-2xl font-extrabold text-slate-900">{analytics.kpis.pending_requests}</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <p className="text-xs uppercase text-slate-500">Approval rate</p>
                      <p className="mt-1 text-2xl font-extrabold text-slate-900">{analytics.kpis.approval_rate}%</p>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                      <p className="text-xs uppercase text-slate-500">Avg days</p>
                      <p className="mt-1 text-2xl font-extrabold text-slate-900">{analytics.kpis.average_requested_days}</p>
                    </div>
                  </div>

                  <div className="mt-6 h-64 rounded-2xl border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-2 shadow-sm">
                    <ResponsiveContainer width="100%" height="100%">
                      <BarChart data={monthlyTrendChart}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                        <XAxis dataKey="month" />
                        <YAxis allowDecimals={false} />
                        <Tooltip />
                        <Bar dataKey="total" fill="#0f172a" radius={[4, 4, 0, 0]} />
                        <Bar dataKey="approved" fill="#059669" radius={[4, 4, 0, 0]} />
                      </BarChart>
                    </ResponsiveContainer>
                  </div>
                </>
              ) : (
                <div className="mt-4 space-y-3">
                  <Skeleton className="h-10 w-full" />
                  <Skeleton className="h-10 w-4/5" />
                  <Skeleton className="h-52 w-full" />
                </div>
              )}
            </article>

            <article className="glass-card rounded-[2rem] p-6">
              <h2 className="text-xl font-semibold text-slate-900">{t.teamCalendar}</h2>
              <div className="mt-4">
                <input
                  type="month"
                  value={calendarMonth}
                  onChange={(event) => setCalendarMonth(event.target.value)}
                  className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-emerald-500"
                />
              </div>

              <div className="mt-4 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                {WEEKDAY_LABELS.map((label) => (
                  <div key={label}>{label}</div>
                ))}
              </div>

              <div className="mt-2 grid grid-cols-7 gap-2">
                {calendarGrid.map((day) => {
                  const dayLeaves = leavesByDate.get(day.key) || []
                  const holiday = holidaysByDate.get(day.key)

                  return (
                    <div
                      key={day.key}
                      className={`min-h-20 rounded-xl border p-2 text-left ${
                        day.inCurrentMonth
                          ? 'border-slate-200 bg-white shadow-[0_2px_10px_rgba(15,23,42,0.03)]'
                          : 'border-slate-100 bg-slate-50 text-slate-400'
                      }`}
                    >
                      <p className="text-xs font-semibold">{day.date.getDate()}</p>
                      {holiday ? <p className="mt-1 text-[10px] font-semibold text-amber-600">{holiday.name}</p> : null}
                      {dayLeaves.slice(0, 2).map((name, index) => (
                        <p
                          key={`${day.key}-${name}-${index}`}
                          className="mt-1 truncate rounded bg-gradient-to-r from-emerald-50 to-teal-50 px-1 py-0.5 text-[10px] text-emerald-700"
                        >
                          {name}
                        </p>
                      ))}
                      {dayLeaves.length > 2 ? (
                        <p className="mt-1 text-[10px] text-slate-500">+{dayLeaves.length - 2} more</p>
                      ) : null}
                    </div>
                  )
                })}
              </div>
            </article>
          </section>
        ) : null}

        {canCreateRequests ? (
          <section className="mt-6 grid gap-6 lg:grid-cols-[360px_minmax(0,1fr)]">
            <article className="rounded-[2rem] bg-white/90 p-6 shadow-xl ring-1 ring-slate-200 backdrop-blur">
              <h2 className="text-xl font-semibold text-slate-900">Leave balance</h2>
              <p className="mt-4 text-5xl font-bold text-emerald-600">{user?.available_leave_days}</p>
              <p className="mt-2 text-sm text-slate-500">Carry-over: {user?.carry_over_leave_days ?? 0}</p>

              <form className="mt-8 space-y-4" onSubmit={handleCreateRequest}>
                <h3 className="text-lg font-semibold text-slate-900">Request leave</h3>

                <div>
                  <label className="mb-2 block text-sm font-medium text-slate-700" htmlFor="start_date">
                    Start date
                  </label>
                  <input
                    id="start_date"
                    name="start_date"
                    type="date"
                    required
                    value={requestForm.start_date}
                    onChange={handleRequestChange}
                    className="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  />
                </div>

                <div>
                  <label className="mb-2 block text-sm font-medium text-slate-700" htmlFor="end_date">
                    End date
                  </label>
                  <input
                    id="end_date"
                    name="end_date"
                    type="date"
                    required
                    value={requestForm.end_date}
                    onChange={handleRequestChange}
                    className="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  />
                </div>

                <div>
                  <label className="mb-2 block text-sm font-medium text-slate-700" htmlFor="leave_type">
                    {t.leaveType}
                  </label>
                  <select
                    id="leave_type"
                    name="leave_type"
                    value={requestForm.leave_type}
                    onChange={handleRequestChange}
                    className="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  >
                    <option value="full_day">{t.fullDay}</option>
                    <option value="half_day">{t.halfDay}</option>
                  </select>
                </div>

                <div>
                  <label className="mb-2 block text-sm font-medium text-slate-700" htmlFor="reason">
                    Reason
                  </label>
                  <textarea
                    id="reason"
                    name="reason"
                    rows="4"
                    value={requestForm.reason}
                    onChange={handleRequestChange}
                    placeholder="Optional note for HR"
                    className="w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                  />
                </div>

                <button
                  type="submit"
                  disabled={submitting}
                  className="w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-70"
                >
                  {submitting ? 'Submitting...' : 'Submit request'}
                </button>
              </form>
            </article>

            <article className="rounded-[2rem] bg-white/90 p-6 shadow-xl ring-1 ring-slate-200 backdrop-blur">
              <h2 className="text-xl font-semibold text-slate-900">My requests</h2>

              {loading ? (
                <p className="mt-6 text-sm text-slate-500">Loading requests...</p>
              ) : myRequests.length === 0 ? (
                <p className="mt-6 text-sm text-slate-500">No leave requests found.</p>
              ) : (
                <div className="mt-6 space-y-3">
                  {myRequests.map((leaveRequest) => (
                    <div key={leaveRequest.id} className="rounded-2xl border border-slate-200 p-4">
                      <div className="flex items-center justify-between">
                        <p className="text-sm font-semibold text-slate-800">
                          {formatDisplayDate(leaveRequest.start_date, locale)} - {formatDisplayDate(leaveRequest.end_date, locale)}
                        </p>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
                          {leaveRequest.status}
                        </span>
                      </div>
                      <p className="mt-2 text-xs uppercase tracking-wide text-slate-500">
                        {leaveRequest.leave_type} • {leaveRequest.total_days} days • level {leaveRequest.current_approval_level}/
                        {leaveRequest.required_approval_level}
                      </p>
                      <p className="mt-1 text-sm text-slate-600">{leaveRequest.reason || '-'}</p>
                    </div>
                  ))}
                </div>
              )}
            </article>
          </section>
        ) : null}

        {canApproveRequests ? (
          <section className="glass-card rise-in rounded-[2rem] p-6" style={{ animationDelay: '180ms' }}>
            <h2 className="text-xl font-semibold text-slate-900">Pending leave requests</h2>
            <p className="mt-1 text-sm text-slate-500">Approve or reject employee vacation requests based on your approval level.</p>

            {loading ? (
              <div className="mt-6 space-y-3">
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-full" />
                <Skeleton className="h-10 w-2/3" />
              </div>
            ) : pendingRequests.length === 0 ? (
              <p className="mt-6 text-sm text-slate-500">No pending requests.</p>
            ) : (
              <div className="mt-6 overflow-hidden rounded-3xl border border-slate-200 bg-white">
                <table className="min-w-full divide-y divide-slate-200">
                  <thead className="bg-slate-50/90">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Employee
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Period
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Type
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Approval
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200 bg-white">
                    {pendingRequests.map((leaveRequest) => (
                      <tr key={leaveRequest.id}>
                        <td className="px-4 py-4 text-sm text-slate-700">
                          <div className="font-medium">{leaveRequest.user.name}</div>
                          <div className="text-slate-500">{leaveRequest.user.email}</div>
                        </td>
                        <td className="px-4 py-4 text-sm text-slate-700">
                          {formatDisplayDate(leaveRequest.start_date, locale)} - {formatDisplayDate(leaveRequest.end_date, locale)}
                        </td>
                        <td className="px-4 py-4 text-sm text-slate-700">
                          {leaveRequest.leave_type} ({leaveRequest.total_days})
                        </td>
                        <td className="px-4 py-4 text-sm text-slate-700">
                          {leaveRequest.current_approval_level}/{leaveRequest.required_approval_level}
                        </td>
                        <td className="px-4 py-4 text-sm">
                          <div className="flex gap-2">
                            <Button
                              variant="success"
                              onClick={() => handleStatusUpdate(leaveRequest.id, 'approved')}
                              disabled={statusUpdatingId === leaveRequest.id}
                              className="px-3 py-2"
                            >
                              Approve
                            </Button>
                            <Button
                              variant="danger"
                              onClick={() => handleStatusUpdate(leaveRequest.id, 'rejected')}
                              disabled={statusUpdatingId === leaveRequest.id}
                              className="px-3 py-2"
                            >
                              Reject
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        ) : null}

        {canManagePolicies ? (
          <section className="glass-card rise-in rounded-[2rem] p-6" style={{ animationDelay: '220ms' }}>
            <h2 className="text-xl font-semibold text-slate-900">{t.policyManager}</h2>
            <form className="mt-4 grid gap-4 md:grid-cols-2" onSubmit={handlePolicySubmit}>
              <input
                name="department"
                value={policyForm.department}
                onChange={handlePolicyChange}
                placeholder="department"
                className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
              />
              <input
                name="seniority_min_years"
                type="number"
                min="0"
                value={policyForm.seniority_min_years}
                onChange={handlePolicyChange}
                placeholder="seniority min years"
                className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
              />
              <input
                name="max_consecutive_days"
                type="number"
                min="1"
                value={policyForm.max_consecutive_days}
                onChange={handlePolicyChange}
                placeholder="max consecutive days"
                className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
              />
              <input
                name="required_approval_level"
                type="number"
                min="1"
                max="5"
                value={policyForm.required_approval_level}
                onChange={handlePolicyChange}
                placeholder="required approval level"
                className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
              />
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  name="allow_half_day"
                  type="checkbox"
                  checked={policyForm.allow_half_day}
                  onChange={handlePolicyChange}
                />
                Allow half day
              </label>
              <div className="grid grid-cols-2 gap-2">
                <input
                  name="blackout_start_date"
                  type="date"
                  value={policyForm.blackout_start_date}
                  onChange={handlePolicyChange}
                  className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
                />
                <input
                  name="blackout_end_date"
                  type="date"
                  value={policyForm.blackout_end_date}
                  onChange={handlePolicyChange}
                  className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
                />
              </div>
              <Button type="submit" disabled={submitting} className="rounded-xl px-4 py-2">
                Save policy
              </Button>
            </form>

            <div className="mt-6 space-y-2">
              {policies.map((policy) => (
                <div
                  key={`${policy.department}-${policy.seniority_min_years}`}
                  className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                >
                  <span className="font-semibold">{policy.department}</span> - seniority {policy.seniority_min_years}+ - max {policy.max_consecutive_days}d - approval level {policy.required_approval_level}
                </div>
              ))}
            </div>
          </section>
        ) : null}

        {canManageHolidays ? (
          <section className="glass-card rise-in rounded-[2rem] p-6" style={{ animationDelay: '260ms' }}>
            <h2 className="text-xl font-semibold text-slate-900">Company holidays</h2>
            <form className="mt-4 grid gap-3 md:grid-cols-[1fr_220px_auto]" onSubmit={handleHolidaySubmit}>
              <input
                name="name"
                value={holidayForm.name}
                onChange={handleHolidayChange}
                placeholder="Holiday name"
                required
                className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
              />
              <input
                name="date"
                type="date"
                value={holidayForm.date}
                onChange={handleHolidayChange}
                required
                className="rounded-xl border border-slate-300 bg-white px-3 py-2 outline-none transition focus:border-emerald-500"
              />
              <Button type="submit" disabled={submitting} className="rounded-xl px-4 py-2">
                Add holiday
              </Button>
            </form>

            <div className="mt-4 grid gap-2 md:grid-cols-2">
              {holidays.length === 0 ? (
                <p className="text-sm text-slate-500">No holidays configured.</p>
              ) : (
                holidays.map((holiday) => (
                  <div key={holiday.id} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    <span className="font-semibold">{holiday.name}</span> - {formatDisplayDate(holiday.date, locale)}
                  </div>
                ))
              )}
            </div>
          </section>
        ) : null}
      </div>
    </div>
  )
}
