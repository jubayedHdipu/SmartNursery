<?php
// Smart Nursery Ecosystem - Unified SMTP Mailer Service
// High-reliability native SMTP socket implementation with HTML templating

class Mailer {
    private static ?array $configCache = null;

    /**
     * Retrieve merged mail configuration (File defaults + Database settings overrides)
     */
    public static function getConfig(): array {
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $defaultConfigFile = __DIR__ . '/../config/mail.php';
        $config = file_exists($defaultConfigFile) ? require $defaultConfigFile : [];

        // Attempt to load live overrides from database system_settings
        try {
            if (function_exists('get_db')) {
                $db = get_db();
                $keys = [
                    'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 
                    'smtp_pass', 'smtp_from_email', 'smtp_from_name', 
                    'smtp_notify_login', 'smtp_notify_register'
                ];
                $placeholders = implode(',', array_fill(0, count($keys), '?'));
                $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)");
                $stmt->execute($keys);
                $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                if (!empty($dbSettings['smtp_host'])) $config['smtp_host'] = $dbSettings['smtp_host'];
                if (!empty($dbSettings['smtp_port'])) $config['smtp_port'] = (int)$dbSettings['smtp_port'];
                if (!empty($dbSettings['smtp_secure'])) $config['smtp_secure'] = $dbSettings['smtp_secure'];
                if (!empty($dbSettings['smtp_user'])) $config['smtp_user'] = $dbSettings['smtp_user'];
                if (!empty($dbSettings['smtp_pass'])) $config['smtp_pass'] = $dbSettings['smtp_pass'];
                if (!empty($dbSettings['smtp_from_email'])) $config['from_email'] = $dbSettings['smtp_from_email'];
                if (!empty($dbSettings['smtp_from_name'])) $config['from_name'] = $dbSettings['smtp_from_name'];
                if (isset($dbSettings['smtp_notify_login'])) $config['notify_on_login'] = (bool)$dbSettings['smtp_notify_login'];
                if (isset($dbSettings['smtp_notify_register'])) $config['notify_on_register'] = (bool)$dbSettings['smtp_notify_register'];
            }
        } catch (Throwable $e) {
            // Silently fall back to config/mail.php
        }

