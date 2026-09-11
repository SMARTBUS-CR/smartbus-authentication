<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? __('passwords.mail_subject') }}</title>
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <style>
        /* Base Resets */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #334155; }

        /* Responsive Styles */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: auto !important;
            }
            .content-padding {
                padding: 24px 20px !important;
            }
            .code-box {
                font-size: 28px !important;
                letter-spacing: 6px !important;
                padding: 14px 16px !important;
            }
            .header-padding {
                padding: 24px 20px 16px 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">

    <!-- Main Centered Container -->
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f1f5f9; padding: 32px 12px;">
        <tr>
            <td align="center">
                
                <!-- Email Card -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width: 540px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
                    
                    <!-- Header with Branding -->
                    <tr>
                        <td align="center" class="header-padding" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 36px 32px 28px 32px; text-align: center;">
                            <!-- Icon / Logo -->
                            <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 12px; margin-bottom: 12px;">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center" valign="middle">
                                            <!-- SVG Icon Lock / Security -->
                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <rect x="5" y="11" width="14" height="10" rx="2" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M8 11V7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7V11" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                <circle cx="12" cy="16" r="1.5" fill="#38bdf8"/>
                                            </svg>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: -0.02em;">
                                {{ config('app.name', 'SmartBus') }}
                            </h1>
                            <p style="margin: 6px 0 0 0; color: #94a3b8; font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;">
                                {{ __('passwords.mail_header_subtitle') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td class="content-padding" style="padding: 36px 32px;">
                            
                            <!-- Greeting -->
                            <h2 style="margin: 0 0 12px 0; color: #0f172a; font-size: 18px; font-weight: 600; line-height: 1.4;">
                                @if(!empty($userName))
                                    {{ __('passwords.mail_greeting', ['name' => $userName]) }}
                                @else
                                    {{ __('passwords.mail_greeting_general') }}
                                @endif
                            </h2>

                            <!-- Explanation Message -->
                            <p style="margin: 0 0 20px 0; color: #475569; font-size: 15px; line-height: 1.6;">
                                {{ __('passwords.mail_intro', ['app' => config('app.name', 'SmartBus')]) }}
                            </p>

                            <!-- OTP Code -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 28px 0;">
                                <tr>
                                    <td align="center" style="background-color: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px;">
                                        <span style="display: block; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px;">
                                            {{ __('passwords.mail_code_label') }}
                                        </span>
                                        <div class="code-box" style="font-family: ui-monospace, 'SF Mono', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 34px; font-weight: 700; color: #0284c7; letter-spacing: 8px; line-height: 1; user-select: all; -webkit-user-select: all;">
                                            {{ $code }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Expiration Alert -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 12px 16px;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td valign="top" style="padding-right: 8px; font-size: 14px; line-height: 1.4;">
                                                    ⏱
                                                </td>
                                                <td style="color: #92400e; font-size: 13px; line-height: 1.5; font-weight: 500;">
                                                    {!! __('passwords.mail_expiration', ['minutes' => 15]) !!}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Security Note -->
                            <div style="background-color: #f8fafc; border-radius: 8px; padding: 14px 16px; border: 1px solid #f1f5f9;">
                                <p style="margin: 0; color: #64748b; font-size: 13px; line-height: 1.5;">
                                    🔒 <strong>{{ __('passwords.mail_security_title') }}</strong> {{ __('passwords.mail_security_text') }}
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- Page Footer -->
                    <tr>
                        <td align="center" style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 24px 32px; text-align: center;">
                            <p style="margin: 0 0 6px 0; color: #94a3b8; font-size: 12px; line-height: 1.4;">
                                {{ __('passwords.mail_footer_automated') }}
                            </p>
                            <p style="margin: 0; color: #cbd5e1; font-size: 12px;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'SmartBus') }}. {{ __('passwords.mail_all_rights_reserved') }}
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- Card End -->

            </td>
        </tr>
    </table>

</body>
</html>
