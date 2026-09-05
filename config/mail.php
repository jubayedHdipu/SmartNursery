<?php
// Smart Nursery Ecosystem - Mail & SMTP Configuration
// Clean, secure, and configurable email parameters

return [
    // Outgoing Mail Server (SMTP) Settings
    'smtp_host'          => 'mail.rifatxtra.com',
    'smtp_port'          => 587,
    'smtp_secure'        => 'tls', // 'tls' for STARTTLS (Port 587), 'ssl' for SMTPS (Port 465)
    'smtp_auth'          => true,
    'smtp_user'          => 'business@jubayeddipu.online',
    'smtp_pass'          => '@@teamX@@420',
    'from_email'         => 'business@jubayeddipu.online',
    'from_name'          => 'Smart Nursery Ecosystem',
    'reply_to'           => 'business@jubayeddipu.online',
    
    // Incoming Mail Server (IMAP Reference - for documentation/client use)
    'imap_host'          => 'mail.rifatxtra.com',
    'imap_port'          => 993,
    'imap_secure'        => 'ssl',

    // Automation switches
    'notify_on_login'    => true,  // Send login security alert
    'notify_on_register' => true,  // Send welcome / onboarding email

    // Socket timeout in seconds
    'timeout'            => 8
];
