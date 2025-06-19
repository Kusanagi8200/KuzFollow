#!/bin/bash

# =============================================================================
# GITHUB FOLLOWERS ANALYSIS SCRIPT
# =============================================================================

# --- CONFIGURATION ---
GITHUB_USER="USER"
GITHUB_TOKEN="YOURTOKENGITHUB"
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

# --- ASCII ART ---
show_header() {
    echo -e "${CYAN}${BOLD}"
    echo "███████╗ ██████╗ ██╗     ██╗      ██████╗ ██╗    ██╗███████╗██████╗ ███████╗"
    echo "██╔════╝██╔═══██╗██║     ██║     ██╔═══██╗██║    ██║██╔════╝██╔══██╗██╔════╝"
    echo "█████╗  ██║   ██║██║     ██║     ██║   ██║██║ █╗ ██║█████╗  ██████╔╝███████╗"
    echo "██╔══╝  ██║   ██║██║     ██║     ██║   ██║██║███╗██║██╔══╝  ██╔══██╗╚════██║"
    echo "██║     ╚██████╔╝███████╗███████╗╚██████╔╝╚███╔███╔╝███████╗██║  ██║███████║"
    echo "╚═╝      ╚═════╝ ╚══════╝╚══════╝ ╚═════╝  ╚══╝╚══╝ ╚══════╝╚═╝  ╚═╝╚══════╝"
    echo -e "${NC}"
    echo -e "${PURPLE}${BOLD}════════════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${WHITE}${BOLD}                      GITHUB FOLLOWERS ANALYZER                               ${NC}"
    echo -e "${PURPLE}${BOLD}════════════════════════════════════════════════════════════════════════════════${NC}"
    echo
}

