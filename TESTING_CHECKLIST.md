# Testing Checklist - AI WhatsApp Bot Builder

## Authentication Tests

### Registration
- [ ] User can register with valid information
- [ ] Duplicate email is rejected
- [ ] Password confirmation must match
- [ ] Weak passwords are rejected (< 6 characters)
- [ ] Tenant is created along with user
- [ ] Subscription is created with selected plan
- [ ] User is automatically logged in after registration
- [ ] CSRF token is validated

### Login
- [ ] User can login with correct credentials
- [ ] Login fails with incorrect password
- [ ] Login fails with non-existent email
- [ ] Inactive users cannot login
- [ ] Session is created after successful login
- [ ] Last login timestamp is updated
- [ ] CSRF token is validated

### Logout
- [ ] User can logout successfully
- [ ] Session is destroyed after logout
- [ ] User is redirected to login page
- [ ] Protected pages redirect to login after logout

## Tenant Management Tests (Platform Admin)

### CRUD Operations
- [ ] Platform admin can view all tenants
- [ ] Platform admin can create new tenant
- [ ] Tenant slug is auto-generated from name
- [ ] Duplicate slugs are handled correctly
- [ ] Platform admin can edit tenant
- [ ] Platform admin can delete tenant
- [ ] Deleting tenant cascades to related data
- [ ] Regular users cannot access tenant management

### Tenant Details
- [ ] Tenant details page shows subscription info
- [ ] Usage statistics are displayed
- [ ] Associated users are listed

## User Management Tests

### CRUD Operations
- [ ] Tenant admin can view users in their tenant
- [ ] Tenant admin can create new users
- [ ] User email must be unique
- [ ] Users are created with correct tenant_id
- [ ] Tenant admin can edit users
- [ ] Tenant admin can delete users
- [ ] Tenant admin cannot view users from other tenants
- [ ] Agents cannot access user management

### User Roles
- [ ] Tenant admin can assign roles
- [ ] Role validation works (platform_admin, tenant_admin, agent, developer)
- [ ] Users can only access features allowed by their role

## Bot Management Tests

### CRUD Operations
- [ ] User can create a bot
- [ ] Bot is associated with correct tenant
- [ ] Bot requires valid channel
- [ ] User can edit bot
- [ ] User can delete bot
- [ ] User cannot access bots from other tenants
- [ ] Deleting bot cascades to flows

### Bot Configuration
- [ ] AI settings can be configured
- [ ] AI tone options work (friendly, formal, concise, professional)
- [ ] Default language can be set
- [ ] Bot status can be toggled (active/paused/draft)

## Channel Management Tests

### CRUD Operations
- [ ] User can create WhatsApp channel
- [ ] Phone number validation works
- [ ] Provider types are selectable
- [ ] Webhook token is auto-generated
- [ ] User can edit channel
- [ ] User can delete channel
- [ ] Cannot delete channel with active bots

### Channel Limits
- [ ] Subscription plan limits are enforced
- [ ] Error shown when channel limit exceeded
- [ ] Channel count updates usage tracking

## Flow & Flow Steps Tests

### Flow Operations
- [ ] User can create flow for bot
- [ ] Trigger types work correctly (keyword, message_contains, default)
- [ ] Priority ordering works
- [ ] User can edit flow
- [ ] User can delete flow
- [ ] Flow belongs to correct tenant

### Flow Steps
- [ ] User can add steps to flow
- [ ] Step order can be configured
- [ ] Action types work correctly
- [ ] Action config is stored as JSON
- [ ] Steps can be edited
- [ ] Steps can be deleted
- [ ] Step execution follows correct order

### Flow Execution
- [ ] Keyword triggers match correctly
- [ ] Message contains triggers work
- [ ] Default flow is used as fallback
- [ ] send_text action sends message
- [ ] ask_question action sets variable
- [ ] call_ai action triggers AI response

## Knowledge Base Tests

### CRUD Operations
- [ ] User can create KB entry
- [ ] Entry can be associated with bot or global
- [ ] Fulltext search works on questions/answers
- [ ] User can edit KB entry
- [ ] User can delete KB entry
- [ ] Cannot access other tenants' KB

### AI Search
- [ ] Search returns relevant results
- [ ] Results sorted by relevance
- [ ] Multiple matching entries handled
- [ ] No results returns null

## Contact Management Tests