        self::$configCache = $config;
        return $config;
    }

    /**
     * Clear config cache (useful when settings are updated)
     */
    public static function clearCache(): void {
        self::$configCache = null;
    }

    /**
     * Helper to read multi-line SMTP server replies
     */
    private static function readReply($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $data .= $line;
            // In RFC 821/2821, the last line of a multi-line response has a space at index 3 (e.g. '250 ')
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    /**
     * Core SMTP Socket Transmission Engine
     * Supports Plain, STARTTLS (Port 587), and SSL/TLS (Port 465)
     */
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText = ''): array {
        $toEmail = trim($toEmail);
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid recipient email address: {$toEmail}"];
        }

        $config = self::getConfig();
        $host     = $config['smtp_host'] ?? 'mail.rifatxtra.com';
        $port     = (int)($config['smtp_port'] ?? 587);
        $secure   = strtolower($config['smtp_secure'] ?? 'tls');
        $user     = $config['smtp_user'] ?? '';
        $pass     = $config['smtp_pass'] ?? '';
        $fromMail = $config['from_email'] ?? $user;
        $fromName = $config['from_name'] ?? 'Smart Nursery Ecosystem';
        $timeout  = (int)($config['timeout'] ?? 8);

        $targetHost = ($secure === 'ssl' || $port === 465) ? "ssl://{$host}" : $host;

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client("{$targetHost}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            $msg = "SMTP Connection failed to {$host}:{$port} - {$errstr} ({$errno})";
            error_log("[Mailer Error] " . $msg);
            return ['success' => false, 'error' => $msg];
        }

        stream_set_timeout($socket, $timeout);

        try {
            // 1. Initial Greeting
            $greeting = self::readReply($socket);
            if (!str_starts_with($greeting, '220')) {
                throw new Exception("Bad greeting response: " . trim($greeting));
            }

            // 2. EHLO
            $clientHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
            fwrite($socket, "EHLO {$clientHost}\r\n");
            $ehloResp = self::readReply($socket);
            if (!str_starts_with($ehloResp, '250')) {
                throw new Exception("EHLO rejected: " . trim($ehloResp));
            }

            // 3. STARTTLS Upgrade if using TLS (Port 587)
            if ($secure === 'tls' || $port === 587) {
                fwrite($socket, "STARTTLS\r\n");
                $tlsResp = self::readReply($socket);
                if (!str_starts_with($tlsResp, '220')) {
                    throw new Exception("STARTTLS rejected: " . trim($tlsResp));
                }

                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    throw new Exception("Failed to establish TLS encryption handshake.");
                }

                // Re-send EHLO over secure tunnel
                fwrite($socket, "EHLO {$clientHost}\r\n");
                $ehloResp2 = self::readReply($socket);
                if (!str_starts_with($ehloResp2, '250')) {
                    throw new Exception("Secure EHLO rejected: " . trim($ehloResp2));
                }
            }

            // 4. Authenticate
            if (!empty($config['smtp_auth'])) {
                fwrite($socket, "AUTH LOGIN\r\n");
                $authPrompt = self::readReply($socket);
                if (!str_starts_with($authPrompt, '334')) {
                    throw new Exception("AUTH LOGIN rejected: " . trim($authPrompt));
                }

                fwrite($socket, base64_encode($user) . "\r\n");
                $userPrompt = self::readReply($socket);
                if (!str_starts_with($userPrompt, '334')) {
                    throw new Exception("Username rejected: " . trim($userPrompt));
                }

                fwrite($socket, base64_encode($pass) . "\r\n");
                $passResp = self::readReply($socket);
                if (!str_starts_with($passResp, '235')) {
                    throw new Exception("SMTP Authentication failed: " . trim($passResp));
                }
            }

            // 5. Envelope From
            fwrite($socket, "MAIL FROM:<{$fromMail}>\r\n");
            $fromResp = self::readReply($socket);
            if (!str_starts_with($fromResp, '250')) {
                throw new Exception("MAIL FROM rejected: " . trim($fromResp));
            }

            // 6. Envelope To
            fwrite($socket, "RCPT TO:<{$toEmail}>\r\n");
            $toResp = self::readReply($socket);
            if (!str_starts_with($toResp, '250')) {
                throw new Exception("RCPT TO rejected: " . trim($toResp));
            }

            // 7. Data Handshake
            fwrite($socket, "DATA\r\n");
            $dataPrompt = self::readReply($socket);
            if (!str_starts_with($dataPrompt, '354')) {
                throw new Exception("DATA command rejected: " . trim($dataPrompt));
            }

            // 8. Headers & Body Construction
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $encodedToName = !empty($toName) ? '=?UTF-8?B?' . base64_encode($toName) . '?=' : '';

            $headers = [];
            $headers[] = "Date: " . date('r');
            $headers[] = "From: {$encodedFromName} <{$fromMail}>";
            $headers[] = !empty($encodedToName) ? "To: {$encodedToName} <{$toEmail}>" : "To: <{$toEmail}>";
            $headers[] = "Reply-To: <" . ($config['reply_to'] ?? $fromMail) . ">";
            $headers[] = "Subject: {$encodedSubject}";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "X-Mailer: SmartNursery-Mailer/1.0";
            
            // Build multipart alternative for HTML + PlainText fallback
            $boundary = "----=_NextPart_" . md5(uniqid((string)time(), true));
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $messageBody = "";
            
            // Plaintext fallback section
            $plainTextBody = !empty($plainText) ? $plainText : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
            $messageBody .= "--{$boundary}\r\n";
            $messageBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageBody .= chunk_split(base64_encode($plainTextBody)) . "\r\n";

            // HTML section
            $messageBody .= "--{$boundary}\r\n";
            $messageBody .= "Content-Type: text/html; charset=UTF-8\r\n";
            $messageBody .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $messageBody .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            $messageBody .= "--{$boundary}--\r\n";

            // Complete payload
            $fullMessage = implode("\r\n", $headers) . "\r\n\r\n" . $messageBody . "\r\n.\r\n";
            fwrite($socket, $fullMessage);

            $sendResp = self::readReply($socket);
            if (!str_starts_with($sendResp, '250')) {
                throw new Exception("Message transmission rejected: " . trim($sendResp));
            }

            // 9. Quit
            fwrite($socket, "QUIT\r\n");
            self::readReply($socket);
            fclose($socket);

            return [
                'success' => true,
                'message' => 'Email transmitted and queued successfully by SMTP server.',
                'server_response' => trim($sendResp)
            ];

        } catch (Throwable $e) {
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            error_log("[Mailer Error] " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Master Responsive HTML Email Template with Botanical Aesthetics
     */
    public static function buildEmailTemplate(string $title, string $previewText, string $contentHtml, ?array $actionButton = null): string {
        $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/nursery';
        $appName = defined('APP_NAME') ? APP_NAME : 'Smart Nursery Ecosystem';
        $year = date('Y');

        $buttonHtml = '';
        if ($actionButton && !empty($actionButton['url']) && !empty($actionButton['text'])) {
            $btnUrl  = htmlspecialchars($actionButton['url']);
            $btnText = htmlspecialchars($actionButton['text']);
            $buttonHtml = "
                <table border='0' cellpadding='0' cellspacing='0' style='margin: 28px 0;'>
                    <tr>
                        <td align='center' style='border-radius: 12px; background-color: #059669;'>
                            <a href='{$btnUrl}' target='_blank' style='font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 12px; padding: 14px 28px; border: 1px solid #059669; display: inline-block; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);'>
                                {$btnText} &rarr;
                            </a>
                        </td>
                    </tr>
                </table>
            ";
        }

        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($title) . "</title>
            <style>
                body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
                table { border-collapse: collapse; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.04); }
            </style>
        </head>
        <body style='margin: 0; padding: 32px 12px; background-color: #f8fafc;'>
            <div style='display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;'>
                " . htmlspecialchars($previewText) . "
            </div>

            <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr>
                    <td align='center'>
                        <table class='email-container' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 24px; border: 1px solid #e2e8f0;'>
                            
                            <!-- Header Banner -->
                            <tr>
                                <td style='padding: 32px 36px; background: linear-gradient(135deg, #064e3b 0%, #047857 100%); text-align: left;'>
                                    <table border='0' cellpadding='0' cellspacing='0'>
                                        <tr>
                                            <td style='vertical-align: middle; padding-right: 12px;'>
                                                <div style='width: 42px; height: 42px; background: rgba(255,255,255,0.15); border-radius: 12px; text-align: center; line-height: 42px; font-size: 22px; color: #a7f3d0;'>
                                                    🌿
                                                </div>
                                            </td>
                                            <td style='vertical-align: middle;'>
                                                <span style='font-size: 20px; font-weight: 900; color: #ffffff; letter-spacing: -0.5px;'>Smart<span style='color: #6ee7b7;'>Nursery</span></span>
                                                <div style='font-size: 11px; color: #d1fae5; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;'>Botanical Ecosystem</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <!-- Body Content -->
                            <tr>
                                <td style='padding: 36px 36px 28px 36px; color: #334155; font-size: 15px; line-height: 1.65;'>
                                    <h1 style='margin: 0 0 16px 0; font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;'>
                                        " . htmlspecialchars($title) . "
                                    </h1>
                                    
                                    {$contentHtml}

                                    {$buttonHtml}
                                </td>
                            </tr>

                            <!-- Security / Info Notice Box -->
                            <tr>
                                <td style='padding: 0 36px 28px 36px;'>
                                    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; font-size: 12px; color: #64748b; line-height: 1.5;'>
                                        🔒 <strong>Security Notice:</strong> If you did not initiate this request or notice suspicious activity, please log in and change your password, or contact our support desk immediately.
                                    </div>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style='padding: 24px 36px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8;'>
                                    <p style='margin: 0 0 8px 0; font-weight: 600; color: #64748b;'>{$appName}</p>
                                    <p style='margin: 0 0 8px 0;'>Curated Botanical Wonders • Smart Plant Care • Trusted Local Nurseries</p>
                                    <p style='margin: 0;'>&copy; {$year} {$appName}. All rights reserved.</p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * 1. Send Welcome Email upon Registration
     */
    public static function sendWelcomeEmail(array $user): array {
        $config = self::getConfig();
        if (empty($config['notify_on_register'])) {
            return ['success' => true, 'message' => 'Registration notification skipped (disabled in settings).'];
        }

        $baseUrl     = defined('BASE_URL') ? BASE_URL : 'http://localhost/nursery';
        $name        = $user['name'] ?? 'Gardening Enthusiast';
        $email       = $user['email'] ?? '';
        $role        = strtolower($user['role'] ?? 'customer');
        $nurseryName = $user['nursery_name'] ?? '';

        if ($role === 'vendor') {
            $subject     = "🌱 Welcome to Smart Nursery Partner Network - Registration Received!";
            $previewText = "Thank you for registering your nursery on Smart Nursery Ecosystem.";
            $contentHtml = "
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>Thank you for applying to partner with <strong>Smart Nursery Ecosystem</strong> as an authorized botanical vendor!</p>
                
                <div style='background: #ecfdf5; border-left: 4px solid #059669; padding: 14px 16px; border-radius: 8px; margin: 20px 0;'>
                    <div style='font-size: 13px; font-weight: 700; color: #065f46; margin-bottom: 4px;'>Partner Application Details:</div>
                    <div style='font-size: 13px; color: #047857;'>• <strong>Nursery Name:</strong> " . htmlspecialchars($nurseryName ?: 'Specialty Nursery') . "</div>
                    <div style='font-size: 13px; color: #047857;'>• <strong>Registered Email:</strong> " . htmlspecialchars($email) . "</div>
                    <div style='font-size: 13px; color: #047857;'>• <strong>Current Status:</strong> Pending Verification</div>
                </div>

                <p>Our administration team is currently reviewing your nursery credentials and inventory catalog. Once verified and approved, you will receive an activation email and full access to your vendor management portal where you can list products, track orders, and view payouts.</p>
            ";
            $actionBtn = [
                'text' => 'Go to Vendor Login',
                'url'  => $baseUrl . '/login.php'
            ];
        } else {
            $subject     = "🌿 Welcome to Smart Nursery Ecosystem, " . htmlspecialchars($name) . "!";
            $previewText = "Your green sanctuary awaits. Discover curated plants, smart care, and local nurseries.";
            $contentHtml = "
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>Welcome to <strong>Smart Nursery Ecosystem</strong>! We are thrilled to have you join our thriving community of plant lovers and urban garden enthusiasts.</p>
                
                <p>Here is what you can explore with your new account:</p>
                <ul style='padding-left: 20px; color: #334155;'>
                    <li style='margin-bottom: 8px;'><strong>Curated Botanical Collection:</strong> Browse indoor plants, flowering varieties, bonsai, herbs, and air purifiers.</li>
                    <li style='margin-bottom: 8px;'><strong>Smart Plant Matcher:</strong> Get instant personalized plant recommendations tailored to your room sunlight, pet-friendly requirements, and care experience.</li>
                    <li style='margin-bottom: 8px;'><strong>Local Specialty Nurseries:</strong> Support top-rated local growers with fast, door-to-door delivery.</li>
                </ul>

                <p style='margin-top: 16px;'>Your registered login email is: <code>" . htmlspecialchars($email) . "</code></p>
            ";
            $actionBtn = [
                'text' => 'Explore the Botanical Shop',
                'url'  => $baseUrl . '/shop.php'
            ];
        }

        $fullHtml = self::buildEmailTemplate($subject, $previewText, $contentHtml, $actionBtn);
        return self::send($email, $name, $subject, $fullHtml);
    }

    /**
     * 2. Send Security Login Alert Email
     */
    public static function sendLoginNotification(array $loginData): array {
        $config = self::getConfig();
        if (empty($config['notify_on_login'])) {
            return ['success' => true, 'message' => 'Login notification skipped (disabled in settings).'];
        }

        $baseUrl   = defined('BASE_URL') ? BASE_URL : 'http://localhost/nursery';
        $name      = $loginData['name'] ?? 'User';
        $email     = $loginData['email'] ?? '';
        $roleName  = $loginData['role_display'] ?? $loginData['role_name'] ?? 'Member';
        $ip        = $loginData['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = $loginData['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device');
        $time      = $loginData['time'] ?? date('F j, Y - g:i A T');

        $subject     = "🔐 Security Alert: New Login to Your Smart Nursery Account";
        $previewText = "A successful sign-in was detected on your Smart Nursery account.";

        // Simplify user agent for friendly display
        $friendlyDevice = 'Desktop / Web Browser';
        if (stripos($userAgent, 'Mobile') !== false || stripos($userAgent, 'Android') !== false || stripos($userAgent, 'iPhone') !== false) {
            $friendlyDevice = 'Mobile Device';
        }

        $contentHtml = "
            <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
            <p>We detected a new successful sign-in to your <strong>Smart Nursery</strong> account.</p>

            <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; margin: 20px 0;'>
                <table border='0' cellpadding='4' cellspacing='0' width='100%' style='font-size: 13px;'>
                    <tr>
                        <td style='color: #64748b; width: 110px;'><strong>Date & Time:</strong></td>
                        <td style='color: #0f172a; font-weight: 600;'>" . htmlspecialchars($time) . "</td>
                    </tr>
                    <tr>
                        <td style='color: #64748b;'><strong>Account Role:</strong></td>
                        <td style='color: #0f172a; font-weight: 600;'>" . htmlspecialchars(ucfirst($roleName)) . "</td>
                    </tr>
                    <tr>
                        <td style='color: #64748b;'><strong>IP Address:</strong></td>
                        <td style='color: #0f172a; font-family: monospace;'>" . htmlspecialchars($ip) . "</td>
                    </tr>
                    <tr>
                        <td style='color: #64748b;'><strong>Device Type:</strong></td>
                        <td style='color: #0f172a;'>" . htmlspecialchars($friendlyDevice) . "</td>
                    </tr>
                </table>
            </div>

            <p style='font-size: 13px; color: #475569;'>If this was you, no action is needed and you may safely disregard this message.</p>
            <p style='font-size: 13px; color: #e11d48; font-weight: 600;'>If you did NOT perform this login, please secure your account immediately.</p>
        ";

        $actionBtn = [
            'text' => 'Go to Your Account',
            'url'  => $baseUrl . '/login.php'
        ];

        $fullHtml = self::buildEmailTemplate($subject, $previewText, $contentHtml, $actionBtn);
        return self::send($email, $name, $subject, $fullHtml);
    }

    /**
     * 3. Send Diagnostic / Test Email
     */
    public static function sendTestEmail(string $toEmail): array {
        $subject     = "🧪 Smart Nursery SMTP Delivery Test";
        $previewText = "Your outbound SMTP email server configuration is active and working.";
        $time        = date('F j, Y - g:i:s A T');
        $config      = self::getConfig();

        $contentHtml = "
            <p>Congratulations! Your outgoing SMTP mail server integration is successfully connected and operational.</p>
            
            <div style='background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 16px; margin: 20px 0;'>
                <h4 style='margin: 0 0 10px 0; color: #065f46;'>Active Mail Server Diagnostics:</h4>
                <ul style='margin: 0; padding-left: 20px; font-size: 13px; color: #047857;'>
                    <li><strong>SMTP Host:</strong> " . htmlspecialchars($config['smtp_host'] ?? '') . "</li>
                    <li><strong>SMTP Port:</strong> " . htmlspecialchars((string)($config['smtp_port'] ?? '')) . " (" . strtoupper($config['smtp_secure'] ?? 'TLS') . ")</li>
                    <li><strong>Sender Mailbox:</strong> " . htmlspecialchars($config['from_email'] ?? '') . "</li>
                    <li><strong>Sender Display:</strong> " . htmlspecialchars($config['from_name'] ?? '') . "</li>
                    <li><strong>Timestamp:</strong> {$time}</li>
                </ul>
            </div>
            
            <p>Registration onboarding emails and login security alerts will be delivered automatically using this verified channel.</p>
        ";

        $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/nursery';
        $actionBtn = [
            'text' => 'Open System Settings',
            'url'  => $baseUrl . '/owner/settings.php'
        ];

        $fullHtml = self::buildEmailTemplate($subject, $previewText, $contentHtml, $actionBtn);
        return self::send($toEmail, 'System Administrator', $subject, $fullHtml);
    }
}
