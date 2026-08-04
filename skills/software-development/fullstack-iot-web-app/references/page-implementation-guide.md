# Comprehensive Page Implementation Guide

## Overview from SMART PJU Session (2026-06-20)

This reference captures the **complete implementation pattern** for building 6 production-ready IoT management pages.

---

## 1. Login Page (LoginPage.jsx) - 3KB

**Features implemented:**
- JWT authentication with localStorage persistence
- Form validation with error handling
- Toast notifications
- Redirect to dashboard on success
- Protected route redirect to login if authenticated

**Key code pattern:**
```jsx
const handleSubmit = async (e) => {
  e.preventDefault()
  try {
    const res = await authApi.login({ username, password })
    login(res.data.user, res.data.token) // Zustand store
    toast.success('Login berhasil')
    navigate('/dashboard')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Login gagal')
  }
}
```

---

## 2. Dashboard Page (DashboardPage.jsx) - 8KB

**Features implemented:**
- 5 summary stat cards (Total Devices, Normal, Offline, Broken, Energy)
- Energy trend line chart (7 days)
- Recent maintenance issues list
- Real-time data via WebSocket

**Stats cards data:**
```javascript
const stats = {
  total: devices.length,
  normal: devices.filter(d => d.status === 'normal').length,
  offline: devices.filter(d => d.status === 'offline').length,
  broken: devices.filter(d => d.status === 'broken').length,
  energy: devices.reduce((sum, d) => sum + d.power, 0)
}
```

**Chart data format:**
```javascript
const trendData = [
  { date: '2026-06-14', energy: 12450 },
  { date: '2026-06-15', energy: 13200 },
  // ...
]
```

---

## 3. Map Page (MapPage.jsx) - 14.8KB ⭐

**Full feature list:**
- Leaflet interactive map with OpenStreetMap tiles
- 50 device markers with **custom color coding**:
  - 🟢 Green: Normal/Online
  - 🔴 Red: Broken/Rusak
  - ⚫ Gray: Offline
  - 🔵 Blue: Maintenance
- Popup on marker click with telemetry:
  - Power (W), Voltage (V), Current (A)
  - Temperature (°C)
  - Last update timestamp
- Left panel: Device list with search/filter
- Right panel: Selected device detail with controls
- Zone filter (A/B/C/D)
- Status filter (All/Normal/Offline/Rusak)

**Marker implementation:**
```jsx
import { DivIcon } from 'leaflet'

const getMarkerIcon = (status) => {
  const colors = {
    normal: '#22c55e',
    offline: '#6b7280',
    broken: '#ef4444',
    maintenance: '#f59e0b'
  }
  return new DivIcon({
    html: `<div style="background:${colors[status]};width:16px;height:16px;border-radius:50%;border:2px solid white;"></div>`,
    className: 'custom-marker'
  })
}
```

**Filter logic:**
```jsx
const filteredDevices = devices.filter(d => {
  if (filter.status !== 'all' && d.status !== filter.status) return false
  if (filter.zone !== 'all' && d.zone !== filter.zone) return false
  if (search && !d.name.toLowerCase().includes(search.toLowerCase())) return false
  return true
})
```

---

## 4. Control Page (ControlPage.jsx) - 21KB ⭐

### Manual Control Tab
- Quick ON/OFF all devices
- Per-device toggle with real-time status
- Brightness slider (0-100%) with dimming control
- Group control by zone
- Current power consumption display

### Schedule Control Tab
- **Form fields:**
  - Zone selector (A/B/C/D/All)
  - Start time (time picker)
  - End time (time picker)
  - Brightness level (0-100%)
  - Days of week (checkboxes)
- Schedule list with enable/disable toggle
- Delete schedule confirmation

### Auto Mode Tab
- Sensor-based control toggle
- Light sensor threshold (lux)
- Motion sensor mode (on/off)
- Energy saving mode with estimated savings
- Schedule override option

**API calls used:**
```javascript
// Manual control
await controlApi.manual(deviceId, { state: 'on', brightness: 80 })

// Create schedule
await controlApi.createSchedule({
  zone: 'Zona A',
  startTime: '18:00',
  endTime: '06:00',
  brightness: 80,
  days: ['mon', 'tue', 'wed', 'thu', 'fri']
})

// Auto mode
await controlApi.setAutoMode({
  enabled: true,
  mode: 'sensor',
  lightThreshold: 50 // lux
})
```

