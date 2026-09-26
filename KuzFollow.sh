#!/bin/bash

# =============================================================================
# GITHUB FOLLOWERS ANALYSIS SCRIPT
# =============================================================================

# --- CONFIGURATION ---
GITHUB_USER="${GITHUB_USER:-}"
GITHUB_TOKEN="${GITHUB_TOKEN:-}"
PER_PAGE=100

# --- COLORS ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# --- TERMINAL UI (Bash only) ---
init_ui() {
    if [[ ! -t 1 || ${TERM:-dumb} == dumb || -n ${NO_COLOR+x} ]]; then
        RED='' GREEN='' YELLOW='' BLUE='' PURPLE='' CYAN='' WHITE='' BOLD='' NC=''
    fi
}

ui_rule() {
    local width=${COLUMNS:-72} line
    [[ $width =~ ^[0-9]{1,3}$ ]] || width=72
    width=$((10#$width))
    ((width > 72)) && width=72
    ((width < 1)) && width=1
    printf -v line '%*s' "$width" ''
    printf '%b%s%b\n' "$CYAN" "${line// /-}" "$NC"
}

ui_section() {
    printf '\n%b%s%b\n' "$BOLD$CYAN" "$1" "$NC"
    ui_rule
}

ui_metric() {
    printf '  %-22s %b%s%b\n' "$1" "$BOLD" "$2" "$NC"
}

show_header() {
    ui_rule
    printf '%bKuzFollow | GitHub connections%b\n' "$BOLD$CYAN" "$NC"
    printf 'Analyze your network, then choose an action.\n'
    ui_rule
}

show_accounts() {
    local title=$1 user
    shift
    ui_section "$title ($#)"
    if (($# == 0)); then
        printf '  None. Everything is up to date.\n'
    else
        for user in "$@"; do printf '  - %s\n' "$user"; done
    fi
}

action_menu() {
    local choice confirm
    if [[ ! -t 0 || ! -t 1 ]]; then
        printf '\nReport only: use an interactive terminal for actions.\n'
        return 0
    fi
    while true; do
        ui_section 'Actions'
        ((unfollowed_count > 0)) &&
            printf '  [1] Unfollow non-reciprocal accounts (%s)\n' "$unfollowed_count"
        ((not_followed_back_count > 0)) &&
            printf '  [2] Follow back followers (%s)\n' "$not_followed_back_count"
        printf '  [3] Review account lists\n  [4/q] Finish without changes\n\n'
        IFS= read -r -p 'Your choice: ' choice || return 0
        case $choice in
            1|2)
                if [[ $choice == 1 ]]; then
                    ((unfollowed_count > 0)) || { printf 'No accounts to unfollow.\n'; continue; }
                    printf 'Unfollow %s accounts listed above.\n' "$unfollowed_count"
                else
                    ((not_followed_back_count > 0)) || { printf 'No accounts to follow.\n'; continue; }
                    printf 'Follow %s accounts listed above.\n' "$not_followed_back_count"
                fi
                IFS= read -r -p 'Type YES to confirm (Enter cancels): ' confirm || return 0
                if [[ $confirm != YES ]]; then
                    printf 'Cancelled. No changes made.\n'
                    continue
                fi
                if [[ $choice == 1 ]]; then
                    mass_unfollow "${unfollowed_users[@]}"
                else
                    mass_follow "${not_followed_back_users[@]}"
                fi
                printf 'Run KuzFollow again to refresh the analysis.\n'
                return 0
                ;;
            3)
                show_accounts 'Not following you back' "${unfollowed_users[@]}"
                show_accounts 'Followers to follow back' "${not_followed_back_users[@]}"
                ;;
            4|q|Q|'') printf 'No changes made.\n'; return 0 ;;
            *) printf 'Invalid choice. Choose an available number or q.\n' ;;
        esac
    done
}