### CRUD Operations
- [ ] User can create contact
- [ ] Phone number format is validated
- [ ] Duplicate phone numbers handled (per tenant)
- [ ] Contact can be edited
- [ ] Contact can be deleted
- [ ] Tags can be assigned to contacts
- [ ] Custom fields can be stored

### Contact Search
- [ ] Search by name works
- [ ] Search by phone number works
- [ ] Search by email works
- [ ] Filter by status works
- [ ] Pagination works correctly

## Conversation & Messaging Tests

### Conversation Management
- [ ] Conversation created with first message
- [ ] Conversation linked to correct contact, bot, channel
- [ ] Conversation status can be updated (open/pending/closed)
- [ ] Agent can be assigned to conversation
- [ ] Last message timestamp updates correctly

### Messaging
- [ ] Inbound messages are stored correctly
- [ ] Outbound messages are stored correctly
- [ ] Message type is recorded (text, media, template)
- [ ] Triggered_by is tracked (flow, ai, agent, api)
- [ ] Message history displays chronologically
- [ ] Agent can send manual messages
- [ ] Messages belong to correct tenant

### Inbox
- [ ] Inbox shows all conversations
- [ ] Filter by status works
- [ ] Filter by assigned agent works
- [ ] Unread count is accurate
- [ ] Pagination works
- [ ] Cannot view other tenant's conversations

## Template Management Tests

### CRUD Operations
- [ ] User can create message template
- [ ] Template variables can be defined
- [ ] Template categories work
- [ ] Template language can be set
- [ ] User can edit template
- [ ] User can delete template

### Template Parsing
- [ ] Variables are replaced correctly ({{name}})
- [ ] Multiple variables in one template work
- [ ] Missing variables handled gracefully

## Broadcast Tests

### Broadcast Management
- [ ] User can create broadcast
- [ ] Channel and template can be selected
- [ ] Target filter can be configured
- [ ] Broadcast status progression works
- [ ] Broadcast can be deleted
- [ ] Cannot access other tenant's broadcasts

### Broadcast Execution (Simulated)
- [ ] Target contacts are identified
- [ ] Broadcast messages created for each contact
- [ ] Message count statistics update
- [ ] Send/fail counts are tracked

## Webhook Tests

### Webhook Management
- [ ] User can create webhook
- [ ] URL validation works
- [ ] Events can be selected
- [ ] Secret token is generated
- [ ] Webhook can be edited
- [ ] Webhook can be deleted

### Webhook Logging
- [ ] Webhook calls are logged
- [ ] Response code is recorded
- [ ] Payload and response stored
- [ ] Retry attempts tracked
- [ ] Logs can be viewed

## Subscription & Billing Tests

### Subscription
- [ ] Active subscription is displayed
- [ ] Plan limits are shown
- [ ] Current usage is displayed
- [ ] Usage updates in real-time
- [ ] Plan can be changed
- [ ] Old subscription is cancelled on plan change

### Usage Tracking
- [ ] Channel count is accurate
- [ ] Bot count is accurate
- [ ] Contact count is accurate
- [ ] Message count is accurate
- [ ] Storage usage is calculated
- [ ] Usage recalculates correctly

### Quota Enforcement
- [ ] Cannot exceed max channels
- [ ] Cannot exceed max bots
- [ ] Cannot exceed max contacts
- [ ] Cannot exceed max messages per month
- [ ] Clear error messages shown when limit exceeded

### Billing
- [ ] Invoices are displayed
- [ ] Invoice details are correct
- [ ] Payment can be processed
- [ ] Invoice status updates to paid
- [ ] Payment transaction is recorded
- [ ] Billing history is accessible

## Settings Tests

### Tenant Settings
- [ ] Tenant admin can update settings
- [ ] Name, email, phone can be updated
- [ ] Industry can be set
- [ ] Timezone can be changed
- [ ] Changes are saved correctly

### API Keys
- [ ] User can view API keys
- [ ] New API key can be generated
- [ ] Raw key is shown only once
- [ ] API key can be revoked
- [ ] Revoked keys cannot be used
- [ ] Key prefix is stored

## Public API Tests

### Authentication
- [ ] Request without API key is rejected (401)
- [ ] Request with invalid API key is rejected (401)
- [ ] Request with valid API key is accepted
- [ ] Last used timestamp updates

