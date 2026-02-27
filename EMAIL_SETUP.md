# 📧 EMAIL SETUP GUIDE

## Quick Start

### 1. Gmail Configuration (5 minutes)

#### Step 1: Enable 2-Factor Authentication
1. Go to: https://myaccount.google.com/security
2. Find "2-Step Verification"
3. Click "Enable 2-Step Verification"
4. Follow prompts (you'll need your phone)

#### Step 2: Create App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select:
   - App: **Mail**
   - Device: **Windows Computer** (or your device)
3. Click **Generate**
4. **Copy** the 16-character password shown
5. **Save** this password somewhere secure

### 2. CMS Configuration (2 minutes)

1. Log in as **Super Admin**
2. Navigate to: **Admin** → **Settings**
3. Fill in SMTP section:
   ```
   SMTP Host: smtp.gmail.com
   SMTP Port: 587
   Gmail Address: your-email@gmail.com
   App Password: [paste 16-char password from step above]
   From Email: your-email@gmail.com
   From Name: Complaint Management System
   ```
4. Click **Save Settings**
5. Done! ✅

---

## Email System Details

### What Gets Emailed?

| Event | Recipient | Purpose | When |
|-------|-----------|---------|------|
| **Registration** | New user email | OTP code | After clicking Register |
| **OTP Resend** | User email | New OTP code | If they click "Resend OTP" |
| **New Complaint** | Customer email | Confirmation + ticket # | After submitting complaint |
| **Status Update** | Customer email | Notification of change | When staff updates status |

### Email Sending Flow

```
User Action
    ↓
PHP Validates Input
    ↓
Database Saves Record
    ↓
Mailer::send() Called
    ↓
Check if SMTP Configured
    ├─ YES → Send via Gmail SMTP
    │         ↓
    │    Email Delivered ✅
    │
    └─ NO → Skip Silently
           ↓
      App Still Works (no error)
```

### Templates Included

#### 1️⃣ OTP Email
```
Subject: Your Verification Code
To: customer@example.com

Hi [Name],

Your verification code is: 123456

This code expires in 10 minutes.

Don't share this code with anyone.

— Complaint Management System
```

#### 2️⃣ Ticket Confirmation Email
```
Subject: Ticket TKT-20260225-00001 Submitted
To: customer@example.com

Hi [Name],

Your complaint has been received.

Ticket Number: TKT-20260225-00001
Priority: High
Bank: XYZ Bank
Status: Pending

We'll review and respond shortly.

— Complaint Management System
```

#### 3️⃣ Status Update Email
```
Subject: Ticket TKT-20260225-00001 Status Updated
To: customer@example.com

Hi [Name],

Your ticket has been updated.

Status Changed To: In Progress

Our team is working on your issue.

Ticket: TKT-20260225-00001

— Complaint Management System
```

---

## Testing Email Delivery

### Test 1: Registration OTP
1. Open login page (not logged in)
2. Click "Register" tab
3. Fill in a test email you can access
4. Click "Register"
5. Check your email for OTP code ✉️
6. Enter OTP on verify page

### Test 2: Complaint Submission
1. Log in as customer
2. Click "New Complaint"
3. Fill out form and submit
4. Check customer email for confirmation ✉️

### Test 3: Status Update
1. Log in as bank staff
2. View assigned ticket
3. Change status to "In Progress"
4. Customer email should arrive ✉️

---

## Troubleshooting

### ❌ "SMTP not configured"
**Error appears when**: Trying to send email but settings not saved

**Solution**:
1. Go to Admin → Settings
2. Fill all SMTP fields
3. Click Save
4. Try action again

### ❌ "Connection refused" or "503 Service Unavailable"
**Error appears when**: Network blocked or Gmail not responding

**Solution**:
1. Check internet connection
2. Verify port 587 not blocked (contact IT)
3. Check Gmail account not locked (check Gmail login page)
4. Try again after 5 minutes

### ❌ "Invalid email or password"
**Error appears when**: Wrong App Password

**Solution**:
1. Go to: https://myaccount.google.com/apppasswords
2. Delete old password
3. Generate new 16-character password
4. Update CMS Settings
5. Save and test again

### ❌ "Emails work but look blank"
**Error appears when**: HTML rendering issue

**Solution**:
1. Check email client supports HTML
2. Open in Gmail web (not mobile app)
3. Email client might block HTML images
4. Not a system problem — emails sent correctly

### ✅ Emails not showing?
**Check 1**: Verify Spam folder
- Email clients may filter system emails
- Whitelist: your-email@gmail.com in email client

**Check 2**: Confirm email was sent
1. Log in as Super Admin
2. Go to Admin → Activity Logs
3. Look for "Email sent" entries
4. If not there, check SMTP settings

---

## Security Notes

### ✅ Good Practices
- ✅ Use Gmail App Password (not real Gmail password)
- ✅ Never share SMTP credentials
- ✅ Keep password in settings (database encrypted)
- ✅ Rotate App Password every 90 days (optional)

### ✅ Data Protection
- ✅ Emails logged with timestamp
- ✅ Customer emails masked in logs
- ✅ No email content stored in database
- ✅ OTP expires after 10 minutes

### ❌ Don't Do This
- ❌ Don't use your real Gmail password
- ❌ Don't share SMTP settings publicly
- ❌ Don't put settings in code/comments
- ❌ Don't hardcode email addresses

---

## Advanced Configuration

### Using Different Email Provider

Mailer supports any SMTP provider. To use:

1. Go to Admin → Settings
2. Change SMTP Host (e.g., `smtp.outlook.com`)
3. Change SMTP Port if needed (usually 587)
4. Enter provider's email and password
5. Save and test

**Popular Providers:**
- Gmail: `smtp.gmail.com:587`
- Outlook: `smtp-mail.outlook.com:587`
- AWS SES: `email-smtp.[region].amazonaws.com:587`
- SendGrid: `smtp.sendgrid.net:587`

### Custom Email Templates

To modify templates, edit:
- `includes/mailer.php` → methods:
  - `sendOTP()` (line 214)
  - `sendTicketConfirm()` (line 230)
  - `sendStatusUpdate()` (line 250)

Modify HTML in these methods to change email design.

---

## Status Check Commands

### Check If SMTP Configured
1. Go to Admin → Settings
2. If SMTP fields filled → Configured ✅
3. If SMTP fields empty → Not configured ❌

### Check SMTP Connection
1. Go to Admin → Settings
2. Fill all SMTP fields
3. Click any form action that sends email
4. If email arrives → Connection working ✅
5. If email doesn't arrive → Check settings

### Check Email Logs
1. Go to Admin → Activity Logs
2. Filter for "email" or "send"
3. See timestamps of sent emails
4. Verify customer received them

---

## FAQ

**Q: Can I use free Gmail?**  
A: Yes! Gmail App Password works with free accounts.

**Q: How many emails can I send per day?**  
A: Gmail rate limit: ~500 emails/day from free account.

**Q: What if customer doesn't receive email?**  
A: Check:
1. Spam folder
2. Email address correct
3. SMTP settings correct
4. Internet connection stable

**Q: Can I send to multiple recipients?**  
A: Current system sends one-to-one. For bulk email, modify Mailer::send() method.

**Q: What if Gmail blocks access?**  
A: Gmail may show "Less secure app" warning:
1. Click warning link
2. Allow access
3. Or use App Password method (recommended)

**Q: Can I test without actually sending?**  
A: Leave SMTP fields empty. System will skip silently (no error).

---

## Verification Checklist

Before going live, verify:
- [ ] SMTP settings saved in Admin Settings
- [ ] Test registration OTP received
- [ ] Test complaint confirmation received
- [ ] Test status update notification received
- [ ] Check Admin → Logs show email activities
- [ ] Verify no emails in spam folder
- [ ] Confirm all 3 email templates working

---

**Last Updated**: February 25, 2026  
**Status**: ✅ Email system ready for production  
**Support**: Check Admin → Activity Logs for email delivery details