show_help() {
    printf '%s\n' \
        'Usage: bash KuzFollow.sh [--help]' \
        'Set GITHUB_USER and GITHUB_TOKEN in your environment.' \
        'Requires Bash 4+, curl and jq. Never store your token in the script.' \
        'NO_COLOR=1 disables colors. Redirected output is a read-only report.'
}

spinner() {
    local pid=$1
    local delay=0.1
    local spinstr='|/-\'
    while [ "$(ps a | awk '{print $1}' | grep $pid)" ]; do
        local temp=${spinstr#?}
        printf " [%c]  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# --- USER INFO FUNCTION ---
get_user_info() {
    local response=$(curl -s -u "$GITHUB_USER:$GITHUB_TOKEN" \
        "https://api.github.com/users/$GITHUB_USER" 2>/dev/null)
    
    if [[ -z "$response" ]] || ! echo "$response" | jq . >/dev/null 2>&1; then
        echo -e "${RED}${BOLD}ERROR: UNABLE TO FETCH USER INFO${NC}" >&2
        return 1
    fi
    
    local name=$(echo "$response" | jq -r '.name // "N/A"')
    local public_repos=$(echo "$response" | jq -r '.public_repos // 0')
    
    echo "$name|$public_repos"
}

# --- FOLLOW USER FUNCTION ---
follow_user() {
    local username=$1
    local response=$(curl -s -w "%{http_code}" -o /dev/null -X PUT \
        -u "$GITHUB_USER:$GITHUB_TOKEN" \
        "https://api.github.com/user/following/$username" 2>/dev/null)
    
    if [[ "$response" == "204" ]]; then
        return 0  # Success
    else
        return 1  # Failed
    fi
}

# --- UNFOLLOW USER FUNCTION ---
unfollow_user() {
    local username=$1
    local response=$(curl -s -w "%{http_code}" -o /dev/null -X DELETE \
        -u "$GITHUB_USER:$GITHUB_TOKEN" \
        "https://api.github.com/user/following/$username" 2>/dev/null)
    
    if [[ "$response" == "204" ]]; then
        return 0  # Success
    else
        return 1  # Failed
    fi
}

# --- MASS FOLLOW FUNCTION ---
mass_follow() {
    local users_to_follow=("$@")
    local success_count=0
    local failed_count=0
    
    echo -e "${GREEN}${BOLD}STARTING MASS FOLLOW PROCESS...${NC}"
    echo -e "${YELLOW}${BOLD}TARGET: ${#users_to_follow[@]} ACCOUNTS${NC}"
    echo
    
    for user in "${users_to_follow[@]}"; do
        echo -ne "${YELLOW}FOLLOWING ${WHITE}${user}${NC}... "
        
        if follow_user "$user"; then
            echo -e "${GREEN}✓ SUCCESS${NC}"
            ((success_count++))
        else
            echo -e "${RED}✗ FAILED${NC}"
            ((failed_count++))
        fi
        
        # Rate limiting: wait 1 second between requests
        sleep 1
    done
    
    echo
    echo -e "${GREEN}${BOLD}FOLLOW SUMMARY:${NC}"
    echo -e "${WHITE}• SUCCESSFULLY FOLLOWED: ${GREEN}${success_count}${NC}"
    echo -e "${WHITE}• FAILED TO FOLLOW: ${RED}${failed_count}${NC}"
    echo
}

# --- MASS UNFOLLOW FUNCTION ---
mass_unfollow() {
    local unfollowed_users=("$@")
    local success_count=0
    local failed_count=0
    
    echo -e "${RED}${BOLD}STARTING MASS UNFOLLOW PROCESS...${NC}"
    echo -e "${YELLOW}${BOLD}TARGET: ${#unfollowed_users[@]} ACCOUNTS${NC}"
    echo
    
    for user in "${unfollowed_users[@]}"; do
        echo -ne "${YELLOW}UNFOLLOWING ${WHITE}${user}${NC}... "
        
        if unfollow_user "$user"; then
            echo -e "${GREEN}✓ SUCCESS${NC}"
            ((success_count++))
        else
            echo -e "${RED}✗ FAILED${NC}"
            ((failed_count++))
        fi
        
        # Rate limiting: wait 1 second between requests
        sleep 1
    done
    
    echo
    echo -e "${GREEN}${BOLD}UNFOLLOW SUMMARY:${NC}"
    echo -e "${WHITE}• SUCCESSFULLY UNFOLLOWED: ${GREEN}${success_count}${NC}"
    echo -e "${WHITE}• FAILED TO UNFOLLOW: ${RED}${failed_count}${NC}"
    echo
}

# --- GET USER EVENTS ---
get_user_events() {
    local response=$(curl -s -u "$GITHUB_USER:$GITHUB_TOKEN" \
        "https://api.github.com/users/$GITHUB_USER/events/public?per_page=30" 2>/dev/null)
    
    if [[ -n "$response" ]] && echo "$response" | jq . >/dev/null 2>&1; then
        echo "$response" | jq -r '.[] | "\(.type)|\(.created_at)|\(.repo.name // "N/A")"' | head -10
    fi
}

# --- GET FOLLOWERS WITH DATES ---
get_detailed_followers() {
    local page=1
    
    while true; do
        local response=$(curl -s -u "$GITHUB_USER:$GITHUB_TOKEN" \
            "https://api.github.com/users/$GITHUB_USER/followers?per_page=$PER_PAGE&page=$page" 2>/dev/null)
        
        if [[ -z "$response" ]] || ! echo "$response" | jq -e 'type == "array"' >/dev/null 2>&1; then
            break
        fi
        
        local count=$(echo "$response" | jq length 2>/dev/null)
        [[ "$count" -eq 0 ]] && break
        
        echo "$response" | jq -r '.[] | "\(.login)|\(.created_at)"'
        
        ((page++))
    done
}

# --- GET SIMPLE REPOSITORIES FUNCTION ---
get_all_repos() {
    local page=1
    local all_repos=()
    
    while true; do
        local response=$(curl -s -u "$GITHUB_USER:$GITHUB_TOKEN" \
            "https://api.github.com/users/$GITHUB_USER/repos?type=public&per_page=$PER_PAGE&page=$page" 2>/dev/null)
        
        if [[ -z "$response" ]] || ! echo "$response" | jq -e 'type == "array"' >/dev/null 2>&1; then
            echo -e "${RED}${BOLD}ERROR: INVALID REPOS API RESPONSE${NC}" >&2
            break
        fi
        
        local count=$(echo "$response" | jq length 2>/dev/null)
        [[ "$count" -eq 0 ]] && break
        
        local repo_names=$(echo "$response" | jq -r '.[].name' 2>/dev/null)
        all_repos+=($repo_names)
        
        ((page++))
    done
    
    printf '%s\n' "${all_repos[@]}"
}

# --- OPTIMIZED FETCH FUNCTION ---
get_all_users() {
    local endpoint=$1
    local page=1
    local all_users=()
    
    while true; do
        local response
        response=$(curl -fsS -u "$GITHUB_USER:$GITHUB_TOKEN" \
            "https://api.github.com/users/$GITHUB_USER/$endpoint?per_page=$PER_PAGE&page=$page" 2>/dev/null) || return 1
        
        # Check if response is valid
        if [[ -z "$response" ]] || ! echo "$response" | jq -e 'type == "array" and all(.[]; (.login | type == "string") and (.login | test("^[A-Za-z0-9-]+$")))' >/dev/null 2>&1; then
            echo -e "${RED}${BOLD}ERROR: INVALID API RESPONSE${NC}" >&2
            return 1
        fi
        
        local count=$(echo "$response" | jq length 2>/dev/null)
        [[ "$count" -eq 0 ]] && break
        
        local logins=$(echo "$response" | jq -r '.[].login' 2>/dev/null)
        all_users+=($logins)
        
        ((page++))
    done
    
    printf '%s\n' "${all_users[@]}"
}

# --- MAIN FUNCTION ---
main() {
    case ${1:-} in
        -h|--help) show_help; return 0 ;;
        '') ;;
        *) show_help >&2; return 2 ;;
    esac
    init_ui
    if ((BASH_VERSINFO[0] < 4)); then
        printf 'ERROR: Bash 4 or newer is required.\n' >&2
        return 1
    fi
    if [[ -z $GITHUB_USER || -z $GITHUB_TOKEN ]]; then
        printf 'ERROR: Set GITHUB_USER and GITHUB_TOKEN first; see --help.\n' >&2
        return 1
    fi
    show_header
    
    # Check dependencies
    if ! command -v curl &> /dev/null || ! command -v jq &> /dev/null; then
        echo -e "${RED}${BOLD}ERROR: CURL AND JQ ARE REQUIRED${NC}"
        exit 1
    fi
    
    # Get user information
    echo -e "${YELLOW}${BOLD}FETCHING USER INFORMATION...${NC}"
    user_info=$(get_user_info)
    if [[ $? -eq 0 ]]; then
        IFS='|' read -r user_name public_repos_count <<< "$user_info"
        echo -e "${GREEN}✓ USER INFO RETRIEVED${NC}"
    else
        user_name="N/A"
        public_repos_count="0"
    fi
    echo
    
    # Get repositories (simple list)
    echo -e "${YELLOW}${BOLD}FETCHING PUBLIC REPOSITORIES...${NC}"
    repos_list=($(get_all_repos))
    echo -e "${GREEN}✓ ${#repos_list[@]} REPOSITORIES RETRIEVED${NC}"
    echo
    
    # Get user events
    echo -e "${YELLOW}${BOLD}FETCHING RECENT ACTIVITY EVENTS...${NC}"
    events_data=$(get_user_events)
    echo -e "${GREEN}✓ RECENT EVENTS RETRIEVED${NC}"
    echo
    
    # Get detailed followers
    echo -e "${YELLOW}${BOLD}FETCHING DETAILED FOLLOWERS INFORMATION...${NC}"
    detailed_followers_data=$(get_detailed_followers)
    echo -e "${GREEN}✓ DETAILED FOLLOWERS DATA RETRIEVED${NC}"
    echo
    
    # Fetch data
    echo -e "${YELLOW}${BOLD}FETCHING ACCOUNTS YOU FOLLOW...${NC}"
    local following_data followers_data
    following_data=$(get_all_users "following") || {
        printf 'ERROR: Following list unavailable; no actions offered.\n' >&2
        return 1
    }
    following_list=($following_data)
    echo -e "${GREEN}✓ ${#following_list[@]} ACCOUNTS RETRIEVED${NC}"
    echo
    
    echo -e "${YELLOW}${BOLD}FETCHING ACCOUNTS THAT FOLLOW YOU...${NC}"
    followers_data=$(get_all_users "followers") || {
        printf 'ERROR: Followers list unavailable; no actions offered.\n' >&2
        return 1
    }
    followers_list=($followers_data)
    echo -e "${GREEN}✓ ${#followers_list[@]} FOLLOWERS RETRIEVED${NC}"
    echo
    
    # Create followers and following maps for optimized lookup
    declare -A followers_map
    declare -A following_map
    
    for user in "${followers_list[@]}"; do
        followers_map["$user"]=1
    done
    
    for user in "${following_list[@]}"; do
        following_map["$user"]=1
    done
    
    # Analysis and display
    echo -e "${BLUE}${BOLD}ANALYZING DATA...${NC}"
    echo
    
    # Users you follow but don't follow you back
    local unfollowed_count=0
    local unfollowed_users=()
    
    for user in "${following_list[@]}"; do
        if [[ -z "${followers_map[$user]}" ]]; then
            unfollowed_users+=("$user")
            ((unfollowed_count++))
        fi
    done
    
    # Users who follow you but you don't follow back
    local not_followed_back_count=0
    local not_followed_back_users=()
    
    for user in "${followers_list[@]}"; do
        if [[ -z "${following_map[$user]}" ]]; then
            not_followed_back_users+=("$user")
            ((not_followed_back_count++))
        fi
    done
    
    # Summary first, followed by the exact action targets.
    ui_section 'Overview'
    ui_metric 'Account' "$GITHUB_USER"
    ui_metric 'Name' "$user_name"
    ui_metric 'Public repositories' "${#repos_list[@]}"
    ui_section 'Connections'
    ui_metric 'Following' "${#following_list[@]}"
    ui_metric 'Followers' "${#followers_list[@]}"
    ui_metric 'Mutual connections' "$((${#following_list[@]} - unfollowed_count))"
    ui_metric 'Not following back' "$unfollowed_count"
    ui_metric 'To follow back' "$not_followed_back_count"
    show_accounts 'Not following you back' "${unfollowed_users[@]}"
    show_accounts 'Followers to follow back' "${not_followed_back_users[@]}"

    if ((unfollowed_count > 0 || not_followed_back_count > 0)); then
        action_menu
    else
        printf '\nAll connections are mutual. No action needed.\n'
    fi

    # Display simple repositories list
    if [[ ${#repos_list[@]} -gt 0 ]]; then
        echo -e "${BLUE}${BOLD}PUBLIC REPOSITORIES (${#repos_list[@]} TOTAL):${NC}"
        ui_rule
        
        # Show first 15 repos, then summarize
        local displayed=0
        for repo in "${repos_list[@]}"; do
            if [[ $displayed -lt 15 ]]; then
                echo -e "${CYAN}• ${WHITE}${repo}${NC}"
                ((displayed++))
            fi
        done
        
        if [[ ${#repos_list[@]} -gt 15 ]]; then
            echo -e "${YELLOW}   ... and $((${#repos_list[@]} - 15)) more repositories${NC}"
        fi
        echo
    else
        echo -e "${YELLOW}${BOLD}NO PUBLIC REPOSITORIES FOUND${NC}"
        echo
    fi
    
    # Display recent events
    if [[ -n "$events_data" ]]; then
        echo -e "${PURPLE}${BOLD}RECENT ACTIVITY EVENTS:${NC}"
        ui_rule
        
        while IFS='|' read -r event_type date repo_name; do
            local formatted_date=$(date -d "${date}" '+%Y-%m-%d %H:%M' 2>/dev/null || echo "${date}")
            case "$event_type" in
                "PushEvent")
                    echo -e "${GREEN}📤 PUSH${NC} to ${CYAN}${repo_name}${NC} on ${BLUE}${formatted_date}${NC}"
                    ;;
                "CreateEvent")
                    echo -e "${YELLOW}➕ CREATE${NC} in ${CYAN}${repo_name}${NC} on ${BLUE}${formatted_date}${NC}"
                    ;;
                "WatchEvent")
                    echo -e "${PURPLE}⭐ STARRED${NC} ${CYAN}${repo_name}${NC} on ${BLUE}${formatted_date}${NC}"
                    ;;
                "ForkEvent")
                    echo -e "${RED}🍴 FORKED${NC} ${CYAN}${repo_name}${NC} on ${BLUE}${formatted_date}${NC}"
                    ;;
                *)
                    echo -e "${WHITE}🔄 ${event_type}${NC} in ${CYAN}${repo_name}${NC} on ${BLUE}${formatted_date}${NC}"
                    ;;
            esac
        done <<< "$events_data"
        echo
    fi
    
    # Display detailed followers info
    if [[ -n "$detailed_followers_data" ]]; then
        echo -e "${CYAN}${BOLD}FOLLOWERS (FIRST 10):${NC}"
        ui_rule
        
        echo "$detailed_followers_data" | head -10 | while IFS='|' read -r login created_at; do
            printf '  - %s\n' "$login"
        done
        echo
    fi
    
    echo
    ui_rule
    echo -e "${GREEN}${BOLD}ANALYSIS COMPLETED!${NC}"
    ui_rule
}

# --- EXECUTION ---
if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
    main "$@"
fi