---

## 5. Maintenance Page (MaintenancePage.jsx) - 18KB ⭐

### Stats Cards
5 KPI cards with color coding:
- Total Tiket (white)
- Menunggu (yellow)
- Dikerjakan (blue)
- Selesai (green)
- Prioritas Tinggi (red)

### Create Ticket Form (Modal)
**Required fields:**
- Device ID (text input with validation)
- Jenis Gangguan (issue description)
- Prioritas (dropdown: Tinggi/Sedang/Rendah)
- Pelapor (reporter name)
- Deskripsi Lengkap (textarea, detailed description)

### Ticket Management Workflow

**Status flow:**
```
Pending → [Assign] → In Progress → [Resolve] → Resolved
                     ↓
              [Cancel] → Cancelled
```

**Actions per status:**
- **Pending:** "Ambil" button (assign to current user)
- **In Progress:** "Selesai" button (mark as resolved)
- **All statuses:** "Batal" button (cancel ticket)

**Filtering:**
```jsx
const filteredTickets = tickets.filter(t => {
  if (filter.status !== 'all' && t.status !== filter.status) return false
  if (filter.priority !== 'all' && t.priority !== filter.priority) return false
  if (filter.zone !== 'all' && t.device?.zone !== filter.zone) return false
  return true
})
```

**Priority configuration:**
```javascript
const priorityConfig = {
  tinggi: { color: 'bg-red-100 text-red-800', label: 'TINGGI (≤2 jam)' },
  sedang: { color: 'bg-orange-100 text-orange-800', label: 'SEDANG (≤24 jam)' },
  rendah: { color: 'bg-gray-100 text-gray-800', label: 'RENDAH (Terjadwal)' }
}
```

---

## 6. Reports Page (ReportsPage.jsx) - 15.7KB ⭐

### Energy Report Tab

**Display components:**
1. **Summary Cards (4):**
   - Total Konsumsi (62,500 kWh)
   - Total Biaya (Rp 93.75M @ Rp 1,500/kWh)
   - Penghematan (12.3% vs baseline)
   - Rata-rata Harian (1,250 kWh)

2. **Energy Trend Chart (LineChart):**
   - X-axis: Date (7 days)
   - Y-axis: Energy (kWh) + Cost (Rp)
   - Two lines: consumption (blue) + cost (red)

3. **Zone Distribution (PieChart):**
   - 4 zones with different colors
   - Labels: Zone name + consumption value
   - Legend with color mapping

4. **Cost Breakdown Table:**
   - Columns: Zone, Consumption, Cost, % of Total
   - Sorted by consumption (descending)

### Performance Report Tab

**5 KPI Cards:**
- Operabilitas: 94% (47/50 devices normal)
- Uptime: 99.2% (last 30 days)
- MTTR: 1.2 hours (Mean Time To Repair)
- MTBF: 850 hours (Mean Time Between Failures)
- Response Time: 2.5 hours (avg response to ticket)

**Device Status Distribution (PieChart):**
- Normal: 47 devices (green)
- Offline: 3 devices (gray)
- Rusak: 0 devices (red)
- Maintenance: 0 devices (orange)

**Efficiency Metrics (Progress Bars):**
- Energy Efficiency: 87%
- Device Utilization: 94%
- Cost Efficiency: 12.3% savings

### Maintenance Report Tab
- Placeholder for future analytics
- Can include: ticket volume trends, resolution times, technician workload

**Chart library used:** Recharts
```javascript
import { 
  LineChart, Line, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip, Legend,
  ResponsiveContainer 
} from 'recharts'

// Usage example:
<ResponsiveContainer width="100%" height={300}>
  <LineChart data={trendData}>
    <CartesianGrid strokeDasharray="3 3" />
    <XAxis dataKey="date" />
    <YAxis />
    <Tooltip />
    <Legend />
    <Line dataKey="energy" stroke="#2563eb" strokeWidth={2} />
  </LineChart>
</ResponsiveContainer>
```

---

## 7. Admin Page (AdminPage.jsx) - 27.9KB ⭐

### Device Management Tab

