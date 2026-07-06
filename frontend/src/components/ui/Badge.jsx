import PropTypes from 'prop-types'

const toneMap = {
  neutral: 'bg-slate-100 text-slate-700',
  success: 'bg-emerald-100 text-emerald-700',
  warning: 'bg-amber-100 text-amber-700',
  danger: 'bg-rose-100 text-rose-700',
}

export default function Badge({ tone = 'neutral', children }) {
  return <span className={`rounded-full px-3 py-1 text-xs font-semibold ${toneMap[tone]}`}>{children}</span>
}

Badge.propTypes = {
  tone: PropTypes.oneOf(['neutral', 'success', 'warning', 'danger']),
  children: PropTypes.node.isRequired,
}
