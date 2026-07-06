import PropTypes from 'prop-types'

export default function Skeleton({ className = '' }) {
  return <div className={`skeleton ${className}`} aria-hidden="true" />
}

Skeleton.propTypes = {
  className: PropTypes.string,
}
