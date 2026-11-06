<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $appName }} {{ $planName }}</title>
</head>
<body style="margin:0;padding:0;background:#14110a;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2b2416;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#14110a;padding:26px 12px;">
    <tr><td align="center">
        <table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;background:#fffdf7;border-radius:22px;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.45);">

            {{-- Hero --}}
            <tr><td align="center" style="background:#1d1708;background-image:linear-gradient(160deg,#2a1f08 0%,#120d03 60%,#3b2a00 140%);padding:34px 28px 38px;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" height="38" style="display:block;margin:0 auto 22px;height:38px;border:0;">
                @else
                    <div style="font-size:22px;font-weight:800;color:#ffe08a;letter-spacing:.5px;margin-bottom:22px;">{{ $appName }}</div>
                @endif
                <div style="display:inline-block;background:linear-gradient(135deg,#ffe08a,#c89b2e);color:#3b2a00;font-size:12px;font-weight:900;letter-spacing:2.2px;padding:7px 16px;border-radius:99px;">&#9733; {{ strtoupper($planName) }} MEMBER</div>
                <h1 style="margin:18px 0 8px;font-size:30px;line-height:1.2;color:#ffffff;font-weight:900;">Welcome to Gold, {{ $firstName }}! 🎉</h1>
                <p style="margin:0;font-size:15px;line-height:1.55;color:#e7dcc0;">Your membership is <strong style="color:#ffd76a;">active right now</strong>. Every order just got a little sweeter.</p>
            </td></tr>

            {{-- Membership card --}}
            <tr><td style="padding:0 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:-18px;background:linear-gradient(135deg,#ffe9a8,#e0b24a);border-radius:16px;box-shadow:0 8px 22px rgba(200,155,46,.35);">
                    <tr><td style="padding:18px 22px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:11px;letter-spacing:1.6px;font-weight:800;color:#6b4e07;text-transform:uppercase;">Plan</td>
                                <td align="right" style="font-size:11px;letter-spacing:1.6px;font-weight:800;color:#6b4e07;text-transform:uppercase;">Valid till</td>
                            </tr>
                            <tr>
                                <td style="font-size:22px;font-weight:900;color:#2b1d00;">{{ $planName }}</td>
                                <td align="right" style="font-size:22px;font-weight:900;color:#2b1d00;">{{ $validTill ?: '—' }}</td>
                            </tr>
                        </table>
                    </td></tr>
                </table>
            </td></tr>

            {{-- Benefits --}}
            <tr><td style="padding:28px 28px 6px;">
                <h2 style="margin:0 0 12px;font-size:18px;font-weight:900;color:#2b2416;">What you get with Gold</h2>
                @foreach($benefits as $b)
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:9px;">
                        <tr>
                            <td width="30" valign="top"><div style="width:22px;height:22px;border-radius:50%;background:#c89b2e;color:#fff;font-size:13px;line-height:22px;text-align:center;font-weight:900;">&#10003;</div></td>
                            <td style="font-size:14.5px;line-height:1.5;color:#3d3425;">{{ $b }}</td>
                        </tr>
                    </table>
                @endforeach
            </td></tr>

            {{-- Receipt --}}
            <tr><td style="padding:18px 28px 6px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #efe4c4;border-radius:12px;background:#fffaf0;">
                    <tr><td style="padding:14px 18px;">
                        <div style="font-size:12px;font-weight:800;letter-spacing:1.4px;color:#8a6a1c;text-transform:uppercase;margin-bottom:8px;">Membership details</div>
                        @foreach(array_filter([
                            'Started on' => $startsOn,
                            'Valid till' => $validTill,
                            'Amount paid' => $amountPaid,
                            'Payment reference' => $paymentRef,
                            'Granted by' => $grantedBy,
                        ]) as $label => $value)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                                <td style="padding:5px 0;font-size:13.5px;color:#6b5f45;">{{ $label }}</td>
                                <td align="right" style="padding:5px 0;font-size:13.5px;font-weight:700;color:#2b2416;word-break:break-all;">{{ $value }}</td>
                            </tr></table>
                        @endforeach
                    </td></tr>
                </table>
            </td></tr>

            {{-- CTA --}}
            <tr><td align="center" style="padding:26px 28px 30px;">
                <a href="{{ $appUrl }}" style="display:inline-block;background:linear-gradient(135deg,#ffd76a,#c89b2e);color:#2b1d00;font-weight:900;font-size:15px;text-decoration:none;padding:14px 34px;border-radius:99px;box-shadow:0 6px 16px rgba(200,155,46,.4);">Start ordering &rarr;</a>
                <p style="margin:14px 0 0;font-size:12.5px;color:#8a7f66;">Open the {{ $appName }} app and look for the Gold badge at checkout.</p>
            </td></tr>

            {{-- Footer --}}
            <tr><td style="background:#1d1708;padding:20px 28px;text-align:center;">
                <p style="margin:0 0 6px;font-size:12.5px;color:#cdbf98;">
                    Need help?@if($supportPhone) Call <strong style="color:#ffd76a;">{{ $supportPhone }}</strong>@endif
                    @if($supportEmail) {{ $supportPhone ? 'or write to' : 'Write to' }} <a href="mailto:{{ $supportEmail }}" style="color:#ffd76a;text-decoration:none;font-weight:700;">{{ $supportEmail }}</a>@endif
                </p>
                <p style="margin:0;font-size:11px;color:#8d8160;">You are receiving this because you joined {{ $appName }} Gold. Free delivery and other benefits are subject to the membership terms shown in the app.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
