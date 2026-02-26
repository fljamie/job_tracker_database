#!/bin/bash

# ──────────────────────────────────────────────────────────────
#  Job Application Tracker v2.04 — Mac/Linux Launcher
#  Checks requirements, offers to install missing ones, then
#  starts the PHP server and opens the browser.
# ──────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT=8013
PHP_OK=0
CURL_OK=0

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
CYAN='\033[0;36m'
NC='\033[0m'

launch_server() {
    echo ""
    echo -e " ${CYAN}================================================${NC}"
    echo "   Launching Job Application Tracker"
    echo -e " ${CYAN}================================================${NC}"
    echo ""
    echo " Server:  http://127.0.0.1:$PORT"
    echo " Browser opens automatically in 2 seconds..."
    echo " Press Ctrl+C or use the in-app Shutdown button to stop."
    echo -e " ${CYAN}================================================${NC}"
    echo ""
    rm -f "$SCRIPT_DIR/.shutdown_flag"
    (sleep 2 && {
        if [[ "$OSTYPE" == "darwin"* ]]; then
            open "http://127.0.0.1:$PORT"
        else
            xdg-open "http://127.0.0.1:$PORT" 2>/dev/null || echo "  Open http://127.0.0.1:$PORT in your browser"
        fi
    }) &
    php -S 127.0.0.1:$PORT -t "$SCRIPT_DIR"
}

echo ""
echo -e "${CYAN} ================================================${NC}"
echo -e "${CYAN}   Job Application Tracker v2.04${NC}"
echo -e "${CYAN}   Starting setup check...${NC}"
echo -e "${CYAN} ================================================${NC}"
echo ""

# Already running?
if lsof -i :$PORT > /dev/null 2>&1; then
    echo -e " ${GREEN}[OK]${NC} Server already running - opening browser..."
    [[ "$OSTYPE" == "darwin"* ]] && open "http://127.0.0.1:$PORT" || xdg-open "http://127.0.0.1:$PORT" 2>/dev/null
    exit 0
fi

echo " Checking requirements..."
echo ""

# Check PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v 2>/dev/null | head -n1 | awk '{print $2}')
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_OK=1
    echo -e " ${GREEN}[OK]${NC} PHP $PHP_VERSION found"
    [ "$PHP_MAJOR" -lt 8 ] 2>/dev/null && echo -e " ${YELLOW}[WARNING]${NC} PHP 8.0+ recommended (you have $PHP_VERSION)"
else
    echo -e " ${RED}[MISSING]${NC} PHP is not installed"
    PHP_OK=0
fi

# Check cURL
if [ $PHP_OK -eq 1 ]; then
    if php -r "exit(extension_loaded('curl') ? 0 : 1);" 2>/dev/null; then
        CURL_OK=1
        echo -e " ${GREEN}[OK]${NC} PHP cURL extension enabled"
    else
        echo -e " ${YELLOW}[WARNING]${NC} PHP cURL extension not enabled (needed for AI features)"
        CURL_OK=0
    fi
fi

echo ""

# All good
if [ $PHP_OK -eq 1 ] && [ $CURL_OK -eq 1 ]; then
    launch_server; exit 0
fi

echo -e " ${CYAN}================================================${NC}"
echo "   Some requirements need attention"
echo -e " ${CYAN}================================================${NC}"
echo ""

# PHP missing
if [ $PHP_OK -eq 0 ]; then
    echo " PHP is required to run this application."
    echo ""
    if [[ "$OSTYPE" == "darwin"* ]]; then
        echo "   [1] Auto-install via Homebrew (recommended)"
        echo "   [2] Open php.net download page"
        echo "   [3] Exit"
        read -p "  Choose (1/2/3): " CHOICE
        case "$CHOICE" in
            1)
                if ! command -v brew &> /dev/null; then
                    echo " Installing Homebrew first (you may be prompted for your password)..."
                    /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
                    [ -f "/opt/homebrew/bin/brew" ] && eval "$(/opt/homebrew/bin/brew shellenv)"
                fi
                brew install php
                echo -e "\n ${GREEN}[OK]${NC} PHP installed! Please run ./START_HERE.sh again."
                exit 0 ;;
            2) open "https://www.php.net/downloads"; echo " Run ./START_HERE.sh after installing."; exit 0 ;;
            *) exit 0 ;;
        esac
    else
        echo "   [1] Auto-install via apt  (Ubuntu/Debian)"
        echo "   [2] Auto-install via dnf  (Fedora/RHEL)"
        echo "   [3] Exit"
        read -p "  Choose (1/2/3): " CHOICE
        case "$CHOICE" in
            1) sudo apt-get update && sudo apt-get install -y php php-curl php-mbstring php-json && echo -e "\n ${GREEN}[OK]${NC} Done! Run ./START_HERE.sh again."; exit 0 ;;
            2) sudo dnf install -y php php-curl php-mbstring && echo -e "\n ${GREEN}[OK]${NC} Done! Run ./START_HERE.sh again."; exit 0 ;;
            *) exit 0 ;;
        esac
    fi
fi

# cURL missing but PHP ok
if [ $PHP_OK -eq 1 ] && [ $CURL_OK -eq 0 ]; then
    echo " AI features require PHP cURL. The app works fine without it."
    echo ""
    echo "   [1] Try to fix automatically"
    echo "   [2] Continue without AI features"
    echo "   [3] Exit"
    read -p "  Choose (1/2/3): " CHOICE
    case "$CHOICE" in
        1)
            if [[ "$OSTYPE" == "darwin"* ]]; then
                brew reinstall php
            else
                sudo apt-get install -y php-curl 2>/dev/null || sudo dnf install -y php-curl 2>/dev/null
            fi
            echo " Done. Run ./START_HERE.sh again."; exit 0 ;;
        2) launch_server; exit 0 ;;
        *) exit 0 ;;
    esac
fi

launch_server