### Send Message Endpoint
- [ ] Valid request creates conversation
- [ ] Valid request creates messages
- [ ] Bot engine processes message
- [ ] Response includes conversation ID
- [ ] Response includes bot replies
- [ ] Invalid bot_id is rejected
- [ ] Missing required fields rejected
- [ ] Invalid JSON rejected

### Webhook Endpoint
- [ ] Valid webhook creates conversation
- [ ] Incoming message is processed
- [ ] Bot replies are generated
- [ ] Unknown channel is rejected
- [ ] Invalid payload is rejected

## Tenant Isolation Tests

### Data Segregation
- [ ] Tenant A cannot view Tenant B's bots
- [ ] Tenant A cannot view Tenant B's contacts
- [ ] Tenant A cannot view Tenant B's conversations
- [ ] Tenant A cannot view Tenant B's templates
- [ ] Tenant A cannot view Tenant B's broadcasts
- [ ] Tenant A cannot view Tenant B's webhooks

### Cross-Tenant Access Prevention
- [ ] Direct URL access to other tenant's resource returns null/404
- [ ] API key from Tenant A cannot access Tenant B's data
- [ ] Database queries always filter by tenant_id

## Security Tests

### SQL Injection
- [ ] Login form protected against SQL injection
- [ ] Search inputs protected
- [ ] Filter inputs protected
- [ ] All inputs use prepared statements

### XSS Protection
- [ ] User input is escaped in views
- [ ] JavaScript cannot be injected in forms
- [ ] HTML tags are sanitized

### CSRF Protection
- [ ] All POST forms include CSRF token
- [ ] Forms without token are rejected
- [ ] Token validation works correctly
- [ ] Token is session-specific

### Password Security
- [ ] Passwords are hashed with bcrypt
- [ ] Password hashes are not reversible
- [ ] Weak passwords are rejected

### File Upload Security
- [ ] File type validation works
- [ ] File size limit is enforced
- [ ] MIME type is verified
- [ ] Malicious files are rejected
- [ ] Directory traversal prevented

## Performance Tests

### Database Performance
- [ ] Queries use indexes effectively
- [ ] Pagination prevents full table scans
- [ ] JOINs are optimized
- [ ] N+1 query problems avoided

### Page Load Times
- [ ] Dashboard loads in < 2 seconds
- [ ] List pages load in < 2 seconds
- [ ] Search results appear quickly
- [ ] Large data sets are paginated

## Error Handling Tests

### Database Errors
- [ ] Connection errors handled gracefully
- [ ] Query errors don't expose SQL
- [ ] Transaction rollback works

### Validation Errors
- [ ] Clear error messages shown
- [ ] Multiple errors displayed
- [ ] Form retains values on error

### 404 Errors
- [ ] Non-existent pages show 404
- [ ] Invalid resource IDs handled

## Browser Compatibility

- [ ] Works in Chrome
- [ ] Works in Firefox
- [ ] Works in Safari
- [ ] Works in Edge
- [ ] Mobile responsive design works

## Overall System Tests

- [ ] Fresh installation works
- [ ] Database import succeeds
- [ ] Default credentials work
- [ ] All main features accessible
- [ ] No PHP errors in logs
- [ ] No JavaScript console errors
- [ ] SSL certificate valid (production)
- [ ] Security headers present

---

## Test Execution Summary

**Total Tests**: 250+

**Categories**:
- Authentication: 8 tests
- Tenant Management: 9 tests
- User Management: 9 tests
- Bot Management: 9 tests
- Channel Management: 7 tests
- Flows: 13 tests
- Knowledge Base: 7 tests
- Contacts: 9 tests
- Conversations: 13 tests
- Templates: 7 tests
- Broadcasts: 7 tests
- Webhooks: 7 tests
- Subscription & Billing: 16 tests
- Settings: 7 tests
- Public API: 10 tests
- Tenant Isolation: 8 tests
- Security: 11 tests
- Performance: 6 tests
- Error Handling: 6 tests
- Browser Compatibility: 5 tests
- System: 8 tests

---

**Test Environment Setup**:
1. Use fresh database installation
2. Import database.sql with seed data
3. Configure .env with test database
4. Run tests in isolation
5. Clean up test data after each test

**Automated Testing**:
- Run: `php tests/AuthTest.php`
- Run: `php tests/TenantIsolationTest.php`
- Add more test files as needed

**Manual Testing**:
- Use this checklist for manual verification
- Test each feature systematically
- Document any bugs found
- Verify fixes before deployment