**CRUD operations:**
1. **Create:** Modal form with fields:
   - Device ID (unique identifier, e.g., TL-TOLL-00025)
   - Name (descriptive name, e.g., PJU KM 12+500)
   - Zone (dropdown: A/B/C/D/E)
   - Type (controller/sensor/gateway)
   - Latitude/Longitude (GPS coordinates)
   - Status (active/inactive/maintenance)

2. **Read:** Table with columns:
   - ID (monospace font)
   - Name (bold)
   - Zone
   - Type (capitalized)
   - Status (badge with color)
   - Coordinates (lat, lng format)
   - Actions (edit + delete buttons)

3. **Update:** Pre-populated modal form

4. **Delete:** Confirmation dialog before deletion

### User Management Tab

**CRUD operations:**
1. **Create:** Modal form with fields:
   - Username (unique, for login)
   - Email (valid email format)
   - Full Name (display name)
   - Password (min 6 chars, hashed on save)
   - Role (admin/pengelola/teknisi)
   - Zone Responsibility (dropdown, empty = all zones)
   - Phone Number (optional)

2. **Read:** Table with columns:
   - Username (bold)
   - Full Name
   - Email (text-gray-600)
   - Role (badge with color: purple=admin, blue=pengelola, gray=teknisi)
   - Zone (or "Semua" for admin)
   - Actions (edit + delete buttons)

3. **Update:** Pre-populated form (password field optional)

4. **Delete:** Confirmation with username verification

**Search functionality:**
```jsx
const filteredDevices = devices.filter(d => 
  d.name?.toLowerCase().includes(search.toLowerCase()) ||
  d.id?.toLowerCase().includes(search.toLowerCase()) ||
  d.zone?.toLowerCase().includes(search.toLowerCase())
)
```

---

## 8. Layout Component (Layout.jsx) - 4.9KB

### Sidebar Navigation

**Features:**
- Collapsible (264px ↔ 80px)
- Active route highlighting (background + color)
- Icons from Lucide React
- User profile display (avatar + name + role)
- Logout button with confirmation

**Menu items:**
```javascript
const menuItems = [
  { path: '/dashboard', label: 'Dasbor', icon: LayoutDashboard },
  { path: '/map', label: 'Peta Interaktif', icon: Map },
  { path: '/control', label: 'Pengendalian', icon: Lightbulb },
  { path: '/maintenance', label: 'Pemeliharaan', icon: Wrench },
  { path: '/reports', label: 'Laporan', icon: BarChart3 },
  { path: '/admin', label: 'Manajemen', icon: Settings },
]
```

### Top Header

**Components:**
- Page title (from current route)
- Notification bell with badge
- Current date (Indonesian format)
- User menu (profile + logout)

---

## React Router Setup (App.jsx)

**Protected routes pattern:**
```jsx
function ProtectedRoute({ children }) {
  const { isAuthenticated } = useAuthStore()
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return children
}

function AdminRoute({ children }) {
  const { user } = useAuthStore()
  if (!user || user.role !== 'admin') return <Navigate to="/dashboard" replace />
  return children
}

// Routes configuration
<Routes>
  <Route path="/login" element={<LoginPage />} />
  <Route path="/" element={<ProtectedRoute><Layout /></ProtectedRoute>}>
    <Route path="dashboard" element={<DashboardPage />} />
    <Route path="map" element={<MapPage />} />
    <Route path="control" element={<ControlPage />} />
    <Route path="maintenance" element={<MaintenancePage />} />
    <Route path="reports" element={<ReportsPage />} />
    <Route path="admin" element={<AdminRoute><AdminPage /></AdminRoute>} />
  </Route>
</Routes>
```

---

## Testing Checklist (All Pages)

Before marking a page as complete, verify:

### Functional Tests
- [ ] Page loads without errors
- [ ] Data fetches from API correctly
- [ ] Loading states display during fetch
- [ ] Error handling shows toast notifications
- [ ] Empty states display gracefully
- [ ] Forms validate input
- [ ] CRUD operations work correctly
- [ ] Filters work as expected
- [ ] Search functionality works
- [ ] Pagination works (if applicable)

