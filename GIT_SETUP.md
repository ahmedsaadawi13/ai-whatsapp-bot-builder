# Git Setup Guide

This guide helps you set up Git and push the AI WhatsApp Bot Builder to GitHub.

## Prerequisites

- Git installed on your system
- GitHub CLI (`gh`) installed (optional but recommended)
- GitHub account

## Quick Setup (With GitHub CLI)

If you have GitHub CLI installed:

```bash
# Navigate to project directory
cd /home/user/ai-whatsapp-bot-builder

# Initialize git repository
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: AI WhatsApp Bot Builder - Complete Multi-Tenant SaaS Platform

Features:
- Multi-tenant architecture with complete isolation
- Bot builder with flow design
- AI-powered responses with knowledge base
- Agent inbox for human takeover
- Broadcast campaigns
- Subscription management with usage limits
- Billing and invoicing
- Webhook integrations
- RESTful public API
- Analytics dashboard
- Template management
- Contact management
- Full CRUD operations for all entities

Tech Stack:
- PHP 7.x - 8.x compatible
- MySQL with InnoDB
- Custom lightweight MVC framework
- Vanilla JavaScript
- Responsive CSS

Security Features:
- CSRF protection
- SQL injection prevention (prepared statements)
- Password hashing (bcrypt)
- Input validation and sanitization
- File upload security
- Tenant data isolation

Includes:
- Complete database schema with seed data
- Comprehensive documentation
- Deployment guide
- Testing suite
- Code review and best practices"

# Set main branch
git branch -M main

# Create GitHub repository (public)
gh repo create ai-whatsapp-bot-builder --public --source=. --remote=origin --push

# Or for private repository
# gh repo create ai-whatsapp-bot-builder --private --source=. --remote=origin --push
```

## Manual Setup (Without GitHub CLI)

### 1. Initialize Local Repository

```bash
cd /home/user/ai-whatsapp-bot-builder

git init

git add .

git commit -m "Initial commit: AI WhatsApp Bot Builder"

git branch -M main
```

### 2. Create GitHub Repository

1. Go to https://github.com/new
2. Enter repository name: `ai-whatsapp-bot-builder`
3. Choose Public or Private
4. Do NOT initialize with README (we already have one)
5. Click "Create repository"

### 3. Push to GitHub

```bash
# Add remote (replace YOUR_USERNAME with your GitHub username)
git remote add origin https://github.com/YOUR_USERNAME/ai-whatsapp-bot-builder.git

# Push to main branch
git push -u origin main
```

## Alternative: Using SSH

If you prefer SSH authentication:

```bash
# Add remote with SSH
git remote add origin git@github.com:YOUR_USERNAME/ai-whatsapp-bot-builder.git

# Push to main branch
git push -u origin main
```

## Verify Setup

After pushing, verify:

1. Visit your GitHub repository URL
2. Check that all files are present
3. Verify README.md is displayed
4. Check that database.sql was uploaded

## Create Release (Optional)

To create a release version:

```bash
# Tag the current commit
git tag -a v1.0.0 -m "Release v1.0.0: Initial production-ready version"

# Push the tag
git push origin v1.0.0
```

Or use GitHub CLI:

```bash
gh release create v1.0.0 \
  --title "v1.0.0 - Initial Release" \
  --notes "Complete AI WhatsApp Bot Builder SaaS Platform

## Features
- Multi-tenant architecture
- Bot builder with visual flows
- AI knowledge base integration
- Agent inbox
- Broadcast campaigns
- Subscription & billing
- Public REST API
- Webhook integrations
- Analytics dashboard

## Installation
See README.md for installation instructions.

## Demo
Login credentials included in seed data.

## Support
Report issues on GitHub."
```

## .gitignore

Create `.gitignore` file to exclude sensitive/unnecessary files:

```bash
cat > .gitignore << 'EOF'
# Environment
.env

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Logs
*.log

# Temporary files
/storage/uploads/*
!/storage/uploads/.gitkeep

# Vendor (if using Composer)
/vendor/

# Cache
/cache/
/tmp/
EOF

git add .gitignore
git commit -m "Add .gitignore"
git push
```

## Update Repository Description

Using GitHub CLI:

```bash
gh repo edit --description "Complete Multi-Tenant SaaS platform for building AI-powered WhatsApp bots. Built with PHP, MySQL, and custom MVC framework. Features: bot builder, knowledge base, agent inbox, broadcasts, subscriptions, billing, webhooks, and REST API."

gh repo edit --add-topic "php"
gh repo edit --add-topic "saas"
gh repo edit --add-topic "multi-tenant"
gh repo edit --add-topic "whatsapp-bot"
gh repo edit --add-topic "chatbot"
gh repo edit --add-topic "mvc"
gh repo edit --add-topic "mysql"
gh repo edit --add-topic "ai"
gh repo edit --add-topic "bot-builder"
```

## Post-Push Checklist

- [ ] Repository created on GitHub
- [ ] All files pushed successfully
- [ ] README.md displays correctly
- [ ] License added (if desired)
- [ ] Repository description set
- [ ] Topics/tags added
- [ ] .gitignore configured
- [ ] Initial release created (optional)

## Next Steps

1. **Add License**: Consider adding a LICENSE file (MIT, GPL, etc.)
2. **Enable Issues**: Use for bug tracking
3. **Add Contributing Guidelines**: CONTRIBUTING.md file
4. **Set up GitHub Actions**: For CI/CD (optional)
5. **Add Wiki**: For extended documentation
6. **Enable Discussions**: For community support

## Collaborative Development

### For Contributors

```bash
# Clone the repository
git clone https://github.com/YOUR_USERNAME/ai-whatsapp-bot-builder.git

# Create feature branch
git checkout -b feature/new-feature

# Make changes and commit
git add .
git commit -m "Add new feature"

# Push feature branch
git push origin feature/new-feature

# Create Pull Request on GitHub
gh pr create --title "Add new feature" --body "Description of changes"
```

### For Maintainers

```bash
# Review and merge PR
gh pr review PR_NUMBER --approve
gh pr merge PR_NUMBER

# Or merge locally
git checkout main
git merge feature/new-feature
git push origin main
```

## Troubleshooting

### Authentication Issues

If using HTTPS and prompted for password:

```bash
# Use Personal Access Token instead of password
# Generate token at: https://github.com/settings/tokens
```

### Large File Issues

If database.sql is too large:

```bash
# Use Git LFS
git lfs install
git lfs track "*.sql"
git add .gitattributes
git commit -m "Track SQL files with Git LFS"
```

### Permission Denied

If you get permission denied:

```bash
# Check remote URL
git remote -v

# Update to use SSH
git remote set-url origin git@github.com:YOUR_USERNAME/ai-whatsapp-bot-builder.git
```

---

**Repository is now live on GitHub!** 🚀

Share the repository:
- https://github.com/YOUR_USERNAME/ai-whatsapp-bot-builder

Star the repo, invite collaborators, and start building!