# --- SPINNER FUNCTION ---
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
        
        if [[ -z "$response" ]] || ! echo "$response" | jq . >/dev/null 2>&1; then
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
        
        if [[ -z "$response" ]] || ! echo "$response" | jq . >/dev/null 2>&1; then
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
        local response=$(curl -s -u "$GITHUB_USER:$GITHUB_TOKEN" \
            "https://api.github.com/users/$GITHUB_USER/$endpoint?per_page=$PER_PAGE&page=$page" 2>/dev/null)
        
        # Check if response is valid
        if [[ -z "$response" ]] || ! echo "$response" | jq . >/dev/null 2>&1; then
            echo -e "${RED}${BOLD}ERROR: INVALID API RESPONSE${NC}" >&2
            break
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
    following_list=($(get_all_users "following"))
    echo -e "${GREEN}✓ ${#following_list[@]} ACCOUNTS RETRIEVED${NC}"
    echo
    
    echo -e "${YELLOW}${BOLD}FETCHING ACCOUNTS THAT FOLLOW YOU...${NC}"
    followers_list=($(get_all_users "followers"))
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
    
    # Display results
    echo -e "${PURPLE}${BOLD}═══════════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${WHITE}${BOLD}                                RESULTS                                        ${NC}"
    echo -e "${PURPLE}${BOLD}═══════════════════════════════════════════════════════════════════════════════${NC}"
    echo
    echo -e "${CYAN}${BOLD}USER INFORMATION:${NC}"
    echo -e "${WHITE}• USERNAME        : ${GREEN}${BOLD}${GITHUB_USER}${NC}"
    echo -e "${WHITE}• DISPLAY NAME    : ${GREEN}${BOLD}${user_name}${NC}"
    echo -e "${WHITE}• PUBLIC REPOS    : ${GREEN}${BOLD}${#repos_list[@]}${NC} ${WHITE}REPOSITORIES${NC}"
    echo
    echo -e "${CYAN}${BOLD}FOLLOW STATISTICS:${NC}"
    echo -e "${WHITE}• YOU FOLLOW      : ${GREEN}${BOLD}${#following_list[@]}${NC} ${WHITE}ACCOUNTS${NC}"
    echo -e "${WHITE}• FOLLOW YOU      : ${GREEN}${BOLD}${#followers_list[@]}${NC} ${WHITE}ACCOUNTS${NC}"
    echo -e "${WHITE}• DON'T FOLLOW BACK : ${RED}${BOLD}${unfollowed_count}${NC} ${WHITE}ACCOUNTS${NC}"
    echo -e "${WHITE}• YOU DON'T FOLLOW BACK : ${BLUE}${BOLD}${not_followed_back_count}${NC} ${WHITE}ACCOUNTS${NC}"
    echo
    
    # Display accounts you follow but don't follow you back
    if [[ $unfollowed_count -gt 0 ]]; then
        echo -e "${RED}${BOLD}ACCOUNTS THAT DON'T FOLLOW YOU BACK:${NC}"
        echo -e "${RED}${BOLD}════════════════════════════════════════${NC}"
        
        for user in "${unfollowed_users[@]}"; do
            echo -e "${YELLOW}• ${WHITE}${user}${NC}"
        done
        echo
    fi
    
    # Display followers you don't follow back
    if [[ $not_followed_back_count -gt 0 ]]; then
        echo -e "${BLUE}${BOLD}FOLLOWERS YOU DON'T FOLLOW BACK:${NC}"
        echo -e "${BLUE}${BOLD}═══════════════════════════════════${NC}"
        
        for user in "${not_followed_back_users[@]}"; do
            echo -e "${CYAN}• ${WHITE}${user}${NC}"
        done
        echo
    fi
    
    # Interactive menu
    if [[ $unfollowed_count -gt 0 ]] || [[ $not_followed_back_count -gt 0 ]]; then
        echo -e "${PURPLE}${BOLD}ACTION MENU:${NC}"
        echo -e "${WHITE}What would you like to do?${NC}"
        echo
        
        if [[ $unfollowed_count -gt 0 ]]; then
            echo -e "${CYAN}[1]${NC} Unfollow accounts that don't follow you back (${RED}${unfollowed_count}${NC} accounts)"
        fi
        
        if [[ $not_followed_back_count -gt 0 ]]; then
            echo -e "${CYAN}[2]${NC} Follow back your followers (${BLUE}${not_followed_back_count}${NC} accounts)"
        fi
        
        echo -e "${CYAN}[3]${NC} Show detailed lists again"
        echo -e "${CYAN}[4]${NC} Do nothing and exit"
        echo
        
        read -p "Enter your choice [1-4]: " choice
        echo
        
        case $choice in
            1)
                if [[ $unfollowed_count -gt 0 ]]; then
                    echo -e "${RED}${BOLD}⚠️  WARNING: THIS WILL UNFOLLOW ${unfollowed_count} ACCOUNTS! ⚠️${NC}"
                    echo -e "${WHITE}Are you absolutely sure? This action cannot be undone easily.${NC}"
                    read -p "Type 'YES' to confirm: " confirm
                    echo
                    
                    if [[ "$confirm" == "YES" ]]; then
                        mass_unfollow "${unfollowed_users[@]}"
                    else
                        echo -e "${YELLOW}${BOLD}OPERATION CANCELLED${NC}"
                        echo
                    fi
                else
                    echo -e "${RED}${BOLD}NO ACCOUNTS TO UNFOLLOW${NC}"
                fi
                ;;
            2)
                if [[ $not_followed_back_count -gt 0 ]]; then
                    echo -e "${GREEN}${BOLD}🎯 READY TO FOLLOW ${not_followed_back_count} ACCOUNTS!${NC}"
                    echo -e "${WHITE}This will follow back all your followers that you're not following yet.${NC}"
                    read -p "Type 'YES' to confirm: " confirm
                    echo
                    
                    if [[ "$confirm" == "YES" ]]; then
                        mass_follow "${not_followed_back_users[@]}"
                    else
                        echo -e "${YELLOW}${BOLD}OPERATION CANCELLED${NC}"
                        echo
                    fi
                else
                    echo -e "${GREEN}${BOLD}NO NEW FOLLOWERS TO FOLLOW BACK${NC}"
                fi
                ;;
            3)
                if [[ $unfollowed_count -gt 0 ]]; then
                    echo -e "${RED}${BOLD}ACCOUNTS THAT DON'T FOLLOW YOU BACK:${NC}"
                    for user in "${unfollowed_users[@]}"; do
                        echo -e "${YELLOW}• ${WHITE}${user}${NC}"
                    done
                    echo
                fi
                
                if [[ $not_followed_back_count -gt 0 ]]; then
                    echo -e "${BLUE}${BOLD}FOLLOWERS YOU DON'T FOLLOW BACK:${NC}"
                    for user in "${not_followed_back_users[@]}"; do
                        echo -e "${CYAN}• ${WHITE}${user}${NC}"
                    done
                    echo
                fi
                ;;
            4)
                echo -e "${GREEN}${BOLD}NO CHANGES MADE${NC}"
                echo
                ;;
            *)
                echo -e "${RED}${BOLD}INVALID CHOICE - NO ACTION TAKEN${NC}"
                echo
                ;;
        esac
        
    else
        echo -e "${GREEN}${BOLD}PERFECT BALANCE! ALL YOUR FOLLOWINGS FOLLOW YOU BACK AND YOU FOLLOW ALL YOUR FOLLOWERS!${NC}"
        echo
    fi
    
    # Display simple repositories list
    if [[ ${#repos_list[@]} -gt 0 ]]; then
        echo -e "${BLUE}${BOLD}PUBLIC REPOSITORIES (${#repos_list[@]} TOTAL):${NC}"
        echo -e "${BLUE}${BOLD}════════════════════════════════════${NC}"
        
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
        echo -e "${PURPLE}${BOLD}═══════════════════════${NC}"
        
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
        echo -e "${CYAN}${BOLD}RECENT FOLLOWERS (LAST 10):${NC}"
        echo -e "${CYAN}${BOLD}════════════════════════════${NC}"
        
        echo "$detailed_followers_data" | head -10 | while IFS='|' read -r login created_at; do
            local join_date=$(date -d "${created_at}" '+%Y-%m-%d' 2>/dev/null || echo "${created_at}")
            echo -e "${WHITE}👤 ${GREEN}${login}${NC} - Joined GitHub: ${BLUE}${join_date}${NC}"
        done
        echo
    fi
    
    echo
    echo -e "${PURPLE}${BOLD}═══════════════════════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}${BOLD}ANALYSIS COMPLETED!${NC}"
    echo -e "${PURPLE}${BOLD}═══════════════════════════════════════════════════════════════════════════════${NC}"
}

# --- EXECUTION ---
main "$@"
