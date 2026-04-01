# MRAM SMS Module for WHMCS

Send SMS notifications to clients via **MRAM SMS Gateway** (msg.mram.com.bd) directly from your WHMCS admin panel. Developed By Codeisoft.com

## Features

- **Automated Notifications** — 19+ WHMCS events (billing, hosting, domain, support, client)
- **Bulk SMS** — Send to all clients, active clients, or custom numbers
- **Editable Templates** — Customize messages with dynamic variables per event
- **SMS Log** — Track all sent messages with status, error codes, and filters
- **Balance Check** — View your MRAM SMS balance from the dashboard
- **Delivery Reports** — Check DLR status via API
- **Bangla SMS** — Unicode support for Bengali messages
- **Admin Alerts** — Get notified when clients register, reply to tickets, etc.

## Installation

1. **Upload** the `mram_sms` folder to:
   ```
   /path/to/whmcs/modules/addons/mram_sms/
   ```

2. **Activate** the module:
   - Go to **WHMCS Admin → Setup → Addon Modules**
   - Find **MRAM SMS** and click **Activate**

3. **Configure** the module:
   - Enter your **API Key** (get from msg.mram.com.bd)
   - Enter your **Sender ID** (approved masking name)
   - Choose **SMS Type** (text or unicode for Bangla)
   - Set **Admin Phone** for alert notifications
   - Choose **SMS Label** (transactional or promotional)
   - Set **Country Code** (default: 88 for Bangladesh)

4. **Customize Templates**:
   - Go to **Addons → MRAM SMS → Templates**
   - Edit message templates for each event
   - Enable/disable individual notifications

## Module Structure

```
modules/addons/mram_sms/
├── mram_sms.php          # Main addon (config, activate, admin UI)
├── mram_sms_api.php      # API wrapper for msg.mram.com.bd
├── functions.php          # Helper functions
├── hooks.php              # Hook loader
├── hooks/                 # 19 individual hook files
│   ├── AcceptOrder.php
│   ├── InvoiceCreated.php
│   ├── InvoicePaid.php
│   ├── InvoicePaymentReminder.php
│   ├── AfterModuleCreate.php
│   ├── AfterModuleSuspend.php
│   ├── AfterModuleUnsuspend.php
│   ├── AfterModuleChangePassword.php
│   ├── AfterModuleChangePackage.php
│   ├── AfterRegistrarRegistration.php
│   ├── AfterRegistrarRenewal.php
│   ├── DomainRenewalReminder.php
│   ├── ClientAdd.php
│   ├── ClientAreaRegister.php
│   ├── ClientChangePassword.php
│   ├── TicketOpen.php
│   ├── TicketAdminReply.php
│   ├── TicketUserReply.php
│   └── TicketClose.php
└── README.md
```

## Template Variables

| Event | Variables |
|-------|-----------|
| Order Accepted | `{client_name}`, `{order_id}`, `{order_num}`, `{amount}` |
| Invoice Created | `{client_name}`, `{invoice_id}`, `{amount}`, `{currency}`, `{due_date}` |
| Invoice Paid | `{client_name}`, `{invoice_id}`, `{amount}`, `{currency}`, `{payment_method}` |
| Service Activated | `{client_name}`, `{service}`, `{domain}`, `{username}`, `{password}` |
| Service Suspended | `{client_name}`, `{service}`, `{domain}`, `{suspend_reason}` |
| Domain Registered | `{client_name}`, `{domain}`, `{expiry_date}`, `{registrar}` |
| Ticket Opened | `{client_name}`, `{ticket_id}`, `{subject}`, `{department}`, `{priority}` |
| Ticket Reply | `{client_name}`, `{ticket_id}`, `{subject}`, `{reply_message}` |
| Client Registered | `{client_name}`, `{email}`, `{whmcs_url}` |

## API Error Codes

| Code | Meaning |
|------|---------|
| 1002 | Sender Id/Masking Not Found |
| 1003 | API Not Found |
| 1007 | Balance Insufficient |
| 1008 | Message is empty |
| 1012 | Invalid Number |
| 1016 | IP address not allowed |

## Requirements

- WHMCS 7.x or 8.x
- PHP 7.2+
- cURL extension enabled
- Active MRAM SMS account (msg.mram.com.bd)

## Support

For API issues, contact MRAM SMS support at msg.mram.com.bd.

---
**Version:** 10.0.0
