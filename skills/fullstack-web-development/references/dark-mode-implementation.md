# Dark Mode Implementation in SMART PJU

## Overview
Implement dark mode using Tailwind CSS class strategy with a custom React hook for persistence.

## Files Modified

1. `tailwind.config.js` - Enable dark mode
2. `src/hooks/useDarkMode.js` - Custom hook for state and persistence
3. `src/components/Layout/Layout.jsx` - Apply dark class to root
4. `src/components/Layout/Header.jsx` - Add toggle button
5. `src/components/Layout/Sidebar.jsx` - Update colors for dark mode
6. `src/index.css` - Update base and component styles with dark variants

## Implementation Details

### tailwind.config.js
```javascript
module.exports = {
  darkMode: 'class', // Enable class-based dark mode
  // ... rest of config
}
```

### useDarkMode Hook
```javascript
import { useState, useEffect } from 'react'

export const useDarkMode = () => {
  const [darkMode, setDarkMode] = useState(false)

  // Initialize from localStorage or system preference
  useEffect(() => {
    const savedMode = localStorage.getItem('darkMode')
    if (savedMode !== null) {
      setDarkMode(savedMode === 'true')
    } else {
      setDarkMode(window.matchMedia('(prefers-color-scheme: dark)').matches)
    }
  }, [])

  // Apply/remove dark class on root element
  useEffect(() => {
    if (darkMode) {
      document.documentElement.classList.add('dark')
      localStorage.setItem('darkMode', 'true')
    } else {
      document.documentElement.classList.remove('dark')
      localStorage.setItem('darkMode', 'false')
    }
  }, [darkMode])

  const toggleDarkMode = () => setDarkMode(!darkMode)

  return { darkMode, toggleDarkMode }
}
```

### Layout.jsx
```javascript
import { useDarkMode } from '../../hooks/useDarkMode'

export default function Layout() {
  const { darkMode } = useDarkMode()
  return (
    <div className={`flex h-screen bg-gray-50 dark:bg-gray-900`}>
      {/* ... */}
    </div>
  )
}
```

### Header.jsx Toggle Button
```javascript
import { Moon, Sun } from 'lucide-react'
import { useDarkMode } from '../../hooks/useDarkMode'

export default function Header() {
  const { darkMode, toggleDarkMode } = useDarkMode()
  return (
    <header className="h-16 bg-white dark:bg-gray-800">
      {/* ... */}
      <button onClick={toggleDarkMode} className="hover:bg-gray-100 dark:hover:bg-gray-700">
        {darkMode ? <Sun className="text-yellow-400" /> : <Moon className="text-gray-400" />}
        <span className="ml-2 text-xs">{darkMode ? 'Terang' : 'Gelap'}</span>
      </button>
    </header>
  )
}
```

### Styling Updates (index.css)
Add `dark:` variants to all components:
- `bg-gray-50 dark:bg-gray-900`
- `text-gray-900 dark:text-gray-100`
- `border-gray-200 dark:border-gray-700`
- etc.

## Usage
- System preference respected on first visit
- Toggle button in header persists choice across sessions
- Works on all pages via Layout wrapper

## Testing
1. Verify initial load matches system preference
2. Click toggle button and observe color changes
3. Refresh page - choice should persist
4. Change system OS theme - new visits respect OS setting until manually overridden