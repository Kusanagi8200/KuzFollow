# 🔍 GitHub Followers Analyzer

<div align="center">

![GitHub Followers Analyzer](https://img.shields.io/badge/GitHub-Followers_Analyzer-blue?style=for-the-badge&logo=github)
![Bash](https://img.shields.io/badge/Bash-4EAA25?style=for-the-badge&logo=gnu-bash&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)
![Version](https://img.shields.io/badge/Version-2.0-orange?style=for-the-badge)

**A powerful Bash script to analyze and manage your GitHub relationships**

[Installation](#-installation) • [Usage](#-usage) • [Features](#-features) • [Examples](#-examples) • [FAQ](#-faq)

</div>

---

## 📋 Table of Contents

- [🎯 Overview](#-overview)
- [✨ Features](#-features)
- [📦 Installation](#-installation)
- [🚀 Usage](#-usage)
- [⚙️ Configuration](#️-configuration)
- [📊 Output Examples](#-output-examples)
- [🔧 Dependencies](#-dependencies)
- [🛡️ Security](#️-security)
- [❓ FAQ](#-faq)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

---

## 🎯 Overview

The **GitHub Followers Analyzer** is an interactive Bash script that allows you to deeply analyze your GitHub relationships. It identifies who follows you, who you follow, and who doesn't follow you back, while offering automated actions to optimize your network.

### 🎪 Visual Demo

```
███████╗ ██████╗ ██╗     ██╗      ██████╗ ██╗    ██╗███████╗██████╗ ███████╗
██╔════╝██╔═══██╗██║     ██║     ██╔═══██╗██║    ██║██╔════╝██╔══██╗██╔════╝
█████╗  ██║   ██║██║     ██║     ██║   ██║██║ █╗ ██║█████╗  ██████╔╝███████╗
██╔══╝  ██║   ██║██║     ██║     ██║   ██║██║███╗██║██╔══╝  ██╔══██╗╚════██║
██║     ╚██████╔╝███████╗███████╗╚██████╔╝╚███╔███╔╝███████╗██║  ██║███████║
╚═╝      ╚═════╝ ╚══════╝╚══════╝ ╚═════╝  ╚══╝╚══╝ ╚══════╝╚═╝  ╚═╝╚══════╝
```

---

## ✨ Features

### 🔍 **Complete Analysis**
- ✅ **Bidirectional tracking**: Analyzes who follows you and who you follow
- ✅ **Non-reciprocal detection**: Identifies accounts that don't follow you back
- ✅ **Unfollowed followers**: Lists your followers you haven't followed back yet
- ✅ **Detailed statistics**: Complete display of your GitHub metrics

### 🤖 **Automated Actions**
- 🔄 **Mass unfollowing**: Stop following non-reciprocal accounts
- ➕ **Auto-follow**: Follow back your new followers
- 📊 **Detailed reports**: Complete summaries after each action
- ⏱️ **Rate limiting**: Automatic compliance with GitHub API limits

### 📈 **Detailed Information**
- 👤 **User profile**: Name, public repositories, statistics
- 📚 **Repository list**: Overview of your public projects
- 🎯 **Recent activity**: Latest GitHub events
- 📅 **Follower history**: Account creation dates

### 🎨 **User Interface**
- 🌈 **Colorful interface**: Intuitive navigation with color codes
- 📱 **Interactive menu**: Simple and clear action choices
- ⚡ **Real-time feedback**: Live operation progress
- 🛡️ **Secure confirmations**: Protection against accidental actions

---

## 📦 Installation

### 📋 Prerequisites

Make sure you have the following tools installed:

```bash
# Check curl
curl --version

# Check jq
jq --version

# Install if needed (Ubuntu/Debian)
sudo apt update
sudo apt install curl jq

# Install if needed (macOS with Homebrew)
brew install curl jq

# Install if needed (CentOS/RHEL/Fedora)
sudo yum install curl jq
# or
sudo dnf install curl jq
```

### 🔑 GitHub Token Setup

1. **Generate a Personal Access Token**:
   - Go to [GitHub Settings > Developer Settings > Personal Access Tokens](https://github.com/settings/tokens)
   - Click "Generate new token (classic)"
   - Select scopes: `user:follow`, `read:user`
   - Copy the generated token

2. **Clone and configure the script**:
```bash
# Clone the repository
git clone https://github.com/yourusername/github-followers-analyzer.git
cd github-followers-analyzer

# Make the script executable
chmod +x FOLLOWERS.sh

# Edit the configuration
nano FOLLOWERS.sh
```

3. **Update the configuration variables**:
```bash
GITHUB_USER="your_username"
GITHUB_TOKEN="your_personal_access_token"
```

---

## 🚀 Usage

### 🎬 Quick Start

```bash
# Run the script
./FOLLOWERS.sh
```

### 📖 Step-by-Step Guide

1. **Launch the analyzer**:
   ```bash
   ./FOLLOWERS.sh
   ```

2. **Review the analysis**:
   - User information and statistics
   - Lists of followers and following
   - Non-reciprocal relationships

3. **Choose an action**:
   - `[1]` Unfollow accounts that don't follow you back
   - `[2]` Follow back your followers
   - `[3]` Show detailed lists again
   - `[4]` Do nothing and exit

4. **Confirm your choice**:
   - Type `YES` to confirm mass operations
   - Watch the real-time progress

---

## ⚙️ Configuration

### 🔧 Script Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `GITHUB_USER` | Your GitHub username | `"Kusanagi8200"` |
| `GITHUB_TOKEN` | Your personal access token | `"ghp_..."` |
| `PER_PAGE` | Results per API page | `100` |

### 🎨 Color Customization

The script uses ANSI color codes that you can customize:

```bash
RED='\033[0;31m'      # Error messages
GREEN='\033[0;32m'    # Success messages
YELLOW='\033[1;33m'   # Warnings
BLUE='\033[0;34m'     # Information
PURPLE='\033[0;35m'   # Headers
CYAN='\033[0;36m'     # Highlights
```

---

## 📊 Output Examples

### 📈 Sample Analysis Output

```
═══════════════════════════════════════════════════════════════════════════════
                                RESULTS                                        
═══════════════════════════════════════════════════════════════════════════════

USER INFORMATION:
• USERNAME        : Kusanagi8200
• DISPLAY NAME    : John Doe
• PUBLIC REPOS    : 42 REPOSITORIES

FOLLOW STATISTICS:
• YOU FOLLOW      : 156 ACCOUNTS
• FOLLOW YOU      : 98 ACCOUNTS
• DON'T FOLLOW BACK : 23 ACCOUNTS
• YOU DON'T FOLLOW BACK : 12 ACCOUNTS

ACCOUNTS THAT DON'T FOLLOW YOU BACK:
════════════════════════════════════════
• user1
• user2
• user3

FOLLOWERS YOU DON'T FOLLOW BACK:
═══════════════════════════════════
• follower1
• follower2
• follower3
```

### 🎯 Action Menu

```
ACTION MENU:
What would you like to do?

[1] Unfollow accounts that don't follow you back (23 accounts)
[2] Follow back your followers (12 accounts)
[3] Show detailed lists again
[4] Do nothing and exit

Enter your choice [1-4]: 
```

---

## 🔧 Dependencies

| Tool | Purpose | Installation |
|------|---------|--------------|
| **curl** | API requests | `apt install curl` |
| **jq** | JSON parsing | `apt install jq` |
| **bash** | Script execution | Usually pre-installed |
| **date** | Date formatting | Usually pre-installed |

### 🧪 Testing Dependencies

```bash
# Test script
./test_dependencies.sh

# Or manually check
command -v curl >/dev/null 2>&1 || echo "curl is missing"
command -v jq >/dev/null 2>&1 || echo "jq is missing"
```

---

## 🛡️ Security

### 🔐 Token Security

- **Never commit your token** to version control
- **Use environment variables** for sensitive data:
  ```bash
  export GITHUB_TOKEN="your_token_here"
  export GITHUB_USER="your_username"
  ./FOLLOWERS.sh
  ```
- **Regularly rotate** your personal access tokens
- **Use minimal permissions** (only `user:follow`, `read:user`)

### ⚠️ Rate Limiting

The script automatically handles GitHub API rate limits:
- **1 second delay** between follow/unfollow operations
- **Automatic retries** on rate limit errors
- **Progress indicators** for long operations

### 🔒 Confirmation Requirements

All destructive actions require explicit confirmation:
- Type `YES` (case-sensitive) to confirm
- Mass operations show warning messages
- Operation summaries after completion

---

## ❓ FAQ

### 🤔 Common Questions

**Q: Why do I need a personal access token?**
A: GitHub's API requires authentication for follow/unfollow operations and accessing private information.

**Q: Is it safe to use this script?**
A: Yes, the script only uses minimal permissions and includes safety confirmations for all actions.

**Q: Can I run this on multiple accounts?**
A: Yes, just change the `GITHUB_USER` and `GITHUB_TOKEN` variables for each account.

**Q: What if the script fails?**
A: Check your internet connection, token validity, and ensure all dependencies are installed.

**Q: How often should I run this?**
A: It depends on your activity, but weekly or monthly runs are usually sufficient.

### 🐛 Troubleshooting

**Error: "jq: command not found"**
```bash
# Install jq
sudo apt install jq  # Ubuntu/Debian
brew install jq      # macOS
```

**Error: "Invalid API response"**
```bash
# Check your token and username
echo "User: $GITHUB_USER"
echo "Token: ${GITHUB_TOKEN:0:10}..."  # Show only first 10 chars
```

**Error: "Rate limit exceeded"**
- Wait for the rate limit to reset (usually 1 hour)
- The script will automatically handle this in future versions

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

### 🚀 Getting Started

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Commit your changes**: `git commit -m 'Add amazing feature'`
4. **Push to the branch**: `git push origin feature/amazing-feature`
5. **Open a Pull Request**

### 📝 Contribution Guidelines

- Follow the existing code style
- Add comments for complex logic
- Test your changes thoroughly
- Update documentation as needed

### 🐛 Bug Reports

When reporting bugs, please include:
- Your operating system
- Bash version (`bash --version`)
- Steps to reproduce
- Expected vs actual behavior
- Any error messages

### 💡 Feature Requests

We'd love to hear your ideas! Please:
- Check existing issues first
- Describe the use case clearly
- Explain why it would be useful
- Consider implementation complexity

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

### 📜 MIT License Summary

- ✅ **Commercial use** allowed
- ✅ **Modification** allowed
- ✅ **Distribution** allowed
- ✅ **Private use** allowed
- ❌ **Liability** not provided
- ❌ **Warranty** not provided

---

## 🙏 Acknowledgments

- Thanks to all contributors and users
- Inspired by the need for better GitHub relationship management
- Built with ❤️ for the open-source community

---

## 📞 Support

- 🐛 **Bug Reports**: [Open an issue](https://github.com/yourusername/github-followers-analyzer/issues)
- 💬 **Discussions**: [Join the discussion](https://github.com/yourusername/github-followers-analyzer/discussions)
- 📧 **Contact**: [Email us](mailto:your-email@example.com)

---

<div align="center">

**⭐ If this project helped you, please give it a star! ⭐**

Made with ❤️ by [YourName](https://github.com/yourusername)

</div>
