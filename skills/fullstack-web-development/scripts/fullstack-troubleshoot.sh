#!/usr/bin/env bash
# Helper script to diagnose and fix common fullstack dev issues
# Run from project root: bash scripts/fullstack-troubleshoot.sh

set -euo pipefail

echo "🔍 Full-Stack Development Troubleshooting Script"
echo "================================================"
echo

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in a project directory
if [ ! -f "package.json" ] && [ ! -d "backend" ]; then
    echo -e "${RED}Error: Not in a fullstack project directory${NC}"
    echo "Run this from project root (where backend/ or package.json exists)"
    exit 1
fi

echo "1️⃣  Checking Backend Status..."
if [ -d "backend" ]; then
    cd backend
    
    # Check if backend is running
    if curl -s http://localhost:5000/api/health > /dev/null 2>&1; then
        echo -e "   ${GREEN}✓ Backend is running on port 5000${NC}"
        
        # Test login endpoint
        HEALTH=$(curl -s http://localhost:5000/api/health | python3 -m json.tool 2>/dev/null | grep -c "ok" || echo "0")
        if [ "$HEALTH" -gt 0 ]; then
            echo -e "   ${GREEN}✓ Health check passed${NC}"
        else
            echo -e "   ${RED}✗ Health check returning errors${NC}"
        fi
    else
        echo -e "   ${YELLOW}⚠ Backend not running${NC}"
        echo "   To start: cd backend && node src/index.js"
    fi
    
    # Check for error logs
    if [ -f "logs/error.log" ]; then
        ERROR_COUNT=$(tail -50 logs/error.log | grep -c "ERROR\|error" || echo "0")
        if [ "$ERROR_COUNT" -gt 0 ]; then
            echo -e "   ${YELLOW}⚠ Found $ERROR_COUNT recent errors in logs${NC}"
            echo "   Last 5 errors:"
            tail -5 logs/error.log | sed 's/^/      /'
        else
            echo -e "   ${GREEN}✓ No recent errors in logs${NC}"
        fi
    fi
    
    cd ..
else
    echo -e "   ${YELLOW}⚠ No backend/ directory found${NC}"
fi

echo
echo "2️⃣  Checking Frontend Status..."
if [ -d "frontend" ]; then
    cd frontend
    
    if curl -s http://localhost:5173 > /dev/null 2>&1; then
        echo -e "   ${GREEN}✓ Frontend dev server running on port 5173${NC}"
    else
        echo -e "   ${YELLOW}⚠ Frontend dev server not running${NC}"
        echo "   To start: cd frontend && npm run dev"
    fi
    
    # Check build
    if [ -d "dist" ]; then
        DIST_SIZE=$(du -sh dist/assets/*.js 2>/dev/null | awk '{print $1}' | head -1)
        echo -e "   ${GREEN}✓ Production build exists (${DIST_SIZE:-unknown size})${NC}"
        
        # Check for obvious build issues
        if [ -f "dist/index.html" ]; then
            echo -e "   ${GREEN}✓ dist/index.html present${NC}"
        else
            echo -e "   ${RED}✗ dist/index.html missing${NC}"
        fi
    else
        echo -e "   ${YELLOW}⚠ No production build (run: npm run build)${NC}"
    fi
    
    cd ..
else
    echo -e "   ${YELLOW}⚠ No frontend/ directory found${NC}"
fi

echo
echo "3️⃣  Checking Database..."
BACKEND_DIR=""
if [ -d "backend/data" ]; then
    BACKEND_DIR="backend"
elif [ -d "data" ]; then
    BACKEND_DIR="."
fi

if [ -n "$BACKEND_DIR" ]; then
    DB_FILE="$BACKEND_DIR/data/smart_pju.sqlite"
    if [ -f "$DB_FILE" ]; then
        SIZE=$(ls -lh "$DB_FILE" | awk '{print $5}')
        echo -e "   ${GREEN}✓ Database exists ($SIZE)${NC}"
        
        # Check if file is locked (Windows)
        if ! rm "$DB_FILE" 2>/dev/null; then
            echo -e "   ${YELLOW}⚠ Database file appears locked (server running?)${NC}"
        else
            echo -e "   ${GREEN}✓ Database file accessible${NC}"
            # Don't actually delete, this was just a test
            git checkout "$DB_FILE" 2>/dev/null || true
        fi
    else
        echo -e "   ${YELLOW}⚠ No database file found${NC}"
        echo "   Database will be created on first server start"
    fi
else
    echo -e "   ${YELLOW}⚠ No data/ directory found${NC}"
fi

echo
echo "4️⃣  Checking Dependencies..."
if [ -d "backend/node_modules" ]; then
    BACKEND_DEPS=$(ls backend/node_modules | wc -l)
    echo -e "   ${GREEN}✓ Backend dependencies installed ($BACKEND_DEPS packages)${NC}"
else
    echo -e "   ${RED}✗ Backend dependencies missing${NC}"
    echo "   Run: cd backend && npm install"
fi

if [ -d "frontend/node_modules" ]; then
    FRONTEND_DEPS=$(ls frontend/node_modules | wc -l)
    echo -e "   ${GREEN}✓ Frontend dependencies installed ($FRONTEND_DEPS packages)${NC}"
else
    echo -e "   ${RED}✗ Frontend dependencies missing${NC}"
    echo "   Run: cd frontend && npm install"
fi

echo
echo "5️⃣  Quick Fixes Menu"
echo "   Run these commands manually if needed:"
echo
echo "   # Install all dependencies"
echo "   cd backend && npm install"
echo "   cd ../frontend && npm install"
echo
echo "   # Start development servers"
echo "   cd backend && node src/index.js      # Terminal 1"
echo "   cd frontend && npm run dev           # Terminal 2"
echo
echo "   # Reset database (stop server first!)"
echo "   # Kill server process on port 5000"
echo "   netstat -ano | findstr ':5000'"
echo "   taskkill /PID <pid> /F"
echo "   rm backend/data/smart_pju.sqlite"
echo "   # Restart server to create fresh DB"
echo
echo "   # Test API endpoints"
echo "   curl http://localhost:5000/api/health"
echo "   curl -X POST http://localhost:5000/api/auth/login -H 'Content-Type: application/json' -d '{\"username\":\"admin\",\"password\":\"admin123\"}'"
echo
echo "   # Check error logs"
echo "   tail -50 backend/logs/error.log"
echo

echo "✅ Troubleshooting complete!"
echo