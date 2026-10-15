import { useEffect, useState } from 'react'
import { apiRequest } from './lib/api.js'
import { getIdentity, track, trackPageView } from './lib/analytics.js'

const money = (cents) => `$${(Number(cents) / 100).toFixed(2)}`
const isoDay = (date) => date.toISOString().slice(0, 10)
const initialDashboardTo = isoDay(new Date())
const initialDashboardFrom = isoDay(new Date(Date.now() - 6 * 86400000))

function Header({ dashboard = false }) {
  return (
    <header className="topbar">
      <a className="wordmark" href="/" aria-label="Good Table home"><span className="wordmark-mark">g</span> good table</a>
      <a className="top-link" href={dashboard ? '/' : '/dashboard'}>
        {dashboard ? '← Back to the restaurant' : <>Analytics <span aria-hidden="true">↗</span></>}
      </a>
    </header>
  )
}

function Restaurant() {
  const [menu, setMenu] = useState([])
  const [cart, setCart] = useState({})
  const [message, setMessage] = useState('')
  const [loadingMenu, setLoadingMenu] = useState(true)
  const [placingOrder, setPlacingOrder] = useState(false)

  useEffect(() => {
    let active = true
    apiRequest('/api/menu')
      .then((data) => { if (active) setMenu(data.items) })
      .catch((error) => { if (active) setMessage(error.message) })
      .finally(() => { if (active) setLoadingMenu(false) })
    return () => { active = false }
  }, [])

  const addItem = (item) => {
    setCart((current) => ({ ...current, [item.id]: (current[item.id] ?? 0) + 1 }))
    setMessage('')
    void track('menu_item_added', { item_id: item.id, item_name: item.name })
  }

  const removeItem = (id) => setCart((current) => {
    const next = { ...current }
    if (next[id] <= 1) delete next[id]
    else next[id] -= 1
    return next
  })

  const lines = Object.entries(cart).map(([id, quantity]) => ({ item: menu.find((item) => item.id === id), quantity })).filter((line) => line.item)
  const itemCount = lines.reduce((total, line) => total + line.quantity, 0)
  const total = lines.reduce((sum, line) => sum + line.item.price_cents * line.quantity, 0)

  const placeOrder = async () => {
    setPlacingOrder(true)
    setMessage('Sending your order…')
    try {
      const identity = getIdentity()
      const result = await apiRequest('/api/orders', {
        method: 'POST',
        body: JSON.stringify({
          lines: lines.map(({ item, quantity }) => ({ id: item.id, quantity })),
          visitor_id: identity.visitor_id,
          session_id: identity.session_id,
        }),
      })
      setCart({})
      setMessage(`Order received. That's ${money(result.total_cents)} well spent.`)
      void track('order_confirmation_viewed', { order_id: result.id })
    } catch (error) {
      setMessage(error.message)
    } finally {
      setPlacingOrder(false)
    }
  }

  return (
    <>
      <Header />
      <main className="shop-layout">
        <section className="intro">
          <div className="intro-copy-block">
            <p className="eyebrow">A neighborhood kitchen · Open today</p>
            <h1>Good food.<br /><em>No big occasion.</em></h1>
            <p className="intro-copy">A few things we love making, ready when you are.</p>
            <div className="today-note"><span className="note-dot" /> Kitchen is taking orders</div>
          </div>
          <figure className="hero-image">
            <img src={menu[0]?.image ?? 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?auto=format&fit=crop&w=900&q=85'} alt="Fresh pasta with tomato and herbs" />
            <figcaption>Made here, served with care.</figcaption>
          </figure>
        </section>
        <section className="menu-section" aria-labelledby="menu-title">
          <div className="section-heading"><div><p className="eyebrow">Seasonal things, made simply</p><h2 id="menu-title">Today's menu</h2></div><span className="menu-count">{menu.length} GOOD THINGS</span></div>
          <div className="menu-list" aria-live="polite">
            {loadingMenu && <p className="empty-state">Opening the kitchen…</p>}
            {!loadingMenu && menu.map((item, index) => (
              <article className="menu-item" key={item.id} style={{ '--item-order': index }}>
                <img className="dish-image" src={item.image} alt={item.name} loading="lazy" />
                <div className="dish-info"><span className="dish-category">{item.category}</span><h3>{item.name}</h3><p>{item.description}</p></div>
                <div className="dish-action"><strong>{money(item.price_cents)}</strong><button className="add-button" type="button" title={`Add ${item.name}`} aria-label={`Add ${item.name}`} onClick={() => addItem(item)}>+</button></div>
              </article>
            ))}
            {!loadingMenu && menu.length === 0 && <p className="empty-state">{message || 'The menu is taking a moment. Please refresh to try again.'}</p>}
          </div>
        </section>
        <aside className="order-panel" aria-labelledby="order-title">
          <div className="order-head"><div><p className="eyebrow">Your table</p><h2 id="order-title">Your order</h2></div><span className="cart-count">{itemCount}</span></div>
          <div className="cart-lines">
            {lines.length === 0 && <p className="empty-cart">A good meal starts with one thing.</p>}
            {lines.map(({ item, quantity }) => (
              <div className="cart-line" key={item.id}>
                <div><strong>{item.name}</strong><span>{quantity} × {money(item.price_cents)}</span></div>
                <button type="button" title={`Remove one ${item.name}`} aria-label={`Remove one ${item.name}`} onClick={() => removeItem(item.id)}>−</button>
              </div>
            ))}
          </div>
          <div className="order-total"><span>Total</span><strong>{money(total)}</strong></div>
          <button className="order-button" type="button" disabled={itemCount === 0 || placingOrder} onClick={placeOrder}>
            {placingOrder ? 'Sending…' : 'Place order'} <span aria-hidden="true">→</span>
          </button>
          <p className="order-message" role="status">{message}</p>
          <p className="checkout-note">Pickup at the counter · Pay when you arrive</p>
        </aside>
      </main>
      <footer className="site-footer"><span>Good Table, made for the neighborhood.</span><span>Seasonal things, made simply.</span></footer>
    </>
  )
}

function Dashboard() {
  const [from, setFrom] = useState(initialDashboardFrom)
  const [to, setTo] = useState(initialDashboardTo)
  const [appliedRange, setAppliedRange] = useState({ from: initialDashboardFrom, to: initialDashboardTo })
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    const query = new URLSearchParams(appliedRange)
    apiRequest(`/api/analytics?${query}`)
      .then((result) => { if (active) { setData(result); setError('') } })
      .catch((exception) => { if (active) setError(exception.message) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [appliedRange])

  const applyRange = (event) => {
    event.preventDefault()
    if (from > to) {
      setError('The start date must be before the end date.')
      return
    }
    setError('')
    setLoading(true)
    setAppliedRange({ from, to })
  }

  const cards = [
    { label: 'Page views', value: data?.metrics.page_views ?? 0, note: 'Across all pages', tone: 'green' },
    { label: 'Unique visitors', value: data?.metrics.unique_visitors ?? 0, note: 'Browser identities', tone: 'coral' },
    { label: 'Orders', value: data?.metrics.orders ?? 0, note: 'Created by the kitchen API', tone: 'yellow' },
    { label: 'All events', value: data?.metrics.total_events ?? 0, note: 'Browser + server', tone: 'blue' },
  ]
  const dates = []
  const start = new Date(`${appliedRange.from}T00:00:00Z`)
  const end = new Date(`${appliedRange.to}T00:00:00Z`)
  for (const date = new Date(start); date <= end; date.setUTCDate(date.getUTCDate() + 1)) dates.push(isoDay(date))
  const dailyTotals = new Map(dates.map((date) => [date, 0]))
  for (const event of data?.trends ?? []) dailyTotals.set(event.date, (dailyTotals.get(event.date) ?? 0) + event.count)
  const chartMax = Math.max(1, ...dailyTotals.values())
  const eventTotals = new Map()
  for (const event of data?.trends ?? []) eventTotals.set(event.name, (eventTotals.get(event.name) ?? 0) + event.count)
  const sortedEvents = [...eventTotals.entries()].sort((left, right) => right[1] - left[1])

  return (
    <>
      <Header dashboard />
      <main className="dashboard-main">
        <section className="dashboard-title-row">
          <div><p className="eyebrow">A little look behind the counter</p><h1>What's happening</h1></div>
          <form className="date-filter" onSubmit={applyRange}>
            <label>From<input type="date" required value={from} onChange={(event) => setFrom(event.target.value)} /></label>
            <label>To<input type="date" required value={to} onChange={(event) => setTo(event.target.value)} /></label>
            <button type="submit" aria-label="Apply date range">Apply</button>
          </form>
        </section>
        {error && <p className="dashboard-message" role="alert">{error}</p>}
        {loading && <p className="dashboard-message" role="status">Gathering the day's numbers…</p>}
        <section className="metric-grid" aria-label="Key metrics">
          {cards.map((card) => <article className={`metric-card metric-${card.tone}`} key={card.label}><span>{card.label}</span><strong>{Number(card.value).toLocaleString()}</strong><small>{card.note}</small></article>)}
        </section>
        <section className="dashboard-grid">
          <article className="dashboard-section trend-section">
            <div className="section-heading"><div><p className="eyebrow">Day by day</p><h2>Event activity</h2></div><span className="chart-legend"><i /> Events</span></div>
            <div className="trend-chart" aria-label="Events by day">
              {dates.map((date, index) => {
                const count = dailyTotals.get(date) ?? 0
                return <div className="bar-column" key={date} title={`${date}: ${count} events`}><span className="bar-value">{count || ''}</span><div className={`bar ${index % 4 === 1 ? 'bar-dark' : ''}`} style={{ height: `${count ? Math.max(5, count / chartMax * 100) : 0}%` }} /><span className="bar-label">{dates.length <= 14 || index % Math.ceil(dates.length / 14) === 0 ? date.slice(5) : ''}</span></div>
              })}
              {dates.length === 0 && <p className="empty-state">Choose a valid date range.</p>}
            </div>
          </article>
          <article className="dashboard-section pages-section">
            <div className="section-heading"><div><p className="eyebrow">Where people land</p><h2>Popular pages</h2></div></div>
            {(data?.pages?.length ?? 0) === 0 ? <p className="empty-state">No page views in this date range.</p> : <table className="page-table"><thead><tr><th>Page</th><th>Views</th></tr></thead><tbody>{data.pages.map((page) => <tr key={page.path}><td title={page.path}>{page.path}</td><td>{Number(page.views).toLocaleString()}</td></tr>)}</tbody></table>}
          </article>
        </section>
        <section className="dashboard-section event-section">
          <div className="section-heading"><div><p className="eyebrow">What people did</p><h2>Event breakdown</h2></div></div>
          {sortedEvents.length === 0 ? <p className="empty-state">No events in this date range.</p> : <div className="event-breakdown">{sortedEvents.map(([name, count]) => <div className="event-row" key={name}><span className="event-dot" /><span>{name.replaceAll('_', ' ')}</span><strong>{Number(count).toLocaleString()}</strong></div>)}</div>}
        </section>
      </main>
      <footer className="site-footer"><span>Good Table analytics</span><span>{loading ? 'Refreshing data…' : 'Events update when you apply a date range.'}</span></footer>
    </>
  )
}

export default function App() {
  const dashboard = window.location.pathname === '/dashboard'
  useEffect(() => { void trackPageView(dashboard ? 'Analytics dashboard' : 'Good Table restaurant') }, [dashboard])
  return dashboard ? <Dashboard /> : <Restaurant />
}