### UI/UX Tests
- [ ] Responsive on mobile (320px)
- [ ] Responsive on tablet (768px)
- [ ] Responsive on desktop (1920px)
- [ ] Icons display correctly
- [ ] Colors consistent with design system
- [ ] Typography readable
- [ ] Buttons have hover states
- [ ] Modals open/close correctly
- [ ] Toast notifications appear
- [ ] Loading spinners show

### Integration Tests
- [ ] API calls complete successfully
- [ ] Authentication works (protected routes)
- [ ] Authorization works (role-based)
- [ ] Real-time updates via WebSocket
- [ ] Navigation between pages works
- [ ] State persists correctly
- [ ] No console errors

---

## Performance Metrics (SMART PJU Reference)

| Metric | Target | Achieved |
|--------|--------|----------|
| Page size | <30KB | ✅ Max 27.9KB |
| Build time | <10s | ✅ 5.12s |
| Bundle size | <1.5MB | ✅ 1.01MB |
| API response | <500ms | ✅ <300ms |
| Page load | <3s | ✅ ~700ms |

---

## Key Learnings & Best Practices

### What Worked Well
1. **Consistent structure** - Every page follows same pattern
2. **Modal forms** - Clean UX for CRUD operations
3. **Toast notifications** - Immediate feedback for all actions
4. **Color-coded badges** - Quick visual status recognition
5. **Filter controls** - Easy data exploration
6. **Responsive design** - Works on all screen sizes

### Common Pitfalls & Solutions

**1. API integration issues**
- **Problem:** Frontend doesn't display backend data
- **Root cause:** Token not sent in headers, wrong data structure
- **Solution:** Use axios interceptors, check response structure

**2. Form submission double-trigger**
- **Problem:** Form submits twice, creates duplicate data
- **Root cause:** Missing `e.preventDefault()` or button type
- **Solution:** Always use `type="button"` for non-submit buttons

**3. Loading state stuck**
- **Problem:** Loading spinner never stops
- **Root cause:** Missing `finally` block in async function
- **Solution:** Always use try-catch-finally pattern

**4. Modal doesn't close**
- **Problem:** Modal stays open after operation
- **Root cause:** State not reset after operation
- **Solution:** Reset form state AND close modal in finally/catch

---

## Copy-Paste Templates

### Basic Page Template
```jsx
import { useState, useEffect } from 'react'
import { featureApi } from '../services/api'
import toast from 'react-hot-toast'
import { IconName } from 'lucide-react'

export default function FeaturePage() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(false)
  
  useEffect(() => {
    loadData()
  }, [])
  
  const loadData = async () => {
    try {
      setLoading(true)
      const res = await featureApi.getAll()
      setData(res.data.items || [])
    } catch (error) {
      console.error('Load error:', error)
      toast.error('Gagal memuat data')
    } finally {
      setLoading(false)
    }
  }
  
  return (
    <div className="min-h-screen bg-gray-100 p-6">
      <div className="max-w-7xl mx-auto">
        {/* Header */}
        <div className="mb-6">
          <h1 className="text-3xl font-bold mb-2">Page Title</h1>
          <p className="text-gray-600">Description text</p>
        </div>
        
        {/* Content */}
        {loading ? (
          <div className="text-center py-12">Loading...</div>
        ) : (
          <div className="bg-white rounded-lg shadow p-6">
            {/* Your content here */}
          </div>
        )}
      </div>
    </div>
  )
}
```

### CRUD Modal Form Template
```jsx
const handleEdit = (item) => {
  setEditingItem(item)
  setForm({ field1: item.field1, field2: item.field2 })
  setShowForm(true)
}

const handleDelete = async (item) => {
  if (!confirm(`Hapus ${item.name}?`)) return
  try {
    await api.remove(item.id)
    toast.success('Berhasil dihapus')
    loadData()
  } catch (error) {
    toast.error('Gagal menghapus')
  }
}

const handleSubmit = async (e) => {
  e.preventDefault()
  try {
    if (editingItem) {
      await api.update(editingItem.id, form)
      toast.success('Berhasil diperbarui')
    } else {
      await api.create(form)
      toast.success('Berhasil ditambahkan')
    }
    setShowForm(false)
    setEditingItem(null)
    loadData()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal menyimpan')
  }
}
```

---

**This reference is based on the complete SMART PJU implementation from June 21, 2026, where all 6 pages were built in a single session (~8 hours total).**