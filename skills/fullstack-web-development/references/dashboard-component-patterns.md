# React Dashboard Component Patterns

## Overview

Common patterns and pitfalls when building dashboard pages with child components in React + Vite applications.

## Component Structure

### ✅ CORRECT: Flat Structure

```
src/
├── pages/
│   └── DashboardPage.jsx
├── components/
│   ├── DashboardStats.jsx      ← Flat, at root of components/
│   ├── EnergyChart.jsx
│   └── RecentAlerts.jsx
├── services/
│   └── api.js
└── store/
    └── authStore.js
```

**Import syntax:**
```javascript
import DashboardStats from '../components/DashboardStats'
import EnergyChart from '../components/EnergyChart'
import RecentAlerts from '../components/RecentAlerts'
```

### ❌ WRONG: Nested Structure

```
src/
├── pages/
│   └── DashboardPage.jsx
└── components/
    └── Dashboard/            ← Don't nest unless necessary
        ├── DashboardStats.jsx
        ├── EnergyChart.jsx
        └── RecentAlerts.jsx
```

**Why this fails:**
- Import paths become longer and error-prone
- Easy to reference non-existent directories
- Silent failures when component files missing

## Debugging Empty Pages

When a page renders blank with NO console errors:

### Step 1: Check Component Imports

```bash
# Verify all imported components exist
ls -la src/components/DashboardStats.jsx
ls -la src/components/EnergyChart.jsx
ls -la src/components/RecentAlerts.jsx
```

### Step 2: Add Debug Logging

In parent component `useEffect`:

```javascript
useEffect(() => {
  console.log('Page mounted, starting data load...')
  
  const load = async () => {
    try {
      const data = await api.getData()
      console.log('✓ API response:', data)
      setData(data)
    } catch (err) {
      console.error('❌ Error:', err)
      console.error('Details:', err.response?.data)
    }
  }
  load()
}, [])
```

### Step 3: Test API Directly

```bash
# Test backend API returns data
curl http://localhost:5000/api/devices/summary \
  -H "Authorization: Bearer YOUR_TOKEN"

# Should return JSON, not 401/500 errors
```

### Step 4: Check Auth Token

```javascript
// In browser console (F12)
localStorage.getItem('token')
// Should return: "eyJhbGciOiJIUzI1NiIsInR..."
// NOT: null
```

### Step 5: Verify Vite Proxy

In `vite.config.js`:

```javascript
export default defineConfig({
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost:5000',  // Backend port
        changeOrigin: true,
      },
      '/socket.io': {
        target: 'http://localhost:5000',
        ws: true,  // WebSocket support
      },
    },
  },
})
```

## Child Component Patterns

### Stats Card Component

```jsx
import { Zap, AlertTriangle } from 'lucide-react'

const statCards = [
  { key: 'total', label: 'Total', icon: Zap, color: 'text-blue-600' },
  { key: 'error', label: 'Error', icon: AlertTriangle, color: 'text-red-600' },
]

export default function StatsCards({ data }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      {statCards.map(({ key, label, icon: Icon, color }) => (
        <div key={key} className="card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600">{label}</p>
              <p className="text-3xl font-bold">{data[key] ?? 0}</p>
            </div>
            <div className={`p-3 rounded-full bg-${color.split('-')[1]}-50`}>
              <Icon className={`w-6 h-6 ${color}`} />
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}
```

### Chart Component (Recharts)

```jsx
import { LineChart, Line, ResponsiveContainer } from 'recharts'

export default function SimpleChart({ data = [], loading = false }) {
  if (loading) return <LoadingSpinner />
  if (!data.length) return <EmptyState />
  
  return (
    <ResponsiveContainer width="100%" height={300}>
      <LineChart data={data}>
        <CartesianGrid strokeDasharray="3 3" />
        <XAxis dataKey="date" />
        <YAxis />
        <Tooltip />
        <Line dataKey="value" stroke="#2563eb" strokeWidth={2} />
      </LineChart>
    </ResponsiveContainer>
  )
}
```

### Alert/Ticket List

```jsx
export default function AlertList({ tickets = [] }) {
  if (!tickets.length) {
    return <EmptyState message="No active alerts" />
  }
  
  return (
    <div className="space-y-3">
      {tickets.map(ticket => (
        <div key={ticket.id} className="p-3 rounded border">
          <h4 className="font-medium">{ticket.issue}</h4>
          <div className="flex gap-2 mt-2">
            <StatusBadge status={ticket.status} />
            <PriorityBadge priority={ticket.priority} />
          </div>
        </div>
      ))}
    </div>
  )
}
```

## Testing Checklist

Before marking dashboard as complete:

- [ ] All child component files exist at correct paths
- [ ] Import paths match file locations exactly
- [ ] Console shows no import errors
- [ ] API calls return data (check Network tab)
- [ ] Token present in localStorage after login
- [ ] Loading states display during data fetch
- [ ] Error states handle API failures gracefully
- [ ] Empty states show when no data available
- [ ] Responsive design works on mobile/tablet

## Related Files

- `templates/dashboard-component.jsx` - Boilerplate dashboard page
- `scripts/verify-imports.sh` - Check all imports resolve
- `references/debugging-guide.md` - Extended troubleshooting

---

**Created:** June 21, 2026  
**Context:** SMART PJU dashboard blank page debugging